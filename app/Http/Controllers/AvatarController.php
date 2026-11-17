<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AvatarController extends Controller
{
    /** Avatar by email hash (for other sites). */
    public function show(Request $request, AvatarService $avatars, string $hash): Response
    {
        [$size, $format] = $this->params($request, $avatars);

        return $this->respond($request, $avatars, $avatars->render(strtolower($hash), $size, $format), $size);
    }

    /** Avatar for a public profile page: /{username}/avatar */
    public function profile(Request $request, AvatarService $avatars, string $username): Response
    {
        [$size, $format] = $this->params($request, $avatars);
        $profile = Profile::where('username', strtolower($username))->where('show_profile', true)->first();

        return $this->respond($request, $avatars, $profile ? $avatars->renderProfile($profile, $size, $format) : null, $size);
    }

    /** @return array{0:int,1:string} */
    private function params(Request $request, AvatarService $avatars): array
    {
        $size = $avatars->clampSize((int) ($request->query('s', $request->query('size', 0))));
        $webp = $avatars->supportsWebp() && str_contains((string) $request->header('Accept'), 'image/webp');

        return [$size, $webp ? 'webp' : 'jpeg'];
    }

    private function respond(Request $request, AvatarService $avatars, ?array $result, int $size): Response
    {
        $default = (string) $request->query('d', $request->query('default', 'mp'));

        if ($result) {
            return $this->cached($request, $result['body'], $result['mime'], $result['etag'], config('avatar.max_age'));
        }

        // Identical response whether unknown, unverified or private.
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
