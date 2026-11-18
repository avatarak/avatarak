<?php

namespace App\Support;

/**
 * A tiny PNG can claim to be 30000x30000 pixels; decoding it needs gigabytes of memory and kills the PHP worker.
 * Read the size from the header first and refuse anything that would not fit comfortably (36 megapixels is about
 * 144 MB decoded, which sits inside the 256 MB memory limit with room for the resized copy).
 */
final class ImageGuard
{
    public const MAX_PIXELS = 36_000_000;

    public static function decodable(string $bytes): bool
    {
        $i = @getimagesizefromstring($bytes);

        return is_array($i) && $i[0] > 0 && $i[1] > 0 && $i[0] * $i[1] <= self::MAX_PIXELS;
    }
}
