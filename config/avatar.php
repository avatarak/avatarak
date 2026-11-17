<?php

return [
    // Disk holding the original uploaded avatars (path stored in profiles.avatar_path)
    'disk' => env('AVATAR_DISK', 'local'),

    // Cache store for rendered avatars. null = default cache store (use "redis" in production)
    'cache_store' => env('AVATAR_CACHE_STORE'),
    'cache_ttl' => (int) env('AVATAR_CACHE_TTL', 86400),

    // Browser/CDN cache lifetime in seconds
    'max_age' => (int) env('AVATAR_MAX_AGE', 3600),
    'default_max_age' => (int) env('AVATAR_DEFAULT_MAX_AGE', 300),

    'default_size' => 80,
    'min_size' => 16,
    'max_size' => 512,
    'quality' => 85,
];
