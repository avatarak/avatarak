<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Site;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create();
        [$this->site, $this->key] = Site::createWithKey(['user_id' => $owner->id, 'name' => 's', 'domain' => 's.ir']);
    }

    private function person(string $email = 'ali@example.com', array $profile = [], array $emailAttrs = []): string
    {
        $u = User::factory()->create();
        Profile::create($profile + [
            'user_id' => $u->id, 'username' => 'u'.$u->id, 'name_fa' => 'علی', 'name_en' => 'Ali',
            'bio' => 'hi', 'website' => 'https://ali.ir', 'socials' => ['github' => 'ali'],
            'avatar_path' => 'avatars/a.png', 'avatar_version' => '1',
        ]);
        $u->emails()->create($emailAttrs + ['email' => $email, 'verified_at' => now()]);

        return UserEmail::hash($email);
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer '.$this->key];
    }

    public function test_requires_api_key(): void
    {
        $h = $this->person();
        $this->getJson("/api/v1/profiles/$h")->assertUnauthorized();
        $this->getJson("/api/v1/profiles/$h", ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    }

    public function test_accepts_x_api_key_header(): void
    {
        $h = $this->person();
        $this->getJson("/api/v1/profiles/$h", ['X-API-Key' => $this->key])->assertOk();
    }

    public function test_returns_public_profile_without_private_fields(): void
    {
        $h = $this->person();
        $res = $this->getJson("/api/v1/profiles/$h", $this->auth())->assertOk()
            ->assertJsonPath('data.name_fa', 'علی')
            ->assertJsonPath('data.name_en', 'Ali')
            ->assertJsonPath('data.socials.github', 'ali')
            ->assertJsonPath('data.avatar_url', route('avatar', $h));

        $body = $res->getContent();
        $this->assertStringNotContainsString('ali@example.com', $body);
        $this->assertStringNotContainsString('user_id', $body);
        $this->assertStringNotContainsString('allow_indexing', $body);
    }

    public function test_hidden_cases_all_look_like_404(): void
    {
        $unverified = $this->person('a@x.ir', emailAttrs: ['verified_at' => null]);
        $private = $this->person('b@x.ir', emailAttrs: ['is_public' => false]);
        $hidden = $this->person('c@x.ir', ['show_profile' => false]);
        $unknown = UserEmail::hash('nobody@x.ir');

        $bodies = [];
        foreach ([$unverified, $private, $hidden, $unknown] as $h) {
            $r = $this->getJson("/api/v1/profiles/$h", $this->auth())->assertNotFound();
            $bodies[] = $r->getContent();
        }
        $this->assertCount(1, array_unique($bodies));
    }

    public function test_avatar_url_null_when_avatar_hidden(): void
    {
        $h = $this->person(profile: ['show_avatar' => false]);
        $this->getJson("/api/v1/profiles/$h", $this->auth())->assertOk()->assertJsonPath('data.avatar_url', null);
    }

    public function test_batch_lookup(): void
    {
        $a = $this->person('a@x.ir');
        $b = $this->person('b@x.ir', ['show_profile' => false]);
        $c = UserEmail::hash('none@x.ir');

        $res = $this->postJson('/api/v1/profiles/lookup', ['hashes' => [$a, $b, $c, strtoupper($a)]], $this->auth())->assertOk();
        $data = $res->json('data');

        $this->assertCount(3, $data);
        $this->assertSame('Ali', $data[$a]['name_en']);
        $this->assertNull($data[$b]);
        $this->assertNull($data[$c]);
    }

    public function test_batch_validation(): void
    {
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => ['nope']], $this->auth())->assertStatus(422);
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => array_fill(0, 101, str_repeat('a', 64))], $this->auth())->assertStatus(422);
    }

    public function test_per_site_rate_limit(): void
    {
        $this->site->update(['rate_limit_per_minute' => 2]);
        $h = $this->person();
        $this->getJson("/api/v1/profiles/$h", $this->auth())->assertOk()->assertHeader('X-RateLimit-Remaining', '1');
        $this->getJson("/api/v1/profiles/$h", $this->auth())->assertOk();
        $this->getJson("/api/v1/profiles/$h", $this->auth())->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_last_used_is_recorded(): void
    {
        $h = $this->person();
        $this->getJson("/api/v1/profiles/$h", $this->auth())->assertOk();
        $this->assertNotNull($this->site->fresh()->last_used_at);
    }

    public function test_site_create_command_issues_working_key(): void
    {
        $u = User::factory()->create(['email' => 'o@x.ir']);
        $this->artisan('site:create', ['user' => 'o@x.ir', 'name' => 'blog', 'domain' => 'blog.ir'])->assertSuccessful();
        $this->assertSame(1, $u->sites()->count());
    }
}
