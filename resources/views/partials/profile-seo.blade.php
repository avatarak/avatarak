{{-- canonical, hreflang, description, Open Graph, Twitter Card and structured data for a public profile (see App\Services\Seo) --}}
<meta name="description" content="{{ $seo['description'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
@foreach($seo['alternates'] as $hl => $href)
    <link rel="alternate" hreflang="{{ $hl }}" href="{{ $href }}">
@endforeach
<meta property="og:type" content="profile">
<meta property="og:site_name" content="{{ $brand }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:locale" content="{{ $seo['locale'] }}">
@foreach($seo['alt_locales'] as $al)
    <meta property="og:locale:alternate" content="{{ $al }}">
@endforeach
<meta property="profile:username" content="{{ $p->username }}">
@if($seo['image'])
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta property="og:image:alt" content="{{ $seo['image_alt'] }}">
@endif
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
@if($seo['image'])<meta name="twitter:image" content="{{ $seo['image'] }}">@endif
@if($seo['json_ld'])
<script type="application/ld+json">{!! $seo['json_ld'] !!}</script>
@endif
