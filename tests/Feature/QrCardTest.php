<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Services\QrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrCardTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $attrs = []): Profile
    {
        $u = User::factory()->create();

        return Profile::create($attrs + ['user_id' => $u->id, 'username' => 'javid', 'languages' => 'both', 'primary_lang' => 'en', 'name_fa' => 'جاوید میرزایی', 'name_en' => 'Javid', 'headline' => 'Engineer', 'location' => 'Tehran', 'accent_color' => '#0F766E']);
    }

    private function xml(string $svg): \SimpleXMLElement
    {
        $x = simplexml_load_string($svg);
        $this->assertNotFalse($x, 'must be well-formed XML');

        return $x;
    }

    public static function colors(): array
    {
        return [['#abc', [170, 187, 204]], ['ABC', [170, 187, 204]], ['#1D4FC4', [29, 79, 196]], ['nope', null], ['#12', null], ['#12345g', null], ['', null], [null, null]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('colors')]
    public function test_colour_parsing(?string $in, ?array $out): void
    {
        $this->assertSame($out, QrService::parseColor($in));
    }

    public function test_contrast_ratio(): void
    {
        $this->assertEqualsWithDelta(21.0, QrService::contrast([0, 0, 0], [255, 255, 255]), 0.01);
        $this->assertEqualsWithDelta(1.0, QrService::contrast([9, 9, 9], [9, 9, 9]), 0.01);
    }

    public function test_qr_is_a_valid_svg_of_the_requested_size(): void
    {
        $this->profile();
        $r = $this->get('/javid.qr')->assertOk()->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')->assertHeader('Access-Control-Allow-Origin', '*');
        $x = $this->xml($r->getContent());
        $this->assertSame('512', (string) $x['width']);
        $this->assertStringContainsString('<path', $r->getContent());

        $this->assertSame('300', (string) $this->xml($this->get('/javid.qr?size=300')->getContent())['width']);
        $this->assertSame('64', (string) $this->xml($this->get('/javid.qr?size=1')->getContent())['width'], 'clamped up');
        $this->assertSame('2048', (string) $this->xml($this->get('/javid.qr?size=99999')->getContent())['width'], 'clamped down');
    }

    public function test_qr_colours_and_encoded_content(): void
    {
        $this->profile();
        $svg = $this->get('/javid.qr?fg=1D4FC4&bg=%23fff')->getContent();
        $this->assertStringContainsString('#1d4fc4', strtolower($svg));

        $url = $this->get('/javid.qr')->getContent();
        $vcard = $this->get('/javid.qr?content=vcard')->getContent();
        $this->assertNotSame($url, $vcard, 'a vCard code is a different (denser) code');
        $this->assertNotSame($url, $this->get('/javid.qr?margin=0')->getContent());
        $this->assertSame($url, $this->get('/javid.qr?fg=nonsense')->getContent(), 'bad colours fall back to the default');
    }

    public function test_unscannable_colour_pairs_are_refused(): void
    {
        $this->profile();
        $this->get('/javid.qr?fg=ffffff&bg=eeeeee')->assertStatus(422);
        $this->get('/javid.qr?fg=777777&bg=888888')->assertStatus(422);
        $this->get('/javid.qr?fg=000&bg=fff')->assertOk();
    }

    public function test_download_header_and_hidden_profiles(): void
    {
        $p = $this->profile();
        $this->get('/javid.qr?download=1')->assertHeader('Content-Disposition', 'attachment; filename="javid-qr.svg"');
        $this->get('/javid.qr')->assertHeaderMissing('Content-Disposition');
        $p->update(['show_profile' => false]);
        $this->get('/javid.qr')->assertNotFound();
        $this->get('/javid/print')->assertNotFound();
        $this->get('/javid/share.svg')->assertNotFound();
        $this->get('/nobody.qr')->assertNotFound();
    }

    public function test_print_page_single_and_sheet(): void
    {
        $this->profile();
        $r = $this->get('/javid/print')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $html = $r->getContent();
        $this->assertStringContainsString('size: 85mm 55mm', $html);
        $this->assertSame(1, substr_count($html, 'class="bc"'));
        $this->assertStringContainsString('جاوید میرزایی', $html);
        $this->assertStringContainsString('Engineer', $html);
        $this->assertStringContainsString('<svg', $html, 'QR is inline, no external request');
        $this->assertStringContainsString(preg_replace('#^https?://#', '', route('profile.show', 'javid')), $html);
        $this->assertStringContainsString('#0F766E', $html, 'uses the profile accent');

        $sheet = $this->get('/javid/print?sheet=1')->assertOk()->getContent();
        $this->assertStringContainsString('size: A4', $sheet);
        $this->assertSame(10, substr_count($sheet, 'class="bc"'));
    }

    public function test_print_page_language_follows_the_cookie(): void
    {
        $this->profile();
        $this->withUnencryptedCookie('locale', 'en')->get('/javid/print')->assertOk()->assertSee('Back to profile')->assertSee('dir="ltr"', false);
        $this->withUnencryptedCookie('locale', 'fa')->get('/javid/print')->assertOk()->assertSee('بازگشت به پروفایل')->assertSee('dir="rtl"', false);
    }

    public function test_print_page_escapes_user_text(): void
    {
        $this->profile(['username' => 'evil', 'name_fa' => '<script>alert(1)</script>', 'name_en' => null, 'headline' => '"><img src=x onerror=alert(1)>']);
        $html = $this->get('/evil/print')->getContent();
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function test_share_image_without_a_photo_uses_initials(): void
    {
        $this->profile();
        $r = $this->get('/javid/share.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');
        $x = $this->xml($r->getContent());
        $this->assertSame(['1080', '1350'], [(string) $x['width'], (string) $x['height']]);
        $this->assertStringContainsString('جاوید میرزایی', $r->getContent());
        $this->assertStringContainsString('>جم</text>', $r->getContent());
        $this->assertStringNotContainsString('<image', $r->getContent());
        $this->assertStringContainsString('#0F766E', $r->getContent());
    }

    public function test_share_image_embeds_the_photo_and_escapes_text(): void
    {
        Storage::fake('local');
        $p = $this->profile(['name_fa' => 'A & B <i>', 'headline' => 'x"y']);
        $this->actingAs($p->user)->postJson('/account/avatars', ['avatar' => UploadedFile::fake()->image('a.jpg', 500, 500)])->assertCreated();
        auth()->logout();

        $r = $this->get('/javid/share.svg');
        $x = $this->xml($r->getContent());                            // still well-formed with &, <, "
        $this->assertStringContainsString('data:image/jpeg;base64,', $r->getContent());
        $this->assertStringContainsString('A &amp; B &lt;i&gt;', $r->getContent());
        $this->assertSame(1, substr_count($r->getContent(), '<image'));
        $this->assertNotNull($x);

        $p->fresh()->update(['show_avatar' => false]);
        $this->assertStringNotContainsString('<image', $this->get('/javid/share.svg')->getContent(), 'a hidden photo is not embedded');
    }

    public function test_share_image_download_header(): void
    {
        $this->profile();
        $this->get('/javid/share.svg?download=1')->assertHeader('Content-Disposition', 'attachment; filename="javid-card.svg"');
    }

    public function test_reserved_names_do_not_collide_with_the_new_routes(): void
    {
        $this->postJson('/register', ['name' => 'x', 'email' => 'q@x.ir', 'password' => 'secret-pass-1', 'username' => 'print'])->assertCreated();
        $this->get('/print')->assertOk();       // a profile called "print" is still reachable at /print
    }
}
