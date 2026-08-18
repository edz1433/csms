@extends('layouts.app')

@section('title', 'Delivery '.$delivery->po_number)
@section('header', 'Delivery Receipt')

@section('content')
<div class="mb-4 flex items-center justify-between print:hidden">
    <a href="{{ route('deliveries.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Deliveries
    </a>
    <div class="flex items-center gap-2">
        @if ($delivery->isEditable())
            <x-action-guard>
                {{-- The balance of a partial shipment is recorded here. --}}
                <x-ui.button variant="ghost" icon="pencil" :href="route('deliveries.edit', $delivery)">
                    {{ $delivery->isPartial() ? 'Record Delivery' : 'Edit' }}
                </x-ui.button>
            </x-action-guard>
        @endif
        <x-ui.button variant="ghost" icon="printer" onclick="window.print()">Print</x-ui.button>
    </div>
</div>

<div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-6 max-w-3xl mx-auto" id="receipt" data-aos="fade-up">
    <div class="flex items-center gap-3 pb-4 border-b-2 border-cpsu-green">
        <img src="{{ asset('images/cpsu-logo.png') }}" class="h-14 w-14 object-contain" onerror="this.style.display='none'">
        <div>
            <h2 class="font-extrabold text-cpsu-green leading-tight">Central Philippines State University</h2>
            <p class="text-sm text-gray-500">Common Supply Management System - Delivery Receipt</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 text-sm">
        <div><p class="text-xs text-gray-400 uppercase">PO Number</p><p class="font-semibold font-mono">{{ $delivery->po_number }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Fund Cluster</p><p class="font-semibold">{{ $delivery->fundCluster?->code ?? '-' }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Supplier</p><p class="font-semibold">{{ $delivery->supplier?->name ?? '-' }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Date Received</p><p class="font-semibold">{{ $delivery->received_at?->format('M d, Y') }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Received By</p><p class="font-semibold">{{ $delivery->receiver?->name }}</p></div>
        <div>
            <p class="text-xs text-gray-400 uppercase">Delivery Status</p>
            @if ($delivery->isPartial())
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                    <i data-lucide="package-open" class="w-3.5 h-3.5"></i>
                    Partial — {{ number_format($delivery->outstandingQty(), 2) }} still due
                </span>
            @else
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">
                    <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                    Complete
                </span>
            @endif
        </div>
    </div>

    <div class="mt-5 rounded-lg border border-cpsu-border bg-cpsu-bg/40 p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs text-gray-500 uppercase">Inspection and Acceptance Report</span>
            @if ($delivery->iar)
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">
                    <i data-lucide="clipboard-check" class="w-3.5 h-3.5"></i>
                    {{ $delivery->iar->iar_number }}
                </span>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold {{ $delivery->iar->is_paid ? 'bg-cpsu-green/10 text-cpsu-green' : 'bg-amber-100 text-amber-700' }}">
                    <i data-lucide="{{ $delivery->iar->is_paid ? 'check-circle-2' : 'clock' }}" class="w-3.5 h-3.5"></i>
                    {{ $delivery->iar->is_paid ? 'Paid' : 'Unpaid' }}
                </span>
                @if ($delivery->iar->is_paid && $delivery->iar->or_number)
                    <span class="text-xs text-gray-600">OR #<b>{{ $delivery->iar->or_number }}</b></span>
                @endif
            @else
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                    <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                    No IAR
                </span>
                <span class="text-xs text-gray-500">Accounting can mark payment only after Supply creates the IAR.</span>
            @endif
        </div>

        <div class="print:hidden flex gap-2">
            @if ($delivery->iar)
                <x-ui.button variant="ghost" icon="eye" :href="route('iars.show', $delivery->iar)">Open IAR</x-ui.button>
            @else
                <x-action-guard>
                    <x-ui.button variant="primary" icon="plus" :href="route('iars.create', ['delivery_id' => $delivery->id])">Create IAR</x-ui.button>
                </x-action-guard>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm mt-6">
            <thead>
                <tr class="text-left text-xs uppercase text-gray-500 border-y border-cpsu-border">
                    <th class="py-2 pr-3">#</th>
                    <th class="py-2 pr-3">Stock No.</th>
                    <th class="py-2 pr-3">Item</th>
                    <th class="py-2 pr-3 text-right">Ordered</th>
                    <th class="py-2 pr-3 text-right">Received</th>
                    <th class="py-2 pr-3 text-right">Balance</th>
                    <th class="py-2 pr-3">Unit</th>
                    <th class="py-2 pr-3">Date Received</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cpsu-border">
                @foreach ($delivery->items as $i => $line)
                    <tr>
                        <td class="py-2 pr-3 text-gray-400">{{ $i + 1 }}</td>
                        <td class="py-2 pr-3 font-mono text-xs">{{ $line->item->stock_number ?? '-' }}</td>
                        <td class="py-2 pr-3">{{ $line->item->name }}</td>
                        <td class="py-2 pr-3 text-right">{{ $line->ordered_qty !== null ? number_format($line->ordered_qty, 2) : '—' }}</td>
                        <td class="py-2 pr-3 text-right font-semibold">{{ number_format($line->quantity, 2) }}</td>
                        <td class="py-2 pr-3 text-right {{ $line->balanceQty() > 0 ? 'text-amber-700 font-semibold' : 'text-gray-400' }}">
                            {{ $line->ordered_qty !== null ? number_format($line->balanceQty(), 2) : '—' }}
                        </td>
                        <td class="py-2 pr-3">{{ $line->unit->abbreviation }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $line->received_at?->format('M d, Y') ?? $delivery->received_at?->format('M d, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

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
