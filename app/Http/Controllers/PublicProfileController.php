<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\UserEmail;
use Illuminate\Http\Response;

class PublicProfileController extends Controller
{
    private const SOCIAL_URLS = [
        'telegram' => 'https://t.me/%s',
        'instagram' => 'https://instagram.com/%s',
        'x' => 'https://x.com/%s',
        'github' => 'https://github.com/%s',
        'linkedin' => 'https://linkedin.com/in/%s',
        'eitaa' => 'https://eitaa.com/%s',
        'rubika' => 'https://rubika.ir/%s',
    ];

    public function show(string $username): Response
    {
        $profile = Profile::where('username', strtolower($username))->where('show_profile', true)->firstOrFail();

        // avatar is served by hash, so use the primary public verified email if any
        $email = UserEmail::where('user_id', $profile->user_id)
            ->whereNotNull('verified_at')->where('is_public', true)
            ->orderByDesc('is_primary')->first();

        $socials = [];
        foreach ($profile->socials ?? [] as $net => $handle) {
            if (isset(self::SOCIAL_URLS[$net])) {
                $socials[$net] = sprintf(self::SOCIAL_URLS[$net], rawurlencode($handle));
            }
        }

        $res = response()->view('profile', [
            'p' => $profile,
            'socials' => $socials,
            'avatarUrl' => ($profile->show_avatar && $profile->avatar_path && $email) ? route('avatar', [$email->email_hash, 's' => 256]) : null,
            'indexable' => $profile->allow_indexing,
        ]);

        if (! $profile->allow_indexing) {
            $res->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $res;
    }
}
