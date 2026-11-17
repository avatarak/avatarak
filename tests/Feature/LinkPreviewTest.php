<?php

namespace Tests\Feature;

use App\Jobs\FetchLinkThumbnails;
use App\Models\Profile;
use App\Models\User;
use App\Services\LinkPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LinkPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function jpeg(int $w = 800, int $h = 450): string
    {
        $i = imagecreatetruecolor($w, $h);
        imagefill($i, 0, 0, imagecolorallocate($i, 30, 90, 200));
        ob_start(); imagejpeg($i); return ob_get_clean();
    }

    private function service(array $map = []): LinkPreviewService
    {
        config(['linkpreview.pin_dns' => false]);
        $s = new LinkPreviewService;
        $s->resolver = fn (string $host) => $map[$host] ?? ['93.184.216.34'];   // public by default

        return $s;
    }

    public static function privateIps(): array
    {
        return [['127.0.0.1'], ['10.1.2.3'], ['172.16.0.1'], ['192.168.1.1'], ['169.254.169.254'], ['0.0.0.0'], ['100.64.0.1'],
            ['::1'], ['fe80::1'], ['fd00::1'], ['::ffff:10.0.0.1'], ['::ffff:127.0.0.1'], ['198.18.0.1']];
    }

    #[DataProvider('privateIps')]
    public function test_private_addresses_are_rejected(string $ip): void
    {
        $this->assertFalse(LinkPreviewService::isPublicIp($ip), $ip);
    }

    public function test_public_addresses_are_accepted(): void
    {
        foreach (['93.184.216.34', '8.8.8.8', '1.1.1.1', '2606:4700:4700::1111'] as $ip) {
            $this->assertTrue(LinkPreviewService::isPublicIp($ip), $ip);
        }
        $this->assertFalse(LinkPreviewService::isPublicIp('not-an-ip'));
    }

    public function test_validate_blocks_dangerous_urls(): void
    {
        $s = $this->service(['internal.corp' => ['10.0.0.5'], 'mixed.example' => ['93.184.216.34', '127.0.0.1']]);
        foreach ([
            'http://127.0.0.1/', 'http://localhost/', 'http://[::1]/', 'http://169.254.169.254/latest/meta-data/',
            'http://internal.corp/', 'http://mixed.example/',                 // one bad address is enough
            'file:///etc/passwd', 'gopher://x.com/', 'ftp://x.com/', 'javascript:alert(1)',
            'http://user:pw@example.com/', 'http://example.com:22/', 'http://example.com:8080/', 'http:///nohost',
        ] as $url) {
            try {
                $s->validate($url);
                $this->fail("should have rejected $url");
            } catch (\RuntimeException $e) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('example.com', $s->validate('https://example.com/x')[0]);
    }

    public function test_fetch_stores_a_cover_cropped_jpeg_from_og_image(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://site.example/page' => Http::response('<html><head><meta property="og:image" content="/img/cover.jpg"></head></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'https://site.example/img/cover.jpg' => Http::response($this->jpeg(1200, 800), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $s = $this->service();
        $this->assertTrue($s->fetch('https://site.example/page'));

        $path = LinkPreviewService::path(LinkPreviewService::key('https://site.example/page'));
        Storage::disk('local')->assertExists($path);
        $this->assertSame([470, 264], array_slice(getimagesizefromstring(Storage::disk('local')->get($path)), 0, 2));
    }

    public function test_twitter_image_and_relative_urls_work_and_missing_image_is_false(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://a.example/' => Http::response('<meta name="twitter:image" content="https://cdn.example/t.png">', 200, ['Content-Type' => 'text/html']),
            'https://cdn.example/t.png' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/png']),
            'https://b.example/' => Http::response('<html><title>no image here</title></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $s = $this->service();
        $this->assertTrue($s->fetch('https://a.example/'));
        $this->assertFalse($s->fetch('https://b.example/'));
    }

    public function test_redirect_to_a_private_host_is_blocked(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://evil.example/' => Http::response('', 302, ['Location' => 'http://10.0.0.7/admin']),
            '*' => Http::response('<meta property="og:image" content="/x.jpg">', 200, ['Content-Type' => 'text/html']),
        ]);
        $s = $this->service(['10.0.0.7' => ['10.0.0.7']]);
        $this->assertFalse($s->fetch('https://evil.example/'));
        Http::assertSentCount(1);              // never followed the redirect
    }

    public function test_og_image_pointing_at_an_internal_address_is_blocked(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://site.example/' => Http::response('<meta property="og:image" content="http://169.254.169.254/latest/meta-data/">', 200, ['Content-Type' => 'text/html']),
        ]);
        $this->assertFalse($this->service()->fetch('https://site.example/'));
        Http::assertSentCount(1);
    }

    public function test_redirect_chain_is_limited(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://loop.example/next'])]);
        $this->assertFalse($this->service()->fetch('https://loop.example/'));
    }

    public function test_non_image_and_tiny_and_fake_images_are_rejected(): void
    {
        Storage::fake('local');
        $html = fn ($img) => Http::response('<meta property="og:image" content="'.$img.'">', 200, ['Content-Type' => 'text/html']);
        Http::fake([
            'https://t1.example/' => $html('https://c.example/a.jpg'),
            'https://c.example/a.jpg' => Http::response('<?php system($_GET[0]); ?>', 200, ['Content-Type' => 'image/jpeg']),   // lies about type
            'https://t2.example/' => $html('https://c.example/b.png'),
            'https://c.example/b.png' => Http::response($this->jpeg(40, 40), 200, ['Content-Type' => 'image/png']),            // favicon-sized
            'https://t3.example/' => $html('https://c.example/c.html'),
            'https://c.example/c.html' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),               // wrong type
        ]);
        $s = $this->service();
        $this->assertFalse($s->fetch('https://t1.example/'));
        $this->assertFalse($s->fetch('https://t2.example/'));
        $this->assertFalse($s->fetch('https://t3.example/'));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_oversized_responses_are_rejected(): void
    {
        config(['linkpreview.max_image_bytes' => 1000]);
        Storage::fake('local');
        Http::fake([
            'https://t.example/' => Http::response('<meta property="og:image" content="https://c.example/big.jpg">', 200, ['Content-Type' => 'text/html']),
            'https://c.example/big.jpg' => Http::response($this->jpeg(1600, 900), 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $this->assertFalse($this->service()->fetch('https://t.example/'));
    }

    public function test_job_fills_pending_links_and_marks_failures(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $p = Profile::create(['user_id' => $u->id, 'username' => 'sara', 'links' => [
            ['label' => 'A', 'url' => 'https://ok.example/'], ['label' => 'B', 'url' => 'https://none.example/'], ['label' => 'C', 'url' => 'https://done.example/', 'thumb' => 'abc'],
        ]]);
        Http::fake([
            'https://ok.example/' => Http::response('<meta property="og:image" content="https://c.example/i.jpg">', 200, ['Content-Type' => 'text/html']),
            'https://c.example/i.jpg' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
            'https://none.example/' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $this->app->instance(LinkPreviewService::class, $this->service());

        (new FetchLinkThumbnails($p->id))->handle(app(LinkPreviewService::class));

        $links = $p->fresh()->links;
        $this->assertSame(LinkPreviewService::key('https://ok.example/'), $links[0]['thumb']);
        $this->assertFalse($links[1]['thumb']);
        $this->assertSame('abc', $links[2]['thumb']);            // untouched
        Http::assertSentCount(3);                                // done.example was not fetched
    }

    public function test_saving_links_keeps_known_thumbs_and_fetches_only_new_urls_after_response(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'sara', 'links' => [['label' => 'Old', 'url' => 'https://old.example/', 'thumb' => 'kept']]]);
        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
        $this->app->instance(LinkPreviewService::class, $this->service());

        $this->actingAs($u)->patchJson('/account/profile', ['links' => [
            ['label' => 'Old renamed', 'url' => 'https://old.example/'],
            ['label' => 'New', 'url' => 'https://new.example/'],
        ]])->assertOk();

        $links = $u->profile->fresh()->links;
        $this->assertSame('kept', $links[0]['thumb']);
        $this->assertFalse($links[1]['thumb']);                  // fetched after the response, none found
        Http::assertSentCount(1);                                // only new.example
    }

    public function test_previews_can_be_disabled(): void
    {
        config(['linkpreview.enabled' => false]);
        Http::fake();
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'sara']);
        $this->actingAs($u)->patchJson('/account/profile', ['links' => [['label' => 'x', 'url' => 'https://x.example/']]])->assertOk();
        Http::assertNothingSent();
    }

    public function test_thumb_route_serves_only_stored_thumbnails(): void
    {
        Storage::fake('local');
        $key = LinkPreviewService::key('https://x.example/');
        $this->service()->store('https://x.example/', $this->jpeg());

        $r = $this->get("/thumb/$key")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('public', $r->headers->get('Cache-Control'));
        $this->get('/thumb/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/thumb/../../etc/passwd')->assertNotFound();
        $this->get('/thumb/'.strtoupper($key))->assertNotFound();
    }

    public function test_show_link_images_toggle_is_saved(): void
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'sara']);
        $this->actingAs($u)->patchJson('/account/profile', ['show_link_images' => false])->assertOk()->assertJsonPath('profile.show_link_images', false);
    }
}
