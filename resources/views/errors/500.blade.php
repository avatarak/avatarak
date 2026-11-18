@php
    \App\Http\Middleware\SetLocale::apply(request());
    $rtl = app()->getLocale() !== 'en';
    $t = fn (string $fa, string $en) => $rtl ? $fa : $en;
@endphp
@extends('errors.layout', ['code' => 500])
@section('title', $t('مشکلی از سمت ما پیش آمد', 'Something went wrong on our side'))
@section('message', $t('خطایی غیرمنتظره رخ داد و گزارشش ثبت شد. چند لحظه‌ی دیگر دوباره امتحان کنید؛ داده‌های شما سالم است.', 'An unexpected error occurred and it has been logged. Please try again in a moment; your data is safe.'))
@section('art')
<svg class="art" width="132" height="132" viewBox="0 0 132 132" aria-hidden="true"><circle cx="66" cy="66" r="58" fill="var(--tint)"/><circle cx="66" cy="66" r="40" fill="var(--brand)"/><path d="M50 58l10 5M82 58l-10 5" stroke="var(--card)" stroke-width="4.5" stroke-linecap="round"/><path d="M52 86q14-13 28 0" fill="none" stroke="var(--card)" stroke-width="4.5" stroke-linecap="round"/><path d="M104 22l-6 14h9l-7 15" fill="none" stroke="var(--brand)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>
@endsection
