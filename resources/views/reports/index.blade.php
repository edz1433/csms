@extends('layouts.app')

@section('title', 'Reports')
@section('header', 'Reports')
@section('subheader', 'Requisition and Issue Slip (RIS) — pick a release to view its slip')

@section('content')
@include('reports.partials.tabs', ['active' => 'ris'])

<div x-data="risReport({
        base: @js(route('reports.ris.pdf')),
        records: {{ Illuminate\Support\Js::from($releases) }},
     })">
    {{-- Filters --}}
    <div class="relative z-20 bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5 mb-4" data-aos="fade-up">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            @include('reports.partials.scope-filters', ['prefix' => 'ris'])

            <div class="sm:col-span-10">
                <label class="block text-xs font-medium text-gray-500 mb-1">Release (RIS No.)</label>
                <select id="ris-release" x-model="releaseId" autocomplete="off"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                    <option value="">Search by RIS number, office or date…</option>
                    @foreach ($releases as $r)
                        <option value="{{ $r['value'] }}">{{ $r['text'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <x-ui.button variant="primary" icon="file-text" class="w-full h-[38px]" x-on:click="generate()">Generate PDF</x-ui.button>
            </div>
        </div>
    </div>

    @include('reports.partials.pdf-frame', [
        'title' => 'Requisition and Issue Slip',
        'emptyText' => 'Choose a release, then click Generate PDF to preview the Requisition and Issue Slip.',
    ])
</div>

@push('scripts')
<script>
  function risReport(cfg) {
    return {
      releaseId: '', fund_cluster_id: '', account_title_id: '', url: '',
      init() {
        var self = this;
        new TomSelect('#ris-fund', { create: false, allowEmptyOption: true });
        new TomSelect('#ris-account', { create: false, allowEmptyOption: true });
        var picker = new TomSelect('#ris-release', { create: false, allowEmptyOption: true });
        this.applyScope = CPSU.scopePicker(picker, cfg.records, 'Search by RIS number, office or date…');
        this.$watch('fund_cluster_id', function () { self.rescope(); });
        this.$watch('account_title_id', function () { self.rescope(); });
      },
      rescope() {
        var res = this.applyScope(this.fund_cluster_id, this.account_title_id);
        this.releaseId = res.kept;
        if (!res.count) { CPSU.toast('No releases match these filters.', 'info'); }
      },
      generate() {
        if (!this.releaseId) { CPSU.toast('Please select a release first.', 'error'); return; }
        var p = new URLSearchParams({ release_id: this.releaseId, _: Date.now() });
        this.url = cfg.base + '?' + p.toString();
      },
    };
  }
</script>
@endpush
@endsection
