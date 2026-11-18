<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $a = []): Profile
    {
        return Profile::create($a + ['user_id' => User::factory()->create()->id, 'username' => 'seoguy', 'name_en' => 'Seo Guy', 'headline' => 'Engineer', 'location' => 'Tehran',
            'bio' => "Builds things.\nLikes <b>tags</b>.", 'allow_indexing' => true, 'languages' => 'en', 'primary_lang' => 'en',
            'socials' => ['github' => 'seoguy'], 'website' => 'https://seo.example']);
    }

    private function ld(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return json_decode($m[1] ?? 'null', true) ?? [];
    }

    public function test_indexable_profile_has_canonical_og_twitter_and_a_person_schema(): void
    {
        $this->profile();
        $r = $this->get('/seoguy')->assertOk();
        $r->assertSee('<link rel="canonical" href="'.url('/seoguy').'">', false)
            ->assertSee('property="og:type" content="profile"', false)->assertSee('property="og:title" content="Seo Guy — ', false)
            ->assertSee('property="og:image" content="', false)->assertSee('name="twitter:card" content="summary"', false)
            ->assertSee('property="og:locale" content="en_US"', false)->assertDontSee('noindex', false);
        $ld = $this->ld($r->getContent());
        $this->assertSame('ProfilePage', $ld['@type']);
        $this->assertSame('Person', $ld['mainEntity']['@type']);
        $this->assertSame('Seo Guy', $ld['mainEntity']['name']);
        $this->assertSame('Engineer', $ld['mainEntity']['jobTitle']);
        $this->assertSame('Tehran', $ld['mainEntity']['homeLocation']['name']);
        $this->assertContains('https://github.com/seoguy', $ld['mainEntity']['sameAs']);
        $this->assertContains('https://seo.example', $ld['mainEntity']['sameAs']);
        $this->assertSame('en', $ld['inLanguage']);
    }

    public function test_structured_data_never_carries_private_data_and_cannot_break_out_of_the_script_tag(): void
    {
        $u = User::factory()->create(['email' => 'secret@example.com']);
        $this->profile(['user_id' => $u->id, 'headline' => '</script><script>alert(1)</script>']);
        $html = $this->get('/seoguy')->getContent();
        $this->assertStringNotContainsString('secret@example.com', $html);
        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertSame('</script><script>alert(1)</script>', $this->ld($html)['mainEntity']['jobTitle']);
    }

    public function test_noindex_profiles_have_no_schema_but_still_have_link_previews(): void
    {
        $this->profile(['allow_indexing' => false]);
        $r = $this->get('/seoguy')->assertOk()->assertSee('noindex, nofollow', false)->assertSee('property="og:title"', false);
        $this->assertSame([], $this->ld($r->getContent()));
    }

    public function test_description_comes_from_the_bio_then_headline_then_a_default(): void
    {
        $this->profile();
        $this->get('/seoguy')->assertSee('<meta name="description" content="Builds things. Likes &lt;b&gt;tags&lt;/b&gt;.">', false);
        Profile::where('username', 'seoguy')->update(['bio' => null]);
        $this->get('/seoguy')->assertSee('content="Seo Guy — Engineer · Tehran"', false);
        Profile::where('username', 'seoguy')->update(['headline' => null, 'location' => null]);
        $this->get('/seoguy')->assertSee('public profile on', false);
    }

    public function test_bilingual_profile_has_hreflang_with_x_default_and_per_language_canonicals(): void
    {
        $this->profile(['languages' => 'both', 'primary_lang' => 'en', 'name_fa' => 'سئو']);
        $en = $this->get('/seoguy')->assertSee('hreflang="x-default" href="'.url('/seoguy').'"', false)
            ->assertSee('hreflang="fa" href="'.url('/seoguy').'?lang=fa"', false)->assertSee('hreflang="en" href="'.url('/seoguy').'"', false)
            ->assertSee('<link rel="canonical" href="'.url('/seoguy').'">', false)->assertSee('og:locale:alternate" content="fa_IR"', false);
        $this->get('/seoguy?lang=fa')->assertSee('<link rel="canonical" href="'.url('/seoguy').'?lang=fa">', false)->assertSee('property="og:locale" content="fa_IR"', false);
        $this->assertSame('سئو', $this->ld($this->get('/seoguy')->getContent())['mainEntity']['alternateName']);
    }

    public function test_data_exports_are_never_indexed(): void
    {
        $this->profile();
        foreach (['json', 'md', 'vcf'] as $f) {
            $this->get("/seoguy.$f")->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_robots_txt_blocks_private_areas_and_points_to_the_sitemap(): void
    {
        $r = $this->get('/robots.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', $r->headers->get('Content-Type'));
        $r->assertSee('Disallow: /panel')->assertSee('Disallow: /admin')->assertSee('Disallow: /account')->assertSee('Sitemap: '.url('/sitemap.xml'));
        $this->assertFileDoesNotExist(public_path('robots.txt'));      // a static file would shadow the route
    }

    public function test_sitemap_lists_only_indexable_visible_profiles_with_something_to_say(): void
    {
        $this->profile();                                                                                                   // listed
        $this->profile(['username' => 'hidden1', 'allow_indexing' => false]);                                               // owner said no
        $this->profile(['username' => 'private1', 'show_profile' => false]);                                                // not public
        $this->profile(['username' => 'thin1', 'bio' => null, 'headline' => null]);                                         // nothing to say
        $this->profile(['username' => 'noname1', 'name_en' => null, 'name_fa' => null]);                                    // no name
        $s = User::factory()->create(['suspended_at' => now()]);
        $this->profile(['username' => 'susp1', 'user_id' => $s->id]);                                                      // suspended
        $this->profile(['username' => 'dualg', 'languages' => 'both', 'name_fa' => 'دو']);

        $index = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<sitemapindex', $index);
        $this->assertStringContainsString(url('/sitemap-pages.xml'), $index);
        $this->assertStringContainsString(url('/sitemap-profiles-1.xml'), $index);
        $this->assertStringNotContainsString('sitemap-profiles-2', $index);

        $xml = $this->get('/sitemap-profiles-1.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>'.url('/seoguy').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/dualg').'</loc>', $xml);
        $this->assertStringContainsString('hreflang="x-default"', $xml);
        foreach (['hidden1', 'private1', 'thin1', 'noname1', 'susp1'] as $u) {
            $this->assertStringNotContainsString('/'.$u.'<', $xml);
        }
        $this->get('/sitemap-profiles-2.xml')->assertNotFound();
        $simple = simplexml_load_string($xml);
        $this->assertNotFalse($simple);                                                                                      // well-formed
    }

    public function test_pages_sitemap_lists_the_public_site(): void
    {
        $xml = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        foreach (['/', '/docs', '/docs/getting-started', '/terms', '/privacy'] as $p) {
            $this->assertStringContainsString('<loc>'.url($p).'</loc>', $xml);
        }
        $this->assertStringNotContainsString('/panel', $xml);
    }

    public function test_home_page_is_readable_without_javascript_and_has_site_schemas(): void
    {
        foreach (['/' => 'fa', '/en' => 'en'] as $path => $l) {
            $h = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('<html lang="'.$l.'"', $h);
            $this->assertStringContainsString('<link rel="canonical" href="'.($l === 'en' ? url('/en') : url('/')).'">', $h);
            $this->assertStringContainsString('property="og:image" content="'.url('/brand/og-default.jpg').'"', $h);
            $this->assertStringContainsString('summary_large_image', $h);
            $this->assertStringNotContainsString('noindex', $h);
            $this->assertMatchesRegularExpression('#<h1[^>]*>[^<]+</h1>#', $h);
            $types = collect($this->ld($h)['@graph'])->pluck('@type')->all();
            $this->assertSame(['WebSite', 'Organization', 'SoftwareApplication', 'FAQPage'], $types);
        }
        // the structured FAQ is exactly the FAQ the page shows
        $faq = collect($this->ld($this->get('/en')->getContent())['@graph'])->firstWhere('@type', 'FAQPage');
        $this->assertCount(5, $faq['mainEntity']);
        $this->assertSame('Is it really free?', $faq['mainEntity'][0]['name']);
        $this->get('/')->assertSee('مستندات');
    }

    public function test_each_language_of_the_home_page_has_its_own_address_and_points_at_the_other(): void
    {
        foreach (['/', '/en'] as $p) {
            $this->get($p)->assertSee('hreflang="fa" href="'.url('/').'"', false)->assertSee('hreflang="en" href="'.url('/en').'"', false)->assertSee('hreflang="x-default" href="'.url('/').'"', false);
        }
        $this->get('/en')->assertSee('dir="ltr"', false)->assertSee('og:locale" content="en_US"', false)->assertSee('og:locale:alternate" content="fa_IR"', false)->assertSee('One photo, one identity');
        $this->get('/')->assertSee('dir="rtl"', false)->assertSee('og:locale" content="fa_IR"', false)->assertSee('یک عکس');
        // the address decides: a saved language choice never changes what a crawler (or anyone) gets at an address
        $this->withUnencryptedCookie('locale', 'en')->get('/')->assertSee('<html lang="fa"', false)->assertSee('یک عکس');
        $this->withUnencryptedCookie('locale', 'fa')->get('/en')->assertSee('<html lang="en"', false);
        // the default language has no second address
        $this->get('/fa')->assertRedirect('/')->assertStatus(301);
    }

    public function test_default_language_can_be_english_and_then_persian_lives_at_slash_fa(): void
    {
        config(['brand.default_locale' => 'en']);
        $this->get('/')->assertSee('<html lang="en"', false)->assertSee('<link rel="canonical" href="'.url('/').'">', false);
        $this->get('/fa')->assertOk()->assertSee('<html lang="fa"', false)->assertSee('<link rel="canonical" href="'.url('/fa').'">', false);
        $this->get('/en')->assertRedirect('/')->assertStatus(301);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/').'"', $this->get('/fa')->getContent());
    }

    public function test_pages_sitemap_pairs_the_languages_of_every_page(): void
    {
        $xml = $this->get('/sitemap-pages.xml')->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('<loc>'.url('/en').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/docs').'?lang=en</loc>', $xml);
        $this->assertStringContainsString('hreflang="en" href="'.url('/terms').'?lang=en"', $xml);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/docs/getting-started').'"', $xml);
    }

    public function test_docs_pages_have_canonical_article_schema_breadcrumbs_and_hreflang(): void
    {
        $h = $this->get('/docs/getting-started')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/docs/getting-started').'">', $h);
        $this->assertStringContainsString('hreflang="en" href="'.url('/docs/getting-started').'?lang=en"', $h);
        $this->assertStringContainsString('hreflang="x-default"', $h);
        $this->assertStringContainsString('property="og:type" content="article"', $h);
        $types = collect($this->ld($h)['@graph'])->pluck('@type')->all();
        $this->assertSame(['TechArticle', 'BreadcrumbList'], $types);
        $g = collect($this->ld($h)['@graph']);
        $this->assertSame(3, count($g->firstWhere('@type', 'BreadcrumbList')['itemListElement']));
        $this->assertSame('fa', $g->firstWhere('@type', 'TechArticle')['inLanguage']);

        $en = $this->get('/docs/getting-started?lang=en')->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/docs/getting-started').'?lang=en">', $en);
        $this->assertSame('en', collect($this->ld($en)['@graph'])->firstWhere('@type', 'TechArticle')['inLanguage']);

        $idx = $this->get('/docs')->getContent();
        $this->assertSame(['CollectionPage', 'BreadcrumbList'], collect($this->ld($idx)['@graph'])->pluck('@type')->all());
        $this->get('/docs/api-explorer')->assertSee('<link rel="canonical" href="'.url('/docs/api-explorer').'">', false);
    }

    public function test_legal_pages_have_canonical_webpage_schema_and_hreflang(): void
    {
        foreach (['terms', 'privacy'] as $p) {
            $h = $this->get("/$p")->assertOk()->getContent();
            $this->assertStringContainsString('<link rel="canonical" href="'.url("/$p").'">', $h);
            $this->assertStringContainsString('hreflang="en" href="'.url("/$p").'?lang=en"', $h);
            $this->assertSame(['WebPage', 'BreadcrumbList'], collect($this->ld($h)['@graph'])->pluck('@type')->all());
        }
    }

    public function test_dashboard_and_admin_shells_are_not_indexable(): void
    {
        foreach (['/panel', '/admin'] as $p) {
            $h = $this->get($p)->assertOk()->getContent();
            $this->assertStringContainsString('noindex, nofollow', $h);
            $this->assertStringNotContainsString('application/ld+json', $h);
            $this->assertStringNotContainsString('rel="canonical"', $h);
        }
    }

    public function test_landing_seo_data_matches_the_page_copy(): void
    {
        // resources/data/landing-seo.json is generated from copy-data.js (npm run build); catch a forgotten regeneration
        $json = json_decode(file_get_contents(resource_path('data/landing-seo.json')), true);
        $src = file_get_contents(resource_path('js/components/landing/copy-data.js'));
        foreach (['fa', 'en'] as $l) {
            foreach ($json[$l]['faq'] as [$q, $a]) {
                $this->assertStringContainsString(addcslashes($q, "'"), $src, "stale FAQ question in landing-seo.json ($l); run npm run build");
            }
            $this->assertStringContainsString($json[$l]['title'], $src);
        }
    }

    public function test_browsers_get_a_clickable_page_and_crawlers_get_the_xml(): void
    {
        $this->profile();
        // a crawler: plain XML
        foreach (['/sitemap.xml', '/sitemap-pages.xml', '/sitemap-profiles-1.xml'] as $p) {
            $r = $this->get($p)->assertOk();
            $this->assertStringStartsWith('application/xml', $r->headers->get('Content-Type'));
            $this->assertNotFalse(simplexml_load_string($r->getContent()));
            $this->assertStringContainsString('Sec-Fetch-Dest', $r->headers->get('Vary'));
        }
        // a person in a browser: the same data as links
        $this->withHeader('Sec-Fetch-Dest', 'document')->get('/sitemap.xml')->assertOk()
            ->assertSee('<a href="'.url('/sitemap-profiles-1.xml').'">', false)->assertSee('noindex', false);
        $this->withHeader('Sec-Fetch-Dest', 'document')->get('/sitemap-profiles-1.xml')->assertOk()
            ->assertSee('<a href="'.url('/seoguy').'">', false)->assertSee('Last modified');
        // a browser fetching it as a resource (e.g. a validator tool) still gets XML
        $this->withHeader('Sec-Fetch-Dest', 'empty')->get('/sitemap.xml')->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function test_prerendered_home_page_is_embedded_with_the_installs_own_brand(): void
    {
        $file = resource_path('data/landing-prerender-fa.html');
        $backup = is_file($file) ? file_get_contents($file) : null;
        try {
            file_put_contents($file, '<div class="x"><h1>سلام</h1><b>@@BRAND_NAME@@|@@BRAND_NAME_EN@@|@@ORIGIN@@</b></div>');
            config(['brand.name' => 'برند <من>', 'brand.name_en' => '', 'brand.logo_url' => null]);
            $h = $this->get('/')->assertOk()->getContent();
            $this->assertStringContainsString('<div id="app" data-prerendered><div class="x"><h1>سلام</h1>', $h);     // attached node-for-node: no whitespace in between
            $this->assertStringContainsString('<b>برند &lt;من&gt;|برند &lt;من&gt;|'.rtrim(url('/'), '/').'</b>', $h);      // tokens filled in and escaped
            $this->assertStringNotContainsString('position:absolute;width:1px', $h);                                      // the crawler-only copy is not needed

            // a custom logo changes the markup, so such an install falls back to the plain text
            config(['brand.logo_url' => 'https://cdn.example/logo.png']);
            $h = $this->get('/')->getContent();
            $this->assertStringNotContainsString('data-prerendered', $h);
            $this->assertStringContainsString('position:absolute;width:1px', $h);
        } finally {
            $backup === null ? @unlink($file) : file_put_contents($file, $backup);
        }
    }
}
