<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\ProfileExport;
use App\Services\StatsRecorder;
use Illuminate\Http\Request;
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

    public function show(Request $request, ProfileExport $export, string $username): Response
    {
        $profile = Profile::visible()->where('username', strtolower($username))->firstOrFail();

        // the owner looking at their own page is not a view
        if (auth()->id() !== $profile->user_id) {
            StatsRecorder::record($profile->user_id, 'profile', $request);
        }

        $hidden = $profile->hidden_sections ?? [];
        $links = $export->links($profile);
        $name = $profile->username;

        $res = response()->view('profile', [
            'p' => $profile,
            'socials' => $export->socials($profile),
            'links' => $links,
            'showImages' => (bool) $profile->show_link_images,
            'showBio' => ! in_array('bio', $hidden, true),
            'showLinks' => ! in_array('links', $hidden, true),
            'showVerified' => ! in_array('verified', $hidden, true),
            'verifiedAccounts' => $export->verifiedAccounts($profile),
            'isOwner' => auth()->id() === $profile->user_id,
            'icons' => json_decode(file_get_contents(resource_path('data/social-icons.json')), true) ?: [],
            'verified' => $export->verified($profile),
            'avatarUrl' => $export->avatarUrl($profile, 512),
            'avatarSmall' => $export->avatarUrl($profile, 128),
            'display' => $export->display($profile),
            'created' => $profile->created_at?->diffForHumans(),
            'updated' => $profile->updated_at?->diffForHumans(),
            'socialCount' => count($export->socials($profile)),
            'formats' => [
                'json' => route('profile.export', [$name, 'json']), 'md' => route('profile.export', [$name, 'md']),
                'vcf' => route('profile.export', [$name, 'vcf']), 'card' => route('profile.export', [$name, 'card']),
            ],
            'indexable' => $profile->allow_indexing,
            'whiteLabel' => $profile->user ? app(\App\Services\PlanService::class)->can($profile->user, 'white_label') : false,
        ]);

        if (! $profile->allow_indexing) {
            $res->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $res;
    }
}
