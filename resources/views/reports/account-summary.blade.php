@extends('layouts.app')

@section('title', 'Inventory Summary')
@section('header', 'Reports')
@section('subheader', 'Inventory Summary — purchases and issuances by month for one account title')

@section('content')
@include('reports.partials.tabs', ['active' => 'account-summary'])

<div x-data="accountSummaryReport({
        base: @js(route('reports.account-summary.pdf')),
        fund_cluster_id: @js($filters['fund_cluster_id']),
        account_title_id: @js($filters['account_title_id']),
        year: {{ $filters['year'] }},
     })">

    {{-- Filters --}}
    <div class="relative z-20 bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5 mb-4" data-aos="fade-up">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            @include('reports.partials.scope-filters', [
                'prefix' => 'as',
                'fundClass' => 'sm:col-span-4',
                'accountClass' => 'sm:col-span-4',
            ])

            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">Year</label>
                <select x-model.number="year"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                    @foreach ($years as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <x-ui.button variant="primary" icon="file-text" class="w-full h-[38px]" x-on:click="generate()">Generate</x-ui.button>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">
            The report opens with the balance carried into the year, then lists what was purchased and issued each
            month. Leave the filters on <span class="font-medium text-gray-500">All</span> to roll every account
            title and fund cluster into one summary.
        </p>
    </div>

    @include('reports.partials.pdf-frame', [
        'title' => 'Inventory Summary',
        'emptyText' => 'Choose an account title and year, then click Generate to preview the summary.',
    ])
</div>

@push('scripts')
<script>
  function accountSummaryReport(cfg) {
    return {
      fund_cluster_id: cfg.fund_cluster_id || '', account_title_id: cfg.account_title_id || '',
      year: cfg.year, url: '',
      init() {
        new TomSelect('#as-fund', { create: false, allowEmptyOption: true });
        new TomSelect('#as-account', { create: false, allowEmptyOption: true });
      },
      generate() {
        // Both filters are optional — leaving them on "All" rolls every
        // account title and fund cluster into one summary.
        var p = { year: this.year, _: Date.now() };
        if (this.account_title_id) { p.account_title_id = this.account_title_id; }
        if (this.fund_cluster_id) { p.fund_cluster_id = this.fund_cluster_id; }
        this.url = cfg.base + '?' + new URLSearchParams(p).toString();
      },
    };
  }
</script>
@endpush
@endsection
