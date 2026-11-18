{{-- Fetch the fonts the first paint needs right away, so text does not jump when they arrive (layout shift) --}}
@php
    $preload = [];
    try {
        $preload[] = \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2');
        if (app()->getLocale() !== 'en') {
            $preload[] = \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource-variable/vazirmatn/files/vazirmatn-arabic-wght-normal.woff2');
        }
    } catch (\Throwable) {
        $preload = [];      // no build (tests, first run): the page works without the hint
    }
@endphp
@foreach($preload as $font)
    <link rel="preload" as="font" type="font/woff2" href="{{ $font }}" crossorigin>
@endforeach
