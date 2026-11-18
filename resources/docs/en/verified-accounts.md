---
title: Verified accounts
description: How users prove they own a website, a GitHub or a Telegram account, and how to run the Telegram bot yourself.
---

A **verified account** is one the user has *proved* they own. It appears on their profile with a blue check and is listed in the `verified_accounts` field of the [public JSON](/docs/exports). Three kinds exist today: **websites**, **GitHub** and **Telegram**. The `network` field of each entry is `website`, `github` or `telegram`.

Verification is also what makes a link trustworthy: verified accounts are rendered with `rel="me"`, the standard way to say "this link is the same person".

## Website

The user enters a domain in the dashboard and publishes the code we give them in **any one** of three ways, then presses *Check now*:

| Method | What to publish |
|---|---|
| Meta tag | `<meta name="avatarak-verification" content="TOKEN">` in the `<head>` of the home page |
| File | `https://DOMAIN/.well-known/avatarak-verification.txt` containing only `TOKEN` |
| DNS | a `TXT` record on `DOMAIN` with the value `avatarak-verification=TOKEN` |

We fetch the page from our server. If the site redirects to a **different domain**, nothing is proved (an apex-to-`www` redirect is fine). Only public addresses on ports 80 and 443 are contacted.

## GitHub

Two ways, both stored by the **numeric GitHub id** (a login can be renamed and taken by someone else, an id cannot):

| Method | How |
|---|---|
| Public gist | the user creates a **public** gist named `avatarak-verification.txt` containing `avatarak-verification=TOKEN` and presses *Check now*. Works with no server setup. |
| Sign in with GitHub | one click, available when the server has an OAuth app configured. We only read the public login and id. |

Secret gists and gists from other accounts are ignored. The gist can be deleted once the account is verified.

## Telegram

The user types their Telegram username, presses **Verify with Telegram**, opens our bot and presses **Start**. Telegram tells us who pressed it, and we confirm the username matches the one entered. The account is stored by its **numeric Telegram id**, because a username can be released and registered by someone else later.

## Rules

- One external account can be verified for **one** user. A second claim is refused.
- A code is single-use, expires after **72 hours** and allows at most 40 checks.
- Ownership is checked **once**, when verifying. A domain that expires later stays verified until the user removes it.
- We store only the account identifier, the method and the time. No tokens from other services.

## Optional GitHub settings (self-hosting)

The gist method needs nothing. Two optional additions in `.env`:

```text
GITHUB_API_TOKEN=          # raises GitHub's anonymous rate limit (60 requests/hour per server IP)
GITHUB_CLIENT_ID=          # an OAuth app enables "Sign in with GitHub"
GITHUB_CLIENT_SECRET=
```

Set the OAuth app's callback URL to `{{origin}}/account/verifications/github/callback`.

## Running the Telegram bot (self-hosting)

1. Create a bot with [@BotFather](https://t.me/BotFather) and note its token and username.
2. Add to `.env` (make the secret with `openssl rand -hex 32`):

   ```text
   TELEGRAM_BOT_TOKEN=123456:ABC...
   TELEGRAM_BOT_USERNAME=your_bot
   TELEGRAM_WEBHOOK_SECRET=<64 random hex characters>
   ```

3. Receive messages in **one** of two ways:

   ```bash
   php artisan telegram:webhook     # production: an HTTPS URL Telegram can reach
   php artisan telegram:poll        # local development: no public URL needed
   ```

The webhook rejects any request without the exact secret in `X-Telegram-Bot-Api-Secret-Token`, and does not exist at all until Telegram is configured. Never share the bot token; if it leaks, send `/revoke` to BotFather.

> Telegram is filtered in some countries. The server must be able to reach `api.telegram.org`, and Telegram must be able to reach your webhook URL. If `telegram:poll` fails with a connection error, try `--timeout=3` or run it through a VPN or proxy.
