@php
    $accent = ($p->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $p->accent_color)) ? $p->accent_color : null;
    $display = $p->name_fa ?: ($p->name_en ?: $p->username);
    $rtl = config('brand.default_locale') !== 'en';
    $t = $rtl
        ? ['links' => 'لینک‌ها', 'share' => 'اشتراک‌گذاری', 'site' => 'مشاهده‌ی وب‌سایت', 'copied' => 'لینک کپی شد', 'more' => 'نمایش بیشتر', 'less' => 'نمایش کمتر', 'edit' => 'ویرایش پروفایل']
        : ['links' => 'Links', 'share' => 'Share', 'site' => 'Visit website', 'copied' => 'Link copied', 'more' => 'Show more', 'less' => 'Show less', 'edit' => 'Edit profile'];
@endphp
<!doctype html>
<html lang="{{ $rtl ? 'fa' : 'en' }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $display }} — {{ config('brand.name') }}</title>
    @unless($indexable)<meta name="robots" content="noindex, nofollow">@endunless
    @if($p->bio)<meta name="description" content="{{ \Illuminate\Support\Str::limit($p->bio, 160) }}">@endif
    @include('partials.brand')
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen">
@if($isOwner)
    <a href="/" class="fixed end-4 top-4 z-10 flex items-center gap-2 rounded-full bg-white py-1.5 pe-4 ps-1.5 text-sm font-semibold text-ink shadow-card transition-colors hover:bg-soft">
        @if($avatarUrl)<img src="{{ $avatarUrl }}" alt="" class="size-8 rounded-full object-cover">@else<span class="size-8 rounded-full bg-line"></span>@endif
        {{ $t['edit'] }}
    </a>
@endif
<div class="profile-canvas min-h-screen px-4 py-10 sm:py-14" @if($accent) style="--accent: {{ $accent }}" @endif>
    <div class="mx-auto max-w-[640px] space-y-5">
        <article class="card overflow-hidden">
            <div class="relative h-36 overflow-hidden bg-[color-mix(in_srgb,var(--accent)_22%,#fff)]">
                @if($avatarUrl)<img src="{{ $avatarUrl }}" alt="" class="absolute inset-0 size-full scale-150 object-cover opacity-80 blur-2xl">@endif
            </div>
            <div class="px-6 pb-9 sm:px-12">
                <div class="relative -mt-[6.5rem] w-fit">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $display }}" width="160" height="160" class="size-36 rounded-full bg-line object-cover shadow-[0_2px_14px_rgb(0_0_0/.16)] sm:size-40">
                    @else
                        <div class="size-36 rounded-full bg-line sm:size-40"></div>
                    @endif
                </div>

                <h1 class="mt-6 text-[2.5rem] font-bold leading-[1.15] tracking-tight">{{ $display }}</h1>
                @if($p->name_fa && $p->name_en)<p class="mt-1 text-lg text-ink-2"><bdi dir="ltr">{{ $p->name_en }}</bdi></p>@endif
                @if($p->headline)<p class="mt-2 text-xl leading-snug text-ink-2">{{ $p->headline }}</p>@endif
                @if($p->location)<p class="mt-1 flex items-center gap-1.5 text-lg text-ink-2"><x-icon name="pin" :size="18" class="shrink-0 opacity-70" /> {{ $p->location }}</p>@endif

                @if($verified || count($socials))
                    <div class="mt-10 flex flex-wrap items-center gap-3.5 text-ink">
                        @if($verified)
                            <svg width="24" height="24" viewBox="0 0 24 24" role="img" aria-label="verified" class="text-[#3B6BF0]"><path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/><path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/></svg>
                            @if(count($socials))<span class="h-px w-3 bg-muted/50"></span>@endif
                        @endif
                        @foreach($socials as $net => $url)
                            <a href="{{ $url }}" target="_blank" rel="nofollow noopener ugc" title="{{ $net }}" aria-label="{{ $net }}" class="transition-opacity hover:opacity-60">
                                @if(isset($icons[$net]))
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icons[$net] }}"/></svg>
                                @else
                                    <span class="inline-grid size-[22px] place-items-center rounded-full bg-ink text-[11px] font-bold uppercase text-white">{{ $net[0] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif

                @if($p->bio && $showBio)
                    <div class="mt-10" data-bio>
                        <p class="line-clamp-3 whitespace-pre-line text-[1.0625rem] leading-7" data-bio-text>{{ $p->bio }}</p>
                        <button type="button" class="mt-1 hidden font-medium text-[var(--accent)] hover:underline" data-bio-toggle data-more="{{ $t['more'] }}" data-less="{{ $t['less'] }}">{{ $t['more'] }}</button>
                    </div>
                @endif

                <div class="mt-8 grid gap-3 {{ $p->website ? 'grid-cols-2' : '' }}">
                    <button type="button" class="accent-btn" data-share data-copied="{{ $t['copied'] }}">{{ $t['share'] }}</button>
                    @if($p->website)<a class="accent-btn" href="{{ $p->website }}" target="_blank" rel="nofollow noopener ugc">{{ $t['site'] }}</a>@endif
                </div>
            </div>
        </article>

        @if(count($links) && $showLinks)
            <section class="card px-6 py-8 sm:px-12">
                <h2 class="text-[1.375rem] font-bold">{{ $t['links'] }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @foreach($links as $l)
                        <li>
                            <a href="{{ $l['url'] }}" target="_blank" rel="nofollow noopener ugc" class="group flex items-start gap-4 py-5">
                                <span class="mt-0.5 grid size-14 shrink-0 place-items-center rounded-[8px] bg-[color-mix(in_srgb,var(--accent)_10%,#fff)] text-[var(--accent)]"><x-icon name="link" :size="24" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1.5 font-semibold text-[var(--accent)]"><span class="truncate group-hover:underline">{{ $l['label'] }}</span><x-icon name="external" :size="15" class="shrink-0" /></span>
                                    @if(! empty($l['description']))
                                        <span class="mt-1 line-clamp-3 block text-[0.9375rem] leading-6 text-ink-2">{{ $l['description'] }}</span>
                                    @else
                                        <span class="mt-0.5 block truncate text-sm text-muted"><bdi dir="ltr">{{ parse_url($l['url'], PHP_URL_HOST) }}</bdi></span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="pb-4 pt-2 text-center text-sm text-white/90"><a href="/" class="hover:underline">{{ config('brand.name') }}</a></p>
    </div>
</div>
<script>
(function () {
    // "Show more": only offered when the bio actually overflows its 3 lines
    document.querySelectorAll('[data-bio]').forEach(function (box) {
        var text = box.querySelector('[data-bio-text]'), btn = box.querySelector('[data-bio-toggle]');
        function measure() {
            var was = !text.classList.contains('line-clamp-3');
            text.classList.add('line-clamp-3');
            var over = text.scrollHeight > text.clientHeight + 1;
            if (was) text.classList.remove('line-clamp-3');
            btn.classList.toggle('hidden', !over && !was);
        }
        btn.addEventListener('click', function () {
            var open = text.classList.toggle('line-clamp-3') === false;
            btn.textContent = open ? btn.dataset.less : btn.dataset.more;
        });
        measure(); window.addEventListener('resize', measure);
    });
    document.querySelectorAll('[data-share]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!navigator.clipboard) return;
            var label = b.textContent;
            navigator.clipboard.writeText(location.href).then(function () {
                b.textContent = b.dataset.copied; setTimeout(function () { b.textContent = label; }, 1800);
            });
        });
    });
})();
</script>
</body>
</html>
