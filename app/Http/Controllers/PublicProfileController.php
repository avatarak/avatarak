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
        'youtube' => 'https://youtube.com/@%s',
        'whatsapp' => 'https://wa.me/%s',
        'facebook' => 'https://facebook.com/%s',
        'eitaa' => 'https://eitaa.com/%s',
        'rubika' => 'https://rubika.ir/%s',
    ];

    public function show(string $username): Response
    {
        $profile = Profile::where('username', strtolower($username))->where('show_profile', true)->firstOrFail();

        $socials = [];
        foreach ($profile->socials ?? [] as $net => $handle) {
            if (isset(self::SOCIAL_URLS[$net]) && $handle !== '') {
                $socials[$net] = sprintf(self::SOCIAL_URLS[$net], rawurlencode($handle));
            }
        }

        // Only http(s) links are ever rendered (also enforced on save).
        $links = collect($profile->links ?? [])
            ->filter(fn ($l) => ! empty($l['label']) && ! empty($l['url']) && preg_match('#^https?://#i', $l['url']))
            ->values()->all();

        $hidden = $profile->hidden_sections ?? [];

        $res = response()->view('profile', [
            'p' => $profile,
            'socials' => $socials,
            'links' => $links,
            'showBio' => ! in_array('bio', $hidden, true),
            'showLinks' => ! in_array('links', $hidden, true),
            'isOwner' => auth()->id() === $profile->user_id,
            'icons' => json_decode(file_get_contents(resource_path('data/social-icons.json')), true) ?: [],
            'verified' => UserEmail::where('user_id', $profile->user_id)->whereNotNull('verified_at')->exists(),
            'avatarUrl' => ($profile->show_avatar && $profile->avatar_path)
                ? route('profile.avatar', ['username' => $profile->username, 's' => 512, 'v' => $profile->avatar_version]) : null,
            'indexable' => $profile->allow_indexing,
        ]);

        if (! $profile->allow_indexing) {
            $res->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $res;
    }
}
