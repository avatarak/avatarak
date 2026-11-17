<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'sara', 'name_fa' => 'سارا', 'bio' => 'BIO_TEXT', 'website' => 'https://sara.ir', 'socials' => ['github' => 'sara'],
            'links' => [['label' => 'LINK_LABEL', 'url' => 'https://blog.sara.ir/x']]]);

        return $u;
    }

    public function test_accent_and_order_are_saved_and_rendered(): void
    {
        $u = $this->user();
        $this->actingAs($u)->patchJson('/account/appearance', ['accent_color' => '#0F9D7A', 'section_order' => ['links', 'bio']])
            ->assertOk()->assertJsonPath('profile.accent_color', '#0F9D7A');

        $html = $this->get('/u/sara')->assertOk()->assertSee('--accent: #0F9D7A', false)->getContent();
        $this->assertLessThan(strpos($html, 'leading-8">BIO_TEXT'), strpos($html, 'LINK_LABEL'), 'links card must precede bio card');
    }

    public function test_appearance_validation_blocks_css_injection(): void
    {
        $this->actingAs($this->user());
        foreach (['red', '#FFF', '#12345G', 'url(javascript:1)', '#FFFFFF;}</style><script>'] as $bad) {
            $this->patchJson('/account/appearance', ['accent_color' => $bad])->assertJsonValidationErrors('accent_color');
        }
        $this->patchJson('/account/appearance', ['section_order' => ['bio', 'bio']])->assertStatus(422);
        $this->patchJson('/account/appearance', ['section_order' => ['bio', 'evil']])->assertStatus(422);
        $this->patchJson('/account/appearance', ['hidden_sections' => ['website']])->assertStatus(422);
    }

    public function test_stored_bad_accent_is_ignored_when_rendering(): void
    {
        $u = $this->user();
        $u->profile->forceFill(['accent_color' => '#FFF;}</style><script>alert(1)</script>'])->save();
        $this->get('/u/sara')->assertOk()->assertDontSee('<script>alert(1)', false);
    }

    public function test_account_exposes_own_email_hash_only_to_owner(): void
    {
        $u = $this->user();
        $u->emails()->create(['email' => 'sara@x.ir', 'verified_at' => now()]);
        $this->getJson('/account')->assertUnauthorized();
        $this->actingAs($u)->getJson('/account')->assertJsonPath('emails.0.email_hash', UserEmail::hash('sara@x.ir'));
    }

    public function test_hidden_sections_are_not_rendered(): void
    {
        $u = $this->user();
        $this->actingAs($u)->patchJson('/account/appearance', ['hidden_sections' => ['links']])->assertOk()
            ->assertJsonPath('profile.hidden_sections.0', 'links');
        $this->get('/u/sara')->assertOk()->assertSee('BIO_TEXT')->assertDontSee('LINK_LABEL');
    }

    public function test_links_validation_rejects_unsafe_urls(): void
    {
        $this->actingAs($this->user());
        foreach (['javascript:alert(1)', 'data:text/html,x', 'ftp://x.ir', 'not a url'] as $bad) {
            $this->patchJson('/account/profile', ['links' => [['label' => 'x', 'url' => $bad]]])->assertJsonValidationErrors('links.0.url');
        }
        $this->patchJson('/account/profile', ['links' => [['label' => '', 'url' => 'https://a.ir']]])->assertJsonValidationErrors('links.0.label');
        $this->patchJson('/account/profile', ['links' => array_fill(0, 11, ['label' => 'x', 'url' => 'https://a.ir'])])->assertJsonValidationErrors('links');
    }

    public function test_links_headline_location_and_new_socials_render_escaped(): void
    {
        $u = $this->user();
        $this->actingAs($u)->patchJson('/account/profile', [
            'headline' => 'مهندس <script>1</script>', 'location' => 'Tehran',
            'links' => [['label' => 'My <b>blog</b>', 'url' => 'https://blog.sara.ir/x?a=1&b=2']],
            'socials' => ['youtube' => 'sara', 'eitaa' => 'sara_e'],
        ])->assertOk();

        $html = $this->get('/u/sara')->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>1</script>', $html);
        $this->assertStringNotContainsString('<b>blog</b>', $html);
        $this->assertStringContainsString('Tehran', $html);
        $this->assertStringContainsString('youtube.com/@sara', $html);
        $this->assertStringContainsString('eitaa.com/sara_e', $html);
        $this->assertStringContainsString('rel="nofollow noopener ugc"', $html);
    }

    public function test_verified_badge_only_for_verified_owner(): void
    {
        $u = $this->user();
        $this->get('/u/sara')->assertDontSee('aria-label="verified"', false);
        $u->emails()->create(['email' => 'sara@x.ir', 'verified_at' => now()]);
        $this->get('/u/sara')->assertSee('aria-label="verified"', false);
    }

    public function test_brand_config_colors_are_rendered_with_winning_specificity(): void
    {
        config(['brand.colors.brand' => '#112233', 'brand.colors.ink' => 'red;}</style><script>x</script>', 'brand.colors.canvas' => null]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('html:root{ --brand:#112233 }', str_replace("\n", ' ', $html));
        $this->assertStringNotContainsString('<script>x</script>', $html); // invalid color value is dropped
    }

    public function test_no_color_overrides_by_default(): void
    {
        $this->assertStringNotContainsString('html:root', $this->get('/')->getContent());
    }
}
