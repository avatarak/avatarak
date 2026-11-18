@php
    \App\Http\Middleware\SetLocale::apply(request());
    $rtl = app()->getLocale() !== 'en';
    $t = fn (string $fa, string $en) => $rtl ? $fa : $en;
@endphp
@extends('errors.layout', ['code' => 403])
@section('title', $t('اجازه‌ی دسترسی ندارید', 'You do not have access'))
@section('message', $t('این بخش برای حساب شما باز نیست. اگر فکر می‌کنید اشتباهی رخ داده، با حساب دیگری وارد شوید.', 'This area is not open to your account. If you think this is a mistake, try signing in with another account.'))
@section('art')
<svg class="art" width="132" height="132" viewBox="0 0 132 132" aria-hidden="true"><circle cx="66" cy="66" r="58" fill="var(--tint)"/><circle cx="66" cy="50" r="15" fill="var(--brand)" opacity=".35"/><path d="M36 100c4-18 17-26 30-26s26 8 30 26z" fill="var(--brand)" opacity=".35"/><rect x="46" y="68" width="40" height="32" rx="7" fill="var(--brand)"/><path d="M54 68V58a12 12 0 0 1 24 0v10" fill="none" stroke="var(--brand)" stroke-width="6" stroke-linecap="round"/><circle cx="66" cy="83" r="4.5" fill="var(--card)"/><path d="M66 86v6" stroke="var(--card)" stroke-width="4" stroke-linecap="round"/></svg>
@endsection
