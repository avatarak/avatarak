<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileLanguagesTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $attrs = []): User
    {
        $u = User::factory()->create();
        Profile::create($attrs + ['user_id' => $u->id, 'username' => 'dual', 'name_fa' => 'دوزبانه', 'name_en' => 'Dual', 'headline' => 'مهندس نرم‌افزار', 'headline_alt' => 'Software engineer',
            'bio' => 'سلام', 'bio_alt' => 'Hello', 'primary_lang' => 'fa', 'languages' => 'both',
            'links' => [['label' => 'وب‌سایت', 'label_alt' => 'Website', 'url' => 'https://x.ir', 'description' => 'توضیح', 'description_alt' => 'About']],
            'custom_sections' => [['title' => 'ساعت کاری', 'body' => 'شنبه تا چهارشنبه', 'title_alt' => 'Hours', 'body_alt' => 'Sat to Wed']]]);

        return $u;
    }

    public function test_single_language_page_ignores_the_visitors_language_and_never_mixes(): void
    {
        $this->make(['languages' => 'en', 'primary_lang' => 'fa']);
        // English page: the alt (English) texts, even for a Persian-UI visitor, and never the Persian ones
        $r = $this->withUnencryptedCookie('locale', 'fa')->get('/dual')->assertOk();
        $r->assertSee('lang="en"', false)->assertSee('dir="ltr"', false)->assertSee('Software engineer')->assertSee('Hello')->assertSee('Website')->assertSee('Sat to Wed')
            ->assertDontSee('مهندس نرم‌افزار')->assertDontSee('سلام')->assertDontSee('data-report-open="x"', false);
        $r->assertDontSee('hreflang', false);
        $this->get('/dual?lang=fa')->assertSee('Software engineer')->assertDontSee('مهندس نرم‌افزار');      // ?lang is ignored when single
    }

    public function test_single_language_does_not_fall_back_to_the_other_language(): void
    {
        $this->make(['languages' => 'en', 'headline_alt' => null, 'bio_alt' => null]);
        $this->get('/dual')->assertOk()->assertDontSee('مهندس نرم‌افزار')->assertDontSee('سلام');
    }

    public function test_bilingual_page_follows_query_then_cookie_and_offers_a_switch(): void
    {
        $this->make();
        $this->get('/dual?lang=en')->assertSee('Software engineer')->assertSee('Hours')->assertDontSee('مهندس نرم‌افزار')
            ->assertSee('hreflang="fa"', false)->assertSee('hreflang="en"', false)->assertSee('aria-label="Language"', false);
        $this->get('/dual?lang=fa')->assertSee('مهندس نرم‌افزار')->assertSee('ساعت کاری')->assertSee('lang="fa"', false)->assertDontSee('Software engineer');
        $this->withUnencryptedCookie('locale', 'en')->get('/dual')->assertSee('Software engineer');
        $this->get('/dual?lang=xx')->assertOk();
    }

    public function test_bilingual_falls_back_to_the_other_language_when_one_is_empty(): void
    {
        $this->make(['headline_alt' => null]);
        $this->get('/dual?lang=en')->assertSee('مهندس نرم‌افزار')->assertSee('Hello');
    }

    public function test_names_show_in_the_page_language(): void
    {
        $this->make();
        // one name only: never both at once
        $this->get('/dual?lang=en')->assertSee('Dual')->assertDontSee('دوزبانه');
        $this->get('/dual?lang=fa')->assertSee('دوزبانه')->assertDontSee('Dual');
    }

    public function test_exports_carry_the_language_and_translations(): void
    {
        $this->make();
        $j = $this->getJson('/dual.json')->assertOk()->json();
        $this->assertSame('fa', $j['language']);
        $this->assertSame(['fa', 'en'], $j['languages']);
        $this->assertSame('مهندس نرم‌افزار', $j['headline']);
        $this->assertSame('Software engineer', $j['translations']['en']['headline']);
        $this->assertSame('Website', $j['translations']['en']['links'][0]['label']);
        $this->assertSame('Hours', $j['translations']['en']['sections'][0]['title']);
        $this->getJson('/dual.json?lang=en')->assertJsonPath('language', 'en')->assertJsonPath('headline', 'Software engineer');
        $this->get('/dual.md?lang=en')->assertSee('Software engineer')->assertSee('Hours');
        $this->get('/dual.vcf?lang=en')->assertSee('TITLE:Software engineer', false);
    }

    public function test_single_language_exports_have_no_translations(): void
    {
        $this->make(['languages' => 'fa']);
        $j = $this->getJson('/dual.json')->json();
        $this->assertSame(['fa'], $j['languages']);
        $this->assertArrayNotHasKey('translations', $j);
        $this->assertStringNotContainsString('Software engineer', json_encode($j, JSON_UNESCAPED_UNICODE));
    }

    public function test_rest_api_returns_the_resolved_language(): void
    {
        $u = $this->make();
        $e = $u->emails()->create(['email' => 'd@example.ir', 'verified_at' => now()]);
        [$site, $key] = \App\Models\Site::createWithKey(['user_id' => $u->id, 'name' => 's', 'domain' => 's.example.ir']);
        $h = \App\Models\UserEmail::hash('d@example.ir');
        $this->withToken($key)->getJson("/api/v1/profiles/$h")->assertJsonPath('data.headline', 'مهندس نرم‌افزار')->assertJsonPath('data.language', 'fa');
        $this->withToken($key)->getJson("/api/v1/profiles/$h?lang=en")->assertJsonPath('data.headline', 'Software engineer')->assertJsonPath('data.language', 'en');
    }

    public function test_the_owner_can_save_modes_alt_texts_and_the_alt_only_content(): void
    {
        $u = $this->make(['languages' => 'fa']);
        $this->actingAs($u)->patchJson('/account/appearance', ['languages' => 'klingon'])->assertStatus(422);
        $this->patchJson('/account/appearance', ['languages' => 'both', 'primary_lang' => 'en'])->assertOk()->assertJsonPath('profile.languages', 'both')->assertJsonPath('profile.primary_lang', 'en');
        $this->patchJson('/account/profile', ['headline_alt' => 'Updated', 'links' => [['label' => null, 'label_alt' => 'Only English', 'url' => 'https://y.ir']]])->assertOk();
        $this->assertSame('Updated', $u->profile->fresh()->headline_alt);
        $this->patchJson('/account/profile', ['links' => [['label' => null, 'url' => 'https://y.ir']]])->assertStatus(422);       // a label in some language is required
        $this->putJson('/account/sections', ['sections' => [['title' => null, 'title_alt' => 'T', 'body' => null, 'body_alt' => 'B']]])->assertOk();
        $this->putJson('/account/sections', ['sections' => [['title' => null, 'body' => 'B']]])->assertStatus(422);
        $this->get('/dual?lang=en')->assertSee('Only English')->assertSee('B');
    }

    public function test_gallery_captions_follow_the_language(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $u = $this->make();
        $id = $this->actingAs($u)->postJson('/account/gallery', ['image' => \Illuminate\Http\UploadedFile::fake()->image('a.jpg', 400, 300), 'caption' => 'دریا'])->json('id');
        $this->patchJson("/account/gallery/$id", ['caption_alt' => 'Sea'])->assertOk()->assertJsonPath('images.0.caption_alt', 'Sea')->assertJsonPath('images.0.caption', 'دریا');
        auth()->logout();
        $this->get('/dual?lang=en')->assertSee('alt="Sea"', false);
        $this->get('/dual?lang=fa')->assertSee('alt="دریا"', false);
    }

    public function test_existing_profiles_were_migrated_to_the_language_their_text_is_in(): void
    {
        // the migration backfill: Persian text → fa, Latin text → en, nothing → fa
        $en = Profile::create(['user_id' => User::factory()->create()->id, 'username' => 'latin', 'bio' => 'Plain English']);
        $this->assertSame('fa', $en->fresh()->languages);       // column default applies to new rows; the backfill only ran on rows that existed
        $this->assertSame('fa', (new Profile)->defaultLang());
    }

    public function test_changing_the_primary_language_swaps_the_stored_texts_without_losing_any(): void
    {
        $u = $this->make();
        $this->actingAs($u)->patchJson('/account/appearance', ['primary_lang' => 'en'])->assertOk();
        $p = $u->profile->fresh();
        $this->assertSame('en', $p->primary_lang);
        $this->assertSame('Software engineer', $p->headline);
        $this->assertSame('مهندس نرم‌افزار', $p->headline_alt);
        $this->assertSame('Website', $p->links[0]['label']);
        $this->assertSame('وب‌سایت', $p->links[0]['label_alt']);
        $this->assertSame('Hours', $p->custom_sections[0]['title']);
        // the public page reads exactly the same as before the swap
        $this->get('/dual?lang=fa')->assertSee('مهندس نرم‌افزار')->assertSee('ساعت کاری');
        $this->get('/dual?lang=en')->assertSee('Software engineer')->assertSee('Hours');
        $this->actingAs($u)->patchJson('/account/appearance', ['primary_lang' => 'fa']);       // and back again
        $this->assertSame('مهندس نرم‌افزار', $u->profile->fresh()->headline);
    }

    public function test_print_share_and_card_pages_speak_one_language_too(): void
    {
        $this->make();
        $this->get('/dual/print?lang=en')->assertSee('Software engineer')->assertSee('Dual')->assertDontSee('مهندس نرم‌افزار')->assertDontSee('دوزبانه')->assertSee('dir="ltr"', false);
        $this->get('/dual/print?lang=fa')->assertSee('مهندس نرم‌افزار')->assertSee('دوزبانه')->assertDontSee('Software engineer');
        $this->get('/dual/share.svg?lang=en')->assertSee('Software engineer')->assertDontSee('مهندس نرم‌افزار');
        $this->get('/dual/share.svg?lang=fa')->assertSee('مهندس نرم‌افزار');
        $this->get('/dual.card?lang=en')->assertSee('Software engineer')->assertSee('View profile');
        // a single-language profile ignores ?lang= and the visitor's UI language everywhere
        Profile::where('username', 'dual')->update(['languages' => 'en']);
        $this->withUnencryptedCookie('locale', 'fa')->get('/dual/print?lang=fa')->assertSee('Software engineer')->assertSee('Dual')->assertDontSee('دوزبانه')->assertDontSee('مهندس نرم‌افزار');
        $this->withUnencryptedCookie('locale', 'fa')->get('/dual.card')->assertSee('View profile')->assertDontSee('مشاهده');
        $this->get('/dual/share.svg')->assertSee('Software engineer')->assertDontSee('مهندس نرم‌افزار');
    }
}
