<?php

namespace App\Services;

use App\Models\GalleryImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** The photos shown on a public profile. Re-encoded on upload: no EXIF/GPS, no hidden payloads, bounded size. */
class Gallery
{
    public const FULL = 1600;
    public const THUMB = 480;

    private function disk()
    {
        return Storage::disk(config('avatar.disk'));
    }

    public function limit(User $user): int
    {
        return app(PlanService::class)->limit($user, 'gallery');
    }

    public function add(User $user, UploadedFile $file, ?string $caption): GalleryImage
    {
        abort_if($user->galleryImages()->count() >= $this->limit($user), 422, __('app.gallery_limit'));

        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        abort_unless($src, 422, __('app.invalid_image'));

        $full = $this->scaled($src, self::FULL);
        $thumb = $this->scaled($src, self::THUMB);
        $base = "gallery/{$user->id}/".Str::random(24).'.jpg';
        $this->disk()->put($base, $this->jpeg($full, 86));
        $this->disk()->put($base.'.thumb.jpg', $this->jpeg($thumb, 80));

        return GalleryImage::create([
            'user_id' => $user->id, 'path' => $base, 'caption' => $caption ?: null,
            'position' => (int) $user->galleryImages()->max('position') + 1,
            'width' => imagesx($full), 'height' => imagesy($full),
        ]);
    }

    public function remove(GalleryImage $image): void
    {
        $this->disk()->delete([$image->path, $image->thumbPath()]);
        $image->delete();
    }

    public function bytes(GalleryImage $image, bool $thumb): ?string
    {
        $path = $thumb ? $image->thumbPath() : $image->path;

        return $this->disk()->exists($path) ? $this->disk()->get($path) : null;
    }

    /** Fit inside a box of $max px on the longest side (never enlarges); flattens transparency to white. */
    private function scaled(\GdImage $src, int $max): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $ratio = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private function jpeg(\GdImage $img, int $quality): string
    {
        ob_start();
        imagejpeg($img, null, $quality);

        return (string) ob_get_clean();
    }
}
