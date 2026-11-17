<?php

namespace App\Services;

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
     * @return array{body: string, mime: string, etag: string}|null
     */
    public function render(string $hash, int $size, string $format): ?array
    {
        $email = UserEmail::with('user.profile')
            ->where('email_hash', $hash)
            ->whereNotNull('verified_at')
            ->where('is_public', true)
            ->first();

        $profile = $email?->user?->profile;

        if (! $profile || ! $profile->show_avatar || ! $profile->avatar_path) {
            return null;
        }

        $version = $profile->avatar_version ?: '0';
        $key = "avatar:{$profile->id}:{$version}:{$size}:{$format}";

        $body = $this->cache()->remember($key, config('avatar.cache_ttl'), function () use ($profile, $size, $format) {
            return $this->resize($profile->avatar_path, $size, $format);
        });

        if (! $body) {
            $this->cache()->forget($key);

            return null;
        }

        return [
            'body' => $body,
            'mime' => $format === 'webp' ? 'image/webp' : 'image/jpeg',
            'etag' => '"'.md5($key).'"',
        ];
    }

    /**
     * Re-encode an upload as a square JPEG (max 1024px). Re-encoding drops EXIF/GPS
     * metadata and any non-image payload; the original file is never stored.
     */
    public function store(Profile $profile, UploadedFile $file): void
    {
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

        $disk = Storage::disk(config('avatar.disk'));
        $path = "avatars/{$profile->id}/".Str::random(20).'.jpg';
        $disk->put($path, $bytes);

        $old = $profile->avatar_path;
        $profile->update(['avatar_path' => $path, 'avatar_version' => Str::random(12)]);
        if ($old) {
            $disk->delete($old);
        }
    }

    public function remove(Profile $profile): void
    {
        if ($profile->avatar_path) {
            Storage::disk(config('avatar.disk'))->delete($profile->avatar_path);
        }
        $profile->update(['avatar_path' => null, 'avatar_version' => null]);
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
        return Cache::store(config('avatar.cache_store'));
    }
}
