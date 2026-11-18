<?php

namespace App\Services;

/**
 * Time-based one-time passwords (RFC 6238, HMAC-SHA1, 6 digits, 30 s), compatible with Google Authenticator,
 * Microsoft Authenticator, Authy, 1Password, Aegis and friends. Implemented here so the project has no extra dependency.
 */
class Totp
{
    public const STEP = 30;

    public const DIGITS = 6;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[\s=-]/', '', $b32) ?? '');
        $bits = '';
        foreach (str_split($b32) as $c) {
            $i = strpos(self::ALPHABET, $c);
            if ($i === false) {
                throw new \InvalidArgumentException('Not a base32 string.');
            }
            $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $o = ord($hash[19]) & 0x0F;
        $n = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);

        return str_pad((string) ($n % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function currentStep(?int $time = null): int
    {
        return intdiv($time ?? time(), self::STEP);
    }

    /**
     * Returns the matched time step (so the caller can refuse to accept it twice), or null.
     * Accepts the previous and next step as well, to forgive clock drift.
     */
    public static function verify(string $secret, string $input, ?int $afterStep = null, ?int $time = null, int $window = 1): ?int
    {
        $input = preg_replace('/\D/', '', strtr($input, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'])) ?? '';
        if (strlen($input) !== self::DIGITS) {
            return null;
        }
        $now = self::currentStep($time);
        for ($s = $now - $window; $s <= $now + $window; $s++) {
            if ($afterStep !== null && $s <= $afterStep) {
                continue;                                  // already used: a code is good once
            }
            if (hash_equals(self::code($secret, $s), $input)) {
                return $s;
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?'.http_build_query([
            'secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => self::DIGITS, 'period' => self::STEP,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
