<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\VerificationChallenge;
use App\Models\VerifiedAccount;
use App\Services\TelegramVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'a-long-random-webhook-secret-value';

    protected function setUp(): void
    {
        parent::setUp();
        config(['verification.telegram' => ['bot_token' => '123:ABC', 'bot_username' => 'avatarak_bot', 'webhook_secret' => self::SECRET]]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function user(string $name = 'javid'): User
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => $name]);

        return $u;
    }

    private function update(string $token, array $from = [], array $chat = [], ?string $text = null): array
    {
        return ['update_id' => 1, 'message' => [
            'text' => $text ?? "/start $token",
            'from' => $from + ['id' => 777001, 'is_bot' => false, 'username' => 'Javid_T', 'language_code' => 'fa'],
            'chat' => $chat + ['id' => 777001, 'type' => 'private'],
        ]];
    }

    private function hook(array $update, ?string $secret = self::SECRET)
    {
        return $this->postJson('/api/telegram/webhook', $update, $secret === null ? [] : ['X-Telegram-Bot-Api-Secret-Token' => $secret]);
    }

    public function test_handle_normalization(): void
    {
        foreach ([['@Javid_T', 'javid_t'], ['https://t.me/Javid_T', 'javid_t'], ['t.me/javid_t?start=x', 'javid_t'], ['javid_t', 'javid_t']] as [$in, $out]) {
            $this->assertSame($out, TelegramVerifier::normalizeHandle($in));
        }
        foreach (['', 'abc', '1abcde', 'has space', 'bad-dash', str_repeat('a', 33), '@'] as $bad) {
            $this->assertNull(TelegramVerifier::normalizeHandle($bad), $bad);
        }
    }

    public function test_start_with_valid_token_verifies_by_numeric_id(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'telegram', 'javid_t');

        $this->hook($this->update($c->token))->assertOk();

        $a = VerifiedAccount::first();
        $this->assertSame($u->id, $a->user_id);
        $this->assertSame('javid_t', $a->identifier);              // lowercased
        $this->assertSame('777001', $a->external_id);
        $this->assertSame('telegram_bot', $a->method);
        $this->assertSame('https://t.me/javid_t', $a->url());
        $this->assertDatabaseMissing('verification_challenges', ['id' => $c->id]);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r['text'], 'تأیید شد'));
    }

    public function test_webhook_requires_the_secret(): void
    {
        $c = VerificationChallenge::issue($this->user(), 'telegram', 'javid_t');
        $this->hook($this->update($c->token), null)->assertForbidden();
        $this->hook($this->update($c->token), 'wrong')->assertForbidden();
        $this->hook($this->update($c->token), self::SECRET.'x')->assertForbidden();
        $this->assertSame(0, VerifiedAccount::count());
    }

    public function test_webhook_does_not_exist_when_unconfigured(): void
    {
        config(['verification.telegram.bot_token' => null]);
        $this->hook([], self::SECRET)->assertNotFound();
        config(['verification.telegram' => ['bot_token' => 'x', 'bot_username' => 'b', 'webhook_secret' => '']]);
        $this->hook([], '')->assertNotFound();                     // empty secret must never authenticate
    }

    public function test_username_must_match_the_one_entered(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'telegram', 'victim_handle');
        // attacker (different telegram account) uses a token that was issued for @victim_handle
        $this->hook($this->update($c->token, ['username' => 'attacker1', 'id' => 999]))->assertOk();
        $this->assertSame(0, VerifiedAccount::count());
        $this->assertDatabaseHas('verification_challenges', ['id' => $c->id]);   // still usable by the real owner
    }

    public function test_account_without_username_or_bots_are_refused(): void
    {
        $c = VerificationChallenge::issue($this->user(), 'telegram', 'javid_t');
        $this->hook($this->update($c->token, ['username' => null]))->assertOk();
        $this->hook($this->update($c->token, ['is_bot' => true]))->assertOk();
        $this->assertSame(0, VerifiedAccount::count());
    }

    public function test_expired_unknown_and_reused_tokens(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'telegram', 'javid_t');
        $this->hook($this->update('x'.str_repeat('y', 31)))->assertOk();            // unknown
        $c->update(['expires_at' => now()->subMinute()]);
        $this->hook($this->update($c->token))->assertOk();                          // expired
        $this->assertSame(0, VerifiedAccount::count());

        $c->update(['expires_at' => now()->addHour()]);
        $this->hook($this->update($c->token))->assertOk();
        $this->assertSame(1, VerifiedAccount::count());
        $this->hook($this->update($c->token))->assertOk();                          // single use
        $this->assertSame(1, VerifiedAccount::count());
    }

    public function test_telegram_account_already_verified_for_another_user_is_refused(): void
    {
        $first = $this->user('first');
        VerifiedAccount::create(['user_id' => $first->id, 'network' => 'telegram', 'identifier' => 'old_name', 'external_id' => '777001', 'method' => 'telegram_bot', 'verified_at' => now()]);

        $second = $this->user('second');
        $c = VerificationChallenge::issue($second, 'telegram', 'javid_t');
        $this->hook($this->update($c->token))->assertOk();                          // same numeric id, new username

        $this->assertSame(1, VerifiedAccount::count());
        $this->assertSame($first->id, VerifiedAccount::first()->user_id);
        Http::assertSent(fn ($r) => str_contains($r['text'] ?? '', 'قبلاً'));
    }

    public function test_same_user_reverifying_replaces_their_old_record(): void
    {
        $u = $this->user();
        VerifiedAccount::create(['user_id' => $u->id, 'network' => 'telegram', 'identifier' => 'old_name', 'external_id' => '777001', 'method' => 'telegram_bot', 'verified_at' => now()]);
        $c = VerificationChallenge::issue($u, 'telegram', 'javid_t');
        $this->hook($this->update($c->token))->assertOk();
        $this->assertSame(['javid_t'], VerifiedAccount::pluck('identifier')->all());
    }

    public function test_only_private_chats_and_proper_start_commands_count(): void
    {
        $c = VerificationChallenge::issue($this->user(), 'telegram', 'javid_t');
        $this->hook($this->update($c->token, [], ['type' => 'group']))->assertOk();
        $this->hook($this->update($c->token, [], [], $c->token))->assertOk();                  // no /start
        $this->hook($this->update($c->token, [], [], "hello /start {$c->token}"))->assertOk();
        $this->hook(['update_id' => 2, 'edited_message' => ['text' => "/start {$c->token}"]])->assertOk();
        $this->hook(['garbage' => true])->assertOk();
        $this->assertSame(0, VerifiedAccount::count());
    }

    public function test_telegram_outage_does_not_undo_verification(): void
    {
        Http::fake(['api.telegram.org/*' => fn () => throw new \RuntimeException('network down')]);
        $c = VerificationChallenge::issue($this->user(), 'telegram', 'javid_t');
        $this->hook($this->update($c->token))->assertOk();
        $this->assertSame(1, VerifiedAccount::count());
    }

    public function test_start_without_token_gets_a_hint(): void
    {
        $this->hook($this->update('', [], [], '/start'))->assertOk();
        Http::assertSent(fn ($r) => str_contains($r['text'] ?? '', 'تأیید با تلگرام'));
    }

    public function test_commands_refuse_to_run_unconfigured_or_insecure(): void
    {
        config(['verification.telegram.bot_token' => null]);
        $this->artisan('telegram:webhook')->assertFailed();
        config(['verification.telegram' => ['bot_token' => 'x', 'bot_username' => 'b', 'webhook_secret' => 'short']]);
        $this->artisan('telegram:webhook', ['url' => 'https://x.ir/hook'])->assertFailed();
        config(['verification.telegram.webhook_secret' => self::SECRET]);
        $this->artisan('telegram:webhook', ['url' => 'http://x.ir/hook'])->assertFailed();       // http rejected
    }

    public function test_poll_never_prints_the_bot_token_when_telegram_is_unreachable(): void
    {
        Http::fake(['api.telegram.org/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException(
            'cURL error 56: unexpected eof while reading for https://api.telegram.org/bot123:ABC/getUpdates'
        )]);

        $code = \Illuminate\Support\Facades\Artisan::call('telegram:poll', ['--once' => true]);
        $out = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Telegram unreachable', $out);
        $this->assertStringContainsString('HTTPS_PROXY', $out);                  // tells the user what to do
        $this->assertStringNotContainsString('123:ABC', $out);
        $this->assertStringNotContainsString('ABC', $out);
    }

    public function test_poll_handles_updates_it_receives(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'telegram', 'javid_t');
        Http::swap(new \Illuminate\Http\Client\Factory);      // setUp() already installed a catch-all fake; first fake wins
        Http::fake([
            'api.telegram.org/bot123:ABC/getUpdates*' => Http::response(['ok' => true, 'result' => [$this->update($c->token)]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:poll', ['--once' => true])->assertSuccessful();
        $this->assertSame(1, VerifiedAccount::count());
    }

    public function test_once_mode_never_holds_the_connection_open(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->artisan('telegram:poll', ['--once' => true, '--timeout' => 40])->assertSuccessful();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'getUpdates') && str_contains($r->url(), 'timeout=0'));
    }
}
