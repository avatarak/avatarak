<?php

namespace App\Services;

use App\Http\Controllers\SessionsController;
use App\Mail\SecurityNotice;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Emails about important events on an account, so a takeover or an admin action never goes unnoticed. */
class SecurityNotices
{
    public static function send(User $user, string $kind, array $context = []): void
    {
        if (! in_array($kind, SecurityNotice::KINDS, true)) {
            return;
        }

        try {
            Mail::to(self::address($user))->send(new SecurityNotice($kind, $context + ['when' => now()->format('Y-m-d H:i').' UTC']));
        } catch (\Throwable $e) {
            report($e);                                 // a failing mail server must never break the action itself
        }
    }

    /** The primary verified address if there is one, else the sign-up address. */
    private static function address(User $user): string
    {
        return UserEmail::where('user_id', $user->id)->where('is_primary', true)->whereNotNull('verified_at')->value('email') ?: $user->email;
    }

    /**
     * Remember this browser for this user. When it has never been seen (and the user has signed in before),
     * announce it: that is the email that tells someone their account was opened from somewhere new.
     */
    public static function noteSignIn(User $user, Request $request): void
    {
        try {
            $ua = (string) $request->userAgent();
            $d = SessionsController::describe($ua);
            $ip = (string) $request->ip();
            // browser + OS + the first three IPv4 groups (or the first four IPv6 groups): stable across a mobile IP change
            $net = str_contains($ip, ':') ? implode(':', array_slice(explode(':', $ip), 0, 4)) : implode('.', array_slice(explode('.', $ip), 0, 3));
            $fp = hash_hmac('sha256', implode('|', [$d['browser'] ?? '?', $d['os'] ?? '?', $net]), (string) config('app.key'));

            $known = DB::table('known_devices')->where('user_id', $user->id);
            $hadAny = (clone $known)->exists();
            $seen = (clone $known)->where('fingerprint', $fp)->first();

            if ($seen) {
                DB::table('known_devices')->where('id', $seen->id)->update(['last_seen_at' => now(), 'updated_at' => now()]);

                return;
            }
            DB::table('known_devices')->insert(['user_id' => $user->id, 'fingerprint' => $fp, 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            if ($hadAny) {
                self::send($user, 'new_device', ['device' => trim(($d['browser'] ?? 'Unknown browser').' · '.($d['os'] ?? 'Unknown OS')), 'ip' => $ip]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function prune(): int
    {
        return DB::table('known_devices')->where('last_seen_at', '<', now()->subDays(400))->delete();
    }
}
