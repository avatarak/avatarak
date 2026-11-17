<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUrlTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $username = 'javid', array $profile = []): User
    {
        Storage::fake('local');
        $u = User::factory()->create();
        Profile::create($profile + ['user_id' => $u->id, 'username' => $username, 'name_fa' => 'جاوید']);

        return $u;
    }

    private function withAvatar(User $u): void
    {
        $this->actingAs($u)->postJson('/account/avatars', ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)])->assertCreated();
        auth()->logout();
    }

    public function test_profile_lives_at_the_root(): void
    {
        $this->user();
        $this->get('/javid')->assertOk()->assertSee('جاوید');
        $this->get('/JAVID')->assertOk();
        $this->assertSame(url('/javid'), route('profile.show', 'javid'));
    }

    public function test_legacy_u_prefix_redirects(): void
    {
        $this->user();
        $this->get('/u/javid')->assertStatus(301)->assertRedirect('/javid');
        $this->get('/u/nobody')->assertRedirect('/nobody');
        $this->get('/nobody')->assertNotFound();
    }

    public function test_root_catch_all_does_not_shadow_real_routes(): void
    {
        $this->get('/')->assertOk()->assertSee('id="app"', false);
        $this->getJson('/account')->assertUnauthorized();
        $this->get('/up')->assertOk();
        $this->get('/avatar/'.str_repeat('a', 64))->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/favicon.ico')->assertNotFound();
        $this->get('/x')->assertNotFound();                 // too short for a username
        $this->get('/not-valid!')->assertNotFound();
    }

    public function test_reserved_usernames_cannot_be_registered(): void
    {
        foreach (['account', 'login', 'up', 'avatar', 'api', 'admin', 'build', 'register', 'u'] as $name) {
            $this->postJson('/register', ['name' => 'x', 'email' => "$name@x.ir", 'password' => 'secret-pass-1', 'username' => $name])
                ->assertJsonValidationErrors('username');
        }
    }

    public function test_profile_avatar_is_public_without_a_verified_email(): void
    {
        $u = $this->user();
        $this->withAvatar($u);
        $this->assertSame(0, UserEmail::count());           // no email at all

        $this->get('/javid')->assertOk()->assertSee('/javid/avatar?', false);
        $this->get('/javid/avatar?s=200')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_profile_avatar_respects_privacy_switches(): void
    {
        $u = $this->user();
        $this->withAvatar($u);

        $u->profile->update(['show_avatar' => false]);
        $this->get('/javid/avatar')->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/javid')->assertDontSee('/javid/avatar?', false);

        $u->profile->update(['show_avatar' => true, 'show_profile' => false]);
        $this->get('/javid/avatar')->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/javid')->assertNotFound();
        $this->get('/nobody/avatar')->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_hash_route_still_requires_a_verified_public_email(): void
    {
        $u = $this->user();
        $this->withAvatar($u);
        $e = $u->emails()->create(['email' => 'j@x.ir']);
        $this->get('/avatar/'.UserEmail::hash('j@x.ir'))->assertHeader('Content-Type', 'image/svg+xml');
        $e->forceFill(['verified_at' => now()])->save();
        $this->get('/avatar/'.UserEmail::hash('j@x.ir'))->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_api_profile_url_uses_root_path(): void
    {
        $this->user();
        $owner = User::factory()->create();
        [, $key] = \App\Models\Site::createWithKey(['user_id' => $owner->id, 'name' => 's', 'domain' => 's.ir']);
        $u = User::first();
        $u->emails()->create(['email' => 'j@x.ir', 'verified_at' => now()]);
        $this->getJson('/api/v1/profiles/'.UserEmail::hash('j@x.ir'), ['Authorization' => "Bearer $key"])
            ->assertOk()->assertJsonPath('data.profile_url', url('/javid'));
    }

    public function test_auto_verify_only_works_locally_and_when_enabled(): void
    {
        Mail::fake();
        $u = $this->user();
        config(['identity.auto_verify_emails' => true]);

        $this->app['env'] = 'production';
        $a = \App\Http\Controllers\EmailController::attach($u, 'prod@x.ir');
        $this->assertFalse($a->isVerified());

        $this->app['env'] = 'local';
        config(['identity.auto_verify_emails' => false]);
        $this->assertFalse(\App\Http\Controllers\EmailController::attach($u, 'off@x.ir')->isVerified());

        config(['identity.auto_verify_emails' => true]);
        $c = \App\Http\Controllers\EmailController::attach($u, 'local@x.ir');
        $this->assertTrue($c->isVerified());
        $this->assertNull($c->verification_token);
    }

    public function test_auto_verify_never_overrides_an_existing_verified_owner(): void
    {
        Mail::fake();
        $this->app['env'] = 'local';
        config(['identity.auto_verify_emails' => true]);
        $a = $this->user('aaa');
        $b = $this->user('bbb');
        $first = \App\Http\Controllers\EmailController::attach($a, 'shared@x.ir');
        $this->assertTrue($first->isVerified());
        $second = \App\Http\Controllers\EmailController::attach($b, 'shared@x.ir');
        $this->assertFalse($second->isVerified());
    }

    public function test_email_verify_command(): void
    {
        $u = $this->user();
        $u->emails()->create(['email' => 'j@x.ir']);
        $this->artisan('email:verify', ['email' => ' J@X.ir '])->assertSuccessful();
        $this->assertTrue($u->emails()->first()->isVerified());
        $this->artisan('email:verify', ['email' => 'none@x.ir'])->assertFailed();
    }

    public function test_command_refuses_when_two_accounts_claim_the_address(): void
    {
        $a = $this->user('aaa');
        $b = $this->user('bbb');
        $a->emails()->create(['email' => 'dup@x.ir']);
        $b->emails()->create(['email' => 'dup@x.ir']);
        $this->artisan('email:verify', ['email' => 'dup@x.ir'])->assertFailed();
        $this->assertSame(0, UserEmail::whereNotNull('verified_at')->count());
    }
}
