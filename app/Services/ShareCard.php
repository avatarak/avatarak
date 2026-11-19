<?php

namespace App\Services;

use App\Models\Profile;

/** The 1080x1350 share image, in the three card designs. Pure string building: it is served as SVG and rasterised by the browser. */
class ShareCard
{
    private const FONT = "'Vazirmatn Card', Tahoma, Arial, sans-serif";

    private const RANGES = [
        'arabic' => 'U+0600-06FF,U+0750-077F,U+08A0-08FF,U+200C-200E,U+2010-2011,U+FB50-FDFF,U+FE70-FEFC',
        'latin' => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2212',
    ];

    private static ?string $fontCss = null;

    /** Vazirmatn travels inside the image: an SVG shown as <img> or turned into a PNG cannot use the page's fonts. */
    private static function fontCss(): string
    {
        if (self::$fontCss === null) {
            $css = '';
            foreach (self::RANGES as $set => $range) {
                $file = resource_path("fonts/vazirmatn-{$set}-wght-normal.woff2");
                if (is_file($file)) {
                    $css .= "@font-face{font-family:'Vazirmatn Card';font-weight:100 900;src:url(data:font/woff2;base64,".base64_encode(file_get_contents($file)).") format('woff2');unicode-range:{$range}}";
                }
            }
            self::$fontCss = $css;
        }

        return self::$fontCss;
    }

    public function __construct(private QrService $qr, private AvatarService $avatars, private ProfileExport $export) {}

    public function render(Profile $profile, CardTheme $th, ?string $phone, bool $rtl): string
    {
        $esc = fn (?string $s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $name = $this->export->display($profile);
        $sub = implode(' · ', array_filter([$profile->headline, $profile->location]));
        $url = route('profile.show', $profile->username);
        $shown = preg_replace('#^https?://#', '', $url);
        $phoneText = $phone ? CardPhone::pretty($phone) : null;
        $ink = CardTheme::INK;
        $grey = '#5B6470';

        $text = fn (float $x, float $y, int|float $size, string $fill, string $s, string $anchor = 'middle', int $weight = 400, bool $ltr = false) => '<text x="'.$x.'" y="'.$y.'" text-anchor="'.$anchor.'" font-size="'.$size.'" font-weight="'.$weight.'" fill="'.$fill.'" font-family="'.self::FONT.'"'.($ltr ? ' direction="ltr" unicode-bidi="plaintext"' : '').'>'.$esc($s).'</text>';
        $fit = fn (string $s, int $max, int $min = 44, float $width = 880) => (int) max($min, min($max, $width / max(1, mb_strlen($s)) / 0.58));

        $photo = $this->avatars->renderProfile($profile, 360, 'jpeg');
        $face = function (float $cx, float $cy, float $r, string $id) use ($photo, $profile, $name, $esc) {
            if ($photo) {
                return '<clipPath id="'.$id.'"><circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'"/></clipPath><image href="data:image/jpeg;base64,'.base64_encode($photo['body']).'" x="'.($cx - $r).'" y="'.($cy - $r).'" width="'.($r * 2).'" height="'.($r * 2).'" clip-path="url(#'.$id.')" preserveAspectRatio="xMidYMid slice"/>';
            }

            return '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="'.DefaultAvatars::PALETTE[DefaultAvatars::colorIndex($profile->username)][0].'"/>'
                .'<text x="'.$cx.'" y="'.$cy.'" dy=".35em" text-anchor="middle" fill="#fff" font-size="'.($r * .75).'" font-weight="700" font-family="'.self::FONT.'">'.$esc(DefaultAvatars::initials($name)).'</text>';
        };
        $qrAt = function (float $x, float $y, int $size, string $fg) use ($url) {
            return preg_replace('/^<svg /', '<svg x="'.$x.'" y="'.$y.'" ', $this->qr->inner($this->qr->svg($url, $size, CardTheme::rgb($fg), [255, 255, 255], 0)), 1);
        };

        $out = '';
        if ($th->style === 'bold') {
            $on = $th->onAccent;
            $start = $rtl ? 980 : 100;       // text edge: right in RTL, left in LTR
            $anchor = $rtl ? 'end' : 'start';
            $size = $fit($name, 132);
            $out .= '<rect width="1080" height="1350" fill="'.$th->accent.'"/>'
                .'<circle cx="'.($rtl ? 120 : 960).'" cy="40" r="330" fill="none" stroke="#fff" stroke-opacity=".10" stroke-width="110"/>'
                .'<circle cx="'.($rtl ? 960 : 120).'" cy="1330" r="220" fill="none" stroke="#fff" stroke-opacity=".08" stroke-width="70"/>'
                .$face($rtl ? 900 : 180, 190, 100, 'f').'<circle cx="'.($rtl ? 900 : 180).'" cy="190" r="106" fill="none" stroke="'.$on.'" stroke-opacity=".5" stroke-width="8"/>'
                .$text($start, 520, $size, $on, $name, $anchor, 800);
            $y = 520 + 80;
            if ($sub !== '') {
                $out .= $text($start, $y, 44, $on, $sub, $anchor, 400);
                $y += 70;
            }
            if ($phoneText) {
                $out .= $text($start, $y, 48, $on, $phoneText, $anchor, 600, true);
            }
            $tile = $rtl ? 100 : 580;
            $out .= '<rect x="'.$tile.'" y="860" width="400" height="400" rx="36" fill="#fff"/>'.$qrAt($tile + 40, 900, 320, $th->qr)
                .$text($start, 1230, 34, $on, $shown, $anchor, 600, true);
        } else {
            $band = $th->style === 'band';
            $cy = $band ? 330 : 300;
            $out .= '<rect width="1080" height="1350" fill="#fff"/>';
            if ($band) {
                $out .= '<rect width="1080" height="360" fill="'.$th->accent.'"/>'
                    .'<circle cx="150" cy="40" r="260" fill="#fff" fill-opacity=".10"/><circle cx="980" cy="330" r="170" fill="#fff" fill-opacity=".08"/>'
                    .'<circle cx="540" cy="'.$cy.'" r="196" fill="#fff"/>';
            } else {
                $out .= '<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$th->accent.'" stop-opacity=".14"/><stop offset=".6" stop-color="'.$th->accent.'" stop-opacity="0"/></linearGradient></defs>'
                    .'<rect width="1080" height="820" fill="url(#g)"/>'
                    .'<circle cx="540" cy="'.$cy.'" r="190" fill="#fff" stroke="'.$th->accentInk.'" stroke-width="8"/>';
            }
            $out .= $face(540, $cy, $band ? 180 : 172, 'f');
            $ny = $cy + 300;
            $out .= $text(540, $ny, $fit($name, 80, 48, 900), $ink, $name, 'middle', 800);
            $y = $ny + 62;
            if ($sub !== '') {
                $out .= $text(540, $y, 38, $grey, $sub);
                $y += 62;
            }
            if ($phoneText) {
                $out .= $text(540, $y, 42, $th->accentInk, $phoneText, 'middle', 600, true);
            }
            $out .= '<rect x="340" y="830" width="400" height="400" rx="28" fill="#fff" stroke="'.($band ? $th->accent : '#E4E7EC').'" stroke-width="'.($band ? 8 : 2).'"/>'
                .$qrAt(370, 860, 340, $th->qr)
                .$text(540, 1290, 34, $th->accentInk, $shown, 'middle', 600, true);
        }

        return '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 1080 1350" role="img" aria-label="'.$esc($name).'"><style>'.self::fontCss().'</style>'.$out.'</svg>';
    }
}
