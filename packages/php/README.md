# avatarak/avatarak-php

PHP client for the [Avatarak.ir](https://avatarak.ir) avatar and profile API, with optional Laravel integration.

```bash
composer require avatarak/avatarak-php
```

```php
$avatarak = new Avatarak\Client('avk_YOUR_API_KEY');
$avatarak->avatarUrl('ali@example.com', size: 96);   // no key needed
$avatarak->profile('ali@example.com');               // ?Profile
$avatarak->lookup($emails);                          // [hash => ?Profile], batched by 100
```

Laravel: set `AVATARAK_KEY`, then use the `Avatarak` facade or `<x-avatarak-avatar :email="$user->email" :size="48" />`.

Full guide: `/docs/sdks` on your Avatarak install. Requires PHP 8.1+ and `ext-curl` (or pass your own transport).
MIT licensed.
