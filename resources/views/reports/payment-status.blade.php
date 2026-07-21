@extends('layouts.app')

@section('title', 'Payment Status Report')
@section('header', 'Payment Status Report')
@section('subheader', 'Supplier payments on deliveries — paid / unpaid tracking')

@section('content')
<div class="mb-4">
    <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Reports
    </a>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 mb-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="w-full rounded-lg border border-cpsu-border px-2 py-2 text-sm focus:border-cpsu-green outline-none">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
        <input type="date" name="to" value="{{ $filters['to'] }}" class="w-full rounded-lg border border-cpsu-border px-2 py-2 text-sm focus:border-cpsu-green outline-none">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
        <select name="supplier_id" class="w-full rounded-lg border border-cpsu-border px-2 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
            <option value="">All</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s->id }}" @selected($filters['supplier_id'] == $s->id)>{{ $s->name }}</option>
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

<div class="bg-white rounded-xl border border-cpsu-border shadow-sm" data-aos="fade-up">
    <table class="w-full text-sm cpsu-table">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg">
                <th class="px-4 py-3">PO Number</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Supplier</th>
                <th class="px-4 py-3 text-center">Items</th>
                <th class="px-4 py-3">Received By</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3">OR No.</th>
                <th class="px-4 py-3">Paid On / By</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-cpsu-border">
            @forelse ($rows as $d)
                <tr class="hover:bg-cpsu-bg">
                    <td class="px-4 py-2.5"><a href="{{ route('deliveries.show', $d->id) }}" class="font-mono text-cpsu-green hover:underline">{{ $d->po_number }}</a></td>
                    <td class="px-4 py-2.5 whitespace-nowrap">{{ $d->received_at?->format('M d, Y') }}</td>
                    <td class="px-4 py-2.5">{{ $d->supplier?->name ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-center">{{ $d->items_count }}</td>
                    <td class="px-4 py-2.5">{{ $d->receiver?->name }}</td>
                    <td class="px-4 py-2.5 text-center">
                        @if ($d->is_paid)
                            <x-ui.badge color="green">Paid</x-ui.badge>
                        @else
                            <x-ui.badge color="amber">Unpaid</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs">{{ $d->or_number ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-xs text-gray-500">
                        @if ($d->is_paid){{ $d->paid_at?->format('M d, Y') }} · {{ $d->payer?->name }}@else—@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No deliveries match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
