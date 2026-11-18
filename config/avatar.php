<?php

return [
    // Disk holding the original uploaded avatars (path stored in profiles.avatar_path)
    'disk' => env('AVATAR_DISK', 'local'),

    // Cache store for rendered avatars. null = default cache store (use "redis" in production)
    // `AVATAR_CACHE_STORE=` (empty) in .env must mean "use the default store", not a store named ""
    'cache_store' => env('AVATAR_CACHE_STORE') ?: null,
    'cache_ttl' => (int) env('AVATAR_CACHE_TTL', 86400),

    // Browser/CDN cache lifetime in seconds
    'max_age' => (int) env('AVATAR_MAX_AGE', 3600),
    // Extra lifetime for SHARED caches (CDNs, reverse proxies) via s-maxage, 0 = off. A changed photo keeps its URL,
    // so a shared cache can show the old one for up to this long: keep it short (minutes), not days.
    's_maxage' => (int) env('AVATAR_S_MAXAGE', 0),
    'stale_while_revalidate' => (int) env('AVATAR_STALE_WHILE_REVALIDATE', 0),

    'default_max_age' => (int) env('AVATAR_DEFAULT_MAX_AGE', 300),

    'default_size' => 80,
    'min_size' => 16,
    'max_size' => 512,
    'quality' => 85,
];
