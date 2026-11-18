<?php

namespace Tests\Feature;

use App\Mail\LoginCode;
use App\Models\Profile;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ the algorithm itself

    public function test_rfc_6238_test_vectors(): void
    {
        // RFC 6238 appendix B, SHA-1, secret "12345678901234567890"; the 6-digit codes are the last 6 of the 8-digit ones
        $secret = Totp::base32Encode('12345678901234567890');
        foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037', 20000000000 => '353130'] as $time => $code) {
            $this->assertSame($code, Totp::code($secret, Totp::currentStep($time)), "t=$time");
        }
    }

    public function test_base32_round_trip_and_leniency(): void
    {
        $raw = random_bytes(20);
        $b32 = Totp::base32Encode($raw);
        $this->assertSame($raw, Totp::base32Decode($b32));
        $this->assertSame($raw, Totp::base32Decode(strtolower(chunk_split($b32, 4, ' '))), 'case and spaces do not matter');
        $this->assertSame(32, strlen(Totp::generateSecret()));
        $this->expectException(\InvalidArgumentException::class);
        Totp::base32Decode('not*base32!');
    }

    public function test_verify_accepts_neighbouring_steps_only_and_each_step_once(): void
    {
        $s = Totp::generateSecret();
        $t = 1_700_000_000;
        $step = Totp::currentStep($t);
        $this->assertSame($step, Totp::verify($s, Totp::code($s, $step), null, $t));
        $this->assertSame($step - 1, Totp::verify($s, Totp::code($s, $step - 1), null, $t), 'a code from 30 s ago');
        $this->assertSame($step + 1, Totp::verify($s, Totp::code($s, $step + 1), null, $t), 'a clock a little fast');
        $this->assertNull(Totp::verify($s, Totp::code($s, $step - 2), null, $t));
        $this->assertNull(Totp::verify($s, Totp::code($s, $step + 2), null, $t));
        $this->assertNull(Totp::verify($s, Totp::code($s, $step), $step, $t), 'replaying the same step is refused');
        $this->assertNull(Totp::verify($s, '12345', null, $t));
        $this->assertNull(Totp::verify($s, '', null, $t));
        $this->assertSame($step, Totp::verify($s, strtr(Totp::code($s, $step), array_combine(str_split('0123456789'), mb_str_split('۰۱۲۳۴۵۶۷۸۹'))), null, $t), 'Persian digits');
        $this->assertSame($step, Totp::verify($s, substr(Totp::code($s, $step), 0, 3).' '.substr(Totp::code($s, $step), 3), null, $t), 'a space in the middle');
    }

    public function test_otpauth_uri(): void
    {
        $u = Totp::uri('ABCDEFGH', 'ali@example.com', 'Avatarak.ir');
        $this->assertStringStartsWith('otpauth://totp/Avatarak.ir%3Aali%40example.com?', $u);
        $this->assertStringContainsString('secret=ABCDEFGH', $u);
        $this->assertStringContainsString('issuer=Avatarak.ir', $u);
        $this->assertStringContainsString('period=30', $u);
    }

    // ------------------------------------------------------------------ enrolment

    private function user(): User
    {
        $u = User::factory()->create(['email' => 'ali@example.com', 'password' => 'secret-pass-1']);
        Profile::create(['user_id' => $u->id, 'username' => 'ali']);
        $u->emails()->create(['email' => 'ali@example.com', 'is_primary' => true, 'verified_at' => now()]);

        return $u;
    }

    private function enable(User $u): array
    {
        $this->actingAs($u);
        $setup = $this->postJson('/account/2fa/setup', ['password' => 'secret-pass-1'])->assertOk();
        $secret = str_replace(' ', '', $setup->json('secret'));
        $codes = $this->postJson('/account/2fa/confirm', ['code' => Totp::code($secret, Totp::currentStep())])->assertOk()->json('recovery_codes');
        auth()->logout();
        $this->flushSession();

        return [$secret, $codes];
    }

    public function test_enrolment_needs_the_password_then_a_real_code_and_hands_out_recovery_codes_once(): void
    {
        $u = $this->user();
        $this->actingAs($u);

        $this->postJson('/account/2fa/setup', ['password' => 'wrong'])->assertStatus(422)->assertJsonValidationErrors('password');
        $r = $this->postJson('/account/2fa/setup', ['password' => 'secret-pass-1'])->assertOk();
        $this->assertStringContainsString('<svg', $r->json('qr'));
        $this->assertStringContainsString('otpauth://totp/', $r->json('uri'));
        $secret = str_replace(' ', '', $r->json('secret'));
        $this->assertFalse($u->fresh()->hasTwoFactor(), 'not on until confirmed');

        $this->postJson('/account/2fa/confirm', ['code' => '000000'])->assertStatus(422);
        $this->assertFalse($u->fresh()->hasTwoFactor());

        $codes = $this->postJson('/account/2fa/confirm', ['code' => Totp::code($secret, Totp::currentStep())])->assertOk()->json('recovery_codes');
        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));
        $this->assertMatchesRegularExpression('/^[a-z2-9]{5}-[a-z2-9]{5}$/', $codes[0]);
        $this->assertTrue($u->fresh()->hasTwoFactor());
        $this->getJson('/account/2fa')->assertJson(['enabled' => true, 'recovery_codes_left' => 10]);
        $this->getJson('/account')->assertJsonPath('two_factor', true);

        $this->postJson('/account/2fa/setup', ['password' => 'secret-pass-1'])->assertStatus(422);     // already on
    }

    public function test_secrets_and_recovery_codes_are_encrypted_and_never_returned(): void
    {
        $u = $this->user();
        [$secret, $codes] = $this->enable($u);

        $raw = DB::table('users')->where('id', $u->id)->first();
        $this->assertStringNotContainsString($secret, $raw->two_factor_secret, 'encrypted at rest');
        $this->assertStringNotContainsString($codes[0], (string) $raw->two_factor_recovery_codes);
        $this->assertStringNotContainsString($secret, json_encode($u->fresh()->toArray()), 'hidden from serialisation');

        $this->actingAs($u);
        foreach (['/account', '/account/2fa'] as $url) {
            $this->assertStringNotContainsString($secret, $this->getJson($url)->getContent());
        }
    }

    // ------------------------------------------------------------------ signing in

    public function test_password_sign_in_stops_at_the_second_step(): void
    {
        $u = $this->user();
        [$secret] = $this->enable($u);

        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1'])->assertOk()->assertExactJson(['two_factor' => true]);
        $this->assertGuest();
        $this->getJson('/account')->assertStatus(401);

        $this->postJson('/login/2fa', ['code' => '000000'])->assertStatus(422);
        $this->assertGuest();

        $step = Totp::currentStep();
        $this->postJson('/login/2fa', ['code' => Totp::code($secret, $step + 1)])->assertOk();           // a code from the next window is fine
        $this->assertAuthenticatedAs($u);
        $this->assertNotNull($u->fresh()->last_login_at);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $u = $this->user();
        [$secret] = $this->enable($u);
        $code = Totp::code($secret, Totp::currentStep() + 1);

        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1']);
        $this->postJson('/login/2fa', ['code' => $code])->assertOk();
        $this->postJson('/logout');

        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1']);
        $this->postJson('/login/2fa', ['code' => $code])->assertStatus(422);       // an observer who saw it cannot reuse it
        $this->assertGuest();
    }

    public function test_recovery_codes_work_once_and_are_counted(): void
    {
        $u = $this->user();
        [, $codes] = $this->enable($u);

        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1']);
        $this->postJson('/login/2fa', ['recovery_code' => strtoupper($codes[0])])->assertOk();    // case and dash do not matter
        $this->assertAuthenticatedAs($u);
        $this->getJson('/account/2fa')->assertJson(['recovery_codes_left' => 9]);
        $this->postJson('/logout');

        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1']);
        $this->postJson('/login/2fa', ['recovery_code' => $codes[0]])->assertStatus(422);         // spent
        $this->postJson('/login/2fa', ['recovery_code' => str_replace('-', '', $codes[1])])->assertOk();
    }

    public function test_the_emailed_code_does_not_bypass_the_second_factor(): void
    {
        $u = $this->user();
        [$secret] = $this->enable($u);
        Mail::fake();
        $this->postJson('/login/code', ['email' => 'ali@example.com'])->assertOk();
        $code = null;
        Mail::assertQueued(LoginCode::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $this->postJson('/login/code/verify', ['email' => 'ali@example.com', 'code' => $code])->assertOk()->assertExactJson(['two_factor' => true]);
        $this->assertGuest();
        $this->postJson('/login/2fa', ['code' => Totp::code($secret, Totp::currentStep() + 1)])->assertOk();
        $this->assertAuthenticatedAs($u);
        // signing in with an emailed code still allows setting a new password right away
        $this->patchJson('/account/password', ['password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertOk();
    }

    public function test_the_second_step_is_throttled_per_account_and_locks_after_five_misses(): void
    {
        $u = $this->user();
        [$secret] = $this->enable($u);
        Cache::flush();
        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login/2fa', ['code' => '000000'])->assertStatus(422);
        }
        $this->postJson('/login/2fa', ['code' => Totp::code($secret, Totp::currentStep() + 1)])->assertStatus(429);   // even the right code: locked
        $this->assertGuest();
    }

    public function test_the_second_step_needs_the_first_and_expires(): void
    {
        $u = $this->user();
        [$secret] = $this->enable($u);

        $this->postJson('/login/2fa', ['code' => Totp::code($secret, Totp::currentStep() + 1)])->assertStatus(422);     // nobody is halfway through
        $this->assertGuest();

        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/login/2fa', ['code' => Totp::code($secret, Totp::currentStep() + 1)])->assertStatus(422);

        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1']);
        $this->travel(6)->minutes();
        $this->postJson('/login/2fa', ['code' => Totp::code($secret, Totp::currentStep() + 1)])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_users_without_two_factor_sign_in_as_before(): void
    {
        $u = $this->user();
        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1'])->assertOk()->assertExactJson(['message' => 'ok']);
        $this->assertAuthenticatedAs($u);
    }

    public function test_a_suspended_user_cannot_finish_the_second_step(): void
    {
        $u = $this->user();
        [$secret] = $this->enable($u);
        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1']);
        $u->forceFill(['suspended_at' => now()])->save();
        $this->postJson('/login/2fa', ['code' => Totp::code($secret, Totp::currentStep() + 1)])->assertStatus(422);
        $this->assertGuest();
    }

    // ------------------------------------------------------------------ turning it off, recovery codes

    public function test_disabling_needs_the_password_and_a_valid_code(): void
    {
        $u = $this->user();
        [$secret, $codes] = $this->enable($u);
        $this->actingAs($u);

        $this->postJson('/account/2fa/disable', ['password' => 'wrong', 'code' => Totp::code($secret, Totp::currentStep() + 1)])->assertStatus(422);
        $this->postJson('/account/2fa/disable', ['password' => 'secret-pass-1', 'code' => '000000'])->assertStatus(422);
        $this->assertTrue($u->fresh()->hasTwoFactor());

        $this->postJson('/account/2fa/disable', ['password' => 'secret-pass-1', 'code' => $codes[2]])->assertOk()->assertJson(['enabled' => false]);   // a recovery code works too
        $u = $u->fresh();
        $this->assertFalse($u->hasTwoFactor());
        $this->assertNull($u->two_factor_secret);
        $this->assertNull($u->two_factor_recovery_codes);
        $this->postJson('/account/2fa/disable', ['password' => 'secret-pass-1', 'code' => '123456'])->assertStatus(422);        // not on any more
    }

    public function test_regenerating_replaces_all_recovery_codes(): void
    {
        $u = $this->user();
        [$secret, $old] = $this->enable($u);
        $this->actingAs($u);

        $this->postJson('/account/2fa/recovery-codes', ['password' => 'secret-pass-1', 'code' => '000000'])->assertStatus(422);
        $new = $this->postJson('/account/2fa/recovery-codes', ['password' => 'secret-pass-1', 'code' => Totp::code($secret, Totp::currentStep() + 1)])->assertOk()->json('recovery_codes');
        $this->assertCount(10, $new);
        $this->assertEmpty(array_intersect($old, $new));

        auth()->logout();
        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1']);
        $this->postJson('/login/2fa', ['recovery_code' => $old[0]])->assertStatus(422);
        $this->postJson('/login/2fa', ['recovery_code' => $new[0]])->assertOk();
    }

    public function test_endpoints_require_login(): void
    {
        foreach ([['GET', '/account/2fa'], ['POST', '/account/2fa/setup'], ['POST', '/account/2fa/confirm'], ['POST', '/account/2fa/disable'], ['POST', '/account/2fa/recovery-codes']] as [$m, $url]) {
            $this->json($m, $url)->assertStatus(401);
        }
    }

    // ------------------------------------------------------------------ admins

    public function test_admins_can_be_forced_to_use_two_factor(): void
    {
        $a = $this->user();
        $a->forceFill(['is_admin' => true])->save();

        $this->actingAs($a)->getJson('/admin/api/overview')->assertOk();
        config(['admin.require_two_factor' => true]);
        $this->getJson('/admin/api/overview')->assertStatus(403)->assertJsonPath('code', 'two_factor_required');

        $this->enable($a);
        $this->actingAs($a->fresh())->getJson('/admin/api/overview')->assertOk();
    }
}
