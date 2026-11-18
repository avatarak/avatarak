<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\VerifiedAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifiedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VerifiedAccount::with('user.profile')->orderByDesc('id');
        if (in_array($n = (string) $request->query('network'), ['website', 'github', 'telegram'], true)) {
            $query->where('network', $n);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $query->where(fn ($w) => $w->whereRaw('lower(identifier) like ?', [$like])->orWhereHas('user', fn ($u) => $u->whereRaw('lower(email) like ?', [$like])));
        }

        $page = $query->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (VerifiedAccount $a) => [
                'id' => $a->id, 'network' => $a->network, 'identifier' => $a->identifier, 'method' => $a->method, 'url' => $a->url(), 'verified_at' => $a->verified_at?->toIso8601String(),
                'user' => ['id' => $a->user?->id, 'email' => $a->user?->email, 'username' => $a->user?->profile?->username],
            ])->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $a = VerifiedAccount::findOrFail($id);
        AuditLog::record('verified.revoke', $a, ['owner' => $a->user?->email]);
        $a->delete();

        return response()->json(['message' => 'ok']);
    }
}
