<?php

namespace App\Console\Commands;

use App\Services\TelegramVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll
        {--once : Handle pending updates once and exit}
        {--timeout=8 : Seconds Telegram holds each request open waiting for messages (lower it if your VPN/network drops idle connections; 0 = no waiting)}';

    protected $description = 'Receive bot updates by long polling (for local development, no public URL needed)';

    public function handle(TelegramVerifier $telegram): int
    {
        if (! TelegramVerifier::enabled()) {
            $this->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_BOT_USERNAME first.');

            return self::FAILURE;
        }
        $base = 'https://api.telegram.org/bot'.config('verification.telegram.bot_token');
        try {
            Http::timeout(15)->post("$base/deleteWebhook");      // polling and webhooks are mutually exclusive
        } catch (\Throwable $e) {
            $this->warn('Could not reach Telegram yet: '.$this->redact($e->getMessage()));
        }

        $wait = $this->option('once') ? 0 : max(0, min(50, (int) $this->option('timeout')));
        $offset = 0;
        $this->info('Listening for Telegram updates… (Ctrl+C to stop)');
        do {
            try {
                $res = Http::timeout($wait + 12)->connectTimeout(8)->get("$base/getUpdates", ['offset' => $offset, 'timeout' => $wait, 'allowed_updates' => json_encode(['message'])]);
            } catch (\Throwable $e) {
                $this->warn('Telegram unreachable: '.$this->redact($e->getMessage()));
                $this->line('If other requests to Telegram work, your network is probably dropping the idle long-poll connection: try --timeout=3. If nothing reaches Telegram, run through a VPN/proxy (HTTPS_PROXY=http://127.0.0.1:PORT).');
                if ($this->option('once')) {
                    return self::FAILURE;
                }
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

    /** Exception messages contain the request URL, which contains the bot token. Never print that. */
    private function redact(string $message): string
    {
        $token = (string) config('verification.telegram.bot_token');
        $message = $token !== '' ? str_replace($token, '[token]', $message) : $message;

        return preg_replace('#/bot\d+:[A-Za-z0-9_-]+#', '/bot[token]', $message);
    }
}
