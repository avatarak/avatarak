<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/** ZarinPal v4 (https://docs.zarinpal.com): request -> redirect to StartPay -> callback -> verify. */
class ZarinPalGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'zarinpal';
    }

    private function base(): string
    {
        return config('billing.zarinpal.sandbox') ? 'https://sandbox.zarinpal.com/pg/v4/payment' : 'https://payment.zarinpal.com/pg/v4/payment';
    }

    private function startPay(): string
    {
        return config('billing.zarinpal.sandbox') ? 'https://sandbox.zarinpal.com/pg/StartPay/' : 'https://payment.zarinpal.com/pg/StartPay/';
    }

    public function start(Payment $payment, User $user, string $callbackUrl): string
    {
        $r = Http::timeout(15)->acceptJson()->asJson()->post($this->base().'/request.json', [
            'merchant_id' => config('billing.zarinpal.merchant_id'), 'amount' => $payment->amount, 'currency' => $payment->currency,
            'description' => 'Avatarak '.$payment->plan.' / '.$payment->period, 'callback_url' => $callbackUrl, 'metadata' => ['email' => $user->email],
        ]);

        $authority = $r->json('data.authority');
        if (! $r->ok() || (int) $r->json('data.code') !== 100 || ! is_string($authority) || ! preg_match('/^[A-Za-z0-9]{10,64}$/', $authority)) {
            throw new \RuntimeException('The payment gateway did not accept the request.');
        }
        $payment->forceFill(['authority' => $authority])->save();

        return $this->startPay().$authority;
    }

    public function verify(Payment $payment, array $query): array
    {
        if (($query['Status'] ?? '') !== 'OK' || ($query['Authority'] ?? '') !== $payment->authority) {
            return ['ok' => false];
        }

        $r = Http::timeout(20)->acceptJson()->asJson()->post($this->base().'/verify.json', [
            'merchant_id' => config('billing.zarinpal.merchant_id'), 'amount' => $payment->amount, 'authority' => $payment->authority,
        ]);

        // 100 = verified now, 101 = already verified (a refresh of the callback page)
        if (! $r->ok() || ! in_array((int) $r->json('data.code'), [100, 101], true)) {
            return ['ok' => false];
        }

        return ['ok' => true, 'ref_id' => (string) $r->json('data.ref_id'), 'card_pan' => (string) $r->json('data.card_pan')];
    }
}
