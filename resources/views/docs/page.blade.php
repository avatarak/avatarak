@extends('docs.layout')

@section('content')
    <article class="docs-prose" data-docs-article>
        <p class="text-sm text-muted">{{ $page['group'] }}</p>
        <h1>{{ $page['title'] }}</h1>
        @unless($translated)
            <p class="docs-note" role="status">{{ $t['notTranslated'] }}</p>
        @endunless
        @if($page['description'])<p class="docs-lead">{{ $page['description'] }}</p>@endif
        {!! $page['html'] !!}
    </article>

    <div class="mt-14 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-6 text-sm">
        <a href="{{ $editUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-muted hover:text-brand"><x-icon name="external" :size="16" /> {{ $t['edit'] }}</a>
    </div>
    <nav class="mt-6 grid gap-4 sm:grid-cols-2" aria-label="pagination">
        @if($prev)<a href="{{ route('docs.show', $prev['slug']) }}" class="rounded-[4px] border border-line p-4 hover:border-brand"><span class="block text-xs text-muted">{{ $t['prev'] }}</span><span class="font-semibold">{{ $prev['title'] }}</span></a>@else<span></span>@endif
        @if($next)<a href="{{ route('docs.show', $next['slug']) }}" class="rounded-[4px] border border-line p-4 hover:border-brand sm:text-end"><span class="block text-xs text-muted">{{ $t['next'] }}</span><span class="font-semibold">{{ $next['title'] }}</span></a>@endif
    </nav>
@endsection

@section('toc')
    <aside class="sticky top-16 hidden h-[calc(100vh-4rem)] overflow-y-auto py-12 xl:block" aria-label="{{ $t['onThisPage'] }}">
        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.04em] text-muted">{{ $t['onThisPage'] }}</p>
        <ul class="space-y-1.5 text-sm" data-docs-toc></ul>
    </aside>
@endsection
