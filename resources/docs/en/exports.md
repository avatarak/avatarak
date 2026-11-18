---
title: Public profile data
description: JSON, Markdown, vCard, QR code, printable business card and an embeddable card for every public profile, with no API key.
---

Every **public** profile page has machine-readable versions, built from the same data the page shows. They need **no API key** and are meant for other sites to use. Emails are never included.

| Format | URL | Content type |
|---|---|---|
| JSON | `{{origin}}/{username}.json` | `application/json` |
| Markdown | `{{origin}}/{username}.md` | `text/markdown` |
| vCard 3.0 | `{{origin}}/{username}.vcf` | `text/vcard` (downloads as `{username}.vcf`) |
| Embeddable card | `{{origin}}/{username}.card` | `text/html` |
| QR code | `{{origin}}/{username}.qr` | `image/svg+xml` |
| Share image | `{{origin}}/{username}/share.svg` | `image/svg+xml` (1080×1350) |
| Printable card | `{{origin}}/{username}/print` | `text/html` |

If the profile does not exist or the user switched the page off, every format returns `404`.

## JSON

```bash
curl {{origin}}/javid.json
```

```json
{
  "username": "javid",
  "url": "{{origin}}/javid",
  "display_name": "جاوید میرزایی",
  "name_fa": "جاوید میرزایی",
  "name_en": "Javid Mirzaei",
  "headline": "Full-stack engineer",
  "location": "Tehran",
  "bio": "Founder and engineer.",
  "website": "https://javid.example.ir",
  "avatar_url": "{{origin}}/javid/avatar?s=512&v=Kx3Qm1",
  "verified": true,
  "socials": { "github": "https://github.com/javid" },
  "links": [
    { "label": "Portfolio", "url": "https://javid.example.ir/work", "description": "My projects.", "image": "{{origin}}/thumb/9c1f..." }
  ],
  "verified_accounts": [
    { "network": "website", "identifier": "javid.example.ir", "url": "https://javid.example.ir" },
    { "network": "telegram", "identifier": "javid_t", "url": "https://t.me/javid_t" }
  ],
  "gallery": [
    { "url": "{{origin}}/javid/gallery/7/full", "thumb_url": "{{origin}}/javid/gallery/7/thumb", "caption": "Our office", "width": 1600, "height": 1067 }
  ],
  "sections": [
    { "title": "Opening hours", "body": "Sat–Wed, 9 to 17" }
  ],
  "created_at": "2026-11-01T10:12:00+00:00",
  "updated_at": "2026-11-17T20:41:00+00:00"
}
```

| Field | Notes |
|---|---|
| `avatar_url` | `null` when the user hides their photo |
| `verified` | `true` if the owner verified at least one email |
| `socials` | network → **full URL** (unlike the REST API, which returns handles) |
| `links[].image` | present only when the user shows link images |
| `verified_accounts` | accounts the user *proved* they own: see [Verified accounts](/docs/verified-accounts) |
| `gallery` | the photos the owner added, in order; empty when hidden. Full size is at most 1600 px |
| `sections` | free-text sections the owner wrote (`title`, `body`; plain text) |

Fields the user left empty are `null`, an empty object or an empty list.

## Use it from a browser

These endpoints send `Access-Control-Allow-Origin: *`, so you can read them from any web page:

```js
const profile = await (await fetch('{{origin}}/javid.json')).json();
document.querySelector('#name').textContent = profile.display_name;
```

## Embeddable card

A small profile card you can place on your own page:

```html
<iframe src="{{origin}}/javid.card" width="380" height="104" style="border:0" loading="lazy"></iframe>
```

The same code is available in the **Advanced details** window of any profile page, together with a downloadable **QR code** of the profile URL (made in the browser).

## QR code, business card and share image

`/{username}.qr` is a vector QR code of the profile address, so it stays sharp at any print size.

| Parameter | Default | Description |
|---|---|---|
| `size` | `512` | width and height in pixels, `64`–`2048` |
| `fg`, `bg` | `101517`, `ffffff` | colours as hex (`1D4FC4` or `#abc`). A pair with a contrast below 3:1 is refused with `422`, because a phone could not scan it. |
| `margin` | `2` | quiet-zone width in modules, `0`–`8` |
| `content` | the address | `vcard` encodes the contact card instead (falls back to the address if it would be too dense to scan reliably) |
| `download` | | `1` sends it as a file attachment |

`/{username}/share.svg` is a portrait card (photo or initials, name, headline, QR code) for a post or story; add `?download=1` to save it. `/{username}/print` is a print-ready **85×55 mm business card**; add `?sheet=1` for an A4 sheet of ten.

## Headers and limits

| Header | Value |
|---|---|
| `Cache-Control` | `public, max-age=300` |
| `Access-Control-Allow-Origin` | `*` |
| `X-Robots-Tag` | `noindex, nofollow`, unless the user allowed search-engine indexing |

Limit: **60 requests per minute per IP address**. Cache the result on your side instead of requesting on every page view.
