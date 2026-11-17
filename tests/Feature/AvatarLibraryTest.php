<?php

namespace Tests\Feature;

use App\Models\Avatar;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'ali'): User
    {
        Storage::fake('local');
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => $name]);

        return $u;
    }

    private function up(int $w = 300, int $h = 200): UploadedFile
    {
        return UploadedFile::fake()->image('a.jpg', $w, $h);
    }

    public function test_upload_adds_to_library_and_activates(): void
    {
        $u = $this->user();
        $id1 = $this->actingAs($u)->postJson('/account/avatars', ['avatar' => $this->up()])->assertCreated()->json('id');
        $id2 = $this->postJson('/account/avatars', ['avatar' => $this->up()])->assertCreated()->json('id');

        $list = $this->getJson('/account')->json('avatars');
        $this->assertCount(2, $list);
        $this->assertSame($id2, collect($list)->firstWhere('active', true)['id']);
        $this->assertSame($id2, $u->profile->fresh()->avatar_id);
        $this->assertNotSame($id1, $id2);
    }

    public function test_activate_switches_public_avatar_and_busts_cache(): void
    {
        $u = $this->user();
        $u->emails()->create(['email' => 'ali@x.ir', 'verified_at' => now()]);
        $this->actingAs($u);
        $a = $this->postJson('/account/avatars', ['avatar' => $this->up(200, 200)])->json('id');
        $this->postJson('/account/avatars', ['avatar' => $this->up(300, 300)])->json('id');
        $hash = UserEmail::hash('ali@x.ir');

        $etagBefore = $this->get("/avatar/$hash")->assertHeader('Content-Type', 'image/jpeg')->headers->get('ETag');
        $this->postJson("/account/avatars/$a/activate")->assertOk();
        $etagAfter = $this->get("/avatar/$hash")->headers->get('ETag');
        $this->assertNotSame($etagBefore, $etagAfter);
    }

    public function test_delete_active_deactivates_and_removes_file(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        $id = $this->postJson('/account/avatars', ['avatar' => $this->up()])->json('id');
        $path = Avatar::find($id)->path;
        Storage::disk('local')->assertExists($path);

        $this->deleteJson("/account/avatars/$id")->assertOk()->assertJsonPath('profile.has_avatar', false);
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($u->profile->fresh()->avatar_id);
        $this->assertSame(0, Avatar::count());
    }

    public function test_delete_inactive_keeps_active(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        $old = $this->postJson('/account/avatars', ['avatar' => $this->up()])->json('id');
        $new = $this->postJson('/account/avatars', ['avatar' => $this->up()])->json('id');
        $this->deleteJson("/account/avatars/$old")->assertOk();
        $this->assertSame($new, $u->profile->fresh()->avatar_id);
        $this->assertTrue($u->profile->fresh()->avatar_path !== null);
    }

    public function test_use_none_keeps_library(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        $this->postJson('/account/avatars', ['avatar' => $this->up()]);
        $this->deleteJson('/account/avatar')->assertOk();
        $this->assertSame(1, $u->avatars()->count());
        $this->assertNull($u->profile->fresh()->avatar_path);
    }

    public function test_limit_of_ten(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/account/avatars', ['avatar' => $this->up(80, 80)])->assertCreated();
        }
        $this->postJson('/account/avatars', ['avatar' => $this->up(80, 80)])->assertStatus(422)
            ->assertJsonPath('message', 'حداکثر ۱۰ عکس می‌توانید داشته باشید. ابتدا یکی را حذف کنید.');
    }

    public function test_cannot_touch_other_users_avatars(): void
    {
        $a = $this->user('aaa');
        $this->actingAs($a);
        $id = $this->postJson('/account/avatars', ['avatar' => $this->up()])->json('id');

        $b = User::factory()->create();
        Profile::create(['user_id' => $b->id, 'username' => 'bbb']);
        $this->actingAs($b);
        $this->getJson("/account/avatars/$id/image")->assertNotFound();
        $this->postJson("/account/avatars/$id/activate")->assertNotFound();
        $this->deleteJson("/account/avatars/$id")->assertNotFound();
        $this->assertSame(1, Avatar::count());
    }

    public function test_library_image_is_private_and_a_jpeg(): void
    {
        $u = $this->user();
        $id = $this->actingAs($u)->postJson('/account/avatars', ['avatar' => $this->up(500, 300)])->json('id');
        $r = $this->get("/account/avatars/$id/image")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('private', $r->headers->get('Cache-Control'));
        $this->assertSame([300, 300], array_slice(getimagesizefromstring($r->getContent()), 0, 2));
        auth()->logout();
        $this->getJson("/account/avatars/$id/image")->assertUnauthorized();
    }

    public function test_upload_requires_auth_and_rejects_non_images(): void
    {
        $this->postJson('/account/avatars', [])->assertUnauthorized();
        $this->actingAs($this->user());
        $this->postJson('/account/avatars', [])->assertJsonValidationErrors('avatar');
        $this->postJson('/account/avatars', ['avatar' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')])->assertStatus(422);
        $this->postJson('/account/avatars', ['avatar' => UploadedFile::fake()->create('x.php', 5, 'application/x-php')])->assertStatus(422);
    }
}
