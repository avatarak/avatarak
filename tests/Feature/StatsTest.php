<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Site;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\StatsRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $hash;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = User::factory()->create();
        Profile::create(['user_id' => $this->owner->id, 'username' => 'javid', 'name_en' => 'Javid']);
        $this->owner->emails()->create(['email' => 'javid@example.com', 'verified_at' => now()]);
        $this->actingAs($this->owner)->postJson('/account/avatars', ['avatar' => UploadedFile::fake()->image('a.jpg', 400, 400)])->assertCreated();
        auth()->logout();
        $this->hash = UserEmail::hash('javid@example.com');
    }

    private function total(string $metric): int
    {
        return (int) DB::table('daily_stats')->where('user_id', $this->owner->id)->where('metric', $metric)->sum('count');
    }

    public function test_avatar_serves_are_counted_with_the_referring_host(): void
    {
        $this->get("/avatar/{$this->hash}", ['Referer' => 'https://www.Blog.Example.com/post/1?x=1'])->assertOk();
        $this->get("/avatar/{$this->hash}", ['Referer' => 'https://blog.example.com/'])->assertOk();
        $this->get("/avatar/{$this->hash}")->assertOk();                       // no referrer: counted, host unknown

        $this->assertSame(3, $this->total('avatar'));
        $row = DB::table('stat_referrers')->first();
        $this->assertSame(['blog.example.com', 2, 'avatar'], [$row->host, $row->count, $row->metric]);
        $this->assertSame(1, DB::table('stat_referrers')->count());
    }

    public function test_conditional_requests_and_profile_avatar_urls_count_too(): void
    {
        $etag = $this->get("/avatar/{$this->hash}")->headers->get('ETag');
        $this->get("/avatar/{$this->hash}", ['If-None-Match' => $etag])->assertStatus(304);
        $this->get('/javid/avatar')->assertOk();
        $this->assertSame(3, $this->total('avatar'));
    }

    public function test_defaults_are_never_counted(): void
    {
        $this->get('/avatar/'.str_repeat('a', 64))->assertOk();                                   // unknown
        $this->get('/avatar/'.UserEmail::hash('nobody@example.com').'?d=identicon')->assertOk();
        $this->owner->profile->update(['show_avatar' => false]);
        $this->get("/avatar/{$this->hash}")->assertOk();                                          // hidden photo -> default
        $this->assertSame(0, DB::table('daily_stats')->count());
    }

    public function test_profile_views_and_exports_are_counted_but_not_the_owners_own(): void
    {
        $this->get('/javid')->assertOk();
        $this->get('/javid.json')->assertOk();
        $this->get('/javid.qr')->assertOk();
        $this->assertSame([1, 2], [$this->total('profile'), $this->total('export')]);

        $this->actingAs($this->owner)->get('/javid')->assertOk();
        $this->get('/javid.json')->assertOk();
        $this->assertSame([1, 2], [$this->total('profile'), $this->total('export')], 'owner traffic is ignored');
    }

    public function test_bots_head_requests_and_own_site_referrers_are_ignored(): void
    {
        $this->get("/avatar/{$this->hash}", ['User-Agent' => 'Googlebot/2.1']);
        $this->get("/avatar/{$this->hash}", ['User-Agent' => 'Slackbot-LinkExpanding 1.0']);
        $this->call('HEAD', "/avatar/{$this->hash}");
        $this->assertSame(0, $this->total('avatar'));

        $this->get("/avatar/{$this->hash}", ['Referer' => config('app.url').'/somewhere', 'User-Agent' => 'Mozilla/5.0']);
        $this->assertSame(1, $this->total('avatar'));
        $this->assertSame(0, DB::table('stat_referrers')->count(), 'our own pages are not a referrer');
    }

    public static function referers(): array
    {
        return [['https://www.example.com/a', 'example.com'], ['http://Sub.Example.ORG:8080/x', 'sub.example.org'], ['https://127.0.0.1/x', null],
            ['not a url', null], ['', null], ['https://localhost/x', null], ['javascript:alert(1)', null], ['https://'.str_repeat('a', 120).'.com/', null]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('referers')]
    public function test_referer_host_extraction(string $referer, ?string $expected): void
    {
        $req = \Illuminate\Http\Request::create('/x', 'GET', [], [], [], ['HTTP_REFERER' => $referer]);
        $this->assertSame($expected, StatsRecorder::refererHost($req));
    }

    public function test_api_use_is_counted_per_connected_site(): void
    {
        [, $key] = Site::createWithKey(['user_id' => User::factory()->create()->id, 'name' => 's', 'domain' => 'shop.example.ir']);
        $h = ['Authorization' => "Bearer $key"];
        $this->getJson("/api/v1/profiles/{$this->hash}", $h)->assertOk();
        $this->postJson('/api/v1/profiles/lookup', ['hashes' => [$this->hash, str_repeat('c', 64)]], $h)->assertOk();
        $this->getJson('/api/v1/profiles/'.str_repeat('d', 64), $h)->assertNotFound();

        $this->assertSame(2, $this->total('api'), 'only profiles that were actually returned');
        $this->assertSame('shop.example.ir', DB::table('stat_referrers')->where('metric', 'api')->value('host'));
    }

    public function test_the_stats_endpoint_returns_a_zero_filled_series_and_top_referrers(): void
    {
        $this->get("/avatar/{$this->hash}", ['Referer' => 'https://a.example.com/']);
        $this->get("/avatar/{$this->hash}", ['Referer' => 'https://a.example.com/']);
        $this->get("/avatar/{$this->hash}", ['Referer' => 'https://b.example.com/']);
        $this->get('/javid');
        DB::table('daily_stats')->insert(['user_id' => $this->owner->id, 'date' => now()->subDays(3)->toDateString(), 'metric' => 'avatar', 'count' => 10]);

        $r = $this->actingAs($this->owner)->getJson('/account/stats?days=7')->assertOk();
        $this->assertSame(7, $r->json('days'));
        $this->assertCount(7, $r->json('series'));
        $this->assertSame(now()->toDateString(), $r->json('series.6.date'));
        $this->assertEquals(['avatar' => 13, 'profile' => 1, 'export' => 0, 'api' => 0], $r->json('totals'));
        $this->assertSame(3, $r->json('series.6.avatar'));
        $this->assertSame(10, $r->json('series.3.avatar'));
        $this->assertSame(0, $r->json('series.0.avatar'));
        $this->assertSame([['host' => 'a.example.com', 'count' => 2], ['host' => 'b.example.com', 'count' => 1]], $r->json('referrers'));

        $this->assertSame(30, $this->getJson('/account/stats?days=999')->json('days'), 'unsupported ranges fall back to 30');
        $this->assertSame(0, $this->getJson('/account/stats?days=90')->json('totals.api'));
    }

    public function test_users_only_see_their_own_numbers(): void
    {
        $this->get("/avatar/{$this->hash}");
        $other = User::factory()->create();
        Profile::create(['user_id' => $other->id, 'username' => 'other']);
        $this->actingAs($other)->getJson('/account/stats')->assertOk()->assertJsonPath('totals.avatar', 0)->assertJsonPath('referrers', []);
        auth()->logout();
        $this->getJson('/account/stats')->assertStatus(401);
    }

    public function test_old_rows_are_pruned_and_deleting_a_user_deletes_their_stats(): void
    {
        $old = now()->subDays(config('stats.retention_days') + 1)->toDateString();
        DB::table('daily_stats')->insert(['user_id' => $this->owner->id, 'date' => $old, 'metric' => 'avatar', 'count' => 5]);
        DB::table('stat_referrers')->insert(['user_id' => $this->owner->id, 'date' => $old, 'metric' => 'avatar', 'host' => 'x.com', 'count' => 5]);
        $this->get("/avatar/{$this->hash}");

        $this->assertSame(2, StatsRecorder::prune());
        $this->assertSame(1, DB::table('daily_stats')->count());

        $this->owner->delete();
        $this->assertSame(0, DB::table('daily_stats')->count());
    }

    public function test_distinct_hosts_per_day_are_capped(): void
    {
        config(['stats.max_hosts_per_day' => 3]);
        foreach (['a', 'b', 'c', 'd', 'e'] as $h) {
            $this->get("/avatar/{$this->hash}", ['Referer' => "https://$h.example.com/"]);
        }
        $this->get("/avatar/{$this->hash}", ['Referer' => 'https://a.example.com/']);     // an existing host keeps counting

        $hosts = DB::table('stat_referrers')->pluck('count', 'host')->all();
        $this->assertSame(['a.example.com' => 2, 'b.example.com' => 1, 'c.example.com' => 1, 'other' => 2], $hosts);
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['stats.enabled' => false]);
        $this->get("/avatar/{$this->hash}")->assertOk();
        $this->get('/javid')->assertOk();
        $this->assertSame(0, DB::table('daily_stats')->count());
    }

    public function test_a_failure_to_record_never_breaks_the_image(): void
    {
        DB::statement('drop table stat_referrers');
        $this->get("/avatar/{$this->hash}", ['Referer' => 'https://a.example.com/'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_no_visitor_identifiers_are_stored(): void
    {
        $this->get("/avatar/{$this->hash}", ['Referer' => 'https://a.example.com/', 'User-Agent' => 'Mozilla/5.0 Secret']);
        $dump = json_encode([DB::table('daily_stats')->get(), DB::table('stat_referrers')->get()]);
        $this->assertStringNotContainsString('127.0.0.1', $dump);
        $this->assertStringNotContainsString('Secret', $dump);
        $this->assertSame(['id', 'user_id', 'date', 'metric', 'count'], array_keys((array) DB::table('daily_stats')->first()));
    }
}
