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
    $rt = $rtl
        ? ['report' => 'گزارش این پروفایل', 'title' => 'گزارش پروفایل', 'why' => 'دلیل', 'details' => 'توضیح (اختیاری)', 'send' => 'ارسال گزارش', 'cancel' => 'انصراف', 'sending' => 'در حال ارسال…', 'fail' => 'ارسال نشد. دوباره تلاش کنید.', 'note' => 'گزارش‌ها توسط تیم ما بررسی می‌شود. هویت شما ثبت نمی‌شود.',
            'reasons' => ['spam' => 'هرزنامه یا تبلیغ', 'impersonation' => 'جعل هویت', 'inappropriate' => 'محتوا یا عکس نامناسب', 'illegal' => 'محتوای غیرقانونی', 'other' => 'سایر']]
        : ['report' => 'Report this profile', 'title' => 'Report profile', 'why' => 'Reason', 'details' => 'Details (optional)', 'send' => 'Send report', 'cancel' => 'Cancel', 'sending' => 'Sending…', 'fail' => 'Could not send. Please try again.', 'note' => 'Reports are reviewed by our team. Your identity is not recorded.',
            'reasons' => ['spam' => 'Spam or advertising', 'impersonation' => 'Impersonation', 'inappropriate' => 'Inappropriate content or photo', 'illegal' => 'Illegal content', 'other' => 'Other']];
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
        @if($avatarSmall)<img src="{{ $avatarSmall }}" alt="" class="size-7 rounded-full object-cover">@else<img src="{{ route('profile.avatar', ['username' => $p->username, 's' => 64, 'd' => 'initials']) }}" alt="" class="size-7 rounded-full object-cover">@endif
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
                    <img src="{{ route('profile.avatar', ['username' => $p->username, 's' => 296, 'd' => 'initials']) }}" alt="{{ $display }}" width="148" height="148" class="size-[9.25rem] rounded-full bg-line object-cover">
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
                                {{ $v['network'] === 'website' ? $t['web'] : (['github' => 'GitHub', 'telegram' => 'Telegram', 'linkedin' => 'LinkedIn'][$v['network']] ?? ucfirst($v['network'])) }}
                                <svg width="18" height="18" viewBox="0 0 24 24" role="img" aria-label="verified" class="text-[#3B6BF0]"><path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/><path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/></svg>
                            </div>
                            @if($v['url'])<a href="{{ $v['url'] }}" target="_blank" rel="me noopener" class="mt-0.5 block truncate text-base text-[var(--accent)] hover:underline"><bdi dir="ltr">{{ preg_replace('#^https?://#', '', $v['url']) }}</bdi></a>@else<span class="mt-0.5 block truncate text-base text-[var(--accent)]"><bdi>{{ $v['identifier'] }}</bdi></span>@endif
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
        <p class="pb-2 text-center text-sm text-white/90">@unless($whiteLabel)<a href="/" class="hover:underline">{{ $brand }}</a>@endunless
            @unless($isOwner)@unless($whiteLabel) · @endunless<button type="button" data-report-open class="underline-offset-2 hover:underline">{{ $rt['report'] }}</button>@endunless</p>
    </div>
</div>

