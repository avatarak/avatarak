<?php

namespace App\Http\Controllers;

use App\Models\VerificationChallenge;
use App\Models\VerifiedAccount;
use App\Services\TelegramVerifier;
use App\Services\WebsiteVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->state($request));
    }

    /** Start (or resume) proving a website: returns the exact code to publish, three ways. */
    public function website(Request $request, WebsiteVerifier $verifier): JsonResponse
    {
        $host = WebsiteVerifier::normalizeHost((string) $request->validate(['domain' => ['required', 'string', 'max:300']])['domain']);
        abort_unless($host, 422, __('app.verify_bad_domain'));

        $user = $request->user();
        if ($user->verifiedAccounts()->where('network', 'website')->where('identifier', $host)->exists()) {
            return response()->json(['verified' => true] + $this->state($request));
        }
        abort_if(VerifiedAccount::where('network', 'website')->where('identifier', $host)->exists(), 422, __('app.verify_taken'));

        return response()->json(['verified' => false, 'instructions' => $verifier->instructions(VerificationChallenge::issue($user, 'website', $host))]);
    }

    public function websiteCheck(Request $request, WebsiteVerifier $verifier): JsonResponse
    {
        $host = WebsiteVerifier::normalizeHost((string) $request->validate(['domain' => ['required', 'string', 'max:300']])['domain']);
        abort_unless($host, 422, __('app.verify_bad_domain'));

        $challenge = VerificationChallenge::where('user_id', $request->user()->id)->where('network', 'website')->where('identifier', $host)->first();
        abort_unless($challenge, 404, __('app.verify_no_challenge'));

        $result = $verifier->check($challenge);
        if (! $result['ok']) {
            return response()->json([
                'verified' => false, 'error' => $result['error'], 'message' => __('app.verify_err_'.$result['error']),
            ], $result['error'] === 'not_found' ? 200 : 422);
        }

        return response()->json(['verified' => true, 'method' => $result['method']] + $this->state($request));
    }

    public function telegram(Request $request, TelegramVerifier $telegram): JsonResponse
    {
        abort_unless(TelegramVerifier::enabled(), 503, __('app.verify_tg_disabled'));

        $input = $request->input('handle') ?: ($request->user()->profile->socials['telegram'] ?? '');
        $handle = TelegramVerifier::normalizeHandle((string) $input);
        abort_unless($handle, 422, __('app.verify_tg_bad_handle'));

        $user = $request->user();
        if ($user->verifiedAccounts()->where('network', 'telegram')->where('identifier', $handle)->exists()) {
            return response()->json(['verified' => true] + $this->state($request));
        }
        abort_if(VerifiedAccount::where('network', 'telegram')->where('identifier', $handle)->exists(), 422, __('app.verify_taken'));

        $challenge = VerificationChallenge::issue($user, 'telegram', $handle);

        return response()->json([
            'verified' => false, 'handle' => $handle, 'link' => $telegram->link($challenge), 'expires_at' => $challenge->expires_at->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->verifiedAccounts()->findOrFail($id)->delete();

        return response()->json($this->state($request));
    }

    private function state(Request $request): array
    {
        return [
            'accounts' => $request->user()->verifiedAccounts()->orderBy('id')->get()->map(fn (VerifiedAccount $a) => [
                'id' => $a->id, 'network' => $a->network, 'identifier' => $a->identifier, 'url' => $a->url(),
                'method' => $a->method, 'verified_at' => $a->verified_at?->toIso8601String(),
            ])->values(),
            'telegram' => ['enabled' => TelegramVerifier::enabled(), 'bot' => config('verification.telegram.bot_username')],
        ];
    }
}
