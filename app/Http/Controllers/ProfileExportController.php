<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\CardPhone;
use App\Services\CardTheme;
use App\Services\ProfileExport;
use App\Services\QrService;
use App\Services\ShareCard;
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

        // a bilingual profile can be asked for either language: /name.json?lang=en (data formats default to the primary language)
        $profile = $this->speak($request, $profile, uiDefault: $format === 'card');

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
        // these are data files for programs, never search results (the HTML page is what gets indexed)
        $res->headers->set('X-Robots-Tag', 'noindex, nofollow');

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

    /**
     * The profile as a visitor should read it: one language, never two. A single-language profile always speaks its own;
     * a bilingual one follows ?lang=, else (for pages meant for people) the UI language. Also sets the page locale.
     */
    private function speak(Request $request, Profile $profile, bool $uiDefault = true): Profile
    {
        $lang = $profile->chooseLang($request->query('lang') ?: ($uiDefault ? app()->getLocale() : null));
        app()->setLocale($lang);

        return $profile->localized($lang);
    }

    /**
     * /{username}/print — the business card, front and back (85x55 mm), or an A4 sheet of ten per side for duplex printing.
     * ?style=classic|band|bold previews a design; ?sheet=1 the A4 layout.
     */
    public function printCard(Request $request, ProfileExport $export, QrService $qr, string $username): Response
    {
        $profile = $this->speak($request, Profile::visible()->where('username', strtolower($username))->firstOrFail());
        $theme = new CardTheme($profile, $request->query('style'));
        $url = route('profile.show', $profile->username);

        return response()->view('cards.print', [
            'p' => $profile, 'name' => $export->display($profile), 'url' => $url,
            'qrSvg' => $qr->inner($qr->svg($url, 400, CardTheme::rgb($theme->qr), [255, 255, 255], 0)),
            'qrDark' => $qr->inner($qr->svg($url, 400, CardTheme::rgb(CardTheme::INK), [255, 255, 255], 0)),
            'avatar' => $export->avatarUrl($profile, 256), 'rtl' => app()->getLocale() !== 'en',
            'th' => $theme, 'sheet' => $request->boolean('sheet'),
            'phone' => ($ph = $this->phoneFor($profile, forPrint: true)) ? CardPhone::pretty($ph) : null,
            'styles' => Profile::CARD_STYLES,
        ], 200, ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, max-age=0']);
    }

    /** /{username}/share.svg — a 1080x1350 shareable card (post or story) with the QR code. ?style= previews a design. */
    public function shareImage(Request $request, ShareCard $card, string $username): Response
    {
        $profile = $this->speak($request, Profile::visible()->where('username', strtolower($username))->firstOrFail());
        $svg = $card->render($profile, new CardTheme($profile, $request->query('style')), $this->phoneFor($profile, forPrint: false), app()->getLocale() !== 'en');

        $res = response($svg, 200, ['Content-Type' => 'image/svg+xml; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'Access-Control-Allow-Origin' => '*', 'X-Content-Type-Options' => 'nosniff']);
        if ($request->boolean('download')) {
            $res->headers->set('Content-Disposition', 'attachment; filename="'.$profile->username.'-card.svg"');
        }

        return $res;
    }

    /** The card phone is private by default: the owner sees it on their own print page, everyone else only when it was made public. */
    private function phoneFor(Profile $profile, bool $forPrint): ?string
    {
        if (! $profile->card_phone) {
            return null;
        }

        return $profile->card_phone_public || ($forPrint && auth()->id() === $profile->user_id) ? $profile->card_phone : null;
    }
}
