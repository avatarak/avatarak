<?php

namespace App\Services;

/** The optional phone number on the business card: one tidy, dialable form for people and for the contact file. */
class CardPhone
{
    /** @return string|null  "09121234567" for Iranian mobiles, "+<digits>" for foreign ones, null when it is not a phone number */
    public static function normalize(?string $raw): ?string
    {
        $s = strtr(trim((string) $raw), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        if ($s === '' || preg_match('/[^\d\s+\-().]/', $s)) {
            return null;
        }
        $plus = str_starts_with($s, '+') || str_starts_with($s, '00');
        $digits = preg_replace('/\D/', '', $s);
        if (str_starts_with($s, '00')) {
            $digits = substr($digits, 2);
        }
        if (! $plus && preg_match('/^09\d{9}$/', $digits)) {
            return $digits;
        }
        if (preg_match('/^989\d{9}$/', $digits)) {
            return '0'.substr($digits, 2);
        }
        if ($plus && preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            return '+'.$digits;
        }

        return null;
    }

    /** "0912 345 6789" for reading; foreign numbers stay as stored. */
    public static function pretty(string $stored): string
    {
        return preg_match('/^09(\d{2})(\d{3})(\d{4})$/', $stored, $m) ? "09{$m[1]} {$m[2]} {$m[3]}" : $stored;
    }

    /** E.164 for the vCard. */
    public static function e164(string $stored): string
    {
        return preg_match('/^09\d{9}$/', $stored) ? '+98'.substr($stored, 1) : $stored;
    }
}
