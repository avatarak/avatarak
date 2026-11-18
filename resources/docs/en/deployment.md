---
title: Deployment
description: Run Avatarak.ir on your own server with Docker: configuration, HTTPS, email, CDN, backups and updates.
---

The repository ships a production stack: **nginx**, **PHP-FPM**, a **queue worker**, a **scheduler**, **MySQL** and **Redis**, wired together with Docker Compose. You need a Linux server with Docker, a domain name, and an SMTP account for email.

> This guide matches the files in the repository (`Dockerfile`, `docker-compose.yml`, `docker/`, `.env.production.example`). Read them once; they are short.

## 1. First start

```bash
git clone https://github.com/avatarak/avatarak.git && cd avatarak
cp .env.production.example .env
```

Edit `.env`. Everything marked `!!!` must be changed: the public address (`APP_URL`), the database passwords, and the SMTP settings. Make the application key:

```bash
docker compose run --rm app php artisan key:generate --show      # paste the result into APP_KEY
docker compose up -d --build
docker compose exec app php artisan admin:create you@example.com
```

The app container runs the migrations and caches its configuration on every start. The stack listens on port `8080` (`WEB_PORT` in `.env`).

## 2. HTTPS

Put something that terminates TLS in front of port `8080`. The simplest is [Caddy](https://caddyserver.com), which gets certificates by itself:

```text
avatarak.ir {
    reverse_proxy 127.0.0.1:8080
}
```

Nginx with certbot, Traefik, a cloud load balancer or Cloudflare work equally well. Keep `TRUSTED_PROXIES=*` in `.env` while the app is reachable **only** through that proxy; this is what makes links, QR codes and cookies use `https` and records the real visitor address. If port `8080` is also open to the internet, list the proxy's addresses instead (`TRUSTED_PROXIES=10.0.0.0/8`) or close the port.

## 3. Email

Sign-in codes and verification messages are real email, sent through the queue worker. Fill in `MAIL_*` in `.env`, then check it:

```bash
docker compose exec app php artisan mail:test you@example.com
```

The command sends one message immediately and tells you what went wrong. Use a sending domain with SPF and DKIM set up, or messages will land in spam. Watch the worker with `docker compose logs -f queue`.

## 4. Telegram and GitHub (optional)

Fill in the `TELEGRAM_*` and `GITHUB_*` lines of `.env`, then `docker compose up -d`. In production use the webhook, not polling:

```bash
docker compose exec app php artisan telegram:webhook
```

See [Verified accounts](/docs/verified-accounts) for the details.

## 5. CDN

Avatars are the busiest URL and are built to be cached: they carry no cookies, an `ETag`, `Cache-Control` and open CORS. There are three layers:

- The **browser** keeps a photo for `AVATAR_MAX_AGE` seconds (default one hour).
- The bundled **nginx** keeps a copy for 60 seconds, so a burst of identical requests reaches PHP once.
- A **CDN** (Cloudflare, ArvanCloud, Bunny…) in front of the domain does the heavy lifting. Set `AVATAR_S_MAXAGE` (seconds, `600` in the example) to give shared caches their own lifetime through `s-maxage`.

A changed photo keeps its URL, so a CDN may keep showing the old one for up to `AVATAR_S_MAXAGE` seconds. That is why it is **minutes, not days**; purge a URL by hand if you must. Generated placeholder images never get `s-maxage`.

Two practical notes. The format depends on the `Accept` header (WebP or JPEG, `Vary: Accept`), so use a CDN setting that respects `Vary`, or cache JPEG only. And do not cache `/admin`, `/account` or `/login`: they are dynamic and private.

## Custom domains and HTTPS

Customers' domains need a certificate each. With Caddy this is automatic (*on-demand TLS*): Caddy asks the app, before it gets a certificate, whether it really serves that domain.

```text
{
    on_demand_tls { ask http://127.0.0.1:8080/_internal/domain-check?key=YOUR_DOMAIN_CHECK_KEY }
}
avatarak.ir { reverse_proxy 127.0.0.1:8080 }
https:// {
    tls { on_demand }
    reverse_proxy 127.0.0.1:8080
}
```

Set `DOMAIN_CHECK_KEY` (a long random string) and `CUSTOM_DOMAIN_TARGET` (for example `domains.avatarak.ir`, a DNS name pointing at your server) in `.env`. Without a key the check endpoint is off and no certificates are issued. It approves only verified domains whose owner still has a plan with custom domains and is not suspended.

## 6. Backups

```bash
docker compose exec app php artisan backup:run
```

writes `storage/app/backups/avatarak-YYYY-MM-DD_HHMMSS.zip`: a database dump plus every uploaded photo and thumbnail, and keeps the newest `BACKUP_KEEP` (14). The scheduler runs it every night at 02:30. Set `BACKUP_DISK` to an S3-compatible disk to keep copies **off the server**; a backup on the same machine does not survive losing it.

To restore into a fresh install: unzip the file, load `database.sql` (`mysql avatarak < database.sql`) and copy the `files/` folder into `storage/app/`. Try a restore once before you need it.

## 7. Updates

```bash
git pull
docker compose up -d --build
```

Migrations run automatically; the queue worker and scheduler restart with the new code. Take a `backup:run` first.

## Monitoring

`/up` answers `200` while the process is alive. `/up/ready` also checks the **database, cache and file storage** and answers `503` with only the names of what failed. Point your uptime monitor at `/up/ready`. The admin [dashboard](/docs/administration) shows warnings for settings that are wrong on a live server.

## Checklist before launch

- `LEGAL_ENTITY` and `LEGAL_CONTACT_EMAIL` are set and a lawyer has read `/terms` and `/privacy` (the texts are in `resources/legal/`).
- `ADMIN_REQUIRE_2FA=true`, and every administrator has turned on two-step sign-in.
- `APP_DEBUG=false`, a real `APP_URL` with `https`, a unique `APP_KEY` (keep a copy: losing it invalidates sessions).
- `mail:test` succeeds and a sign-up email arrives in a real inbox.
- `backup:run` works, and a copy lives somewhere other than the server.
- The queue worker and the scheduler containers are running (`docker compose ps`).
- Only ports 80 and 443 are open to the internet.
