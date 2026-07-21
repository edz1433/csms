@props([
    'edit' => null,       // [resourceKey, dataArray]  -> opens edit modal
    'deleteUrl' => null,
    'viewUrl' => null,
    'label' => 'record',
    'resource' => null,   // key for DataTable reload after AJAX delete
])

<div class="flex items-center justify-end gap-1">
    @if ($viewUrl)
        <x-ui.icon-btn icon="eye" variant="view" :href="$viewUrl" title="View" />
    @endif

    <x-action-guard>
        @if ($edit)
            <x-ui.icon-btn
                icon="pencil"
                variant="edit"
                title="Edit"
                onclick="window.openEdit('{{ $edit[0] }}', {{ Illuminate\Support\Js::from($edit[1]) }})" />
        @endif

        @if ($deleteUrl)
            <x-ui.icon-btn
                icon="trash-2"
                variant="danger"
                title="Delete"
                onclick="CPSU.deleteResource('{{ $deleteUrl }}', '{{ $label }}', @js($resource ?? $edit[0] ?? null))" />
        @endif
    </x-action-guard>
</div>
