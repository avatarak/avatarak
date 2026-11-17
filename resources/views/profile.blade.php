@php
    $accent = ($p->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $p->accent_color)) ? $p->accent_color : null;
    $order = collect($p->section_order ?: ['bio', 'links', 'website'])->intersect(['bio', 'links', 'website'])->values();
    foreach (['bio', 'links', 'website'] as $s) { if (! $order->contains($s)) { $order->push($s); } }
    $display = $p->name_fa ?: ($p->name_en ?: $p->username);
@endphp
<!doctype html>
<html lang="{{ config('brand.default_locale') }}" dir="{{ config('brand.default_locale') === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $display }} — {{ config('brand.name') }}</title>
    @unless($indexable)<meta name="robots" content="noindex, nofollow">@endunless
    @if($p->bio)<meta name="description" content="{{ \Illuminate\Support\Str::limit($p->bio, 160) }}">@endif
    @include('partials.brand')
    @if($accent)<style>:root{--brand: {{ $accent }}}</style>@endif
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen">
<main class="mx-auto max-w-xl px-4 py-10">
    <article class="card overflow-hidden">
        <div class="h-28" style="background: color-mix(in srgb, var(--brand) 16%, var(--surface))"></div>
        <div class="px-6 pb-8 text-center">
            <div class="-mt-16 mb-4 flex justify-center">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $display }}" width="128" height="128"
                         class="size-32 rounded-full border-4 border-surface object-cover bg-line">
                @else
                    <div class="size-32 rounded-full border-4 border-surface bg-line"></div>
                @endif
            </div>
            <h1 class="font-display text-2xl font-bold tracking-tight">{{ $display }}</h1>
            @if($p->name_fa && $p->name_en)<div dir="ltr" class="text-muted">{{ $p->name_en }}</div>@endif
            <div dir="ltr" class="text-sm text-muted">&#64;{{ $p->username }}</div>

            @foreach($order as $section)
                @if($section === 'bio' && $p->bio)
                    <p class="mt-6 leading-8">{{ $p->bio }}</p>
                @elseif($section === 'website' && $p->website)
                    <p class="mt-6"><a class="font-medium text-brand hover:underline" href="{{ $p->website }}" rel="nofollow noopener ugc" target="_blank">{{ parse_url($p->website, PHP_URL_HOST) }}</a></p>
                @elseif($section === 'links' && $socials)
                    <ul class="mt-6 flex flex-wrap justify-center gap-2">
                        @foreach($socials as $net => $url)
                            <li><a class="btn btn-ghost !py-1.5 text-sm" href="{{ $url }}" rel="nofollow noopener ugc" target="_blank">{{ $net }}</a></li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        </div>
    </article>
    <p class="mt-6 text-center text-sm text-muted"><a href="/" class="hover:underline">{{ config('brand.name') }}</a></p>
</main>
</body>
</html>
