---
title: Privacy Policy
description: What {{brand}} collects, why, who sees it, and your choices.
---

**{{entity}}** ("we") runs {{brand}} at {{origin}}. This page explains what data we handle. It matches how the software behaves; the technical details are in the [developer docs](/docs/privacy-and-limits).

> **Note for the operator:** have this text reviewed for your jurisdiction before launch, and fill in `LEGAL_ENTITY` and `LEGAL_CONTACT_EMAIL` in `.env`.

## What we collect

| Data | Why | Who can see it |
|---|---|---|
| Email address(es), name, password (stored only as a hash) | to run your account and sign you in | you; our administrators |
| Profile: username, names, headline, location, bio, links, social handles, website | to show your public profile | **everyone**, if you keep the profile public |
| Photos you upload (we re-encode them and strip metadata such as GPS) | to show your avatar | everyone, if you keep your photo public and an email verified and public |
| Verified accounts (website, GitHub, Telegram identifiers) | to show the blue check | everyone, if your profile is public |
| Sign-in information: time of last sign-in; for each active session the IP address and browser; a hashed fingerprint of devices you used | security: the list of your sessions, alerts about a new device | you; our administrators |
| Two-step sign-in secret and recovery codes (encrypted / hashed) | to protect your account | nobody, they are never shown |
| Daily counters of how often your avatar/profile/API data was used, and the **host name** of the sites that used it | the statistics page | you; our administrators in total |
| Reports about profiles: reason, note, and a keyed hash of the reporter's IP for 24 hours | moderation | our administrators |
| Administrator actions (who did what) | accountability | our administrators |

We do **not** sell data, show ads, or use tracking cookies or third-party analytics.

## Your email address is not public

Other websites ask us for an avatar by the **SHA-256 hash** of an email address. We only return a photo if the address is verified, marked public and the photo is switched on. For every other case the answer looks the same, so nobody can use us to find out whether an address has an account. Your email address itself is never shown on your profile, in our public data, or through the API.

## Cookies

We use only what is needed: a session cookie to keep you signed in, a security (XSRF) cookie, and a `locale` cookie that remembers your language. No advertising or tracking cookies.

## Other services we use

- An **email provider** (SMTP) to send sign-in codes and security messages.
- **Telegram** and **GitHub**, only if you choose to verify an account with them. They tell us the identifier of the account you proved; we do not receive your messages, repositories or tokens.
- A **CDN** may cache public avatar images.

## How long we keep data

- Your account data: until you delete the account. Then it is removed immediately from the live service, including photos.
- Statistics: 90 days. Sign-in codes: minutes. Reports: until reviewed and then for as long as needed for abuse prevention.
- **Backups** are kept for about 14 days, so deleted data can remain in a backup until it is rotated out.

## Your choices and rights

- **Privacy settings** control whether your photo and profile are public and indexed by search engines.
- **Export:** in **Security** you can download everything we hold about you (profile, emails, verified accounts, sites, photos) as a ZIP file.
- **Delete:** in **Security** you can delete your account and its data at any time.
- You can correct your data in the dashboard, or ask us to at {{contact}}. If you are in a place where the law gives you more rights (access, objection, complaint to an authority), they apply too.

## Security

Passwords are hashed; two-step sign-in is available; we email you about important security events (new device, password change, two-step changes, suspension); secrets are encrypted at rest. No system is perfectly secure; tell us at {{contact}} if you find a problem.

## Children

The service is not meant for children below the age at which they can consent where they live. If you believe a child has an account, contact us and we will remove it.

## Changes

If we change this policy in a material way we will say so on the site or by email. The date above shows the latest update.

## Contact

{{contact}}
