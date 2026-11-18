<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

/** Turns something that happened to a profile into queued, signed deliveries. */
class Webhooks
{
    /** Header value: "t=<unix>,v1=<hmac-sha256 of "<t>.<body>">" — the same scheme the SDK helpers verify. */
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** What the owner's endpoints receive: only things the owner already controls; never other people's data. */
    public function payload(User $user, string $event, array $data = []): array
    {
        $profile = $user->profile;

        return [
            'id' => (string) Str::uuid(),
            'type' => $event,
            'created_at' => now()->toIso8601String(),
            'data' => $data + [
                'username' => $profile?->username,
                'profile_url' => $profile ? url('/'.$profile->username) : null,
                'email_hashes' => $user->emails()->whereNotNull('verified_at')->pluck('email_hash')->values()->all(),
            ],
        ];
    }

    public function dispatch(User $user, string $event, array $data = []): int
    {
        if (! config('webhooks.enabled') || $user->isSuspended()) {
            return 0;
        }
        $hooks = $user->webhooks()->get()->filter(fn (Webhook $w) => $w->wants($event));
        if ($hooks->isEmpty()) {
            return 0;
        }
        $payload = $this->payload($user, $event, $data);
        foreach ($hooks as $hook) {
            $this->queue($hook, $payload);
        }

        return $hooks->count();
    }

    public function queue(Webhook $hook, array $payload): WebhookDelivery
    {
        $delivery = $hook->deliveries()->create(['event_id' => $payload['id'], 'event' => $payload['type'], 'payload' => $payload]);
        // keep the log short
        $old = $hook->deliveries()->orderByDesc('id')->skip(50)->take(1000)->pluck('id');
        if ($old->isNotEmpty()) {
            WebhookDelivery::whereIn('id', $old)->delete();
        }
        DeliverWebhook::dispatch($delivery->id);

        return $delivery;
    }
}
