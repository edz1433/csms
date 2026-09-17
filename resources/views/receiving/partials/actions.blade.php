@props(['delivery'])

<div class="flex items-center justify-end gap-1">
    <x-ui.icon-btn icon="eye" variant="view" :href="route('deliveries.show', $delivery)" title="View receipt" />
    @if ($delivery->isEditable())
        <x-action-guard>
            <x-ui.icon-btn icon="{{ $delivery->hasLines() ? 'pencil' : 'package-plus' }}" variant="edit"
                           :href="route('deliveries.edit', $delivery)"
                           title="{{ $delivery->hasLines() ? 'Edit / record a later batch' : 'Add the items received' }}" />
        </x-action-guard>
    @endif
</div>
