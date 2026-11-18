---
title: Libraries (PHP and JavaScript)
description: Official PHP and JavaScript clients, a Laravel integration and a drop-in web component.
---

You can call the [REST API](/docs/api-reference) with any HTTP client, but the official libraries hash the address for you, batch lookups, and turn errors into typed exceptions. Both live in this repository under `packages/`.

| Library | Install | Use it for |
|---|---|---|
| **PHP** `avatarak/avatarak-php` | `composer require avatarak/avatarak-php` | any PHP 8.1+ app, with Laravel integration built in |
| **JavaScript** `@avatarak/client` | `npm install @avatarak/client` | browsers, Node 18+, Deno, Bun. No dependencies |

> Keep your API key on the **server**. Profile lookups need it, so call them from PHP or Node, not from a public web page. Avatar URLs need **no key** and are safe in the browser.

## PHP

```php
use Avatarak\Client;

$avatarak = new Client('avk_YOUR_API_KEY', '{{origin}}');

// No key, no request: just a URL
$avatarak->avatarUrl('ali@example.com', size: 96, default: 'identicon');

// One profile, or null when there is no public profile
$profile = $avatarak->profile('ali@example.com');
echo $profile?->displayName();

// Many at once: any number of addresses, sent in batches of 100
$profiles = $avatarak->lookup($users->pluck('email'));   // [hash => ?Profile]
```

`Profile` has typed properties for every field of the [API response](/docs/api-reference) (`nameFa`, `headline`, `links`, `verifiedAccounts`, `avatarUrl`…) and `isVerified('github')`.

Errors are exceptions: `AuthenticationException` (401), `RateLimitException` (429, with `retryAfter` seconds) and the base `ApiException`.

### Laravel

The package is auto-discovered. Add to `.env`:

```text
AVATARAK_KEY=avk_YOUR_API_KEY
AVATARAK_URL={{origin}}
```

```php
use Avatarak\Laravel\Facades\Avatarak;

Avatarak::profile($user->email);
```

```blade
<x-avatarak-avatar :email="$user->email" :size="48" default="identicon" class="rounded-full" alt="" />
```

The component renders an `<img>` with a 2× `srcset` and never writes the address into the page.

## JavaScript

```js
import { Avatarak, avatarUrl } from '@avatarak/client';

// Browser or server, no key:
const url = await avatarUrl('ali@example.com', { size: 96, default: 'identicon' });

// Server only, with the key:
const api = new Avatarak({ apiKey: process.env.AVATARAK_KEY, baseUrl: '{{origin}}' });
const profile = await api.profile('ali@example.com');            // object or null
const many = await api.lookup(['a@x.ir', 'b@y.com']);           // { hash: object | null }
```

Errors: `AuthenticationError`, `RateLimitError` (`retryAfter`) and `ApiError`. TypeScript types are included.

### Web component

For plain HTML pages, no build step:

```html
<script type="module" src="https://unpkg.com/@avatarak/client/src/element.js"></script>

<avatarak-avatar email="ali@example.com" size="48" default="identicon" alt="Ali"></avatarak-avatar>
```

The address is hashed in the browser and never sent. Attributes: `email` or `hash`, `size` (16–512), `default`, `alt`, and `base-url` for your own install.

## Self-hosting

Pass your own server as the base URL (`new Client($key, 'https://id.example.com')`, `baseUrl` in JS, or `AVATARAK_URL` in Laravel). Everything else is identical.
