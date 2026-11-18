<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationChallenge;
use App\Models\VerifiedAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * GitHub ownership, two ways:
 *  - gist  : publish a PUBLIC gist named avatarak-verification.txt containing "avatarak-verification=TOKEN".
 *            Needs no setup: we read the user's public gists from the GitHub API (fixed hosts only).
 *  - oauth : "Sign in with GitHub" (only when GITHUB_CLIENT_ID / SECRET are configured).
 *
 * The record is keyed by the numeric GitHub id; logins can be renamed and re-registered, ids cannot.
 */
class GitHubVerifier
{
    public const FILE = 'avatarak-verification.txt';

    public static function oauthEnabled(): bool
    {
        return filled(config('verification.github.client_id')) && filled(config('verification.github.client_secret'));
    }

    /** "@Octo-Cat", "https://github.com/octo-cat" -> "octo-cat"; null if not a valid GitHub login. */
    public static function normalizeHandle(string $input): ?string
    {
        $h = trim($input);
        $h = preg_replace('#^(https?://)?(www\.)?github\.com/#i', '', $h);
        $h = strtolower(ltrim(explode('/', explode('?', $h)[0])[0], '@'));

        return preg_match('/^[a-z0-9](?:[a-z0-9]|-(?=[a-z0-9])){0,38}$/', $h) ? $h : null;
    }

    public function instructions(VerificationChallenge $c): array
    {
        return [
            'handle' => $c->identifier, 'token' => $c->token, 'filename' => self::FILE,
            'content' => 'avatarak-verification='.$c->token, 'gist_url' => 'https://gist.github.com/',
            'expires_at' => $c->expires_at->toIso8601String(),
        ];
    }

    /** @return array{ok:bool,error?:string} */
    public function check(VerificationChallenge $c): array
    {
        if ($c->expired()) {
            return ['ok' => false, 'error' => 'expired'];
        }
        if ($c->attempts >= (int) config('verification.max_checks_per_challenge')) {
            return ['ok' => false, 'error' => 'too_many_attempts'];
        }
        $c->increment('attempts');

        $resp = $this->api('https://api.github.com/users/'.rawurlencode($c->identifier).'/gists?per_page=50');
        if ($resp->status() === 404) {
            return ['ok' => false, 'error' => 'github_user_not_found'];
        }
        if (in_array($resp->status(), [403, 429], true)) {
            return ['ok' => false, 'error' => 'github_rate_limited'];
        }
        if (! $resp->ok() || ! is_array($resp->json())) {
            return ['ok' => false, 'error' => 'github_unavailable'];
        }

        $expected = 'avatarak-verification='.$c->token;
        foreach ($resp->json() as $gist) {
            $file = $gist['files'][self::FILE] ?? null;
            $owner = $gist['owner'] ?? [];
            if (! $file || empty($gist['public']) || ($file['size'] ?? 999) > 300 || empty($owner['id'])
                || strtolower((string) ($owner['login'] ?? '')) !== $c->identifier
                || (parse_url((string) ($file['raw_url'] ?? ''), PHP_URL_HOST) !== 'gist.githubusercontent.com')) {
                continue;
            }
            $raw = Http::timeout(8)->withHeaders(['User-Agent' => 'Avatarak'])->get($file['raw_url']);
            if ($raw->ok() && hash_equals($expected, trim((string) $raw->body()))) {
                return $this->record($c->user_id, $c->identifier, (string) $owner['id'], 'gist', $c);
            }
        }

        return ['ok' => false, 'error' => 'not_found'];
    }

    public function authorizeUrl(string $state, string $redirect): string
    {
        return 'https://github.com/login/oauth/authorize?'.http_build_query([
            'client_id' => config('verification.github.client_id'), 'redirect_uri' => $redirect, 'state' => $state, 'allow_signup' => 'false',
        ]);
    }

    /** @return array{ok:bool,error?:string} */
    public function completeOAuth(User $user, string $code, string $redirect): array
    {
        $tok = Http::timeout(8)->acceptJson()->asForm()->post('https://github.com/login/oauth/access_token', [
            'client_id' => config('verification.github.client_id'), 'client_secret' => config('verification.github.client_secret'),
            'code' => $code, 'redirect_uri' => $redirect,
        ]);
        $access = $tok->ok() ? ($tok->json('access_token') ?: null) : null;
        if (! $access) {
            return ['ok' => false, 'error' => 'github_oauth_failed'];
        }

        $me = Http::timeout(8)->acceptJson()->withToken($access)->withHeaders(['User-Agent' => 'Avatarak'])->get('https://api.github.com/user');
        if (! $me->ok() || ! $me->json('id') || ! $me->json('login')) {
            return ['ok' => false, 'error' => 'github_oauth_failed'];
        }

        return $this->record($user->id, strtolower((string) $me->json('login')), (string) $me->json('id'), 'github_oauth');
    }

    /** @return array{ok:bool,error?:string,method?:string} */
    private function record(int $userId, string $login, string $id, string $method, ?VerificationChallenge $challenge = null): array
    {
        return DB::transaction(function () use ($userId, $login, $id, $method, $challenge) {
            $taken = VerifiedAccount::where('network', 'github')
                ->where(fn ($q) => $q->where('external_id', $id)->orWhere('identifier', $login))
                ->where('user_id', '!=', $userId)->lockForUpdate()->exists();
            if ($taken) {
                return ['ok' => false, 'error' => 'taken'];
            }

            VerifiedAccount::where('network', 'github')->where('user_id', $userId)->delete();
            VerifiedAccount::create([
                'user_id' => $userId, 'network' => 'github', 'identifier' => $login, 'external_id' => $id,
                'method' => $method, 'verified_at' => now(),
            ]);
            $challenge?->delete();

            return ['ok' => true, 'method' => $method];
        });
    }

    private function api(string $url)
    {
        $req = Http::timeout(8)->acceptJson()->withHeaders(['User-Agent' => 'Avatarak', 'X-GitHub-Api-Version' => '2022-11-28']);
        if (filled(config('verification.github.api_token'))) {
            $req = $req->withToken(config('verification.github.api_token'));
        }

        return $req->get($url);
    }
}
