<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::orderByDesc('id');
        if ($a = trim((string) $request->query('action', ''))) {
            $query->where('action', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $a).'%');
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $query->where(fn ($w) => $w->whereRaw('lower(admin_label) like ?', [$like])->orWhereRaw("lower(coalesce(target_label, '')) like ?", [$like]));
        }

        $page = $query->paginate(30);

        return response()->json([
            'data' => $page->getCollection()->map(fn (AuditLog $l) => [
                'id' => $l->id, 'action' => $l->action, 'admin' => $l->admin_label, 'target_type' => $l->target_type, 'target_id' => $l->target_id,
                'target' => $l->target_label, 'meta' => $l->meta, 'at' => $l->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
