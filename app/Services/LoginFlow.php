<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * The one place that turns "this person proved who they are" into a session, so every way of signing in
 * (password, emailed code) goes through the same second-factor check.
 */
class LoginFlow
{
    public const PENDING_MINUTES = 5;

    public const MAX_FAILS = 5;

    public const LOCK_MINUTES = 10;

    /** Called once the first factor (password or emailed code) is right. */
    public function afterFirstFactor(Request $request, User $user, string $method): JsonResponse
    {
        if ($user->hasTwoFactor()) {
            // Not signed in yet: only remember who is halfway through, for a few minutes.
            $request->session()->put('login.pending', ['id' => $user->id, 'method' => $method, 'at' => now()->timestamp]);

            return response()->json(['two_factor' => true]);
        }

        return $this->complete($request, $user, $method);
    }

    public function complete(Request $request, User $user, string $method): JsonResponse
    {
        Auth::login($user);
        SecurityNotices::noteSignIn($user, $request);
        $request->session()->forget('login.pending');
        $request->session()->regenerate();
        if ($method === 'code') {
            $request->session()->put('reauth_at', now()->timestamp);   // may set a new password right away
        }

        return response()->json(['message' => 'ok']);
    }

    /** The user who passed the first factor in this session and is waiting on the second one. */
    public function pendingUser(Request $request): ?User
    {
        $p = $request->session()->get('login.pending');
        if (! is_array($p) || now()->timestamp - ($p['at'] ?? 0) > self::PENDING_MINUTES * 60) {
            return null;
        }

        return User::find($p['id']);
    }

    public function locked(User $user): bool
    {
        return (int) Cache::get($this->failKey($user), 0) >= self::MAX_FAILS;
    }

    public function fail(User $user): void
    {
        $k = $this->failKey($user);
        Cache::add($k, 0, now()->addMinutes(self::LOCK_MINUTES));
        Cache::increment($k);
    }

    public function clearFails(User $user): void
    {
        Cache::forget($this->failKey($user));
    }

    private function failKey(User $user): string
    {
        return '2fa-fail:'.$user->id;
    }
}
