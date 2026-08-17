@props(['iar'])

<div class="flex items-center justify-end gap-1">
    <x-ui.icon-btn icon="eye" variant="view" :href="route('iars.show', $iar)" title="View IAR" />
</div>
