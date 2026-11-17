<?php

/*
 | White-label design tokens. Change these (via .env) to rebrand the whole UI;
 | layout and UX stay the same. Colors must be #RRGGBB.
 */
return [
    'name' => env('BRAND_NAME', 'گراواتار'),
    'name_en' => env('BRAND_NAME_EN', 'Iravatar'),
    'logo_url' => env('BRAND_LOGO_URL'),            // null = built-in monogram
    'tagline' => env('BRAND_TAGLINE', 'یک عکس، یک هویت، همه‌جا'),
    'tagline_en' => env('BRAND_TAGLINE_EN', 'One photo, one identity, everywhere'),
    'default_locale' => env('BRAND_LOCALE', 'fa'),   // fa | en

    // Leave unset to use the design defaults in resources/css/app.css (Gravatar-like blue).
    // Set any of these in .env (as #RRGGBB) to rebrand; invalid values are ignored.
    'colors' => [
        'canvas' => env('BRAND_COLOR_CANVAS'),
        'surface' => env('BRAND_COLOR_SURFACE'),
        'ink' => env('BRAND_COLOR_INK'),
        'brand' => env('BRAND_COLOR_PRIMARY'),
        'line' => env('BRAND_COLOR_LINE'),
    ],
];
