@props(['color' => 'gray'])

@php
    $map = [
        'green'  => 'bg-cpsu-green/10 text-cpsu-green',
        'gold'   => 'bg-cpsu-gold/20 text-cpsu-gold-dark',
        'red'    => 'bg-red-100 text-red-700',
        'blue'   => 'bg-blue-100 text-blue-700',
        'gray'   => 'bg-gray-100 text-gray-600',
        'amber'  => 'bg-amber-100 text-amber-700',
    ][$color] ?? 'bg-gray-100 text-gray-600';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold $map"]) }}>
    {{ $slot }}
</span>
