<?php

return [
    // none   = payments off: nobody can buy, the plans page says "contact us" (admins can still grant plans)
    // zarinpal = ZarinPal (https://zarinpal.com), the usual Iranian gateway
    // fake   = LOCAL TESTING ONLY: pretends the payment succeeded. Refused when APP_ENV=production.
    'gateway' => env('BILLING_GATEWAY', 'none'),

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox' => (bool) env('ZARINPAL_SANDBOX', false),
    ],

    // How many days before a plan ends the reminder email is sent.
    'remind_days' => 7,

    // Shown when payments are off.
    'contact_email' => env('BILLING_CONTACT_EMAIL', env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS'))),
];
