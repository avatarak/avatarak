<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use App\Models\Profile;
use App\Services\PlanService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requests for a customer's own domain show THEIR profile and nothing else. Anything that is not the profile
 * page, its avatar/exports or a static asset is a plain 404, so no part of the app (login, panel, admin, API)
 * is ever reachable through a domain we do not control.
 */
class ResolveCustomDomain
{
    private const ALLOWED = '#^/(?:$|avatar$|profile\.(?:json|md|vcf|card|qr)$|build/|brand/|thumb/[a-f0-9]{40}$|favicon\.(?:ico|svg)$|apple-touch-icon\.png$|site\.webmanifest$|up$)#';

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(preg_replace('/^www\./', '', $request->getHost()));
        $own = strtolower(preg_replace('/^www\./', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST)));

        if ($host === $own || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $next($request);                                   // our own address: nothing to do (the common, fast path)
        }

        $row = Cache::remember(CustomDomain::cacheKey($host), 60, fn () => CustomDomain::where('host', $host)->first()?->only(['user_id', 'verified_at']) ?? false);
        if (! is_array($row)) {
            return $next($request);        // not a domain anyone registered with us (an alternate name of this server, a health check…): untouched
        }
        $ownerId = $row['verified_at'] ? $row['user_id'] : null;     // a claim that was never proven serves nothing
        $profile = $ownerId ? Profile::visible()->where('user_id', $ownerId)->with('user')->first() : null;

        // unknown domain, hidden profile, suspended owner, or a plan that no longer includes custom domains
        if (! $profile || ! app(PlanService::class)->can($profile->user, 'custom_domain')) {
            abort(404);
        }
        if (! preg_match(self::ALLOWED, $request->getPathInfo())) {
            abort(404);
        }

        $request->attributes->set('custom_profile', $profile);

        return $next($request);
    }
}
