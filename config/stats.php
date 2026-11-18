<?php

return [
    // Count views of avatars, profiles and exports (aggregated per day; see the privacy docs).
    'enabled' => (bool) env('STATS_ENABLED', true),

    // Days of history kept (older rows are deleted by `php artisan stats:prune`, scheduled daily).
    'retention_days' => (int) env('STATS_RETENTION_DAYS', 90),

    // More distinct referrer hosts than this per user, metric and day are counted under "other".
    'max_hosts_per_day' => 200,
];
