<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\VerificationChallenge;
use App\Models\VerifiedAccount;
use App\Services\GitHubVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'javid'): User
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => $name]);

        return $u;
    }

    private function start(User $u, string $handle = 'octo-cat'): string
    {
        $r = $this->actingAs($u)->postJson('/account/verifications/github', ['handle' => $handle])->assertOk();
        $this->assertFalse($r->json('verified'));
        $this->assertSame('avatarak-verification.txt', $r->json('instructions.filename'));

        return $r->json('instructions.token');
    }

    private function gistsFake(string $token, array $over = []): void
    {
        $gist = array_replace_recursive([
            'public' => true, 'owner' => ['login' => 'Octo-Cat', 'id' => 583231],
            'files' => ['avatarak-verification.txt' => ['size' => 50, 'raw_url' => 'https://gist.githubusercontent.com/octo-cat/abc/raw/avatarak-verification.txt']],
        ], $over);
        Http::fake([
            'api.github.com/users/*/gists*' => Http::response([$gist]),
            'gist.githubusercontent.com/*' => Http::response('avatarak-verification='.$token."\n"),
        ]);
    }

    public static function handles(): array
    {
        return [['octo-cat', 'octo-cat'], ['@Octo-Cat', 'octo-cat'], ['https://github.com/Octo-Cat/', 'octo-cat'], ['github.com/octo-cat?tab=x', 'octo-cat'],
            ['-bad', null], ['bad-', null], ['a--b', null], ['has space', null], [str_repeat('a', 40), null], ['', null]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('handles')]
    public function test_handle_normalization(string $in, ?string $out): void
    {
        $this->assertSame($out, GitHubVerifier::normalizeHandle($in));
    }

    public function test_gist_proof_verifies_and_stores_the_numeric_id(): void
    {
        $u = $this->user();
        $token = $this->start($u);
        $this->gistsFake($token);

        $this->postJson('/account/verifications/github/check', ['handle' => 'octo-cat'])->assertOk()->assertJsonPath('verified', true)->assertJsonPath('method', 'gist');

        $a = VerifiedAccount::first();
        $this->assertSame(['github', 'octo-cat', '583231'], [$a->network, $a->identifier, $a->external_id]);
        $this->assertSame('https://github.com/octo-cat', $a->url());
        $this->assertSame(0, VerificationChallenge::count(), 'challenge is consumed');
    }

    public function test_wrong_token_is_not_verified(): void
    {
        $u = $this->user();
        $this->start($u);
        $this->gistsFake('someone-elses-token');

        $this->postJson('/account/verifications/github/check', ['handle' => 'octo-cat'])->assertOk()->assertJsonPath('verified', false)->assertJsonPath('error', 'not_found');
        $this->assertSame(0, VerifiedAccount::count());
    }

    public function test_secret_gists_wrong_owner_and_foreign_raw_hosts_are_ignored(): void
    {
        $u = $this->user();
        $token = $this->start($u);

        foreach ([['public' => false], ['owner' => ['login' => 'someone-else']],
            ['files' => ['avatarak-verification.txt' => ['raw_url' => 'https://evil.example/raw']]]] as $over) {
            $this->gistsFake($token, $over);
            $this->postJson('/account/verifications/github/check', ['handle' => 'octo-cat'])->assertJsonPath('verified', false);
        }
        $this->assertSame(0, VerifiedAccount::count());
        Http::assertNotSent(fn ($r) => $r->url() === 'https://evil.example/raw');
    }

    public function test_an_account_can_belong_to_one_user_only(): void
    {
        $a = $this->user('first');
        $b = $this->user('second');
        VerifiedAccount::create(['user_id' => $a->id, 'network' => 'github', 'identifier' => 'octo-cat', 'external_id' => '583231', 'method' => 'gist', 'verified_at' => now()]);

        $this->actingAs($b)->postJson('/account/verifications/github', ['handle' => 'octo-cat'])->assertStatus(422);

        // a renamed login that still maps to the same id cannot be claimed by someone else either
        $token = $this->start($b, 'renamed-cat');
        $this->gistsFake($token, ['owner' => ['login' => 'renamed-cat', 'id' => 583231]]);
        $this->postJson('/account/verifications/github/check', ['handle' => 'renamed-cat'])->assertStatus(422)->assertJsonPath('error', 'taken');
    }

    public function test_github_errors_are_reported_not_hidden(): void
    {
        $u = $this->user();
        $this->start($u);
        Http::fake(['api.github.com/*' => Http::sequence()->push([], 404)->push([], 403)->push('boom', 500)]);
        $this->postJson('/account/verifications/github/check', ['handle' => 'octo-cat'])->assertStatus(422)->assertJsonPath('error', 'github_user_not_found');
        $this->postJson('/account/verifications/github/check', ['handle' => 'octo-cat'])->assertStatus(422)->assertJsonPath('error', 'github_rate_limited');
        $this->postJson('/account/verifications/github/check', ['handle' => 'octo-cat'])->assertStatus(422)->assertJsonPath('error', 'github_unavailable');
    }

    public function test_expired_challenge_and_attempt_cap(): void
    {
        $u = $this->user();
        $token = $this->start($u);
        $this->gistsFake($token);
        VerificationChallenge::query()->update(['attempts' => 40]);
        $this->postJson('/account/verifications/github/check', ['handle' => 'octo-cat'])->assertStatus(422)->assertJsonPath('error', 'too_many_attempts');
        VerificationChallenge::query()->update(['attempts' => 0, 'expires_at' => now()->subMinute()]);
        $this->postJson('/account/verifications/github/check', ['handle' => 'octo-cat'])->assertStatus(422)->assertJsonPath('error', 'expired');
    }

    public function test_check_without_starting_is_404_and_bad_handles_are_rejected(): void
    {
        $this->actingAs($this->user());
        $this->postJson('/account/verifications/github/check', ['handle' => 'octo-cat'])->assertNotFound();
        $this->postJson('/account/verifications/github', ['handle' => '-nope'])->assertStatus(422);
        $this->postJson('/account/verifications/github', [])->assertStatus(422);
    }

    public function test_it_requires_login(): void
    {
        $this->postJson('/account/verifications/github', ['handle' => 'octo-cat'])->assertStatus(401);
    }

    public function test_oauth_is_off_until_configured(): void
    {
        $this->actingAs($this->user());
        $this->getJson('/account/verifications')->assertJsonPath('github.oauth', false);
        $this->get('/account/verifications/github/redirect')->assertStatus(503);
    }

    public function test_oauth_flow_verifies_by_github_id(): void
    {
        config(['verification.github.client_id' => 'cid', 'verification.github.client_secret' => 'sec']);
        $u = $this->user();
        $this->actingAs($u)->getJson('/account/verifications')->assertJsonPath('github.oauth', true);

        $loc = $this->get('/account/verifications/github/redirect')->assertRedirect()->headers->get('Location');
        parse_str(parse_url($loc, PHP_URL_QUERY), $q);
        $this->assertStringStartsWith('https://github.com/login/oauth/authorize', $loc);
        $this->assertSame('cid', $q['client_id']);
        $this->assertSame(route('verify.github.callback'), $q['redirect_uri']);

        Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'tok']),
            'api.github.com/user' => Http::response(['login' => 'Octo-Cat', 'id' => 583231]),
        ]);
        $this->get('/account/verifications/github/callback?code=abc&state='.$q['state'])->assertRedirect('/panel?verify=github-ok');

        $a = VerifiedAccount::first();
        $this->assertSame(['github', 'octo-cat', '583231', 'github_oauth'], [$a->network, $a->identifier, $a->external_id, $a->method]);
    }

    public function test_oauth_rejects_a_wrong_state_and_failed_exchange(): void
    {
        config(['verification.github.client_id' => 'cid', 'verification.github.client_secret' => 'sec']);
        $this->actingAs($this->user());
        $this->get('/account/verifications/github/redirect');

        $this->get('/account/verifications/github/callback?code=abc&state=forged')->assertRedirect('/panel?verify=github-github_oauth_failed');
        $this->assertSame(0, VerifiedAccount::count());

        $this->get('/account/verifications/github/redirect');
        $state = session('github_oauth_state');
        Http::fake(['github.com/login/oauth/access_token' => Http::response(['error' => 'bad_verification_code'])]);
        $this->get('/account/verifications/github/callback?code=abc&state='.$state)->assertRedirect('/panel?verify=github-github_oauth_failed');
        $this->assertSame(0, VerifiedAccount::count());
    }

    public function test_verified_github_appears_in_the_public_json_with_its_url(): void
    {
        $u = $this->user();
        VerifiedAccount::create(['user_id' => $u->id, 'network' => 'github', 'identifier' => 'octo-cat', 'external_id' => '1', 'method' => 'gist', 'verified_at' => now()]);
        $this->getJson('/javid.json')->assertOk()->assertJsonPath('verified_accounts.0.url', 'https://github.com/octo-cat');
    }
}
