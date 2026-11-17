<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_list_and_delete_site(): void
    {
        $u = User::factory()->create();
        $res = $this->actingAs($u)->postJson('/account/sites', ['name' => 'وبلاگ', 'domain' => 'Blog.Example.ir'])->assertCreated();
        $key = $res->json('api_key');
        $this->assertStringStartsWith('ira_', $key);
        $this->assertSame('blog.example.ir', $res->json('domain'));

        // listing never exposes the key or its hash
        $list = $this->getJson('/account/sites')->assertOk();
        $this->assertStringNotContainsString($key, $list->getContent());
        $this->assertStringNotContainsString('api_key_hash', $list->getContent());

        // the issued key works against the API
        $this->getJson('/api/v1/profiles/'.str_repeat('a', 64), ['Authorization' => "Bearer $key"])->assertNotFound();

        $this->deleteJson('/account/sites/'.$res->json('id'))->assertOk();
        $this->getJson('/api/v1/profiles/'.str_repeat('a', 64), ['Authorization' => "Bearer $key"])->assertUnauthorized();
    }

    public function test_domain_validation_and_ownership(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a);
        foreach (['not a domain', 'http://x.ir', 'x', '-bad.ir', 'a.ir/path'] as $bad) {
            $this->postJson('/account/sites', ['name' => 'x', 'domain' => $bad])->assertJsonValidationErrors('domain');
        }

        [$site] = Site::createWithKey(['user_id' => $b->id, 'name' => 'b', 'domain' => 'b.ir']);
        $this->deleteJson("/account/sites/{$site->id}")->assertNotFound();
        $this->assertSame([], $this->getJson('/account/sites')->json());
    }

    public function test_own_avatar_preview_requires_auth_and_is_private(): void
    {
        $this->getJson('/account/avatar')->assertUnauthorized();
        $u = User::factory()->create();
        \App\Models\Profile::create(['user_id' => $u->id, 'username' => 'zed']);
        $this->actingAs($u)->getJson('/account/avatar')->assertNotFound();
    }

    public function test_spa_shell_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('id="app"', false);
    }
}
