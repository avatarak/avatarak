<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\VerificationChallenge;
use App\Models\VerifiedAccount;
use App\Services\LinkPreviewService;
use App\Services\WebsiteVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'javid'): User
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => $name]);

        return $u;
    }

    private function verifier(array $txt = []): WebsiteVerifier
    {
        config(['linkpreview.pin_dns' => false]);
        $http = new LinkPreviewService;
        $http->resolver = fn () => ['93.184.216.34'];
        $v = new WebsiteVerifier($http);
        $v->txtResolver = fn (string $host) => $txt[$host] ?? [];

        return $v;
    }

    public static function hosts(): array
    {
        return [
            ['https://www.Example.COM/path?q=1', 'example.com'], ['example.com', 'example.com'], ['WWW.example.com.', 'example.com'],
            ['sub.site.co.uk', 'sub.site.co.uk'], ['http://my-site.ir/', 'my-site.ir'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hosts')]
    public function test_host_normalization(string $in, string $out): void
    {
        $this->assertSame($out, WebsiteVerifier::normalizeHost($in));
    }

    public function test_bad_hosts_are_rejected(): void
    {
        foreach (['', 'localhost', '127.0.0.1', '10.0.0.1', 'http://[::1]/', 'no-dot', 'a..b.com', '-bad.com', 'exa mple.com', 'javascript:alert(1)', 'a.b', str_repeat('a', 300).'.com'] as $bad) {
            $this->assertNull(WebsiteVerifier::normalizeHost($bad), "should reject [$bad]");
        }
    }

    public function test_meta_tag_verifies(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'website', 'example.com');
        Http::fake(['https://example.com/' => Http::response('<html><head><meta name="iravatar-verification" content="'.$c->token.'"></head></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->assertSame(['ok' => true, 'method' => 'meta'], $this->verifier()->check($c));
        $this->assertDatabaseHas('verified_accounts', ['user_id' => $u->id, 'network' => 'website', 'identifier' => 'example.com', 'method' => 'meta']);
        $this->assertDatabaseMissing('verification_challenges', ['id' => $c->id]);          // single use
    }

    public function test_well_known_file_verifies(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'website', 'example.com');
        Http::fake([
            'https://example.com/.well-known/iravatar-verification.txt' => Http::response("  {$c->token}\n", 200, ['Content-Type' => 'text/plain']),
            '*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $this->assertSame('file', $this->verifier()->check($c)['method']);
    }

    public function test_dns_txt_verifies(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'website', 'example.com');
        Http::fake(['*' => Http::response('', 404)]);
        $r = $this->verifier(['example.com' => ['v=spf1 -all', 'iravatar-verification='.$c->token]])->check($c);
        $this->assertSame('dns', $r['method']);
    }

    public function test_wrong_token_does_not_verify_and_counts_an_attempt(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'website', 'example.com');
        Http::fake(['*' => Http::response('<meta name="iravatar-verification" content="WRONG">', 200, ['Content-Type' => 'text/html'])]);
        $this->assertSame(['ok' => false, 'error' => 'not_found'], $this->verifier()->check($c));
        $this->assertSame(1, $c->fresh()->attempts);
        $this->assertSame(0, VerifiedAccount::count());
    }

    public function test_someone_elses_token_cannot_be_used(): void
    {
        // attacker publishes their OWN token on THEIR site; victim's domain must not verify for them
        $attacker = $this->user('evil');
        $mine = VerificationChallenge::issue($attacker, 'website', 'victim.com');
        Http::fake(['https://victim.com/' => Http::response('<meta name="iravatar-verification" content="not-the-attackers-token">', 200, ['Content-Type' => 'text/html'])]);
        $this->assertFalse($this->verifier()->check($mine)['ok']);
    }

    public function test_redirect_to_another_domain_proves_nothing(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'website', 'mine.com');
        Http::fake([
            'https://mine.com/' => Http::response('', 302, ['Location' => 'https://other.com/']),
            'https://other.com/' => Http::response('<meta name="iravatar-verification" content="'.$c->token.'">', 200, ['Content-Type' => 'text/html']),
            '*' => Http::response('', 404),
        ]);
        $this->assertFalse($this->verifier()->check($c)['ok']);
    }

    public function test_apex_to_www_redirect_is_fine(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'website', 'mine.com');
        Http::fake([
            'https://mine.com/' => Http::response('', 301, ['Location' => 'https://www.mine.com/']),
            'https://www.mine.com/' => Http::response('<meta name="iravatar-verification" content="'.$c->token.'">', 200, ['Content-Type' => 'text/html']),
        ]);
        $this->assertTrue($this->verifier()->check($c)['ok']);
    }

    public function test_private_hosts_are_never_fetched(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'website', 'internal.example');
        config(['linkpreview.pin_dns' => false]);
        $http = new LinkPreviewService;
        $http->resolver = fn () => ['10.0.0.5'];
        Http::fake();
        $this->assertFalse((new WebsiteVerifier($http))->check($c)['ok']);
        Http::assertNothingSent();
    }

    public function test_a_domain_verified_by_someone_else_cannot_be_taken(): void
    {
        $owner = $this->user('owner');
        VerifiedAccount::create(['user_id' => $owner->id, 'network' => 'website', 'identifier' => 'example.com', 'method' => 'dns', 'verified_at' => now()]);

        $other = $this->user('other');
        $c = VerificationChallenge::issue($other, 'website', 'example.com');
        Http::fake(['*' => Http::response('<meta name="iravatar-verification" content="'.$c->token.'">', 200, ['Content-Type' => 'text/html'])]);
        $this->assertSame(['ok' => false, 'error' => 'taken'], $this->verifier()->check($c));
        $this->assertSame($owner->id, VerifiedAccount::first()->user_id);
    }

    public function test_expired_and_exhausted_challenges_are_refused(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'website', 'example.com');
        $c->update(['expires_at' => now()->subMinute()]);
        Http::fake();
        $this->assertSame('expired', $this->verifier()->check($c)['error']);
        Http::assertNothingSent();

        $c->update(['expires_at' => now()->addHour(), 'attempts' => 40]);
        $this->assertSame('too_many_attempts', $this->verifier()->check($c->fresh())['error']);
    }

    public function test_issue_reuses_valid_token_and_renews_expired_one(): void
    {
        $u = $this->user();
        $a = VerificationChallenge::issue($u, 'website', 'example.com');
        $this->assertSame($a->token, VerificationChallenge::issue($u, 'website', 'example.com')->token);
        $a->update(['expires_at' => now()->subDay()]);
        $this->assertNotSame($a->token, VerificationChallenge::issue($u, 'website', 'example.com')->token);
    }

    public function test_instructions_contain_all_three_methods(): void
    {
        $u = $this->user();
        $c = VerificationChallenge::issue($u, 'website', 'example.com');
        $i = $this->verifier()->instructions($c);
        $this->assertStringContainsString($c->token, $i['meta']);
        $this->assertSame('iravatar-verification='.$c->token, $i['dns']['value']);
        $this->assertSame('https://example.com/.well-known/iravatar-verification.txt', $i['file']['path']);
    }
}
