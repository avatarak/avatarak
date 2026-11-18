<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerifiedAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * LinkedIn ownership through "Sign In with LinkedIn using OpenID Connect". LinkedIn gives an opaque, stable id
 * (`sub`) and a name, but no public profile address, so the badge says "LinkedIn · Name" without a link.
 * The record is keyed by `sub`, which the person cannot change.
 */
class LinkedInVerifier
{
    public static function enabled(): bool
    {
        return filled(config('verification.linkedin.client_id')) && filled(config('verification.linkedin.client_secret'));
    }

    public function authorizeUrl(string $state, string $redirect): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query([
            'response_type' => 'code', 'client_id' => config('verification.linkedin.client_id'), 'redirect_uri' => $redirect,
            'state' => $state, 'scope' => 'openid profile',
        ]);
    }

    /** @return array{ok:bool,error?:string} */
    public function complete(User $user, string $code, string $redirect): array
    {
        $tok = Http::timeout(8)->acceptJson()->asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirect,
            'client_id' => config('verification.linkedin.client_id'), 'client_secret' => config('verification.linkedin.client_secret'),
        ]);
        $access = $tok->ok() ? $tok->json('access_token') : null;
        if (! $access) {
            return ['ok' => false, 'error' => 'linkedin_failed'];
        }

        $me = Http::timeout(8)->acceptJson()->withToken($access)->get('https://api.linkedin.com/v2/userinfo');
        $sub = $me->ok() ? (string) $me->json('sub') : '';
        if ($sub === '' || ! preg_match('/^[A-Za-z0-9_\-]{3,100}$/', $sub)) {
            return ['ok' => false, 'error' => 'linkedin_failed'];
        }
        $name = trim(mb_substr((string) ($me->json('name') ?: trim($me->json('given_name').' '.$me->json('family_name'))), 0, 120));

        return DB::transaction(function () use ($user, $sub, $name) {
            $taken = VerifiedAccount::where('network', 'linkedin')->where(fn ($q) => $q->where('external_id', $sub)->orWhere('identifier', $sub))
                ->where('user_id', '!=', $user->id)->lockForUpdate()->exists();
            if ($taken) {
                return ['ok' => false, 'error' => 'taken'];
            }
            VerifiedAccount::where('network', 'linkedin')->where('user_id', $user->id)->delete();
            VerifiedAccount::create(['user_id' => $user->id, 'network' => 'linkedin', 'identifier' => $sub, 'label' => $name ?: null, 'external_id' => $sub, 'method' => 'linkedin_oauth', 'verified_at' => now()]);

            return ['ok' => true];
        });
    }
}
