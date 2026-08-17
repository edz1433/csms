@props(['item', 'canWrite' => false])

<div class="flex items-center justify-end gap-1">
    <x-ui.icon-btn icon="eye" variant="view" :href="route('items.show', $item)" title="View stock card" />

    <x-ui.icon-btn icon="qr-code" variant="default" title="Print QR tag"
        :href="route('inventory.labels', ['item_id' => $item->id])" target="_blank" rel="noopener" />

    @if ($canWrite)
        <x-ui.icon-btn
            icon="pencil" variant="edit" title="Edit"
            onclick="window.openEdit('items', {{ Illuminate\Support\Js::from($item->only(['id','stock_number','name','description','unit_id','account_title_id','unit_cost','on_hand_qty','is_active'])) }})" />
        <x-ui.icon-btn
            icon="trash-2" variant="danger" title="Delete"
            onclick="CPSU.deleteResource('{{ route('items.destroy', $item) }}', 'item', 'items')" />
    @endif
</div>
