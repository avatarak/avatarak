<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Support\Facades\DB;

/**
 * One-time sign-in codes sent by email: 6 digits, valid for 10 minutes, at most 5 wrong guesses,
 * single use. Only an HMAC of the code is stored, so a database leak does not reveal usable codes.
 */
class LoginCodeService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    /** Wrong guesses allowed per address per hour across ALL codes, so asking for fresh codes does not buy fresh guesses. */
    public const HOURLY_GUESSES = 15;

    /** The account that owns this address: its sign-up email, or any email it has verified. */
    public function findUser(string $email): ?User
    {
        $email = UserEmail::normalize($email);

        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }

        $row = UserEmail::where('email_hash', UserEmail::hash($email))->whereNotNull('verified_at')->first();

        return $row?->user;
    }

    /** Issues a fresh code, or null when one was sent less than RESEND_SECONDS ago. */
    public function issue(string $email): ?string
    {
        $hash = UserEmail::hash($email);

        $recent = DB::table('login_codes')->where('email_hash', $hash)
            ->where('created_at', '>', now()->subSeconds(self::RESEND_SECONDS))->exists();
        if ($recent) {
            return null;
        }

        DB::table('login_codes')->where('email_hash', $hash)->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('login_codes')->insert([
            'email_hash' => $hash, 'code_hash' => $this->digest($hash, $code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $code;
    }

    /** True once, for the right code; wrong guesses are counted and burn the code after MAX_ATTEMPTS. */
    public function consume(string $email, string $code): bool
    {
        $hash = UserEmail::hash($email);
        $budget = 'otp-guesses:'.$hash;
        if ((int) \Illuminate\Support\Facades\Cache::get($budget, 0) >= self::HOURLY_GUESSES) {
            return false;
        }
        $code = preg_replace('/\D/', '', strtr($code, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'])) ?? '';

        $ok = DB::transaction(function () use ($hash, $code) {
            $row = DB::table('login_codes')->where('email_hash', $hash)->whereNull('used_at')
                ->where('expires_at', '>', now())->orderByDesc('id')->lockForUpdate()->first();
            if (! $row || $row->attempts >= self::MAX_ATTEMPTS) {
                return false;
            }

            if (! hash_equals($row->code_hash, $this->digest($hash, $code))) {
                DB::table('login_codes')->where('id', $row->id)->increment('attempts');

                return false;
            }

            DB::table('login_codes')->where('id', $row->id)->update(['used_at' => now()]);

            return true;
        });
        if (! $ok) {
            \Illuminate\Support\Facades\Cache::add($budget, 0, now()->addHour());
            \Illuminate\Support\Facades\Cache::increment($budget);
        }

        return $ok;
    }

    public function prune(): int
    {
        return DB::table('login_codes')->where('expires_at', '<', now()->subDay())->delete();
    }

    private function digest(string $emailHash, string $code): string
    {
        return hash_hmac('sha256', $emailHash.'|'.$code, (string) config('app.key'));
    }
}
