@extends('layouts.app')

@section('title', 'Items / Stocks')
@section('header', 'Items & Stock Levels')
@section('subheader', 'Master item list with on-hand quantities')

@section('content')
<x-setup.scaffold
    resource="items"
    singular="Item"
    :store-url="route('items.store')"
    :update-url="route('items.update', '__ID__')"
    :ajax-url="route('items.index')"
    :can-write="auth()->user()->isAdministrator()"
    blurb="On-hand quantity is the authoritative stock level, updated by Receiving and Releasing."
    :blank="['id' => null, 'stock_number' => '', 'name' => '', 'description' => '', 'unit_id' => '', 'account_title_id' => '', 'unit_cost' => '', 'on_hand_qty' => 0, 'is_active' => true]"
    :order="[[1, 'asc']]"
    :columns="[
        ['data' => 'stock_number', 'title' => 'Stock No.'],
        ['data' => 'name', 'title' => 'Item'],
        ['data' => 'unit', 'title' => 'Unit', 'className' => 'text-center'],
        ['data' => 'account', 'title' => 'Account Title / RCA'],
        ['data' => 'unit_cost', 'title' => 'Unit Cost', 'className' => 'text-right'],
        ['data' => 'on_hand', 'title' => 'On Hand', 'className' => 'text-right'],
        ['data' => 'status', 'title' => 'Status', 'orderable' => false, 'searchable' => false, 'className' => 'text-center'],
        ['data' => 'action', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]"
>
    {{-- Consolidated QR sheet: every active item's tag in one printable file. --}}
    <x-slot:toolbar>
        <x-ui.button variant="ghost" icon="qr-code" :href="route('inventory.labels')"
                     target="_blank" rel="noopener" title="Print one QR tag per item">
            QR Tags
        </x-ui.button>
    </x-slot:toolbar>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="space-y-1">
            <label class="block text-sm font-medium text-cpsu-black">Item Code</label>
            <input type="text" x-model="form.stock_number" readonly
                   class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-gray-50 text-gray-500 cursor-not-allowed font-mono"
                   :placeholder="mode === 'create' ? 'Auto-generated (e.g. CS00001)' : ''">
            <p class="text-xs text-gray-400">Assigned automatically, starting at CS00001.</p>
        </div>
        <x-setup.select label="Default Unit" name="unit_id" required :options="$units" placeholder="Select unit" />
    </div>
    <x-setup.field label="Item Name" name="name" required placeholder="e.g. Bond Paper A4 (sub. 20)" />

    <div class="space-y-1">
        <label class="block text-sm font-medium text-cpsu-black">Description</label>
        <textarea x-model="form.description" rows="2"
                  class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20"
                  placeholder="Optional notes / specification"></textarea>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-setup.select label="Default Account Title" name="account_title_id" :options="$accountTitles"
            placeholder="Select account title (optional)" />
        <div class="space-y-1">
            <label class="block text-sm font-medium text-cpsu-black">Unit Cost (₱)</label>
            <input type="number" step="0.01" min="0" x-model="form.unit_cost"
                   class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20"
                   placeholder="0.00">
            <p class="text-xs text-gray-400">Used to price issuances on the RSMI report.</p>
        </div>
        <div class="space-y-1">
            <label class="block text-sm font-medium text-cpsu-black">On Hand</label>
            <input type="number" step="0.01" min="0" inputmode="decimal" x-model="form.on_hand_qty"
                   class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20"
                   placeholder="0.00">
            <p class="text-xs text-gray-400">Accepts decimal quantities.</p>
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-cpsu-black select-none">
        <input type="checkbox" x-model="form.is_active" class="rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green/30">
        Active (available for receiving &amp; releasing)
    </label>
</x-setup.scaffold>
@endsection
