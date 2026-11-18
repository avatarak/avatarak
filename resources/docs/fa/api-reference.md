---
title: مرجع API
description: endpointها، قالب درخواست و پاسخ، مدل داده و خطاهای REST API.
---

آدرس پایه: `{{origin}}/api/v1`. هر درخواست به یک [کلید API](/docs/authentication) نیاز دارد. پاسخ‌ها JSON هستند.

## گرفتن یک پروفایل

```text
GET /api/v1/profiles/{hash}
```

`{hash}` همان [هش SHA-256](/docs/hash) ایمیل است (۶۴ نویسه‌ی هگز).

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

پاسخ هدر `Cache-Control: private, max-age=60` دارد.

## گرفتن چند پروفایل با هم

```text
POST /api/v1/profiles/lookup
```

بین ۱ تا **۱۰۰** هش را در یک درخواست بفرستید (تکراری‌ها ادغام می‌شوند و حروف بزرگ پذیرفته است). نتیجه یک شیء با کلید هش است؛ هشی که پروفایل عمومی ندارد `null` می‌شود.

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

برای نمایش فهرست نظرات یا اعضا با یک تماس به‌جای چند تماس از همین استفاده کنید.

## شیء پروفایل

| فیلد | نوع | توضیح |
|---|---|---|
| `hash` | string | شناسه‌ای که خواسته‌اید (با حروف کوچک) |
| `username` | string | نام کاربری پروفایل |
| `profile_url` | string | صفحه‌ی عمومی پروفایل |
| `name_fa` | string \| null | نام نمایشی فارسی |
| `name_en` | string \| null | نام نمایشی انگلیسی |
| `bio` | string \| null | بیوگرافی کوتاه |
| `website` | string \| null | وب‌سایت شخصی |
| `socials` | object | شبکه ← نام کاربری، مثل `{"github": "javid"}`؛ اگر خالی باشد `{}` |
| `avatar_url` | string \| null | [آدرس آواتار](/docs/avatars)، یا `null` وقتی کاربر عکس را مخفی کرده |

برای داده‌ی کامل‌تر (عنوان، موقعیت، لینک‌ها، حساب‌های تأییدشده) از [خروجی JSON عمومی](/docs/exports) صفحه‌ی پروفایل استفاده کنید.

## خطاها

| وضعیت | بدنه | چه وقت |
|---|---|---|
| `401` | `{"message": "Invalid or missing API key."}` | کلید نیست، اشتباه است، یا سایتش حذف شده. هدر `WWW-Authenticate: Bearer` می‌فرستد. |
| `404` | `{"message": "Profile not found."}` | پروفایل وجود ندارد **یا عمومی نیست**. پایین‌تر را ببینید. |
| `422` | `{"message": "...", "errors": {"hashes": ["..."]}}` | `lookup` با فهرست خالی، بیش از ۱۰۰ هش، یا مقداری که ۶۴ نویسه‌ی هگز نیست |
| `429` | `{"message": "Rate limit exceeded."}` | درخواست زیاد. به‌اندازه‌ی `Retry-After` ثانیه صبر کنید. |

### چرا برای همه چیز یک `404`

ایمیل ناشناخته، ایمیل تأییدنشده، ایمیل خصوصی و پروفایل مخفی، همگی **همان** `404` را می‌گیرند (و `lookup` مقدار `null` برمی‌گرداند). این عمدی است: جلوی کسی را می‌گیرد که بخواهد با API بفهمد کدام آدرس‌های ایمیل حساب دارند. تلاش نکنید این حالت‌ها را از هم جدا کنید. [حریم خصوصی و محدودیت‌ها](/docs/privacy-and-limits) را ببینید.
