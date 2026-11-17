<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\VerifiedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifiedAccountsDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => 'javid', 'name_fa' => 'جاوید']);
        VerifiedAccount::create(['user_id' => $u->id, 'network' => 'website', 'identifier' => 'javid.ir', 'method' => 'dns', 'verified_at' => now()]);
        VerifiedAccount::create(['user_id' => $u->id, 'network' => 'telegram', 'identifier' => 'javid_t', 'external_id' => '1', 'method' => 'telegram_bot', 'verified_at' => now()]);

        return $u;
    }

    public function test_public_page_lists_only_verified_accounts_with_badge(): void
    {
        $this->user();
        $html = $this->get('/javid')->assertOk()->assertSee('حساب‌های تأییدشده')->getContent();
        $this->assertStringContainsString('javid.ir', $html);
        $this->assertStringContainsString('t.me/javid_t', $html);
        $this->assertStringContainsString('rel="me noopener"', $html);
        $this->assertSame(2, substr_count($html, 'aria-label="verified"') - 0 >= 2 ? 2 : 0);   // at least one badge per row
    }

    public function test_unverified_social_handles_are_not_listed_as_verified(): void
    {
        $u = $this->user();
        $u->profile->update(['socials' => ['github' => 'someone', 'telegram' => 'not_mine']]);
        $card = $this->get('/javid')->assertOk()->getContent();
        $section = substr($card, strpos($card, 'حساب‌های تأییدشده'));
        $this->assertStringNotContainsString('github.com/someone', substr($section, 0, strpos($section, 'data-adv-open')));
        $this->assertStringNotContainsString('t.me/not_mine', substr($section, 0, strpos($section, 'data-adv-open')));
    }

    public function test_section_can_be_hidden_and_page_has_no_card_without_accounts(): void
    {
        $u = $this->user();
        $u->profile->update(['hidden_sections' => ['verified']]);
        $this->get('/javid')->assertOk()->assertDontSee('حساب‌های تأییدشده</h2>', false);

        $u->profile->update(['hidden_sections' => []]);
        $u->verifiedAccounts()->delete();
        $this->get('/javid')->assertOk()->assertDontSee('حساب‌های تأییدشده</h2>', false);
    }

    public function test_profile_that_is_hidden_does_not_leak_verified_accounts(): void
    {
        $u = $this->user();
        $u->profile->update(['show_profile' => false]);
        $this->get('/javid')->assertNotFound();
        $this->get('/javid.json')->assertNotFound();
    }

    public function test_exports_include_verified_accounts_without_internal_ids(): void
    {
        $this->user();
        $d = $this->get('/javid.json')->assertOk()->json('verified_accounts');
        $this->assertSame([
            ['network' => 'website', 'identifier' => 'javid.ir', 'url' => 'https://javid.ir'],
            ['network' => 'telegram', 'identifier' => 'javid_t', 'url' => 'https://t.me/javid_t'],
        ], $d);
        $this->assertStringContainsString('## Verified accounts', $this->get('/javid.md')->getContent());
        $this->assertStringNotContainsString('external_id', $this->get('/javid.json')->getContent());
        $this->assertStringNotContainsString('777', $this->get('/javid.json')->getContent());
    }

    public function test_section_toggle_validation_accepts_verified(): void
    {
        $u = $this->user();
        $this->actingAs($u)->patchJson('/account/appearance', ['hidden_sections' => ['verified', 'links']])->assertOk();
        $this->patchJson('/account/appearance', ['hidden_sections' => ['verified', 'evil']])->assertStatus(422);
    }
}
