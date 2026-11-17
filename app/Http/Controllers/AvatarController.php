<?php

namespace App\Http\Controllers;

use App\Services\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AvatarController extends Controller
{
    public function show(Request $request, AvatarService $avatars, string $hash): Response
    {
        $hash = strtolower($hash);
        $size = $avatars->clampSize((int) ($request->query('s', $request->query('size', 0))));
        $default = (string) $request->query('d', $request->query('default', 'mp'));
        $webp = str_contains((string) $request->header('Accept'), 'image/webp');

        $result = $avatars->render($hash, $size, $webp ? 'webp' : 'jpeg');

        if ($result) {
            return $this->cached(
                $request,
                $result['body'],
                $result['mime'],
                $result['etag'],
                config('avatar.max_age'),
            );
        }

        // Identical response whether the hash is unknown, unverified or private.
        return match ($default) {
            '404' => response('', 404)->header('Cache-Control', 'public, max-age='.config('avatar.default_max_age')),
            'blank' => $this->cached($request, $avatars->blankPng(), 'image/png', '"blank"', config('avatar.default_max_age')),
            default => $this->cached($request, $avatars->defaultSvg($size), 'image/svg+xml', '"mp-'.$size.'"', config('avatar.default_max_age')),
        };
    }

    private function cached(Request $request, string $body, string $mime, string $etag, int $maxAge): Response
    {
        $headers = [
            'Content-Type' => $mime,
            'ETag' => $etag,
            'Cache-Control' => "public, max-age={$maxAge}",
            'Vary' => 'Accept',
            'X-Content-Type-Options' => 'nosniff',
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ];

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers);
    }
}
