@extends('layouts.app')

@section('title', 'Supply Ledger Card')
@section('header', 'Reports')
@section('subheader', 'Supply Ledger Card (Appendix 57) — per-item receipt / issue / balance ledger')

@section('content')
@include('reports.partials.tabs', ['active' => 'ledger'])

<div x-data="ledgerCard({
        base: @js(route('reports.ledger.pdf')),
        from: @js(now()->startOfYear()->toDateString()),
        to: @js(now()->endOfYear()->toDateString()),
        records: {{ Illuminate\Support\Js::from($items) }},
     })">

    {{-- Filter form (fund cluster · account title · item · date range · generate) --}}
    <div class="relative z-20 bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5 mb-4" data-aos="fade-up">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            @include('reports.partials.scope-filters', ['prefix' => 'ledger'])

            <div class="sm:col-span-6">
                <label class="block text-xs font-medium text-gray-500 mb-1">Item</label>
                <select id="ledger-item" x-model="itemId" autocomplete="off"
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
                    <input id="ledger-range" type="text" readonly placeholder="Select date range"
                           class="w-full rounded-lg border border-cpsu-border pl-9 pr-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none cursor-pointer">
                </div>
            </div>
            <div class="sm:col-span-2">
                <x-ui.button variant="primary" icon="file-text" class="w-full h-[38px]" x-on:click="generate()">Generate</x-ui.button>
            </div>
        </div>
    </div>

    {{-- Generated card (iframe) --}}
    <div x-show="url" x-cloak data-aos="fade-up"
         class="relative z-10 bg-white rounded-xl border border-cpsu-border shadow-sm overflow-hidden">
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
      itemId: '', fund_cluster_id: '', account_title_id: '',
      from: cfg.from, to: cfg.to, url: '',
      init() {
        var self = this;
        new TomSelect('#ledger-fund', { create: false, allowEmptyOption: true });
        new TomSelect('#ledger-account', { create: false, allowEmptyOption: true });
        var picker = new TomSelect('#ledger-item', { create: false, allowEmptyOption: true });
        this.applyScope = CPSU.scopePicker(picker, cfg.records, 'Search item by name or code…');
        this.$watch('fund_cluster_id', function () { self.rescope(); });
        this.$watch('account_title_id', function () { self.rescope(); });
        flatpickr('#ledger-range', {
          mode: 'range', dateFormat: 'Y-m-d',
          defaultDate: [cfg.from, cfg.to],
          onChange: function (dates) {
            if (dates.length === 2) {
              self.from = self.fmt(dates[0]);
              self.to = self.fmt(dates[1]);
            }
          },
        });
      },
      fmt(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
      },
      rescope() {
        var res = this.applyScope(this.fund_cluster_id, this.account_title_id);
        this.itemId = res.kept;
        if (!res.count) { CPSU.toast('No items match these filters.', 'info'); }
      },
      generate() {
        if (!this.itemId) { CPSU.toast('Please select an item first.', 'error'); return; }
        var p = new URLSearchParams({ item_id: this.itemId, from: this.from, to: this.to, _: Date.now() });
        this.url = cfg.base + '?' + p.toString();
      },
    };
  }
</script>
@endpush
@endsection
