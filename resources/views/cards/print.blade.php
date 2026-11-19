@php
    $t = $rtl
        ? ['print' => 'چاپ', 'sheet' => 'برگه‌ی A4 (۱۰ کارت)', 'single' => 'یک کارت', 'back' => 'بازگشت به پروفایل', 'title' => 'کارت ویزیت', 'scan' => 'برای دیدن پروفایل اسکن کنید', 'view' => 'پروفایل من را ببینید', 'front' => 'روی کارت', 'rear' => 'پشت کارت', 'design' => 'طرح کارت',
            'styles' => ['classic' => 'کلاسیک', 'band' => 'نوار برند', 'bold' => 'رنگی'],
            'hint' => 'در پنجره‌ی چاپ «مقیاس: ۱۰۰٪» (Actual size) و حاشیه‌ی «بدون» را انتخاب کنید. دو صفحه چاپ می‌شود: اول روی کارت، بعد پشت آن.',
            'duplex' => 'برای چاپ دورو روی یک برگه، گزینه‌ی «چاپ دوطرفه» با «برگرداندن از لبه‌ی بلند» (Flip on long edge) را بزنید. در چاپگر تک‌رو، برگه‌ی روی کارت را دوباره در چاپگر بگذارید و صفحه‌ی دوم را چاپ کنید.',
            'nophone' => 'شماره‌ی موبایل را می‌توانید در بخش «کد QR و کارت» اضافه کنید.']
        : ['print' => 'Print', 'sheet' => 'A4 sheet (10 cards)', 'single' => 'Single card', 'back' => 'Back to profile', 'title' => 'Business card', 'scan' => 'Scan to view my profile', 'view' => 'View my profile', 'front' => 'Front', 'rear' => 'Back', 'design' => 'Card design',
            'styles' => ['classic' => 'Classic', 'band' => 'Brand band', 'bold' => 'Bold'],
            'hint' => 'In the print dialog choose “Actual size” (100%) and no margins. Two pages print: the front first, then the back.',
            'duplex' => 'For two-sided printing choose “Print on both sides” with “Flip on long edge”. On a one-sided printer, put the printed sheet back in and print page 2 on the other side.',
            'nophone' => 'You can add a mobile number under “QR code & card”.'];
    $shown = preg_replace('#^https?://#', '', $url);
    $sub = implode(' · ', array_filter([$p->headline, $p->location]));
    $q = request()->query('lang') ? ['lang' => request()->query('lang')] : [];
    $href = fn (array $x) => route('profile.print', ['username' => $p->username] + $x + ['style' => $th->style, 'sheet' => $sheet ? 1 : 0] + $q);
    $ac = $th->accent; $ink = $th->accentInk; $on = $th->onAccent;
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
        .bc { --ac: {{ $ac }}; --ink: {{ $ink }}; --on: {{ $on }}; width: 85mm; height: 55mm; box-sizing: border-box; position: relative; overflow: hidden; border-radius: 3mm; box-shadow: 0 1px 8px rgb(0 0 0 / .2); break-inside: avoid; background: #fff; color: #101517; font-family: 'Vazirmatn Variable', Vazirmatn, Tahoma, Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .bc .q svg { display: block; width: 100%; height: auto; }
        .bc b, .bc span { unicode-bidi: plaintext; }
        .bc img.ph { object-fit: cover; border-radius: 50%; display: block; }
        .bc.back .cap { font-size: 2.6mm; }
        /* classic */
        .bc-classic { padding: 5.5mm; display: flex; flex-direction: column; }
        .bc-classic .hd { display: flex; align-items: center; gap: 3.6mm; }
        .bc-classic .ph { width: 18.5mm; height: 18.5mm; box-shadow: 0 0 0 .8mm #fff, 0 0 0 1.4mm var(--ink); flex: none; margin: .6mm; }
        .bc-classic .nm { font-size: 5.2mm; font-weight: 800; line-height: 1.25; overflow-wrap: anywhere; }
        .bc-classic .sb { font-size: 2.8mm; color: #5B6470; margin-top: .6mm; }
        .bc-classic .rule { height: .5mm; margin: 3.2mm 0; background: linear-gradient(90deg, var(--ac), transparent); }
        .bc-classic .ft { margin-top: auto; display: flex; justify-content: space-between; align-items: flex-end; gap: 3mm; }
        .bc-classic .ct { display: flex; flex-direction: column; gap: 1.1mm; font-size: 2.9mm; color: #33394A; }
        .bc-classic .ct b { color: var(--ink); font-weight: 700; }
        .bc-classic .q { width: 17mm; flex: none; }
        .bc-classic.back { background: var(--ac); color: var(--on); display: grid; place-items: center; align-content: center; gap: 3mm; padding: 0; }
        .bc-classic.back .q { width: 29mm; background: #fff; padding: 2.8mm; border-radius: 3mm; box-sizing: content-box; }
        /* band */
        .bc-band .band { position: absolute; inset: 0 0 auto 0; height: 23mm; background: var(--ac); background-image: radial-gradient(circle at 15% 20%, rgb(255 255 255 / .22), transparent 45%); }
        .bc-band .ph { position: absolute; top: 11mm; inset-inline: 0; margin-inline: auto; width: 22mm; height: 22mm; box-shadow: 0 0 0 1.3mm #fff, 0 .6mm 2.5mm rgb(0 0 0 / .3); }
        .bc-band .who { position: absolute; top: 35.5mm; inset-inline: 3mm; text-align: center; display: flex; flex-direction: column; align-items: center; gap: .6mm; }
        .bc-band .nm { font-size: 4.8mm; font-weight: 800; line-height: 1.2; }
        .bc-band .sb { font-size: 2.7mm; color: #5B6470; }
        .bc-band .pill { margin-top: 1.4mm; display: flex; gap: 2mm; align-items: center; font-size: 2.6mm; background: color-mix(in srgb, var(--ac) 10%, #fff); color: var(--ink); padding: .6mm 3mm; border-radius: 9mm; }
        .bc-band .pill span::before { content: '•'; margin-inline-end: 2mm; opacity: .5; }
        .bc-band.back { background: #F3F5FA; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2.4mm; }
        .bc-band.back .ttl { font-size: 3.4mm; font-weight: 800; color: var(--ink); }
        .bc-band.back .q { width: 26mm; background: #fff; padding: 3mm; border-radius: 3.4mm; border: .8mm solid var(--ac); box-sizing: content-box; box-shadow: 0 1mm 3mm rgb(0 0 0 / .12); }
        .bc-band.back .cap { color: #5B6470; }
        /* bold */
        .bc-bold { background: var(--ac); color: var(--on); }
        .bc .deco { position: absolute; inset: 0; width: 100%; height: 100%; }
        .bc[dir=rtl] .deco { transform: scaleX(-1); }
        .bc-bold:not(.back) .ph { position: absolute; top: 5mm; inset-inline-start: 6mm; width: 13mm; height: 13mm; box-shadow: 0 0 0 .8mm rgb(255 255 255 / .45); }
        .bc-bold:not(.back) .who { position: absolute; top: 19.5mm; inset-inline: 6mm; }
        .bc-bold .nm { font-size: 6.4mm; font-weight: 800; line-height: 1.15; overflow-wrap: anywhere; }
        .bc-bold .sb { font-size: 2.9mm; opacity: .92; margin-top: .8mm; }
        .bc-bold:not(.back) .q { position: absolute; inset-inline-end: 5.5mm; bottom: 5.5mm; width: 14mm; background: #fff; padding: 1.6mm; border-radius: 2.6mm; box-sizing: content-box; }
        .bc-bold:not(.back) .ct { position: absolute; inset-inline-start: 6mm; bottom: 6mm; display: flex; flex-direction: column; gap: 1mm; font-size: 2.8mm; }
        .bc-bold .ct b { font-weight: 600; }
        .bc-bold.back { background: #fff; color: #5B6470; display: grid; place-items: center; align-content: center; gap: 2.6mm; }
        .bc-bold.back .q { width: 32mm; position: relative; }
        .bc-bold.back .cap { position: relative; }
        /* pages */
        .sheet { display: grid; grid-template-columns: repeat(2, 85mm); grid-auto-rows: 55mm; justify-content: center; }
        .sheet .bc { box-shadow: none; border-radius: 0; outline: .2mm dashed #b9bfc9; outline-offset: -.1mm; }
        .side-label { font-size: .8rem; color: #5B6470; margin-bottom: .5rem; }
        .pair { display: flex; flex-wrap: wrap; gap: 1.6rem; justify-content: center; align-items: flex-start; }
        .pick a { display: inline-flex; padding: .45rem .9rem; border: 1px solid #dfe2e8; border-radius: 8px; background: #fff; font-weight: 500; font-size: .9rem; min-height: 44px; align-items: center; }
        .pick a[aria-current="true"] { border-color: #101517; box-shadow: 0 0 0 1px #101517; }
        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .stage { padding: 0 !important; display: block !important; min-height: 0 !important; }
            .pair { display: block; }
            .side { break-after: page; }
            .side:last-child { break-after: auto; }
            .side-label { display: none; }
            .bc { box-shadow: none; }
        }
    </style>
</head>
<body class="bg-soft">
<div class="no-print mx-auto flex max-w-3xl flex-wrap items-center gap-3 px-4 py-5">
    <a href="{{ route('profile.show', $p->username) }}" class="btn btn-ghost">{{ $t['back'] }}</a>
    <a href="{{ $href(['sheet' => $sheet ? 0 : 1]) }}" class="btn btn-ghost">{{ $sheet ? $t['single'] : $t['sheet'] }}</a>
    <button type="button" class="btn btn-primary ms-auto" data-print>{{ $t['print'] }}</button>
    <div class="pick flex w-full flex-wrap items-center gap-2" role="group" aria-label="{{ $t['design'] }}">
        <span class="text-sm text-muted">{{ $t['design'] }}:</span>
        @foreach($styles as $s)<a href="{{ $href(['style' => $s]) }}" @if($s === $th->style) aria-current="true" @endif>{{ $t['styles'][$s] }}</a>@endforeach
    </div>
    <p class="w-full text-sm text-muted">{{ $t['hint'] }}</p>
    @if($sheet)<p class="w-full text-sm text-muted">{{ $t['duplex'] }}</p>@endif
    @if(!$phone && auth()->id() === $p->user_id)<p class="w-full text-sm text-muted">{{ $t['nophone'] }}</p>@endif
</div>

<div class="stage grid min-h-[60vh] place-items-center p-6">
    <div class="pair">
        @foreach(['front', 'back'] as $side)
            <div class="side">
                <div class="side-label">{{ $side === 'front' ? $t['front'] : $t['rear'] }}</div>
                @if($sheet)
                    <div class="sheet">@for($i = 0; $i < 10; $i++) @include('cards.face', ['side' => $side]) @endfor</div>
                @else
                    @include('cards.face', ['side' => $side])
                @endif
            </div>
        @endforeach
    </div>
</div>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });</script>
</body>
</html>
