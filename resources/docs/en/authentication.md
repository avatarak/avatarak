---
title: Authentication
description: Create an API key for your site and send it with each request.
---

The [avatar URL](/docs/avatars) is public. The **REST API** needs an **API key**. Keys are free and belong to one *site* (an app or website you connect).

## Create a key

1. Sign in to your dashboard and open **Developers**.
2. Enter a **site name** and its **domain**, then press **Create key**.
3. **Copy the key immediately.** It is shown only once; only a hash is stored, so it cannot be recovered. How many sites you can have depends on your [plan](/docs/plans) (3 on Free).

Keys look like `avk_` followed by 40 random characters.

## Send the key

Use a bearer token in the `Authorization` header (or the `X-API-Key` header):

```bash
curl -H "Authorization: Bearer avk_YOUR_API_KEY" \
  {{origin}}/api/v1/profiles/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee
```

```js
// Node 18+, on your SERVER
const res = await fetch('{{origin}}/api/v1/profiles/' + hash, {
  headers: { Authorization: `Bearer ${process.env.AVATARAK_API_KEY}` },
});
const { data } = await res.json();
```

```php
<?php
$ch = curl_init('{{origin}}/api/v1/profiles/' . $hash);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . getenv('AVATARAK_API_KEY')],
]);
$profile = json_decode(curl_exec($ch), true)['data'] ?? null;
```

## Key scopes

When you create a key you choose what it may do: `profiles:read` (one profile by hash) and `profiles:lookup` (up to 100 at once). A request outside a key's scopes gets `403`. Keys made before scopes existed keep full access. You can try every endpoint in your browser at [/docs/api-explorer](/docs/api-explorer); the machine-readable description is at [/docs/openapi.json](/docs/openapi.json).

## Base URL

```text
{{origin}}/api/v1
```

All responses are JSON. Errors have the shape `{"message": "..."}`; see the [API reference](/docs/api-reference#errors).

## Rate limit

Each key is limited per minute (default **600 requests**). Every response carries:

| Header | Meaning |
|---|---|
| `X-RateLimit-Limit` | requests allowed per minute for this key |
| `X-RateLimit-Remaining` | requests left in the current minute |
| `Retry-After` | (only on `429`) seconds to wait |

## Keep your key safe

- **Never put the key in client-side code** (a web page, a mobile app). Call the API from your server and pass the result to the browser.
- Store it in an environment variable or a secrets manager, not in your repository.
- Always use HTTPS.
- If a key leaks, delete the site in your dashboard and create a new key; the old one stops working immediately.
