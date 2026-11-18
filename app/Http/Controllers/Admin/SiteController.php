<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    private static function present(Site $s): array
    {
        return [
            'id' => $s->id, 'name' => $s->name, 'domain' => $s->domain, 'prefix' => $s->api_key_prefix, 'rate_limit' => $s->effectiveRateLimit(), 'rate_limit_override' => $s->rate_limit_per_minute !== null,
            'plan' => $s->plan, 'disabled' => $s->disabled_at !== null, 'owner_suspended' => (bool) $s->user?->isSuspended(),
            'last_used_at' => $s->last_used_at?->toIso8601String(), 'created_at' => $s->created_at?->toIso8601String(),
            'owner' => ['id' => $s->user?->id, 'email' => $s->user?->email],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Site::with('user')->orderByDesc('id');
        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $query->where(fn ($w) => $w->whereRaw('lower(domain) like ?', [$like])->orWhereRaw('lower(name) like ?', [$like])->orWhere('api_key_prefix', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->whereRaw('lower(email) like ?', [$like])));
        }
        match ((string) $request->query('status')) {
            'disabled' => $query->whereNotNull('disabled_at'),
            'active' => $query->whereNull('disabled_at')->where('last_used_at', '>=', now()->subDays(7)),
            'idle' => $query->whereNull('disabled_at')->where(fn ($w) => $w->whereNull('last_used_at')->orWhere('last_used_at', '<', now()->subDays(30))),
            default => null,
        };

        $page = $query->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Site $s) => self::present($s))->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['rate_limit_per_minute' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'], 'disabled' => ['sometimes', 'boolean']]);
        $s = Site::with('user')->findOrFail($id);

        $meta = [];
        if (array_key_exists('rate_limit_per_minute', $data)) {                 // null clears the override: back to the plan's limit
            $meta['rate_limit'] = [$s->rate_limit_per_minute, $data['rate_limit_per_minute']];
            $s->rate_limit_per_minute = $data['rate_limit_per_minute'];
        }
        if (isset($data['disabled'])) {
            $s->disabled_at = $data['disabled'] ? now() : null;
            $meta['disabled'] = (bool) $data['disabled'];
        }
        $s->save();
        AuditLog::record(isset($data['disabled']) ? ($data['disabled'] ? 'site.disable' : 'site.enable') : 'site.update', $s, $meta);

        return response()->json(self::present($s));
    }

    public function destroy(int $id): JsonResponse
    {
        $s = Site::findOrFail($id);
        AuditLog::record('site.delete', $s, ['owner' => $s->user?->email]);
        $s->delete();

        return response()->json(['message' => 'ok']);
    }
}
