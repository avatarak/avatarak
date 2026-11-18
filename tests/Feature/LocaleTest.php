<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    private array $bad = ['name' => '', 'email' => 'nope', 'password' => 'x', 'username' => 'Admin'];

    public function test_validation_messages_are_persian_by_default(): void
    {
        $res = $this->postJson('/register', $this->bad)->assertStatus(422);
        $this->assertSame('فیلد نام الزامی است.', $res->json('errors.name.0'));
        $this->assertSame('فیلد ایمیل باید یک ایمیل معتبر باشد.', $res->json('errors.email.0'));
        $this->assertStringContainsString('نام کاربری باید', $res->json('errors.username.0'));
        $this->assertStringContainsString('گذرواژه', $res->json('errors.password.0'));
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{4,} field/', $res->getContent());
    }

    public function test_x_locale_en_switches_to_english(): void
    {
        $res = $this->postJson('/register', $this->bad, ['X-Locale' => 'en'])->assertStatus(422);
        $this->assertSame('The name field is required.', $res->json('errors.name.0'));
        $this->assertStringContainsString('Username must be', $res->json('errors.username.0'));
    }

    public function test_unsupported_locale_falls_back_to_default(): void
    {
        $res = $this->postJson('/register', $this->bad, ['X-Locale' => '../../etc'])->assertStatus(422);
        $this->assertSame('فیلد نام الزامی است.', $res->json('errors.name.0'));
    }

    public function test_domain_rule_and_login_messages_localized(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/account/sites', ['name' => 'x', 'domain' => 'bad domain'])
            ->assertJsonPath('errors.domain.0', 'قالب فیلد دامنه نامعتبر است.');
        $this->postJson('/logout');
        $this->postJson('/login', ['email' => $u->email, 'password' => 'wrong'])->assertJsonPath('message', 'ایمیل یا گذرواژه نادرست است.');
        $this->postJson('/login', ['email' => $u->email, 'password' => 'wrong'], ['X-Locale' => 'en'])->assertJsonPath('message', 'Incorrect email or password.');
    }

    public function test_avatar_upload_errors_are_persian(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $r = $this->postJson('/account/avatar', ['avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]);
        $this->assertSame('فرمت عکس باید JPG، PNG یا WebP باشد.', $r->json('errors.avatar.0'));
    }

    public function test_guest_401_is_localized(): void
    {
        $this->getJson('/account')->assertUnauthorized()->assertJsonPath('message', 'ابتدا وارد شوید.');
        $this->getJson('/account', ['X-Locale' => 'en'])->assertJsonPath('message', 'Please log in first.');
    }

    public function test_api_v1_stays_english_for_integrators(): void
    {
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => ['x']], ['Authorization' => 'Bearer bad'])
            ->assertUnauthorized()->assertJsonPath('message', 'Invalid or missing API key.');
    }

    private function profile(): User
    {
        $u = User::factory()->create();
        \App\Models\Profile::create(['user_id' => $u->id, 'username' => 'sara', 'languages' => 'both', 'name_fa' => 'سارا', 'bio' => 'x', 'website' => 'https://s.ir']);

        return $u;
    }

    public function test_public_profile_follows_the_locale_cookie(): void
    {
        $this->profile();

        $en = $this->withUnencryptedCookie('locale', 'en')->get('/sara')->assertOk();
        $en->assertSee('Share')->assertSee('Advanced details')->assertSee('lang="en"', false)->assertSee('dir="ltr"', false);
        $en->assertDontSee('اشتراک‌گذاری');

        $fa = $this->withUnencryptedCookie('locale', 'fa')->get('/sara')->assertOk();
        $fa->assertSee('اشتراک‌گذاری')->assertSee('جزئیات بیشتر')->assertSee('lang="fa"', false)->assertSee('dir="rtl"', false);
    }

    public function test_owner_edit_button_is_translated(): void
    {
        $u = $this->profile();
        $this->actingAs($u)->withUnencryptedCookie('locale', 'en')->get('/sara')->assertSee('Edit profile')->assertDontSee('ویرایش پروفایل');
        $this->actingAs($u)->withUnencryptedCookie('locale', 'fa')->get('/sara')->assertSee('ویرایش پروفایل')->assertDontSee('Edit profile');
    }

    public function test_cookie_falls_back_to_brand_default_and_header_wins_over_cookie(): void
    {
        $this->profile();
        $this->withUnencryptedCookie('locale', 'klingon')->get('/sara')->assertSee('اشتراک‌گذاری');         // invalid -> default (fa)
        $this->withUnencryptedCookie('locale', '../../x')->get('/sara')->assertSee('اشتراک‌گذاری');
        $this->get('/sara')->assertSee('اشتراک‌گذاری');                                                      // no cookie -> default

        // a bilingual page with no visitor choice (e.g. a crawler) speaks the owner's primary language
        \App\Models\Profile::where('username', 'sara')->update(['primary_lang' => 'en']);
        $this->get('/sara')->assertSee('Share');

        // the SPA header beats the cookie for API calls
        $this->withUnencryptedCookie('locale', 'fa')->postJson('/register', [], ['X-Locale' => 'en'])
            ->assertJsonPath('errors.name.0', 'The name field is required.');
    }

    public function test_card_embed_follows_locale(): void
    {
        $this->profile();
        $this->withUnencryptedCookie('locale', 'en')->get('/sara.card')->assertSee('View profile');
        $this->withUnencryptedCookie('locale', 'fa')->get('/sara.card')->assertSee('مشاهده‌ی پروفایل');
    }

    public function test_cookie_is_not_encrypted_by_laravel(): void
    {
        $this->profile();
        // a plain cookie, exactly as JavaScript writes it, must work through the real cookie middleware
        $this->call('GET', '/sara', [], ['locale' => 'en'])->assertSee('Share');
    }

    public function test_brand_name_follows_locale_on_the_public_page(): void
    {
        $this->profile();
        config(['brand.name' => 'نام فارسی', 'brand.name_en' => 'English Name']);
        $this->withUnencryptedCookie('locale', 'en')->get('/sara')->assertSee('— English Name', false)->assertDontSee('نام فارسی');
        $this->withUnencryptedCookie('locale', 'fa')->get('/sara')->assertSee('— نام فارسی', false);
    }

    public function test_default_site_name_is_avatarak_in_both_languages(): void
    {
        $this->profile();
        $this->withUnencryptedCookie('locale', 'fa')->get('/sara')->assertSee('— Avatarak.ir', false);
        $this->withUnencryptedCookie('locale', 'en')->get('/sara')->assertSee('— Avatarak.ir', false);
        $this->assertSame('Avatarak.ir', config('brand.name'));
        $this->assertSame('Avatarak.ir', config('brand.name_en'));
        $this->get('/')->assertSee('Avatarak.ir', false);                      // SPA shell: <title> and window.__BRAND__
    }
}
