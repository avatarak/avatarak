<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\DefaultAvatars;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultAvatarTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $attrs = []): Profile
    {
        $u = User::factory()->create();

        return Profile::create($attrs + ['user_id' => $u->id, 'username' => 'javid', 'name_fa' => 'جاوید میرزایی', 'name_en' => 'Javid Mirzaei']);
    }

    public static function initials(): array
    {
        return [['جاوید میرزایی', 'جم'], ['Javid Mirzaei', 'JM'], ['ali', 'A'], ['  ali   reza   x ', 'AR'], ['علی_رضا', 'عر'], ['', '?'], ['---', '?'], ['42 nine', '4N'], ["Dr. O'Neil", 'DO']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('initials')]
    public function test_initials(string $name, string $expected): void
    {
        $this->assertSame($expected, DefaultAvatars::initials($name));
    }

    public function test_colour_rule_is_stable_and_pinned(): void
    {
        // these exact values are mirrored by InitialsAvatar.vue; change both together
        $this->assertSame(4210934747, DefaultAvatars::fnv1a('javid'));
        $this->assertSame(11, DefaultAvatars::colorIndex('javid'));
        $this->assertSame(DefaultAvatars::colorIndex('javid'), DefaultAvatars::colorIndex('  JAVID '));
        $this->assertCount(12, DefaultAvatars::PALETTE);
    }

    public function test_identicon_is_deterministic_symmetric_and_depends_only_on_the_hash(): void
    {
        $h = hash('sha256', 'someone@example.com');
        $a = $this->get("/avatar/$h?d=identicon")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertSame($a->getContent(), $this->get("/avatar/$h?d=identicon")->getContent());
        $this->assertNotSame($a->getContent(), $this->get('/avatar/'.hash('sha256', 'other@example.com').'?d=identicon')->getContent());
        $this->assertStringContainsString('width="80"', $this->get("/avatar/$h?d=identicon&s=80")->getContent());
        $this->assertStringContainsString('width="256"', $this->get("/avatar/$h?d=identicon&s=256")->getContent());

        // mirror image: every cell at column c has a twin at column 4-c
        preg_match_all('/<rect x="(\d+)" y="(\d+)" width="10"/', $a->getContent(), $m, PREG_SET_ORDER);
        $cells = array_map(fn ($x) => $x[1].','.$x[2], $m);
        foreach ($m as [, $x, $y]) {
            $this->assertContains((15 + (4 - intdiv($x - 15, 10)) * 10).','.$y, $cells);
        }
    }

    public function test_identicon_cannot_tell_known_from_unknown_hashes(): void
    {
        $p = $this->profile();
        $u = $p->user;
        $u->emails()->create(['email' => 'known@example.com', 'verified_at' => now()]);       // verified, public, but no photo
        $u->emails()->create(['email' => 'unverified@example.com']);

        foreach (['known@example.com', 'unverified@example.com', 'nobody@example.com'] as $e) {
            $h = UserEmail::hash($e);
            $body = $this->get("/avatar/$h?d=identicon")->assertOk()->getContent();
            $this->assertSame((new DefaultAvatars)->identicon($h, 80), $body, 'looks the same whatever the account state');
        }
    }

    public function test_hash_endpoint_never_shows_initials_even_if_asked(): void
    {
        $p = $this->profile();
        $p->user->emails()->create(['email' => 'known@example.com', 'verified_at' => now()]);
        $svg = $this->get('/avatar/'.UserEmail::hash('known@example.com').'?d=initials')->assertOk()->getContent();
        $this->assertStringNotContainsString('<text', $svg, 'would reveal that the address belongs to a profile');
        $this->assertStringContainsString('#9ca3af', $svg);
    }

    public function test_profile_without_a_photo_gets_its_initials(): void
    {
        $this->profile();
        $r = $this->get('/javid/avatar?s=120')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('>جم</text>', $r->getContent());
        $this->assertStringContainsString(DefaultAvatars::PALETTE[11][0], $r->getContent());
        $this->assertStringContainsString('width="120"', $r->getContent());
        $this->assertSame('max-age=300, public', collect(explode(',', $r->headers->get('Cache-Control')))->map(fn ($x) => trim($x))->sort()->implode(', '));
    }

    public function test_profile_avatar_default_can_be_overridden_and_unknown_or_hidden_profiles_stay_neutral(): void
    {
        $this->profile();
        $this->get('/javid/avatar?d=mp')->assertOk()->assertSee('#9ca3af', false);
        $this->get('/javid/avatar?d=404')->assertNotFound();
        $this->get('/javid/avatar?d=blank')->assertHeader('Content-Type', 'image/png');
        $this->get('/javid/avatar?d=identicon')->assertOk()->assertDontSee('<text', false);

        $this->get('/nobody/avatar')->assertOk()->assertDontSee('<text', false)->assertSee('#9ca3af', false);
        Profile::where('username', 'javid')->update(['show_profile' => false]);
        $this->get('/javid/avatar')->assertOk()->assertDontSee('<text', false)->assertDontSee('جم', false);
    }

    public function test_initials_are_xml_escaped(): void
    {
        $this->profile(['username' => 'evil', 'name_fa' => null, 'name_en' => '<script>']);
        $svg = $this->get('/evil/avatar')->getContent();
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertSame(1, preg_match('/>S<\/text>/', $svg));
    }

    public function test_etag_changes_with_the_name_and_304_works(): void
    {
        $p = $this->profile();
        $r = $this->get('/javid/avatar')->assertOk();
        $this->get('/javid/avatar', ['If-None-Match' => $r->headers->get('ETag')])->assertStatus(304);
        $p->update(['name_fa' => 'علی رضایی']);
        $r2 = $this->get('/javid/avatar');
        $this->assertNotSame($r->headers->get('ETag'), $r2->headers->get('ETag'));
        $this->assertStringContainsString('>عر</text>', $r2->getContent());
    }

    public function test_public_page_uses_the_initials_image_when_there_is_no_photo(): void
    {
        $this->profile();
        $this->get('/javid')->assertOk()->assertSee('/javid/avatar?s=296&amp;d=initials', false);
        $this->get('/javid.card')->assertOk()->assertSee('/javid/avatar?s=128&amp;d=initials', false);
    }
}
