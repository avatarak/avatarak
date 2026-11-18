<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->sites()->latest('id')->get()
            ->map(fn (Site $s) => $s->only(['id', 'name', 'domain', 'api_key_prefix', 'plan', 'last_used_at']) + ['scopes' => $s->scopes ?? Site::SCOPES])->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'domain' => ['required', 'string', 'max:190', 'regex:/^(?=.{1,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i'],
            'scopes' => ['sometimes', 'array', 'min:1'],
            'scopes.*' => ['string', \Illuminate\Validation\Rule::in(Site::SCOPES)],
        ]);

        abort_if($request->user()->sites()->count() >= app(\App\Services\PlanService::class)->limit($request->user(), 'sites'), 422, __('app.plan_limit_sites'));

        [$site, $key] = Site::createWithKey([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'domain' => strtolower($data['domain']),
            'scopes' => isset($data['scopes']) ? array_values(array_unique($data['scopes'])) : null,
        ]);

        // the plain key is returned exactly once
        return response()->json($site->only(['id', 'name', 'domain', 'api_key_prefix', 'plan']) + ['scopes' => $site->scopes ?? Site::SCOPES, 'api_key' => $key], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->sites()->findOrFail($id)->delete();

        return response()->json(['message' => 'ok']);
    }
}
