@extends('layouts.app')

@section('title', 'Stock Card Report')
@section('header', 'Stock Card Report')

@section('content')
<div class="mb-4">
    <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Reports
    </a>
</div>

{{-- Item picker --}}
<form method="GET" class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
    <div class="flex-1 min-w-[240px]">
        <label class="block text-xs font-medium text-gray-500 mb-1">Item</label>
        <select name="item_id" id="report-item" onchange="this.form.submit()"
                class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
            <option value="">Select an item…</option>
            @foreach ($items as $it)
                <option value="{{ $it->id }}" @selected($item && $item->id === $it->id)>{{ $it->name }} {{ $it->stock_number ? '('.$it->stock_number.')' : '' }}</option>
            @endforeach
        </select>
    </div>
    @if ($item)
        <div class="ml-auto flex items-center gap-2">
            <x-ui.button variant="ghost" icon="printer" onclick="window.print()">Print</x-ui.button>
            <x-ui.button variant="ghost" icon="download" :href="route('reports.export', 'stock-card').'?format=csv&item_id='.$item->id">CSV</x-ui.button>
            <x-ui.button variant="ghost" icon="file-text" :href="route('reports.export', 'stock-card').'?format=pdf&item_id='.$item->id">PDF</x-ui.button>
        </div>
    @endif
</form>

@if ($item)
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <div class="flex items-center justify-between pb-4 border-b border-cpsu-border mb-4">
            <div>
                <h2 class="font-extrabold text-cpsu-black">{{ $item->name }}</h2>
                <p class="text-xs text-gray-400 font-mono">{{ $item->stock_number }} · Unit: {{ $item->unit?->abbreviation }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400 uppercase">Current Balance</p>
                <p class="text-2xl font-extrabold text-cpsu-green">{{ number_format($item->on_hand_qty, 2) }}</p>
            </div>
        </div>
        <div>
            <table class="w-full text-sm cpsu-table">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg">
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Reference</th>
                        <th class="px-4 py-2 text-right">In</th>
                        <th class="px-4 py-2 text-right">Out</th>
                        <th class="px-4 py-2 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cpsu-border">
                    @forelse ($rows as $r)
                        <tr class="hover:bg-cpsu-bg">
                            <td class="px-4 py-2.5 whitespace-nowrap">{{ $r['date']?->format('M d, Y') }}</td>
                            <td class="px-4 py-2.5 font-mono text-cpsu-green">{{ $r['ref'] }}</td>
                            <td class="px-4 py-2.5 text-right text-cpsu-success font-semibold">{{ $r['type'] === 'in' ? number_format($r['qty'], 2) : '' }}</td>
                            <td class="px-4 py-2.5 text-right text-cpsu-danger font-semibold">{{ $r['type'] === 'out' ? number_format($r['qty'], 2) : '' }}</td>
                            <td class="px-4 py-2.5 text-right font-bold">{{ number_format($r['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No transactions for this item.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-10 text-center text-gray-400" data-aos="fade-up">
        <i data-lucide="scroll-text" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
        <p>Select an item to view its stock card.</p>
    </div>
@endif
@endsection
