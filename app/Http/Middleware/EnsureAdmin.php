<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => __('app.unauthenticated')], 401);
        }
        if (! $user->is_admin) {
            // not 403 with details: a normal user learns nothing about what the admin area contains
            return response()->json(['message' => __('app.not_found')], 404);
        }

        if (config('admin.require_two_factor') && ! $user->hasTwoFactor()) {
            return response()->json(['message' => __('app.admin_needs_2fa'), 'code' => 'two_factor_required'], 403);
        }

        return $next($request);
    }
}
