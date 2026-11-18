<?php

namespace Tests\Feature;

use App\Models\GalleryImage;
use App\Models\Profile;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RichProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'rich', bool $pro = false): User
    {
        Storage::fake('local');
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => $name, 'name_en' => 'Rich']);
        if ($pro) {
            app(PlanService::class)->grant($u, 'pro', 30, 'admin');
        }

        return $u->fresh();
    }

    private function photo(string $name = 'p.jpg', int $w = 2400, int $h = 1200): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    public function test_upload_is_resized_and_limited_by_plan(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        $r = $this->postJson('/account/gallery', ['image' => $this->photo(), 'caption' => 'Sea'])->assertCreated();
        $img = GalleryImage::find($r->json('id'));
        $this->assertLessThanOrEqual(1600, $img->width);
        $this->assertSame(1600, $img->width);
        $this->assertSame(800, $img->height);
        Storage::disk('local')->assertExists($img->path);
        Storage::disk('local')->assertExists($img->thumbPath());
        $this->assertSame('image/jpeg', getimagesizefromstring(Storage::disk('local')->get($img->path))['mime']);

        $this->postJson('/account/gallery', ['image' => $this->photo()])->assertCreated();
        $this->postJson('/account/gallery', ['image' => $this->photo()])->assertCreated();
        $this->postJson('/account/gallery', ['image' => $this->photo()])->assertStatus(422);       // Free = 3
        $this->assertSame(3, $u->galleryImages()->count());
    }

    public function test_non_images_and_tiny_images_are_refused(): void
    {
        $u = $this->user();
        $this->actingAs($u)->postJson('/account/gallery', ['image' => UploadedFile::fake()->create('x.jpg', 10, 'text/plain')])->assertStatus(422);
        $this->postJson('/account/gallery', ['image' => $this->photo('s.jpg', 20, 20)])->assertStatus(422);
        $this->postJson('/account/gallery', [])->assertStatus(422);
    }

    public function test_public_photo_only_for_visible_profiles_and_shown_gallery(): void
    {
        $u = $this->user();
        $id = $this->actingAs($u)->postJson('/account/gallery', ['image' => $this->photo(), 'caption' => 'Hi'])->json('id');
        auth()->logout();

        $this->get("/rich/gallery/$id/thumb")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get("/rich/gallery/$id")->assertOk();
        $this->get("/rich/gallery/$id/huge")->assertNotFound();
        $this->get('/rich')->assertSee('data-gallery-item', false)->assertSee('Hi');

        $u->profile->update(['hidden_sections' => ['gallery']]);
        $this->get("/rich/gallery/$id/thumb")->assertNotFound();
        $this->get('/rich')->assertDontSee('data-gallery-item', false);
        $this->getJson('/rich.json')->assertJsonPath('gallery', []);

        $u->profile->update(['hidden_sections' => [], 'show_profile' => false]);
        $this->get("/rich/gallery/$id/thumb")->assertNotFound();

        $u->profile->update(['show_profile' => true]);
        $u->forceFill(['suspended_at' => now()])->save();
        $this->get("/rich/gallery/$id/thumb")->assertNotFound();
    }

    public function test_a_photo_is_only_served_under_its_owners_username(): void
    {
        $a = $this->user('alpha');
        $b = User::factory()->create();
        Profile::create(['user_id' => $b->id, 'username' => 'bravo']);
        $id = $this->actingAs($a)->postJson('/account/gallery', ['image' => $this->photo()])->json('id');
        auth()->logout();
        $this->get("/bravo/gallery/$id/thumb")->assertNotFound();
        $this->actingAs($b)->deleteJson("/account/gallery/$id")->assertNotFound();
        $this->getJson("/account/gallery/$id")->assertNotFound();
    }

    public function test_caption_reorder_and_delete(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        $a = $this->postJson('/account/gallery', ['image' => $this->photo()])->json('id');
        $b = $this->postJson('/account/gallery', ['image' => $this->photo()])->json('id');
        $this->patchJson("/account/gallery/$a", ['caption' => 'First'])->assertOk();
        $this->postJson('/account/gallery/order', ['order' => [$b, $a]])->assertOk()->assertJsonPath('images.0.id', $b);
        $this->postJson('/account/gallery/order', ['order' => [$b, 999999]])->assertStatus(422);

        $path = GalleryImage::find($a)->path;
        $this->deleteJson("/account/gallery/$a")->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertNull(GalleryImage::find($a));
    }

    public function test_deleting_the_account_removes_the_photos(): void
    {
        $u = $this->user();
        $id = $this->actingAs($u)->postJson('/account/gallery', ['image' => $this->photo()])->json('id');
        $path = GalleryImage::find($id)->path;
        app(\App\Services\UserDeletion::class)->delete($u);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, GalleryImage::count());
    }

    public function test_custom_sections_are_limited_by_plan_and_rendered_as_plain_text(): void
    {
        $u = $this->user();
        $this->actingAs($u)->putJson('/account/sections', ['sections' => [['title' => 'Hours <b>', 'body' => "9 to 5\n<script>alert(1)</script>"]]])->assertOk();
        $html = $this->get('/rich')->getContent();
        $this->assertStringContainsString('Hours &lt;b&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->getJson('/rich.json')->assertJsonPath('sections.0.title', 'Hours <b>');

        $three = array_fill(0, 3, ['title' => 'T', 'body' => 'B']);
        $this->actingAs($u)->putJson('/account/sections', ['sections' => $three])->assertStatus(422);      // Free = 2
        $this->putJson('/account/sections', ['sections' => [['title' => '', 'body' => 'x']]])->assertStatus(422);
        $this->putJson('/account/sections', ['sections' => [['title' => 'T', 'body' => str_repeat('a', 1501)]]])->assertStatus(422);
        $this->putJson('/account/sections', ['sections' => []])->assertOk();
        $this->assertSame([], $u->profile->fresh()->custom_sections);
    }

    public function test_pro_gets_more_room(): void
    {
        $u = $this->user('proo', true);
        $six = array_fill(0, 6, ['title' => 'T', 'body' => 'B']);
        $this->actingAs($u)->putJson('/account/sections', ['sections' => $six])->assertOk();
        $this->putJson('/account/sections', ['sections' => array_merge($six, [['title' => 'T', 'body' => 'B']])])->assertStatus(422);
        $this->getJson('/account')->assertJsonPath('profile.section_limit', 6);
    }

    public function test_theme_is_validated_and_applied(): void
    {
        $u = $this->user();
        $this->actingAs($u)->patchJson('/account/appearance', ['theme' => 'neon'])->assertStatus(422);
        $this->patchJson('/account/appearance', ['theme' => 'dark'])->assertOk()->assertJsonPath('profile.theme', 'dark');
        $this->get('/rich')->assertSee('data-theme="dark"', false);
        $u->profile->update(['theme' => 'gradient']);
        $this->get('/rich')->assertSee('data-theme="gradient"', false);
    }

    public function test_markdown_export_and_webhook_for_gallery_change(): void
    {
        $u = $this->user('mdx', true);
        $u->webhooks()->create(['url' => 'https://93.184.216.34/h', 'secret' => 's', 'events' => ['profile.updated']]);
        \Illuminate\Support\Facades\Bus::fake();
        $this->actingAs($u)->postJson('/account/gallery', ['image' => $this->photo(), 'caption' => 'Cap'])->assertCreated();
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\DeliverWebhook::class);
        $this->assertContains('gallery', \App\Models\WebhookDelivery::first()->payload['data']['changed']);
        $this->putJson('/account/sections', ['sections' => [['title' => 'About us', 'body' => 'Body text']]])->assertOk();
        auth()->logout();
        $this->get('/mdx.md')->assertSee('## About us', false)->assertSee('![Cap](', false);
    }
}
