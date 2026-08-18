@props(['delivery'])

<div class="flex items-center justify-end gap-1">
    <x-ui.icon-btn icon="eye" variant="view" :href="route('deliveries.show', $delivery)" title="View receipt" />
    @if ($delivery->isEditable())
        <x-action-guard>
            <x-ui.icon-btn icon="pencil" variant="edit" :href="route('deliveries.edit', $delivery)"
                           title="Edit / record a later batch" />
        </x-action-guard>
    @endif
</div>
