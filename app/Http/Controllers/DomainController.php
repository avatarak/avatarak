<?php

namespace App\Http\Controllers;

use App\Models\CustomDomain;
use App\Services\DomainVerifier;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DomainController extends Controller
{
    private function present(CustomDomain $d, DomainVerifier $v): array
    {
        return ['id' => $d->id, 'host' => $d->host, 'verified' => $d->isVerified(), 'instructions' => $v->instructions($d), 'last_checked_at' => $d->last_checked_at?->toIso8601String()];
    }

    public function index(Request $request, PlanService $plans, DomainVerifier $v): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'allowed' => $plans->limit($u, 'domains'), 'target' => config('domains.target'),
            'domains' => $u->customDomains()->orderBy('id')->get()->map(fn ($d) => $this->present($d, $v))->values(),
        ]);
    }

    public function store(Request $request, PlanService $plans, DomainVerifier $v): JsonResponse
    {
        $u = $request->user();
        $host = DomainVerifier::normalize((string) $request->validate(['host' => ['required', 'string', 'max:253']])['host']);
        abort_unless($host, 422, __('app.domain_invalid'));
        abort_unless($plans->limit($u, 'domains') > 0, 403, __('app.domain_plan'));

        $existing = CustomDomain::where('host', $host)->first();
        abort_if($existing && ($existing->user_id !== $u->id), 422, __('app.domain_taken'));          // a verified OR pending claim by someone else blocks it
        if (! $existing) {
            abort_if($u->customDomains()->count() >= $plans->limit($u, 'domains'), 422, __('app.domain_limit'));
            $existing = CustomDomain::create(['user_id' => $u->id, 'host' => $host, 'token' => Str::random(32)]);
        }
        $d = $existing;

        return response()->json($this->present($d, $v), 201);
    }

    public function check(Request $request, int $id, DomainVerifier $v): JsonResponse
    {
        $d = $request->user()->customDomains()->findOrFail($id);
        $r = $v->check($d);

        return response()->json($r + ['domain' => $this->present($d->fresh(), $v)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->customDomains()->findOrFail($id)->delete();

        return response()->json(['message' => 'ok']);
    }

    /** Caddy's on-demand TLS asks this before it gets a certificate: only for domains we really serve. */
    public function tlsCheck(Request $request, PlanService $plans)
    {
        $key = (string) config('domains.check_key');
        abort_unless($key !== '' && hash_equals($key, (string) $request->query('key')), 404);

        $host = DomainVerifier::normalize((string) $request->query('domain'));
        $d = $host ? CustomDomain::with('user')->where('host', $host)->whereNotNull('verified_at')->first() : null;
        abort_unless($d && $d->user && ! $d->user->isSuspended() && $plans->can($d->user, 'custom_domain'), 404);

        return response('ok', 200, ['Cache-Control' => 'no-store']);
    }
}
