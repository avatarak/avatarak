<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\VerifiedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = $this->person('boss@example.com', 'boss', ['is_admin' => true]);
    }

    private function person(string $email, string $username, array $force = [], array $profile = []): User
    {
        $u = User::factory()->create(['email' => $email, 'name' => ucfirst($username)]);
        if ($force) {
            $u->forceFill($force)->save();
        }
        Profile::create($profile + ['user_id' => $u->id, 'username' => $username, 'name_en' => ucfirst($username)]);
        $u->emails()->create(['email' => $email, 'is_primary' => true, 'verified_at' => now()]);

        return $u;
    }

    private function asAdmin()
    {
        return $this->actingAs($this->admin);
    }

    private function photo(User $u): void
    {
        $this->actingAs($u)->postJson('/account/avatars', ['avatar' => UploadedFile::fake()->image('a.jpg', 400, 400)])->assertCreated();
        auth()->logout();
    }

    // ---------------------------------------------------------------- access control

    public static function endpoints(): array
    {
        return [['GET', '/admin/api/overview'], ['GET', '/admin/api/users'], ['GET', '/admin/api/users/1'], ['GET', '/admin/api/reports'], ['GET', '/admin/api/sites'],
            ['GET', '/admin/api/verified'], ['GET', '/admin/api/audit'], ['POST', '/admin/api/users/1/suspend'], ['POST', '/admin/api/users/1/unsuspend'],
            ['POST', '/admin/api/users/1/admin'], ['POST', '/admin/api/users/1/rename'], ['POST', '/admin/api/users/1/avatar/remove'], ['POST', '/admin/api/users/1/profile/clear'],
            ['DELETE', '/admin/api/users/1'], ['POST', '/admin/api/reports/1/resolve'], ['POST', '/admin/api/reports/1/reopen'], ['PATCH', '/admin/api/sites/1'],
            ['DELETE', '/admin/api/sites/1'], ['DELETE', '/admin/api/verified/1']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('endpoints')]
    public function test_every_admin_endpoint_rejects_guests_and_ordinary_users(string $method, string $url): void
    {
        $this->json($method, $url)->assertStatus(401);
        $normal = $this->person('n@example.com', 'normal');
        $this->actingAs($normal)->json($method, $url)->assertStatus(404);      // learns nothing about the admin area
        $this->assertSame(0, AuditLog::count());
    }

    public function test_the_admin_page_itself_is_just_the_app_shell(): void
    {
        $this->get('/admin')->assertOk();
        $this->assertStringNotContainsString('boss@example.com', $this->get('/admin')->getContent());
    }

    public function test_account_endpoint_tells_the_app_whether_you_are_an_admin(): void
    {
        $this->asAdmin()->getJson('/account')->assertJsonPath('is_admin', true);
        $this->actingAs($this->person('n@example.com', 'normal'))->getJson('/account')->assertJsonPath('is_admin', false);
    }

    public function test_is_admin_cannot_be_set_through_registration_or_profile_updates(): void
    {
        $this->postJson('/register', ['name' => 'x', 'email' => 'hack@example.com', 'password' => 'secret-pass-1', 'username' => 'hacker', 'is_admin' => true])->assertCreated();
        $this->assertFalse((bool) User::where('email', 'hack@example.com')->value('is_admin'));
        $this->patchJson('/account/profile', ['is_admin' => true, 'name_en' => 'x']);
        $this->assertFalse((bool) User::where('email', 'hack@example.com')->value('is_admin'));
    }

    // ---------------------------------------------------------------- overview

    public function test_overview_numbers(): void
    {
        $a = $this->person('a@example.com', 'alice');
        $this->person('b@example.com', 'bob', ['suspended_at' => now()]);
        $this->photo($a);
        VerifiedAccount::create(['user_id' => $a->id, 'network' => 'github', 'identifier' => 'a', 'external_id' => '1', 'method' => 'gist', 'verified_at' => now()]);
        Site::createWithKey(['user_id' => $a->id, 'name' => 's', 'domain' => 's.ir']);
        Report::create(['profile_id' => $a->profile->id, 'reason' => 'spam']);
        DB::table('daily_stats')->insert(['user_id' => $a->id, 'date' => now()->toDateString(), 'metric' => 'avatar', 'count' => 7]);

        $r = $this->asAdmin()->getJson('/admin/api/overview')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $c = $r->json('counts');
        $this->assertSame([3, 1, 1, 2, 1, 1, 1, 1], [$c['users'], $c['admins'], $c['suspended'], $c['public_profiles'], $c['avatars'], $c['verified_accounts'], $c['sites'], $c['reports_open']]);
        $this->assertSame(1, $c['verified_by_network']['github']);
        $this->assertCount(30, $r->json('series'));
        $this->assertSame(3, $r->json('series.29.signups'));
        $this->assertSame(1, $r->json('series.29.uploads'));
        $this->assertSame(7, $r->json('series.29.avatar'));
        $this->assertSame(7, $r->json('period.requests'));
        $this->assertSame(3, $r->json('period.signups'));
        $this->assertCount(3, $r->json('recent_users'));
        $this->assertSame('alice', $r->json('open_reports.0.profile.username'));
        $this->assertSame(PHP_VERSION, $r->json('system.php'));
        $this->assertStringNotContainsString(config('app.key'), $r->getContent());
        $this->assertStringNotContainsString('password', strtolower($r->getContent()));
    }

    // ---------------------------------------------------------------- users

    public function test_user_search_filters_sorting_and_pagination(): void
    {
        $this->person('alice@example.com', 'alice');
        $this->person('bob@corp.ir', 'bobby', ['suspended_at' => now()]);
        $c = $this->person('carol@example.com', 'carol');
        $c->emails()->update(['verified_at' => null]);
        $c->emails()->create(['email' => 'second@elsewhere.org', 'verified_at' => now()]);
        $c->emails()->update(['verified_at' => null]);                       // carol has no verified email at all

        $get = fn (string $qs) => $this->asAdmin()->getJson('/admin/api/users?'.$qs)->assertOk();
        $names = fn ($r) => array_column($r->json('data'), 'username');

        $this->assertEqualsCanonicalizing(['alice', 'bobby', 'carol', 'boss'], $names($get('')));
        $this->assertSame(['alice'], $names($get('q=ALI')));
        $this->assertSame(['bobby'], $names($get('q=corp.ir')));
        $this->assertSame(['carol'], $names($get('q=elsewhere')), 'matches any of the user\'s emails');
        $this->assertSame(['bobby'], $names($get('q=bob')));
        $this->assertSame([], $names($get('q=%25')), 'LIKE wildcards are escaped');
        $this->assertSame([], $names($get('q=_')));
        $this->assertSame(['alice'], $names($get('q='.User::where('email', 'alice@example.com')->value('id'))), 'a number finds that id');
        $this->assertSame(['boss'], $names($get('filter=admins')));
        $this->assertSame(['bobby'], $names($get('filter=suspended')));
        $this->assertSame(['carol'], $names($get('filter=unverified')));

        $this->assertSame('boss', $names($get('sort=newest'))[3] === 'boss' ? 'boss' : 'x', 'oldest is last when newest first');
        $this->assertSame('boss', $names($get('sort=oldest'))[0]);

        for ($i = 0; $i < 25; $i++) {
            $this->person("u$i@example.com", "user$i");
        }
        $p1 = $get('');
        $this->assertSame([1, 2, 29, 20], [$p1->json('meta.page'), $p1->json('meta.last_page'), $p1->json('meta.total'), $p1->json('meta.per_page')]);
        $this->assertCount(20, $p1->json('data'));
        $this->assertCount(9, $get('page=2')->json('data'));
    }

    public function test_user_search_never_leaks_secrets(): void
    {
        $this->person('a@example.com', 'alice');
        $body = $this->asAdmin()->getJson('/admin/api/users')->getContent();
        $this->assertStringNotContainsString('password', strtolower($body));
        $this->assertStringNotContainsString('remember_token', $body);
    }

    public function test_user_detail(): void
    {
        $a = $this->person('alice@example.com', 'alice', [], ['bio' => 'hello', 'headline' => 'Dev']);
        $a->emails()->create(['email' => 'unproven@example.com']);
        VerifiedAccount::create(['user_id' => $a->id, 'network' => 'github', 'identifier' => 'a', 'external_id' => '1', 'method' => 'gist', 'verified_at' => now()]);
        [, $key] = Site::createWithKey(['user_id' => $a->id, 'name' => 's', 'domain' => 'shop.ir']);
        $this->photo($a);
        Report::create(['profile_id' => $a->profile->id, 'reason' => 'spam']);
        Report::create(['profile_id' => $a->profile->id, 'reason' => 'other', 'status' => 'dismissed']);

        $r = $this->asAdmin()->getJson('/admin/api/users/'.$a->id)->assertOk();
        $this->assertSame(['alice', 'Dev'], [$r->json('profile.username'), $r->json('profile.headline')]);
        $this->assertSame([true, false], array_column($r->json('emails'), 'verified'));
        $this->assertSame('github', $r->json('verified_accounts.0.network'));
        $this->assertSame('shop.ir', $r->json('sites.0.domain'));
        $this->assertSame(1, $r->json('avatars_count'));
        $this->assertSame([2, 1], [$r->json('reports.total'), $r->json('reports.open')]);
        $this->assertStringNotContainsString($key, $r->getContent(), 'the API key is never shown');
        $this->assertStringNotContainsString('api_key_hash', $r->getContent());
        $this->asAdmin()->getJson('/admin/api/users/99999')->assertNotFound();
    }

    // ---------------------------------------------------------------- suspension

    public function test_suspending_takes_the_user_off_every_public_surface(): void
    {
        $u = $this->person('alice@example.com', 'alice', [], ['bio' => 'hi']);
        $this->photo($u);
        $hash = UserEmail::hash('alice@example.com');
        [, $key] = Site::createWithKey(['user_id' => $u->id, 'name' => 's', 'domain' => 's.ir']);
        $viewer = Site::createWithKey(['user_id' => $this->admin->id, 'name' => 'v', 'domain' => 'v.ir'])[1];

        $this->get('/alice')->assertOk();
        $this->get("/avatar/$hash")->assertHeader('Content-Type', 'image/jpeg');
        $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $viewer"])->assertOk();

        $this->asAdmin()->postJson("/admin/api/users/{$u->id}/suspend", ['reason' => 'Spam profile'])->assertOk()->assertJsonPath('suspended', true);

        foreach (['/alice', '/alice.json', '/alice.md', '/alice.vcf', '/alice.card', '/alice.qr', '/alice/print', '/alice/share.svg'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->get("/avatar/$hash")->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/alice/avatar')->assertHeader('Content-Type', 'image/svg+xml')->assertDontSee('<text', false);
        $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $viewer"])->assertNotFound();
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => [$hash]], ['Authorization' => "Bearer $viewer"])->assertJsonPath("data.$hash", null);
        $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $key"])->assertStatus(401);       // the suspended user's own key stops too
        $this->postJson('/login', ['email' => 'alice@example.com', 'password' => 'password'])->assertStatus(403);

        $this->assertSame('Spam profile', $u->fresh()->suspended_reason);
        $this->assertSame(1, AuditLog::where('action', 'user.suspend')->count());

        $this->asAdmin()->postJson("/admin/api/users/{$u->id}/unsuspend")->assertOk()->assertJsonPath('suspended', false);
        $this->get('/alice')->assertOk();
        $this->get("/avatar/$hash")->assertHeader('Content-Type', 'image/jpeg');
        $this->assertNull($u->fresh()->suspended_reason);
    }

    public function test_suspending_needs_a_reason_and_cannot_hit_yourself_or_another_admin(): void
    {
        $u = $this->person('alice@example.com', 'alice');
        $other = $this->person('other@example.com', 'other', ['is_admin' => true]);

        $this->asAdmin()->postJson("/admin/api/users/{$u->id}/suspend", [])->assertStatus(422);
        $this->postJson("/admin/api/users/{$u->id}/suspend", ['reason' => 'ab'])->assertStatus(422);
        $this->postJson("/admin/api/users/{$this->admin->id}/suspend", ['reason' => 'because'])->assertStatus(422);
        $this->postJson("/admin/api/users/{$other->id}/suspend", ['reason' => 'because'])->assertStatus(422);
        $this->postJson('/admin/api/users/9999/suspend', ['reason' => 'because'])->assertNotFound();
        $this->assertNull($u->fresh()->suspended_at);
        $this->assertSame(0, AuditLog::count());
    }

    // ---------------------------------------------------------------- admin flag, rename, moderation

    public function test_promote_and_demote(): void
    {
        $u = $this->person('alice@example.com', 'alice');
        $this->asAdmin()->postJson("/admin/api/users/{$u->id}/admin", ['is_admin' => true])->assertOk()->assertJsonPath('is_admin', true);
        $this->actingAs($u->fresh())->getJson('/admin/api/overview')->assertOk();
        $this->asAdmin()->postJson("/admin/api/users/{$u->id}/admin", ['is_admin' => false])->assertOk();
        $this->actingAs($u->fresh())->getJson('/admin/api/overview')->assertNotFound();
        $this->asAdmin()->postJson("/admin/api/users/{$this->admin->id}/admin", ['is_admin' => false])->assertStatus(422);
        $this->assertTrue($this->admin->fresh()->is_admin, 'nobody can demote themselves');
        $this->postJson("/admin/api/users/{$u->id}/admin", [])->assertStatus(422);
        $this->assertSame(['user.promote', 'user.demote'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_rename_validates_and_is_audited(): void
    {
        $u = $this->person('alice@example.com', 'alice');
        $this->person('taken@example.com', 'taken');

        $this->asAdmin()->postJson("/admin/api/users/{$u->id}/rename", ['username' => 'taken'])->assertStatus(422);
        $this->postJson("/admin/api/users/{$u->id}/rename", ['username' => 'admin'])->assertStatus(422);
        $this->postJson("/admin/api/users/{$u->id}/rename", ['username' => 'A!'])->assertStatus(422);
        $this->postJson("/admin/api/users/{$u->id}/rename", ['username' => 'alice'])->assertOk();            // unchanged is fine
        $this->postJson("/admin/api/users/{$u->id}/rename", ['username' => 'alice_2'])->assertOk()->assertJsonPath('username', 'alice_2');
        $this->get('/alice')->assertNotFound();
        $this->get('/alice_2')->assertOk();
        $this->assertSame(['from' => 'alice', 'to' => 'alice_2'], AuditLog::where('action', 'user.rename')->latest('id')->first()->meta);
    }

    public function test_remove_avatar_and_clear_profile(): void
    {
        $u = $this->person('alice@example.com', 'alice', [], ['bio' => 'b', 'headline' => 'h', 'location' => 'l', 'website' => 'https://a.ir', 'socials' => ['github' => 'a'], 'links' => [['label' => 'x', 'url' => 'https://x.ir']]]);
        $this->photo($u);
        $this->photo($u);
        $this->assertSame(2, $u->avatars()->count());

        $this->asAdmin()->postJson("/admin/api/users/{$u->id}/avatar/remove")->assertOk();
        $this->assertNull($u->profile->fresh()->avatar_path);
        $this->assertSame(2, $u->avatars()->count(), 'only deactivated, the library stays');

        $this->postJson("/admin/api/users/{$u->id}/avatar/remove", ['all' => true])->assertOk();
        $this->assertSame(0, $u->avatars()->count());

        $this->postJson("/admin/api/users/{$u->id}/profile/clear", ['fields' => ['bio', 'links']])->assertOk();
        $p = $u->profile->fresh();
        $this->assertNull($p->bio);
        $this->assertNull($p->links);
        $this->assertSame(['h', 'l', 'https://a.ir'], [$p->headline, $p->location, $p->website], 'untouched fields stay');
        $this->postJson("/admin/api/users/{$u->id}/profile/clear", ['fields' => ['names']])->assertOk();
        $this->postJson("/admin/api/users/{$u->id}/profile/clear", ['fields' => ['password']])->assertStatus(422);
        $this->postJson("/admin/api/users/{$u->id}/profile/clear", [])->assertStatus(422);
        $this->assertSame(['user.avatar_removed', 'user.avatar_removed', 'user.profile_cleared', 'user.profile_cleared'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    // ---------------------------------------------------------------- deletion

    public function test_deleting_a_user_needs_the_typed_email_and_removes_everything_including_files(): void
    {
        $u = $this->person('alice@example.com', 'alice');
        $this->photo($u);
        VerifiedAccount::create(['user_id' => $u->id, 'network' => 'github', 'identifier' => 'a', 'external_id' => '1', 'method' => 'gist', 'verified_at' => now()]);
        Site::createWithKey(['user_id' => $u->id, 'name' => 's', 'domain' => 's.ir']);
        DB::table('daily_stats')->insert(['user_id' => $u->id, 'date' => now()->toDateString(), 'metric' => 'avatar', 'count' => 1]);
        $path = $u->avatars()->first()->path;
        Storage::disk('local')->assertExists($path);

        $this->asAdmin()->deleteJson("/admin/api/users/{$u->id}", [])->assertStatus(422);
        $this->deleteJson("/admin/api/users/{$u->id}", ['confirm' => 'wrong@example.com'])->assertStatus(422);
        $this->assertNotNull(User::find($u->id));

        $this->deleteJson("/admin/api/users/{$u->id}", ['confirm' => ' ALICE@example.com '])->assertOk();
        $this->assertNull(User::find($u->id));
        foreach (['profiles', 'user_emails', 'avatars', 'verified_accounts', 'sites', 'daily_stats'] as $t) {
            $this->assertSame(0, DB::table($t)->where('user_id', $u->id)->count(), $t);
        }
        Storage::disk('local')->assertMissing($path);
        $this->get('/alice')->assertNotFound();

        $log = AuditLog::where('action', 'user.delete')->first();
        $this->assertSame('alice@example.com', $log->target_label);
        $this->assertNull(User::find($log->target_id));
    }

    public function test_deleting_yourself_or_an_admin_is_refused(): void
    {
        $other = $this->person('other@example.com', 'other', ['is_admin' => true]);
        $this->asAdmin()->deleteJson("/admin/api/users/{$this->admin->id}", ['confirm' => 'boss@example.com'])->assertStatus(422);
        $this->deleteJson("/admin/api/users/{$other->id}", ['confirm' => 'other@example.com'])->assertStatus(422);
        $this->assertSame(2, User::count());
    }

    public function test_users_can_delete_their_own_account_with_the_password(): void
    {
        $u = $this->person('alice@example.com', 'alice');
        $u->update(['password' => 'secret-pass-1']);
        $this->photo($u);
        $path = $u->avatars()->first()->path;

        $this->actingAs($u)->deleteJson('/account', ['password' => 'wrong'])->assertStatus(422);
        $this->assertNotNull(User::find($u->id));
        $this->deleteJson('/account', ['password' => 'secret-pass-1'])->assertOk();
        $this->assertNull(User::find($u->id));
        Storage::disk('local')->assertMissing($path);
        $this->assertGuest();
    }

    public function test_admins_cannot_delete_themselves_through_the_account_endpoint(): void
    {
        $this->admin->update(['password' => 'secret-pass-1']);
        $this->actingAs($this->admin)->deleteJson('/account', ['password' => 'secret-pass-1'])->assertStatus(422);
        $this->assertNotNull(User::find($this->admin->id));
    }

    // ---------------------------------------------------------------- reports

    public function test_visitors_can_report_a_profile_once_a_day(): void
    {
        $u = $this->person('alice@example.com', 'alice');
        $this->postJson('/alice/report', ['reason' => 'spam', 'details' => 'sells pills'])->assertOk()->assertJsonStructure(['message']);
        $this->postJson('/alice/report', ['reason' => 'spam'])->assertOk();             // same person again: same answer, no second row
        $this->assertSame(1, Report::count());
        $r = Report::first();
        $this->assertSame(['spam', 'sells pills', 'open', $u->profile->id], [$r->reason, $r->details, $r->status, $r->profile_id]);
        $this->assertSame(32, strlen($r->reporter_hash));
        $this->assertStringNotContainsString('127.0.0.1', json_encode($r->toArray()));

        Report::query()->update(['created_at' => now()->subDays(2)]);
        $this->postJson('/alice/report', ['reason' => 'other'])->assertOk();
        $this->assertSame(2, Report::count());
    }

    public function test_report_validation_hidden_profiles_and_self_reports(): void
    {
        $u = $this->person('alice@example.com', 'alice');
        $this->postJson('/alice/report', [])->assertStatus(422);
        $this->postJson('/alice/report', ['reason' => 'nonsense'])->assertStatus(422);
        $this->postJson('/alice/report', ['reason' => 'spam', 'details' => str_repeat('x', 501)])->assertStatus(422);
        $this->postJson('/nobody/report', ['reason' => 'spam'])->assertNotFound();

        $this->actingAs($u)->postJson('/alice/report', ['reason' => 'spam'])->assertOk();
        $this->assertSame(0, Report::count(), 'you cannot report yourself, but you get the same answer');
        auth()->logout();

        $u->profile->update(['show_profile' => false]);
        $this->postJson('/alice/report', ['reason' => 'spam'])->assertNotFound();
    }

    public function test_reports_are_throttled(): void
    {
        $this->person('alice@example.com', 'alice');
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/alice/report', ['reason' => 'spam'])->assertOk();
        }
        $this->postJson('/alice/report', ['reason' => 'spam'])->assertStatus(429);
    }

    public function test_admin_report_queue_resolve_and_reopen(): void
    {
        $a = $this->person('alice@example.com', 'alice');
        $b = $this->person('bob@example.com', 'bobby');
        $r1 = Report::create(['profile_id' => $a->profile->id, 'reason' => 'spam', 'reporter_hash' => 'x']);
        $r2 = Report::create(['profile_id' => $a->profile->id, 'reason' => 'illegal', 'reporter_hash' => 'y']);
        $r3 = Report::create(['profile_id' => $b->profile->id, 'reason' => 'other', 'reporter_hash' => 'z']);

        $list = $this->asAdmin()->getJson('/admin/api/reports')->assertOk();
        $this->assertSame(3, $list->json('meta.total'));
        $this->assertSame(['open' => 3, 'actioned' => 0, 'dismissed' => 0], $list->json('counts'));
        $this->assertSame(2, collect($list->json('data'))->firstWhere('id', $r1->id)['open_for_profile']);
        $this->assertCount(1, $this->getJson('/admin/api/reports?reason=other')->json('data'));

        $this->postJson("/admin/api/reports/{$r1->id}/resolve", ['status' => 'actioned', 'note' => 'removed photo', 'all_for_profile' => true])->assertOk()->assertJsonPath('updated', 2);
        $this->assertSame(['actioned', 'actioned', 'open'], [$r1->fresh()->status, $r2->fresh()->status, $r3->fresh()->status]);
        $this->assertSame([$this->admin->id, 'removed photo'], [$r1->fresh()->handled_by, $r1->fresh()->note]);

        $this->postJson("/admin/api/reports/{$r3->id}/resolve", ['status' => 'dismissed'])->assertOk()->assertJsonPath('updated', 1);
        $this->assertSame(0, $this->getJson('/admin/api/reports')->json('meta.total'));
        $this->assertSame(2, $this->getJson('/admin/api/reports?status=actioned')->json('meta.total'));
        $this->assertSame('boss@example.com', $this->getJson('/admin/api/reports?status=dismissed')->json('data.0.handled_by'));

        $this->postJson("/admin/api/reports/{$r3->id}/reopen")->assertOk();
        $this->assertSame('open', $r3->fresh()->status);
        $this->assertNull($r3->fresh()->handled_by);
        $this->postJson("/admin/api/reports/{$r1->id}/resolve", ['status' => 'nonsense'])->assertStatus(422);
        $this->postJson('/admin/api/reports/9999/resolve', ['status' => 'dismissed'])->assertNotFound();
    }

    public function test_deleting_a_profile_owner_removes_their_reports(): void
    {
        $a = $this->person('alice@example.com', 'alice');
        Report::create(['profile_id' => $a->profile->id, 'reason' => 'spam']);
        $this->asAdmin()->deleteJson("/admin/api/users/{$a->id}", ['confirm' => 'alice@example.com'])->assertOk();
        $this->assertSame(0, Report::count());
    }

    // ---------------------------------------------------------------- sites

    public function test_site_management_and_disabling_a_key(): void
    {
        $owner = $this->person('shop@example.com', 'shop');
        [$site, $key] = Site::createWithKey(['user_id' => $owner->id, 'name' => 'Shop', 'domain' => 'shop.ir']);
        Site::createWithKey(['user_id' => $owner->id, 'name' => 'Blog', 'domain' => 'blog.ir']);
        $hash = UserEmail::hash('boss@example.com');

        $list = $this->asAdmin()->getJson('/admin/api/sites')->assertOk();
        $this->assertSame(2, $list->json('meta.total'));
        $this->assertSame('shop@example.com', $list->json('data.1.owner.email'));
        $this->assertStringNotContainsString($key, $list->getContent());
        $this->assertStringNotContainsString('api_key_hash', $list->getContent());
        $this->assertCount(1, $this->getJson('/admin/api/sites?q=blog')->json('data'));
        $this->assertCount(2, $this->getJson('/admin/api/sites?q=shop@example')->json('data'));
        $this->assertCount(2, $this->getJson('/admin/api/sites?status=idle')->json('data'));

        $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $key"]);               // exercise the key once
        $this->assertSame(200, $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $key"])->status());

        $this->patchJson("/admin/api/sites/{$site->id}", ['disabled' => true])->assertOk()->assertJsonPath('disabled', true);
        $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $key"])->assertStatus(401)->assertJsonPath('message', 'Invalid or missing API key.');
        $this->assertCount(1, $this->getJson('/admin/api/sites?status=disabled')->json('data'));

        $this->patchJson("/admin/api/sites/{$site->id}", ['disabled' => false])->assertOk();
        $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $key"])->assertStatus(200);

        $this->patchJson("/admin/api/sites/{$site->id}", ['rate_limit_per_minute' => 5])->assertOk()->assertJsonPath('rate_limit', 5);
        $this->patchJson("/admin/api/sites/{$site->id}", ['rate_limit_per_minute' => 0])->assertStatus(422);
        $this->patchJson("/admin/api/sites/{$site->id}", ['rate_limit_per_minute' => 'many'])->assertStatus(422);

        $this->deleteJson("/admin/api/sites/{$site->id}")->assertOk();
        $this->getJson("/api/v1/profiles/$hash", ['Authorization' => "Bearer $key"])->assertStatus(401);
        $this->assertSame(['site.disable', 'site.enable', 'site.update', 'site.delete'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    // ---------------------------------------------------------------- verified accounts

    public function test_verified_accounts_can_be_listed_filtered_and_revoked(): void
    {
        $a = $this->person('alice@example.com', 'alice');
        $w = VerifiedAccount::create(['user_id' => $a->id, 'network' => 'website', 'identifier' => 'alice.ir', 'method' => 'meta', 'verified_at' => now()]);
        VerifiedAccount::create(['user_id' => $a->id, 'network' => 'github', 'identifier' => 'alice-gh', 'external_id' => '5', 'method' => 'gist', 'verified_at' => now()]);

        $this->asAdmin()->getJson('/admin/api/verified')->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertSame('github', $this->getJson('/admin/api/verified?network=github')->json('data.0.network'));
        $this->assertCount(1, $this->getJson('/admin/api/verified?q=alice.ir')->json('data'));
        $this->assertCount(2, $this->getJson('/admin/api/verified?q=alice@example')->json('data'));
        $this->assertSame('alice', $this->getJson('/admin/api/verified?network=website')->json('data.0.user.username'));

        $this->deleteJson("/admin/api/verified/{$w->id}")->assertOk();
        $this->assertSame(1, VerifiedAccount::count());
        $this->assertSame('verified.revoke', AuditLog::first()->action);
        $this->deleteJson('/admin/api/verified/99999')->assertNotFound();
    }

    // ---------------------------------------------------------------- audit log

    public function test_audit_log_records_who_did_what_and_survives_deletions(): void
    {
        $a = $this->person('alice@example.com', 'alice');
        $this->asAdmin()->postJson("/admin/api/users/{$a->id}/suspend", ['reason' => 'because'])->assertOk();
        $this->postJson("/admin/api/users/{$a->id}/unsuspend")->assertOk();
        $this->deleteJson("/admin/api/users/{$a->id}", ['confirm' => 'alice@example.com'])->assertOk();

        $log = $this->getJson('/admin/api/audit')->assertOk();
        $this->assertSame(3, $log->json('meta.total'));
        $this->assertSame(['user.delete', 'user.unsuspend', 'user.suspend'], array_column($log->json('data'), 'action'));
        $this->assertSame('boss@example.com', $log->json('data.0.admin'));
        $this->assertSame('alice@example.com', $log->json('data.0.target'));
        $this->assertSame(['reason' => 'because'], $log->json('data.2.meta'));
        $this->assertCount(1, $this->getJson('/admin/api/audit?action=user.s')->json('data'));
        $this->assertCount(3, $this->getJson('/admin/api/audit?action=user.')->json('data'));
        $this->assertCount(1, $this->getJson('/admin/api/audit?action=user.delete')->json('data'));
        $this->assertCount(3, $this->getJson('/admin/api/audit?q=boss@')->json('data'));
        $this->assertCount(3, $this->getJson('/admin/api/audit?q=alice')->json('data'));

        $this->admin->delete();                                         // even if the admin account disappears the trail stays
        $this->assertSame(3, AuditLog::count());
        $this->assertSame('boss@example.com', AuditLog::first()->admin_label);
    }

    public function test_audit_log_rows_are_immutable_from_the_api(): void
    {
        $this->asAdmin()->deleteJson('/admin/api/audit')->assertStatus(405);
        $this->patchJson('/admin/api/audit/1')->assertStatus(404);
    }

    // ---------------------------------------------------------------- artisan

    public function test_admin_create_and_grant_commands(): void
    {
        $this->artisan('admin:create', ['email' => 'New@Example.com', '--password' => 'long-enough-1'])->assertSuccessful();
        $u = User::where('email', 'new@example.com')->first();
        $this->assertTrue($u->is_admin);
        $this->assertNotNull($u->emails()->first()->verified_at);
        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]{2,31}$/', $u->profile->username);
        $this->assertNotContains($u->profile->username, \App\Rules\Username::RESERVED);

        $this->artisan('admin:create', ['email' => 'new@example.com'])->assertFailed();                       // exists
        $this->artisan('admin:create', ['email' => 'not-an-email'])->assertFailed();
        $this->artisan('admin:create', ['email' => 'short@example.com', '--password' => 'abc'])->assertFailed();
        $this->artisan('admin:create', ['email' => 'admin@example.com', '--password' => 'long-enough-1'])->assertSuccessful();
        $this->assertNotSame('admin', User::where('email', 'admin@example.com')->first()->profile->username, 'reserved names are never used');

        $normal = $this->person('n@example.com', 'normal');
        $this->artisan('admin:grant', ['email' => 'n@example.com'])->assertSuccessful();
        $this->assertTrue($normal->fresh()->is_admin);
        $this->artisan('admin:grant', ['email' => 'n@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($normal->fresh()->is_admin);
        $this->artisan('admin:grant', ['email' => 'nobody@example.com'])->assertFailed();
    }

    public function test_the_last_admin_cannot_be_revoked_from_the_command_line(): void
    {
        User::where('id', '!=', $this->admin->id)->delete();
        $this->artisan('admin:grant', ['email' => 'boss@example.com', '--revoke' => true])->assertFailed();
        $this->assertTrue($this->admin->fresh()->is_admin);
    }

    public function test_the_public_page_offers_a_report_form_to_visitors_but_not_to_the_owner(): void
    {
        $u = $this->person('alice@example.com', 'alice');
        $this->get('/alice')->assertOk()->assertSee('data-report-open', false)->assertSee(route('profile.report', 'alice'), false)->assertSee('گزارش این پروفایل');
        $this->withUnencryptedCookie('locale', 'en')->get('/alice')->assertSee('Report this profile');
        $this->actingAs($u)->get('/alice')->assertOk()->assertDontSee('data-report-open', false);
    }
}
