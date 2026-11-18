<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\AvatarService;
use App\Services\DefaultAvatars;
use App\Services\ProfileExport;
use App\Services\QrService;
use App\Services\StatsRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileExportController extends Controller
{
    public function show(Request $request, ProfileExport $export, QrService $qr, string $username, string $format): Response
    {
        $profile = Profile::visible()->where('username', strtolower($username))->firstOrFail();

        if (auth()->id() !== $profile->user_id) {
            StatsRecorder::record($profile->user_id, 'export', $request);
        }

        // a bilingual profile can be asked for either language: /name.json?lang=en
        $lang = $profile->chooseLang($request->query('lang') ?: ($format === 'card' ? app()->getLocale() : null));
        $profile = $profile->localized($lang);
        if ($format === 'card') {
            app()->setLocale($lang);
        }

        $res = match ($format) {
            'json' => response(json_encode($export->data($profile), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 200, ['Content-Type' => 'application/json; charset=utf-8']),
            'md' => response($export->markdown($profile), 200, ['Content-Type' => 'text/markdown; charset=utf-8']),
            'vcf' => response($export->vcard($profile), 200, [
                'Content-Type' => 'text/vcard; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="'.$profile->username.'.vcf"',
            ]),
            'qr' => $this->qr($request, $export, $qr, $profile),
            'card' => response()->view('cards.profile', [
                'p' => $profile, 'name' => $export->display($profile), 'avatar' => $export->avatarUrl($profile, 256), 'verified' => $export->verified($profile),
                'rtl' => app()->getLocale() !== 'en',
            ]),
        };

        // Public data by design: other sites may read it from the browser.
        $res->headers->set('Access-Control-Allow-Origin', '*');
        $res->headers->set('Cache-Control', 'public, max-age=300');
        $res->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $profile->allow_indexing) {
            $res->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $res;
    }

    /** /{username}.qr — vector QR of the profile address. ?size=64..2048 &fg=hex &bg=hex &margin=0..8 &content=vcard &download=1 */
    private function qr(Request $request, ProfileExport $export, QrService $qr, Profile $profile): Response
    {
        $size = max(64, min(2048, (int) $request->query('size', 512)));
        $margin = max(0, min(8, (int) $request->query('margin', 2)));
        $fg = QrService::parseColor($request->query('fg')) ?? [16, 21, 23];
        $bg = QrService::parseColor($request->query('bg')) ?? [255, 255, 255];

        // A code a phone cannot read is worse than a refusal: keep dark-on-light contrast above 3:1.
        abort_if(QrService::contrast($fg, $bg) < 3, 422, 'fg and bg do not have enough contrast to be scanned.');

        $text = route('profile.show', $profile->username);
        if ($request->query('content') === 'vcard') {
            $vcard = $export->vcard($profile);
            $text = strlen($vcard) <= 1100 ? $vcard : $text;   // too big for a reliable code: fall back to the address
        }

        $res = response($qr->svg($text, $size, $fg, $bg, $margin), 200, ['Content-Type' => 'image/svg+xml; charset=utf-8']);
        if ($request->boolean('download')) {
            $res->headers->set('Content-Disposition', 'attachment; filename="'.$profile->username.'-qr.svg"');
        }

        return $res;
    }

    /** /{username}/print — a business card (85x55 mm) and a ready-to-print A4 sheet of ten. */
    public function printCard(Request $request, ProfileExport $export, QrService $qr, string $username): Response
    {
        $profile = Profile::visible()->where('username', strtolower($username))->firstOrFail();

        return response()->view('cards.print', [
            'p' => $profile, 'name' => $export->display($profile), 'url' => route('profile.show', $profile->username),
            'qrSvg' => $qr->inner($qr->svg(route('profile.show', $profile->username), 400, [16, 21, 23], [255, 255, 255], 1)),
            'avatar' => $export->avatarUrl($profile, 256), 'rtl' => app()->getLocale() !== 'en',
            'accent' => $this->accent($profile), 'sheet' => $request->boolean('sheet'),
        ], 200, ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, max-age=0']);
    }

    /** /{username}/share.svg — a 1080x1350 shareable card (post or story) with the QR code. */
    public function shareImage(Request $request, ProfileExport $export, QrService $qr, AvatarService $avatars, string $username): Response
    {
        $profile = Profile::visible()->where('username', strtolower($username))->firstOrFail();
        $esc = fn (?string $s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $accent = $this->accent($profile);
        $name = $export->display($profile);
        $photo = $avatars->renderProfile($profile, 320, 'jpeg');

        $face = $photo
            ? '<clipPath id="c"><circle cx="540" cy="380" r="160"/></clipPath><image href="data:image/jpeg;base64,'.base64_encode($photo['body']).'" x="380" y="220" width="320" height="320" clip-path="url(#c)" preserveAspectRatio="xMidYMid slice"/>'
            : '<circle cx="540" cy="380" r="160" fill="'.DefaultAvatars::PALETTE[DefaultAvatars::colorIndex($profile->username)][0].'"/>'
                .'<text x="540" y="380" dy=".35em" text-anchor="middle" fill="#fff" font-size="120" font-weight="700" font-family="Vazirmatn, Tahoma, Arial, sans-serif">'.$esc(DefaultAvatars::initials($name)).'</text>';

        $sub = $esc(implode(' · ', array_filter([$profile->headline, $profile->location])));
        $qrNested = preg_replace('/^<svg /', '<svg x="340" y="800" ', $qr->inner($qr->svg(route('profile.show', $profile->username), 400, [16, 21, 23], [255, 255, 255], 0)), 1);

        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 1080 1350" role="img" aria-label="'.$esc($name).'">'
            .'<rect width="1080" height="1350" fill="'.$esc($accent).'"/>'
            .'<rect x="60" y="60" width="960" height="1230" rx="36" fill="#fff"/>'
            .$face
            .'<text x="540" y="650" text-anchor="middle" font-size="64" font-weight="700" fill="#101517" font-family="Vazirmatn, Tahoma, Arial, sans-serif">'.$esc($name).'</text>'
            .($sub !== '' ? '<text x="540" y="715" text-anchor="middle" font-size="34" fill="#5B6470" font-family="Vazirmatn, Tahoma, Arial, sans-serif">'.$sub.'</text>' : '')
            .'<rect x="320" y="780" width="440" height="440" rx="20" fill="#fff" stroke="#E4E7EC" stroke-width="2"/>'
            .$qrNested
            .'<text x="540" y="1262" text-anchor="middle" font-size="32" font-weight="600" fill="#101517" font-family="Inter, Arial, sans-serif" direction="ltr">'.$esc(preg_replace('#^https?://#', '', route('profile.show', $profile->username))).'</text>'
            .'</svg>';

        $res = response($svg, 200, ['Content-Type' => 'image/svg+xml; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'Access-Control-Allow-Origin' => '*', 'X-Content-Type-Options' => 'nosniff']);
        if ($request->boolean('download')) {
            $res->headers->set('Content-Disposition', 'attachment; filename="'.$profile->username.'-card.svg"');
        }

        return $res;
    }

    private function accent(Profile $profile): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $profile->accent_color) ? $profile->accent_color : '#1D4FC4';
    }
}
