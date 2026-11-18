<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Site;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\OpenApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_spec_is_public_json_with_open_cors(): void
    {
        $r = $this->get('/docs/openapi.json')->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
        $spec = $r->json();
        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertSame(rtrim(config('app.url'), '/'), $spec['servers'][0]['url']);
        $this->assertStringContainsString('API', $spec['info']['title']);
        $this->assertNotEmpty($spec['info']['version']);
    }

    public function test_every_documented_endpoint_really_exists_with_that_method(): void
    {
        $spec = (new OpenApi)->build();
        // "{username}.{format}" is one route that serves .json/.md/.vcf/...: expand it by its allowed formats
        $routes = collect(Route::getRoutes()->getRoutes())->flatMap(function (LaravelRoute $r) {
            $uri = ltrim($r->uri(), '/');
            if (str_contains($uri, '{format}') && isset($r->wheres['format'])) {
                return array_map(fn ($f) => [str_replace('{format}', $f, $uri), $r->methods()], explode('|', $r->wheres['format']));
            }

            return [[$uri, $r->methods()]];
        });

        foreach ($spec['paths'] as $path => $item) {
            foreach (array_keys($item) as $method) {
                $uri = ltrim(preg_replace('/\{(\w+)\}/', '{$1}', $path), '/');
                $found = $routes->contains(fn ($r) => in_array(strtoupper($method), $r[1], true) && ($r[0] === $uri || preg_replace('/\{\w+\??\}/', '{x}', $r[0]) === preg_replace('/\{\w+\??\}/', '{x}', $uri)));
                $this->assertTrue($found, strtoupper($method)." $path is documented but no such route exists");
            }
        }
    }

    public function test_every_api_route_is_documented(): void
    {
        $spec = (new OpenApi)->build();
        $documented = collect(array_keys($spec['paths']))->map(fn ($p) => preg_replace('/\{\w+\}/', '{x}', ltrim($p, '/')))->all();

        foreach (Route::getRoutes()->getRoutes() as $r) {
            if (str_starts_with($r->uri(), 'api/v1/')) {
                $this->assertContains(preg_replace('/\{\w+\??\}/', '{x}', $r->uri()), $documented, $r->uri().' is a public API route missing from the OpenAPI document');
            }
        }
    }

    public function test_the_profile_schema_matches_what_the_api_returns_field_for_field(): void
    {
        $spec = (new OpenApi)->build();
        $schema = $spec['components']['schemas']['Profile'];
        $this->assertSame(OpenApi::PROFILE_FIELDS, $schema['required']);
        $this->assertSame(OpenApi::PROFILE_FIELDS, array_keys($schema['properties']));

        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'ali']);
        $u->emails()->create(['email' => 'ali@example.com', 'verified_at' => now()]);
        [, $key] = Site::createWithKey(['user_id' => User::factory()->create()->id, 'name' => 's', 'domain' => 's.ir']);
        $live = $this->getJson('/api/v1/profiles/'.UserEmail::hash('ali@example.com'), ['Authorization' => "Bearer $key"])->assertOk()->json('data');

        $this->assertSame(OpenApi::PROFILE_FIELDS, array_keys($live), 'the document and the live response list the same fields in the same order');
    }

    public function test_documented_limits_and_responses_match_the_implementation(): void
    {
        $spec = (new OpenApi)->build();
        $lookup = $spec['paths']['/api/v1/profiles/lookup']['post'];
        $this->assertSame(\App\Http\Controllers\Api\ProfileApiController::MAX_BATCH, $lookup['requestBody']['content']['application/json']['schema']['properties']['hashes']['maxItems']);
        $avatar = $spec['paths']['/avatar/{hash}']['get']['parameters'];
        $byName = collect($avatar)->keyBy('name');
        $this->assertSame([config('avatar.min_size'), config('avatar.max_size'), config('avatar.default_size')], [$byName['s']['schema']['minimum'], $byName['s']['schema']['maximum'], $byName['s']['schema']['default']]);
        $this->assertEqualsCanonicalizing(['mp', 'identicon', 'blank', '404'], $byName['d']['schema']['enum']);

        foreach (['401', '403', '404', '429'] as $code) {
            $this->assertArrayHasKey($code, $spec['paths']['/api/v1/profiles/{hash}']['get']['responses']);
        }
        $this->assertSame(['bearerAuth' => []], $spec['paths']['/api/v1/profiles/{hash}']['get']['security'][0]);
        $this->assertSame([], $spec['paths']['/avatar/{hash}']['get']['security'], 'avatars need no key');
    }

    public function test_every_reference_resolves(): void
    {
        $spec = (new OpenApi)->build();
        $json = json_encode($spec);
        preg_match_all('/"\$ref":"#\/components\/schemas\/(\w+)"/', $json, $m);
        foreach (array_unique($m[1]) as $name) {
            $this->assertArrayHasKey($name, $spec['components']['schemas'], "dangling \$ref $name");
        }
    }

    public function test_the_explorer_page_renders_in_both_languages(): void
    {
        foreach (['fa' => 'آزمایش API', 'en' => 'API explorer'] as $l => $title) {
            $html = $this->get("/docs/api-explorer?lang=$l")->assertOk()->getContent();
            $this->assertStringContainsString($title, $html);
            $this->assertStringContainsString('id="explorer"', $html);
            $this->assertStringContainsString(route('docs.openapi'), $html);
            $this->assertStringContainsString('openapi.json', $html);
        }
        $this->get('/docs/api-explorer')->assertOk();                       // not swallowed by the /docs/{slug} route
    }

    // ------------------------------------------------------------------ key scopes

    private function profileFor(string $email = 'ali@example.com'): string
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'ali']);
        $u->emails()->create(['email' => $email, 'verified_at' => now()]);

        return UserEmail::hash($email);
    }

    public function test_keys_without_a_scope_list_can_do_everything_like_before(): void
    {
        $hash = $this->profileFor();
        [, $key] = Site::createWithKey(['user_id' => User::factory()->create()->id, 'name' => 's', 'domain' => 's.ir']);
        $h = ['Authorization' => "Bearer $key"];
        $this->getJson("/api/v1/profiles/$hash", $h)->assertOk();
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => [$hash]], $h)->assertOk();
    }

    public function test_a_read_only_key_cannot_use_lookup_and_a_lookup_only_key_cannot_read(): void
    {
        $hash = $this->profileFor();
        $owner = User::factory()->create();
        [, $read] = Site::createWithKey(['user_id' => $owner->id, 'name' => 'r', 'domain' => 'r.ir', 'scopes' => ['profiles:read']]);
        [, $look] = Site::createWithKey(['user_id' => $owner->id, 'name' => 'l', 'domain' => 'l.ir', 'scopes' => ['profiles:lookup']]);

        $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $read"])->assertOk();
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => [$hash]], ['Authorization' => "Bearer $read"])->assertStatus(403)->assertJsonPath('message', 'This key does not have the "profiles:lookup" scope.');
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => [$hash]], ['Authorization' => "Bearer $look"])->assertOk();
        $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $look"])->assertStatus(403)->assertJsonPath('message', 'This key does not have the "profiles:read" scope.');
    }

    public function test_scopes_are_chosen_when_a_site_is_created_and_validated(): void
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'owner']);
        $this->actingAs($u);

        $r = $this->postJson('/account/sites', ['name' => 'A', 'domain' => 'a.example.ir', 'scopes' => ['profiles:read']])->assertCreated();
        $this->assertSame(['profiles:read'], $r->json('scopes'));
        $this->assertSame(['profiles:read'], Site::find($r->json('id'))->scopes);

        $all = $this->postJson('/account/sites', ['name' => 'B', 'domain' => 'b.example.ir'])->assertCreated();
        $this->assertSame(Site::SCOPES, $all->json('scopes'), 'no choice = everything');

        $this->postJson('/account/sites', ['name' => 'C', 'domain' => 'c.example.ir', 'scopes' => ['admin:everything']])->assertStatus(422);
        $this->postJson('/account/sites', ['name' => 'C', 'domain' => 'c.example.ir', 'scopes' => []])->assertStatus(422);
        $this->getJson('/account/sites')->assertOk()->assertJsonPath('0.scopes', Site::SCOPES);
    }
}
