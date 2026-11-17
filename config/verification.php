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
];
