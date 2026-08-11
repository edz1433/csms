@extends('layouts.app')

@section('title', 'Stock Status Report')
@section('header', 'Reports')
@section('subheader', 'Stock Status — every item with its balance, unit price and total cost')

@section('content')
@include('reports.partials.tabs', ['active' => 'stock-status'])

<div x-data="stockStatusReport({
        base: @js(route('reports.stock-status.pdf')),
        fund_cluster_id: @js($filters['fund_cluster_id']),
        account_title_id: @js($filters['account_title_id']),
        with_stock: {{ $filters['with_stock'] ? 'true' : 'false' }},
     })">

    {{-- Filters --}}
    <div class="relative z-20 bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5 mb-4" data-aos="fade-up">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            @include('reports.partials.scope-filters', [
                'prefix' => 'ss',
                'fundClass' => 'sm:col-span-4',
                'accountClass' => 'sm:col-span-4',
            ])

            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">Coverage</label>
                <select x-model="with_stock"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                    <option :value="false">All items</option>
                    <option :value="true">With stock only</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <x-ui.button variant="primary" icon="file-text" class="w-full h-[38px]" x-on:click="generate()">Generate</x-ui.button>
            </div>
        </div>
    </div>

    @include('reports.partials.pdf-frame', [
        'title' => 'Stock Status Report',
        'emptyText' => 'Set the filters above, then click Generate to preview the stock status report.',
    ])
</div>

@push('scripts')
<script>
  function stockStatusReport(cfg) {
    return {
      fund_cluster_id: cfg.fund_cluster_id || '', account_title_id: cfg.account_title_id || '',
      with_stock: cfg.with_stock, url: '',
      init() {
        new TomSelect('#ss-fund', { create: false, allowEmptyOption: true });
        new TomSelect('#ss-account', { create: false, allowEmptyOption: true });
      },
      params() {
        var p = { _: Date.now() };
        if (this.fund_cluster_id) { p.fund_cluster_id = this.fund_cluster_id; }
        if (this.account_title_id) { p.account_title_id = this.account_title_id; }
        if (this.with_stock) { p.with_stock = 1; }
        return new URLSearchParams(p).toString();
      },
      generate() { this.url = cfg.base + '?' + this.params(); },
    };
  }
</script>
@endpush
@endsection
