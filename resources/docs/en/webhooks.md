---
title: Webhooks
description: Get a signed HTTP request whenever your profile changes, and how to verify it.
---

Instead of polling the [API](/docs/api-reference), add a **webhook** and we call your server when something changes. Webhooks come with the **Pro** plan and above (see [Plans](/docs/plans)). Add one in the panel under **Developers → Webhooks**.

## Events

| Event | When |
|---|---|
| `profile.updated` | A profile field changed. `data.changed` lists which fields. |
| `avatar.changed` | An avatar was uploaded or deleted. |
| `verification.added` | A verified account was added (`data.network`, `data.identifier`). |
| `verification.removed` | A verified account was removed. |
| `ping` | The test you send from the panel. |

## The request

We `POST` JSON to your address. It must be a public **https** URL: private, loopback and link-local addresses are refused, redirects are not followed, and credentials in the URL are not allowed.

```json
{
  "id": "5b1e0b0e-6a4c-4e5e-9c53-1d3f4b8c8d11",
  "type": "profile.updated",
  "created_at": "2026-11-18T12:00:00+00:00",
  "data": {
    "changed": ["headline"],
    "username": "javid",
    "profile_url": "https://avatarak.ir/javid",
    "email_hashes": ["9d86ec2b…"]
  }
}
```

`email_hashes` are the SHA-256 hashes of **your own** verified addresses, so you can match the event to a user on your side. The payload never contains an email address.

Headers: `Avatarak-Event`, `Avatarak-Delivery` (the event `id`, use it to ignore duplicates) and `Avatarak-Signature`.

## Verify the signature

Each webhook has a secret (`whsec_…`) shown **once** when you create it. The signature header is `t=<unix time>,v1=<hex>`, where `v1` is `HMAC-SHA256(secret, "<t>.<raw body>")`. Check it against the **raw** request body, and reject old timestamps (5 minutes is a good limit).

```php
use Avatarak\Webhook;

$ok = Webhook::verify(file_get_contents('php://input'), $_SERVER['HTTP_AVATARAK_SIGNATURE'] ?? null, $secret);
```

```js
import { verifyWebhook } from '@avatarak/client';

const ok = await verifyWebhook(rawBody, req.headers['avatarak-signature'], secret);
```

## Retries

Answer with any `2xx` within 8 seconds. Otherwise we retry up to 5 times, with growing pauses (30 s, 2 min, 10 min, 1 h). After **20 failures in a row** the webhook is switched off automatically; turn it back on in the panel. Make your handler idempotent: the same event can arrive more than once.
