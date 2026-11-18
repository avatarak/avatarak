---
title: API reference
description: Endpoints, request and response formats, data model and errors of the REST API.
---

Base URL: `{{origin}}/api/v1`. Every request needs an [API key](/docs/authentication). Responses are JSON.

## Get a profile

```text
GET /api/v1/profiles/{hash}
```

`{hash}` is the [SHA-256 hash](/docs/hash) of the email (64 hex characters).

```bash
curl -H "Authorization: Bearer avk_YOUR_API_KEY" \
  {{origin}}/api/v1/profiles/f7254a5b7d62b61c5353da4a235812f4b2171e8f16fd876a210d268da1793fd7
```

```json
{
  "data": {
    "hash": "f7254a5b7d62b61c5353da4a235812f4b2171e8f16fd876a210d268da1793fd7",
    "username": "javid",
    "profile_url": "{{origin}}/javid",
    "name_fa": "جاوید میرزایی",
    "name_en": "Javid Mirzaei",
    "bio": "Full-stack engineer and founder.",
    "website": "https://javid.example.ir",
    "socials": { "github": "javid", "telegram": "javid_t" },
    "avatar_url": "{{origin}}/avatar/f7254a5b7d62b61c5353da4a235812f4b2171e8f16fd876a210d268da1793fd7"
  }
}
```

The response has `Cache-Control: private, max-age=60`.

## Look up many profiles

```text
POST /api/v1/profiles/lookup
```

Send between 1 and **100** hashes in one request (duplicates are merged, upper-case is accepted). The result is an object keyed by hash; hashes without a public profile are `null`.

```bash
curl -X POST -H "Authorization: Bearer avk_YOUR_API_KEY" -H "Content-Type: application/json" \
  -d '{"hashes":["f7254a5b7d62b61c5353da4a235812f4b2171e8f16fd876a210d268da1793fd7","84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee"]}' \
  {{origin}}/api/v1/profiles/lookup
```

```json
{
  "data": {
    "f7254a5b7d62b61c5353da4a235812f4b2171e8f16fd876a210d268da1793fd7": { "username": "javid", "...": "same fields as above" },
    "84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee": null
  }
}
```

Use this to render a list of comments or members with one call instead of many.

## Profile object

| Field | Type | Description |
|---|---|---|
| `hash` | string | the identifier you asked for (lower case) |
| `username` | string | the profile's username |
| `profile_url` | string | the public profile page |
| `name_fa` | string \| null | Persian display name |
| `name_en` | string \| null | English display name |
| `bio` | string \| null | short biography |
| `website` | string \| null | personal website |
| `socials` | object | network → handle, e.g. `{"github": "javid"}`; `{}` when empty |
| `avatar_url` | string \| null | the [avatar URL](/docs/avatars), or `null` when the user hides the photo |

For richer data (headline, location, links, verified accounts) use the public [JSON export](/docs/exports) of the profile page.

## Errors

| Status | Body | When |
|---|---|---|
| `401` | `{"message": "Invalid or missing API key."}` | no key, wrong key, or a deleted site. Sends `WWW-Authenticate: Bearer`. |
| `404` | `{"message": "Profile not found."}` | the profile does not exist **or is not public**. See below. |
| `422` | `{"message": "...", "errors": {"hashes": ["..."]}}` | `lookup` with an empty list, more than 100 hashes, or a value that is not 64 hex characters |
| `429` | `{"message": "Rate limit exceeded."}` | too many requests. Wait `Retry-After` seconds. |

### Why one `404` for everything

Unknown email, unverified email, private email and hidden profile all return the **same** `404` (and `lookup` returns `null`). This is intentional: it stops anyone from using the API to discover which email addresses have an account. Do not try to tell the cases apart. See [Privacy and limits](/docs/privacy-and-limits).
