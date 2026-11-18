@php
    $t = $rtl
        ? ['updated' => 'آخرین به‌روزرسانی', 'home' => 'صفحه‌ی اصلی', 'terms' => 'شرایط استفاده', 'privacy' => 'حریم خصوصی']
        : ['updated' => 'Last updated', 'home' => 'Home', 'terms' => 'Terms', 'privacy' => 'Privacy'];
    $self = request()->url();
    $date = \Illuminate\Support\Carbon::parse($updated)->locale($rtl ? 'fa' : 'en')->translatedFormat('j F Y');
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $brand }}</title>
    @php $seo = app(\App\Services\Seo::class)->legal($page, $title, $description, $locale, $brand); @endphp
    @include('partials.seo-page')
    <x-brand-head />
    @include('partials.brand')
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-ink">
<header class="border-b border-line">
    <div class="mx-auto flex h-16 max-w-3xl items-center gap-4 px-5">
        <a href="/" class="flex items-center gap-2 font-bold"><x-logo-mark :size="28" /> {{ $brand }}</a>
        <nav class="ms-auto flex items-center gap-4 text-sm font-medium">
            <a href="{{ route('legal.show', 'terms') }}" class="{{ $page === 'terms' ? 'text-brand' : 'hover:text-brand' }}">{{ $t['terms'] }}</a>
            <a href="{{ route('legal.show', 'privacy') }}" class="{{ $page === 'privacy' ? 'text-brand' : 'hover:text-brand' }}">{{ $t['privacy'] }}</a>
            <span class="flex overflow-hidden rounded-[4px] border border-line" role="group" aria-label="language">
                <a href="{{ $self }}?lang=fa" class="px-2.5 py-1 {{ $locale === 'fa' ? 'bg-ink text-white' : 'hover:bg-soft' }}">فا</a>
                <a href="{{ $self }}?lang=en" class="px-2.5 py-1 {{ $locale === 'en' ? 'bg-ink text-white' : 'hover:bg-soft' }}">EN</a>
            </span>
        </nav>
    </div>
</header>
<main class="mx-auto max-w-3xl px-5 py-12">
    <h1 class="text-3xl font-bold leading-tight">{{ $title }}</h1>
    <p class="mt-2 text-sm text-muted">{{ $t['updated'] }}: {{ $date }}</p>
    <article class="docs-prose mt-8">{!! $html !!}</article>
</main>
<footer class="border-t border-line py-8 text-center text-sm text-muted"><a href="/" class="hover:underline">{{ $t['home'] }}</a></footer>
</body>
</html>
