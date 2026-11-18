@php
    \App\Http\Middleware\SetLocale::apply(request());
    $rtl = app()->getLocale() !== 'en';
    $t = fn (string $fa, string $en) => $rtl ? $fa : $en;
@endphp
@extends('errors.layout', ['code' => 503])
@section('title', $t('در حال به‌روزرسانی هستیم', 'We will be right back'))
@section('message', $t('سرویس برای چند دقیقه در دست نگهداری است. به‌زودی برمی‌گردیم.', 'The service is briefly down for maintenance. We will be back shortly.'))
@section('art')
<svg class="art" width="132" height="132" viewBox="0 0 132 132" aria-hidden="true"><circle cx="66" cy="66" r="58" fill="var(--tint)"/><circle cx="66" cy="66" r="38" fill="none" stroke="var(--brand)" stroke-width="6"/><path d="M66 44v23l15 9" fill="none" stroke="var(--brand)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>
@endsection