{{-- Advanced details --}}
<div id="adv" class="pm" hidden>
    <div class="pm-backdrop">
        <div class="pm-panel adv-panel" role="dialog" aria-modal="true" aria-label="{{ $t['adv'] }}" tabindex="-1">
            <div class="grid md:grid-cols-[minmax(0,.8fr)_minmax(0,1.45fr)]">
                <aside class="adv-aside relative hidden overflow-hidden p-8 text-white md:flex md:flex-col">
                    <span class="grid size-11 place-items-center rounded-xl bg-white/12 ring-1 ring-white/20"><x-icon name="code" :size="22" /></span>
                    <p class="mt-6 text-xs font-semibold uppercase tracking-[0.08em] text-white/60">{{ $t['advFor'] }}</p>
                    <h3 class="mt-2 text-[1.5rem] font-semibold leading-snug">{{ $t['advTitle'] }}</h3>
                    <p class="mt-3 text-sm leading-6 text-white/75">{{ $t['advText'] }}</p>
                    <div class="mt-auto pt-8">
                        <div class="flex items-center justify-between rounded-t-xl bg-black/35 px-3 py-1.5 text-[11px] font-medium text-white/55"><span>cURL</span>
                            <button type="button" class="rounded px-1.5 py-0.5 hover:bg-white/10 hover:text-white" data-copy="curl {{ $formats['json'] }}" data-copied="{{ $t['copied'] }}"><span>{{ $t['copy'] }}</span></button></div>
                        <pre dir="ltr" class="overflow-x-auto rounded-b-xl bg-black/25 p-3 text-start text-xs leading-5 text-white/90">curl {{ $formats['json'] }}</pre>
                        <a href="{{ url('/docs') }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-white hover:underline">{{ $rtl ? 'مستندات توسعه‌دهندگان' : 'Developer docs' }} <x-icon name="external" :size="15" /></a>
                    </div>
                </aside>

                <div class="relative bg-white p-6 sm:p-8">
                    <button type="button" data-adv-close class="icon-btn absolute end-3 top-3" aria-label="{{ $t['close'] }}"><x-icon name="close" :size="20" /></button>

                    <a href="{{ route('profile.show', $p->username) }}" class="adv-who mt-8 flex items-center gap-4 rounded-2xl p-4 md:mt-0">
                        @if($avatarSmall)<img src="{{ $avatarSmall }}" alt="" width="56" height="56" class="size-14 shrink-0 rounded-full bg-line object-cover ring-2 ring-white">@else<img src="{{ route('profile.avatar', ['username' => $p->username, 's' => 112, 'd' => 'initials']) }}" alt="" width="56" height="56" class="size-14 shrink-0 rounded-full bg-line object-cover ring-2 ring-white">@endif
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-lg font-bold leading-tight">{{ $display }}</span>
                            @if($p->headline)<span class="block truncate text-sm text-ink/70">{{ $p->headline }}</span>@endif
                            <span dir="ltr" class="block truncate text-start text-xs text-ink/55">{{ preg_replace('#^https?://#', '', route('profile.show', $p->username)) }}</span>
                        </span>
                        <x-icon name="chevron" :size="18" class="shrink-0 text-ink/40 rtl:rotate-180" />
                    </a>

                    <dl class="mt-5 grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        @foreach([[$t['linksN'], count($links)], [$t['socialsN'], $socialCount], [$t['connections'], count($verifiedAccounts)]] as [$lab, $val])
                            <div class="rounded-xl bg-soft px-4 py-3"><dd class="text-2xl font-bold leading-none tabular-nums">{{ $val }}</dd><dt class="mt-1.5 text-xs text-ink/60">{{ $lab }}</dt></div>
                        @endforeach
                        <div class="rounded-xl bg-soft px-4 py-3"><dd class="flex items-center gap-1.5 text-base font-semibold leading-none">@if($verified)<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" class="text-[#3B6BF0]"><path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/><path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/></svg>@endif{{ $verified ? $t['yes'] : $t['no'] }}</dd><dt class="mt-1.5 text-xs text-ink/60">{{ $t['verified'] }}</dt></div>
                        <div class="rounded-xl bg-soft px-4 py-3"><dd class="text-base font-semibold leading-none">{{ $created }}</dd><dt class="mt-1.5 text-xs text-ink/60">{{ $t['created'] }}</dt></div>
                        <div class="rounded-xl bg-soft px-4 py-3"><dd class="text-base font-semibold leading-none">{{ $updated }}</dd><dt class="mt-1.5 text-xs text-ink/60">{{ $t['updated'] }}</dt></div>
                    </dl>

                    <p class="mt-6 text-sm font-semibold">{{ $t['embed'] }}</p>
                    <div class="mt-2 flex items-start gap-2 rounded-xl border border-line bg-soft/60 p-2.5">
                        <code dir="ltr" class="min-w-0 flex-1 break-all p-1 text-start font-mono text-xs leading-5 text-ink-2">{{ $embed }}</code>
                        <button type="button" class="icon-btn shrink-0 !bg-white shadow-sm" data-copy="{{ $embed }}" data-copied="{{ $t['copied'] }}" title="{{ $t['copy'] }}" aria-label="{{ $t['copy'] }}"><x-icon name="copy" :size="18" /></button>
                    </div>

                    <p class="mt-6 text-sm font-semibold">{{ $t['formats'] }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a class="fmt-pill" href="{{ $formats['json'] }}" target="_blank" rel="noopener"><x-icon name="code" :size="16" /> JSON</a>
                        <a class="fmt-pill" href="{{ $formats['md'] }}" target="_blank" rel="noopener"><x-icon name="news" :size="16" /> Markdown</a>
                        <a class="fmt-pill" href="{{ $formats['vcf'] }}" download><x-icon name="idCard" :size="16" /> vCard</a>
                        <a class="fmt-pill" href="{{ $formats['card'] }}" target="_blank" rel="noopener"><x-icon name="globe" :size="16" /> Card</a>
                        <button type="button" class="fmt-pill" data-qr-toggle><x-icon name="qr" :size="16" /> {{ $t['qr'] }}</button>
                    </div>

                    <div data-qr hidden data-url="{{ route('profile.show', $p->username) }}" data-avatar="{{ $avatarSmall }}" data-name="{{ $p->username }}" class="mt-4 flex flex-wrap items-center gap-5 rounded-2xl border border-line p-4">
                        <div data-qr-box class="size-36 shrink-0 overflow-hidden rounded-xl border border-line [&>svg]:size-full" aria-label="{{ $t['qr'] }}"></div>
                        <div class="flex flex-col items-start gap-3 text-sm">
                            <div class="inline-flex overflow-hidden rounded-full border border-line">
                                <button type="button" data-qr-mode="plain" aria-pressed="true" class="px-4 py-1.5 aria-pressed:bg-ink aria-pressed:text-white">{{ $t['plain'] }}</button>
                                @if($avatarSmall)<button type="button" data-qr-mode="avatar" aria-pressed="false" class="px-4 py-1.5 aria-pressed:bg-ink aria-pressed:text-white">{{ $t['withAvatar'] }}</button>@endif
                            </div>
                            <button type="button" data-qr-download class="fmt-pill"><x-icon name="download" :size="16" /> {{ $t['download'] }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@unless($isOwner)
<dialog id="report-dialog" class="report-dialog m-auto w-[min(27rem,calc(100vw-2rem))] overflow-visible rounded-2xl border-0 bg-white p-0 text-ink backdrop:bg-ink/50" aria-labelledby="report-title">
    <form method="dialog" data-report-form data-url="{{ route('profile.report', $p->username) }}" data-sending="{{ $rt['sending'] }}" data-fail="{{ $rt['fail'] }}" class="p-7" novalidate>
        <div class="flex items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-amber-50 text-amber-700"><x-icon name="flag" :size="20" /></span>
            <div><h2 id="report-title" class="text-xl font-bold leading-tight">{{ $rt['title'] }}</h2><p class="mt-1 text-sm leading-6 text-ink/65">{{ $rt['note'] }}</p></div>
        </div>

        <span class="label mt-5" id="report-reason-label">{{ $rt['why'] }}</span>
        <div class="sel" data-select>
            <input type="hidden" name="reason" value="spam">
            <button type="button" class="sel-btn" data-select-btn aria-haspopup="listbox" aria-expanded="false" aria-labelledby="report-reason-label">
                <span data-select-label>{{ $rt['reasons']['spam'] }}</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="sel-caret"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <ul class="sel-list" data-select-list role="listbox" aria-labelledby="report-reason-label" hidden>
                @foreach($rt['reasons'] as $k => $label)
                    <li role="option" tabindex="-1" data-value="{{ $k }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"><span>{{ $label }}</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="sel-check"><path d="m5 12 5 5L20 7"/></svg></li>
                @endforeach
            </ul>
        </div>

        <label class="label mt-4" for="report-details">{{ $rt['details'] }}</label>
        <textarea id="report-details" name="details" rows="3" maxlength="500" class="field"></textarea>

        <div class="mt-6 flex justify-end gap-2">
            <button type="button" data-report-close class="btn btn-ghost">{{ $rt['cancel'] }}</button>
            <button type="submit" data-report-send class="btn btn-primary min-w-28">{{ $rt['send'] }}</button>
        </div>
    </form>
</dialog>
@endunless

{{-- one toast for the whole page --}}
<div id="toast" class="toast" role="status" aria-live="polite" hidden><span data-toast-icon></span><span data-toast-text></span></div>
</body>
</html>
