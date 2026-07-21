@extends('layouts.app')

@section('title', 'RIS '.$release->ris_number)
@section('header', 'Requisition & Issue Slip')

@section('content')
<div class="mb-4 flex items-center justify-between print:hidden">
    <a href="{{ route('releases.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Releases
    </a>
    <x-ui.button variant="ghost" icon="printer" onclick="window.print()">Print</x-ui.button>
</div>

<div
    class="bg-white rounded-xl border border-cpsu-border shadow-sm p-6 max-w-4xl mx-auto"
    id="ris" data-aos="fade-up"
>
    {{-- Letterhead --}}
    <div class="flex items-center gap-3 pb-4 border-b-2 border-cpsu-green">
        <img src="{{ asset('images/cpsu-logo.png') }}" class="h-14 w-14 object-contain" onerror="this.style.display='none'">
        <div class="flex-1">
            <h2 class="font-extrabold text-cpsu-green leading-tight">Central Philippines State University</h2>
            <p class="text-sm text-gray-500">Common Supply Management System — Requisition &amp; Issue Slip</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-400 uppercase">RIS Number</p>
            <p class="font-mono font-bold text-cpsu-black">{{ $release->ris_number }}</p>
        </div>
    </div>

    {{-- Meta --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 text-sm">
        <div><p class="text-xs text-gray-400 uppercase">Fund Cluster</p><p class="font-semibold">{{ $release->fundCluster?->code }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Destination</p><p class="font-semibold">{{ $release->location?->name }} <span class="text-xs text-gray-400">[{{ ucfirst($release->location?->type) }}]</span></p></div>
        <div><p class="text-xs text-gray-400 uppercase">Date Released</p><p class="font-semibold">{{ $release->released_at?->format('M d, Y') }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Released By</p><p class="font-semibold">{{ $release->releaser?->name }}</p></div>
    </div>

    {{-- Lines --}}
    <table class="w-full text-sm mt-6">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-500 border-y border-cpsu-border">
                <th class="py-2 pr-3">#</th>
                <th class="py-2 pr-3">Item</th>
                <th class="py-2 pr-3">Account Title / RCA</th>
                <th class="py-2 pr-3 text-right">Qty</th>
                <th class="py-2 pr-3">Unit</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-cpsu-border">
            @foreach ($release->items as $i => $line)
                <tr>
                    <td class="py-2.5 pr-3 text-gray-400">{{ $i + 1 }}</td>
                    <td class="py-2.5 pr-3">
                        <p class="font-medium">{{ $line->item->name }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $line->item->stock_number }}</p>
                    </td>
                    <td class="py-2.5 pr-3">
                        {{ $line->accountTitle?->name }}
                        <span class="font-mono text-xs text-cpsu-green block">{{ $line->rca_code }}</span>
                    </td>
                    <td class="py-2.5 pr-3 text-right font-semibold">{{ number_format($line->quantity, 2) }}</td>
                    <td class="py-2.5 pr-3">{{ $line->unit->abbreviation }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($release->remarks)
        <div class="mt-5 text-sm">
            <p class="text-xs text-gray-400 uppercase">Remarks</p>
            <p>{{ $release->remarks }}</p>
        </div>
    @endif

    <div class="mt-8 pt-4 border-t border-cpsu-border text-xs text-gray-400 flex justify-between">
        <span>Generated {{ now()->format('M d, Y g:i A') }}</span>
        <span>Release #{{ $release->id }}</span>
    </div>
</div>
@endsection
