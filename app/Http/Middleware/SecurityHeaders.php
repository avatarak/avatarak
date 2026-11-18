<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side protections on every response, set here (not only in nginx) so they hold on any hosting:
 * no MIME sniffing, a strict referrer policy, no framing (except the embeddable profile card), HSTS over https and,
 * for pages, a Content-Security-Policy that only allows our own scripts (plus the few inline ones that carry a nonce).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // pages get a per-request nonce, which the Vite tags and our one inline script carry
        $nonce = $this->csp() ? Vite::useCspNonce() : null;

        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure() && app()->isProduction()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // /{username}.card is meant to be put in an iframe on other sites; nothing else is
        $embeddable = str_ends_with($request->path(), '.card');
        if (stripos((string) $h->get('Content-Type'), 'text/html') !== false) {
            if (! $embeddable) {
                $h->set('X-Frame-Options', 'SAMEORIGIN');
            }
            if ($nonce !== null) {
                $h->set('Content-Security-Policy', implode('; ', [
                    "default-src 'self'", "base-uri 'self'", "object-src 'none'", "form-action 'self'",
                    "script-src 'self' 'nonce-{$nonce}'", "style-src 'self' 'unsafe-inline'",
                    "img-src 'self' data: blob: https:", "font-src 'self' data:", "connect-src 'self'",
                    'frame-ancestors '.($embeddable ? '*' : "'self'"),
                ]));
            }
        }

        return $response;
    }

    /** Not while the Vite dev server is running (hot reload needs inline code and a websocket). */
    private function csp(): bool
    {
        return config('app.csp', true) && ! is_file(public_path('hot'));
    }
}
