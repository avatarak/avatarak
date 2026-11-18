---
title: Avatars
description: The avatar URL, its parameters, defaults, caching and CORS behaviour.
---

## Request an avatar

```text
GET {{origin}}/avatar/{hash}
```

No API key is needed. `{hash}` is the [SHA-256 hash](/docs/hash) of the email address (64 hex characters).

```html
<img src="{{origin}}/avatar/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee?s=200"
     width="100" height="100" alt="Profile photo">
```

## Parameters

| Parameter | Default | Description |
|---|---|---|
| `s` (or `size`) | `80` | Square size in pixels, from `16` to `512`. Values outside the range are clamped. Request about **2×** the displayed size for sharp images on high-density screens. |
| `d` (or `default`) | `mp` | What to return when there is no public avatar: `mp` a neutral silhouette (SVG), `blank` a 1×1 transparent PNG, `404` an empty `404` response. Any other value behaves like `mp`. URLs are **not** accepted. |

## Response

- A **square** image, centre-cropped. **JPEG**, or **WebP** when your `Accept` header contains `image/webp`.
- Defaults are served as `image/svg+xml` (`mp`), `image/png` (`blank`) or a `404`.

| Header | Value |
|---|---|
| `Cache-Control` | `public, max-age=3600` for photos, `public, max-age=300` for defaults |
| `ETag` | present; send `If-None-Match` to receive `304 Not Modified` |
| `Vary` | `Accept` |
| `Access-Control-Allow-Origin` | `*` (usable from any page, including `<canvas>`) |
| `Cross-Origin-Resource-Policy` | `cross-origin` |

The photo URL does not change when the user uploads a new photo, so the browser or CDN picks it up when the cache expires (at most one hour for photos).

## When is a photo returned?

Only when **all** of these hold: the email is **verified** by its owner, marked **public**, the user's *show avatar* switch is on, and a photo is set. Otherwise you get the default image, identical for unknown, unverified and private emails. This is deliberate; see [Privacy and limits](/docs/privacy-and-limits).

## A profile's own avatar

Public profile pages use a URL based on the username instead of an email:

```text
GET {{origin}}/{username}/avatar
```

It accepts the same `s` and `d` parameters and returns the photo whenever the profile is public and *show avatar* is on. No email is involved, so verification is not required.

## Examples

```js
const url = `{{origin}}/avatar/${hash}?s=96&d=404`;
const res = await fetch(url);
const hasAvatar = res.ok;   // 404 means "no public avatar": show your own placeholder
```

```php
<?php
$url = '{{origin}}/avatar/' . hash('sha256', strtolower(trim($email))) . '?s=96';
echo '<img src="' . htmlspecialchars($url) . '" width="48" height="48" alt="">';
```

```css
.user-avatar {
  background: url('{{origin}}/avatar/HASH?s=128') center / cover;
  border-radius: 50%;
}
```

## Limits

Avatar requests are limited to **600 per minute per IP address**. Browsers and CDNs cache responses, so normal pages stay far below that; put a CDN in front if you serve many users from one address. See [Privacy and limits](/docs/privacy-and-limits).
