<div class="bc">
    <div class="bc-main">
        <img class="bc-photo" src="{{ route('profile.avatar', ['username' => $p->username, 's' => 256, 'd' => 'initials']) }}" alt="">
        <div class="bc-name">{{ $name }}</div>
        @if($p->headline)<div class="bc-sub">{{ $p->headline }}</div>@endif
        @if($p->location)<div class="bc-sub">{{ $p->location }}</div>@endif
        <div class="bc-url">{{ $shown }}</div>
    </div>
    <div class="bc-qr">{!! $qrSvg !!}<span>{{ $t['scan'] }}</span></div>
</div>
