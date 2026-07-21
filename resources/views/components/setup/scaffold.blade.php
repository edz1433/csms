@props([
    'resource',            // e.g. 'locations'
    'singular',            // e.g. 'Location'
    'storeUrl',
    'updateUrl',           // template containing __ID__
    'ajaxUrl',
    'columns' => [],       // array of DataTables column defs
    'blank' => [],         // blank form object
    'order' => [[0, 'asc']],
    'tableId' => null,
])

@php $tableId = $tableId ?? ($resource.'-table'); @endphp

<div x-data="setupForm({
        resource: @js($resource),
        singular: @js($singular),
        storeUrl: @js($storeUrl),
        updateUrl: @js($updateUrl),
        blank: {{ Illuminate\Support\Js::from($blank) }}
     })">

    {{-- Toolbar --}}
    <div class="flex items-center justify-between gap-3 mb-4">
        <p class="text-sm text-gray-500 hidden sm:block">Manage {{ Str::lower(Str::plural($singular)) }} used across the system.</p>
        <x-action-guard>
            <x-ui.button variant="primary" icon="plus" onclick="window.openCreate('{{ $resource }}')">
                New {{ $singular }}
            </x-ui.button>
        </x-action-guard>
    </div>

    {{-- Table card --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4" data-aos="fade-up">
        <div class="overflow-x-auto">
            <table id="{{ $tableId }}" class="w-full text-sm"></table>
        </div>
    </div>

    {{-- Create / Edit modal --}}
    <x-ui.modal :name="$resource.'-form'" :title="'New '.$singular">
        <form @submit.prevent="submit()" class="space-y-4">
            {{ $slot }}

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-cpsu-border">
                <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', '{{ $resource }}-form')">Cancel</x-ui.button>
                <x-ui.button variant="primary" type="submit" x-bind:disabled="submitting">
                    <span x-show="!submitting" x-text="mode === 'create' ? 'Create' : 'Save changes'"></span>
                    <span x-show="submitting" x-cloak>Saving…</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var dt = CPSU.dataTable('#{{ $tableId }}', @js($ajaxUrl), {{ Illuminate\Support\Js::from($columns) }}, { order: {{ Illuminate\Support\Js::from($order) }} });
    window.reloadTable['{{ $resource }}'] = function () { dt.ajax.reload(null, false); };
  });
</script>
@endpush
