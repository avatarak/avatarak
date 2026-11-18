---
title: داده‌ی عمومی پروفایل
description: JSON، Markdown، vCard، کد QR، کارت ویزیت چاپی و کارت قابل‌جاسازی برای هر پروفایل عمومی، بدون کلید API.
---

هر صفحه‌ی پروفایل **عمومی** نسخه‌های ماشین‌خوان دارد که از همان داده‌ی نمایش‌داده‌شده در صفحه ساخته می‌شوند. **کلید API نمی‌خواهند** و برای استفاده‌ی سایت‌های دیگر هستند. ایمیل هیچ‌وقت در آن‌ها نیست.

| قالب | آدرس | نوع محتوا |
|---|---|---|
| JSON | `{{origin}}/{username}.json` | `application/json` |
| Markdown | `{{origin}}/{username}.md` | `text/markdown` |
| vCard 3.0 | `{{origin}}/{username}.vcf` | `text/vcard` (با نام `{username}.vcf` دانلود می‌شود) |
| کارت قابل‌جاسازی | `{{origin}}/{username}.card` | `text/html` |
| کد QR | `{{origin}}/{username}.qr` | `image/svg+xml` |
| تصویر اشتراک‌گذاری | `{{origin}}/{username}/share.svg` | `image/svg+xml` (۱۰۸۰×۱۳۵۰) |
| کارت چاپی | `{{origin}}/{username}/print` | `text/html` |

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
  "gallery": [
    { "url": "{{origin}}/javid/gallery/7/full", "thumb_url": "{{origin}}/javid/gallery/7/thumb", "caption": "Our office", "width": 1600, "height": 1067 }
  ],
  "sections": [
    { "title": "Opening hours", "body": "Sat–Wed, 9 to 17" }
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
| `gallery` | عکس‌هایی که مالک اضافه کرده، به ترتیب؛ اگر پنهان باشد خالی است. اندازه‌ی کامل حداکثر ۱۶۰۰ پیکسل است |
| `sections` | بخش‌های متنی آزاد که مالک نوشته (`title` و `body`؛ متن ساده) |

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

## کد QR، کارت ویزیت و تصویر اشتراک‌گذاری

`/{username}.qr` یک کد QR برداری (vector) از آدرس پروفایل است، پس در هر اندازه‌ی چاپی واضح می‌ماند.

| پارامتر | پیش‌فرض | توضیح |
|---|---|---|
| `size` | `512` | عرض و ارتفاع به پیکسل، `64` تا `2048` |
| `fg` و `bg` | `101517` و `ffffff` | رنگ‌ها به‌صورت هگز (`1D4FC4` یا `#abc`). جفت‌رنگی که کنتراست آن زیر ۳ به ۱ باشد با `422` رد می‌شود، چون گوشی نمی‌تواند آن را اسکن کند. |
| `margin` | `2` | عرض حاشیه‌ی سفید به‌صورت واحد ماژول، `0` تا `8` |
| `content` | آدرس پروفایل | با `vcard` به‌جای آدرس، کارت مخاطب را کد می‌کند (اگر برای اسکن مطمئن بیش‌ازحد فشرده باشد، به آدرس برمی‌گردد) |
| `download` | | با `1` به‌صورت فایل دانلودی ارسال می‌شود |

`/{username}/share.svg` یک کارت عمودی (عکس یا حروف اول، نام، عنوان و کد QR) برای پست یا استوری است؛ برای ذخیره `?download=1` را اضافه کنید. `/{username}/print` یک **کارت ویزیت ۸۵×۵۵ میلی‌متری** آماده‌ی چاپ است؛ با `?sheet=1` یک برگه‌ی A4 شامل ده کارت می‌گیرید.

## هدرها و محدودیت

| هدر | مقدار |
|---|---|
| `Cache-Control` | `public, max-age=300` |
| `Access-Control-Allow-Origin` | `*` |
| `X-Robots-Tag` | `noindex, nofollow`، مگر اینکه کاربر ایندکس شدن در موتورهای جست‌وجو را اجازه داده باشد |

محدودیت: **۶۰ درخواست در دقیقه برای هر آدرس IP**. نتیجه را سمت خودتان کش کنید و برای هر بازدید صفحه درخواست ندهید.
