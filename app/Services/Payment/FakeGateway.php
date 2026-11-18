<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

/** For trying the whole flow on a development machine: "pays" instantly. Never available in production. */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function start(Payment $payment, User $user, string $callbackUrl): string
    {
        $authority = 'FAKE'.Str::upper(Str::random(20));
        $payment->forceFill(['authority' => $authority])->save();

        return $callbackUrl.(str_contains($callbackUrl, '?') ? '&' : '?').http_build_query(['Authority' => $authority, 'Status' => 'OK']);
    }

    public function verify(Payment $payment, array $query): array
    {
        return ($query['Status'] ?? '') === 'OK' && ($query['Authority'] ?? '') === $payment->authority
            ? ['ok' => true, 'ref_id' => 'TEST-'.$payment->id, 'card_pan' => '603799******9999'] : ['ok' => false];
    }
}
