@extends('layouts.app')

@section('title', 'Stock Card Report')
@section('header', 'Reports')
@section('subheader', 'Stock Card — running balance per item, generated as PDF')

@section('content')
@include('reports.partials.tabs', ['active' => 'stock-card'])

<div x-data="stockCardReport({
        base: @js(route('reports.stock-card.pdf')),
        from: @js($from), to: @js($to),
        records: {{ Illuminate\Support\Js::from($items) }},
     })">
    {{-- Filters --}}
    <div class="relative z-20 bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5 mb-4" data-aos="fade-up">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            @include('reports.partials.scope-filters', ['prefix' => 'sc'])

            <div class="sm:col-span-6">
                <label class="block text-xs font-medium text-gray-500 mb-1">Item</label>
                <select id="sc-item" x-model="itemId" autocomplete="off"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                    <option value="">Search item by name or code…</option>
                    @foreach ($items as $it)
                        <option value="{{ $it['value'] }}">{{ $it['text'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Date Range</label>
                <div class="relative">
                    <i data-lucide="calendar" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input x-ref="range" type="text" readonly placeholder="Select date range"
                           class="w-full rounded-lg border border-cpsu-border pl-9 pr-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none cursor-pointer">
                </div>
            </div>
            <div class="sm:col-span-2">
                <x-ui.button variant="primary" icon="file-text" class="w-full h-[38px]" x-on:click="generate()">Generate PDF</x-ui.button>
            </div>
        </div>

        {{-- Optional extras --}}
        <div class="mt-3 pt-3 border-t border-cpsu-border">
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                <input type="checkbox" x-model="withQr"
                       class="rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green">
                <i data-lucide="qr-code" class="w-4 h-4 text-cpsu-green"></i>
                Include the item's QR inventory tag on the card
            </label>
        </div>
    </div>

    @include('reports.partials.pdf-frame', [
        'title' => 'Stock Card',
        'emptyText' => 'Choose an item and date range, then click Generate PDF to preview the stock card.',
    ])
</div>

@push('scripts')
<script>
  function stockCardReport(cfg) {
    return {
      itemId: '', fund_cluster_id: '', account_title_id: '', withQr: false,
      from: cfg.from, to: cfg.to, url: '',
      init() {
        var self = this;
        new TomSelect('#sc-fund', { create: false, allowEmptyOption: true });
        new TomSelect('#sc-account', { create: false, allowEmptyOption: true });
        var picker = new TomSelect('#sc-item', { create: false, allowEmptyOption: true });
        this.applyScope = CPSU.scopePicker(picker, cfg.records, 'Search item by name or code…');
        this.$watch('fund_cluster_id', function () { self.rescope(); });
        this.$watch('account_title_id', function () { self.rescope(); });
        flatpickr(this.$refs.range, {
          mode: 'range', dateFormat: 'Y-m-d', defaultDate: [cfg.from, cfg.to],
          onChange: function (d) { if (d.length === 2) { self.from = self.fmt(d[0]); self.to = self.fmt(d[1]); } },
        });
      },
      rescope() {
        var res = this.applyScope(this.fund_cluster_id, this.account_title_id);
        this.itemId = res.kept;
        if (!res.count) { CPSU.toast('No items match these filters.', 'info'); }
      },
      fmt(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); },
      generate() {
        if (!this.itemId) { CPSU.toast('Please select an item first.', 'error'); return; }
        var p = new URLSearchParams({ format: 'pdf', item_id: this.itemId, from: this.from, to: this.to, _: Date.now() });
        if (this.withQr) { p.set('qr', 1); }
        this.url = cfg.base + '?' + p.toString();
      },
    };
  }
</script>
@endpush
@endsection
