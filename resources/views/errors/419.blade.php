@php
    \App\Http\Middleware\SetLocale::apply(request());
    $rtl = app()->getLocale() !== 'en';
    $t = fn (string $fa, string $en) => $rtl ? $fa : $en;
@endphp
@extends('errors.layout', ['code' => 419])
@section('title', $t('نشست شما منقضی شد', 'Your session expired'))
@section('message', $t('برای امنیت شما، فرم بعد از مدتی بی‌حرکتی منقضی می‌شود. صفحه را دوباره باز کنید و ادامه دهید.', 'For your security a form expires after a while. Reload the page and carry on.'))
@section('art')
<svg class="art" width="132" height="132" viewBox="0 0 132 132" aria-hidden="true"><circle cx="66" cy="66" r="58" fill="var(--tint)"/><circle cx="66" cy="66" r="38" fill="none" stroke="var(--brand)" stroke-width="6"/><path d="M66 44v23l15 9" fill="none" stroke="var(--brand)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>
@endsection
