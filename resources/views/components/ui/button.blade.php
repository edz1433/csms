@props([
    'variant' => 'primary', // primary | secondary | ghost | danger
    'type' => 'button',
    'href' => null,
    'icon' => null,
])

@php
    $tone = [
        'primary'   => 'bg-cpsu-green hover:bg-cpsu-green-dark text-white',
        'secondary' => 'bg-cpsu-gold hover:bg-cpsu-gold-dark text-cpsu-black',
        'ghost'     => 'bg-white border border-cpsu-border text-cpsu-black hover:bg-cpsu-bg',
        'danger'    => 'bg-cpsu-danger hover:bg-red-700 text-white',
    ][$variant] ?? 'bg-cpsu-green hover:bg-cpsu-green-dark text-white';

    $classes = "inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all active:scale-95 disabled:opacity-60 $tone";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<i data-lucide="{{ $icon }}" class="w-4 h-4"></i>@endif {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<i data-lucide="{{ $icon }}" class="w-4 h-4"></i>@endif {{ $slot }}
    </button>
@endif
