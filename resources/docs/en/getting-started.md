---
title: Getting started
description: What {{brand}} does, and the two ways to integrate it: an avatar URL and a REST API.
---

{{brand}} turns an **email address** into an **avatar** and a **public profile**. A person uploads their photo and details once; every site that knows their email can show them.

There are two ways to use it:

| | Avatar URL | REST API |
|---|---|---|
| What you get | the user's photo | profile data: name, bio, website, socials, avatar |
| API key | not needed | required (free) |
| Call it from | anywhere, even an `<img>` tag | your server |

## Quick start

**1. Hash the email.** Every URL is built from the SHA-256 hash of the email address, never the email itself. See [Creating the identifier](/docs/hash).

```js
// sha256 of "myemailaddress@example.com"
const hash = '84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee';
```

**2. Show the avatar.** No key, no sign-up:

```html
<img src="{{origin}}/avatar/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee?s=96&d=mp"
     width="96" height="96" alt="">
```

**3. (Optional) Get profile data.** Create an [API key](/docs/authentication) and call the API from your server:

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  {{origin}}/api/v1/profiles/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee
```

## Base URLs

| Purpose | URL |
|---|---|
| Avatar by email hash | `{{origin}}/avatar/{hash}` |
| REST API | `{{origin}}/api/v1` |
| A public profile | `{{origin}}/{username}` |
| Public profile as JSON / Markdown / vCard / embeddable card | `{{origin}}/{username}.json` · `.md` · `.vcf` · `.card` |

## What a user controls

People decide what is public. A photo is only served for an email the user has **verified** and marked **public**, and only if they left *show avatar* on. If any of that is not true you receive the default image (avatars) or a `404` (API), and the two cases look exactly the same, so nobody can use your integration to find out which emails have an account. Details: [Privacy and limits](/docs/privacy-and-limits).

## Next steps

- [Creating the identifier (hash)](/docs/hash)
- [Avatars](/docs/avatars): sizes, defaults, caching
- [Authentication](/docs/authentication) and the [API reference](/docs/api-reference)
