@props(['size' => 32, 'face' => '#fff'])
{{-- Avatarak.ir mark: an @ whose inner bowl is an avatar. Ring and disc use currentColor. --}}
<svg {{ $attributes->merge(['class' => 'text-brand shrink-0']) }} width="{{ $size }}" height="{{ $size }}" viewBox="12 12 76 76" aria-hidden="true" focusable="false">
    <path d="M65 38V56A8 8 0 0 0 81 56V50A31 31 0 1 0 68.6 74.8" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="50" cy="50" r="13" fill="currentColor"/>
    <circle cx="50" cy="45" r="4.5" fill="{{ $face }}"/>
    <path d="M39.75 58Q50 47 60.25 58A13 13 0 0 1 39.75 58Z" fill="{{ $face }}"/>
</svg>
