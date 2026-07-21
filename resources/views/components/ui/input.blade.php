@props([
    'label' => null,
    'name',
    'type' => 'text',
    'required' => false,
    'hint' => null,
])

<div class="space-y-1">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-cpsu-black">
            {{ $label }} @if($required)<span class="text-cpsu-danger">*</span>@endif
        </label>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $attributes->get('id', $name) }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20 outline-none']) }}
    >
    @if ($hint)
        <p class="text-xs text-gray-400">{{ $hint }}</p>
    @endif
</div>
