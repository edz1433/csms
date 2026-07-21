@props(['delivery'])

<div class="flex items-center justify-end gap-1">
    <x-ui.icon-btn icon="eye" variant="view" :href="route('deliveries.show', $delivery)" title="View receipt" />
</div>
