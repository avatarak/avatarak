---
title: کتابخانه‌ها (PHP و جاوااسکریپت)
description: کلاینت‌های رسمی PHP و جاوااسکریپت، یکپارچگی با لاراول و یک وب‌کامپوننت آماده.
---

[REST API](/docs/api-reference) را با هر کلاینت HTTP می‌شود صدا زد، ولی کتابخانه‌های رسمی خودشان آدرس را هش می‌کنند، جست‌وجوها را دسته‌ای می‌فرستند و خطاها را به استثنای (exception) مشخص تبدیل می‌کنند. هر دو در همین مخزن، داخل پوشه‌ی `packages/` هستند.

| کتابخانه | نصب | کاربرد |
|---|---|---|
| **PHP** `avatarak/avatarak-php` | `composer require avatarak/avatarak-php` | هر برنامه‌ی PHP 8.1 به بالا، با یکپارچگی لاراول |
| **جاوااسکریپت** `@avatarak/client` | `npm install @avatarak/client` | مرورگر، Node 18 به بالا، Deno و Bun. بدون وابستگی |

> کلید API را روی **سرور** نگه دارید. جست‌وجوی پروفایل به کلید نیاز دارد، پس آن را از PHP یا Node صدا بزنید، نه از صفحه‌ی عمومی وب. آدرس آواتار **کلید نمی‌خواهد** و در مرورگر امن است.

## PHP

```php
use Avatarak\Client;

$avatarak = new Client('avk_YOUR_API_KEY', '{{origin}}');

// بدون کلید و بدون درخواست: فقط یک آدرس
$avatarak->avatarUrl('ali@example.com', size: 96, default: 'identicon');

// یک پروفایل، یا null وقتی پروفایل عمومی وجود ندارد
$profile = $avatarak->profile('ali@example.com');
echo $profile?->displayName();

// چند تا یک‌جا: هر تعداد آدرس، در دسته‌های ۱۰۰تایی
$profiles = $avatarak->lookup($users->pluck('email'));   // [hash => ?Profile]
```

کلاس `Profile` برای تک‌تک فیلدهای [پاسخ API](/docs/api-reference) ویژگی تایپ‌دار دارد (`nameFa`، `headline`، `links`، `verifiedAccounts`، `avatarUrl` و…) و متد `isVerified('github')`.

خطاها استثنا هستند: `AuthenticationException` (۴۰۱)، `RateLimitException` (۴۲۹، با `retryAfter` به ثانیه) و کلاس پایه‌ی `ApiException`.

### لاراول

پکیج خودکار شناسایی می‌شود. در `.env` اضافه کنید:

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

کامپوننت یک `<img>` با `srcset` دو برابر می‌سازد و آدرس ایمیل را هرگز در صفحه نمی‌نویسد.

## جاوااسکریپت

```js
import { Avatarak, avatarUrl } from '@avatarak/client';

// مرورگر یا سرور، بدون کلید:
const url = await avatarUrl('ali@example.com', { size: 96, default: 'identicon' });

// فقط سرور، با کلید:
const api = new Avatarak({ apiKey: process.env.AVATARAK_KEY, baseUrl: '{{origin}}' });
const profile = await api.profile('ali@example.com');            // شیء یا null
const many = await api.lookup(['a@x.ir', 'b@y.com']);           // { hash: شیء | null }
```

خطاها: `AuthenticationError`، `RateLimitError` (با `retryAfter`) و `ApiError`. تایپ‌های TypeScript همراه پکیج است.

### وب‌کامپوننت

برای صفحه‌های HTML ساده، بدون هیچ مرحله‌ی build:

```html
<script type="module" src="https://unpkg.com/@avatarak/client/src/element.js"></script>

<avatarak-avatar email="ali@example.com" size="48" default="identicon" alt="Ali"></avatarak-avatar>
```

آدرس ایمیل در خودِ مرورگر هش می‌شود و ارسال نمی‌شود. ویژگی‌ها: `email` یا `hash`، `size` (۱۶ تا ۵۱۲)، `default`، `alt` و `base-url` برای نصب شخصی خودتان.

## میزبانی شخصی

آدرس سرور خودتان را به‌عنوان پایه بدهید (`new Client($key, 'https://id.example.com')`، `baseUrl` در جاوااسکریپت، یا `AVATARAK_URL` در لاراول). بقیه‌چیز دقیقاً مثل هم است.
