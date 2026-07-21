@extends('layouts.app')

@section('title', 'Delivery '.$delivery->po_number)
@section('header', 'Delivery Receipt')

@section('content')
<div class="mb-4 flex items-center justify-between print:hidden">
    <a href="{{ route('deliveries.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Deliveries
    </a>
    <x-ui.button variant="ghost" icon="printer" onclick="window.print()">Print</x-ui.button>
</div>

<div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-6 max-w-3xl mx-auto" id="receipt" data-aos="fade-up">
    {{-- Letterhead --}}
    <div class="flex items-center gap-3 pb-4 border-b-2 border-cpsu-green">
        <img src="{{ asset('images/cpsu-logo.png') }}" class="h-14 w-14 object-contain" onerror="this.style.display='none'">
        <div>
            <h2 class="font-extrabold text-cpsu-green leading-tight">Central Philippines State University</h2>
            <p class="text-sm text-gray-500">Common Supply Management System — Delivery Receipt</p>
        </div>
    </div>

    {{-- Meta --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 text-sm">
        <div><p class="text-xs text-gray-400 uppercase">PO Number</p><p class="font-semibold font-mono">{{ $delivery->po_number }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Supplier</p><p class="font-semibold">{{ $delivery->supplier?->name ?? '—' }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Date Received</p><p class="font-semibold">{{ $delivery->received_at?->format('M d, Y') }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Received By</p><p class="font-semibold">{{ $delivery->receiver?->name }}</p></div>
    </div>

    {{-- Lines --}}
    <table class="w-full text-sm mt-6">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-500 border-y border-cpsu-border">
                <th class="py-2 pr-3">#</th>
                <th class="py-2 pr-3">Stock No.</th>
                <th class="py-2 pr-3">Item</th>
                <th class="py-2 pr-3 text-right">Quantity</th>
                <th class="py-2 pr-3">Unit</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-cpsu-border">
            @foreach ($delivery->items as $i => $line)
                <tr>
                    <td class="py-2 pr-3 text-gray-400">{{ $i + 1 }}</td>
                    <td class="py-2 pr-3 font-mono text-xs">{{ $line->item->stock_number ?? '—' }}</td>
                    <td class="py-2 pr-3">{{ $line->item->name }}</td>
                    <td class="py-2 pr-3 text-right font-semibold">{{ number_format($line->quantity, 2) }}</td>
                    <td class="py-2 pr-3">{{ $line->unit->abbreviation }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($delivery->remarks)
        <div class="mt-5 text-sm">
            <p class="text-xs text-gray-400 uppercase">Remarks</p>
            <p>{{ $delivery->remarks }}</p>
        </div>
    @endif

    <div class="mt-8 pt-4 border-t border-cpsu-border text-xs text-gray-400 flex justify-between">
        <span>Generated {{ now()->format('M d, Y g:i A') }}</span>
        <span>Delivery #{{ $delivery->id }}</span>
    </div>
</div>
@endsection
