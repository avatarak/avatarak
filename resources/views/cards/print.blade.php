@php
    $t = $rtl
        ? ['print' => 'چاپ', 'sheet' => 'برگه‌ی A4 (۱۰ کارت)', 'single' => 'یک کارت', 'back' => 'بازگشت به پروفایل', 'title' => 'کارت ویزیت', 'hint' => 'در پنجره‌ی چاپ «مقیاس: ۱۰۰٪» (Actual size) را انتخاب کنید و حاشیه را روی «بدون» بگذارید.', 'scan' => 'اسکن کنید']
        : ['print' => 'Print', 'sheet' => 'A4 sheet (10 cards)', 'single' => 'Single card', 'back' => 'Back to profile', 'title' => 'Business card', 'hint' => 'In the print dialog choose “Actual size” (100%) and no margins.', 'scan' => 'Scan me'];
    $shown = preg_replace('#^https?://#', '', $url);
@endphp
<!doctype html>
<html lang="{{ $rtl ? 'fa' : 'en' }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <x-brand-head />
    <title>{{ $t['title'] }} — {{ $name }}</title>
    @include('partials.brand')
    @vite(['resources/css/app.css'])
    <style>
        @page { size: {{ $sheet ? 'A4' : '85mm 55mm' }}; margin: {{ $sheet ? '10mm' : '0' }}; }
        .bc { width: 85mm; height: 55mm; box-sizing: border-box; background: #fff; display: flex; align-items: stretch; position: relative; overflow: hidden; border-radius: 3mm; box-shadow: 0 1px 6px rgb(0 0 0 / .18); break-inside: avoid; }
        .bc::after { content: ''; position: absolute; inset-inline: 0; bottom: 0; height: 3.2mm; background: {{ $accent }}; }
        .bc-main { flex: 1; min-width: 0; padding: 6mm 4mm 7mm 6mm; display: flex; flex-direction: column; justify-content: center; gap: 1.2mm; }
        .bc-photo { width: 17mm; height: 17mm; border-radius: 50%; object-fit: cover; margin-bottom: 1.5mm; }
        .bc-name { font-size: 4.6mm; font-weight: 700; line-height: 1.2; color: #101517; overflow-wrap: anywhere; }
        .bc-sub { font-size: 3mm; color: #5B6470; line-height: 1.35; }
        .bc-url { font-size: 2.9mm; font-weight: 600; color: {{ $accent }}; direction: ltr; unicode-bidi: plaintext; overflow-wrap: anywhere; margin-top: 1mm; }
        .bc-qr { width: 29mm; padding: 5mm 5mm 7mm 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1mm; }
        .bc-qr svg { width: 100%; height: auto; display: block; }
        .bc-qr span { font-size: 2.4mm; color: #5B6470; }
        .sheet { display: grid; grid-template-columns: repeat(2, 85mm); grid-auto-rows: 55mm; gap: 0; justify-content: center; }
        .sheet .bc { box-shadow: none; border-radius: 0; outline: .2mm dashed #b9bfc9; outline-offset: -.1mm; }
        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .stage { padding: 0 !important; min-height: 0 !important; display: block !important; }
            .bc { box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-soft">
<div class="no-print mx-auto flex max-w-3xl flex-wrap items-center gap-3 px-4 py-5">
    <a href="{{ route('profile.show', $p->username) }}" class="btn btn-ghost">{{ $t['back'] }}</a>
    <a href="{{ route('profile.print', ['username' => $p->username, 'sheet' => $sheet ? 0 : 1] + (request()->query('lang') ? ['lang' => request()->query('lang')] : [])) }}" class="btn btn-ghost">{{ $sheet ? $t['single'] : $t['sheet'] }}</a>
    <button type="button" class="btn btn-primary ms-auto" onclick="window.print()">{{ $t['print'] }}</button>
    <p class="w-full text-sm text-muted">{{ $t['hint'] }}</p>
</div>

<div class="stage grid min-h-[60vh] place-items-center p-6">
    @if($sheet)
        <div class="sheet">
            @for($i = 0; $i < 10; $i++) @include('cards.print-face') @endfor
        </div>
    @else
        @include('cards.print-face')
    @endif
</div>
</body>
</html>
