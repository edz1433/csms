@props([
    'icon',
    'title' => '',
    'href' => null,
    'variant' => 'default', // default | view | edit | danger
])

@php
    $tone = [
        'default' => 'text-gray-500 hover:text-cpsu-green hover:bg-cpsu-green/10',
        'view'    => 'text-blue-600 hover:text-blue-700 hover:bg-blue-50',
        'edit'    => 'text-cpsu-green hover:text-cpsu-green-dark hover:bg-cpsu-green/10',
        'danger'  => 'text-cpsu-danger hover:text-red-700 hover:bg-red-50',
    ][$variant] ?? 'text-gray-500 hover:text-cpsu-green hover:bg-cpsu-green/10';

    $classes = "inline-flex items-center justify-center h-8 w-8 rounded-lg transition-all active:scale-90 $tone";
@endphp

@if ($href)
    <a href="{{ $href }}" title="{{ $title }}" {{ $attributes->merge(['class' => $classes]) }}>
        <i data-lucide="{{ $icon }}" class="w-[17px] h-[17px]"></i>
    </a>
@else
    <button type="button" title="{{ $title }}" {{ $attributes->merge(['class' => $classes]) }}>
        <i data-lucide="{{ $icon }}" class="w-[17px] h-[17px]"></i>
    </button>
@endif
