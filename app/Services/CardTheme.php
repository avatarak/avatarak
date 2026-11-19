<?php

namespace App\Services;

use App\Models\Profile;

/** Colours for the business card and share image, derived from the user's accent so text is always readable. */
class CardTheme
{
    public const INK = '#101517';

    public readonly string $accent;
    public readonly string $accentInk;   // accent used as text/lines on white
    public readonly string $onAccent;    // text on an accent background
    public readonly string $qr;          // QR colour on white
    public readonly string $style;

    public function __construct(Profile $p, ?string $style = null)
    {
        $this->accent = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $p->accent_color) ? strtoupper($p->accent_color) : '#1D4FC4';
        $a = QrService::parseColor($this->accent);
        $white = [255, 255, 255];
        $ink = QrService::parseColor(self::INK);
        $this->accentInk = QrService::contrast($a, $white) >= 4.5 ? $this->accent : self::INK;
        $this->onAccent = QrService::contrast($a, $white) >= QrService::contrast($a, $ink) ? '#FFFFFF' : self::INK;
        $this->qr = QrService::contrast($a, $white) >= 4.5 ? $this->accent : self::INK;
        $this->style = in_array($style, Profile::CARD_STYLES, true) ? $style : (in_array($p->card_style, Profile::CARD_STYLES, true) ? $p->card_style : 'classic');
    }

    public static function rgb(string $hex): array
    {
        return QrService::parseColor($hex);
    }
}
