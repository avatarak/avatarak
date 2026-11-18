---
title: Creating the identifier (hash)
description: How to turn an email address into the SHA-256 hash used by every URL.
---

Every avatar and API URL uses a **consistent hash** of the user's email address. You must produce exactly the same hash the service expects:

1. **Trim** leading and trailing whitespace from the email address.
2. Force all characters to **lower case**.
3. Hash the result with **SHA-256** and write it as 64 lowercase hexadecimal characters.

## A worked example

Say a user typed `MyEmailAddress@example.com ` with a trailing space by mistake. Hashing that string directly gives the wrong identifier:

```text
sha256("MyEmailAddress@example.com ")
= bbb8db824654275128ce09499cbbeff439840ae68f19f83861c243450dc1d6c7
```

After trimming and lower-casing you get the right one:

```text
sha256("myemailaddress@example.com")
= 84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee
```

## Code

```js
// Browser or Node 18+ (Web Crypto)
async function emailHash(email) {
  const bytes = new TextEncoder().encode(email.trim().toLowerCase());
  const digest = await crypto.subtle.digest('SHA-256', bytes);
  return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}
```

```php
<?php
$hash = hash('sha256', strtolower(trim($email)));
```

```python
import hashlib

def email_hash(email: str) -> str:
    return hashlib.sha256(email.strip().lower().encode('utf-8')).hexdigest()
```

```bash
email=$(printf '%s' "$EMAIL" | xargs | tr '[:upper:]' '[:lower:]')
printf '%s' "$email" | openssl dgst -sha256 | awk '{print $NF}'
```

## Notes

- The hash is a one-way fingerprint, but it is **not secret**: anyone who knows an email can compute it. That is why the service answers identically for unknown, unverified and private emails; see [Privacy and limits](/docs/privacy-and-limits).
- Never send the plain email address to the service. You never need to.
- Hashing in the browser is fine. Do not use `md5` or `sha1`; only SHA-256 is accepted.
- A hash in a URL may be upper or lower case; it is normalised to lower case.

Once you have the hash, request an [avatar](/docs/avatars) or a [profile](/docs/api-reference).
