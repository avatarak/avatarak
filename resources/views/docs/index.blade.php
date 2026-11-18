@extends('docs.layout')

@php
    $h = $rtl ? [
        'title' => 'از یک آدرس ایمیل، آواتار و پروفایل بگیرید', 'text' => 'کاربران شما قبلاً در آواتارک عکس و پروفایل ساخته‌اند. با یک آدرس تصویر یا یک تماس API، ثبت‌نام‌های ساده را به خوش‌آمدگویی شخصی‌شده تبدیل کنید.',
        'read' => 'خواندن مستندات', 'key' => 'دریافت کلید API', 'topics' => 'موضوع‌ها', 'quick' => 'شروع سریع', 'quickText' => 'تصویر کاربر را با دو خط نشان دهید: ایمیل را هش کنید و آدرس را در یک تگ img بگذارید.',
    ] : [
        'title' => 'Turn email addresses into avatars and profiles', 'text' => 'Your users already made a photo and a profile on Avatarak. With one image URL or one API call, turn plain signups into personalised welcomes.',
        'read' => 'Read the docs', 'key' => 'Get an API key', 'topics' => 'Topics', 'quick' => 'Quick start', 'quickText' => 'Show a user\'s photo in two lines: hash the email and put the URL in an img tag.',
    ];
@endphp

@section('content')
    <section class="docs-hero -mx-4 rounded-[8px] px-6 py-12 sm:-mx-0 sm:px-12 sm:py-16">
        <h1 class="text-balance text-[2.25rem] font-bold leading-[1.15] tracking-tight sm:text-[3.25rem]">{{ $h['title'] }}</h1>
        <p class="mt-5 max-w-[38rem] text-lg leading-[1.6]">{{ $h['text'] }}</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('docs.show', 'getting-started') }}" class="btn btn-primary !min-h-[48px] !px-6">{{ $h['read'] }}</a>
            <a href="/" class="btn btn-ghost !min-h-[48px] !px-6">{{ $h['key'] }}</a>
        </div>
    </section>

    <section class="mt-12">
        <h2 class="text-2xl font-bold">{{ $h['quick'] }}</h2>
        <p class="mt-2 text-ink/80">{{ $h['quickText'] }}</p>
        <pre dir="ltr" class="docs-quick mt-4 overflow-x-auto rounded-[4px] bg-[#2b343b] p-5 text-start text-[13px] leading-[1.7] text-white/90"><code>&lt;img src="{{ rtrim(url('/'), '/') }}/avatar/&lt;sha256-of-email&gt;?s=96"
     width="96" height="96" alt=""&gt;</code></pre>
    </section>

    <section class="mt-12">
        <h2 class="text-2xl font-bold">{{ $h['topics'] }}</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            @foreach($tree as $group)
                @foreach($group['pages'] as $p)
                    <a href="{{ route('docs.show', $p['slug']) }}" class="rounded-[4px] border border-line p-5 transition-colors hover:border-brand">
                        <span class="text-xs text-muted">{{ $group['title'] }}</span>
                        <span class="mt-1 block text-lg font-semibold">{{ $p['title'] }}</span>
                        <span class="mt-1 block text-sm leading-6 text-ink/70">{{ $p['description'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </div>
    </section>
@endsection
