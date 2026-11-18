---
title: Privacy and limits
description: What we never expose, the rate limits, and how to use the service responsibly.
---

## What we never expose

- **Email addresses.** Not on the public page, not in the API, not in any export. Only a one-way hash appears in URLs.
- Anything the user switched off: the photo, the profile page, or search-engine indexing. Each is a separate switch in the dashboard.
- Your API key, which is stored only as a hash.

## Identical answers

For an email that is unknown, unverified or private, the service answers **exactly the same way**: the default image for avatars and a plain `404` (or `null` in `lookup`) for the API. Because a hash is not secret (anyone can compute the hash of an address they know), different answers would reveal which people have an account. Build your integration so it never needs to tell these cases apart.

A user can also hide their whole profile page; then the page, its avatar and its JSON/Markdown/vCard/card exports all return `404`.

## Limits

| What | Limit |
|---|---|
| Avatar URLs (`/avatar/{hash}`, `/{username}/avatar`) | 600 requests per minute per IP |
| REST API | 600 requests per minute per API key (configurable per site) |
| `profiles/lookup` | 100 hashes per request |
| Public exports (`.json`, `.md`, `.vcf`, `.card`) | 60 requests per minute per IP |
| Avatar size (`s`) | 16 to 512 pixels |
| Sites per user | 10 |

When you exceed a limit you get `429` with `Retry-After`.

## Caching

Cache politely and your integration stays fast and well inside the limits:

| Data | Suggested cache |
|---|---|
| Avatar image | let the browser/CDN follow `Cache-Control` (1 hour) and use `ETag` |
| API profile | up to 1 hour |
| Public JSON | up to 5 minutes (`Cache-Control: public, max-age=300`) |

If a profile later returns `404`, the user hid it or removed it: stop showing the data you stored.

## Responsible use

- **Do not enumerate or guess** hashes, or test lists of emails to find who has an account.
- Use a profile's data only to show it to *that person's own audience* in the context they chose, and respect their later changes by refreshing regularly.
- Respect `X-Robots-Tag: noindex`.
- Do not call the API from client-side code with your key; proxy through your server.
- Do not resell or republish the data as a bulk dataset.

We may rate-limit or disable keys that break these rules. To report abuse, open an issue in the project's GitHub repository; for a security vulnerability, use the repository's Security tab (private reporting) instead of a public issue.
