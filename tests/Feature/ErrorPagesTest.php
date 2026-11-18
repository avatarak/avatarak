<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_unknown_page_shows_the_branded_404_in_both_languages(): void
    {
        $this->withUnencryptedCookie('locale', 'fa')->get('/no/such/page')->assertNotFound()->assertSee('این صفحه پیدا نشد')->assertSee('<span class="code">404</span>', false);
        $this->withUnencryptedCookie('locale', 'en')->get('/no/such/page')->assertNotFound()->assertSee('Page not found')->assertSee('dir="ltr"', false);
        $this->withUnencryptedCookie('locale', 'fa')->get('/nobody-here-xyz')->assertNotFound()->assertSee('404', false);
    }

    public function test_api_and_json_requests_keep_json_errors(): void
    {
        $this->getJson('/no/such/page')->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/api/v1/nothing')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_403_419_429_500_503_pages_render(): void
    {
        Route::get('/_t/{c}', fn (int $c) => abort($c))->where('c', '[0-9]+');
        foreach ([403 => 'اجازه‌ی دسترسی', 419 => 'نشست شما منقضی', 429 => 'درخواست‌ها زیاد', 500 => 'مشکلی از سمت ما', 503 => 'در حال به‌روزرسانی'] as $code => $text) {
            $this->withUnencryptedCookie('locale', 'fa')->get("/_t/$code")->assertStatus($code)->assertSee($text)->assertSee(">$code</span>", false)->assertSee('noindex', false);
        }
    }

    public function test_pages_do_not_depend_on_the_vite_build_or_leak_details(): void
    {
        Route::get('/_boom', fn () => throw new \RuntimeException('secret db password'));
        config(['app.debug' => false]);
        $r = $this->get('/_boom')->assertStatus(500);
        $r->assertDontSee('secret db password')->assertDontSee('RuntimeException')->assertDontSee('/build/assets', false);
    }
}
