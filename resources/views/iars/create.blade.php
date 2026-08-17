@extends('layouts.app')

@section('title', 'Create IAR')
@section('header', 'Create IAR')
@section('subheader', 'Appendix 62 - Inspection and Acceptance Report')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <a href="{{ route('iars.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to IAR
    </a>
</div>

<form method="POST" action="{{ route('iars.store') }}" class="space-y-4 max-w-5xl mx-auto">
    @csrf

    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5">
        <h2 class="font-bold text-sm mb-4 flex items-center gap-2">
            <i data-lucide="truck" class="w-4 h-4 text-cpsu-green"></i> Delivery covered by this IAR
        </h2>

        <label class="block text-sm font-medium mb-1">Delivery / PO</label>
        <select name="delivery_id" required
                onchange="if (this.value) window.location='{{ route('iars.create') }}?delivery_id=' + this.value"
                class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
            <option value="">Select a delivery without IAR</option>
            @foreach ($deliveries as $d)
                <option value="{{ $d->id }}" @selected($delivery?->id === $d->id)>
                    {{ $d->po_number }} - {{ $d->supplier?->name ?? 'No supplier' }} ({{ $d->received_at?->format('M d, Y') }})
                </option>
            @endforeach
            @if ($delivery && !$deliveries->contains('id', $delivery->id))
                <option value="{{ $delivery->id }}" selected>{{ $delivery->po_number }} - {{ $delivery->supplier?->name ?? 'No supplier' }}</option>
            @endif
        </select>

        @if ($delivery)
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm">
                <div><p class="text-xs text-gray-400 uppercase">Supplier</p><p class="font-semibold">{{ $delivery->supplier?->name ?? 'No supplier' }}</p></div>
                <div><p class="text-xs text-gray-400 uppercase">PO Number</p><p class="font-semibold font-mono">{{ $delivery->po_number }}</p></div>
                <div><p class="text-xs text-gray-400 uppercase">Fund Cluster</p><p class="font-semibold">{{ $delivery->fundCluster?->code ?? 'None' }}</p></div>
                <div><p class="text-xs text-gray-400 uppercase">Date Received</p><p class="font-semibold">{{ $delivery->received_at?->format('M d, Y') }}</p></div>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5">
        <h2 class="font-bold text-sm mb-4 flex items-center gap-2">
            <i data-lucide="clipboard-check" class="w-4 h-4 text-cpsu-green"></i> IAR Details
        </h2>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">IAR Number</label>
                <input name="iar_number" value="{{ old('iar_number', $nextIarNumber) }}" required
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">IAR Date</label>
                <input name="iar_date" type="date" value="{{ old('iar_date', now()->toDateString()) }}" required
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Requisitioning Office / Dept.</label>
                <input name="requisitioning_office" value="{{ old('requisitioning_office') }}"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Responsibility Center Code</label>
                <input name="responsibility_center_code" value="{{ old('responsibility_center_code') }}"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Invoice Number</label>
                <input name="invoice_number" value="{{ old('invoice_number') }}"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Invoice Date</label>
                <input name="invoice_date" type="date" value="{{ old('invoice_date') }}"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Date Inspected</label>
                <input name="inspection_date" type="date" value="{{ old('inspection_date') }}"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Inspection Officer / Committee</label>
                <input name="inspection_officer" value="{{ old('inspection_officer') }}"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Date Received / Accepted</label>
                <input name="acceptance_date" type="date" value="{{ old('acceptance_date', $delivery?->received_at?->toDateString()) }}"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Accepted By</label>
                <input name="accepted_by" value="{{ old('accepted_by', auth()->user()->name) }}"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Acceptance</label>
                <select name="acceptance_status"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                    <option value="complete" @selected(old('acceptance_status', 'complete') === 'complete')>Complete</option>
                    <option value="partial" @selected(old('acceptance_status') === 'partial')>Partial</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Partial Quantity</label>
                <input name="partial_quantity" type="number" step="0.01" min="0" value="{{ old('partial_quantity') }}"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
            </div>
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium mb-1">Remarks</label>
            <textarea name="remarks" rows="3"
                      class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">{{ old('remarks') }}</textarea>
        </div>
    </div>

    @if ($delivery)
        <div class="bg-white rounded-xl border border-cpsu-border shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-cpsu-border font-bold text-sm">Items from Delivery</div>
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500 bg-cpsu-bg">
                    <tr>
                        <th class="px-4 py-2 text-left">Stock No.</th>
                        <th class="px-4 py-2 text-left">Description</th>
                        <th class="px-4 py-2 text-left">Unit</th>
                        <th class="px-4 py-2 text-right">Quantity</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cpsu-border">
                    @foreach ($delivery->items as $line)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">{{ $line->item?->stock_number ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $line->item?->name }}</td>
                            <td class="px-4 py-2">{{ $line->unit?->abbreviation }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ number_format((float) $line->quantity, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="flex justify-end gap-2">
        <x-ui.button variant="ghost" :href="route('iars.index')">Cancel</x-ui.button>
        <x-ui.button variant="primary" type="submit" icon="save" :disabled="!$delivery">Create IAR</x-ui.button>
    </div>
</form>
@endsection
