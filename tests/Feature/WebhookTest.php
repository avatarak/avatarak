<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\User;
use App\Models\Webhook;
use App\Models\VerifiedAccount;
use App\Services\Webhooks;
use App\Services\WebhookUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function pro(): User
    {
        $u = User::factory()->create();
        $u->profile()->create(['username' => 'ali'.$u->id, 'name_fa' => 'علی']);
        app(\App\Services\PlanService::class)->grant($u, 'pro', 30, 'admin');

        return $u->fresh();
    }

    public function test_free_plan_cannot_add_and_pro_can(): void
    {
        $free = User::factory()->create();
        $this->actingAs($free)->postJson('/account/webhooks', ['url' => 'https://example.com/h', 'events' => ['profile.updated']])->assertStatus(422);

        $pro = $this->pro();
        $r = $this->actingAs($pro)->postJson('/account/webhooks', ['url' => 'https://93.184.216.34/h', 'events' => ['profile.updated']])->assertCreated();
        $this->assertStringStartsWith('whsec_', $r->json('secret'));
        $this->actingAs($pro)->getJson('/account/webhooks')->assertOk()->assertJsonMissingPath('webhooks.0.secret');
    }

    public function test_url_must_be_public_https(): void
    {
        $pro = $this->pro();
        foreach (['http://example.com/h', 'https://127.0.0.1/h', 'https://10.0.0.5/h', 'https://[::1]/h', 'https://user:pw@example.com/h', 'ftp://example.com'] as $bad) {
            $this->actingAs($pro)->postJson('/account/webhooks', ['url' => $bad, 'events' => ['profile.updated']])->assertStatus(422);
        }
        $this->assertSame(0, Webhook::count());
    }

    public function test_url_guard_blocks_private_resolution_targets(): void
    {
        $this->expectException(\RuntimeException::class);
        WebhookUrl::check('https://169.254.169.254/latest');
    }

    public function test_profile_change_queues_signed_delivery_only_for_subscribed_events(): void
    {
        Bus::fake();
        $pro = $this->pro();
        $pro->webhooks()->create(['url' => 'https://93.184.216.34/h', 'secret' => 's3', 'events' => ['profile.updated']]);
        $pro->webhooks()->create(['url' => 'https://93.184.216.34/other', 'secret' => 's4', 'events' => ['avatar.changed']]);

        $pro->profile->update(['headline' => 'Hello']);

        Bus::assertDispatchedTimes(DeliverWebhook::class, 1);
        $d = \App\Models\WebhookDelivery::first();
        $this->assertSame('profile.updated', $d->event);
        $this->assertContains('headline', $d->payload['data']['changed']);
        $this->assertSame('ali'.$pro->id, $d->payload['data']['username']);
    }

    public function test_verification_events_and_suspended_owner(): void
    {
        Bus::fake();
        $pro = $this->pro();
        $pro->webhooks()->create(['url' => 'https://93.184.216.34/h', 'secret' => 's', 'events' => ['verification.added', 'verification.removed']]);
        $v = VerifiedAccount::create(['user_id' => $pro->id, 'network' => 'github', 'identifier' => 'ali', 'method' => 'oauth', 'verified_at' => now()]);
        $v->delete();
        Bus::assertDispatchedTimes(DeliverWebhook::class, 2);
    }

    public function test_delivery_sends_valid_signature_and_records_result(): void
    {
        config(['webhooks.allow_private' => true]);
        $pro = $this->pro();
        $hook = $pro->webhooks()->create(['url' => 'https://hooks.example.test/in', 'secret' => 'topsecret', 'events' => ['profile.updated']]);
        Http::fake(['hooks.example.test/*' => Http::response('ok', 200)]);

        $delivery = app(Webhooks::class)->queue($hook, app(Webhooks::class)->payload($pro, 'ping'));

        Http::assertSent(function ($req) use ($delivery) {
            [$t, $v] = array_map(fn ($p) => explode('=', $p, 2)[1], explode(',', $req->header('Avatarak-Signature')[0]));

            return hash_equals(hash_hmac('sha256', $t.'.'.$req->body(), 'topsecret'), $v)
                && $req->header('Avatarak-Delivery')[0] === $delivery->event_id
                && json_decode($req->body(), true)['type'] === 'ping';
        });
        $this->assertSame(200, $delivery->fresh()->status);
        $this->assertSame(0, $hook->fresh()->failures);
    }

    public function test_repeated_failures_switch_the_endpoint_off_and_reenabling_resets(): void
    {
        config(['webhooks.allow_private' => true]);
        $pro = $this->pro();
        $hook = $pro->webhooks()->create(['url' => 'https://down.example.test/in', 'secret' => 's', 'events' => ['profile.updated'], 'failures' => Webhook::MAX_FAILURES - 1]);
        Http::fake(['down.example.test/*' => Http::response('no', 500)]);
        $d = app(Webhooks::class)->queue($hook, app(Webhooks::class)->payload($pro, 'ping'));
        $this->assertNotNull($hook->fresh()->disabled_at);
        $this->assertSame('HTTP 500', $d->fresh()->error);

        $this->actingAs($pro)->patchJson("/account/webhooks/{$hook->id}", ['active' => true])->assertOk();
        $this->assertNull($hook->fresh()->disabled_at);
        $this->assertSame(0, $hook->fresh()->failures);
    }

    public function test_only_the_owner_can_touch_a_webhook_and_secret_rotates(): void
    {
        $pro = $this->pro();
        $other = $this->pro();
        $hook = $pro->webhooks()->create(['url' => 'https://example.com/h', 'secret' => 'old', 'events' => ['profile.updated']]);
        $this->actingAs($other)->deleteJson("/account/webhooks/{$hook->id}")->assertNotFound();
        $r = $this->actingAs($pro)->postJson("/account/webhooks/{$hook->id}/secret")->assertOk();
        $this->assertSame($r->json('secret'), $hook->fresh()->secret);
        $this->actingAs($pro)->deleteJson("/account/webhooks/{$hook->id}")->assertOk();
    }

    public function test_plan_webhook_limit_is_enforced(): void
    {
        $pro = $this->pro();
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($pro)->postJson('/account/webhooks', ['url' => "https://93.184.216.34/$i", 'events' => ['profile.updated']])->assertCreated();
        }
        $this->actingAs($pro)->postJson('/account/webhooks', ['url' => 'https://93.184.216.34/x', 'events' => ['profile.updated']])->assertStatus(422);
    }
}
