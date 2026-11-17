<?php

namespace App\Http\Controllers;

use App\Rules\Username;
use App\Services\AvatarService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public const SOCIALS = ['telegram', 'instagram', 'x', 'github', 'linkedin', 'eitaa', 'rubika'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile', 'emails');
        $p = $user->profile;

        return response()->json([
            'name' => $user->name,
            'profile' => $p->only(['username', 'name_fa', 'name_en', 'bio', 'website', 'socials',
                'show_avatar', 'show_profile', 'allow_indexing', 'accent_color', 'section_order']) + ['has_avatar' => (bool) $p->avatar_path, 'avatar_version' => $p->avatar_version],
            'emails' => $user->emails->map(fn ($e) => $e->only(['id', 'email', 'email_hash', 'is_primary', 'is_public'])
                + ['verified' => $e->isVerified()])->values(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        $data = $request->validate([
            'username' => ['sometimes', new Username, Rule::unique('profiles', 'username')->ignore($profile->id)],
            'name_fa' => ['nullable', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url:http,https', 'max:200'],
            'socials' => ['nullable', 'array'],
            'socials.*' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9._-]+$/'],
        ]);

        if (isset($data['socials'])) {
            // whitelist of known networks only; strip leading @
            $data['socials'] = collect($data['socials'])
                ->only(self::SOCIALS)->filter()->map(fn ($v) => ltrim($v, '@'))->all();
        }

        $profile->update($data);

        return $this->show($request);
    }

    public const SECTIONS = ['bio', 'links', 'website'];

    public function updateAppearance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'section_order' => ['nullable', 'array', 'max:3'],
            'section_order.*' => ['string', 'distinct', Rule::in(self::SECTIONS)],
        ]);
        $request->user()->profile->update($data);

        return $this->show($request);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'show_avatar' => ['sometimes', 'boolean'],
            'show_profile' => ['sometimes', 'boolean'],
            'allow_indexing' => ['sometimes', 'boolean'],
        ]);
        $request->user()->profile->update($data);

        return $this->show($request);
    }

    public function uploadAvatar(Request $request, AvatarService $avatars): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000']]);

        $avatars->store($request->user()->profile, $request->file('avatar'));

        return response()->json(['message' => 'ok']);
    }

    /** Owner's own avatar, for the dashboard preview (never cached publicly). */
    public function myAvatar(Request $request)
    {
        $path = $request->user()->profile->avatar_path;
        abort_unless($path, 404);

        return response(Storage::disk(config('avatar.disk'))->get($path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteAvatar(Request $request, AvatarService $avatars): JsonResponse
    {
        $avatars->remove($request->user()->profile);

        return response()->json(['message' => 'ok']);
    }
}
