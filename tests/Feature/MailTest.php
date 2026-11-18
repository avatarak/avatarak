<?php

namespace Tests\Feature;

use App\Mail\LoginCode;
use App\Mail\VerifyEmailAddress;
use App\Models\UserEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactional_mail_is_queued_so_a_slow_smtp_server_never_blocks_a_request(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new LoginCode('123456'));
        $this->assertInstanceOf(ShouldQueue::class, new VerifyEmailAddress(new UserEmail(['email' => 'a@b.ir'])));
    }

    public function test_the_login_code_email_is_branded_and_localised(): void
    {
        app()->setLocale('fa');
        $fa = (new LoginCode('482913'))->render();
        $this->assertStringContainsString('dir="rtl"', $fa);
        $this->assertStringContainsString('482913', $fa);
        $this->assertStringContainsString('کد ورود شما', $fa);
        $this->assertStringContainsString(config('brand.name'), $fa);

        app()->setLocale('en');
        $en = (new LoginCode('482913'))->render();
        $this->assertStringContainsString('dir="ltr"', $en);
        $this->assertStringContainsString('Your sign-in code', $en);
    }

    public function test_the_verification_email_has_a_button_and_the_plain_link(): void
    {
        $e = new UserEmail(['email' => 'a@b.ir', 'verification_token' => 'tok123']);
        $html = (new VerifyEmailAddress($e))->render();
        $url = route('email.verify', 'tok123');
        $this->assertSame(2, substr_count($html, $url), 'once as the button, once as readable text');
        $this->assertStringContainsString('<a href="'.$url.'"', $html);
    }

    public function test_the_queued_mail_keeps_the_language_of_the_request(): void
    {
        Mail::fake();
        $this->postJson('/register', ['name' => 'A', 'email' => 'a@b.ir', 'password' => 'secret-pass-1', 'username' => 'abc'], ['X-Locale' => 'en'])->assertCreated();
        Mail::assertQueued(VerifyEmailAddress::class, fn ($m) => $m->locale === 'en');
    }

    public function test_the_test_command_refuses_bad_addresses_and_sends_through_the_configured_mailer(): void
    {
        $this->artisan('mail:test', ['email' => 'nope'])->assertFailed();
        $this->artisan('mail:test', ['email' => 'me@example.com', '--locale' => 'en'])->assertSuccessful();
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());
    }
}
