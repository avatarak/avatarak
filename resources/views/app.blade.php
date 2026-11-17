@php
    $brandJs = [
        'name' => config('brand.name'),
        'name_en' => config('brand.name_en'),
        'logo_url' => config('brand.logo_url'),
        'tagline' => config('brand.tagline'),
        'tagline_en' => config('brand.tagline_en'),
        'locale' => config('brand.default_locale'),
    ];
@endphp
<!doctype html>
<html lang="{{ config('brand.default_locale') }}" dir="{{ config('brand.default_locale') === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('brand.name') }}</title>
    @include('partials.brand')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.__BRAND__ = @json($brandJs);</script>
</head>
<body>
    <div id="app"></div>
    <noscript>JavaScript is required.</noscript>
</body>
</html>
