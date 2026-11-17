<?php

namespace App\Console\Commands;

use App\Services\TelegramVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll {--once : Handle pending updates once and exit}';

    protected $description = 'Receive bot updates by long polling (for local development, no public URL needed)';

    public function handle(TelegramVerifier $telegram): int
    {
        if (! TelegramVerifier::enabled()) {
            $this->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_BOT_USERNAME first.');

            return self::FAILURE;
        }
        $base = 'https://api.telegram.org/bot'.config('verification.telegram.bot_token');
        Http::timeout(15)->post("$base/deleteWebhook");          // polling and webhooks are mutually exclusive

        $offset = 0;
        $this->info('Listening for Telegram updates… (Ctrl+C to stop)');
        do {
            try {
                $res = Http::timeout(35)->get("$base/getUpdates", ['offset' => $offset, 'timeout' => $this->option('once') ? 0 : 25, 'allowed_updates' => json_encode(['message'])]);
            } catch (\Throwable $e) {
                $this->warn('Telegram unreachable: '.$e->getMessage());
                sleep(3);

                continue;
            }
            foreach ($res->json('result') ?? [] as $update) {
                $offset = max($offset, ($update['update_id'] ?? 0) + 1);
                $telegram->handleUpdate($update);
                $this->line('handled update '.($update['update_id'] ?? '?'));
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
