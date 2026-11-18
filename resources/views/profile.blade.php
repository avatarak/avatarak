@php
    $accent = ($p->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $p->accent_color)) ? $p->accent_color : null;
    $rtl = app()->getLocale() !== 'en';
    $brand = $rtl ? config('brand.name') : (config('brand.name_en') ?: config('brand.name'));
    $t = $rtl ? [
        'links' => 'لینک‌ها', 'verifiedAccounts' => 'حساب‌های تأییدشده', 'connections' => 'حساب‌های تأییدشده', 'web' => 'وب‌سایت', 'share' => 'اشتراک‌گذاری', 'site' => 'وب‌سایت', 'copied' => 'کپی شد', 'more' => 'نمایش بیشتر', 'less' => 'نمایش کمتر', 'edit' => 'ویرایش پروفایل',
        'adv' => 'جزئیات بیشتر', 'advFor' => 'برای توسعه‌دهنده‌ها', 'advTitle' => 'تجربه‌های شخصی‌سازی‌شده بسازید', 'advText' => 'از داده‌ی عمومی این پروفایل در برنامه یا سایت خودتان استفاده کنید و کارت پروفایل را جاسازی کنید.',
        'created' => 'ساخته‌شده', 'updated' => 'به‌روزرسانی', 'linksN' => 'لینک‌ها', 'socialsN' => 'شبکه‌ها', 'verified' => 'تأییدشده', 'yes' => 'بله', 'no' => 'خیر',
        'embed' => 'کد جاسازی', 'formats' => 'خروجی‌ها', 'qr' => 'کد QR', 'plain' => 'ساده', 'withAvatar' => 'با عکس', 'download' => 'دانلود SVG', 'copy' => 'کپی', 'close' => 'بستن', 'view' => 'مشاهده‌ی پروفایل', 'qrOpen' => 'کد QR',
    ] : [
        'links' => 'Links', 'verifiedAccounts' => 'Verified Accounts', 'connections' => 'Verified connections', 'web' => 'Website', 'share' => 'Share', 'site' => 'Website', 'copied' => 'Copied', 'more' => 'Show more', 'less' => 'Show less', 'edit' => 'Edit profile',
        'adv' => 'Advanced details', 'advFor' => 'For developers', 'advTitle' => 'Craft personalized experiences', 'advText' => 'Use this profile\'s public data in your app or site, and embed the profile card.',
        'created' => 'Created', 'updated' => 'Updated', 'linksN' => 'Links', 'socialsN' => 'Socials', 'verified' => 'Verified', 'yes' => 'Yes', 'no' => 'No',
        'embed' => 'Embed code', 'formats' => 'Formats', 'qr' => 'QR code', 'plain' => 'Plain', 'withAvatar' => 'With avatar', 'download' => 'Download SVG', 'copy' => 'Copy', 'close' => 'Close', 'view' => 'View profile', 'qrOpen' => 'QR code',
    ];
    $embed = '<iframe src="'.$formats['card'].'" width="380" height="104" style="border:0" loading="lazy"></iframe>';
@endphp
<!doctype html>
<html lang="{{ $rtl ? 'fa' : 'en' }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $display }} — {{ $brand }}</title>
    @unless($indexable)<meta name="robots" content="noindex, nofollow">@endunless
    @if($p->bio)<meta name="description" content="{{ \Illuminate\Support\Str::limit($p->bio, 160) }}">@endif
    @include('partials.brand')
    <x-brand-head />
    @vite(['resources/css/app.css', 'resources/js/profile.js'])
</head>
<body class="min-h-screen">
@if($isOwner)
    <a href="/" class="fixed end-4 top-4 z-10 flex items-center gap-2 rounded-full bg-white py-1 pe-3.5 ps-1 text-sm font-semibold text-ink shadow-card transition-colors hover:bg-soft">
        @if($avatarSmall)<img src="{{ $avatarSmall }}" alt="" class="size-7 rounded-full object-cover">@else<span class="size-7 rounded-full bg-line"></span>@endif
        {{ $t['edit'] }}
    </a>
@endif

