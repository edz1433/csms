@extends('layouts.app')

@section('title', 'Payment Status Report')
@section('header', 'Reports')
@section('subheader', 'Payment Status — supplier payments on deliveries, generated as PDF')

@section('content')
@include('reports.partials.tabs', ['active' => 'payment-status'])

<div x-data="paymentReport({
        base: @js(route('reports.export', 'payment-status')),
        from: @js($filters['from']),
        to: @js($filters['to']),
        supplier_id: @js($filters['supplier_id']),
        status: @js($filters['status']),
        fund_cluster_id: @js($filters['fund_cluster_id']),
        account_title_id: @js($filters['account_title_id']),
     })">
    {{-- Filters --}}
    <div class="relative z-20 bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5 mb-4" data-aos="fade-up">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            <div class="sm:col-span-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Date Range</label>
                <div class="relative">
                    <i data-lucide="calendar" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input x-ref="range" type="text" readonly placeholder="Select date range"
                           class="w-full rounded-lg border border-cpsu-border pl-9 pr-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none cursor-pointer">
                </div>
            </div>
            <div class="sm:col-span-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                <select id="pay-supplier" x-model="supplier_id"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select x-model="status"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                    <option value="">All</option>
                    <option value="paid">Paid</option>
                    <option value="unpaid">Unpaid</option>
                </select>
            </div>

            @include('reports.partials.scope-filters', [
                'prefix' => 'pay',
                'fundClass' => 'sm:col-span-5',
                'accountClass' => 'sm:col-span-5',
            ])

            <div class="sm:col-span-2">
                <x-ui.button variant="primary" icon="file-text" class="w-full h-[38px]" x-on:click="generate()">Generate PDF</x-ui.button>
            </div>
        </div>
    </div>

    @include('reports.partials.pdf-frame', [
        'title' => 'Payment Status',
        'emptyText' => 'Set the filters above, then click Generate PDF to preview the payment status report.',
    ])
</div>

@push('scripts')
<script>
  function paymentReport(cfg) {
    return {
      from: cfg.from, to: cfg.to, supplier_id: cfg.supplier_id || '', status: cfg.status || '',
      fund_cluster_id: cfg.fund_cluster_id || '', account_title_id: cfg.account_title_id || '', url: '',
      init() {
        var self = this;
        new TomSelect('#pay-supplier', { create: false, allowEmptyOption: true });
        new TomSelect('#pay-fund', { create: false, allowEmptyOption: true });
        new TomSelect('#pay-account', { create: false, allowEmptyOption: true });
        flatpickr(this.$refs.range, {
          mode: 'range', dateFormat: 'Y-m-d', defaultDate: [cfg.from, cfg.to],
          onChange: function (d) { if (d.length === 2) { self.from = self.fmt(d[0]); self.to = self.fmt(d[1]); } },
        });
      },
      fmt(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); },
      generate() {
        var params = { format: 'pdf', from: this.from, to: this.to, _: Date.now() };
        if (this.supplier_id) { params.supplier_id = this.supplier_id; }
        if (this.status) { params.status = this.status; }
        if (this.fund_cluster_id) { params.fund_cluster_id = this.fund_cluster_id; }
        if (this.account_title_id) { params.account_title_id = this.account_title_id; }
        this.url = cfg.base + '?' + new URLSearchParams(params).toString();
      },
    };
  }
</script>
@endpush
@endsection
