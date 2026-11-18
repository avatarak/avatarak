<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailAddress;
use App\Models\Profile;
use App\Models\Site;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\LoginCodeService;
use App\Support\ImageGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Regression tests for the findings of the security review (see the commit message for the list). */
class SecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $name = 'u', bool $verified = false): User
    {
        $u = User::factory()->create(['email' => $email, 'password' => 'known-password-1']);
        Profile::create(['user_id' => $u->id, 'username' => $name.$u->id]);
        $u->emails()->create(['email' => $email, 'is_primary' => true, 'verified_at' => $verified ? now() : null, 'verification_token' => $verified ? null : 'tok'.$u->id]);

        return $u;
    }

    // ---------------------------------------------------------------- F1: pre-hijacking of an address

    private function squat(): User
    {
        $attacker = $this->user('victim@example.com', 'squat');                    // registered with the victim's address
        $mine = $attacker->emails()->create(['email' => 'attacker@evil.test', 'verified_at' => now()]);     // a way back in
        [$site] = Site::createWithKey(['user_id' => $attacker->id, 'name' => 's', 'domain' => 'evil.test']);
        $attacker->webhooks()->create(['url' => 'https://93.184.216.34/h', 'secret' => 's', 'events' => ['profile.updated']]);
        $attacker->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $attacker;
    }

    public function test_when_the_real_owner_signs_in_with_a_code_the_squatters_way_in_is_cut(): void
    {
        $a = $this->squat();
        $code = app(LoginCodeService::class)->issue('victim@example.com');
        $this->postJson('/login/code/verify', ['email' => 'victim@example.com', 'code' => $code])->assertOk()->assertJsonMissing(['two_factor' => true]);

        $a->refresh();
        $this->assertFalse(Hash::check('known-password-1', $a->password), 'the squatter\'s password no longer works');
        $this->assertSame(['victim@example.com'], $a->emails()->pluck('email')->all(), 'their extra sign-in address is gone');
        $this->assertNotNull($a->emails()->first()->verified_at);
        $this->assertFalse($a->hasTwoFactor());
        $this->assertSame(0, $a->sites()->count());
        $this->assertSame(0, $a->webhooks()->count());
        $this->getJson('/account')->assertOk()->assertJsonPath('email', 'victim@example.com');              // and the owner is in
    }

    public function test_a_click_on_the_link_from_another_browser_also_cuts_it_but_the_owners_own_browser_does_not(): void
    {
        $a = $this->squat();
        $this->get('/verify-email/tok'.$a->id)->assertOk();                          // nobody signed in: could be the real owner
        $this->assertSame(1, $a->emails()->count());
        $this->assertFalse(Hash::check('known-password-1', $a->fresh()->password));

        // the owner clicking in the browser they registered in keeps everything
        $b = $this->user('honest@example.com', 'honest');
        [$site] = Site::createWithKey(['user_id' => $b->id, 'name' => 's', 'domain' => 'ok.test']);
        $this->actingAs($b)->get('/verify-email/tok'.$b->id)->assertOk();
        $this->assertTrue(Hash::check('known-password-1', $b->fresh()->password));
        $this->assertSame(1, $b->sites()->count());
    }

    public function test_verifying_a_second_address_never_resets_the_account(): void
    {
        $u = $this->user('main@example.com', 'main', verified: true);
        $u->emails()->create(['email' => 'second@example.com', 'verification_token' => 'second-token']);
        $this->get('/verify-email/second-token')->assertOk();
        $this->assertSame(2, $u->emails()->count());
        $this->assertTrue(Hash::check('known-password-1', $u->fresh()->password));
    }

    // ---------------------------------------------------------------- F3: password guessing

    public function test_wrong_passwords_are_throttled_per_address_and_visitor_but_codes_still_work(): void
    {
        $u = $this->user('target@example.com', 'target', verified: true);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'target@example.com', 'password' => 'nope'])->assertStatus(422);
        }
        $this->postJson('/login', ['email' => 'target@example.com', 'password' => 'known-password-1'])->assertStatus(429);        // even the right one waits
        // a different visitor is not locked out by a stranger's guesses
        $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])->postJson('/login', ['email' => 'target@example.com', 'password' => 'known-password-1'])->assertOk();
        // and the emailed-code route is untouched
        $code = app(LoginCodeService::class)->issue('target@example.com');
        $this->postJson('/login/code/verify', ['email' => 'target@example.com', 'code' => $code])->assertOk();
    }

    public function test_guessing_from_many_machines_is_stopped_by_the_per_address_limit(): void
    {
        $this->user('many@example.com', 'many', verified: true);
        for ($i = 0; $i < 40; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.{$i}.5"])->postJson('/login', ['email' => 'many@example.com', 'password' => 'x'.$i])->assertStatus(422);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.1'])->postJson('/login', ['email' => 'many@example.com', 'password' => 'known-password-1'])->assertStatus(429);
    }

    public function test_unknown_addresses_get_the_same_answer_as_wrong_passwords(): void
    {
        $this->user('real@example.com', 'real', verified: true);
        $a = $this->postJson('/login', ['email' => 'real@example.com', 'password' => 'wrong'])->assertStatus(422)->json();
        $b = $this->postJson('/login', ['email' => 'ghost@example.com', 'password' => 'wrong'])->assertStatus(422)->json();
        $this->assertSame($a, $b);
    }

    // ---------------------------------------------------------------- F5: password change signs the others out

    public function test_changing_the_password_signs_out_every_other_session(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->user('pw@example.com', 'pw', verified: true);
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $u->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($u)->patchJson('/account/password', ['current_password' => 'known-password-1', 'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9'])->assertOk();
        $this->assertSame(0, DB::table('sessions')->where('id', 'other-session')->count());
    }

    // ---------------------------------------------------------------- F7 / F8: other guessing

    public function test_bad_api_keys_are_throttled_per_visitor(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/v1/profiles/'.str_repeat('a', 64), ['Authorization' => 'Bearer avk_wrong'.$i])->assertStatus(401);
        }
        $this->getJson('/api/v1/profiles/'.str_repeat('a', 64), ['Authorization' => 'Bearer avk_again'])->assertStatus(429);
    }

    public function test_asking_for_fresh_codes_does_not_buy_fresh_guesses(): void
    {
        $this->user('otp@example.com', 'otp', verified: true);
        $codes = app(LoginCodeService::class);
        for ($i = 0; $i < LoginCodeService::HOURLY_GUESSES; $i++) {
            DB::table('login_codes')->delete();                      // a fresh code each time (as if resent), a wrong guess each time
            $codes->issue('otp@example.com');
            $this->assertFalse($codes->consume('otp@example.com', '000000'.$i === '0000000' ? '111111' : '000001'));
        }
        DB::table('login_codes')->delete();
        $good = $codes->issue('otp@example.com');
        $this->assertFalse($codes->consume('otp@example.com', $good), 'even the right code is refused once the hour\'s guesses are spent');
        RateLimiter::clear('x');
    }

    // ---------------------------------------------------------------- F4: decompression bombs

    private function pngHeader(int $w, int $h): string
    {
        $ihdr = pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr)).pack('N', 0).'IEND'.pack('N', crc32('IEND'));
    }

    public function test_images_that_claim_a_huge_size_are_refused_before_decoding(): void
    {
        $this->assertTrue(ImageGuard::decodable($this->pngHeader(6000, 6000)));
        $this->assertFalse(ImageGuard::decodable($this->pngHeader(6001, 6001)));
        $this->assertFalse(ImageGuard::decodable($this->pngHeader(30000, 30000)));
        $this->assertFalse(ImageGuard::decodable('not an image'));
        $this->assertFalse(app(\App\Services\LinkPreviewService::class)->store('https://x.test/a', $this->pngHeader(30000, 30000)));
    }

    // ---------------------------------------------------------------- F2: browser protections

    public function test_pages_carry_a_csp_with_a_matching_nonce_and_cannot_be_framed(): void
    {
        $r = $this->get('/')->assertOk();
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        preg_match('/script-src [^;]*/', $csp, $sm);
        $this->assertStringNotContainsString("'unsafe-inline'", $sm[0]);                     // scripts: only ours and nonce-marked ones
        preg_match("/nonce-([A-Za-z0-9+\\/=]+)/", $csp, $m);
        $this->assertStringContainsString('<script nonce="'.$m[1].'">window.__BRAND__', $r->getContent());          // the inline script carries it
        $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('strict-origin', $r->headers->get('Referrer-Policy'));
        $this->assertNotSame($m[1], $this->nonceOf($this->get('/')), 'a new nonce for every request');
    }

    private function nonceOf($response): string
    {
        preg_match("/nonce-([A-Za-z0-9+\\/=]+)/", (string) $response->headers->get('Content-Security-Policy'), $m);

        return $m[1] ?? '';
    }

    public function test_the_embeddable_card_stays_frameable_and_data_responses_get_no_page_policy(): void
    {
        Profile::create(['user_id' => User::factory()->create()->id, 'username' => 'cardy', 'name_en' => 'C']);
        $card = $this->get('/cardy.card')->assertOk();
        $this->assertNull($card->headers->get('X-Frame-Options'));
        $this->assertStringContainsString('frame-ancestors *', $card->headers->get('Content-Security-Policy'));
        $json = $this->get('/cardy.json')->assertOk();
        $this->assertNull($json->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $json->headers->get('X-Content-Type-Options'));
    }

    public function test_hsts_only_over_https_in_production(): void
    {
        $this->assertNull($this->get('/')->headers->get('Strict-Transport-Security'));
        $this->app['env'] = 'production';
        $this->assertNotNull($this->get('https://localhost/')->headers->get('Strict-Transport-Security'));
        $this->assertNull($this->get('http://localhost/')->headers->get('Strict-Transport-Security'));
    }

    public function test_the_error_page_back_button_works_under_the_policy(): void
    {
        $r = $this->get('/no/such')->assertNotFound();
        $this->assertStringNotContainsString('onclick=', $r->getContent());
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">document\.querySelectorAll\(\'\[data-back\]\'\)/', $r->getContent());
    }

    // ---------------------------------------------------------------- F6: mail links

    public function test_mail_links_use_the_configured_address_not_the_request_host(): void
    {
        config(['app.url' => 'https://avatarak.example']);
        $u = $this->user('m@example.com', 'm');
        $email = $u->emails()->first();
        $m = new VerifyEmailAddress($email);
        $this->assertStringStartsWith('https://avatarak.example/verify-email/', $m->content()->with['url']);
        $this->get('/', ['Host' => 'evil.test']);                                         // a poisoned request in the same process changes nothing
        $this->assertStringStartsWith('https://avatarak.example/verify-email/', (new VerifyEmailAddress($email))->content()->with['url']);
    }

    // ---------------------------------------------------------------- authorization: one user can never reach another's things

    public function test_nobody_can_reach_somebody_elses_resources(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $a = $this->user('a@example.com', 'owner', verified: true);
        $b = $this->user('b@example.com', 'intruder', verified: true);
        $email = $a->emails()->first();
        [$site] = Site::createWithKey(['user_id' => $a->id, 'name' => 's', 'domain' => 'a.test']);
        $hook = $a->webhooks()->create(['url' => 'https://93.184.216.34/h', 'secret' => 's', 'events' => ['profile.updated']]);
        $domain = \App\Models\CustomDomain::create(['user_id' => $a->id, 'host' => 'a-domain.test', 'token' => 't']);
        $this->actingAs($a);
        $gid = $this->postJson('/account/gallery', ['image' => \Illuminate\Http\UploadedFile::fake()->image('a.jpg', 300, 300)])->json('id');
        $avid = $this->postJson('/account/avatars', ['avatar' => \Illuminate\Http\UploadedFile::fake()->image('a.jpg', 300, 300)])->json('id');
        $vid = DB::table('verified_accounts')->insertGetId(['user_id' => $a->id, 'network' => 'github', 'identifier' => 'a', 'method' => 'oauth', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        auth()->logout();

        $this->actingAs($b);
        foreach ([
            ['patch', "/account/emails/{$email->id}"], ['post', "/account/emails/{$email->id}/primary"], ['post', "/account/emails/{$email->id}/resend"], ['delete', "/account/emails/{$email->id}"],
            ['delete', "/account/sites/{$site->id}"],
            ['patch', "/account/webhooks/{$hook->id}"], ['post', "/account/webhooks/{$hook->id}/secret"], ['post', "/account/webhooks/{$hook->id}/ping"], ['delete', "/account/webhooks/{$hook->id}"],
            ['post', "/account/domains/{$domain->id}/check"], ['delete', "/account/domains/{$domain->id}"],
            ['patch', "/account/gallery/{$gid}"], ['delete', "/account/gallery/{$gid}"], ['get', "/account/gallery/{$gid}/full"],
            ['get', "/account/avatars/{$avid}/image"], ['post', "/account/avatars/{$avid}/activate"], ['delete', "/account/avatars/{$avid}"],
            ['delete', "/account/verifications/{$vid}"],
        ] as [$method, $url]) {
            $this->json($method, $url, ['url' => 'https://93.184.216.34/x', 'caption' => 'x', 'is_public' => true, 'events' => ['profile.updated']])->assertNotFound("$method $url must be 404 for another user");
        }
        $this->postJson('/account/gallery/order', ['order' => [$gid]])->assertStatus(422);
        // nothing of A's changed
        $this->assertSame(1, $a->emails()->count());
        $this->assertSame(1, $a->sites()->count());
        $this->assertSame(1, $a->webhooks()->count());
        $this->assertSame(1, DB::table('verified_accounts')->where('id', $vid)->count());
        $this->assertSame(1, DB::table('gallery_images')->where('id', $gid)->count());
    }

    public function test_a_client_cannot_make_itself_an_admin_or_change_ownership_through_a_profile_update(): void
    {
        $u = $this->user('mass@example.com', 'mass', verified: true);
        $other = $this->user('victim2@example.com', 'v2', verified: true);
        $this->actingAs($u)->patchJson('/account/profile', ['is_admin' => true, 'user_id' => $other->id, 'id' => 999, 'suspended_at' => null, 'password' => 'x', 'headline' => 'ok'])->assertOk();
        $this->assertFalse((bool) $u->fresh()->is_admin);
        $this->assertSame($u->id, $u->profile->fresh()->user_id);
        $this->actingAs($u)->patchJson('/account/appearance', ['user_id' => $other->id, 'accent_color' => '#112233'])->assertOk();
        $this->assertSame($u->id, $u->profile->fresh()->user_id);
        $this->actingAs($u)->postJson('/register', [])->assertStatus(422);
    }
}
