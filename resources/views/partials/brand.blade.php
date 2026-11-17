@php
    $tokens = collect(config('brand.colors'))
        ->filter(fn ($v) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v))
        ->map(fn ($v, $k) => "--{$k}:{$v}")->implode(';');
@endphp
@if($tokens)<style>:root{ {!! $tokens !!} }</style>@endif
