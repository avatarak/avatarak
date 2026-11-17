<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\ProfileExport;
use Illuminate\Http\Response;

class ProfileExportController extends Controller
{
    public function show(ProfileExport $export, string $username, string $format): Response
    {
        $profile = Profile::where('username', strtolower($username))->where('show_profile', true)->firstOrFail();

        $res = match ($format) {
            'json' => response(json_encode($export->data($profile), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 200, ['Content-Type' => 'application/json; charset=utf-8']),
            'md' => response($export->markdown($profile), 200, ['Content-Type' => 'text/markdown; charset=utf-8']),
            'vcf' => response($export->vcard($profile), 200, [
                'Content-Type' => 'text/vcard; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="'.$profile->username.'.vcf"',
            ]),
            'card' => response()->view('cards.profile', [
                'p' => $profile, 'name' => $export->display($profile), 'avatar' => $export->avatarUrl($profile, 256), 'verified' => $export->verified($profile),
                'rtl' => config('brand.default_locale') !== 'en',
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
}
