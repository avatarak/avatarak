<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\User;

interface PaymentGateway
{
    public function name(): string;

    /** Create the gateway-side transaction and return the URL to send the customer to. Sets $payment->authority. */
    public function start(Payment $payment, User $user, string $callbackUrl): string;

    /**
     * Ask the gateway whether this payment was really made. Never trusts the browser: the amount comes from OUR row.
     *
     * @param  array<string,mixed>  $query  what the gateway put on the callback URL
     * @return array{ok:bool,ref_id?:string,card_pan?:string}
     */
    public function verify(Payment $payment, array $query): array;
}
