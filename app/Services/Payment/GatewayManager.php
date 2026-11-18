<?php

namespace App\Services\Payment;

class GatewayManager
{
    public static function enabled(): bool
    {
        return self::driver() !== null;
    }

    public static function driver(): ?PaymentGateway
    {
        return match (config('billing.gateway')) {
            'zarinpal' => filled(config('billing.zarinpal.merchant_id')) ? new ZarinPalGateway : null,
            'fake' => app()->isProduction() ? null : new FakeGateway,       // a pretend gateway must never take "payments" in production
            default => null,
        };
    }
}
