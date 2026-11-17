<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationChallenge;
use App\Models\VerifiedAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Telegram ownership via a bot: the user opens t.me/<bot>?start=<token> and presses Start; Telegram
 * delivers "/start <token>" together with the sender's real id and username, which we trust because it
 * comes from Telegram (webhook secret / our own getUpdates call), not from the user.
 *
 * The verified record is keyed by the numeric Telegram id: usernames can be released and re-registered
 * by someone else, ids cannot.
 */
class TelegramVerifier
{
    public static function enabled(): bool
    {
        return filled(config('verification.telegram.bot_token')) && filled(config('verification.telegram.bot_username'));
    }

    /** "@Some_User", "https://t.me/some_user" -> "some_user"; null if not a valid Telegram username. */
    public static function normalizeHandle(string $input): ?string
    {
        $h = trim($input);
        $h = preg_replace('#^(https?://)?(www\.)?(t\.me|telegram\.me)/#i', '', $h);
        $h = strtolower(ltrim(explode('?', $h)[0], '@'));

        return preg_match('/^[a-z][a-z0-9_]{4,31}$/', $h) ? $h : null;
    }

    public function link(VerificationChallenge $c): string
    {
        return 'https://t.me/'.config('verification.telegram.bot_username').'?start='.$c->token;
    }

    /** Handle one Telegram update (from the webhook or from polling). */
    public function handleUpdate(array $update): void
    {
        $msg = $update['message'] ?? null;
        $text = is_array($msg) ? trim((string) ($msg['text'] ?? '')) : '';
        $from = $msg['from'] ?? null;
        $chatId = $msg['chat']['id'] ?? null;

        if (! $msg || ($msg['chat']['type'] ?? '') !== 'private' || ! is_array($from) || ! $chatId
            || ! preg_match('/^\/start(?:@\w+)?\s+([A-Za-z0-9_-]{16,64})$/', $text, $m)) {
            if ($chatId && ($msg['chat']['type'] ?? '') === 'private' && str_starts_with($text, '/start')) {
                $this->reply($chatId, 'hello', $from['language_code'] ?? null);
            }

            return;
        }

        $lang = $from['language_code'] ?? null;
        $challenge = VerificationChallenge::where('network', 'telegram')->where('token', $m[1])->first();

        if (! $challenge || $challenge->expired()) {
            $this->reply($chatId, 'invalid', $lang);

            return;
        }
        if (! empty($from['is_bot']) || empty($from['username']) || empty($from['id'])) {
            $this->reply($chatId, 'no_username', $lang);

            return;
        }
        if (strtolower($from['username']) !== $challenge->identifier) {
            $this->reply($chatId, 'mismatch', $lang, ['expected' => $challenge->identifier, 'got' => $from['username']]);

            return;
        }

        $result = DB::transaction(function () use ($challenge, $from) {
            $id = (string) $from['id'];
            $username = strtolower($from['username']);

            $takenBy = VerifiedAccount::where('network', 'telegram')
                ->where(fn ($q) => $q->where('external_id', $id)->orWhere('identifier', $username))
                ->where('user_id', '!=', $challenge->user_id)->lockForUpdate()->exists();
            if ($takenBy) {
                return 'taken';
            }

            // same user re-verifying (e.g. renamed username) replaces their previous telegram record
            VerifiedAccount::where('network', 'telegram')->where('user_id', $challenge->user_id)->delete();
            VerifiedAccount::create([
                'user_id' => $challenge->user_id, 'network' => 'telegram', 'identifier' => $username,
                'external_id' => $id, 'method' => 'telegram_bot', 'verified_at' => now(),
            ]);
            $challenge->delete();

            return 'ok';
        });

        $this->reply($chatId, $result === 'ok' ? 'verified' : 'taken', $lang);
    }

    private function reply(int|string $chatId, string $key, ?string $lang, array $vars = []): void
    {
        if (! self::enabled()) {
            return;
        }
        $fa = str_starts_with((string) $lang, 'fa');
        $texts = [
            'verified' => [$fa ? '✅ حساب تلگرام شما تأیید شد. می‌توانید به پروفایل برگردید.' : '✅ Your Telegram account is verified. You can go back to your profile.'],
            'invalid' => [$fa ? 'این لینک نامعتبر یا منقضی است. از پنل دوباره شروع کنید.' : 'This link is invalid or expired. Start again from your dashboard.'],
            'taken' => [$fa ? 'این حساب تلگرام قبلاً برای کاربر دیگری تأیید شده است.' : 'This Telegram account is already verified for another user.'],
            'no_username' => [$fa ? 'حساب شما نام کاربری ندارد. در تنظیمات تلگرام یک username بگذارید و دوباره امتحان کنید.' : 'Your account has no username. Set one in Telegram settings and try again.'],
            'mismatch' => [$fa
                ? 'این حساب @'.($vars['got'] ?? '').' است ولی در پنل @'.($vars['expected'] ?? '').' را وارد کرده‌اید.'
                : 'You are @'.($vars['got'] ?? '').' but your dashboard says @'.($vars['expected'] ?? '').'.'],
            'hello' => [$fa ? 'برای تأیید حساب، از پنل روی «تأیید با تلگرام» بزنید.' : 'To verify your account, press “Verify with Telegram” in your dashboard.'],
        ];

        try {
            Http::timeout(5)->post('https://api.telegram.org/bot'.config('verification.telegram.bot_token').'/sendMessage', [
                'chat_id' => $chatId, 'text' => $texts[$key][0] ?? '',
            ]);
        } catch (\Throwable) {
            // a failed reply must never undo or block a verification
        }
    }
}
