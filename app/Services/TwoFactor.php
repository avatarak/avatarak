<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class TwoFactor
{
    public const RECOVERY_CODES = 10;

    /** Start (or restart) enrolment: a new secret that does nothing until it is confirmed with a code. */
    public function begin(User $user): string
    {
        $secret = Totp::generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null,
        ])->save();

        return $secret;
    }

    /** Finish enrolment. Returns the recovery codes (shown once), or null when the code is wrong. */
    public function confirm(User $user, string $code): ?array
    {
        if (! $user->two_factor_secret || $user->hasTwoFactor()) {
            return null;
        }
        $step = Totp::verify($user->two_factor_secret, $code);
        if ($step === null) {
            return null;
        }

        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $this->storeRecoveryCodes($user, $codes);

        return $codes;
    }

    public function checkCode(User $user, string $code): bool
    {
        if (! $user->hasTwoFactor()) {
            return false;
        }
        $step = Totp::verify($user->two_factor_secret, $code, $user->two_factor_last_step);
        if ($step === null) {
            return false;
        }
        $user->forceFill(['two_factor_last_step' => $step])->saveQuietly();

        return true;
    }

    /** Each recovery code works once. */
    public function useRecoveryCode(User $user, string $input): bool
    {
        $hash = $this->hash($input);
        $stored = (array) $user->two_factor_recovery_codes;
        foreach ($stored as $i => $h) {
            if (hash_equals((string) $h, $hash)) {
                unset($stored[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($stored)])->saveQuietly();

                return true;
            }
        }

        return false;
    }

    public function regenerate(User $user): array
    {
        $codes = $this->newRecoveryCodes();
        $this->storeRecoveryCodes($user, $codes);

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count((array) $user->two_factor_recovery_codes);
    }

    /** @return list<string> e.g. "k7m2p-9xqdw" */
    private function newRecoveryCodes(): array
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';              // no 0/o, 1/l/i
        return array_map(function () use ($alphabet) {
            $s = '';
            for ($i = 0; $i < 10; $i++) {
                $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            return substr($s, 0, 5).'-'.substr($s, 5);
        }, range(1, self::RECOVERY_CODES));
    }

    private function storeRecoveryCodes(User $user, array $codes): void
    {
        $user->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => $this->hash($c), $codes)])->save();
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', Str::lower(preg_replace('/[\s-]/', '', $code) ?? ''), (string) config('app.key'));
    }
}
