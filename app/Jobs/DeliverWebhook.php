<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks;
use App\Services\WebhookUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $deliveryId) {}

    /** @return list<int> seconds between attempts */
    public function backoff(): array
    {
        return [30, 120, 600, 3600];
    }

    public function handle(): void
    {
        $d = WebhookDelivery::with('webhook')->find($this->deliveryId);
        $hook = $d?->webhook;
        if (! $d || ! $hook || ! $hook->active || $hook->disabled_at) {
            return;
        }

        $body = json_encode($d->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $d->increment('attempts');
        $status = null;
        $error = null;
        try {
            $target = WebhookUrl::check($hook->url);
            $options = ['allow_redirects' => false];
            if ($target['ip']) {
                // connect to the address we vetted, not whatever the name resolves to a moment later
                $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]];
            }
            $ts = time();
            $res = Http::withOptions($options)->timeout(config('webhooks.timeout'))->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Avatarak-Webhooks/1',
                'Avatarak-Event' => $d->event,
                'Avatarak-Delivery' => $d->event_id,
                'Avatarak-Signature' => Webhooks::sign($hook->secret, $ts, $body),
            ])->withBody($body, 'application/json')->post($hook->url);
            $status = $res->status();
            if (! $res->successful()) {
                $error = 'HTTP '.$status;
            }
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 190);
        }

        $ok = $error === null;
        $d->forceFill(['status' => $status, 'error' => $error, 'delivered_at' => $ok ? now() : null])->save();
        $hook->forceFill([
            'last_delivery_at' => now(), 'last_status' => $status,
            'failures' => $ok ? 0 : $hook->failures + 1,
        ]);
        if (! $ok && $hook->failures >= \App\Models\Webhook::MAX_FAILURES) {
            $hook->disabled_at = now();
        }
        $hook->save();

        if (! $ok) {
            $this->release($this->backoff()[min($this->attempts() - 1, 3)]);
        }
    }
}
