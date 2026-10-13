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
        accounts: {{ Illuminate\Support\Js::from($accountTitles->map(fn ($at) => ['value' => $at->id, 'text' => $at->name.' — '.$at->rca_code])) }},
     })">
    {{-- Filters --}}
    <div class="relative z-20 bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5 mb-4" data-aos="fade-up">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            @include('reports.partials.scope-filters', ['prefix' => 'sc'])

            <div class="sm:col-span-6">
                <label class="block text-xs font-medium text-gray-500 mb-1">Item(s)</label>
                <select id="sc-item" multiple autocomplete="off" placeholder="Search items by name or code…"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                    <option value="all">All items</option>
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
        'emptyText' => 'Choose one or more items (or All items) and a date range, then click Generate PDF. Each item prints on its own page.',
    ])
</div>

@push('scripts')
<script>
  function stockCardReport(cfg) {
    return {
      fund_cluster_id: '', account_title_id: '', withQr: false,
      from: cfg.from, to: cfg.to, url: '',
      init() {
        var self = this;
        new TomSelect('#sc-fund', { create: false, allowEmptyOption: true });
        this.accountPicker = new TomSelect('#sc-account', { create: false, allowEmptyOption: true });
        // Multi-select: each chosen item prints as its own stock card page.
        // "All items" stands alone, so picking it clears the others and vice versa.
        this.itemPicker = new TomSelect('#sc-item', {
          create: false, plugins: ['remove_button'], hidePlaceholder: true,
          onItemAdd: function (value) {
            if (value === 'all') { this.setValue(['all'], true); this.close(); }
            else if (this.items.indexOf('all') !== -1) { this.removeItem('all', true); }
          },
        });
        this.applyScope = CPSU.scopePicker(this.itemPicker, cfg.records, null,
          [{ value: 'all', text: 'All items' }]);
        this.$watch('fund_cluster_id', function () {
          // A cleared account title re-scopes the items through its own watcher.
          if (!self.rescopeAccounts()) { self.rescope(); }
        });
        this.$watch('account_title_id', function () { self.rescope(); });
        flatpickr(this.$refs.range, {
          mode: 'range', dateFormat: 'Y-m-d', defaultDate: [cfg.from, cfg.to],
          onChange: function (d) { if (d.length === 2) { self.from = self.fmt(d[0]); self.to = self.fmt(d[1]); } },
        });
      },
      // Narrow account titles to those booked by items under the chosen fund
      // cluster. Returns true when the current account title was cleared.
      rescopeAccounts() {
        var fund = Number(this.fund_cluster_id), ids = {};
        cfg.records.forEach(function (r) {
          if (!fund || (r.funds || []).indexOf(fund) !== -1) {
            (r.accounts || []).forEach(function (id) { ids[id] = true; });
          }
        });
        var keep = fund ? cfg.accounts.filter(function (a) { return ids[a.value]; }) : cfg.accounts;
        var ts = this.accountPicker, current = this.account_title_id;
        var survives = keep.some(function (a) { return String(a.value) === String(current); });

        ts.clear(true);
        ts.clearOptions();
        ts.addOption({ value: '', text: 'All account titles' });
        ts.addOptions(keep);
        ts.refreshOptions(false);
        ts.setValue(survives ? current : '', true);
        if (current && !survives) { this.account_title_id = ''; return true; }
        return false;
      },
      rescope() {
        var res = this.applyScope(this.fund_cluster_id, this.account_title_id);
        if (!res.count) { CPSU.toast('No items match these filters.', 'info'); }
      },
      fmt(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); },
      generate() {
        var ids = this.itemPicker.getValue();
        if (!ids.length) { CPSU.toast('Please select at least one item, or choose All items.', 'error'); return; }
        var p = new URLSearchParams({ format: 'pdf', from: this.from, to: this.to, _: Date.now() });
        if (ids.indexOf('all') !== -1) {
          p.set('item_id', 'all');
          if (this.fund_cluster_id) { p.set('fund_cluster_id', this.fund_cluster_id); }
          if (this.account_title_id) { p.set('account_title_id', this.account_title_id); }
        } else {
          ids.forEach(function (id) { p.append('item_ids[]', id); });
        }
        if (this.withQr) { p.set('qr', 1); }
        this.url = cfg.base + '?' + p.toString();
      },
    };
  }
</script>
@endpush
@endsection
