@props(['release'])

<div class="flex items-center justify-end gap-1">
    <x-ui.icon-btn icon="eye" variant="view" :href="route('releases.pdf', $release)"
        target="_blank" rel="noopener" title="View RIS (PDF)" />

    @if (auth()->user()?->isAdministrator())
        {{-- Reversal: puts the issued quantities back on hand, then deletes the RIS.
             Js::from (not @js) — directives are not compiled inside a component
             attribute value, they would be emitted verbatim into the onclick. --}}
        <x-ui.icon-btn icon="undo-2" variant="danger" title="Return stock & delete release"
            onclick="CPSU.returnRelease({{ Illuminate\Support\Js::from(route('releases.destroy', $release)) }}, {{ Illuminate\Support\Js::from($release->ris_number) }})" />
    @endif
</div>
