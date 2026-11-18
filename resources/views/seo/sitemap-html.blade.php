<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Sitemap — {{ config('brand.name_en') ?: config('brand.name') }}</title>
    <style>
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif; background:#F6F7F7; color:#101517; }
        main { max-width:56rem; margin:0 auto; padding:2rem 1rem 3rem; }
        h1 { font-size:1.5rem; margin:0 0 .25rem; } p.sub { margin:0 0 1.5rem; color:#5B6470; font-size:.9rem; }
        table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #DCDCDE; border-radius:8px; overflow:hidden; }
        th, td { text-align:left; padding:.65rem .9rem; border-bottom:1px solid #ECEDEE; font-size:.9rem; vertical-align:top; }
        th { background:#F0F2F5; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:#5B6470; } tr:last-child td { border-bottom:0; }
        a { color:#1D4FC4; text-decoration:none; word-break:break-all; } a:hover { text-decoration:underline; }
        .muted { color:#5B6470; white-space:nowrap; }
        .tag { display:inline-block; margin:0 .3rem .2rem 0; padding:.05rem .5rem; border-radius:999px; background:#E8EEFC; font-size:.75rem; }
    </style>
</head>
<body>
<main>
    <h1>{{ $index ? 'Sitemap index' : 'Sitemap' }}</h1>
    <p class="sub">{{ count($rows) }} {{ $index ? 'sitemap file(s)' : 'URL(s)' }}. Search engines receive this list as plain XML; this page is only for people.</p>
    <table>
        <tr>
            @if($index)<th>Sitemap</th>@else<th>URL</th><th>Last modified</th><th>Languages</th>@endif
        </tr>
        @foreach($rows as $r)
            <tr>
                <td><a href="{{ $r['loc'] }}">{{ $r['loc'] }}</a></td>
                @unless($index)
                    <td class="muted">{{ $r['lastmod'] ?: '—' }}</td>
                    <td>@foreach($r['langs'] as $hl => $href)<a class="tag" href="{{ $href }}">{{ $hl }}</a>@endforeach</td>
                @endunless
            </tr>
        @endforeach
    </table>
</main>
</body>
</html>
