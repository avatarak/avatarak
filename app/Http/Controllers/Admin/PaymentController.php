<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with('user')->orderByDesc('id');
        if (in_array($s = (string) $request->query('status'), ['pending', 'paid', 'failed'], true)) {
            $query->where('status', $s);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $query->where(fn ($w) => $w->whereRaw('lower(ref_id) like ?', [$like])->orWhereHas('user', fn ($u) => $u->whereRaw('lower(email) like ?', [$like])));
        }
        $page = $query->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Payment $p) => [
                'id' => $p->id, 'plan' => $p->plan, 'period' => $p->period, 'amount' => $p->amount, 'currency' => $p->currency, 'gateway' => $p->gateway,
                'status' => $p->status, 'ref_id' => $p->ref_id, 'card_pan' => $p->card_pan, 'paid_at' => $p->paid_at?->toIso8601String(), 'created_at' => $p->created_at?->toIso8601String(),
                'user' => ['id' => $p->user?->id, 'email' => $p->user?->email],
            ])->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'totals' => ['paid_amount' => (int) Payment::where('status', 'paid')->sum('amount'), 'paid_count' => Payment::where('status', 'paid')->count()],
        ]);
    }
}
