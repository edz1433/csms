@extends('layouts.app')

@section('title', 'Report of Supplies and Materials Issued')
@section('header', 'Reports')
@section('subheader', 'RSMI (Appendix 64) — supplies & materials issued, generated as PDF')

@section('content')
@include('reports.partials.tabs', ['active' => 'rsmi'])

<div x-data="rsmiReport({
        base: @js(route('reports.rsmi.pdf')),
        from: @js($filters['from']),
        to: @js($filters['to']),
        fund_cluster_id: @js($filters['fund_cluster_id']),
        account_title_id: @js($filters['account_title_id']),
     })">
    {{-- Filters --}}
    <div class="relative z-20 bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5 mb-4" data-aos="fade-up">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            <div class="sm:col-span-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Date Range (period)</label>
                <div class="relative">
                    <i data-lucide="calendar" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input x-ref="range" type="text" readonly placeholder="Select date range"
                           class="w-full rounded-lg border border-cpsu-border pl-9 pr-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none cursor-pointer">
                </div>
            </div>
            @include('reports.partials.scope-filters', [
                'prefix' => 'rsmi',
                'fundClass' => 'sm:col-span-3',
                'accountClass' => 'sm:col-span-3',
            ])

            <div class="sm:col-span-2">
                <x-ui.button variant="primary" icon="file-text" class="w-full h-[38px]" x-on:click="generate()">Generate PDF</x-ui.button>
            </div>
        </div>
    </div>

    @include('reports.partials.pdf-frame', [
        'title' => 'Report of Supplies and Materials Issued (Appendix 64)',
        'emptyText' => 'Choose a period and fund cluster, then click Generate PDF to preview the RSMI.',
    ])
</div>

@push('scripts')
<script>
  function rsmiReport(cfg) {
    return {
      from: cfg.from, to: cfg.to, fund_cluster_id: cfg.fund_cluster_id || '',
      account_title_id: cfg.account_title_id || '', url: '',
      init() {
        var self = this;
        new TomSelect('#rsmi-fund', { create: false, allowEmptyOption: true });
        new TomSelect('#rsmi-account', { create: false, allowEmptyOption: true });
        flatpickr(this.$refs.range, {
          mode: 'range', dateFormat: 'Y-m-d', defaultDate: [cfg.from, cfg.to],
          onChange: function (d) { if (d.length === 2) { self.from = self.fmt(d[0]); self.to = self.fmt(d[1]); } },
        });
      },
      fmt(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); },
      generate() {
        var params = { from: this.from, to: this.to, _: Date.now() };
        if (this.fund_cluster_id) { params.fund_cluster_id = this.fund_cluster_id; }
        if (this.account_title_id) { params.account_title_id = this.account_title_id; }
        this.url = cfg.base + '?' + new URLSearchParams(params).toString();
      },
    };
  }
</script>
@endpush
@endsection
