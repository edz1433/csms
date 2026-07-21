@props(['model', 'url'])
@php $on = (bool) $model->is_active; @endphp

{{-- Inline is_active switch. Accounting staff sees a static badge (no writes). --}}
@if (auth()->user()->isAccountingStaff())
    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $on ? 'bg-cpsu-green/10 text-cpsu-green' : 'bg-gray-100 text-gray-500' }}">
        {{ $on ? 'Active' : 'Inactive' }}
    </span>
@else
    <button type="button" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}"
            onclick="CPSU.toggleActive('{{ $url }}', this)"
            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $on ? 'bg-cpsu-green' : 'bg-gray-300' }}">
        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $on ? 'translate-x-5' : 'translate-x-1' }}"></span>
    </button>
@endif
