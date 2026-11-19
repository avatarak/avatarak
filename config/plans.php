<?php

/*
 | Plans and their limits. Prices are in TOMAN per period (set 0 / leave the gateway off to run everything free).
 | "free" is what everybody gets without paying. Change numbers here, or override the prices from .env.
 */
return [
    'currency' => env('BILLING_CURRENCY', 'IRT'),

    // Days added by one purchase.
    'periods' => ['month' => 30, 'year' => 365],

    'plans' => [
        'free' => [
            'rank' => 0,
            'name' => ['fa' => 'رایگان', 'en' => 'Free'],
            'tagline' => ['fa' => 'برای افراد و سایت‌های کوچک', 'en' => 'For people and small sites'],
            'price' => ['month' => 0, 'year' => 0],
            'limits' => ['sites' => 3, 'rate_limit' => 600, 'gallery' => 3, 'sections' => 2, 'webhooks' => 0, 'domains' => 1],
            'features' => ['custom_domain' => true, 'white_label' => false],
        ],
        'pro' => [
            'rank' => 1,
            'name' => ['fa' => 'حرفه‌ای', 'en' => 'Pro'],
            'tagline' => ['fa' => 'برای فروشگاه‌ها و وب‌سایت‌های فعال', 'en' => 'For shops and busy sites'],
            'price' => ['month' => (int) env('PLAN_PRO_PRICE_MONTH', 150000), 'year' => (int) env('PLAN_PRO_PRICE_YEAR', 1500000)],
            'limits' => ['sites' => 25, 'rate_limit' => 6000, 'gallery' => 12, 'sections' => 6, 'webhooks' => 3, 'domains' => 3],
            'features' => ['custom_domain' => true, 'white_label' => false],
        ],
        'business' => [
            'rank' => 2,
            'name' => ['fa' => 'سازمانی', 'en' => 'Business'],
            'tagline' => ['fa' => 'وایت‌لیبل و سقف‌های بالا', 'en' => 'White-label and high limits'],
            'price' => ['month' => (int) env('PLAN_BUSINESS_PRICE_MONTH', 600000), 'year' => (int) env('PLAN_BUSINESS_PRICE_YEAR', 6000000)],
            'limits' => ['sites' => 200, 'rate_limit' => 60000, 'gallery' => 30, 'sections' => 12, 'webhooks' => 20, 'domains' => 5],
            'features' => ['custom_domain' => true, 'white_label' => true],
        ],
    ],
];
