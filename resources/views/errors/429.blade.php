@php
    \App\Http\Middleware\SetLocale::apply(request());
    $rtl = app()->getLocale() !== 'en';
    $t = fn (string $fa, string $en) => $rtl ? $fa : $en;
@endphp
@extends('errors.layout', ['code' => 429])
@section('title', $t('درخواست‌ها زیاد بود', 'Too many requests'))
@section('message', $t('کمی صبر کنید و دوباره تلاش کنید. این محدودیت جلوی سوءاستفاده را می‌گیرد.', 'Please wait a little and try again. This limit protects the service from abuse.'))
@section('art')
<svg class="art" width="132" height="132" viewBox="0 0 132 132" aria-hidden="true"><circle cx="66" cy="66" r="58" fill="var(--tint)"/><circle cx="66" cy="66" r="38" fill="none" stroke="var(--brand)" stroke-width="6"/><path d="M66 44v23l15 9" fill="none" stroke="var(--brand)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>
@endsection
