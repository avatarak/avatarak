<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a connected site by API key (Authorization: Bearer <key> or X-API-Key)
 * and applies that site's own per-minute rate limit.
 */
class AuthenticateSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->bearerToken() ?: $request->header('X-API-Key');
        $site = is_string($key) && $key !== '' ? Site::findByKey($key) : null;

        // a key that an administrator disabled (or whose owner is suspended) looks like any other bad key
        if (! $site || $site->isDisabled()) {
            // guessing keys: a visitor gets a generous number of misses per minute, then waits
            $miss = 'api-miss:'.$request->ip();
            if (RateLimiter::tooManyAttempts($miss, 120)) {
                return response()->json(['message' => 'Too many failed attempts.'], 429, ['Retry-After' => RateLimiter::availableIn($miss)]);
            }
            RateLimiter::hit($miss, 60);

            return response()->json(['message' => 'Invalid or missing API key.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        $limit = $site->effectiveRateLimit();
        $bucket = 'site-api:'.$site->id;

        if (RateLimiter::tooManyAttempts($bucket, $limit)) {
            $retry = RateLimiter::availableIn($bucket);

            return response()->json(['message' => 'Rate limit exceeded.'], 429, [
                'Retry-After' => $retry,
                'X-RateLimit-Limit' => $limit,
                'X-RateLimit-Remaining' => 0,
            ]);
        }
        RateLimiter::hit($bucket, 60);

        // touch at most once a minute to avoid a write per request
        if (Cache::add('site-touch:'.$site->id, 1, 60)) {
            $site->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('site', $site);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($bucket, $limit));

        return $response;
    }
}
