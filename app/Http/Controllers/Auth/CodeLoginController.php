<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LoginCode;
use App\Models\UserEmail;
use App\Services\AccountRecovery;
use App\Services\LoginCodeService;
use App\Services\LoginFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Passwordless sign-in: email a one-time code, then exchange it for a session. */
class CodeLoginController extends Controller
{
    public function send(Request $request, LoginCodeService $codes): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);

        // Same answer whether or not the address has an account, so this cannot be used to look people up.
        $user = $codes->findUser($data['email']);
        if ($user && ! $user->isSuspended()) {
            $code = $codes->issue($data['email']);
            if ($code) {
                Mail::to(UserEmail::normalize($data['email']))->send(new LoginCode($code));
            }
        }

        return response()->json(['message' => __('app.code_sent'), 'expires_in' => LoginCodeService::TTL_MINUTES * 60]);
    }

    public function verify(Request $request, LoginCodeService $codes, LoginFlow $flow): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'code' => ['required', 'string', 'max:12']]);

        $user = $codes->findUser($data['email']);
        if (! $user || $user->isSuspended() || ! $codes->consume($data['email'], $data['code'])) {
            return response()->json(['message' => __('app.code_invalid'), 'errors' => ['code' => [__('app.code_invalid')]]], 422);
        }

        // Receiving the code proves the mailbox: mark that address verified (unless someone else already did).
        $email = $user->emails()->where('email_hash', UserEmail::hash($data['email']))->whereNull('verified_at')->first();
        $takenElsewhere = UserEmail::where('email_hash', UserEmail::hash($data['email']))->whereNotNull('verified_at')->exists();
        if ($email && ! $takenElsewhere) {
            // the real owner of the sign-up address shows up: whatever the person who registered it set up is not trusted
            if (AccountRecovery::isSignupAddressFirstProof($user, $email)) {
                app(AccountRecovery::class)->reclaim($user, $email);
            }
            $email->update(['verified_at' => now(), 'verification_token' => null]);
        }

        return $flow->afterFirstFactor($request, $user, 'code');
    }
}
