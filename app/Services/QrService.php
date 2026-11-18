<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR codes as SVG (vector: crisp when printed at any size). */
class QrService
{
    /** "#abc", "abc", "#A1B2C3" -> [r,g,b]; null when it is not a hex colour. */
    public static function parseColor(?string $hex): ?array
    {
        $hex = ltrim(trim((string) $hex), '#');
        if (preg_match('/^[0-9a-f]{3}$/i', $hex)) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return null;
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** WCAG contrast ratio between two [r,g,b] colours. */
    public static function contrast(array $a, array $b): float
    {
        $lum = function (array $c) {
            [$r, $g, $bl] = array_map(fn ($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $bl;
        };
        [$l1, $l2] = [$lum($a), $lum($b)];

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /**
     * @param  array{0:int,1:int,2:int}  $fg
     * @param  array{0:int,1:int,2:int}  $bg
     */
    public function svg(string $text, int $size = 512, array $fg = [16, 21, 23], array $bg = [255, 255, 255], int $margin = 2): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin, null, null, Fill::uniformColor(new Rgb(...$bg), new Rgb(...$fg))),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($text, 'utf-8', ErrorCorrectionLevel::M());
    }

    /** The SVG body without the XML prolog, ready to nest inside another SVG. */
    public function inner(string $svg): string
    {
        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }
}
