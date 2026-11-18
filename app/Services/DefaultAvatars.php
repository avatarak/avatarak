<?php

namespace App\Services;

/**
 * Generated placeholder avatars (all SVG, resolution independent):
 *  - identicon : a symmetric 5x5 pattern drawn from the requested hash. It depends on nothing but the hash,
 *                so a known and an unknown address look equally "random" and nothing can be probed.
 *  - monogram  : initials on a colour, for a PUBLIC profile that has no photo (see /{username}/avatar).
 *  - silhouette: the neutral grey person (d=mp).
 *
 * The colour rule (FNV-1a over the lower-cased key, modulo palette size) is mirrored in
 * resources/js/components/InitialsAvatar.vue so the dashboard preview matches the public page.
 */
class DefaultAvatars
{
    /** [background, foreground]; every pair is at least 4.5:1 so the initials stay legible. */
    public const PALETTE = [
        ['#1D4FC4', '#FFFFFF'], ['#0F766E', '#FFFFFF'], ['#7C3AED', '#FFFFFF'], ['#B4231A', '#FFFFFF'],
        ['#9A5B00', '#FFFFFF'], ['#0E7490', '#FFFFFF'], ['#A21CAF', '#FFFFFF'], ['#3F6212', '#FFFFFF'],
        ['#BE185D', '#FFFFFF'], ['#475569', '#FFFFFF'], ['#4338CA', '#FFFFFF'], ['#15803D', '#FFFFFF'],
    ];

    public static function fnv1a(string $s): int
    {
        $h = 0x811c9dc5;
        foreach (unpack('C*', $s) ?: [] as $byte) {
            $h ^= $byte;
            $h = ($h * 0x01000193) & 0xFFFFFFFF;
        }

        return $h;
    }

    public static function colorIndex(string $key): int
    {
        return self::fnv1a(mb_strtolower(trim($key))) % count(self::PALETTE);
    }

    /** First letter of up to two words: "Javid Mirzaei" -> "JM", "جاوید میرزایی" -> "جم", "ali" -> "A". */
    public static function initials(string $name): string
    {
        $words = preg_split('/[\s\x{200c}_.\-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = [];
        foreach (array_slice($words, 0, 2) as $w) {
            $first = preg_match('/\p{L}|\p{N}/u', $w, $m) ? $m[0] : '';
            if ($first !== '') {
                $letters[] = mb_strtoupper($first);
            }
        }

        return $letters ? implode('', $letters) : '?';
    }

    public function monogram(string $name, string $key, int $size): string
    {
        [$bg, $fg] = self::PALETTE[self::colorIndex($key)];
        $text = htmlspecialchars(self::initials($name), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            ."<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 80 80\">"
            ."<rect width=\"80\" height=\"80\" fill=\"{$bg}\"/>"
            ."<text x=\"40\" y=\"40\" dy=\".35em\" text-anchor=\"middle\" fill=\"{$fg}\" font-size=\"32\" font-weight=\"600\" "
            ."font-family=\"Vazirmatn, Tahoma, 'Segoe UI', Arial, sans-serif\">{$text}</text></svg>";
    }

    public function identicon(string $hash, int $size): string
    {
        $bytes = array_values(unpack('C*', (string) hex2bin(str_pad(substr(preg_replace('/[^0-9a-f]/', '0', strtolower($hash)), 0, 64), 64, '0'))));
        [$bg] = self::PALETTE[$bytes[0] % count(self::PALETTE)];

        $cells = '';
        for ($row = 0; $row < 5; $row++) {
            for ($col = 0; $col < 3; $col++) {
                if ($bytes[1 + $row * 3 + $col] % 2 === 0) {
                    continue;
                }
                foreach (array_unique([$col, 4 - $col]) as $c) {
                    $cells .= '<rect x="'.(15 + $c * 10).'" y="'.(15 + $row * 10).'" width="10" height="10"/>';
                }
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            ."<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 80 80\">"
            ."<rect width=\"80\" height=\"80\" fill=\"#F1F3F7\"/><g fill=\"{$bg}\">{$cells}</g></svg>";
    }
}
