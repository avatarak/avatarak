@php
    // Self-contained on purpose: an error page must still render when the database, cache or the Vite build is what broke.
    \App\Http\Middleware\SetLocale::apply(request());
    $rtl = app()->getLocale() !== 'en';
    $brand = $rtl ? (config('brand.name') ?: 'آواتارک') : (config('brand.name_en') ?: config('brand.name') ?: 'Avatarak');
    $t = fn (string $fa, string $en) => $rtl ? $fa : $en;
    $code = $code ?? 500;
@endphp
<!doctype html>
<html lang="{{ $rtl ? 'fa' : 'en' }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ $brand }}</title>
    <style>
        :root { --bg:#F6F7F7; --card:#fff; --ink:#101517; --muted:#5B6470; --line:#DCDCDE; --brand:#1D4FC4; --tint:#E8EEFC; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0B0D11; --card:#171B22; --ink:#E8EAED; --muted:#9AA3AE; --line:#2B323C; --brand:#8FB0FF; --tint:#1B2438; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:
               radial-gradient(90% 60% at 50% 0%, color-mix(in srgb, var(--brand) 14%, transparent), transparent 70%), var(--bg);
               color:var(--ink); font-family: Vazirmatn, "Segoe UI", Tahoma, system-ui, -apple-system, sans-serif; line-height:1.6; }
        main { width:100%; max-width:30rem; text-align:center; }
        .brand { display:inline-flex; align-items:center; gap:.5rem; color:var(--ink); text-decoration:none; font-weight:700; font-size:1.125rem; margin-bottom:1.5rem; }
        .brand svg { color:var(--brand); }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:2.25rem 1.75rem 2rem; box-shadow:0 6px 24px rgb(42 57 75 / .07); }
        .art { display:block; margin:0 auto 1.25rem; }
        .code { font-size:.875rem; font-weight:700; letter-spacing:.14em; color:var(--brand); background:var(--tint); display:inline-block; padding:.15rem .75rem; border-radius:999px; direction:ltr; }
        h1 { font-size:1.75rem; line-height:1.3; margin:.9rem 0 .5rem; }
        p { margin:0; color:var(--muted); }
        .actions { display:flex; flex-wrap:wrap; gap:.75rem; justify-content:center; margin-top:1.75rem; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:.5rem 1.25rem; border-radius:6px; font:inherit; font-weight:600; text-decoration:none; cursor:pointer; border:1px solid var(--brand); }
        .btn-primary { background:var(--brand); color:#fff; } @media (prefers-color-scheme: dark) { .btn-primary { color:#0B0D11; } }
        .btn-ghost { background:transparent; color:var(--brand); }
        .btn:focus-visible { outline:3px solid color-mix(in srgb, var(--brand) 45%, transparent); outline-offset:2px; }
        .foot { margin-top:1.25rem; font-size:.875rem; color:var(--muted); }
        .foot a { color:inherit; }
    </style>
</head>
<body>
<main>
    <a class="brand" href="/">
        <svg width="32" height="32" viewBox="12 12 76 76" aria-hidden="true"><path d="M65 38V56A8 8 0 0 0 81 56V50A31 31 0 1 0 68.6 74.8" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="50" cy="50" r="13" fill="currentColor"/><circle cx="50" cy="45" r="4.5" fill="var(--card)"/><path d="M39.75 58Q50 47 60.25 58A13 13 0 0 1 39.75 58Z" fill="var(--card)"/></svg>
        {{ $brand }}
    </a>
    <section class="card" role="alert">
        @yield('art')
        <span class="code">{{ $code }}</span>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @hasSection('actions')
                @yield('actions')
            @else
                <a class="btn btn-primary" href="/">{{ $t('بازگشت به خانه', 'Back to home') }}</a>
                <button class="btn btn-ghost" type="button" data-back>{{ $t('صفحه‌ی قبل', 'Go back') }}</button>
            @endif
        </div>
    </section>
    <p class="foot">@yield('foot')</p>
</main>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">document.querySelectorAll('[data-back]').forEach(function (b) { b.addEventListener('click', function () { history.length > 1 ? history.back() : location.assign('/'); }); });</script>
</body>
</html>
