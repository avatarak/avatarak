<?php

return [
    // How long a pending proof (token) stays valid.
    'challenge_ttl_hours' => (int) env('VERIFICATION_TTL_HOURS', 72),
    'max_checks_per_challenge' => 40,

    'telegram' => [
        // Create a bot with @BotFather. Leave the token empty to disable Telegram verification.
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => ltrim((string) env('TELEGRAM_BOT_USERNAME'), '@'),
        // Sent back by Telegram in X-Telegram-Bot-Api-Secret-Token; the webhook rejects anything else.
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'github' => [
        // Gist proof works with no setup. Optional: a token only raises GitHub's anonymous rate limit
        // (60/hour per server IP); an OAuth app (callback: <APP_URL>/account/verifications/github/callback)
        // adds the one-click "Sign in with GitHub" method.
        'api_token' => env('GITHUB_API_TOKEN'),
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
    ],
];
