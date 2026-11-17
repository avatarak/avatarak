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
        Profile::create(['user_id' => $u->id, 'username' => 'sara', 'name_fa' => 'سارا', 'bio' => 'BIO_TEXT', 'website' => 'https://sara.ir', 'socials' => ['github' => 'sara']]);

        return $u;
    }

    public function test_accent_and_order_are_saved_and_rendered(): void
    {
        $u = $this->user();
        $this->actingAs($u)->patchJson('/account/appearance', ['accent_color' => '#0F9D7A', 'section_order' => ['links', 'website', 'bio']])
            ->assertOk()->assertJsonPath('profile.accent_color', '#0F9D7A');

        $html = $this->get('/u/sara')->assertOk()->assertSee('--brand: #0F9D7A', false)->getContent();
        $this->assertLessThan(strpos($html, 'leading-8">BIO_TEXT'), strpos($html, 'github.com/sara'), 'links must precede bio');
    }

    public function test_appearance_validation_blocks_css_injection(): void
    {
        $this->actingAs($this->user());
        foreach (['red', '#FFF', '#12345G', 'url(javascript:1)', '#FFFFFF;}</style><script>'] as $bad) {
            $this->patchJson('/account/appearance', ['accent_color' => $bad])->assertJsonValidationErrors('accent_color');
        }
        $this->patchJson('/account/appearance', ['section_order' => ['bio', 'bio']])->assertStatus(422);
        $this->patchJson('/account/appearance', ['section_order' => ['bio', 'evil']])->assertStatus(422);
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
}
