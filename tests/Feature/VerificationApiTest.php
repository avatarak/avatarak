<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\VerificationChallenge;
use App\Models\VerifiedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'javid', array $profile = []): User
    {
        $u = User::factory()->create();
        Profile::create($profile + ['user_id' => $u->id, 'username' => $name]);

        return $u;
    }

    private function tg(bool $on = true): void
    {
        config(['verification.telegram' => $on
            ? ['bot_token' => '1:A', 'bot_username' => 'avatarak_bot', 'webhook_secret' => 'x'.str_repeat('y', 20)]
            : ['bot_token' => null, 'bot_username' => '', 'webhook_secret' => null]]);
        config(['linkpreview.pin_dns' => false]);
    }

    public function test_requires_login(): void
    {
        $this->getJson('/account/verifications')->assertUnauthorized();
        $this->postJson('/account/verifications/website', ['domain' => 'a.com'])->assertUnauthorized();
        $this->postJson('/account/verifications/telegram')->assertUnauthorized();
        $this->deleteJson('/account/verifications/1')->assertUnauthorized();
    }

    public function test_website_start_returns_instructions_and_is_stable(): void
    {
        $u = $this->user();
        $a = $this->actingAs($u)->postJson('/account/verifications/website', ['domain' => 'https://www.Example.com/x'])->assertOk()
            ->assertJsonPath('verified', false)->assertJsonPath('instructions.domain', 'example.com');
        $token = $a->json('instructions.token');
        $this->assertStringContainsString($token, $a->json('instructions.meta'));
        $this->assertSame($token, $this->postJson('/account/verifications/website', ['domain' => 'example.com'])->json('instructions.token'));
    }

    public function test_website_bad_input_and_taken_domain(): void
    {
        $owner = $this->user('owner');
        VerifiedAccount::create(['user_id' => $owner->id, 'network' => 'website', 'identifier' => 'taken.com', 'method' => 'dns', 'verified_at' => now()]);
        $this->actingAs($this->user('other'));
        foreach (['', 'localhost', '10.0.0.1', 'no-dot', 'javascript:1'] as $bad) {
            $this->postJson('/account/verifications/website', ['domain' => $bad])->assertStatus(422);
        }
        $this->postJson('/account/verifications/website', ['domain' => 'taken.com'])->assertStatus(422)
            ->assertJsonPath('message', 'این حساب قبلاً برای کاربر دیگری تأیید شده است.');
    }

    public function test_full_website_flow_via_api(): void
    {
        $this->tg(false);
        $u = $this->user();
        $this->actingAs($u);
        $token = $this->postJson('/account/verifications/website', ['domain' => 'mysite.ir'])->json('instructions.token');

        $published = false;
        Http::fake(function () use (&$published, $token) {   // by reference: arrow fns capture by value
            return Http::response($published ? '<meta name="avatarak-verification" content="'.$token.'">' : '<html></html>', 200, ['Content-Type' => 'text/html']);
        });
        app()->instance(\App\Services\LinkPreviewService::class, tap(new \App\Services\LinkPreviewService, fn ($s) => $s->resolver = fn () => ['93.184.216.34']));

        // not published yet
        $this->postJson('/account/verifications/website/check', ['domain' => 'mysite.ir'])->assertOk()
            ->assertJsonPath('verified', false)->assertJsonPath('error', 'not_found');

        // published
        $published = true;
        $r = $this->postJson('/account/verifications/website/check', ['domain' => 'mysite.ir'])->assertOk()
            ->assertJsonPath('verified', true)->assertJsonPath('method', 'meta');
        $this->assertSame('https://mysite.ir', $r->json('accounts.0.url'));

        // listed in /account and the public profile
        $this->getJson('/account')->assertJsonPath('verified_accounts.0.identifier', 'mysite.ir');
        $this->postJson('/account/verifications/website', ['domain' => 'mysite.ir'])->assertJsonPath('verified', true);   // idempotent
    }

    public function test_check_without_starting_is_404(): void
    {
        $this->actingAs($this->user());
        $this->postJson('/account/verifications/website/check', ['domain' => 'never-started.com'])->assertNotFound();
    }

    public function test_cannot_check_another_users_challenge(): void
    {
        $a = $this->user('aaa');
        $c = VerificationChallenge::issue($a, 'website', 'shared.com');
        Http::fake(['*' => Http::response('<meta name="avatarak-verification" content="'.$c->token.'">', 200, ['Content-Type' => 'text/html'])]);
        $this->actingAs($this->user('bbb'))->postJson('/account/verifications/website/check', ['domain' => 'shared.com'])->assertNotFound();
        $this->assertSame(0, VerifiedAccount::count());
    }

    public function test_telegram_start_uses_profile_handle_and_returns_deep_link(): void
    {
        $this->tg();
        $u = $this->user('javid', ['socials' => ['telegram' => 'Javid_T']]);
        $r = $this->actingAs($u)->postJson('/account/verifications/telegram')->assertOk()->assertJsonPath('handle', 'javid_t');
        $token = VerificationChallenge::first()->token;
        $this->assertSame("https://t.me/avatarak_bot?start=$token", $r->json('link'));
    }

    public function test_telegram_start_validates_and_respects_config(): void
    {
        $this->tg();
        $this->actingAs($this->user());
        $this->postJson('/account/verifications/telegram')->assertStatus(422);                                   // no handle anywhere
        $this->postJson('/account/verifications/telegram', ['handle' => 'ab'])->assertStatus(422);
        $this->tg(false);
        $this->postJson('/account/verifications/telegram', ['handle' => 'javid_t'])->assertStatus(503);
    }

    public function test_telegram_handle_taken_by_another_user(): void
    {
        $this->tg();
        $o = $this->user('owner');
        VerifiedAccount::create(['user_id' => $o->id, 'network' => 'telegram', 'identifier' => 'javid_t', 'external_id' => '1', 'method' => 'telegram_bot', 'verified_at' => now()]);
        $this->actingAs($this->user('other'))->postJson('/account/verifications/telegram', ['handle' => '@javid_t'])->assertStatus(422);
    }

    public function test_index_exposes_state_without_secrets(): void
    {
        $this->tg();
        $r = $this->actingAs($this->user())->getJson('/account/verifications')->assertOk()
            ->assertJsonPath('telegram.enabled', true)->assertJsonPath('telegram.bot', 'avatarak_bot');
        $this->assertStringNotContainsString('1:A', $r->getContent());          // never the bot token
        $this->assertStringNotContainsString('webhook', strtolower($r->getContent()));
    }

    public function test_remove_own_verification_only(): void
    {
        $a = $this->user('aaa');
        $va = VerifiedAccount::create(['user_id' => $a->id, 'network' => 'website', 'identifier' => 'a.com', 'method' => 'dns', 'verified_at' => now()]);
        $b = $this->user('bbb');
        $vb = VerifiedAccount::create(['user_id' => $b->id, 'network' => 'website', 'identifier' => 'b.com', 'method' => 'dns', 'verified_at' => now()]);

        $this->actingAs($a)->deleteJson("/account/verifications/{$vb->id}")->assertNotFound();
        $this->assertSame(2, VerifiedAccount::count());
        $this->deleteJson("/account/verifications/{$va->id}")->assertOk()->assertJsonCount(0, 'accounts');
        $this->assertSame(1, VerifiedAccount::count());
    }
}
