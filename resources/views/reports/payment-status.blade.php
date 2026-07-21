@extends('layouts.app')

@section('title', 'Payment Status Report')
@section('header', 'Payment Status Report')
@section('subheader', 'Released line items — paid / unpaid tracking')

@section('content')
<div class="mb-4">
    <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Reports
    </a>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 mb-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="w-full rounded-lg border border-cpsu-border px-2 py-2 text-sm focus:border-cpsu-green outline-none">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
        <input type="date" name="to" value="{{ $filters['to'] }}" class="w-full rounded-lg border border-cpsu-border px-2 py-2 text-sm focus:border-cpsu-green outline-none">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Location</label>
        <select name="location_id" class="w-full rounded-lg border border-cpsu-border px-2 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
            <option value="">All</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc->id }}" @selected($filters['location_id'] == $loc->id)>{{ $loc->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Fund</label>
        <select name="fund_cluster_id" class="w-full rounded-lg border border-cpsu-border px-2 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
            <option value="">All</option>
            @foreach ($fundClusters as $fc)
                <option value="{{ $fc->id }}" @selected($filters['fund_cluster_id'] == $fc->id)>{{ $fc->code }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
        <select name="status" class="w-full rounded-lg border border-cpsu-border px-2 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
            <option value="">All</option>
            <option value="paid" @selected($filters['status'] === 'paid')>Paid</option>
            <option value="unpaid" @selected($filters['status'] === 'unpaid')>Unpaid</option>
        </select>
    </div>
    <x-ui.button variant="primary" icon="filter" type="submit">Apply</x-ui.button>
</form>

{{-- Totals + export --}}
<div class="flex flex-wrap items-center gap-3 mb-4">
    <x-ui.badge color="green">{{ $totals['paid'] }} paid</x-ui.badge>
    <x-ui.badge color="amber">{{ $totals['unpaid'] }} unpaid</x-ui.badge>
    <div class="ml-auto flex items-center gap-2">
        <x-ui.button variant="ghost" icon="download"
            :href="route('reports.export', 'payment-status').'?format=csv&'.http_build_query($filters)">CSV</x-ui.button>
        <x-ui.button variant="ghost" icon="file-text"
            :href="route('reports.export', 'payment-status').'?format=pdf&'.http_build_query($filters)">PDF</x-ui.button>
    </div>
</div>

<div class="bg-white rounded-xl border border-cpsu-border shadow-sm overflow-x-auto" data-aos="fade-up">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg">
                <th class="px-4 py-3">RIS</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Location</th>
                <th class="px-4 py-3">Item</th>
                <th class="px-4 py-3">RCA</th>
                <th class="px-4 py-3 text-right">Qty</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3">Paid On / By</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-cpsu-border">
            @forelse ($rows as $r)
                <tr class="hover:bg-cpsu-bg">
                    <td class="px-4 py-2.5"><a href="{{ route('releases.show', $r->release_id) }}" class="font-mono text-cpsu-green hover:underline">{{ $r->release->ris_number }}</a></td>
                    <td class="px-4 py-2.5 whitespace-nowrap">{{ $r->release->released_at?->format('M d, Y') }}</td>
                    <td class="px-4 py-2.5">{{ $r->release->location?->name }}</td>
                    <td class="px-4 py-2.5">{{ $r->item?->name }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs">{{ $r->rca_code }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold">{{ number_format($r->quantity, 2) }} <span class="text-xs text-gray-400">{{ $r->unit?->abbreviation }}</span></td>
                    <td class="px-4 py-2.5 text-center">
                        @if ($r->is_paid)
                            <x-ui.badge color="green">Paid</x-ui.badge>
                        @else
                            <x-ui.badge color="amber">Unpaid</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-xs text-gray-500">
                        @if ($r->is_paid){{ $r->paid_at?->format('M d, Y') }} · {{ $r->payer?->name }}@else—@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No released items match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
