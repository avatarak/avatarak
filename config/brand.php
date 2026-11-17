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

    'colors' => [
        'canvas' => env('BRAND_COLOR_CANVAS', '#F8F7F4'),
        'surface' => env('BRAND_COLOR_SURFACE', '#FFFFFF'),
        'ink' => env('BRAND_COLOR_INK', '#20242A'),
        'brand' => env('BRAND_COLOR_PRIMARY', '#3563E9'),
        'line' => env('BRAND_COLOR_LINE', '#E7E9ED'),
    ],
];
