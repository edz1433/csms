@extends('layouts.app')

@section('title', $item->name.' — Stock Card')
@section('header', 'Stock Card')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <a href="{{ route('items.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Items
    </a>
    <x-ui.button variant="ghost" icon="file-text" :href="route('items.pdf', $item)" target="_blank" rel="noopener">
        Stock Card PDF
    </x-ui.button>
</div>

{{-- Item header --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4" data-aos="fade-up">
    <div class="lg:col-span-2 bg-white rounded-xl border border-cpsu-border shadow-sm p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-cpsu-black">{{ $item->name }}</h2>
                <p class="text-sm text-gray-400 font-mono mt-0.5">{{ $item->stock_number ?? 'No stock number' }}</p>
                @if ($item->description)
                    <p class="text-sm text-gray-600 mt-2">{{ $item->description }}</p>
                @endif
            </div>
            <x-ui.badge :color="$item->is_active ? 'green' : 'gray'">{{ $item->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-5 pt-5 border-t border-cpsu-border text-sm">
            <div>
                <p class="text-xs text-gray-400 uppercase">Default Unit</p>
                <p class="font-semibold">{{ $item->unit?->name }} ({{ $item->unit?->abbreviation }})</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-xs text-gray-400 uppercase">Default Account Title</p>
                <p class="font-semibold">
                    {{ $item->accountTitle?->name ?? '—' }}
                    @if ($item->accountTitle)<span class="font-mono text-cpsu-green">{{ $item->accountTitle->rca_code }}</span>@endif
                </p>
            </div>
        </div>
    </div>

    {{-- On-hand card --}}
    <div class="bg-cpsu-green text-white rounded-xl shadow-sm p-5 flex flex-col justify-center">
        <p class="text-xs uppercase tracking-wide text-white/70">Current On-Hand</p>
        <p class="text-4xl font-extrabold mt-1">{{ number_format($item->on_hand_qty, 2) }}</p>
        <p class="text-sm text-white/80 mt-1">{{ $item->unit?->abbreviation }}</p>
    </div>
</div>

{{-- Transaction timeline --}}
<div class="bg-white rounded-xl border border-cpsu-border shadow-sm mt-4" data-aos="fade-up">
    <div class="px-5 py-4 border-b border-cpsu-border flex items-center gap-2">
        <i data-lucide="history" class="w-4 h-4 text-cpsu-green"></i>
        <h3 class="font-bold text-sm">Transaction History</h3>
        <span class="text-xs text-gray-400">(receiving in / releasing out — newest first)</span>
    </div>
    <div>
        <table class="w-full text-sm cpsu-table">
            <thead>
                <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg">
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Reference</th>
                    <th class="px-5 py-3">Supplier / Location</th>
                    <th class="px-5 py-3">RCA</th>
                    <th class="px-5 py-3 text-right">In</th>
                    <th class="px-5 py-3 text-right">Out</th>
                    <th class="px-5 py-3 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cpsu-border">
                @forelse ($timeline as $row)
                    <tr class="hover:bg-cpsu-bg">
                        <td class="px-5 py-3 whitespace-nowrap">{{ $row['date']?->format('M d, Y') }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ $row['link'] }}" class="font-mono text-cpsu-green hover:underline">{{ $row['ref'] }}</a>
                        </td>
                        <td class="px-5 py-3">{{ $row['party'] ?? '—' }}</td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $row['rca'] ?? '—' }}</td>
                        <td class="px-5 py-3 text-right text-cpsu-success font-semibold">{{ $row['type'] === 'in' ? '+'.number_format($row['qty'], 2) : '' }}</td>
                        <td class="px-5 py-3 text-right text-cpsu-danger font-semibold">{{ $row['type'] === 'out' ? '-'.number_format($row['qty'], 2) : '' }}</td>
                        <td class="px-5 py-3 text-right font-bold">{{ number_format($row['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No transactions yet for this item.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
