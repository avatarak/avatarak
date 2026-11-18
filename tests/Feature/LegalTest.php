<?php

namespace Tests\Feature;

use App\Mail\SecurityNotice;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Models\VerifiedAccount;
use App\Services\SecurityNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class LegalTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'ali@example.com', string $name = 'ali'): User
    {
        $u = User::factory()->create(['email' => $email, 'password' => 'secret-pass-1']);
        Profile::create(['user_id' => $u->id, 'username' => $name, 'name_en' => 'Ali', 'bio' => 'hello', 'socials' => ['github' => 'ali'], 'links' => [['label' => 'Blog', 'url' => 'https://ali.ir', 'thumb' => str_repeat('a', 40)]]]);
        $u->emails()->create(['email' => $email, 'is_primary' => true, 'verified_at' => now()]);

        return $u;
    }

    // ------------------------------------------------------------------ pages

    public function test_terms_and_privacy_render_in_both_languages_with_the_operators_details(): void
    {
        config(['legal.entity' => 'Acme Ltd', 'legal.contact_email' => 'legal@acme.test']);
        foreach (['terms', 'privacy'] as $page) {
            foreach (['fa', 'en'] as $l) {
                $r = $this->get("/$page?lang=$l")->assertOk()->assertHeader('Content-Language', $l);
                $html = $r->getContent();
                $this->assertStringContainsString('dir="'.($l === 'fa' ? 'rtl' : 'ltr').'"', $html);
                $this->assertStringContainsString('Acme Ltd', $html);
                $this->assertStringContainsString('legal@acme.test', $html);
                $this->assertStringContainsString('mailto:legal%40acme.test', $html);
                $this->assertStringNotContainsString('{{', $html, "$page/$l left a template token");
                $this->assertStringNotContainsString('�', json_encode($html) ?: '');
            }
        }
        $this->get('/terms?lang=en')->assertSee('Terms of Service');
        $this->get('/privacy?lang=fa')->assertSee('سیاست حریم خصوصی');
        $this->get('/legal-nope')->assertNotFound();
    }

    public function test_the_privacy_policy_matches_what_the_software_really_does(): void
    {
        $en = file_get_contents(resource_path('legal/en/privacy.md'));
        foreach (['SHA-256', '90 days', '14 days', 'XSRF', 'two-step', 'Export', 'Delete'] as $needle) {
            $this->assertStringContainsString($needle, $en);
        }
        $this->assertSame(90, config('stats.retention_days'), 'the policy says statistics are kept 90 days');
        $this->assertSame(14, config('backup.keep'), 'the policy says backups are kept about 14 days');
    }

    public function test_user_provided_values_in_the_legal_pages_are_escaped(): void
    {
        config(['legal.entity' => '<script>alert(1)</script>', 'legal.contact_email' => 'a"b@x.test']);
        $html = $this->get('/terms?lang=en')->getContent();
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_legal_pages_are_public_and_the_names_are_reserved(): void
    {
        $this->get('/terms')->assertOk();
        $this->postJson('/register', ['name' => 'x', 'email' => 'q@x.ir', 'password' => 'secret-pass-1', 'username' => 'terms'])->assertStatus(422)->assertJsonValidationErrors('username');
        $this->postJson('/register', ['name' => 'x', 'email' => 'q@x.ir', 'password' => 'secret-pass-1', 'username' => 'privacy'])->assertStatus(422);
    }

    // ------------------------------------------------------------------ acceptance

    public function test_signing_up_requires_accepting_the_terms_when_enabled(): void
    {
        config(['legal.require_acceptance' => true]);
        $base = ['name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'secret-pass-1', 'username' => 'ali'];

        $this->postJson('/register', $base)->assertStatus(422)->assertJsonValidationErrors('accept_terms');
        $this->postJson('/register', $base + ['accept_terms' => false])->assertStatus(422);
        $this->assertSame(0, User::count());

        $this->postJson('/register', $base + ['accept_terms' => true])->assertCreated();
        $this->assertNotNull(User::first()->terms_accepted_at);
    }

    public function test_acceptance_can_be_switched_off(): void
    {
        $this->postJson('/register', ['name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'secret-pass-1', 'username' => 'ali'])->assertCreated();
        $this->assertNull(User::first()->terms_accepted_at);
    }

    // ------------------------------------------------------------------ export

    public function test_the_export_contains_my_data_and_my_photos_but_no_secrets(): void
    {
        Storage::fake('local');
        $u = $this->user();
        $u->update(['name' => 'Ali Reza']);
        $this->actingAs($u)->postJson('/account/avatars', ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)])->assertCreated();
        VerifiedAccount::create(['user_id' => $u->id, 'network' => 'github', 'identifier' => 'ali', 'external_id' => '9', 'method' => 'gist', 'verified_at' => now()]);
        [, $key] = Site::createWithKey(['user_id' => $u->id, 'name' => 'Shop', 'domain' => 'shop.ir']);
        DB::table('daily_stats')->insert(['user_id' => $u->id, 'date' => now()->toDateString(), 'metric' => 'avatar', 'count' => 5]);
        $u->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => ['hash1']])->save();
        $other = $this->user('bob@example.com', 'bob');                                  // someone else's data must never leak in
        $this->actingAs($u);

        $this->postJson('/account/export', ['password' => 'wrong'])->assertStatus(422);
        $r = $this->post('/account/export', ['password' => 'secret-pass-1'], ['Accept' => 'application/zip'])->assertOk();
        $this->assertSame('application/zip', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('avatarak-data-ali.zip', $r->headers->get('Content-Disposition'));
        $this->assertSame('no-store, private', $r->headers->get('Cache-Control'));

        $tmp = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($tmp, $r->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $names = array_map(fn ($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        $this->assertContains('data.json', $names);
        $this->assertContains('README.txt', $names);
        $this->assertContains('avatars/photo-1.jpg', $names);
        $this->assertNotFalse(imagecreatefromstring($zip->getFromName('avatars/photo-1.jpg')), 'a real image');

        $raw = $zip->getFromName('data.json');
        $d = json_decode($raw, true);
        $this->assertSame(['ali@example.com', 'Ali Reza'], [$d['account']['email'], $d['account']['name']]);
        $this->assertSame('ali', $d['profile']['username']);
        $this->assertSame(['github' => 'ali'], $d['profile']['socials']);
        $this->assertSame([['label' => 'Blog', 'url' => 'https://ali.ir']], $d['profile']['links'], 'thumbnail keys are internal');
        $this->assertSame('github', $d['verified_accounts'][0]['network']);
        $this->assertSame(['Shop', 'shop.ir'], [$d['sites'][0]['name'], $d['sites'][0]['domain']]);
        $this->assertSame(5, $d['statistics']['daily'][0]['count']);
        $this->assertTrue($d['account']['two_factor_enabled']);

        foreach ([$key, 'JBSWY3DPEHPK3PXP', 'hash1', $u->password, 'api_key_hash', 'bob@example.com', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, "must not contain $secret");
        }
        $zip->close();
        @unlink($tmp);
    }

    public function test_the_export_needs_login_and_is_throttled(): void
    {
        $this->postJson('/account/export')->assertStatus(401);
        $u = $this->user();
        $this->actingAs($u);
        for ($i = 0; $i < 3; $i++) {
            $this->post('/account/export', ['password' => 'secret-pass-1'])->assertOk();
        }
        $this->postJson('/account/export', ['password' => 'secret-pass-1'])->assertStatus(429);
    }

    // ------------------------------------------------------------------ security notices

    private function queued(string $kind): bool
    {
        return Mail::queued(SecurityNotice::class, fn ($m) => $m->kind === $kind)->isNotEmpty();
    }

    public function test_changing_the_password_sends_a_notice(): void
    {
        Mail::fake();
        $u = $this->user();
        $this->actingAs($u)->patchJson('/account/password', ['current_password' => 'secret-pass-1', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertOk();
        $this->assertTrue($this->queued('password_changed'));
        Mail::assertQueued(SecurityNotice::class, fn ($m) => $m->hasTo('ali@example.com'));
    }

    public function test_a_new_device_is_announced_but_the_first_ever_sign_in_and_known_devices_are_not(): void
    {
        Mail::fake();
        $u = $this->user();
        $chrome = ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537', 'REMOTE_ADDR' => '203.0.113.10'];
        $login = fn (array $h) => $this->withServerVariables(['REMOTE_ADDR' => $h['REMOTE_ADDR']])->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1'], ['User-Agent' => $h['User-Agent']])->assertOk();

        $login($chrome);
        $this->assertFalse($this->queued('new_device'), 'the very first sign-in is not "new"');
        $this->assertSame(1, DB::table('known_devices')->count());

        $this->postJson('/logout');
        $login($chrome);
        $login(['User-Agent' => $chrome['User-Agent'], 'REMOTE_ADDR' => '203.0.113.99']);      // same network (/24): still the same device
        $this->assertFalse($this->queued('new_device'));

        $login(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1', 'REMOTE_ADDR' => '198.51.100.4']);
        Mail::assertQueued(SecurityNotice::class, fn ($m) => $m->kind === 'new_device' && $m->context['device'] === 'Safari · iOS' && $m->context['ip'] === '198.51.100.4');
        $this->assertSame(2, DB::table('known_devices')->count());
        $this->assertSame(1, Mail::queued(SecurityNotice::class)->count());
    }

    public function test_a_failing_mail_server_never_breaks_the_action(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $this->user();
        SecurityNotices::send(User::first(), 'password_changed');
        $this->assertTrue(true);
    }

    public function test_the_rendered_notice_is_localised_and_explains_what_to_do(): void
    {
        app()->setLocale('fa');
        $html = (new SecurityNotice('new_device', ['device' => 'Chrome · Windows', 'ip' => '1.2.3.4', 'when' => '2026-11-20 10:00 UTC']))->render();
        $this->assertStringContainsString('ورود از دستگاه تازه', $html);
        $this->assertStringContainsString('1.2.3.4', $html);
        $this->assertStringContainsString('اگر این کار از طرف شما نبود', $html);

        app()->setLocale('en');
        $en = (new SecurityNotice('account_suspended', ['reason' => 'Spam links']))->render();
        $this->assertStringContainsString('Spam links', $en);
        $this->assertStringNotContainsString('If this was not you', $en, 'not relevant to a suspension');
        foreach (SecurityNotice::KINDS as $k) {
            $this->assertNotEmpty((new SecurityNotice($k))->render(), $k);
        }
    }

    public function test_two_step_changes_and_suspensions_send_notices(): void
    {
        Mail::fake();
        $u = $this->user();
        $admin = $this->user('boss@example.com', 'boss');
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->postJson("/admin/api/users/{$u->id}/suspend", ['reason' => 'Spam'])->assertOk();
        Mail::assertQueued(SecurityNotice::class, fn ($m) => $m->kind === 'account_suspended' && $m->context['reason'] === 'Spam' && $m->hasTo('ali@example.com'));
        $this->postJson("/admin/api/users/{$u->id}/unsuspend")->assertOk();
        $this->assertTrue($this->queued('account_unsuspended'));

        $this->actingAs($u);
        $secret = str_replace(' ', '', $this->postJson('/account/2fa/setup', ['password' => 'secret-pass-1'])->json('secret'));
        $this->postJson('/account/2fa/confirm', ['code' => \App\Services\Totp::code($secret, \App\Services\Totp::currentStep())])->assertOk();
        $this->assertTrue($this->queued('two_factor_enabled'));
        $this->postJson('/account/2fa/disable', ['password' => 'secret-pass-1', 'code' => \App\Services\Totp::code($secret, \App\Services\Totp::currentStep() + 1)])->assertOk();
        $this->assertTrue($this->queued('two_factor_disabled'));
        $this->assertSame(2, AuditLog::count());
    }

    public function test_unknown_notice_kinds_are_ignored(): void
    {
        Mail::fake();
        SecurityNotices::send($this->user(), 'definitely_not_a_kind');
        Mail::assertNothingQueued();
    }

    public function test_notices_go_to_the_primary_verified_address(): void
    {
        Mail::fake();
        $u = $this->user();
        $u->emails()->update(['is_primary' => false]);
        $u->emails()->create(['email' => 'work@example.com', 'is_primary' => true, 'verified_at' => now()]);
        SecurityNotices::send($u, 'password_changed');
        Mail::assertQueued(SecurityNotice::class, fn ($m) => $m->hasTo('work@example.com'));
    }

    public function test_stale_device_records_are_pruned(): void
    {
        $u = $this->user();
        DB::table('known_devices')->insert([['user_id' => $u->id, 'fingerprint' => 'old', 'last_seen_at' => now()->subDays(500), 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $u->id, 'fingerprint' => 'new', 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]]);
        $this->assertSame(1, SecurityNotices::prune());
        $this->assertSame(['new'], DB::table('known_devices')->pluck('fingerprint')->all());
    }
}
