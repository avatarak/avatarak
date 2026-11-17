<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $profile = [], array $email = []): UserEmail
    {
        Storage::fake('local');
        $img = imagecreatetruecolor(300, 200);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        ob_start(); imagepng($img); Storage::disk('local')->put('avatars/a.png', ob_get_clean());

        $user = User::factory()->create();
        Profile::create($profile + [
            'user_id' => $user->id, 'username' => 'ali',
            'avatar_path' => 'avatars/a.png', 'avatar_version' => 'v1',
        ]);

        return $user->emails()->create($email + ['email' => 'ali@example.com', 'verified_at' => now(), 'is_primary' => true]);
    }

    private function url(string $email, string $q = ''): string
    {
        return '/avatar/'.UserEmail::hash($email).$q;
    }

    public function test_serves_resized_square_jpeg(): void
    {
        $this->makeUser();
        $res = $this->get($this->url('ali@example.com', '?s=100'));

        $res->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('public', $res->headers->get('Cache-Control'));
        $this->assertSame([100, 100], array_slice(getimagesizefromstring($res->getContent()), 0, 2));
    }

    public function test_size_is_clamped(): void
    {
        $this->makeUser();
        $res = $this->get($this->url('ali@example.com', '?s=99999'));
        $this->assertSame(512, getimagesizefromstring($res->getContent())[0]);
    }

    public function test_serves_webp_when_accepted(): void
    {
        $this->makeUser();
        $this->get($this->url('ali@example.com', '?s=64'), ['Accept' => 'image/webp,*/*'])
            ->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_etag_returns_304(): void
    {
        $this->makeUser();
        $etag = $this->get($this->url('ali@example.com'))->headers->get('ETag');
        $this->get($this->url('ali@example.com'), ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_unknown_hash_gets_default_svg(): void
    {
        $this->get($this->url('nobody@example.com'))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_default_404_and_blank_options(): void
    {
        $this->get($this->url('nobody@example.com', '?d=404'))->assertNotFound();
        $this->get($this->url('nobody@example.com', '?d=blank'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_unverified_email_is_not_revealed(): void
    {
        $this->makeUser(email: ['verified_at' => null]);
        $this->get($this->url('ali@example.com'))->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_private_email_is_not_revealed(): void
    {
        $this->makeUser(email: ['is_public' => false]);
        $this->get($this->url('ali@example.com'))->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_hidden_avatar_is_not_revealed(): void
    {
        $this->makeUser(['show_avatar' => false]);
        $this->get($this->url('ali@example.com'))->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_hidden_and_unknown_are_indistinguishable(): void
    {
        $this->makeUser(['show_avatar' => false]);
        $a = $this->get($this->url('ali@example.com', '?s=80'));
        $b = $this->get($this->url('nobody@example.com', '?s=80'));
        $this->assertSame($a->getContent(), $b->getContent());
        $this->assertSame($a->headers->get('ETag'), $b->headers->get('ETag'));
    }

    public function test_invalid_hash_is_404(): void
    {
        $this->get('/avatar/not-a-hash')->assertNotFound();
    }

    public function test_no_session_cookie_is_set(): void
    {
        $this->makeUser();
        $this->get($this->url('ali@example.com'))->assertCookieMissing('laravel-session');
    }
}
