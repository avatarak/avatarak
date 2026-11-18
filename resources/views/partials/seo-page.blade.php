{{-- canonical, hreflang, Open Graph, Twitter Card and structured data for a content page (see App\Services\Seo::docs / legal) --}}
<meta name="description" content="{{ $seo['description'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
@foreach($seo['alternates'] as $hl => $href)
    <link rel="alternate" hreflang="{{ $hl }}" href="{{ $href }}">
@endforeach
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:site_name" content="{{ $seo['brand'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:locale" content="{{ $seo['og_locale'] }}">
@foreach($seo['alt_locales'] as $al)
    <meta property="og:locale:alternate" content="{{ $al }}">
@endforeach
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
<script type="application/ld+json">{!! $seo['json_ld'] !!}</script>
