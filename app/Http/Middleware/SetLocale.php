<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** The SPA tells us its UI language via X-Locale so server messages match it. */
class SetLocale
{
    public const SUPPORTED = ['fa', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        self::apply($request);

        return $next($request);
    }

    /** Also called from the exception handler: `auth` runs before this middleware. */
    public static function apply(Request $request): void
    {
        // 1) X-Locale: sent by the SPA with every API call. 2) `locale` cookie: the user's UI language, which
        // the SPA also stores so server-rendered pages (public profile) follow it. 3) the brand default.
        foreach ([$request->header('X-Locale'), $request->cookie('locale'), config('brand.default_locale')] as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::SUPPORTED, true)) {
                App::setLocale($candidate);

                return;
            }
        }
        App::setLocale('fa');
    }
}
