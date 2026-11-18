---
title: داده‌ی عمومی پروفایل
description: JSON، Markdown، vCard و کارت قابل‌جاسازی برای هر پروفایل عمومی، بدون کلید API.
---

هر صفحه‌ی پروفایل **عمومی** نسخه‌های ماشین‌خوان دارد که از همان داده‌ی نمایش‌داده‌شده در صفحه ساخته می‌شوند. **کلید API نمی‌خواهند** و برای استفاده‌ی سایت‌های دیگر هستند. ایمیل هیچ‌وقت در آن‌ها نیست.

| قالب | آدرس | نوع محتوا |
|---|---|---|
| JSON | `{{origin}}/{username}.json` | `application/json` |
| Markdown | `{{origin}}/{username}.md` | `text/markdown` |
| vCard 3.0 | `{{origin}}/{username}.vcf` | `text/vcard` (با نام `{username}.vcf` دانلود می‌شود) |
| کارت قابل‌جاسازی | `{{origin}}/{username}.card` | `text/html` |

اگر پروفایل وجود نداشته باشد یا کاربر صفحه را خاموش کرده باشد، همه‌ی قالب‌ها `404` می‌دهند.

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
  "created_at": "2026-11-01T10:12:00+00:00",
  "updated_at": "2026-11-17T20:41:00+00:00"
}
```

| فیلد | نکته |
|---|---|
| `avatar_url` | وقتی کاربر عکسش را مخفی کرده `null` است |
| `verified` | اگر صاحب حداقل یک ایمیل را تأیید کرده باشد `true` است |
| `socials` | شبکه ← **آدرس کامل** (برخلاف REST API که نام کاربری برمی‌گرداند) |
| `links[].image` | فقط وقتی کاربر تصویر کنار لینک‌ها را روشن کرده باشد هست |
| `verified_accounts` | حساب‌هایی که کاربر *ثابت کرده* مال خودش است: [حساب‌های تأییدشده](/docs/verified-accounts) را ببینید |

فیلدهایی که کاربر خالی گذاشته `null`، شیء خالی یا فهرست خالی‌اند.

## استفاده از مرورگر

این endpointها `Access-Control-Allow-Origin: *` می‌فرستند، پس از هر صفحه‌ی وب می‌توانید بخوانیدشان:

```js
const profile = await (await fetch('{{origin}}/javid.json')).json();
document.querySelector('#name').textContent = profile.display_name;
```

## کارت قابل‌جاسازی

یک کارت پروفایل کوچک که می‌توانید در صفحه‌ی خودتان بگذارید:

```html
<iframe src="{{origin}}/javid.card" width="380" height="104" style="border:0" loading="lazy"></iframe>
```

همین کد در پنجره‌ی **جزئیات بیشتر** هر صفحه‌ی پروفایل هم هست، همراه با یک **کد QR** قابل‌دانلود از آدرس پروفایل (در خود مرورگر ساخته می‌شود).

## هدرها و محدودیت

| هدر | مقدار |
|---|---|
| `Cache-Control` | `public, max-age=300` |
| `Access-Control-Allow-Origin` | `*` |
| `X-Robots-Tag` | `noindex, nofollow`، مگر اینکه کاربر ایندکس شدن در موتورهای جست‌وجو را اجازه داده باشد |

محدودیت: **۶۰ درخواست در دقیقه برای هر آدرس IP**. نتیجه را سمت خودتان کش کنید و برای هر بازدید صفحه درخواست ندهید.
