<?php

namespace Tests\Feature;

use App\Mail\LoginCode;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CodeLoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'ali@example.com'): User
    {
        $u = User::factory()->create(['email' => $email, 'password' => 'old-password-1']);
        Profile::create(['user_id' => $u->id, 'username' => 'ali']);
        $u->emails()->create(['email' => $email, 'is_primary' => true]);

        return $u;
    }

    private function sendCode(string $email = 'ali@example.com'): ?string
    {
        $code = null;
        Mail::fake();
        $this->postJson('/login/code', ['email' => $email])->assertOk();
        Mail::assertSent(LoginCode::class, function (LoginCode $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        return $code;
    }

    public function test_code_signs_you_in_and_verifies_the_address(): void
    {
        $u = $this->user();
        $code = $this->sendCode();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->postJson('/login/code/verify', ['email' => 'ALI@example.com', 'code' => $code])->assertOk();
        $this->assertAuthenticatedAs($u);
        $this->assertNotNull($u->emails()->first()->verified_at, 'the mailbox is proven by the code');
        $this->assertNotNull($u->fresh()->last_login_at);
    }

    public function test_code_works_once_only(): void
    {
        $this->user();
        $code = $this->sendCode();
        $this->postJson('/login/code/verify', ['email' => 'ali@example.com', 'code' => $code])->assertOk();
        $this->postJson('/logout');
        $this->postJson('/login/code/verify', ['email' => 'ali@example.com', 'code' => $code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_persian_digits_are_accepted(): void
    {
        $this->user();
        $code = $this->sendCode();
        $this->postJson('/login/code/verify', ['email' => 'ali@example.com', 'code' => strtr($code, array_combine(str_split('0123456789'), mb_str_split('۰۱۲۳۴۵۶۷۸۹')))])->assertOk();
    }

    public function test_unknown_address_gets_the_same_answer_and_no_mail(): void
    {
        Mail::fake();
        $a = $this->postJson('/login/code', ['email' => 'nobody@example.com'])->assertOk()->json();
        Mail::assertNothingSent();
        $this->user();
        $b = $this->postJson('/login/code', ['email' => 'ali@example.com'])->assertOk()->json();
        $this->assertSame($a, $b, 'no account enumeration');
    }

    public function test_wrong_codes_burn_the_code_after_five_tries(): void
    {
        $this->user();
        $code = $this->sendCode();
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < LoginCodeService::MAX_ATTEMPTS; $i++) {
            $this->postJson('/login/code/verify', ['email' => 'ali@example.com', 'code' => $wrong])->assertStatus(422);
        }
        $this->postJson('/login/code/verify', ['email' => 'ali@example.com', 'code' => $code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->user();
        $code = $this->sendCode();
        DB::table('login_codes')->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/login/code/verify', ['email' => 'ali@example.com', 'code' => $code])->assertStatus(422);
    }

    public function test_resend_is_rate_limited_per_address(): void
    {
        $this->user();
        Mail::fake();
        $this->postJson('/login/code', ['email' => 'ali@example.com'])->assertOk();
        $this->postJson('/login/code', ['email' => 'ali@example.com'])->assertOk();
        Mail::assertSent(LoginCode::class, 1);
    }

    public function test_only_a_hash_of_the_code_is_stored(): void
    {
        $this->user();
        $code = $this->sendCode();
        $this->assertSame(64, strlen(DB::table('login_codes')->value('code_hash')));
        $this->assertNotSame($code, DB::table('login_codes')->value('code_hash'));
    }

    public function test_a_verified_secondary_address_also_works(): void
    {
        $u = $this->user();
        $u->emails()->create(['email' => 'work@example.com', 'verified_at' => now()]);
        $code = $this->sendCode('work@example.com');
        $this->postJson('/login/code/verify', ['email' => 'work@example.com', 'code' => $code])->assertOk();
        $this->assertAuthenticatedAs($u);
    }

    public function test_an_unverified_secondary_address_cannot_be_used_to_sign_in(): void
    {
        $u = $this->user();
        $u->emails()->create(['email' => 'claimed@example.com']);     // claimed, not proven
        Mail::fake();
        $this->postJson('/login/code', ['email' => 'claimed@example.com'])->assertOk();
        Mail::assertNothingSent();
    }

    public function test_suspended_users_cannot_sign_in_by_code_or_password(): void
    {
        $u = $this->user();
        $u->forceFill(['suspended_at' => now()])->save();
        Mail::fake();
        $this->postJson('/login/code', ['email' => 'ali@example.com'])->assertOk();
        Mail::assertNothingSent();
        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'old-password-1'])->assertStatus(403);
        $this->assertGuest();
    }

    public function test_suspending_signs_out_an_existing_session(): void
    {
        $u = $this->user();
        $this->actingAs($u)->getJson('/account')->assertOk();
        $u->forceFill(['suspended_at' => now()])->save();
        $this->getJson('/account')->assertStatus(401);
    }

    public function test_password_can_be_changed_with_the_current_one(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        $this->patchJson('/account/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertStatus(422);
        $this->patchJson('/account/password', ['current_password' => 'old-password-1', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-1', $u->fresh()->password));
    }

    public function test_after_a_code_sign_in_the_password_can_be_reset_without_the_old_one(): void
    {
        $u = $this->user();
        $code = $this->sendCode();
        $this->postJson('/login/code/verify', ['email' => 'ali@example.com', 'code' => $code])->assertOk();
        $this->patchJson('/account/password', ['password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertOk();
        $this->postJson('/logout');
        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'brand-new-pass-1'])->assertOk();
    }

    public function test_short_passwords_and_mismatched_confirmation_are_rejected(): void
    {
        $this->actingAs($this->user());
        $this->patchJson('/account/password', ['current_password' => 'old-password-1', 'password' => 'short', 'password_confirmation' => 'short'])->assertStatus(422);
        $this->patchJson('/account/password', ['current_password' => 'old-password-1', 'password' => 'long-enough-1', 'password_confirmation' => 'different-1'])->assertStatus(422);
    }
}
