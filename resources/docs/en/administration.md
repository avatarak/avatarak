---
title: Administration
description: The admin area: creating administrators, moderating users and reports, managing API keys, and the audit log.
---

Every install has an **admin area** at `/admin`. It is for the people who run the service: reviewing reports, suspending abusive accounts, managing API keys and watching how the service is used. Normal users cannot see it; for them `/admin/api` simply answers `404`.

## Creating the first administrator

```bash
php artisan admin:create you@example.com --name="Your Name"
```

A password is generated and printed once (add `--password=` to choose your own). You can also sign in with an email code. Later:

```bash
php artisan admin:grant someone@example.com             # make an existing user an administrator
php artisan admin:grant someone@example.com --revoke    # take it away (never the last administrator)
```

Then open `/admin` (or **Admin** in the account menu).

## What you can do

| Area | Purpose |
|---|---|
| **Dashboard** | Users, public profiles, photos, requests, verified accounts, sites and open reports; 30-day growth and traffic charts; the sites using the service most; and a system check that warns about settings that are wrong on a live server (debug on, mail only logged, `sync` queue…). |
| **Users** | Search by name, email, username or id; filter administrators, suspended users, unverified emails, owners of sites. A side panel shows everything about a user and offers the actions below. |
| **Reports** | Visitors can report a profile (spam, impersonation, inappropriate content, illegal content, other). Review each, mark it *actioned* or *dismissed* with a note, close every open report on the same profile at once, or reopen one. |
| **Sites & keys** | Every connected site: owner, last use, rate limit. Disable a key, change its limit, or delete the site. |
| **Verified accounts** | Search every verified website, GitHub and Telegram account, and revoke a verification. |
| **Audit log** | Every administrator action, newest first. |

### Actions on a user

- **Suspend**: needs a reason. The user is signed out on their next request, their profile, exports and avatar disappear everywhere (the [API](/docs/api-reference) included) and their API keys stop working. Lifting the suspension restores everything.
- **Change username**, **remove photo** (or the whole photo library), **clear profile content** (bio, headline, location, website, links, socials, names), **make or remove administrator**.
- **Delete**: removes the account, profile, photos, emails, keys, statistics and reports for good. You must type the user's email to confirm.

You can never suspend or delete yourself or another administrator, and nobody can remove their own administrator access. Remove another administrator's access first, then act on the account.

## The audit log

Each change above is recorded with who did it, when, on what, and the details (for example the suspension reason). Entries keep the administrator's email even if that account is later deleted, and the panel offers no way to edit or remove them.

## Two-step sign-in for administrators

Set `ADMIN_REQUIRE_2FA=true` and administrators must turn on two-step sign-in (Security in the dashboard) before the admin area opens; until then it answers with a clear message. Strongly recommended in production. Administrators also get an email when their account is suspended or their password or two-step settings change, like any user.

## Reports from visitors

Every public profile page has a **Report this profile** link (not shown to the profile's owner). The form takes a reason and an optional note. To stop repeats we keep a **keyed hash** of the reporter's IP address for 24 hours, nothing else, and the page answers the same way whether or not the report was stored. At most six reports a minute are accepted from one address.

## Security notes

- The admin area is the same single-page app, but **every** action goes through `/admin/api`, which checks the administrator flag on the server for each request.
- Emails of users are visible to administrators, as the job requires. API keys are **never** shown: only a short prefix.
- Use a strong, unique password, and consider restricting `/admin` at your web server (for example by IP) as well.
