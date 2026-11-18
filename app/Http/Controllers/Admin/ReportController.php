<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public static function present(Report $r): array
    {
        $p = $r->profile;

        return [
            'id' => $r->id, 'reason' => $r->reason, 'details' => $r->details, 'status' => $r->status, 'created_at' => $r->created_at?->toIso8601String(),
            'handled_at' => $r->handled_at?->toIso8601String(), 'note' => $r->note, 'handled_by' => $r->handler?->email,
            'profile' => $p ? [
                'id' => $p->id, 'user_id' => $p->user_id, 'username' => $p->username, 'display' => $p->name_fa ?: ($p->name_en ?: $p->username),
                'has_avatar' => (bool) $p->avatar_path, 'avatar_version' => $p->avatar_version, 'suspended' => (bool) $p->user?->isSuspended(),
            ] : null,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'open');
        $query = Report::with(['profile.user', 'handler'])->orderByDesc('id');
        if (in_array($status, ['open', 'actioned', 'dismissed'], true)) {
            $query->where('status', $status);
        }
        if ($reason = $request->query('reason')) {
            $query->where('reason', $reason);
        }

        $page = $query->paginate(20);
        $openByProfile = Report::where('status', 'open')->whereIn('profile_id', $page->pluck('profile_id'))->selectRaw('profile_id, count(*) as c')->groupBy('profile_id')->pluck('c', 'profile_id');

        return response()->json([
            'data' => $page->getCollection()->map(fn (Report $r) => self::present($r) + ['open_for_profile' => (int) ($openByProfile[$r->profile_id] ?? 0)])->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'counts' => ['open' => Report::where('status', 'open')->count(), 'actioned' => Report::where('status', 'actioned')->count(), 'dismissed' => Report::where('status', 'dismissed')->count()],
        ]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:actioned,dismissed'], 'note' => ['nullable', 'string', 'max:500'], 'all_for_profile' => ['sometimes', 'boolean']]);
        $r = Report::with('profile')->findOrFail($id);

        $q = ($data['all_for_profile'] ?? false) ? Report::where('profile_id', $r->profile_id)->where('status', 'open') : Report::whereKey($r->id);
        $n = $q->update(['status' => $data['status'], 'note' => $data['note'] ?? null, 'handled_by' => $request->user()->id, 'handled_at' => now()]);

        AuditLog::record('report.'.$data['status'], $r, ['count' => $n, 'note' => $data['note'] ?? null, 'profile' => $r->profile?->username]);

        return response()->json(['updated' => $n]);
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $r = Report::findOrFail($id);
        $r->update(['status' => 'open', 'handled_by' => null, 'handled_at' => null]);
        AuditLog::record('report.reopen', $r);

        return response()->json(['updated' => 1]);
    }
}
