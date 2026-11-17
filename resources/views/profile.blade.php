<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $p->name_fa ?: ($p->name_en ?: $p->username) }} — {{ config('app.name') }}</title>
    @unless($indexable)<meta name="robots" content="noindex, nofollow">@endunless
    @if($p->bio)<meta name="description" content="{{ \Illuminate\Support\Str::limit($p->bio, 160) }}">@endif
    <style>
        body{font-family:Tahoma,sans-serif;background:#f3f4f6;margin:0;display:flex;justify-content:center;padding:2rem 1rem}
        .card{background:#fff;border-radius:16px;padding:2rem;max-width:420px;width:100%;text-align:center;box-shadow:0 2px 12px #0001}
        img,.ph{width:128px;height:128px;border-radius:50%;object-fit:cover;background:#e5e7eb}
        h1{margin:.8rem 0 .2rem;font-size:1.4rem}.en{color:#6b7280;direction:ltr}.bio{margin:1rem 0;line-height:1.9}
        a{color:#2563eb;text-decoration:none}ul{list-style:none;padding:0;display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap}
    </style>
</head>
<body>
<main class="card">
    @if($avatarUrl)<img src="{{ $avatarUrl }}" alt="{{ $p->username }}" width="128" height="128">@else<div class="ph" style="margin:auto"></div>@endif
    <h1>{{ $p->name_fa ?: $p->username }}</h1>
    @if($p->name_en)<div class="en">{{ $p->name_en }}</div>@endif
    <div class="en">&#64;{{ $p->username }}</div>
    @if($p->bio)<p class="bio">{{ $p->bio }}</p>@endif
    @if($p->website)<p><a href="{{ $p->website }}" rel="nofollow noopener ugc" target="_blank">{{ parse_url($p->website, PHP_URL_HOST) }}</a></p>@endif
    @if($socials)
        <ul>@foreach($socials as $net => $url)<li><a href="{{ $url }}" rel="nofollow noopener ugc" target="_blank">{{ $net }}</a></li>@endforeach</ul>
    @endif
</main>
</body>
</html>
