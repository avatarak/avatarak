<?php

return [
    // Your site's API key (avk_...), made in the Avatarak dashboard under Developers. Needed for profile lookups only;
    // avatar URLs work without it.
    'key' => env('AVATARAK_KEY'),

    // Change this only if you host Avatarak yourself.
    'base_url' => env('AVATARAK_URL', 'https://avatarak.ir'),

    'timeout' => (int) env('AVATARAK_TIMEOUT', 8),
];
