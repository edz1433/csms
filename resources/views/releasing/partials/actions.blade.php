@props(['release'])

<div class="flex items-center justify-end gap-1">
    <x-ui.icon-btn icon="eye" variant="view" :href="route('releases.pdf', $release)"
        target="_blank" rel="noopener" title="View RIS (PDF)" />

    @if (auth()->user()?->isAdministrator())
        {{-- Reversal: puts the issued quantities back on hand, then deletes the RIS. --}}
        <x-ui.icon-btn icon="undo-2" variant="danger" title="Return stock & delete release"
            onclick="CPSU.returnRelease('{{ route('releases.destroy', $release) }}', @js($release->ris_number))" />
    @endif
</div>
