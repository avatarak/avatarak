@php
    $fa = app()->getLocale() === 'fa';
    $accent = config('brand.colors.brand') ?: '#1D4FC4';
    $brand = $fa ? config('brand.name') : (config('brand.name_en') ?: config('brand.name'));
    $font = "Vazirmatn, Tahoma, 'Segoe UI', Arial, sans-serif";
@endphp
<!doctype html>
<html lang="{{ $fa ? 'fa' : 'en' }}" dir="{{ $fa ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? $brand }}</title>
</head>
<body style="margin:0;padding:0;background:#F3F5FA;">
@isset($preheader)<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>@endisset
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F5FA;padding:32px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">
            <tr><td style="padding:0 4px 16px;font-family:{{ $font }};font-size:20px;font-weight:700;color:#101517;text-align:{{ $fa ? 'right' : 'left' }};">
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $accent }};margin-{{ $fa ? 'left' : 'right' }}:8px;"></span>{{ $brand }}
            </td></tr>
            <tr><td style="background:#FFFFFF;border-radius:14px;padding:32px 28px;font-family:{{ $font }};color:#101517;font-size:15px;line-height:1.8;text-align:{{ $fa ? 'right' : 'left' }};">
                @isset($title)<h1 style="margin:0 0 14px;font-size:20px;line-height:1.5;color:#101517;">{{ $title }}</h1>@endisset
                @yield('content')
                @isset($button)
                    <p style="margin:26px 0 8px;"><a href="{{ $button['url'] }}" style="display:inline-block;background:{{ $accent }};color:#FFFFFF;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:8px;">{{ $button['label'] }}</a></p>
                    <p dir="ltr" style="margin:0;font-size:12px;line-height:1.6;color:#6B7280;word-break:break-all;text-align:left;">{{ $button['url'] }}</p>
                @endisset
            </td></tr>
            <tr><td style="padding:16px 8px 0;font-family:{{ $font }};font-size:12px;line-height:1.7;color:#6B7280;text-align:center;">{{ __('app.mail_footer', ['app' => $brand]) }}</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
