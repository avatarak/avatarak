<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\BillingNotices;
use App\Services\Payment\GatewayManager;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /** Everything the "Plan & billing" screen needs. */
    public function status(Request $request, PlanService $plans): JsonResponse
    {
        $u = $request->user();
        $active = $plans->active($u);
        $key = $plans->planKey($u);

        $catalog = collect(PlanService::plans())->map(fn ($p, $k) => [
            'key' => $k, 'rank' => $p['rank'], 'name' => $p['name'], 'tagline' => $p['tagline'], 'price' => $p['price'], 'limits' => $p['limits'], 'features' => $p['features'],
        ])->values();

        return response()->json([
            'enabled' => GatewayManager::enabled(), 'currency' => config('plans.currency'), 'contact_email' => config('billing.contact_email'),
            'current' => ['plan' => $key, 'ends_at' => $active?->ends_at?->toIso8601String(), 'source' => $active?->source],
            'usage' => ['sites' => $u->sites()->count()],
            'plans' => $catalog,
            'payments' => $u->payments()->latest('id')->limit(20)->get()->map(fn (Payment $p) => [
                'id' => $p->id, 'plan' => $p->plan, 'period' => $p->period, 'amount' => $p->amount, 'currency' => $p->currency, 'status' => $p->status,
                'ref_id' => $p->ref_id, 'paid_at' => $p->paid_at?->toIso8601String(), 'created_at' => $p->created_at?->toIso8601String(),
            ])->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Start paying: the AMOUNT is decided here from the config, never taken from the browser. */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate(['plan' => ['required', 'string'], 'period' => ['required', 'in:month,year']]);
        $gateway = GatewayManager::driver();
        abort_unless($gateway, 503, __('app.billing_off'));
        abort_unless(PlanService::exists($data['plan']) && $data['plan'] !== 'free' && PlanService::price($data['plan'], $data['period']) > 0, 422, __('app.billing_bad_plan'));

        $payment = Payment::create([
            'user_id' => $request->user()->id, 'plan' => $data['plan'], 'period' => $data['period'], 'amount' => PlanService::price($data['plan'], $data['period']),
            'currency' => config('plans.currency'), 'gateway' => $gateway->name(), 'status' => 'pending',
        ]);

        try {
            $url = $gateway->start($payment, $request->user(), route('billing.callback'));
        } catch (\Throwable $e) {
            report($e);
            $payment->update(['status' => 'failed']);
            abort(502, __('app.billing_failed'));
        }

        return response()->json(['url' => $url]);
    }

    /** The gateway sends the customer back here. We verify with the gateway before granting anything. */
    public function callback(Request $request, PlanService $plans)
    {
        $authority = (string) $request->query('Authority', '');
        $payment = strlen($authority) > 5 ? Payment::where('authority', $authority)->first() : null;
        $gateway = GatewayManager::driver();
        if (! $payment || ! $gateway || $payment->gateway !== $gateway->name()) {
            return redirect('/?billing=failed');
        }
        if ($payment->status === 'paid') {
            return redirect('/?billing=ok');                       // a refresh of the callback page
        }

        $result = $gateway->verify($payment, $request->query());
        if (! $result['ok']) {
            if ($payment->status === 'pending') {
                $payment->update(['status' => 'failed']);
            }

            return redirect('/?billing=failed');
        }

        $sub = DB::transaction(function () use ($payment, $plans, $result) {
            $locked = Payment::lockForUpdate()->find($payment->id);
            if ($locked->status === 'paid') {
                return null;
            }
            $locked->forceFill(['status' => 'paid', 'ref_id' => $result['ref_id'] ?? null, 'card_pan' => $result['card_pan'] ?? null, 'paid_at' => now()])->save();

            return $plans->grant($locked->user, $locked->plan, PlanService::days($locked->period), 'payment', $locked->id);
        });

        if ($sub) {
            BillingNotices::send($payment->user, 'receipt', $sub, $payment->fresh());
        }

        return redirect('/?billing=ok');
    }
}
