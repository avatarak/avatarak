@php
    $self = request()->url();
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($page) ? $page['title'].' · ' : '' }}{{ $t['docs'] }} · {{ $brand }}</title>
    <meta name="description" content="{{ $page['description'] ?? ($rtl ? 'مستندات توسعه‌دهنده‌ها: آواتار، REST API و داده‌ی عمومی پروفایل.' : 'Developer documentation: avatars, the REST API and public profile data.') }}">
    <link rel="alternate" type="text/plain" href="{{ route('docs.llms') }}" title="llms.txt">
    @include('partials.brand')
    @vite(['resources/css/app.css', 'resources/js/docs.js'])
</head>
<body class="bg-white text-ink" data-copy="{{ $t['copy'] }}" data-copied="{{ $t['copied'] }}" data-no-results="{{ $t['noResults'] }}">
<header class="sticky top-0 z-30 border-b border-line bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-[1280px] items-center gap-4 px-4 sm:px-6">
        <button type="button" class="icon-btn lg:hidden" data-docs-menu aria-label="{{ $t['menu'] }}" aria-expanded="false"><x-icon name="menu" :size="22" /></button>
        <a href="{{ route('docs.index') }}" class="flex items-center gap-2 font-bold">
            <span class="grid size-8 place-items-center rounded-lg bg-brand text-sm text-white">{{ mb_substr($brand, 0, 1) }}</span>
            <span class="hidden sm:inline">{{ $brand }}</span>
            <span class="rounded-[4px] border border-line px-2 py-0.5 text-xs font-medium text-brand">{{ $t['docs'] }}</span>
        </a>
        <div class="relative ms-auto hidden max-w-xs flex-1 md:block">
            <input type="search" data-docs-search class="field !min-h-[40px] !py-1.5 text-sm" placeholder="{{ $t['search'] }}" aria-label="{{ $t['search'] }}">
        </div>
        <nav class="ms-auto flex items-center gap-1 md:ms-0 sm:gap-3" aria-label="docs">
            <a href="/" class="hidden px-2 text-sm font-medium hover:text-brand sm:inline">{{ $t['home'] }}</a>
            <a href="{{ $repo }}" target="_blank" rel="noopener" class="hidden px-2 text-sm font-medium hover:text-brand sm:inline">GitHub</a>
            <a href="/" class="btn btn-primary !min-h-[38px] !px-4 text-sm">{{ $t['getKey'] }}</a>
            <span class="flex overflow-hidden rounded-[4px] border border-line text-sm" role="group" aria-label="language">
                <a href="{{ $self }}?lang=fa" class="px-2.5 py-1.5 {{ $locale === 'fa' ? 'bg-ink text-white' : 'hover:bg-soft' }}" hreflang="fa" @if($locale === 'fa') aria-current="true" @endif>فا</a>
                <a href="{{ $self }}?lang=en" class="px-2.5 py-1.5 {{ $locale === 'en' ? 'bg-ink text-white' : 'hover:bg-soft' }}" hreflang="en" @if($locale === 'en') aria-current="true" @endif>EN</a>
            </span>
        </nav>
    </div>
</header>

<div class="mx-auto grid max-w-[1280px] gap-x-10 px-4 sm:px-6 lg:grid-cols-[15rem_minmax(0,1fr)] xl:grid-cols-[15rem_minmax(0,1fr)_14rem]">
    <aside data-docs-nav class="fixed inset-x-0 bottom-0 top-16 z-20 hidden overflow-y-auto bg-white px-6 py-6 lg:sticky lg:top-16 lg:z-auto lg:block lg:h-[calc(100vh-4rem)] lg:px-0 lg:pe-4" aria-label="{{ $t['docs'] }}">
        <div class="mb-5 md:hidden"><input type="search" data-docs-search class="field !min-h-[40px] !py-1.5 text-sm" placeholder="{{ $t['search'] }}" aria-label="{{ $t['search'] }}"></div>
        @foreach($tree as $group)
            <div class="mb-6" data-docs-group>
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.04em] text-muted">{{ $group['title'] }}</p>
                <ul class="space-y-0.5">
                    @foreach($group['pages'] as $p)
                        <li data-docs-item data-title="{{ mb_strtolower($p['title'].' '.$p['description']) }}">
                            <a href="{{ route('docs.show', $p['slug']) }}" class="block rounded-[4px] px-3 py-1.5 text-[0.9375rem] {{ $current === $p['slug'] ? 'bg-brand/10 font-semibold text-brand' : 'text-ink/80 hover:bg-soft hover:text-ink' }}" @if($current === $p['slug']) aria-current="page" @endif>{{ $p['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <p class="hidden text-sm text-muted" data-docs-empty>{{ $t['noResults'] }}</p>
        <a href="{{ route('docs.llms') }}" class="mt-2 inline-flex items-center gap-2 text-sm text-muted hover:text-brand"><x-icon name="code" :size="16" /> llms.txt · {{ $t['llms'] }}</a>
    </aside>

    <main class="min-w-0 py-8 lg:py-12">
        @yield('content')
    </main>

    @hasSection('toc')
        @yield('toc')
    @endif
</div>
</body>
</html>
