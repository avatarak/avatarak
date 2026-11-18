<?php

namespace App\Http\Controllers;

use App\Services\QrService;
use App\Services\TwoFactor;
use App\Services\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Turn the authenticator-app second factor on and off, and manage its recovery codes. */
class TwoFactorController extends Controller
{
    /** Same rule as changing the password: the current password, or a very recent emailed-code sign-in. */
    private function reauth(Request $request, ?string $password): void
    {
        $recent = now()->timestamp - (int) $request->session()->get('reauth_at', 0) < 900;
        if (! $recent && ! Hash::check((string) $password, $request->user()->password)) {
            throw ValidationException::withMessages(['password' => [__('app.wrong_current_password')]]);
        }
    }

    public function status(Request $request, TwoFactor $tf): JsonResponse
    {
        $u = $request->user();

        return response()->json(['enabled' => $u->hasTwoFactor(), 'recovery_codes_left' => $u->hasTwoFactor() ? $tf->remainingRecoveryCodes($u) : 0]);
    }

    /** Step 1: a fresh secret + the QR code to scan. Nothing is switched on yet. */
    public function setup(Request $request, TwoFactor $tf, QrService $qr): JsonResponse
    {
        $data = $request->validate(['password' => ['nullable', 'string']]);
        $this->reauth($request, $data['password'] ?? null);
        abort_if($request->user()->hasTwoFactor(), 422, __('app.twofa_already'));

        $secret = $tf->begin($request->user());
        $uri = Totp::uri($secret, $request->user()->email, config('brand.name_en') ?: config('brand.name'));

        return response()->json(['secret' => trim(chunk_split($secret, 4, ' ')), 'uri' => $uri, 'qr' => $qr->svg($uri, 220, [16, 21, 23], [255, 255, 255], 2)]);
    }

    /** Step 2: prove the app works by typing a code. Returns the recovery codes, once. */
    public function confirm(Request $request, TwoFactor $tf): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $codes = $tf->confirm($request->user(), $data['code']);
        if (! $codes) {
            return response()->json(['message' => __('app.twofa_invalid'), 'errors' => ['code' => [__('app.twofa_invalid')]]], 422);
        }
        $request->session()->put('reauth_at', now()->timestamp);        // enrolling just proved who they are

        \App\Services\SecurityNotices::send($request->user(), 'two_factor_enabled');

        return response()->json(['recovery_codes' => $codes]);
    }

    public function disable(Request $request, TwoFactor $tf): JsonResponse
    {
        $data = $request->validate(['password' => ['nullable', 'string'], 'code' => ['required', 'string', 'max:40']]);
        $this->reauth($request, $data['password'] ?? null);
        $u = $request->user();
        abort_unless($u->hasTwoFactor(), 422, __('app.twofa_not_enabled'));

        if (! $tf->checkCode($u, $data['code']) && ! $tf->useRecoveryCode($u, $data['code'])) {
            return response()->json(['message' => __('app.twofa_invalid'), 'errors' => ['code' => [__('app.twofa_invalid')]]], 422);
        }
        $tf->disable($u);
        \App\Services\SecurityNotices::send($u, 'two_factor_disabled');

        return response()->json(['enabled' => false]);
    }

    public function regenerate(Request $request, TwoFactor $tf): JsonResponse
    {
        $data = $request->validate(['password' => ['nullable', 'string'], 'code' => ['required', 'string', 'max:12']]);
        $this->reauth($request, $data['password'] ?? null);
        $u = $request->user();
        abort_unless($u->hasTwoFactor(), 422, __('app.twofa_not_enabled'));
        if (! $tf->checkCode($u, $data['code'])) {
            return response()->json(['message' => __('app.twofa_invalid'), 'errors' => ['code' => [__('app.twofa_invalid')]]], 422);
        }

        return response()->json(['recovery_codes' => $tf->regenerate($u)]);
    }
}
