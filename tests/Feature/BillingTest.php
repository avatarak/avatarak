<?php

namespace Tests\Feature;

use App\Mail\BillingNotice;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\Payment\GatewayManager;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'ali@example.com', string $name = 'ali'): User
    {
        $u = User::factory()->create(['email' => $email]);
        Profile::create(['user_id' => $u->id, 'username' => $name]);
        $u->emails()->create(['email' => $email, 'is_primary' => true, 'verified_at' => now()]);

        return $u;
    }

    private function fake(): void
    {
        config(['billing.gateway' => 'fake']);
    }

    private function pay(User $u, string $plan = 'pro', string $period = 'month'): Payment
    {
        $url = $this->actingAs($u)->postJson('/account/billing/checkout', ['plan' => $plan, 'period' => $period])->assertOk()->json('url');
        $this->get($url)->assertRedirect('/panel?billing=ok');

        return Payment::latest('id')->first();
    }

    // ------------------------------------------------------------------ catalogue & status

    public function test_everyone_starts_on_free_and_the_screen_lists_the_plans(): void
    {
        $u = $this->user();
        $r = $this->actingAs($u)->getJson('/account/billing')->assertOk();
        $this->assertSame('no-store, private', $r->headers->get('Cache-Control'));
        $this->assertSame(['free', null, false], [$r->json('current.plan'), $r->json('current.ends_at'), $r->json('enabled')]);
        $this->assertSame(['free', 'pro', 'business'], array_column($r->json('plans'), 'key'));
        $this->assertSame(0, $r->json('usage.sites'));
        $this->assertSame(3, $r->json('plans.0.limits.sites'));
        $this->assertSame(150000, $r->json('plans.1.price.month'));
        $this->getJson('/account/billing')->assertOk();
        auth()->logout();
        $this->getJson('/account/billing')->assertStatus(401);
    }

    public function test_payments_are_off_by_default(): void
    {
        $u = $this->user();
        $this->assertFalse(GatewayManager::enabled());
        $this->actingAs($u)->postJson('/account/billing/checkout', ['plan' => 'pro', 'period' => 'month'])->assertStatus(503);
        $this->assertSame(0, Payment::count());
    }

    public function test_zarinpal_without_a_merchant_id_is_off_and_the_fake_gateway_never_runs_in_production(): void
    {
        config(['billing.gateway' => 'zarinpal', 'billing.zarinpal.merchant_id' => null]);
        $this->assertFalse(GatewayManager::enabled());

        config(['billing.gateway' => 'fake']);
        $this->assertTrue(GatewayManager::enabled());
        $this->app['env'] = 'production';
        $this->assertFalse(GatewayManager::enabled(), 'a pretend gateway must never take "payments" in production');
    }

    // ------------------------------------------------------------------ the purchase

    public function test_a_payment_grants_the_plan_sends_a_receipt_and_changes_the_limits(): void
    {
        $this->fake();
        Mail::fake();
        $u = $this->user();
        $plans = app(PlanService::class);
        $this->assertSame(3, $plans->limit($u, 'sites'));

        $p = $this->pay($u);

        $this->assertSame(['paid', 150000, 'IRT', 'pro', 'month', 'fake'], [$p->status, $p->amount, $p->currency, $p->plan, $p->period, $p->gateway]);
        $this->assertNotNull($p->paid_at);
        $this->assertSame('TEST-'.$p->id, $p->ref_id);
        $sub = Subscription::first();
        $this->assertSame(['pro', 'payment', $p->id, $u->id], [$sub->plan, $sub->source, $sub->payment_id, $sub->user_id]);
        $this->assertSame(30, (int) $sub->starts_at->diffInDays($sub->ends_at));

        $this->assertSame('pro', $plans->planKey($u));
        $this->assertSame([25, 6000, true], [$plans->limit($u, 'sites'), $plans->limit($u, 'rate_limit'), $plans->can($u, 'custom_domain')]);
        Mail::assertQueued(BillingNotice::class, fn ($m) => $m->kind === 'receipt' && $m->hasTo('ali@example.com') && str_contains($m->data['amount'], '150,000') && $m->data['ref_id'] === 'TEST-'.$p->id);

        $s = $this->getJson('/account/billing')->assertOk();
        $this->assertSame('pro', $s->json('current.plan'));
        $this->assertSame('payment', $s->json('current.source'));
        $this->assertSame('paid', $s->json('payments.0.status'));
    }

    public function test_the_amount_always_comes_from_the_server(): void
    {
        $this->fake();
        $u = $this->user();
        $this->actingAs($u)->postJson('/account/billing/checkout', ['plan' => 'business', 'period' => 'year', 'amount' => 1, 'price' => 1])->assertOk();
        $this->assertSame(6000000, Payment::first()->amount);
    }

    public function test_bad_plans_and_periods_are_refused(): void
    {
        $this->fake();
        $u = $this->actingAs($this->user());
        foreach ([['free', 'month'], ['nonsense', 'month'], ['pro', 'decade'], ['pro', ''], ['', 'month']] as [$plan, $period]) {
            $this->postJson('/account/billing/checkout', ['plan' => $plan, 'period' => $period])->assertStatus(422);
        }
        $this->assertSame(0, Payment::count());
        config(['plans.plans.pro.price.month' => 0]);
        $this->postJson('/account/billing/checkout', ['plan' => 'pro', 'period' => 'month'])->assertStatus(422);
    }

    public function test_refreshing_the_callback_or_replaying_it_never_pays_twice(): void
    {
        $this->fake();
        Mail::fake();
        $u = $this->user();
        $url = $this->actingAs($u)->postJson('/account/billing/checkout', ['plan' => 'pro', 'period' => 'month'])->json('url');

        $this->get($url)->assertRedirect('/panel?billing=ok');
        $this->get($url)->assertRedirect('/panel?billing=ok');
        $this->get($url)->assertRedirect('/panel?billing=ok');

        $this->assertSame(1, Subscription::count());
        Mail::assertQueued(BillingNotice::class, 1);
    }

    public function test_a_failed_or_forged_callback_grants_nothing(): void
    {
        $this->fake();
        $u = $this->user();
        $url = $this->actingAs($u)->postJson('/account/billing/checkout', ['plan' => 'pro', 'period' => 'month'])->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        $this->get(route('billing.callback', ['Authority' => $q['Authority'], 'Status' => 'NOK']))->assertRedirect('/panel?billing=failed');
        $this->assertSame('failed', Payment::first()->status);
        $this->assertSame(0, Subscription::count());

        $this->get(route('billing.callback', ['Authority' => 'FAKEdoesnotexistatall', 'Status' => 'OK']))->assertRedirect('/panel?billing=failed');
        $this->get(route('billing.callback'))->assertRedirect('/panel?billing=failed');
        $this->get(route('billing.callback', ['Authority' => 'x']))->assertRedirect('/panel?billing=failed');
        $this->assertSame(0, Subscription::count());
        $this->assertSame('free', app(PlanService::class)->planKey($u));
    }

    // ------------------------------------------------------------------ ZarinPal

    public function test_zarinpal_request_verify_and_the_urls(): void
    {
        config(['billing.gateway' => 'zarinpal', 'billing.zarinpal.merchant_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']);
        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'message' => 'Success', 'authority' => 'A00000000000000000000000000000012345', 'fee' => 0]]),
            'payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 987654321, 'card_pan' => '603799******1234']]),
        ]);
        $u = $this->user();

        $url = $this->actingAs($u)->postJson('/account/billing/checkout', ['plan' => 'pro', 'period' => 'year'])->assertOk()->json('url');
        $this->assertSame('https://payment.zarinpal.com/pg/StartPay/A00000000000000000000000000000012345', $url);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/request.json')
            && $r['merchant_id'] === 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' && $r['amount'] === 1500000 && $r['currency'] === 'IRT'
            && $r['callback_url'] === route('billing.callback') && $r['metadata']['email'] === 'ali@example.com');

        $this->get(route('billing.callback', ['Authority' => 'A00000000000000000000000000000012345', 'Status' => 'OK']))->assertRedirect('/panel?billing=ok');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/verify.json') && $r['amount'] === 1500000 && $r['authority'] === 'A00000000000000000000000000000012345');

        $p = Payment::first();
        $this->assertSame(['paid', '987654321', '603799******1234'], [$p->status, $p->ref_id, $p->card_pan]);
        $this->assertSame(365, (int) Subscription::first()->starts_at->diffInDays(Subscription::first()->ends_at));
    }

    public function test_zarinpal_errors_do_not_create_plans_and_the_sandbox_has_its_own_hosts(): void
    {
        config(['billing.gateway' => 'zarinpal', 'billing.zarinpal.merchant_id' => 'm', 'billing.zarinpal.sandbox' => true]);
        Http::fake(['sandbox.zarinpal.com/*' => Http::response(['data' => [], 'errors' => ['code' => -9]], 200)]);
        $u = $this->actingAs($this->user());

        $this->postJson('/account/billing/checkout', ['plan' => 'pro', 'period' => 'month'])->assertStatus(502);
        $this->assertSame('failed', Payment::first()->status);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://sandbox.zarinpal.com/pg/v4/payment/request.json'));

        // a malformed authority is never trusted either
        Http::fake(['sandbox.zarinpal.com/*' => Http::response(['data' => ['code' => 100, 'authority' => '../../evil']])]);
        $this->postJson('/account/billing/checkout', ['plan' => 'pro', 'period' => 'month'])->assertStatus(502);
    }

    public function test_zarinpal_verification_failures_and_the_already_verified_code(): void
    {
        config(['billing.gateway' => 'zarinpal', 'billing.zarinpal.merchant_id' => 'm']);
        $auth = 'A0000000000000000000000000000000AAAA';
        $u = $this->user();
        $p = Payment::create(['user_id' => $u->id, 'plan' => 'pro', 'period' => 'month', 'amount' => 150000, 'currency' => 'IRT', 'gateway' => 'zarinpal', 'authority' => $auth]);

        Http::fake(['payment.zarinpal.com/*/verify.json' => Http::sequence()->push(['data' => ['code' => -50]])->push(['data' => ['code' => 101, 'ref_id' => 55, 'card_pan' => '1']])]);
        $this->get(route('billing.callback', ['Authority' => $auth, 'Status' => 'OK']))->assertRedirect('/panel?billing=failed');
        $this->assertSame(0, Subscription::count());

        $this->assertSame('failed', $p->fresh()->status);
        // the customer comes back again (or refreshes) and the gateway now says "already verified" (101): that is still a paid order
        $this->get(route('billing.callback', ['Authority' => $auth, 'Status' => 'OK']))->assertRedirect('/panel?billing=ok');
        $this->assertSame(1, Subscription::count());
    }

    public function test_a_status_other_than_ok_never_reaches_the_gateway(): void
    {
        config(['billing.gateway' => 'zarinpal', 'billing.zarinpal.merchant_id' => 'm']);
        Http::fake();
        $u = $this->user();
        Payment::create(['user_id' => $u->id, 'plan' => 'pro', 'period' => 'month', 'amount' => 150000, 'currency' => 'IRT', 'gateway' => 'zarinpal', 'authority' => 'A0000000000000000000000000000000BBBB']);
        $this->get(route('billing.callback', ['Authority' => 'A0000000000000000000000000000000BBBB', 'Status' => 'NOK']))->assertRedirect('/panel?billing=failed');
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ stacking, expiry

    public function test_buying_the_same_plan_again_extends_it_and_a_higher_plan_wins(): void
    {
        $this->fake();
        $u = $this->user();
        $this->pay($u);
        $first = Subscription::first();
        $this->pay($u);
        $second = Subscription::latest('id')->first();
        $this->assertTrue($second->starts_at->eq($first->ends_at), 'the second month starts when the first ends');
        $this->assertSame(60, (int) $first->starts_at->diffInDays($second->ends_at));

        $this->pay($u, 'business');
        $biz = Subscription::latest('id')->first();
        $this->assertTrue($biz->starts_at->lte(now()->addSecond()), 'a different plan starts now');
        $this->assertSame('business', app(PlanService::class)->planKey($u), 'the higher plan wins while both are active');
        $this->assertTrue(app(PlanService::class)->can($u, 'white_label'));
    }

    public function test_an_expired_plan_falls_back_to_free(): void
    {
        $u = $this->user();
        app(PlanService::class)->grant($u, 'pro', 10, 'admin');
        $this->assertSame('pro', app(PlanService::class)->planKey($u));
        $this->travel(11)->days();
        $this->assertSame('free', app(PlanService::class)->planKey($u->fresh()));
        $this->assertSame(3, app(PlanService::class)->limit($u->fresh(), 'sites'));
    }

    // ------------------------------------------------------------------ what the plan changes

    public function test_the_site_limit_follows_the_plan(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/account/sites', ['name' => "s$i", 'domain' => "s$i.example.ir"])->assertCreated();
        }
        $this->postJson('/account/sites', ['name' => 'x', 'domain' => 'x.example.ir'])->assertStatus(422)->assertJsonPath('message', __('app.plan_limit_sites'));

        app(PlanService::class)->grant($u, 'pro', 30, 'admin');
        $this->postJson('/account/sites', ['name' => 'x', 'domain' => 'x.example.ir'])->assertCreated();
    }

    public function test_the_api_rate_limit_follows_the_plan_unless_an_administrator_overrides_it(): void
    {
        $owner = $this->user('owner@example.com', 'owner');
        [$site, $key] = Site::createWithKey(['user_id' => $owner->id, 'name' => 's', 'domain' => 's.ir']);
        $h = ['Authorization' => "Bearer $key"];
        $hash = UserEmail::hash('nobody@example.com');

        $this->assertSame('600', $this->getJson("/api/v1/profiles/$hash", $h)->headers->get('X-RateLimit-Limit'));
        app(PlanService::class)->grant($owner, 'pro', 30, 'admin');
        $this->assertSame('6000', $this->getJson("/api/v1/profiles/$hash", $h)->headers->get('X-RateLimit-Limit'));

        $site->update(['rate_limit_per_minute' => 5]);                                       // an explicit override beats the plan, up or down
        $this->assertSame('5', $this->getJson("/api/v1/profiles/$hash", $h)->headers->get('X-RateLimit-Limit'));
        $site->update(['rate_limit_per_minute' => null]);
        $this->assertSame('6000', $this->getJson("/api/v1/profiles/$hash", $h)->headers->get('X-RateLimit-Limit'));
    }

    public function test_white_label_removes_the_brand_link_from_the_public_profile(): void
    {
        $u = $this->user();
        $this->get('/ali')->assertSee('class="hover:underline">'.config('brand.name').'</a>', false);
        app(PlanService::class)->grant($u, 'pro', 30, 'admin');
        $this->get('/ali')->assertSee(config('brand.name').'</a>', false);                   // Pro still shows the brand

        app(PlanService::class)->grant($u, 'business', 30, 'admin');
        $html = $this->get('/ali')->assertOk()->getContent();
        $this->assertStringNotContainsString('class="hover:underline">'.config('brand.name').'</a>', $html);
        $this->assertStringContainsString('data-report-open', $html, 'reporting stays available');
    }

    // ------------------------------------------------------------------ reminders

    public function test_the_reminder_is_sent_once_and_not_to_someone_who_renewed(): void
    {
        Mail::fake();
        $a = $this->user('a@example.com', 'aa');
        $b = $this->user('b@example.com', 'bb');
        $c = $this->user('c@example.com', 'cc');
        $plans = app(PlanService::class);
        $plans->grant($a, 'pro', 5, 'admin');
        $plans->grant($b, 'pro', 5, 'admin');
        $plans->grant($b, 'pro', 30, 'admin');          // b renewed: no reminder for the first period
        $plans->grant($c, 'pro', 60, 'admin');           // far from ending

        $this->artisan('billing:remind')->assertSuccessful();
        Mail::assertQueued(BillingNotice::class, 1);
        Mail::assertQueued(BillingNotice::class, fn ($m) => $m->kind === 'expiring' && $m->hasTo('a@example.com'));

        $this->artisan('billing:remind')->assertSuccessful();
        Mail::assertQueued(BillingNotice::class, 1);     // not again
        $this->assertTrue(collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode("\n") !== '' && str_contains(collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode("\n"), 'billing:remind'));
    }

    // ------------------------------------------------------------------ administrators

    private function admin(): User
    {
        $a = $this->user('boss@example.com', 'boss');
        $a->forceFill(['is_admin' => true])->save();

        return $a;
    }

    public function test_an_administrator_can_grant_and_end_a_plan_and_it_is_audited_and_announced(): void
    {
        Mail::fake();
        $u = $this->user();
        $this->actingAs($this->admin());

        $this->postJson("/admin/api/users/{$u->id}/plan", ['plan' => 'pro', 'days' => 45])->assertOk()->assertJson(['plan' => 'pro']);
        $this->assertSame('admin', Subscription::first()->source);
        Mail::assertQueued(BillingNotice::class, fn ($m) => $m->kind === 'granted' && $m->hasTo('ali@example.com'));
        $this->assertSame(['plan' => 'pro', 'days' => 45], collect(AuditLog::where('action', 'user.plan_granted')->first()->meta)->only(['plan', 'days'])->all());

        $this->getJson("/admin/api/users/{$u->id}")->assertJsonPath('plan', 'pro');
        $this->postJson("/admin/api/users/{$u->id}/plan", ['plan' => 'free'])->assertOk()->assertJson(['plan' => 'free']);
        $this->assertSame('free', app(PlanService::class)->planKey($u->fresh()));
        $this->assertSame(1, AuditLog::where('action', 'user.plan_ended')->count());

        $this->postJson("/admin/api/users/{$u->id}/plan", ['plan' => 'pro'])->assertStatus(422);                 // days needed
        $this->postJson("/admin/api/users/{$u->id}/plan", ['plan' => 'gold', 'days' => 5])->assertStatus(422);
        $this->postJson("/admin/api/users/{$u->id}/plan", ['plan' => 'pro', 'days' => 99999])->assertStatus(422);
    }

    public function test_the_payments_list_and_revenue_figures(): void
    {
        $this->fake();
        $u = $this->user();
        $this->pay($u);
        $this->pay($u, 'business', 'year');
        Payment::create(['user_id' => $u->id, 'plan' => 'pro', 'period' => 'month', 'amount' => 1, 'currency' => 'IRT', 'gateway' => 'fake', 'status' => 'failed']);

        $admin = $this->admin();
        $r = $this->actingAs($admin)->getJson('/admin/api/payments')->assertOk();
        $this->assertSame(3, $r->json('meta.total'));
        $this->assertSame([6150000, 2], [$r->json('totals.paid_amount'), $r->json('totals.paid_count')]);
        $this->assertSame(['ali@example.com'], array_values(array_unique(array_column(array_column($r->json('data'), 'user'), 'email'))));
        $this->assertCount(1, $this->getJson('/admin/api/payments?status=failed')->json('data'));
        $this->assertCount(2, $this->getJson('/admin/api/payments?q=ali@')->json('data') ? array_slice($this->getJson('/admin/api/payments?q=ali@&status=paid')->json('data'), 0, 2) : []);

        $o = $this->getJson('/admin/api/overview')->assertOk();
        $this->assertSame(6150000, $o->json('billing.revenue_30d'));
        $this->assertSame(1, $o->json('billing.paying_users'));
        $this->assertEquals(['pro' => 1, 'business' => 1], $o->json('billing.by_plan'));
    }

    public function test_an_administrators_site_override_can_be_cleared(): void
    {
        $u = $this->user();
        [$site] = Site::createWithKey(['user_id' => $u->id, 'name' => 's', 'domain' => 's.ir']);
        $this->actingAs($this->admin());
        $this->patchJson("/admin/api/sites/{$site->id}", ['rate_limit_per_minute' => 50])->assertOk()->assertJson(['rate_limit' => 50, 'rate_limit_override' => true]);
        $this->patchJson("/admin/api/sites/{$site->id}", ['rate_limit_per_minute' => null])->assertOk()->assertJson(['rate_limit' => 600, 'rate_limit_override' => false]);
    }

    public function test_billing_endpoints_are_for_admins_only_where_they_should_be(): void
    {
        $this->actingAs($this->user());
        $this->getJson('/admin/api/payments')->assertStatus(404);
        $this->postJson('/admin/api/users/1/plan', ['plan' => 'pro', 'days' => 3])->assertStatus(404);
    }
}
