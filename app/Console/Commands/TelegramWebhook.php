<?php

namespace App\Console\Commands;

use App\Services\TelegramVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {url? : Public HTTPS URL (defaults to this app\'s webhook route)} {--delete : Remove the webhook}';

    protected $description = 'Register (or remove) the Telegram bot webhook used for account verification';

    public function handle(): int
    {
        if (! TelegramVerifier::enabled()) {
            $this->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_BOT_USERNAME first.');

            return self::FAILURE;
        }
        $base = 'https://api.telegram.org/bot'.config('verification.telegram.bot_token');

        if ($this->option('delete')) {
            $res = Http::timeout(15)->post("$base/deleteWebhook");
        } else {
            $secret = (string) config('verification.telegram.webhook_secret');
            if (! preg_match('/^[A-Za-z0-9_-]{16,256}$/', $secret)) {
                $this->error('Set TELEGRAM_WEBHOOK_SECRET to 16+ chars of A-Z a-z 0-9 _ - (e.g. php artisan tinker --execute="echo Str::random(40);").');

                return self::FAILURE;
            }
            $url = $this->argument('url') ?: route('telegram.webhook');
            if (! str_starts_with($url, 'https://')) {
                $this->error("Telegram only accepts HTTPS webhooks (got: $url). For local development use: php artisan telegram:poll");

                return self::FAILURE;
            }
            $res = Http::timeout(15)->post("$base/setWebhook", ['url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['message']]);
        }

        $this->line($res->body());

        return $res->successful() && ($res->json('ok') === true) ? self::SUCCESS : self::FAILURE;
    }
}
