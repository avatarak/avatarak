<?php

namespace Tests\Feature;

use Avatarak\Client;
use Avatarak\Laravel\AvatarakServiceProvider;
use Avatarak\Laravel\Facades\Avatarak;
use Illuminate\Support\Facades\Blade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SdkLaravelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['avatarak.key' => 'avk_test', 'avatarak.base_url' => 'https://avatarak.test']);
        $this->app->register(AvatarakServiceProvider::class);
        $this->app->forgetInstance(Client::class);
    }

    public function test_provider_builds_the_client_from_config_and_the_facade_works(): void
    {
        $this->assertInstanceOf(Client::class, app(Client::class));
        $h = hash('sha256', 'ali@example.com');
        $this->assertSame("https://avatarak.test/avatar/$h?s=64", Avatarak::avatarUrl('ali@example.com', 64));
        $this->assertSame($h, Avatarak::hash('ALI@example.com'));
    }

    public function test_blade_component_renders_a_retina_image_without_the_email(): void
    {
        $html = Blade::render('<x-avatarak-avatar email="ali@example.com" :size="48" default="identicon" class="rounded" alt="Ali" />');
        $h = hash('sha256', 'ali@example.com');
        $this->assertStringContainsString("src=\"https://avatarak.test/avatar/$h?s=48&amp;d=identicon\"", $html);
        $this->assertStringContainsString("https://avatarak.test/avatar/$h?s=96&amp;d=identicon 2x", $html);
        $this->assertStringContainsString('class="rounded"', $html);
        $this->assertStringContainsString('alt="Ali"', $html);
        $this->assertStringNotContainsString('ali@example.com', $html);
    }

    public function test_the_sdk_talks_to_this_very_server(): void
    {
        // end to end against the real routes through an in-process transport
        $owner = \App\Models\User::factory()->create();
        [, $key] = \App\Models\Site::createWithKey(['user_id' => $owner->id, 'name' => 's', 'domain' => 's.ir']);
        $u = \App\Models\User::factory()->create();
        \App\Models\Profile::create(['user_id' => $u->id, 'username' => 'ali', 'name_en' => 'Ali', 'headline' => 'Dev']);
        $u->emails()->create(['email' => 'ali@example.com', 'verified_at' => now()]);

        $client = new Client($key, 'http://localhost', 5, function ($method, $url, $headers, $body) {
            $path = parse_url($url, PHP_URL_PATH);
            $res = $this->call($method, $path, [], [], [], [
                'HTTP_AUTHORIZATION' => $headers['Authorization'], 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            ], $body);

            return [$res->getStatusCode(), $res->headers->all(), $res->getContent()];
        });

        $p = $client->profile('ALI@example.com');
        $this->assertSame(['ali', 'Dev'], [$p->username, $p->headline]);
        $this->assertNull($client->profile('nobody@example.com'));

        $many = $client->lookup(['ali@example.com', 'nobody@example.com']);
        $this->assertSame('ali', $many[Client::hash('ali@example.com')]->username);
        $this->assertNull($many[Client::hash('nobody@example.com')]);

        $bad = new Client('avk_wrong', 'http://localhost', 5, fn () => [401, [], '{"message":"Invalid or missing API key."}']);
        $this->expectException(\Avatarak\Exceptions\AuthenticationException::class);
        $bad->profile('ali@example.com');
    }
}
