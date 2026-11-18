<?php

namespace Tests\Feature;

use App\Http\Controllers\SessionsController;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'ali@example.com', string $name = 'ali'): User
    {
        $u = User::factory()->create(['email' => $email, 'password' => 'secret-pass-1']);
        Profile::create(['user_id' => $u->id, 'username' => $name]);

        return $u;
    }

    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);
        $this->app['session']->forgetDrivers();
    }

    /** Sign in for real and keep the session cookie, like a browser does. */
    private function signIn(array $headers = []): void
    {
        $r = $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1'], $headers)->assertOk();
        $cookie = $r->getCookie(config('session.cookie'));            // decrypted: the plain session id
        $this->assertNotNull($cookie, 'the login sets a session cookie');
        $this->withCookie(config('session.cookie'), $cookie->getValue());
        $this->app['auth']->forgetGuards();                           // a real browser starts a new request from the cookie only
    }

    private function otherSession(User $u, string $id, string $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605 Safari/604.1', int $ago = 3600): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $u->id, 'ip_address' => '203.0.113.7', 'user_agent' => $ua, 'payload' => base64_encode(serialize([])), 'last_activity' => time() - $ago]);
    }

    // NOTE: Laravel's test client does not carry a browser-style session cookie between requests, so the
    // "which one is me?" comparison was checked in a real browser, and these tests cover everything that
    // does not depend on it.

    private function handle(string $id): string
    {
        return substr(hash_hmac('sha256', $id, (string) config('app.key')), 0, 20);
    }

    public function test_the_list_describes_each_session_and_never_exposes_a_session_id(): void
    {
        $this->useDatabaseSessions();
        $u = $this->user();
        $this->otherSession($u, 'secret-session-id-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
        $this->otherSession($u, 'second-one', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537', 60);

        $r = $this->actingAs($u)->getJson('/account/sessions')->assertOk();
        $this->assertTrue($r->json('supported'));
        $this->assertCount(2, $r->json('sessions'));
        $this->assertSame(['Chrome', 'Windows'], [$r->json('sessions.0.browser'), $r->json('sessions.0.os')], 'newest first');
        $this->assertSame(['Safari', 'iOS', '203.0.113.7'], [$r->json('sessions.1.browser'), $r->json('sessions.1.os'), $r->json('sessions.1.ip')]);
        $this->assertStringNotContainsString('secret-session-id', $r->getContent());
        $this->assertStringNotContainsString('second-one', $r->getContent());
        $this->assertSame([$this->handle('second-one'), $this->handle('secret-session-id-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA')], array_column($r->json('sessions'), 'id'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{20}$/', $r->json('sessions.0.id'));
    }

    public function test_a_single_session_can_be_signed_out_by_its_handle(): void
    {
        $this->useDatabaseSessions();
        $u = $this->user();
        $this->otherSession($u, 'other-session-id-1');
        $this->otherSession($u, 'other-session-id-2');

        $this->actingAs($u)->deleteJson('/account/sessions/'.$this->handle('other-session-id-1'))->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id-1']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session-id-2']);
    }

    public function test_unknown_or_malformed_handles_are_404(): void
    {
        $this->useDatabaseSessions();
        $u = $this->user();
        $this->otherSession($u, 'mine');
        $this->actingAs($u)->deleteJson('/account/sessions/'.str_repeat('a', 20))->assertNotFound();
        $this->deleteJson('/account/sessions/not-a-handle')->assertNotFound();
        $this->deleteJson('/account/sessions/mine')->assertNotFound();           // the raw id is not a handle
        $this->assertDatabaseHas('sessions', ['id' => 'mine']);
    }

    public function test_you_cannot_touch_somebody_elses_sessions(): void
    {
        $this->useDatabaseSessions();
        $bob = $this->user('bob@example.com', 'bob');
        $this->otherSession($bob, 'bobs-session');
        $u = $this->user();
        $this->otherSession($u, 'my-session');

        $this->assertCount(1, $this->actingAs($u)->getJson('/account/sessions')->json('sessions'), 'only my own');
        $this->deleteJson('/account/sessions/'.$this->handle('bobs-session'))->assertNotFound();
        $this->deleteJson('/account/sessions')->assertOk();
        $this->assertDatabaseHas('sessions', ['id' => 'bobs-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'my-session']);
    }

    public function test_it_says_so_when_the_session_driver_cannot_list_sessions(): void
    {
        $this->actingAs($this->user());                                   // tests run on the array driver
        $this->getJson('/account/sessions')->assertOk()->assertExactJson(['supported' => false, 'sessions' => []]);
        $this->deleteJson('/account/sessions')->assertNotFound();
    }

    public function test_it_requires_login(): void
    {
        $this->getJson('/account/sessions')->assertStatus(401);
        $this->deleteJson('/account/sessions')->assertStatus(401);
    }

    public static function agents(): array
    {
        return [
            ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605 Version/17 Safari/605.1.15', 'Safari', 'macOS'],
            ['Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0', 'Firefox', 'Linux'],
            ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537 Chrome/120 Mobile Safari/537', 'Chrome', 'Android'],
            ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537 Chrome/120 Safari/537 Edg/120', 'Edge', 'Windows'],
            ['curl/8.0', null, null],
            ['', null, null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('agents')]
    public function test_user_agent_description(string $ua, ?string $browser, ?string $os): void
    {
        $this->assertSame(['browser' => $browser, 'os' => $os], SessionsController::describe($ua));
    }
}
