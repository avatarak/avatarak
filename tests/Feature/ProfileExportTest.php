<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileExportTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $attrs = []): Profile
    {
        $u = User::factory()->create(['email' => 'secret-login@private.ir']);
        $u->emails()->create(['email' => 'private@mail.ir', 'verified_at' => now()]);

        return Profile::create($attrs + [
            'user_id' => $u->id, 'username' => 'javid', 'name_fa' => 'جاوید میرزایی', 'name_en' => 'Javid Mirzaei',
            'headline' => 'Engineer, Manova; "Inc"', 'location' => 'Tehran', 'bio' => "Line one, with comma;\nLine two",
            'website' => 'https://javid.ir', 'socials' => ['github' => 'javid', 'telegram' => 'javid_t'],
            'links' => [['label' => 'My [blog]', 'url' => 'https://blog.ir', 'description' => 'Notes', 'thumb' => str_repeat('a', 40)]],
            'avatar_path' => 'avatars/x.jpg', 'avatar_version' => 'v1',
        ]);
    }

    public function test_json_is_public_complete_and_never_contains_emails(): void
    {
        $this->profile();
        $r = $this->get('/javid.json')->assertOk()->assertHeader('Content-Type', 'application/json; charset=utf-8')
            ->assertHeader('Access-Control-Allow-Origin', '*')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $d = $r->json();
        $this->assertSame('javid', $d['username']);
        $this->assertSame('جاوید میرزایی', $d['display_name']);
        $this->assertSame('https://github.com/javid', $d['socials']['github']);
        $this->assertSame('My [blog]', $d['links'][0]['label']);
        $this->assertSame(route('link.thumb', str_repeat('a', 40)), $d['links'][0]['image']);
        $this->assertTrue($d['verified']);
        $this->assertStringContainsString('/javid/avatar', $d['avatar_url']);
        $this->assertStringNotContainsString('@', $r->getContent());          // no emails anywhere
        $this->assertStringContainsString('جاوید', $r->getContent());          // unescaped unicode
        $this->assertArrayNotHasKey('user_id', $d);
    }

    public function test_json_respects_privacy_switches(): void
    {
        $p = $this->profile(['show_avatar' => false, 'show_link_images' => false, 'allow_indexing' => true]);
        $r = $this->get('/javid.json')->assertOk();
        $this->assertNull($r->json('avatar_url'));
        $this->assertArrayNotHasKey('image', $r->json('links.0'));
        $this->assertSame('noindex, nofollow', $r->headers->get('X-Robots-Tag'));        // data files are never search results, whatever the page says

        $p->update(['show_profile' => false]);
        foreach (['json', 'md', 'vcf', 'card'] as $f) {
            $this->get("/javid.$f")->assertNotFound();
        }
        $this->get('/nobody.json')->assertNotFound();
        $this->get('/javid.xml')->assertNotFound();
        $this->get('/javid.php')->assertNotFound();
    }

    public function test_markdown(): void
    {
        $this->profile();
        $r = $this->get('/javid.md')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
        $md = $r->getContent();
        $this->assertStringStartsWith("# جاوید میرزایی\n", $md);
        $this->assertStringContainsString('- Github: <https://github.com/javid>', $md);
        $this->assertStringContainsString('- [My \\[blog\\]](https://blog.ir) — Notes', $md);
        $this->assertStringNotContainsString('@', $md);
    }

    public function test_vcard_is_valid_escaped_and_folded(): void
    {
        $this->profile(['bio' => "Hello, world; \\ x\nsecond line ".str_repeat('جاوید ', 40)]);
        $r = $this->get('/javid.vcf')->assertOk()->assertHeader('Content-Type', 'text/vcard; charset=utf-8');
        $this->assertStringContainsString('attachment; filename="javid.vcf"', $r->headers->get('Content-Disposition'));

        $raw = $r->getContent();
        $this->assertStringStartsWith("BEGIN:VCARD\r\nVERSION:3.0\r\n", $raw);
        $this->assertStringEndsWith("END:VCARD\r\n", $raw);
        $this->assertStringContainsString('TITLE:Engineer\\, Manova\; "Inc"', $raw);
        $this->assertStringContainsString('NOTE:Hello\\, world\; \\\\ x\\nsecond line', str_replace("\r\n ", '', $raw));
        $this->assertStringContainsString('X-SOCIALPROFILE;TYPE=github:https://github.com/javid', $raw);
        $this->assertStringNotContainsString('@', $raw);

        foreach (explode("\r\n", $raw) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line), 'folded lines stay within 75 octets (+1 leading space)');
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'folding never splits a multibyte character');
        }
        $this->assertStringNotContainsString("\n\n", str_replace("\r\n", "\n", $raw));
    }

    public function test_card_is_embeddable(): void
    {
        $this->profile();
        $r = $this->get('/javid.card')->assertOk()->assertSee('جاوید میرزایی')->assertSee('/javid', false);
        $this->assertNull($r->headers->get('X-Frame-Options'));
        $this->assertStringNotContainsString('<script', $r->getContent());
        $this->assertSame('noindex, nofollow', $r->headers->get('X-Robots-Tag'));
    }

    public function test_exports_cannot_be_used_to_read_other_things(): void
    {
        $this->profile();
        $this->get('/javid.json?x=../../.env')->assertOk();
        $this->assertStringNotContainsString('APP_KEY', $this->get('/javid.json')->getContent());
        $this->get('/..%2f.env.json')->assertNotFound();
    }
}
