<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailAddress;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'ali@example.com', string $username = 'ali'): User
    {
        $this->postJson('/register', ['name' => 'Ali', 'email' => $email, 'password' => 'secret-pass-1', 'username' => $username])
            ->assertCreated();

        return User::where('email', $email)->first();
    }

    private function verifyPrimary(User $u): UserEmail
    {
        $e = $u->emails()->where('is_primary', true)->first();
        $this->get(route('email.verify', $e->verification_token))->assertOk();

        return $e->fresh();
    }

    public function test_register_creates_profile_primary_email_and_sends_mail(): void
    {
        Mail::fake();
        $u = $this->register();

        $this->assertSame('ali', $u->profile->username);
        $this->assertTrue($u->emails->first()->is_primary);
        $this->assertFalse($u->emails->first()->isVerified());
        Mail::assertSent(VerifyEmailAddress::class);
        $this->assertAuthenticatedAs($u);
    }

    public function test_register_validation(): void
    {
        $this->register();
        $base = ['name' => 'X', 'email' => 'x@x.ir', 'password' => 'secret-pass-1', 'username' => 'xx1'];
        $this->postJson('/register', ['username' => 'admin'] + $base)->assertJsonValidationErrors('username');
        $this->postJson('/register', ['username' => 'Bad Name'] + $base)->assertJsonValidationErrors('username');
        $this->postJson('/register', ['username' => 'ali'] + $base)->assertJsonValidationErrors('username');
        $this->postJson('/register', ['password' => 'short'] + $base)->assertJsonValidationErrors('password');
        $this->postJson('/register', ['email' => 'ali@example.com'] + $base)->assertJsonValidationErrors('email');
    }

    public function test_login_and_logout(): void
    {
        $this->register();
        $this->postJson('/logout')->assertOk();
        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/login', ['email' => 'ali@example.com', 'password' => 'secret-pass-1'])->assertOk();
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/account')->assertUnauthorized();
        $this->get('/account')->assertUnauthorized();
    }

    public function test_verification_and_avatar_visibility_flow(): void
    {
        Storage::fake('local');
        $u = $this->register();
        $this->actingAs($u);

        $img = UploadedFile::fake()->image('me.png', 400, 300);
        $this->postJson('/account/avatar', ['avatar' => $img])->assertOk();
        $path = $u->profile->fresh()->avatar_path;
        Storage::disk('local')->assertExists($path);
        $this->assertSame([300, 300], array_slice(getimagesizefromstring(Storage::disk('local')->get($path)), 0, 2));

        $hash = UserEmail::hash('ali@example.com');
        // unverified -> default
        $this->get("/avatar/$hash")->assertHeader('Content-Type', 'image/svg+xml');
        $this->verifyPrimary($u);
        $this->get("/avatar/$hash")->assertHeader('Content-Type', 'image/jpeg');

        // replacing deletes the old file
        $this->postJson('/account/avatar', ['avatar' => UploadedFile::fake()->image('n.jpg', 200, 200)])->assertOk();
        Storage::disk('local')->assertMissing($path);

        $this->deleteJson('/account/avatar')->assertOk();
        $this->get("/avatar/$hash")->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_avatar_upload_rejects_bad_files(): void
    {
        Storage::fake('local');
        $this->actingAs($this->register());
        $this->postJson('/account/avatar', ['avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertStatus(422);
        $this->postJson('/account/avatar', ['avatar' => UploadedFile::fake()->image('tiny.png', 10, 10)])->assertStatus(422);
        $this->postJson('/account/avatar', ['avatar' => UploadedFile::fake()->image('big.png', 100, 100)->size(6000)])->assertStatus(422);
    }

    public function test_multiple_emails_and_primary_rules(): void
    {
        Mail::fake();
        $u = $this->register();
        $this->actingAs($u);
        $primary = $this->verifyPrimary($u);

        $id = $this->postJson('/account/emails', ['email' => ' Work@Corp.IR '])->assertCreated()->json('id');
        $this->assertSame('work@corp.ir', UserEmail::find($id)->email);

        $this->postJson("/account/emails/$id/primary")->assertStatus(422); // not verified yet
        $this->get(route('email.verify', UserEmail::find($id)->verification_token))->assertOk();
        $this->postJson("/account/emails/$id/primary")->assertOk();

        $this->assertFalse($primary->fresh()->is_primary);
        $this->deleteJson("/account/emails/$id")->assertStatus(422); // primary
        $this->deleteJson("/account/emails/{$primary->id}")->assertOk();
        $this->patchJson("/account/emails/$id", ['is_public' => false])->assertOk();
        $this->assertFalse(UserEmail::find($id)->is_public);
    }

    public function test_cannot_manage_another_users_email(): void
    {
        $a = $this->register('a@x.ir', 'aaa');
        $b = $this->register('b@x.ir', 'bbb');
        $eb = $b->emails()->first();
        $this->actingAs($a);
        $this->deleteJson("/account/emails/{$eb->id}")->assertNotFound();
        $this->patchJson("/account/emails/{$eb->id}", ['is_public' => false])->assertNotFound();
    }

    public function test_unverified_claim_cannot_block_or_steal_address(): void
    {
        Mail::fake();
        $attacker = $this->register('evil@x.ir', 'evil');
        $this->actingAs($attacker)->postJson('/account/emails', ['email' => 'victim@x.ir'])->assertCreated();

        $victim = $this->register('victim@x.ir', 'victim'); // not blocked
        $this->verifyPrimary($victim);

        // attacker's claim was dropped; owner is the victim
        $this->assertSame(1, UserEmail::where('email_hash', UserEmail::hash('victim@x.ir'))->count());
        $this->assertSame($victim->id, UserEmail::where('email_hash', UserEmail::hash('victim@x.ir'))->first()->user_id);
    }

    public function test_second_verification_of_same_address_is_rejected(): void
    {
        Mail::fake();
        $a = $this->register('a@x.ir', 'aaa');
        $this->actingAs($a)->postJson('/account/emails', ['email' => 'shared@x.ir']);
        $b = $this->register('b@x.ir', 'bbb');
        $this->actingAs($b)->postJson('/account/emails', ['email' => 'shared@x.ir']);
        [$ea, $eb] = [$a->emails()->where('email', 'shared@x.ir')->first(), $b->emails()->where('email', 'shared@x.ir')->first()];

        $this->get(route('email.verify', $ea->verification_token))->assertOk();
        // b's claim was deleted when a verified -> token no longer valid
        $this->get(route('email.verify', $eb->verification_token))->assertNotFound();
    }

    public function test_profile_update_validation_and_socials_whitelist(): void
    {
        $u = $this->register();
        $this->actingAs($u);

        $this->patchJson('/account/profile', [
            'name_fa' => 'علی', 'name_en' => 'Ali', 'bio' => 'سلام', 'website' => 'https://ali.ir',
            'socials' => ['github' => '@ali', 'evil' => 'x', 'telegram' => 'ali_t'],
        ])->assertOk()->assertJsonPath('profile.socials.github', 'ali');

        $socials = $u->profile->fresh()->socials;
        $this->assertSame(['github' => 'ali', 'telegram' => 'ali_t'], $socials);

        $this->patchJson('/account/profile', ['website' => 'javascript:alert(1)'])->assertJsonValidationErrors('website');
        $this->patchJson('/account/profile', ['socials' => ['github' => '"><script>']])->assertStatus(422);
        $this->patchJson('/account/profile', ['username' => 'login'])->assertStatus(422);
    }

    public function test_public_profile_page_privacy(): void
    {
        $u = $this->register();
        $u->profile->update(['name_fa' => 'علی', 'bio' => 'سلام <b>x</b>', 'socials' => ['github' => 'ali']]);

        $res = $this->get('/u/ali')->assertOk()->assertSee('علی');
        $res->assertSee('noindex', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $res->assertDontSee('<b>x</b>', false);   // escaped
        $res->assertDontSee('ali@example.com');

        $u->profile->update(['allow_indexing' => true]);
        $res = $this->get('/u/ali')->assertOk();
        $this->assertNull($res->headers->get('X-Robots-Tag'));
        $res->assertDontSee('noindex', false);

        $u->profile->update(['show_profile' => false]);
        $this->get('/u/ali')->assertNotFound();
        $this->get('/u/nobody')->assertNotFound();
    }

    public function test_privacy_endpoint_controls_flags(): void
    {
        $u = $this->register();
        $this->actingAs($u)->patchJson('/account/privacy', ['allow_indexing' => true, 'show_avatar' => false])
            ->assertOk()->assertJsonPath('profile.allow_indexing', true)->assertJsonPath('profile.show_avatar', false);
        $this->assertFalse($u->profile->fresh()->show_avatar);
    }
}
