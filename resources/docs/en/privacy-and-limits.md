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

## Statistics

Each user can see how often their avatar, profile, exports and API data were used, and on which sites. We store **daily counters only**: how many times, and the **host** of the referring site (for example `blog.example.com`). We do **not** store IP addresses, browsers, cookies or anything about the visitor, and the owner's own visits, bots and `HEAD` requests are not counted. Rows older than 90 days are deleted. Only the data of a **real photo or profile** is counted, never the generic default image, so statistics cannot be used to find out whether an address has an account.

## Your account data

Each user can **download everything we hold about them** (a ZIP with their profile, emails, verified accounts, sites without API keys, statistics and photos) and **delete their account** from *Security* in the dashboard. Deleting removes the account, profile, photos, emails, keys and statistics at once; copies in backups disappear when the backup rotates out (about 14 days). Two-step sign-in (an authenticator app, with recovery codes) and a list of active sessions are in the same place, and we email users about a **new device**, a **password** or **two-step change** and a **suspension**. The full legal texts are the [Terms](/terms) and the [Privacy Policy](/privacy).

## Responsible use

- **Do not enumerate or guess** hashes, or test lists of emails to find who has an account.
- Use a profile's data only to show it to *that person's own audience* in the context they chose, and respect their later changes by refreshing regularly.
- Respect `X-Robots-Tag: noindex`.
- Do not call the API from client-side code with your key; proxy through your server.
- Do not resell or republish the data as a bulk dataset.

We may rate-limit or disable keys that break these rules. To report abuse, open an issue in the project's GitHub repository; for a security vulnerability, use the repository's Security tab (private reporting) instead of a public issue.
