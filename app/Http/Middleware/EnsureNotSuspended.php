<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A suspended user is signed out on their very next request, whatever session they hold. */
class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // The auth middleware has already run for this request, so stop here instead of letting a
            // controller see a user that was just signed out.
            return response()->json(['message' => __('app.account_suspended')], 401);
        }

        return $next($request);
    }
}
