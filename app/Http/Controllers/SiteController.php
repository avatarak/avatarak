<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->sites()->latest()->get()
            ->map(fn (Site $s) => $s->only(['id', 'name', 'domain', 'api_key_prefix', 'plan', 'last_used_at']))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'domain' => ['required', 'string', 'max:190', 'regex:/^(?=.{1,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i'],
        ]);

        abort_if($request->user()->sites()->count() >= 10, 422, __('app.max_sites'));

        [$site, $key] = Site::createWithKey([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'domain' => strtolower($data['domain']),
        ]);

        // the plain key is returned exactly once
        return response()->json($site->only(['id', 'name', 'domain', 'api_key_prefix', 'plan']) + ['api_key' => $key], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->sites()->findOrFail($id)->delete();

        return response()->json(['message' => 'ok']);
    }
}
