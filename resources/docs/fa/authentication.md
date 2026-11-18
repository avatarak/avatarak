---
title: احراز هویت
description: برای سایت خودتان یک کلید API بسازید و آن را با هر درخواست بفرستید.
---

[آدرس آواتار](/docs/avatars) عمومی است. **REST API** به **کلید API** نیاز دارد. کلیدها رایگان‌اند و هر کدام مال یک *سایت* (برنامه یا وب‌سایتی که وصل می‌کنید) است.

## ساخت کلید

1. وارد پنل شوید و **توسعه‌دهندگان** را باز کنید.
2. یک **نام سایت** و **دامنه‌اش** را بنویسید و **ساخت کلید** را بزنید.
3. **کلید را همان لحظه کپی کنید.** فقط یک‌بار نشان داده می‌شود و فقط هش آن ذخیره می‌شود، پس قابل بازیابی نیست. تعداد سایت‌ها به [پلن](/docs/plans) شما بستگی دارد (در پلن رایگان ۳ تا).

کلیدها به شکل `avk_` و بعد ۴۰ نویسه‌ی تصادفی هستند.

## ارسال کلید

از توکن حامل (bearer) در هدر `Authorization` استفاده کنید (یا هدر `X-API-Key`):

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

## دسترسی‌های کلید (scope)

هنگام ساخت کلید انتخاب می‌کنید چه کاری بتواند بکند: `profiles:read` (یک پروفایل با هش) و `profiles:lookup` (تا ۱۰۰ مورد یک‌جا). درخواست خارج از scope کلید `403` می‌گیرد. کلیدهای قدیمی (قبل از scope) دسترسی کامل را نگه می‌دارند. همه‌ی endpointها را می‌توانید در مرورگر در [/docs/api-explorer](/docs/api-explorer) امتحان کنید؛ توضیح ماشین‌خوان در [/docs/openapi.json](/docs/openapi.json) است.

## آدرس پایه

```text
{{origin}}/api/v1
```

همه‌ی پاسخ‌ها JSON هستند. خطاها شکل `{"message": "..."}` دارند؛ [مرجع API](/docs/api-reference#خطاها) را ببینید.

## محدودیت نرخ

هر کلید در دقیقه محدود است (پیش‌فرض **۶۰۰ درخواست**). هر پاسخ این هدرها را دارد:

| هدر | معنی |
|---|---|
| `X-RateLimit-Limit` | تعداد درخواست مجاز در دقیقه برای این کلید |
| `X-RateLimit-Remaining` | درخواست‌های باقی‌مانده‌ی همین دقیقه |
| `Retry-After` | (فقط در `429`) چند ثانیه صبر کنید |

## کلیدتان را امن نگه دارید

- **کلید را هرگز در کد سمت کاربر نگذارید** (صفحه‌ی وب، اپ موبایل). API را از سرور خودتان صدا بزنید و نتیجه را به مرورگر بدهید.
- آن را در متغیر محیطی یا مدیر رمز نگه دارید، نه در مخزن کد.
- همیشه از HTTPS استفاده کنید.
- اگر کلیدی لو رفت، سایت را در پنل حذف کنید و کلید تازه بسازید؛ کلید قدیمی همان لحظه از کار می‌افتد.
