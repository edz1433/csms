@props(['release'])

<div class="flex items-center justify-end gap-1">
    <x-ui.icon-btn icon="eye" variant="view" :href="route('releases.show', $release)" title="View RIS" />
</div>
