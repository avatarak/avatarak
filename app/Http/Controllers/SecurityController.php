<?php

namespace App\Http\Controllers;

use App\Services\UserDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    /** Minutes after a one-time-code sign-in during which no current password is needed. */
    private const REAUTH_MINUTES = 15;

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'confirmed:password_confirmation', Password::min(8)],
        ]);

        $recent = now()->timestamp - (int) $request->session()->get('reauth_at', 0) < self::REAUTH_MINUTES * 60;
        if (! $recent && ! Hash::check((string) ($data['current_password'] ?? ''), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => [__('app.wrong_current_password')]]);
        }

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'ok']);
    }

    /** Delete your own account and everything attached to it. Needs the password (or a fresh email-code sign-in). */
    public function destroy(Request $request, UserDeletion $deletion): JsonResponse
    {
        $data = $request->validate(['password' => ['nullable', 'string']]);
        $user = $request->user();

        $recent = now()->timestamp - (int) $request->session()->get('reauth_at', 0) < self::REAUTH_MINUTES * 60;
        if (! $recent && ! Hash::check((string) ($data['password'] ?? ''), $user->password)) {
            throw ValidationException::withMessages(['password' => [__('app.wrong_current_password')]]);
        }
        abort_if($user->is_admin, 422, __('app.cannot_admin'));

        \Illuminate\Support\Facades\Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $deletion->delete($user);

        return response()->json(['message' => 'ok']);
    }
}
