@props(['name', 'size' => 22])
@php($ui = json_decode(file_get_contents(resource_path('data/ui-icons.json')), true) ?: [])
{{-- Static markup generated from @tabler/icons (trusted, never user input) --}}
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! $ui[$name] ?? '' !!}</svg>
