@props([
    'label',
    'name',
    'type' => 'text',
    'required' => false,
    'hint' => null,
    'placeholder' => '',
])

<div class="space-y-1">
    <label class="block text-sm font-medium text-cpsu-black">
        {{ $label }} @if($required)<span class="text-cpsu-danger">*</span>@endif
    </label>
    <input
        type="{{ $type }}"
        x-model="form.{{ $name }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20']) }}
        x-bind:class="err('{{ $name }}') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'"
    >
    @if ($hint)<p class="text-xs text-gray-400">{{ $hint }}</p>@endif
    <p x-show="err('{{ $name }}')" x-cloak x-text="err('{{ $name }}')" class="text-xs text-cpsu-danger"></p>
</div>
