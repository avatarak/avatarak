<?php

return [
    // Where customers point their domain: a CNAME to this host (or an A record to your server's IP).
    // Defaults to the host of APP_URL; for a clean setup use a dedicated name like domains.example.com.
    'target' => env('CUSTOM_DOMAIN_TARGET') ?: parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),

    // Shared secret for /_internal/domain-check (used by Caddy's on-demand TLS "ask" URL). Empty = endpoint off.
    'check_key' => env('DOMAIN_CHECK_KEY'),

    // Verification codes stop being checked after this many tries (cheap DNS lookups, but not unlimited).
    'max_checks' => 60,
];
