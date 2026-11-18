<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ health

    public function test_ready_is_200_when_everything_works_and_leaks_nothing(): void
    {
        Storage::fake('local');
        $r = $this->getJson('/up/ready')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertSame('no-store, private', $r->headers->get('Cache-Control'));
        $this->get('/up')->assertOk();
    }

    public function test_ready_is_503_with_only_the_name_of_what_failed(): void
    {
        DB::partialMock()->shouldReceive('select')->andThrow(new \RuntimeException('SQLSTATE secret host db.internal'));
        Storage::fake('local');

        $r = $this->getJson('/up/ready')->assertStatus(503);
        $this->assertSame(['status' => 'degraded', 'failed' => ['database']], $r->json());
        $this->assertStringNotContainsString('secret', $r->getContent(), 'no internals on a public URL');
    }

    public function test_ready_reports_unwritable_storage(): void
    {
        config(['filesystems.disks.broken' => ['driver' => 'local', 'root' => '/proc/avatarak-nope', 'throw' => false], 'avatar.disk' => 'broken']);
        $this->getJson('/up/ready')->assertStatus(503)->assertJsonPath('failed', ['storage']);
    }

    // ------------------------------------------------------------------ proxies

    public function test_forwarded_headers_are_ignored_unless_the_proxy_is_trusted(): void
    {
        $req = fn () => \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9']);
        $handle = function (\Illuminate\Http\Request $r) {
            $captured = null;
            (new TrustProxies)->handle($r, function ($r) use (&$captured) {
                $captured = $r;

                return response('');
            });

            return $captured;
        };

        $untrusted = $handle($req());
        $this->assertFalse($untrusted->isSecure(), 'anyone could otherwise fake https or their IP');
        $this->assertSame('10.0.0.5', $untrusted->ip());

        TrustProxies::at('*');
        $trusted = $handle($req());
        $this->assertTrue($trusted->isSecure());
        $this->assertSame('203.0.113.9', $trusted->ip());

        TrustProxies::at(['10.0.0.0/8']);
        $this->assertSame('203.0.113.9', $handle($req())->ip());
        TrustProxies::at(['192.168.0.0/16']);
        $this->assertSame('10.0.0.5', $handle($req())->ip(), 'a proxy outside the list is not trusted');
    }

    // ------------------------------------------------------------------ CDN headers

    private function photo(): string
    {
        Storage::fake('local');
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'javid']);
        $u->emails()->create(['email' => 'javid@example.com', 'verified_at' => now()]);
        $this->actingAs($u)->postJson('/account/avatars', ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)])->assertCreated();
        auth()->logout();

        return UserEmail::hash('javid@example.com');
    }

    private function cc($r): string
    {
        return implode(', ', collect(explode(',', (string) $r->headers->get('Cache-Control')))->map(fn ($x) => trim($x))->all());
    }

    public function test_shared_cache_lifetime_is_off_by_default_so_browsers_behave_as_documented(): void
    {
        $h = $this->photo();
        $this->assertStringNotContainsString('s-maxage', $this->cc($this->get("/avatar/$h")));
    }

    public function test_cdn_lifetimes_apply_to_real_photos_only(): void
    {
        $h = $this->photo();
        config(['avatar.s_maxage' => 600, 'avatar.stale_while_revalidate' => 60]);

        $photo = $this->cc($this->get("/avatar/$h"));
        $this->assertStringContainsString('max-age=3600', $photo);
        $this->assertStringContainsString('s-maxage=600', $photo);
        $this->assertStringContainsString('stale-while-revalidate=60', $photo);

        // generated defaults change when a user uploads, so they must not be pinned in a shared cache
        foreach (['/avatar/'.str_repeat('a', 64), '/avatar/'.str_repeat('a', 64).'?d=identicon', '/nobody/avatar'] as $u) {
            $this->assertStringNotContainsString('s-maxage', $this->cc($this->get($u)), $u);
        }
    }

    public function test_a_304_keeps_the_caching_headers(): void
    {
        $h = $this->photo();
        config(['avatar.s_maxage' => 600]);
        $etag = $this->get("/avatar/$h")->headers->get('ETag');
        $r = $this->get("/avatar/$h", ['If-None-Match' => $etag])->assertStatus(304);
        $this->assertStringContainsString('s-maxage=600', $this->cc($r));
    }
}
