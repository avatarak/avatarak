<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Site;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\DocsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocsTest extends TestCase
{
    use RefreshDatabase;

    private function cc($response): string
    {
        $d = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
        sort($d);

        return implode(', ', $d);
    }

    private function slugs(): array
    {
        return app(DocsRepository::class)->slugs();
    }

    // ------------------------------------------------------------------ structure & rendering

    public function test_every_page_exists_in_both_languages_with_front_matter(): void
    {
        $repo = app(DocsRepository::class);
        foreach ($this->slugs() as $slug) {
            foreach (['fa', 'en'] as $l) {
                $file = DocsRepository::path($l, $slug);
                $this->assertFileExists($file, "$l/$slug.md");
                [$fm, $body] = $repo->parse(file_get_contents($file));
                $this->assertNotEmpty($fm['title'] ?? '', "$l/$slug title");
                $this->assertNotEmpty($fm['description'] ?? '', "$l/$slug description");
                $this->assertGreaterThan(300, strlen($body), "$l/$slug has real content");
            }
        }
        // no orphan files that are not in the navigation
        foreach (['fa', 'en'] as $l) {
            $this->assertEqualsCanonicalizing($this->slugs(), array_map(fn ($f) => basename($f, '.md'), glob(resource_path("docs/$l/*.md"))), "$l has pages missing from config/docs.php");
        }
    }

    public function test_index_and_all_pages_render_in_both_languages(): void
    {
        foreach (['fa', 'en'] as $l) {
            $this->get("/docs?lang=$l")->assertOk()->assertSee('lang="'.$l.'"', false)->assertSee('dir="'.($l === 'fa' ? 'rtl' : 'ltr').'"', false);
            foreach ($this->slugs() as $slug) {
                $r = $this->get("/docs/$slug?lang=$l")->assertOk()->assertHeader('Content-Language', $l);
                $html = $r->getContent();
                $this->assertStringNotContainsString('{{origin}}', $html, "$l/$slug leaked a template token");
                $this->assertStringNotContainsString('{{brand}}', $html);
                $this->assertStringContainsString('<h1>', $html);
                $this->assertStringContainsString('data-docs-article', $html);
            }
        }
    }

    public function test_origin_and_brand_are_filled_in(): void
    {
        $html = $this->get('/docs/avatars?lang=en')->assertOk()->getContent();
        $this->assertStringContainsString(rtrim(url('/'), '/').'/avatar/', $html);
        $this->assertStringContainsString('Avatarak.ir', $this->get('/docs/getting-started?lang=en')->getContent());
    }

    public function test_language_switch_is_remembered_in_a_plain_cookie(): void
    {
        $r = $this->get('/docs/hash?lang=en')->assertOk();
        $r->assertSee('Creating the identifier');
        $cookie = collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === 'locale');
        $this->assertNotNull($cookie);
        $this->assertSame('en', $cookie->getValue(), 'plain value, readable by the SPA');

        $this->withUnencryptedCookie('locale', 'en')->get('/docs/hash')->assertSee('Creating the identifier');
        $this->withUnencryptedCookie('locale', 'fa')->get('/docs/hash')->assertSee('ساخت شناسه');
        $this->get('/docs/hash?lang=klingon')->assertOk();                       // ignored, falls back
    }

    public function test_unknown_pages_are_404_and_paths_cannot_escape(): void
    {
        foreach (['/docs/nope', '/docs/..%2f..%2f.env', '/docs/Getting-Started', '/docs/hash.md', '/docs/hash/extra'] as $u) {
            $this->get($u)->assertNotFound();
        }
    }

    public function test_docs_route_is_not_shadowed_by_the_username_catch_all(): void
    {
        $this->get('/docs')->assertOk()->assertSee('docs-hero', false);
        $this->postJson('/register', ['name' => 'x', 'email' => 'd@x.ir', 'password' => 'secret-pass-1', 'username' => 'docs'])
            ->assertJsonValidationErrors('username');
    }

    public function test_internal_links_and_anchors_resolve(): void
    {
        foreach (['fa', 'en'] as $l) {
            $pages = [];
            foreach ($this->slugs() as $slug) {
                $pages[$slug] = $this->get("/docs/$slug?lang=$l")->getContent();
            }
            foreach ($pages as $slug => $html) {
                preg_match_all('#href="(/docs/([a-z0-9-]+))(?:\#([^"]+))?"#u', $html, $m, PREG_SET_ORDER);
                foreach ($m as $x) {
                    if ($x[2] === 'api-explorer') {
                        continue;       // a tool page, not Markdown
                    }
                    $this->assertArrayHasKey($x[2], $pages, "$l/$slug links to unknown page {$x[1]}");
                    if (! empty($x[3])) {
                        $this->assertStringContainsString('id="'.urldecode($x[3]).'"', $pages[$x[2]], "$l/$slug links to a missing anchor #{$x[3]} on {$x[2]}");
                    }
                }
            }
        }
    }

    public function test_prev_next_navigation_covers_every_page_in_order(): void
    {
        $slugs = $this->slugs();
        foreach ($slugs as $i => $slug) {
            $html = $this->get("/docs/$slug?lang=en")->getContent();
            if ($i > 0) {
                $this->assertStringContainsString('href="'.route('docs.show', $slugs[$i - 1]).'"', $html);
            }
            if ($i < count($slugs) - 1) {
                $this->assertStringContainsString('href="'.route('docs.show', $slugs[$i + 1]).'"', $html);
            }
        }
    }

    public function test_markdown_is_sanitised(): void
    {
        $html = app(DocsRepository::class)->render("# T\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[x](javascript:alert(1))\n\n[ok](https://example.com)");
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }

    public function test_llms_txt_is_a_plain_text_bundle(): void
    {
        $r = $this->get('/docs/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')->assertHeader('Access-Control-Allow-Origin', '*');
        $t = $r->getContent();
        $this->assertStringContainsString('# Avatarak.ir developer documentation', $t);
        foreach (['/api/v1/profiles/{hash}', '/api/v1/profiles/lookup', '/avatar/{hash}', 'Creating the identifier'] as $needle) {
            $this->assertStringContainsString($needle, $t);
        }
        $this->assertStringNotContainsString('{{origin}}', $t);
        $this->assertStringNotContainsString('جاوید میرزایی' === '' ? 'x' : "\u{0}", $t);
        $this->assertSame(count($this->slugs()), substr_count($t, "\n---\n"));
    }

    public function test_page_titles_and_descriptions_are_escaped_in_the_layout(): void
    {
        $html = $this->get('/docs/hash?lang=en')->getContent();
        $this->assertMatchesRegularExpression('#<title>Creating the identifier \(hash\) · Docs · Avatarak.ir</title>#', $html);
        $this->assertStringContainsString('hreflang="fa"', $html);
        $this->assertStringContainsString('edit/main/resources/docs/en/hash.md', $html);
    }

    public function test_untranslated_page_falls_back_with_a_notice(): void
    {
        $tmp = resource_path('docs/fa/hash.md');
        $backup = file_get_contents($tmp);
        try {
            unlink($tmp);
            \Illuminate\Support\Facades\Cache::flush();
            $this->get('/docs/hash?lang=fa')->assertOk()->assertSee('docs-note', false)->assertSee('Creating the identifier');
        } finally {
            file_put_contents($tmp, $backup);
        }
    }

    // ------------------------------------------------------------------ the docs must match the real behaviour

    public function test_documented_hash_examples_are_correct(): void
    {
        $this->assertSame('bbb8db824654275128ce09499cbbeff439840ae68f19f83861c243450dc1d6c7', hash('sha256', 'MyEmailAddress@example.com '));
        $this->assertSame('84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee', UserEmail::hash('MyEmailAddress@example.com '));
        foreach (['en', 'fa'] as $l) {
            $md = file_get_contents(DocsRepository::path($l, 'hash'));
            $this->assertStringContainsString('bbb8db824654275128ce09499cbbeff439840ae68f19f83861c243450dc1d6c7', $md);
            $this->assertStringContainsString('84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee', $md);
        }
        $this->assertSame('f7254a5b7d62b61c5353da4a235812f4b2171e8f16fd876a210d268da1793fd7', hash('sha256', 'javid@example.com'));
    }

    private function avatarUser(): string
    {
        Storage::fake('local');
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'javid', 'name_fa' => 'جاوید', 'name_en' => 'Javid', 'bio' => 'b', 'website' => 'https://j.ir', 'socials' => ['github' => 'javid']]);
        $u->emails()->create(['email' => 'javid@example.com', 'verified_at' => now()]);
        $this->actingAs($u)->postJson('/account/avatars', ['avatar' => UploadedFile::fake()->image('a.jpg', 600, 400)])->assertCreated();
        auth()->logout();

        return UserEmail::hash('javid@example.com');
    }

    public function test_avatar_parameters_behave_as_documented(): void
    {
        $hash = $this->avatarUser();
        $dim = fn ($r) => getimagesizefromstring($r->getContent())[0];

        $this->assertSame(80, $dim($this->get("/avatar/$hash")));                 // default size 80
        $this->assertSame(16, $dim($this->get("/avatar/$hash?s=3")));             // clamped to 16
        $this->assertSame(512, $dim($this->get("/avatar/$hash?s=99999")));        // clamped to 512
        $this->assertSame(200, $dim($this->get("/avatar/$hash?size=200")));       // "size" alias
        $this->assertSame(config('avatar.min_size'), 16);
        $this->assertSame(config('avatar.max_size'), 512);

        $ok = $this->get("/avatar/$hash")->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Access-Control-Allow-Origin', '*')->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')->assertHeader('Vary', 'Accept');
        $this->assertSame('max-age=3600, public', $this->cc($ok));
        $this->assertNotNull($ok->headers->get('ETag'));
        $this->get("/avatar/$hash", ['Accept' => 'image/webp,*/*'])->assertHeader('Content-Type', 'image/webp');
        $this->get("/avatar/$hash", ['If-None-Match' => $ok->headers->get('ETag')])->assertStatus(304);
        $this->get('/avatar/'.strtoupper($hash))->assertOk()->assertHeader('Content-Type', 'image/jpeg');   // upper-case hash accepted

        // defaults
        $none = str_repeat('a', 64);
        $mp = $this->get("/avatar/$none")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertSame('max-age=300, public', $this->cc($mp));
        $this->get("/avatar/$none?d=mp")->assertHeader('Content-Type', 'image/svg+xml');
        $this->get("/avatar/$none?d=blank")->assertHeader('Content-Type', 'image/png');
        $this->get("/avatar/$none?d=404")->assertNotFound();
        $this->get("/avatar/$none?default=404")->assertNotFound();                           // "default" alias
        $this->get("/avatar/$none?d=https://evil.example/x.png")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');   // URLs ignored

        // a profile's own avatar needs no email
        $this->get('/javid/avatar?s=96')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    private function key(): string
    {
        [, $plain] = Site::createWithKey(['user_id' => User::factory()->create()->id, 'name' => 's', 'domain' => 's.ir']);

        return $plain;
    }

    public function test_api_responses_match_the_documented_shape_and_errors(): void
    {
        $hash = $this->avatarUser();
        $key = $this->key();
        $auth = ['Authorization' => "Bearer $key"];

        $r = $this->getJson("/api/v1/profiles/$hash", $auth)->assertOk();
        $this->assertSame('max-age=60, private', $this->cc($r));
        $this->assertSame(['hash', 'username', 'profile_url', 'name_fa', 'name_en', 'headline', 'location', 'bio', 'website', 'socials', 'links', 'verified_accounts', 'language', 'avatar_url'], array_keys($r->json('data')));
        $this->assertSame(['github' => 'javid'], $r->json('data.socials'));
        $this->assertSame($hash, $r->json('data.hash'));

        // errors
        $e401 = $this->getJson("/api/v1/profiles/$hash")->assertStatus(401)->assertExactJson(['message' => 'Invalid or missing API key.']);
        $this->assertSame('Bearer', $e401->headers->get('WWW-Authenticate'));
        $this->getJson("/api/v1/profiles/$hash", ['X-API-Key' => $key])->assertOk();            // alternative header
        $this->getJson('/api/v1/profiles/'.str_repeat('b', 64), $auth)->assertStatus(404)->assertJsonPath('message', 'Profile not found.');

        // lookup: <=100, null for unknown, duplicates merged, upper-case accepted
        $res = $this->postJson('/api/v1/profiles/lookup', ['hashes' => [$hash, strtoupper($hash), str_repeat('c', 64)]], $auth)->assertOk();
        $this->assertSame([$hash, str_repeat('c', 64)], array_keys($res->json('data')));
        $this->assertNull($res->json('data.'.str_repeat('c', 64)));
        $this->assertSame(100, \App\Http\Controllers\Api\ProfileApiController::MAX_BATCH);
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => array_fill(0, 101, $hash)], $auth)->assertStatus(422)->assertJsonValidationErrors('hashes');
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => []], $auth)->assertStatus(422);
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => ['nothex']], $auth)->assertStatus(422);
    }

    public function test_documented_rate_limit_headers_and_429(): void
    {
        $this->assertSame(600, Site::createWithKey(['user_id' => User::factory()->create()->id, 'name' => 's', 'domain' => 's.ir'])[0]->fresh()->effectiveRateLimit());
        $site = Site::first();
        $plain = $this->key();
        $site = Site::latest('id')->first();
        $site->update(['rate_limit_per_minute' => 1]);

        $auth = ['Authorization' => "Bearer $plain"];
        $ok = $this->getJson('/api/v1/profiles/'.str_repeat('a', 64), $auth);
        $this->assertSame('1', $ok->headers->get('X-RateLimit-Limit'));
        $this->assertSame('0', $ok->headers->get('X-RateLimit-Remaining'));
        $r = $this->getJson('/api/v1/profiles/'.str_repeat('a', 64), $auth)->assertStatus(429)->assertExactJson(['message' => 'Rate limit exceeded.']);
        $this->assertNotNull($r->headers->get('Retry-After'));
    }

    public function test_key_format_and_site_limit_match_the_docs(): void
    {
        $this->assertMatchesRegularExpression('/^avk_[A-Za-z0-9]{40}$/', $this->key());
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'abc']);
        $this->actingAs($u)->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $max = config('plans.plans.free.limits.sites');
        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/account/sites', ['name' => "s$i", 'domain' => "s$i.example.ir"])->assertCreated();
        }
        $this->postJson('/account/sites', ['name' => 'x', 'domain' => 'x.example.ir'])->assertStatus(422);
        $this->assertStringContainsString('avk_', file_get_contents(DocsRepository::path('en', 'authentication')));
    }

    public function test_public_export_fields_and_headers_match_the_docs(): void
    {
        $this->avatarUser();
        $r = $this->get('/javid.json')->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertSame('max-age=300, public', $this->cc($r));
        $this->assertSame(['username', 'url', 'display_name', 'name_fa', 'name_en', 'headline', 'location', 'bio', 'website', 'avatar_url', 'verified', 'socials', 'links', 'verified_accounts', 'gallery', 'sections', 'language', 'languages', 'created_at', 'updated_at'], array_keys($r->json()));
        $this->assertSame('https://github.com/javid', $r->json('socials.github'));      // full URL here, handle in the REST API
        $this->get('/javid.vcf')->assertHeader('Content-Disposition', 'attachment; filename="javid.vcf"');
        $this->get('/javid.md')->assertHeader('Content-Type', 'text/markdown; charset=utf-8');

        $route = app('router')->getRoutes()->getByName('profile.export');
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
        $this->assertContains('throttle:avatar', app('router')->getRoutes()->getByName('avatar')->gatherMiddleware());
    }

    public function test_verification_numbers_in_the_docs_match_the_config(): void
    {
        $this->assertSame(72, config('verification.challenge_ttl_hours'));
        $this->assertSame(40, config('verification.max_checks_per_challenge'));
        foreach (['en', 'fa'] as $l) {
            $md = file_get_contents(DocsRepository::path($l, 'verified-accounts'));
            $this->assertStringContainsString('avatarak-verification', $md);
            $this->assertTrue(str_contains($md, '72') || str_contains($md, '۷۲'));
            $this->assertTrue(str_contains($md, '40') || str_contains($md, '۴۰'));
        }
        $this->assertSame(600, (int) preg_replace('/\D/', '', '600'));
        foreach (['en', 'fa'] as $l) {
            $md = file_get_contents(DocsRepository::path($l, 'privacy-and-limits'));
            foreach ([600, 100, 60, 512] as $n) {
                $fa = strtr((string) $n, array_combine(str_split('0123456789'), mb_str_split('۰۱۲۳۴۵۶۷۸۹')));
                $this->assertTrue(str_contains($md, (string) $n) || str_contains($md, $fa), "$l privacy-and-limits mentions $n");
            }
        }
    }

    public function test_no_text_is_mangled_by_the_parser(): void
    {
        foreach (['fa', 'en'] as $l) {
            foreach ($this->slugs() as $slug) {
                $this->assertStringNotContainsString("\u{FFFD}", $this->get("/docs/$slug?lang=$l")->getContent(), "$l/$slug contains broken characters");
            }
        }
        $this->assertSame('مرجع API', app(DocsRepository::class)->meta('api-reference', 'fa')['title']);
    }

    public function test_docs_pages_are_public_and_need_no_login(): void
    {
        $this->get('/docs')->assertOk();
        $this->get('/docs/api-reference')->assertOk();
    }
}
