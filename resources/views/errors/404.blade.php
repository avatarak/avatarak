@php
    \App\Http\Middleware\SetLocale::apply(request());
    $rtl = app()->getLocale() !== 'en';
    $t = fn (string $fa, string $en) => $rtl ? $fa : $en;
@endphp
@extends('errors.layout', ['code' => 404])
@section('title', $t('این صفحه پیدا نشد', 'Page not found'))
@section('message', $t('آدرس را اشتباه وارد کرده‌اید یا این پروفایل دیگر وجود ندارد یا خصوصی است. نام‌کاربری‌ها را می‌توانید خودتان بسازید.', 'The address may be mistyped, or this profile no longer exists or is private. Usernames are yours to claim.'))
@section('art')
<svg class="art" width="132" height="132" viewBox="0 0 132 132" aria-hidden="true"><circle cx="66" cy="66" r="58" fill="var(--tint)"/><circle cx="66" cy="66" r="58" fill="none" stroke="var(--brand)" stroke-width="3" stroke-dasharray="3 9" stroke-linecap="round"/><circle cx="66" cy="54" r="15" fill="none" stroke="var(--brand)" stroke-width="3" stroke-dasharray="3 7" stroke-linecap="round"/><path d="M36 100c4-18 17-26 30-26s26 8 30 26" fill="none" stroke="var(--brand)" stroke-width="3" stroke-dasharray="3 7" stroke-linecap="round"/><text x="66" y="62" text-anchor="middle" font-size="26" font-weight="700" fill="var(--brand)" font-family="system-ui,sans-serif">?</text></svg>
@endsection
