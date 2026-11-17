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
}
