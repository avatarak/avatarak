# Verified accounts

A verified account is one the user has *proved* they own. Today: **website** and **Telegram**.

## Website (no setup needed)

The user enters a domain in the dashboard and publishes the code we give them in any ONE of three ways:

| Method | What to publish |
|---|---|
| meta tag | `<meta name="avatarak-verification" content="TOKEN">` in the home page `<head>` |
| file | `https://DOMAIN/.well-known/avatarak-verification.txt` containing only `TOKEN` |
| DNS | a `TXT` record on `DOMAIN` with the value `avatarak-verification=TOKEN` |

Pages are fetched with the same SSRF-hardened client used for link previews (public IPs only, ports 80/443, redirects re-validated, size/time caps). A redirect to a different domain proves nothing and is ignored.

## Telegram (needs a bot)

1. Create a bot with [@BotFather](https://t.me/BotFather) and note the token and username.
2. Put these in `.env` (generate the secret with `php artisan tinker --execute="echo Str::random(40);"`):

   ```
   TELEGRAM_BOT_TOKEN=123456:ABC...
   TELEGRAM_BOT_USERNAME=your_bot
   TELEGRAM_WEBHOOK_SECRET=<16+ chars of A-Z a-z 0-9 _ ->
   ```

3. Receive updates in ONE of two ways:
   - **Production (webhook, HTTPS URL):** `php artisan telegram:webhook` (add `--delete` to remove it)
   - **Local development (no public URL):** `php artisan telegram:poll`

The user clicks *Verify with Telegram*, opens the bot link and presses **Start**. Telegram tells us who pressed it; the verification only succeeds if that Telegram username matches the one the user entered. The record is keyed by the numeric Telegram id, because usernames can be released and re-registered by someone else.

> Telegram is filtered in some countries. The server must be able to reach `api.telegram.org`
> (to send replies and, for polling/`setWebhook`, to receive updates), and Telegram must be able to reach your webhook URL.

## Rules enforced

- One external account can be verified for **one** user only; a second claim is refused.
- Tokens are single use, expire (default 72 h, `VERIFICATION_TTL_HOURS`) and are limited to 40 checks.
- The webhook rejects any request without the exact secret header, and does not exist unless Telegram is configured.
- We store no OAuth/API tokens: only the account identifier, the method and the time.

## Known limits

- Ownership is checked once, when verifying; it is not re-checked later (a domain that expires or a Telegram username that is re-assigned stays "verified" until removed).
