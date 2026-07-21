@extends('layouts.app')

@section('title', 'Supply Ledger Card')
@section('header', 'Supply Ledger Card')
@section('subheader', 'Appendix 57 — per-item receipt / issue / balance ledger')

@section('content')
<div x-data="ledgerCard({
        base: @js(route('ledger.pdf')),
        from: @js(now()->startOfYear()->toDateString()),
        to: @js(now()->endOfYear()->toDateString()),
     })">

    {{-- Filter form --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5 mb-4" data-aos="fade-up">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">Item</label>
                <select id="ledger-item" x-model="itemId" autocomplete="off"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                    <option value="">Search item by name or code…</option>
                    @foreach ($items as $it)
                        <option value="{{ $it->id }}">{{ $it->stock_number ? $it->stock_number.' — ' : '' }}{{ $it->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                <input type="date" x-model="from"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                <input type="date" x-model="to"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
        </div>
        <div class="flex items-center gap-2 mt-4">
            <x-ui.button variant="primary" icon="file-text" x-on:click="generate()">Generate</x-ui.button>
            <x-ui.button variant="ghost" icon="external-link" x-show="url" x-cloak x-on:click="openTab()">Open in new tab</x-ui.button>
            <p x-show="!itemId" class="text-xs text-gray-400">Select an item to generate the ledger card.</p>
        </div>
    </div>

    {{-- Generated card (iframe) --}}
    <div x-show="url" x-cloak data-aos="fade-up"
         class="bg-white rounded-xl border border-cpsu-border shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-cpsu-border flex items-center gap-2">
            <i data-lucide="notebook-text" class="w-4 h-4 text-cpsu-green"></i>
            <h3 class="font-bold text-sm">Supplies Ledger Card</h3>
        </div>
        <iframe x-ref="frame" :src="url" class="w-full block" style="height:80vh; border:0;"></iframe>
    </div>

    {{-- Empty state --}}
    <div x-show="!url" x-cloak class="bg-white rounded-xl border border-cpsu-border shadow-sm p-12 text-center text-gray-400">
        <i data-lucide="notebook-text" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
        <p>Choose an item and date range, then click <span class="font-semibold text-gray-500">Generate</span> to preview the ledger card.</p>
    </div>
</div>

@push('scripts')
<script>
  function ledgerCard(cfg) {
    return {
      itemId: '', from: cfg.from, to: cfg.to, url: '',
      init() {
        var self = this;
        new TomSelect('#ledger-item', { create: false, allowEmptyOption: true });
      },
      buildUrl() {
        var p = new URLSearchParams({ item_id: this.itemId, from: this.from, to: this.to });
        return cfg.base + '?' + p.toString();
      },
      generate() {
        if (!this.itemId) { CPSU.toast('Please select an item first.', 'error'); return; }
        // cache-bust so re-generating with new filters always reloads
        this.url = this.buildUrl() + '&_=' + Date.now();
      },
      openTab() { if (this.url) window.open(this.url, '_blank'); },
    };
  }
</script>
@endpush
@endsection
