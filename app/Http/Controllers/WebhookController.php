<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use App\Services\PlanService;
use App\Services\WebhookUrl;
use App\Services\Webhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'limit' => app(PlanService::class)->limit($user, 'webhooks'),
            'events' => Webhook::EVENTS,
            'webhooks' => $user->webhooks()->latest('id')->get()->map(fn (Webhook $w) => $this->present($w, true))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500', 'url:https,http'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(Webhook::EVENTS)],
        ]);
        $limit = app(PlanService::class)->limit($user, 'webhooks');
        abort_if($limit < 1, 422, __('app.webhook_plan'));
        abort_if($user->webhooks()->count() >= $limit, 422, __('app.webhook_limit'));
        $this->assertSafe($data['url']);

        $secret = 'whsec_'.Str::random(40);
        $hook = $user->webhooks()->create(['url' => $data['url'], 'secret' => $secret, 'events' => array_values(array_unique($data['events']))]);

        // the signing secret is shown exactly once
        return response()->json($this->present($hook) + ['secret' => $secret], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $hook = $request->user()->webhooks()->findOrFail($id);
        $data = $request->validate([
            'url' => ['sometimes', 'string', 'max:500', 'url:https,http'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(Webhook::EVENTS)],
            'active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['url'])) {
            $this->assertSafe($data['url']);
        }
        if (isset($data['events'])) {
            $data['events'] = array_values(array_unique($data['events']));
        }
        // switching it on again clears the automatic shut-off
        if (($data['active'] ?? false) === true) {
            $data += ['disabled_at' => null, 'failures' => 0];
        }
        $hook->update($data);

        return response()->json($this->present($hook->refresh(), true));
    }

    public function rotate(Request $request, int $id): JsonResponse
    {
        $hook = $request->user()->webhooks()->findOrFail($id);
        $secret = 'whsec_'.Str::random(40);
        $hook->update(['secret' => $secret]);

        return response()->json(['secret' => $secret]);
    }

    public function ping(Request $request, int $id, Webhooks $webhooks): JsonResponse
    {
        $hook = $request->user()->webhooks()->findOrFail($id);
        $payload = $webhooks->payload($request->user(), 'ping', ['message' => 'This is a test delivery.']);
        $webhooks->queue($hook, $payload);

        return response()->json(['message' => 'queued']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->webhooks()->findOrFail($id)->delete();

        return response()->json(['message' => 'ok']);
    }

    private function assertSafe(string $url): void
    {
        try {
            WebhookUrl::check($url);
        } catch (RuntimeException $e) {
            abort(422, __('app.webhook_url'));
        }
    }

    private function present(Webhook $w, bool $withLog = false): array
    {
        $out = $w->only(['id', 'url', 'events', 'active', 'failures', 'last_status', 'last_delivery_at', 'disabled_at']);
        if ($withLog) {
            $out['deliveries'] = $w->deliveries()->latest('id')->limit(10)->get(['id', 'event', 'status', 'attempts', 'error', 'delivered_at', 'created_at']);
        }

        return $out;
    }
}
