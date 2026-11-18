<?php

return [
    // Master switch (plan limits still decide how many endpoints a person may add).
    'enabled' => (bool) env('WEBHOOKS_ENABLED', true),
    'timeout' => (int) env('WEBHOOKS_TIMEOUT', 8),
    // https only, public addresses only. Turn on for local development / tests.
    'allow_private' => (bool) env('WEBHOOKS_ALLOW_PRIVATE', false),
];
