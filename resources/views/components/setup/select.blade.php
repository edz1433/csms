@props([
    'label',
    'name',
    'options' => [],       // [value => label]
    'required' => false,
    'placeholder' => 'Choose…',
])

<div class="space-y-1">
    <label class="block text-sm font-medium text-cpsu-black">
        {{ $label }} @if($required)<span class="text-cpsu-danger">*</span>@endif
    </label>
    <select
        x-model="form.{{ $name }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20 bg-white']) }}
        x-bind:class="err('{{ $name }}') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    <p x-show="err('{{ $name }}')" x-cloak x-text="err('{{ $name }}')" class="text-xs text-cpsu-danger"></p>
</div>
