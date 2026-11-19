<?php

namespace App\Http\Controllers;

use App\Models\Avatar;
use App\Rules\Username;
use App\Services\AvatarService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public const SOCIALS = ['telegram', 'instagram', 'x', 'github', 'linkedin', 'youtube', 'whatsapp', 'facebook', 'eitaa', 'rubika'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile', 'emails');
        $p = $user->profile;

        return response()->json([
            'name' => $user->name,
            'id' => $user->id, 'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'two_factor' => $user->hasTwoFactor(),
            'recent_code_login' => now()->timestamp - (int) $request->session()->get('reauth_at', 0) < 900,
            'profile' => $p->only(['username', 'name_fa', 'name_en', 'bio', 'website', 'socials',
                'show_avatar', 'show_profile', 'allow_indexing', 'accent_color', 'section_order',
                'headline', 'location', 'links', 'hidden_sections', 'show_link_images', 'theme', 'custom_sections', 'languages', 'primary_lang', 'headline_alt', 'location_alt', 'bio_alt', 'card_phone', 'card_phone_public', 'card_style']) + ['section_limit' => app(\App\Services\PlanService::class)->limit($user, 'sections'), 'has_avatar' => (bool) $p->avatar_path, 'avatar_version' => $p->avatar_version, 'avatar_id' => $p->avatar_id],
            'gallery' => $user->galleryImages()->get()->map(fn ($g) => ['id' => $g->id, 'caption' => $g->caption, 'caption_alt' => $g->caption_alt])->values(),
            'avatars' => $user->avatars()->latest('id')->get()->map(fn ($a) => [
                'id' => $a->id, 'active' => $a->id === $p->avatar_id, 'created_at' => $a->created_at,
            ])->values(),
            'verified_accounts' => $user->verifiedAccounts()->orderBy('id')->get()->map(fn ($a) => [
                'id' => $a->id, 'network' => $a->network, 'identifier' => $a->identifier, 'label' => $a->label, 'url' => $a->url(),
            ])->values(),
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
            'headline' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'headline_alt' => ['nullable', 'string', 'max:120'],
            'location_alt' => ['nullable', 'string', 'max:100'],
            'bio_alt' => ['nullable', 'string', 'max:500'],
            'links' => ['nullable', 'array', 'max:10'],
            'links.*.label' => ['required_without:links.*.label_alt', 'nullable', 'string', 'max:60'],
            'links.*.label_alt' => ['nullable', 'string', 'max:60'],
            'links.*.description_alt' => ['nullable', 'string', 'max:160'],
            'links.*.url' => ['required', 'url:http,https', 'max:300'],
            'links.*.description' => ['nullable', 'string', 'max:160'],
            'show_link_images' => ['sometimes', 'boolean'],
            'website' => ['nullable', 'url:http,https', 'max:200'],
            'socials' => ['nullable', 'array'],
            'socials.*' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9._-]+$/'],
        ]);

        if (isset($data['socials'])) {
            // whitelist of known networks only; strip leading @
            $data['socials'] = collect($data['socials'])
                ->only(self::SOCIALS)->filter()->map(fn ($v) => ltrim($v, '@'))->all();
        }

        $needsThumbs = false;
        if (array_key_exists('links', $data)) {
            // keep the preview result for URLs that did not change; new/changed URLs are fetched after the response
            $known = collect($profile->links ?? [])->filter(fn ($l) => array_key_exists('thumb', $l))->pluck('thumb', 'url');
            $data['links'] = collect($data['links'] ?? [])->map(fn ($l) => array_filter(['label' => trim($l['label'] ?? ''), 'url' => $l['url'], 'description' => trim($l['description'] ?? ''), 'label_alt' => trim($l['label_alt'] ?? ''), 'description_alt' => trim($l['description_alt'] ?? '')], fn ($v) => $v !== ''))
                ->map(fn ($l) => $known->has($l['url']) ? $l + ['thumb' => $known[$l['url']]] : $l)
                ->values()->all();
            $needsThumbs = config('linkpreview.enabled') && collect($data['links'])->contains(fn ($l) => ! array_key_exists('thumb', $l));
        }

        $profile->update($data);
        if ($needsThumbs) {
            \App\Jobs\FetchLinkThumbnails::dispatchAfterResponse($profile->id);
        }

        return $this->show($request);
    }

    public const SECTIONS = ['bio', 'links', 'verified', 'gallery'];

    public function updateAppearance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme' => ['sometimes', 'string', Rule::in(\App\Models\Profile::THEMES)],
            'languages' => ['sometimes', 'string', Rule::in(\App\Models\Profile::MODES)],
            'primary_lang' => ['sometimes', 'string', Rule::in(\App\Models\Profile::LANGS)],
            'section_order' => ['nullable', 'array', 'max:4'],
            'section_order.*' => ['string', 'distinct', Rule::in(self::SECTIONS)],
            'hidden_sections' => ['nullable', 'array', 'max:4'],
            'hidden_sections.*' => ['string', 'distinct', Rule::in(self::SECTIONS)],
        ]);
        $profile = $request->user()->profile;
        if (isset($data['primary_lang']) && $data['primary_lang'] !== $profile->primary_lang) {
            $profile->swapPrimary($data['primary_lang']);
        }
        $profile->fill($data)->save();

        return $this->show($request);
    }

    /** Business-card options: the optional phone number, whether it is public, and the design. */
    public function updateCard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'card_phone' => ['nullable', 'string', 'max:40'],
            'card_phone_public' => ['sometimes', 'boolean'],
            'card_style' => ['sometimes', 'string', Rule::in(\App\Models\Profile::CARD_STYLES)],
        ]);
        if (filled($data['card_phone'] ?? null)) {
            $phone = \App\Services\CardPhone::normalize($data['card_phone']);
            if ($phone === null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['card_phone' => [__('app.phone_invalid')]]);
            }
            $data['card_phone'] = $phone;
        } else {
            $data['card_phone'] = null;
        }
        $request->user()->profile->update($data);

        return $this->show($request);
    }

    /** Free-text sections ("Services", "Hours", …). Plain text only; how many depends on the plan. */
    public function updateSections(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sections' => ['present', 'array', 'max:50'],
            'sections.*.title' => ['required_without:sections.*.title_alt', 'nullable', 'string', 'max:60'],
            'sections.*.body' => ['required_without:sections.*.body_alt', 'nullable', 'string', 'max:1500'],
            'sections.*.title_alt' => ['nullable', 'string', 'max:60'],
            'sections.*.body_alt' => ['nullable', 'string', 'max:1500'],
        ]);
        $limit = app(\App\Services\PlanService::class)->limit($request->user(), 'sections');
        abort_if(count($data['sections']) > $limit, 422, __('app.sections_limit'));
        $request->user()->profile->update(['custom_sections' => collect($data['sections'])->map(fn ($s) => [
            'title' => trim($s['title'] ?? ''), 'body' => trim($s['body'] ?? ''), 'title_alt' => trim($s['title_alt'] ?? ''), 'body_alt' => trim($s['body_alt'] ?? ''),
        ])->map(fn ($s) => array_filter($s, fn ($v) => $v !== ''))->values()->all()]);

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

        $avatar = $avatars->store($request->user()->profile, $request->file('avatar'));

        return response()->json(['id' => $avatar->id], 201);
    }

    /** Serve one of the owner's library images (never publicly cached). */
    public function avatarImage(Request $request, int $id)
    {
        $avatar = $request->user()->avatars()->findOrFail($id);

        return response(Storage::disk(config('avatar.disk'))->get($avatar->path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function activateAvatar(Request $request, AvatarService $avatars, int $id): JsonResponse
    {
        $avatars->activate($request->user()->profile, $request->user()->avatars()->findOrFail($id));

        return $this->show($request);
    }

    public function destroyAvatar(Request $request, AvatarService $avatars, int $id): JsonResponse
    {
        $avatars->destroy($request->user()->profile, $request->user()->avatars()->findOrFail($id));

        return $this->show($request);
    }

    /** Owner's active avatar, for previews (never cached publicly). */
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
        $avatars->deactivate($request->user()->profile);

        return response()->json(['message' => 'ok']);
    }
}
