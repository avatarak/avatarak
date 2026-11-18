<!doctype html>
<html lang="{{ $rtl ? 'fa' : 'en' }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <x-brand-head />
    <base target="_blank">
    <title>{{ $name }}</title>
    @include('partials.brand')
    @vite(['resources/css/app.css'])
</head>
<body class="bg-transparent">
<a href="{{ route('profile.show', $p->username) }}" rel="noopener" class="card m-2 flex items-center gap-4 p-4 no-underline">
    @if($avatar)<img src="{{ $avatar }}" alt="" width="64" height="64" class="size-16 shrink-0 rounded-full bg-line object-cover">@else<img src="{{ route('profile.avatar', ['username' => $p->username, 's' => 128, 'd' => 'initials']) }}" alt="" width="64" height="64" class="size-16 shrink-0 rounded-full bg-line object-cover">@endif
    <span class="min-w-0">
        <span class="block truncate text-lg font-bold">{{ $name }}</span>
        @if($p->headline)<span class="block truncate text-sm text-ink-2">{{ $p->headline }}</span>@endif
        <span class="mt-1 block text-sm font-semibold text-brand">{{ $rtl ? 'مشاهده‌ی پروفایل' : 'View profile' }} →</span>
    </span>
</a>
</body>
</html>
