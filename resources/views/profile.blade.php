@php
    $accent = ($p->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $p->accent_color)) ? $p->accent_color : null;
    $display = $p->name_fa ?: ($p->name_en ?: $p->username);
    $rtl = config('brand.default_locale') !== 'en';
    $t = $rtl
        ? ['bio' => 'درباره‌ی من', 'links' => 'لینک‌ها', 'share' => 'اشتراک‌گذاری', 'site' => 'مشاهده‌ی وب‌سایت', 'copied' => 'لینک کپی شد']
        : ['bio' => 'About me', 'links' => 'Links', 'share' => 'Share', 'site' => 'Visit website', 'copied' => 'Link copied'];
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
<div class="profile-canvas min-h-screen px-4 py-10 sm:py-14" @if($accent) style="--accent: {{ $accent }}" @endif>
    <div class="mx-auto max-w-[640px] space-y-5">
        <article class="card overflow-hidden">
            <div class="relative h-44 overflow-hidden bg-[color-mix(in_srgb,var(--accent)_22%,#fff)]">
                @if($avatarUrl)<img src="{{ $avatarUrl }}" alt="" class="absolute inset-0 size-full scale-150 object-cover opacity-80 blur-2xl">@endif
            </div>
            <div class="px-6 pb-8 sm:px-9">
                <div class="relative -mt-[5.5rem]">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $display }}" width="176" height="176" class="size-40 rounded-full bg-line object-cover shadow-[0_2px_14px_rgb(0_0_0/.16)] sm:size-44">
                    @else
                        <div class="size-40 rounded-full bg-line sm:size-44"></div>
                    @endif
                </div>

                <h1 class="mt-5 text-[2.5rem] font-bold leading-tight tracking-tight">{{ $display }}</h1>
                @if($p->name_fa && $p->name_en)<p class="mt-0.5 text-lg text-muted"><bdi dir="ltr">{{ $p->name_en }}</bdi></p>@endif
                @if($p->headline)<p class="mt-2 text-xl leading-snug text-muted">{{ $p->headline }}</p>@endif
                @if($p->location)<p class="mt-2 flex items-center gap-1.5 text-muted"><x-icon name="pin" :size="18" /> {{ $p->location }}</p>@endif

                @if($verified || count($socials))
                    <div class="mt-6 flex flex-wrap items-center gap-4 text-ink">
                        @if($verified)
                            <svg width="28" height="28" viewBox="0 0 24 24" role="img" aria-label="verified" class="text-[#3B6BF0]"><path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/><path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/></svg>
                            @if(count($socials))<span class="h-px w-3.5 bg-muted/50"></span>@endif
                        @endif
                        @foreach($socials as $net => $url)
                            <a href="{{ $url }}" target="_blank" rel="nofollow noopener ugc" title="{{ $net }}" aria-label="{{ $net }}" class="transition-opacity hover:opacity-65">
                                @if(isset($icons[$net]))
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icons[$net] }}"/></svg>
                                @else
                                    <span class="inline-grid size-[26px] place-items-center rounded-full bg-ink text-xs font-bold uppercase text-white">{{ $net[0] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="mt-8 grid gap-3 {{ $p->website ? 'sm:grid-cols-2' : '' }}">
                    <button type="button" class="accent-btn inline-flex items-center justify-center gap-2" data-copied="{{ $t['copied'] }}"
                            onclick="navigator.clipboard && navigator.clipboard.writeText(location.href).then(function(){ var l=this.querySelector('span'); var o=l.textContent; l.textContent=this.dataset.copied; setTimeout(function(){ l.textContent=o; }, 1800); }.bind(this))">
                        <x-icon name="share" :size="19" /><span>{{ $t['share'] }}</span>
                    </button>
                    @if($p->website)<a class="accent-btn inline-flex items-center justify-center gap-2" href="{{ $p->website }}" target="_blank" rel="nofollow noopener ugc"><x-icon name="external" :size="19" /> {{ $t['site'] }}</a>@endif
                </div>
            </div>
        </article>

        @foreach($order as $section)
            @if($section === 'bio' && $p->bio)
                <section class="card p-6 sm:p-9">
                    <h2 class="text-2xl font-bold">{{ $t['bio'] }}</h2>
                    <p class="mt-4 whitespace-pre-line text-[1.0625rem] leading-8">{{ $p->bio }}</p>
                </section>
            @elseif($section === 'links' && count($links))
                <section class="card p-6 sm:p-9">
                    <h2 class="text-2xl font-bold">{{ $t['links'] }}</h2>
                    <ul class="-mx-3 mt-4 divide-y divide-line">
                        @foreach($links as $l)
                            <li>
                                <a href="{{ $l['url'] }}" target="_blank" rel="nofollow noopener ugc" class="group flex items-center gap-4 rounded-[8px] px-3 py-4 transition-colors hover:bg-soft">
                                    <span class="grid size-12 shrink-0 place-items-center rounded-[8px] bg-[color-mix(in_srgb,var(--accent)_10%,#fff)] text-[var(--accent)]"><x-icon name="link" :size="22" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-1.5 font-semibold text-[var(--accent)]"><span class="truncate group-hover:underline">{{ $l['label'] }}</span><x-icon name="external" :size="15" class="shrink-0" /></span>
                                        <span class="block truncate text-sm text-muted"><bdi dir="ltr">{{ parse_url($l['url'], PHP_URL_HOST) }}</bdi></span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach

        <p class="pb-4 pt-2 text-center text-sm text-white/90"><a href="/" class="hover:underline">{{ config('brand.name') }}</a></p>
    </div>
</div>
</body>
</html>
