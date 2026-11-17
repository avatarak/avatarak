<?php

return [
    // Fetch a preview image (og:image) for each profile link and store it on our own disk.
    'enabled' => (bool) env('LINK_PREVIEWS', true),

    // Connect to the exact IP we validated (defeats DNS rebinding). Turn off only when outbound traffic
    // goes through an HTTP proxy, which does its own DNS.
    'pin_dns' => (bool) env('LINK_PREVIEWS_PIN_DNS', true),

    // NEVER enable in production: allows private/loopback targets (only useful in tests/local demos).
    'allow_private' => (bool) env('LINK_PREVIEWS_ALLOW_PRIVATE', false),

    'timeout' => 6,
    'max_html_bytes' => 524288,       // 512 KB
    'max_image_bytes' => 3145728,     // 3 MB
    'max_redirects' => 3,
    'thumb_width' => 470,
    'thumb_height' => 264,
];
