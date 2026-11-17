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
        $locale = $request->header('X-Locale');
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('brand.default_locale', 'fa');
        }
        App::setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'fa');
    }
}
