{{--
    Fund cluster + account title filters, shared by every report.
    Include with: prefix (unique per page), and optional column spans.
    Expects $fundClusters / $accountTitles in scope, plus Alpine state
    named fund_cluster_id and account_title_id.
--}}
@php
    $prefix = $prefix ?? 'scope';
    $fundClass = $fundClass ?? 'sm:col-span-6';
    $accountClass = $accountClass ?? 'sm:col-span-6';
@endphp

<div class="{{ $fundClass }}">
    <label class="block text-xs font-medium text-gray-500 mb-1">Fund Cluster</label>
    <select id="{{ $prefix }}-fund" x-model="fund_cluster_id" autocomplete="off"
            class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
        <option value="">All fund clusters</option>
        @foreach ($fundClusters as $fc)
            <option value="{{ $fc->id }}">{{ $fc->code }} — {{ $fc->name }}</option>
        @endforeach
    </select>
</div>

<div class="{{ $accountClass }}">
    <label class="block text-xs font-medium text-gray-500 mb-1">Account Title</label>
    <select id="{{ $prefix }}-account" x-model="account_title_id" autocomplete="off"
            class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
        <option value="">All account titles</option>
        @foreach ($accountTitles as $at)
            <option value="{{ $at->id }}">{{ $at->name }} — {{ $at->rca_code }}</option>
        @endforeach
    </select>
</div>
