<?php

namespace Tests\Feature;

use App\Models\CustomDomain;
use App\Models\Profile;
use App\Models\User;
use App\Services\DomainVerifier;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'ali@example.com', string $name = 'ali', ?string $plan = 'pro'): User
    {
        $u = User::factory()->create(['email' => $email]);
        Profile::create(['user_id' => $u->id, 'username' => $name, 'name_en' => ucfirst($name), 'headline' => 'Engineer']);
        $u->emails()->create(['email' => $email, 'is_primary' => true, 'verified_at' => now()]);
        if ($plan) {
            app(PlanService::class)->grant($u, $plan, 30, 'admin');
        }

        return $u;
    }

    private function verified(User $u, string $host = 'ali-site.org'): CustomDomain
    {
        return CustomDomain::create(['user_id' => $u->id, 'host' => $host, 'token' => 'tok', 'verified_at' => now()]);
    }

    private function resolver(array $txt = [], array $cname = [], array $a = []): void
    {
        $this->app->singleton(DomainVerifier::class, function () use ($txt, $cname, $a) {
            $v = new DomainVerifier;
            $v->txtResolver = fn ($h) => $txt[$h] ?? [];
            $v->cnameResolver = fn ($h) => $cname[$h] ?? [];
            $v->aResolver = fn ($h) => $a[$h] ?? [];

            return $v;
        });
    }

    // ------------------------------------------------------------------ normalising

    public static function hosts(): array
    {
        return [['https://www.My-Site.com/path?x', 'my-site.com'], ['MYSITE.ir', 'mysite.ir'], ['blog.my-site.org', 'blog.my-site.org'],
            ['localhost', null], ['127.0.0.1', null], ['10.0.0.1', null], ['not a domain', null], ['', null], ['shop.local', null], ['a.test', null],
            ['hidden.onion', null], ['localhost:8000', null]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hosts')]
    public function test_normalisation_keeps_only_plain_public_domains(string $in, ?string $out): void
    {
        $this->assertSame($out, DomainVerifier::normalize($in));
    }

    public function test_our_own_domain_and_anything_under_it_can_never_be_claimed(): void
    {
        config(['app.url' => 'https://avatarak.ir']);
        foreach (['avatarak.ir', 'www.avatarak.ir', 'evil.avatarak.ir', 'login.avatarak.ir'] as $h) {
            $this->assertNull(DomainVerifier::normalize($h), $h);
        }
        $this->assertSame('avatarak.com', DomainVerifier::normalize('avatarak.com'));
        $this->assertSame('notavatarak.ir', DomainVerifier::normalize('notavatarak.ir'));
    }

    // ------------------------------------------------------------------ claiming and verifying

    public function test_the_plan_decides_whether_and_how_many_domains_you_can_add(): void
    {
        $free = $this->user('free@example.com', 'freeuser', null);
        $this->actingAs($free)->postJson('/account/domains', ['host' => 'a-site.org'])->assertCreated();   // Free allows one
        $this->postJson('/account/domains', ['host' => 'b-site.org'])->assertStatus(422);
        $this->getJson('/account/domains')->assertOk()->assertJsonPath('allowed', 1);

        $pro = $this->user('pro@example.com', 'prouser');
        $this->actingAs($pro)->postJson('/account/domains', ['host' => 'one-site.org'])->assertCreated();
        $this->postJson('/account/domains', ['host' => 'two-site.org'])->assertCreated();                // Pro allows three
        $this->postJson('/account/domains', ['host' => 'three-site.org'])->assertCreated();
        $this->postJson('/account/domains', ['host' => 'four-site.org'])->assertStatus(422);
        $this->postJson('/account/domains', ['host' => 'ONE-site.org'])->assertCreated();                // the same one again is not a second domain

        $biz = $this->user('biz@example.com', 'bizuser', 'business');
        $this->actingAs($biz);
        foreach (range(1, 5) as $i) {
            $this->postJson('/account/domains', ['host' => "biz$i-site.org"])->assertCreated();
        }
        $this->postJson('/account/domains', ['host' => 'biz6-site.org'])->assertStatus(422);
    }

    public function test_bad_hosts_are_refused_and_a_host_belongs_to_one_account(): void
    {
        $a = $this->user();
        $b = $this->user('b@example.com', 'bob');
        $this->actingAs($a);
        foreach (['localhost', '127.0.0.1', 'not a domain', '', 'x.local'] as $h) {
            $this->postJson('/account/domains', ['host' => $h])->assertStatus(422);
        }
        $this->postJson('/account/domains', ['host' => 'claimed-site.org'])->assertCreated();
        $this->actingAs($b)->postJson('/account/domains', ['host' => 'www.claimed-site.org'])->assertStatus(422)->assertJsonPath('message', __('app.domain_taken'));
        $this->assertSame(1, CustomDomain::count());
    }

    public function test_instructions_ownership_check_and_the_pointing_hint(): void
    {
        config(['domains.target' => 'domains.avatarak.test.org']);
        $u = $this->user();
        $d = $this->actingAs($u)->postJson('/account/domains', ['host' => 'ali-site.org'])->assertCreated();
        $token = CustomDomain::first()->token;
        $this->assertSame(['TXT', '_avatarak.ali-site.org', "avatarak-domain=$token"], [$d->json('instructions.txt.type'), $d->json('instructions.txt.name'), $d->json('instructions.txt.value')]);
        $this->assertSame(['CNAME', 'domains.avatarak.test.org'], [$d->json('instructions.route.type'), $d->json('instructions.route.value')]);
        $id = $d->json('id');

        $this->resolver();                                                                                     // nothing in DNS yet
        $this->postJson("/account/domains/$id/check")->assertOk()->assertJson(['verified' => false, 'pointed' => false]);
        $this->assertNull(CustomDomain::first()->verified_at);

        $this->resolver(['_avatarak.ali-site.org' => ['avatarak-domain=WRONG']]);
        $this->postJson("/account/domains/$id/check")->assertJson(['verified' => false]);

        $this->resolver(['_avatarak.ali-site.org' => ["  avatarak-domain=$token "]], ['ali-site.org' => ['domains.avatarak.test.org.']]);
        $this->postJson("/account/domains/$id/check")->assertOk()->assertJson(['verified' => true, 'pointed' => true])->assertJsonPath('domain.verified', true);
        $this->assertNotNull(CustomDomain::first()->verified_at);

        // once verified it stays verified even if the TXT record is removed later
        $this->resolver();
        $this->postJson("/account/domains/$id/check")->assertJson(['verified' => true, 'pointed' => false]);
    }

    public function test_pointing_can_also_be_an_a_record_to_the_same_server(): void
    {
        config(['domains.target' => 'domains.avatarak.test.org']);
        $v = new DomainVerifier;
        $v->cnameResolver = fn () => [];
        $v->aResolver = fn ($h) => ['domains.avatarak.test.org' => ['203.0.113.5'], 'ali-site.org' => ['203.0.113.5']][$h] ?? [];
        $this->assertTrue($v->pointed('ali-site.org'));
        $v->aResolver = fn ($h) => ['domains.avatarak.test.org' => ['203.0.113.5'], 'ali-site.org' => ['198.51.100.9']][$h] ?? [];
        $this->assertFalse($v->pointed('ali-site.org'));
    }

    public function test_checks_are_capped_and_domains_can_be_removed_but_only_by_their_owner(): void
    {
        $a = $this->user();
        $b = $this->user('b@example.com', 'bob');
        $this->resolver();
        $this->actingAs($a);
        $id = $this->postJson('/account/domains', ['host' => 'ali-site.org'])->json('id');

        CustomDomain::query()->update(['checks' => config('domains.max_checks')]);
        $this->postJson("/account/domains/$id/check")->assertJson(['verified' => false, 'error' => 'too_many_attempts']);

        $this->actingAs($b)->postJson("/account/domains/$id/check")->assertNotFound();
        $this->deleteJson("/account/domains/$id")->assertNotFound();
        $this->actingAs($a)->deleteJson("/account/domains/$id")->assertOk();
        $this->assertSame(0, CustomDomain::count());
    }

    // ------------------------------------------------------------------ serving

    public function test_a_verified_domain_shows_the_profile_and_its_avatar_and_exports(): void
    {
        $u = $this->user();
        $this->verified($u);

        $page = $this->get('http://ali-site.org/')->assertOk();
        $this->assertStringContainsString('Ali', $page->getContent());
        $this->assertStringContainsString('Engineer', $page->getContent());
        $this->get('http://www.ali-site.org/')->assertOk();                                       // www works too
        $this->get('http://ali-site.org/avatar')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->getJson('http://ali-site.org/profile.json')->assertOk()->assertJsonPath('username', 'ali');
        $this->get('http://ali-site.org/profile.vcf')->assertOk()->assertHeader('Content-Type', 'text/vcard; charset=utf-8');
        $this->get('http://ali-site.org/profile.qr')->assertOk();
        $this->get('http://ali-site.org/profile.card')->assertOk();
        $this->get('http://ali-site.org/ali/avatar?s=512')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');   // the page's own <img> uses this form
        $this->get('http://ali-site.org/bob/avatar')->assertNotFound();                                                 // only the owner's
        $this->assertStringContainsString('/ali/avatar', $page->getContent());
    }

    public function test_nothing_else_of_the_app_is_reachable_through_a_customers_domain(): void
    {
        $u = $this->user();
        $this->verified($u);
        $this->actingAs($u);

        foreach (['/account', '/admin', '/admin/api/overview', '/login', '/register', '/logout', '/docs', '/terms', '/pricing.json', '/ali', '/ali.json', '/bob', '/account/billing',
            '/billing/callback', '/_internal/domain-check', '/profile.exe', '/avatar/'.str_repeat('a', 64) === '' ? '/x' : '/up/ready', '/ali/print'] as $path) {
            $this->get('http://ali-site.org'.$path)->assertNotFound();
        }
        $this->postJson('http://ali-site.org/login', ['email' => 'ali@example.com', 'password' => 'x'])->assertNotFound();
        $this->postJson('http://ali-site.org/account/password', [])->assertNotFound();
    }

    public function test_a_claim_that_was_never_proven_serves_nothing_and_lookalikes_get_no_special_treatment(): void
    {
        $u = $this->user();
        $this->verified($u);
        CustomDomain::create(['user_id' => $u->id, 'host' => 'pending-site.org', 'token' => 't']);        // claimed but never verified
        $this->get('http://pending-site.org/')->assertNotFound();
        $this->get('http://pending-site.org/account')->assertNotFound();

        // a host nobody registered is just another name of this server: it behaves as the app does, and never as ali's profile
        $r = $this->get('http://ali-site.org.attacker.net/')->assertOk();
        $this->assertStringNotContainsString('Engineer', $r->getContent());
        $this->get('http://evil-ali-site.org/')->assertOk()->assertDontSee('Engineer');
    }

    public function test_the_domain_stops_when_the_plan_ends_the_profile_is_hidden_or_the_owner_is_suspended(): void
    {
        $u = $this->user();
        $this->verified($u);
        $this->get('http://ali-site.org/')->assertOk();

        $u->profile->update(['show_profile' => false]);
        Cache::flush();
        $this->get('http://ali-site.org/')->assertNotFound();
        $u->profile->update(['show_profile' => true]);
        $this->get('http://ali-site.org/')->assertOk();

        $u->forceFill(['suspended_at' => now()])->save();
        $this->get('http://ali-site.org/')->assertNotFound();
        $u->forceFill(['suspended_at' => null])->save();

        $this->travel(31)->days();                                                                         // Pro ran out: Free still includes one custom domain
        $this->get('http://ali-site.org/')->assertOk();
    }

    public function test_removing_a_domain_takes_effect_immediately_despite_caching(): void
    {
        $u = $this->user();
        $d = $this->verified($u);
        $this->get('http://ali-site.org/')->assertOk()->assertSee('Engineer');                            // now cached
        $d->delete();
        $this->get('http://ali-site.org/')->assertDontSee('Engineer');                                   // no longer the profile, at once
        $this->get('http://ali-site.org/ali')->assertOk();                                               // just another name of this server again
    }

    public function test_our_own_address_still_works_as_before(): void
    {
        $this->user();
        $this->get('/')->assertOk()->assertSee('id="app"', false);
        $this->get('/ali')->assertOk();
        $this->get('/avatar')->assertNotFound();
        $this->get('/profile.json')->assertNotFound();
    }

    // ------------------------------------------------------------------ the TLS "ask" endpoint

    public function test_the_certificate_check_endpoint_is_secret_and_only_approves_served_domains(): void
    {
        $u = $this->user();
        $this->verified($u);
        CustomDomain::create(['user_id' => $u->id, 'host' => 'unverified-site.org', 'token' => 't']);

        $this->get('/_internal/domain-check?domain=ali-site.org')->assertNotFound();                      // no key configured: the endpoint is off
        config(['domains.check_key' => 's3cret-key']);
        $this->get('/_internal/domain-check?domain=ali-site.org')->assertNotFound();
        $this->get('/_internal/domain-check?key=wrong&domain=ali-site.org')->assertNotFound();
        $this->get('/_internal/domain-check?key=s3cret-key&domain=ali-site.org')->assertOk()->assertSee('ok');
        $this->get('/_internal/domain-check?key=s3cret-key&domain=WWW.Ali-Site.org')->assertOk();
        $this->get('/_internal/domain-check?key=s3cret-key&domain=unverified-site.org')->assertNotFound();
        $this->get('/_internal/domain-check?key=s3cret-key&domain=unknown-site.org')->assertNotFound();
        $this->get('/_internal/domain-check?key=s3cret-key&domain=localhost')->assertNotFound();
        $this->get('/_internal/domain-check?key=s3cret-key')->assertNotFound();

        $u->forceFill(['suspended_at' => now()])->save();
        $this->get('/_internal/domain-check?key=s3cret-key&domain=ali-site.org')->assertNotFound();       // no certificates for suspended owners
    }

    public function test_deleting_the_account_removes_the_domains(): void
    {
        $u = $this->user();
        $this->verified($u);
        $u->delete();
        $this->assertSame(0, CustomDomain::count());
    }
}
