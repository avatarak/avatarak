@php
    $brandJs = [
        'name' => config('brand.name'),
        'name_en' => config('brand.name_en'),
        'logo_url' => config('brand.logo_url'),
        'tagline' => config('brand.tagline'),
        'tagline_en' => config('brand.tagline_en'),
        'locale' => config('brand.default_locale'),
        'pageLocale' => request()->attributes->get('landing'),       // the home page's address decides its language
    ];
    // Only the home page is for search engines; the dashboard and the admin panel share this shell but are not.
    $landingLang = request()->attributes->get('landing');
    $home = $landingLang !== null;
    $seoLoc = $home ? $landingLang : (app()->getLocale() === 'en' ? 'en' : 'fa');
    $homeUrl = \App\Services\Seo::langUrl('/', $seoLoc);
    $homeUrl = $seoLoc === \App\Services\Seo::defaultLang() ? url('/') : url('/'.$seoLoc);
    $seoBrand = $seoLoc === 'en' ? (config('brand.name_en') ?: config('brand.name')) : config('brand.name');
    $landing = $home ? (json_decode(@file_get_contents(resource_path('data/landing-seo.json')), true)[$seoLoc] ?? null) : null;
    $tagline = $seoLoc === 'en' ? (config('brand.tagline_en') ?: config('brand.tagline')) : config('brand.tagline');
    $desc = $landing['subtitle'] ?? $tagline;
    $ogImage = url('/brand/og-default.jpg');
    $ld = null;
    if ($landing) {
        $ld = ['@context' => 'https://schema.org', '@graph' => array_values(array_filter([
            ['@type' => 'WebSite', '@id' => url('/').'#website', 'url' => $homeUrl, 'name' => $seoBrand, 'description' => $desc, 'inLanguage' => $seoLoc],
            ['@type' => 'Organization', '@id' => url('/').'#org', 'name' => $seoBrand, 'url' => url('/'),
                'logo' => config('brand.logo_url') ?: url('/brand/icon-512.png'), 'sameAs' => ['https://github.com/avatarak/avatarak']],
            ['@type' => 'SoftwareApplication', 'name' => $seoBrand, 'url' => url('/'), 'applicationCategory' => 'WebApplication', 'operatingSystem' => 'Web',
                'description' => $desc, 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'IRR'], 'inLanguage' => $seoLoc],
            // the same questions and answers the page shows, word for word
            ! empty($landing['faq']) ? ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($qa) => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $landing['faq'])] : null,
        ]))];
    }
    // The home page is pre-rendered at build time (npm run build): paint it straight from the HTML, then hydrate.
    // A custom logo changes the markup, so such installs keep the plain (crawler-only) text below.
    $prerender = null;
    if ($landing && ! config('brand.logo_url') && is_file($pf = resource_path("data/landing-prerender-{$seoLoc}.html"))) {
        $tokens = ['@@BRAND_NAME@@' => $brandJs['name'], '@@BRAND_NAME_EN@@' => $brandJs['name_en'] ?: $brandJs['name'], '@@TAGLINE@@' => $brandJs['tagline'], '@@TAGLINE_EN@@' => $brandJs['tagline_en'] ?: $brandJs['tagline'],
            '@@ORIGIN@@' => rtrim(url('/'), '/'), '@@HOST@@' => request()->getHttpHost()];
        $prerender = strtr(file_get_contents($pf), array_map('e', $tokens));
    }
@endphp
<!doctype html>
<html lang="{{ $seoLoc }}" dir="{{ $seoLoc === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $home ? $seoBrand.' — '.$tagline : $seoBrand }}</title>
    @if(! $home)
        <meta name="robots" content="noindex, nofollow">
    @else
        <meta name="description" content="{{ $desc }}">
        <link rel="canonical" href="{{ $homeUrl }}">
        @foreach(['fa', 'en'] as $hl)
            <link rel="alternate" hreflang="{{ $hl }}" href="{{ $hl === \App\Services\Seo::defaultLang() ? url('/') : url('/'.$hl) }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ url('/') }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $seoBrand }}">
        <meta property="og:title" content="{{ $seoBrand }} — {{ $tagline }}">
        <meta property="og:description" content="{{ $desc }}">
        <meta property="og:url" content="{{ $homeUrl }}">
        <meta property="og:locale" content="{{ $seoLoc === 'en' ? 'en_US' : 'fa_IR' }}">
        <meta property="og:locale:alternate" content="{{ $seoLoc === 'en' ? 'fa_IR' : 'en_US' }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $seoBrand }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoBrand }} — {{ $tagline }}">
        <meta name="twitter:description" content="{{ $desc }}">
        <meta name="twitter:image" content="{{ $ogImage }}">
        @if($ld)<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!}</script>@endif
    @endif
    @include('partials.font-preload')
    @include('partials.brand')
    <x-brand-head />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.__BRAND__ = @json($brandJs);</script>
</head>
<body>
    {{-- What a crawler (or a browser without JavaScript) reads. Vue replaces it the moment the app starts. --}}
    @if($prerender)
        {{-- no whitespace between the tags and the markup: the browser attaches to it node by node --}}
        <div id="app" data-prerendered>{!! $prerender !!}</div>
    @else
    <div id="app">
        @if($landing)
            <div style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap">
                <h1>{{ $landing['title'] }}</h1>
                <p>{{ $landing['subtitle'] }}</p>
                @foreach($landing['features'] as [$ft, $fx])
                    <h2>{{ $ft }}</h2><p>{{ $fx }}</p>
                @endforeach
                <h2>{{ $landing['faqTitle'] }}</h2>
                @foreach($landing['faq'] as [$q, $a])
                    <h3>{{ $q }}</h3><p>{{ $a }}</p>
                @endforeach
                <nav><a href="/docs">{{ $seoLoc === 'en' ? 'Documentation' : 'مستندات' }}</a> <a href="/terms">{{ $seoLoc === 'en' ? 'Terms' : 'شرایط استفاده' }}</a> <a href="/privacy">{{ $seoLoc === 'en' ? 'Privacy' : 'حریم خصوصی' }}</a></nav>
            </div>
        @endif
    </div>
    @endif
    <noscript>{{ $seoLoc === 'en' ? 'JavaScript is required to sign in and manage your profile.' : 'برای ورود و مدیریت پروفایل به جاوااسکریپت نیاز است.' }}</noscript>
</body>
</html>