<div class="profile-canvas min-h-screen px-4 py-12" @if($accent) style="--accent: {{ $accent }}" @endif>
    <div class="mx-auto max-w-[640px] space-y-6">

        <article class="card relative overflow-hidden !rounded-[4px] px-6 pb-8 pt-8 sm:px-10">
            {{-- header image: a blurred copy of the avatar (same idea as Gravatar's header) --}}
            <div class="absolute inset-x-0 top-0 h-36 overflow-hidden bg-[color-mix(in_srgb,var(--accent)_22%,#fff)]" aria-hidden="true">
                @if($avatarUrl)<img src="{{ $avatarUrl }}" alt="" class="size-full scale-150 object-cover opacity-80 blur-2xl">@endif
            </div>

            <div class="relative w-fit">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $display }}" width="148" height="148" class="size-[9.25rem] rounded-full bg-line object-cover">
                @else
                    <div class="size-[9.25rem] rounded-full bg-line"></div>
                @endif
                <button type="button" data-adv-open data-adv-qr class="absolute end-0 top-0 grid size-[38px] place-items-center rounded-full bg-ink text-white transition-opacity hover:opacity-80" aria-label="{{ $t['qrOpen'] }}" title="{{ $t['qrOpen'] }}">
                    <x-icon name="qr" :size="20" />
                </button>
            </div>

            <h1 class="mt-4 text-[2.125rem] font-bold leading-[1.1] tracking-[-0.01em]">{{ $display }}</h1>
            @if($p->name_fa && $p->name_en)<p class="text-base leading-6 text-ink/75"><bdi dir="ltr">{{ $p->name_en }}</bdi></p>@endif
            @if($p->headline)<p class="text-base leading-6 text-ink/75">{{ $p->headline }}</p>@endif
            @if($p->location)<p class="text-base leading-6 text-ink/75">{{ $p->location }}</p>@endif

            @if($verified || count($socials))
                <div class="mt-7 flex flex-wrap items-center gap-3 text-ink">
                    @if($verified)
                        <svg width="22" height="22" viewBox="0 0 24 24" role="img" aria-label="verified" class="text-[#3B6BF0]"><path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/><path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/></svg>
                        @if(count($socials))<span class="h-px w-3.5 bg-ink/35"></span>@endif
                    @endif
                    @foreach($socials as $net => $url)
                        <a href="{{ $url }}" target="_blank" rel="nofollow noopener ugc" title="{{ $net }}" aria-label="{{ $net }}" class="transition-opacity hover:opacity-60">
                            @if(isset($icons[$net]))
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icons[$net] }}"/></svg>
                            @else
                                <span class="inline-grid size-[18px] place-items-center rounded-full bg-ink text-[10px] font-bold uppercase text-white">{{ $net[0] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif

            @if($p->bio && $showBio)
                <div class="mt-7" data-bio>
                    <p class="line-clamp-3 whitespace-pre-line text-base leading-[1.45]" data-bio-text>{{ $p->bio }}</p>
                    <button type="button" class="mt-1 hidden text-base text-[var(--accent)] hover:underline" data-bio-toggle data-more="{{ $t['more'] }}" data-less="{{ $t['less'] }}">{{ $t['more'] }}</button>
                </div>
            @endif

            <div class="mt-6 grid gap-4 {{ $p->website ? 'grid-cols-2' : '' }}">
                <button type="button" class="accent-btn" data-share data-copied="{{ $t['copied'] }}">{{ $t['share'] }}</button>
                @if($p->website)<a class="accent-btn" href="{{ $p->website }}" target="_blank" rel="nofollow noopener ugc">{{ $t['site'] }}</a>@endif
            </div>
        </article>

        @if(count($links) && $showLinks)
            <section class="card !rounded-[4px] px-6 pb-8 pt-8 sm:px-10">
                <h2 class="text-lg font-bold leading-[1.5]">{{ $t['links'] }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @foreach($links as $l)
                        <li>
                            <a href="{{ $l['url'] }}" target="_blank" rel="nofollow noopener ugc" class="group flex items-start gap-4 py-4 sm:gap-6">
                                @if($showImages && ! empty($l['image']))
                                    <img src="{{ $l['image'] }}" alt="" loading="lazy" width="192" height="108" class="aspect-video w-28 shrink-0 rounded-[4px] bg-line object-cover sm:w-48">
                                @else
                                    <span class="grid aspect-video w-28 shrink-0 place-items-center rounded-[4px] bg-[color-mix(in_srgb,var(--accent)_8%,#fff)] text-[var(--accent)] sm:w-48"><x-icon name="link" :size="22" /></span>
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1 text-sm font-bold leading-6 text-[var(--accent)]"><span class="truncate group-hover:underline">{{ $l['label'] }}</span><x-icon name="external" :size="12" class="shrink-0" /></span>
                                    @if(! empty($l['description']))
                                        <span class="line-clamp-3 block text-sm leading-[26px] text-ink/75">{{ $l['description'] }}</span>
                                    @else
                                        <span class="block truncate text-sm leading-6 text-ink/60"><bdi dir="ltr">{{ parse_url($l['url'], PHP_URL_HOST) }}</bdi></span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(count($verifiedAccounts) && $showVerified)
            <section class="card !rounded-[4px] px-6 pb-6 pt-8 sm:px-10">
                <h2 class="text-lg font-bold leading-[1.5]">{{ $t['verifiedAccounts'] }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @foreach($verifiedAccounts as $v)
                        <li class="py-4">
                            <div class="flex items-center gap-2 text-sm font-bold leading-6">
                                @if(isset($icons[$v['network']]))
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icons[$v['network']] }}"/></svg>
                                @else
                                    <x-icon name="globe" :size="18" />
                                @endif
                                {{ $v['network'] === 'website' ? $t['web'] : (['github' => 'GitHub', 'telegram' => 'Telegram'][$v['network']] ?? ucfirst($v['network'])) }}
                                <svg width="18" height="18" viewBox="0 0 24 24" role="img" aria-label="verified" class="text-[#3B6BF0]"><path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/><path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/></svg>
                            </div>
                            <a href="{{ $v['url'] }}" target="_blank" rel="me noopener" class="mt-0.5 block truncate text-base text-[var(--accent)] hover:underline"><bdi dir="ltr">{{ preg_replace('#^https?://#', '', $v['url']) }}</bdi></a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="flex justify-center pt-2">
            <button type="button" data-adv-open class="inline-flex min-h-[42px] items-center gap-2 rounded-full bg-white/60 px-4 py-2 text-sm text-ink backdrop-blur transition-colors hover:bg-white/80">
                <x-icon name="code" :size="18" /> {{ $t['adv'] }}
            </button>
        </div>
        <p class="pb-2 text-center text-sm text-white/90"><a href="/" class="hover:underline">{{ $brand }}</a></p>
    </div>
</div>

{{-- Advanced details --}}
<div id="adv" class="pm" hidden>
    <div class="pm-backdrop">
        <div class="pm-panel card !rounded-[4px]" role="dialog" aria-modal="true" aria-label="{{ $t['adv'] }}" tabindex="-1">
            <div class="grid md:grid-cols-[minmax(0,.85fr)_minmax(0,1.4fr)]">
                <aside class="hidden rounded-s-[4px] bg-[#101517] p-8 text-white md:block">
                    <p class="text-sm font-bold">{{ $t['advFor'] }}</p>
                    <h3 class="mt-8 text-[1.375rem] font-medium leading-snug">{{ $t['advTitle'] }}</h3>
                    <p class="mt-4 text-sm leading-6 text-white/75">{{ $t['advText'] }}</p>
                    <pre dir="ltr" class="mt-6 overflow-x-auto rounded-[4px] bg-white/10 p-3 text-start text-xs leading-5 text-white/90">curl {{ $formats['json'] }}</pre>
                </aside>

                <div class="relative p-6 sm:p-8">
                    <button type="button" data-adv-close class="icon-btn absolute end-3 top-3" aria-label="{{ $t['close'] }}"><x-icon name="close" :size="20" /></button>

                    <a href="{{ route('profile.show', $p->username) }}" class="mt-6 flex items-center gap-4 rounded-[4px] border border-line p-4 md:mt-0">
                        @if($avatarSmall)<img src="{{ $avatarSmall }}" alt="" width="52" height="52" class="size-[52px] shrink-0 rounded-full bg-line object-cover">@else<span class="size-[52px] shrink-0 rounded-full bg-line"></span>@endif
                        <span class="min-w-0">
                            <span class="block truncate text-base font-bold">{{ $display }}</span>
                            @if($p->headline)<span class="block truncate text-sm text-ink/75">{{ $p->headline }}</span>@endif
                            <span class="block text-sm text-[var(--accent)]">{{ $t['view'] }} →</span>
                        </span>
                    </a>

                    <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div><dt class="text-ink/60">{{ $t['created'] }}</dt><dd class="font-medium">{{ $created }}</dd></div>
                        <div><dt class="text-ink/60">{{ $t['updated'] }}</dt><dd class="font-medium">{{ $updated }}</dd></div>
                        <div><dt class="text-ink/60">{{ $t['linksN'] }}</dt><dd class="font-medium">{{ count($links) }}</dd></div>
                        <div><dt class="text-ink/60">{{ $t['socialsN'] }}</dt><dd class="font-medium">{{ $socialCount }}</dd></div>
                        <div><dt class="text-ink/60">{{ $t['verified'] }}</dt><dd class="font-medium">{{ $verified ? $t['yes'] : $t['no'] }}</dd></div>
                        <div><dt class="text-ink/60">{{ $t['connections'] }}</dt><dd class="font-medium">{{ count($verifiedAccounts) }}</dd></div>
                    </dl>

                    <p class="mt-6 text-sm font-semibold">{{ $t['embed'] }}</p>
                    <div class="mt-2 flex items-start gap-2">
                        <code dir="ltr" class="min-w-0 flex-1 break-all rounded-[4px] bg-soft p-2.5 text-start text-xs leading-5">{{ $embed }}</code>
                        <button type="button" class="icon-btn shrink-0" data-copy="{{ $embed }}" data-copied="{{ $t['copied'] }}" title="{{ $t['copy'] }}" aria-label="{{ $t['copy'] }}"><x-icon name="copy" :size="18" /></button>
                    </div>

                    <p class="mt-6 text-sm font-semibold">{{ $t['formats'] }}</p>
                    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        <a class="underline underline-offset-2 hover:text-[var(--accent)]" href="{{ $formats['json'] }}" target="_blank" rel="noopener">JSON</a>
                        <a class="underline underline-offset-2 hover:text-[var(--accent)]" href="{{ $formats['md'] }}" target="_blank" rel="noopener">Markdown</a>
                        <a class="underline underline-offset-2 hover:text-[var(--accent)]" href="{{ $formats['vcf'] }}" download>vCard</a>
                        <a class="underline underline-offset-2 hover:text-[var(--accent)]" href="{{ $formats['card'] }}" target="_blank" rel="noopener">Card</a>
                        <button type="button" class="underline underline-offset-2 hover:text-[var(--accent)]" data-qr-toggle>{{ $t['qr'] }}</button>
                    </div>

                    <div data-qr hidden data-url="{{ route('profile.show', $p->username) }}" data-avatar="{{ $avatarSmall }}" data-name="{{ $p->username }}" class="mt-5 flex flex-wrap items-center gap-5">
                        <div data-qr-box class="size-40 shrink-0 overflow-hidden rounded-[4px] border border-line [&>svg]:size-full" aria-label="{{ $t['qr'] }}"></div>
                        <div class="flex flex-col items-start gap-2 text-sm">
                            <div class="inline-flex overflow-hidden rounded-[4px] border border-line">
                                <button type="button" data-qr-mode="plain" aria-pressed="true" class="px-3 py-1.5 aria-pressed:bg-ink aria-pressed:text-white">{{ $t['plain'] }}</button>
                                @if($avatarSmall)<button type="button" data-qr-mode="avatar" aria-pressed="false" class="px-3 py-1.5 aria-pressed:bg-ink aria-pressed:text-white">{{ $t['withAvatar'] }}</button>@endif
                            </div>
                            <button type="button" data-qr-download class="underline underline-offset-2 hover:text-[var(--accent)]">{{ $t['download'] }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
