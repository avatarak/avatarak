<?php

namespace App\Services;

use App\Mail\BillingNotice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Support\Facades\Mail;

class BillingNotices
{
    public static function send(User $user, string $kind, Subscription $sub, ?Payment $payment = null): void
    {
        try {
            $fa = app()->getLocale() !== 'en';
            $plan = PlanService::plans()[$sub->plan]['name'][$fa ? 'fa' : 'en'] ?? $sub->plan;
            $to = UserEmail::where('user_id', $user->id)->where('is_primary', true)->whereNotNull('verified_at')->value('email') ?: $user->email;

            Mail::to($to)->send(new BillingNotice($kind, [
                'plan_name' => $plan, 'until' => $sub->ends_at->format('Y-m-d'),
                'amount' => $payment ? number_format($payment->amount).' '.($payment->currency === 'IRT' ? ($fa ? 'تومان' : 'Toman') : $payment->currency) : '',
                'ref_id' => $payment?->ref_id ?? '',
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
