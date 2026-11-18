<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\Profile;
use App\Models\UserEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves an email hash to a rendered avatar image.
 *
 * Privacy: an avatar is only revealed for a verified, public email whose owner
 * has show_avatar enabled. In every other case the caller gets null, which is
 * indistinguishable from "no such user" so hashes can't be probed.
 */
class AvatarService
{
    public function clampSize(?int $size): int
    {
        $size = $size ?: config('avatar.default_size');

        return max(config('avatar.min_size'), min(config('avatar.max_size'), $size));
    }

    /**
     * Avatar for an email hash (used by other sites). Only verified + public emails resolve.
     *
     * @return array{body: string, mime: string, etag: string, user_id: int}|null
     */
    public function render(string $hash, int $size, string $format): ?array
    {
        $email = UserEmail::with('user.profile')
            ->where('email_hash', $hash)
            ->whereNotNull('verified_at')
            ->where('is_public', true)
            ->first();

        $profile = $email?->user?->profile;

        return $profile ? $this->renderProfile($profile, $size, $format) : null;
    }

    /**
     * Avatar for a profile (used by the owner's own public page). Honors show_avatar only:
     * the page itself is public, so no email is involved and nothing about emails leaks.
     *
     * @return array{body: string, mime: string, etag: string, user_id: int}|null
     */
    public function renderProfile(Profile $profile, int $size, string $format): ?array
    {
        if (! $profile->show_avatar || ! $profile->avatar_path) {
            return null;
        }

        $version = $profile->avatar_version ?: '0';
        $key = "avatar:{$profile->id}:{$version}:{$size}:{$format}";

        $body = $this->cached($key, fn () => $this->resize($profile->avatar_path, $size, $format));

        if (! $body) {
            return null;
        }

        return [
            'body' => $body,
            'mime' => $format === 'webp' ? 'image/webp' : 'image/jpeg',
            'etag' => '"'.md5($key).'"',
            'user_id' => $profile->user_id,
        ];
    }

    /**
     * Remember the rendered image. Stored base64-encoded because raw JPEG/WebP bytes are not valid UTF-8,
     * and text-based cache stores (the `database` driver on MySQL) reject or mangle them.
     * A broken cache must never break an image: on any cache error we just render without it.
     */
    private function cached(string $key, \Closure $make): ?string
    {
        try {
            $hit = $this->cache()->get($key);
            if (is_string($hit) && ($raw = base64_decode($hit, true)) !== false && $raw !== '') {
                return $raw;
            }

            $body = $make();
            if ($body) {
                $this->cache()->put($key, base64_encode($body), config('avatar.cache_ttl'));
            }

            return $body;
        } catch (\Throwable $e) {
            report($e);

            return $make();
        }
    }

    /** WebP output needs GD built with WebP; otherwise we silently serve JPEG. */
    public function supportsWebp(): bool
    {
        return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
    }

    /**
     * Re-encode an upload as a square JPEG (max 1024px) and add it to the user's library,
     * making it the active avatar. Re-encoding drops EXIF/GPS metadata and any non-image
     * payload; the original file is never stored.
     */
    public function store(Profile $profile, UploadedFile $file): Avatar
    {
        abort_if(Avatar::where('user_id', $profile->user_id)->count() >= Avatar::MAX_PER_USER, 422, __('app.max_avatars'));

        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        abort_unless($src, 422, __('app.invalid_image'));

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $out = min($side, 1024);

        $dst = imagecreatetruecolor($out, $out);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $out, $out, $side, $side);

        ob_start();
        imagejpeg($dst, null, 90);
        $bytes = ob_get_clean();

        $path = "avatars/{$profile->user_id}/".Str::random(20).'.jpg';
        Storage::disk(config('avatar.disk'))->put($path, $bytes);

        $avatar = Avatar::create(['user_id' => $profile->user_id, 'path' => $path]);
        $this->activate($profile, $avatar);

        return $avatar;
    }

    public function activate(Profile $profile, Avatar $avatar): void
    {
        abort_unless($avatar->user_id === $profile->user_id, 404);
        $profile->update(['avatar_id' => $avatar->id, 'avatar_path' => $avatar->path, 'avatar_version' => Str::random(12)]);
    }

    /** Stop using any avatar; the library is kept. */
    public function deactivate(Profile $profile): void
    {
        $profile->update(['avatar_id' => null, 'avatar_path' => null, 'avatar_version' => null]);
    }

    /** Delete an avatar from the library (and its file). Deactivates it if it was active. */
    public function destroy(Profile $profile, Avatar $avatar): void
    {
        abort_unless($avatar->user_id === $profile->user_id, 404);
        if ($profile->avatar_id === $avatar->id) {
            $this->deactivate($profile);
        }
        Storage::disk(config('avatar.disk'))->delete($avatar->path);
        $avatar->delete();
    }

    /** Center-crop to a square and scale to $size. */
    private function resize(string $path, int $size, string $format): ?string
    {
        $disk = Storage::disk(config('avatar.disk'));
        if (! $disk->exists($path)) {
            return null;
        }

        $src = @imagecreatefromstring($disk->get($path));
        if (! $src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);

        $dst = imagecreatetruecolor($size, $size);
        // white background so transparent PNGs don't turn black in JPEG
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);

        ob_start();
        $format === 'webp'
            ? imagewebp($dst, null, config('avatar.quality'))
            : imagejpeg($dst, null, config('avatar.quality'));
        $out = ob_get_clean();

        return $out ?: null;
    }

    /** Generic silhouette, resolution independent. */
    public function defaultSvg(int $size): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            ."<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 80 80\">"
            .'<rect width="80" height="80" fill="#e5e7eb"/>'
            .'<circle cx="40" cy="31" r="14" fill="#9ca3af"/>'
            .'<path d="M12 80c0-17 12-28 28-28s28 11 28 28z" fill="#9ca3af"/></svg>';
    }

    /** 1x1 transparent PNG. */
    public function blankPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }

    private function cache(): Repository
    {
        return Cache::store(config('avatar.cache_store') ?: null);
    }
}
