---
title: ساخت شناسه (هش)
description: چطور آدرس ایمیل را به هش SHA-256 تبدیل کنید که در همه‌ی آدرس‌ها استفاده می‌شود.
---

هر آدرس آواتار و API از یک **هش یکنواخت** ایمیل کاربر ساخته می‌شود. باید دقیقاً همان هشی را بسازید که سرویس انتظار دارد:

1. فاصله‌های ابتدا و انتهای ایمیل را **حذف (trim)** کنید.
2. همه‌ی حروف را **کوچک** کنید.
3. نتیجه را با **SHA-256** هش کنید و به‌صورت ۶۴ نویسه‌ی هگزادسیمال کوچک بنویسید.

## یک مثال کامل

فرض کنید کاربر اشتباهی `MyEmailAddress@example.com ` را با یک فاصله‌ی انتهایی وارد کرده است. اگر همان رشته را مستقیم هش کنید، شناسه‌ی اشتباه می‌گیرید:

```text
sha256("MyEmailAddress@example.com ")
= bbb8db824654275128ce09499cbbeff439840ae68f19f83861c243450dc1d6c7
```

بعد از trim و کوچک‌کردن حروف، شناسه‌ی درست به دست می‌آید:

```text
sha256("myemailaddress@example.com")
= 84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee
```

## کد

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

## نکته‌ها

- هش یک اثر انگشت یک‌طرفه است، ولی **محرمانه نیست**: هر کس ایمیلی را بداند می‌تواند هشش را بسازد. برای همین سرویس به ایمیل ناشناخته، تأییدنشده و خصوصی یکسان جواب می‌دهد؛ [حریم خصوصی و محدودیت‌ها](/docs/privacy-and-limits) را ببینید.
- هیچ‌وقت خود ایمیل را برای سرویس نفرستید. هرگز لازم نیست.
- هش کردن در مرورگر مشکلی ندارد. از `md5` و `sha1` استفاده نکنید؛ فقط SHA-256 پذیرفته می‌شود.
- هش در آدرس می‌تواند با حروف بزرگ یا کوچک باشد؛ به حروف کوچک تبدیل می‌شود.

بعد از ساخت هش، [آواتار](/docs/avatars) یا [پروفایل](/docs/api-reference) را درخواست کنید.
