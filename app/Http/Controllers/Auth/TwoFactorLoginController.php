<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginFlow;
use App\Services\TwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Second step of signing in: a code from the authenticator app, or one recovery code. */
class TwoFactorLoginController extends Controller
{
    public function verify(Request $request, LoginFlow $flow, TwoFactor $twoFactor): JsonResponse
    {
        $data = $request->validate(['code' => ['nullable', 'string', 'max:40'], 'recovery_code' => ['nullable', 'string', 'max:40']]);

        $user = $flow->pendingUser($request);
        if (! $user || $user->isSuspended()) {
            return response()->json(['message' => __('app.twofa_expired')], 422);
        }
        if ($flow->locked($user)) {
            return response()->json(['message' => __('app.twofa_locked')], 429);
        }

        $ok = filled($data['recovery_code'] ?? null)
            ? $twoFactor->useRecoveryCode($user, $data['recovery_code'])
            : $twoFactor->checkCode($user, (string) ($data['code'] ?? ''));

        if (! $ok) {
            $flow->fail($user);

            return response()->json(['message' => __('app.twofa_invalid'), 'errors' => ['code' => [__('app.twofa_invalid')]]], 422);
        }

        $flow->clearFails($user);
        $method = (string) ($request->session()->get('login.pending.method') ?? 'password');

        return $flow->complete($request, $user, $method);
    }
}
