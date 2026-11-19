@php($dir = $rtl ? 'rtl' : 'ltr')
@if($side === 'front')
    <div class="bc bc-{{ $th->style }}" dir="{{ $dir }}">
        @if($th->style === 'classic')
            <div class="hd"><img class="ph" src="{{ $avatar }}" alt=""><div class="who"><div class="nm">{{ $name }}</div>@if($sub)<div class="sb">{{ $sub }}</div>@endif</div></div>
            <div class="rule"></div>
            <div class="ft"><div class="ct"><b dir="ltr">{{ $shown }}</b>@if($phone)<span dir="ltr">{{ $phone }}</span>@endif</div><div class="q">{!! $qrDark !!}</div></div>
        @elseif($th->style === 'band')
            <div class="band"></div>
            <img class="ph" src="{{ $avatar }}" alt="">
            <div class="who"><div class="nm">{{ $name }}</div>@if($sub)<div class="sb">{{ $sub }}</div>@endif<div class="pill"><b dir="ltr">{{ $shown }}</b>@if($phone)<span dir="ltr">{{ $phone }}</span>@endif</div></div>
        @else
            <svg class="deco" viewBox="0 0 85 55" aria-hidden="true"><circle cx="80" cy="0" r="17" fill="none" stroke="#fff" stroke-opacity=".11" stroke-width="7"/><circle cx="5" cy="55" r="12" fill="none" stroke="#fff" stroke-opacity=".09" stroke-width="4.5"/></svg>
            <img class="ph" src="{{ $avatar }}" alt="">
            <div class="who"><div class="nm">{{ $name }}</div>@if($sub)<div class="sb">{{ $sub }}</div>@endif</div>
            <div class="q">{!! $qrDark !!}</div>
            <div class="ct"><b dir="ltr">{{ $shown }}</b>@if($phone)<span dir="ltr">{{ $phone }}</span>@endif</div>
        @endif
    </div>
@else
    <div class="bc bc-{{ $th->style }} back" dir="{{ $dir }}">
        @if($th->style === 'band')<div class="ttl">{{ $t['view'] }}</div>@endif
        @if($th->style === 'bold')<svg class="deco" viewBox="0 0 85 55" aria-hidden="true"><circle cx="5" cy="2" r="17" fill="none" stroke="{{ $th->accent }}" stroke-opacity=".10" stroke-width="7"/><circle cx="82" cy="56" r="13" fill="none" stroke="{{ $th->accent }}" stroke-opacity=".08" stroke-width="4.5"/></svg>@endif
        <div class="q">{!! $th->style === 'bold' ? $qrSvg : $qrDark !!}</div>
        <div class="cap">{{ $t['scan'] }}</div>
    </div>
@endif
