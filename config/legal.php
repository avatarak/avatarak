<?php

return [
    // Who runs this service and how to reach them. Shown in the Terms and Privacy Policy.
    'entity' => env('LEGAL_ENTITY', env('BRAND_NAME_EN', 'Avatarak.ir')),
    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@example.com')),

    // Sign-up needs a ticked "I accept the Terms and Privacy Policy" box.
    'require_acceptance' => (bool) env('LEGAL_REQUIRE_ACCEPTANCE', true),

    // Bump this date whenever the texts change materially (shown at the top of both pages).
    'updated' => '2026-11-20',
];
