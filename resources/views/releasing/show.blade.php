@extends('layouts.app')

@section('title', 'RIS '.$release->ris_number)
@section('header', 'Requisition & Issue Slip')

@php $canToggle = auth()->user()->isAdministrator() || auth()->user()->isAccountingStaff(); @endphp

@section('content')
<div class="mb-4 flex items-center justify-between print:hidden">
    <a href="{{ route('releases.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Releases
    </a>
    <x-ui.button variant="ghost" icon="printer" onclick="window.print()">Print</x-ui.button>
</div>

<div
    x-data="releaseView({
        canToggle: {{ $canToggle ? 'true' : 'false' }},
        toggleBase: @js(url('releases/'.$release->id.'/items')),
    })"
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
                <th class="py-2 pr-3 text-center">Payment</th>
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
                    <td class="py-2.5 pr-3 text-center">
                        <div x-data="{ paid: {{ $line->is_paid ? 'true' : 'false' }}, id: {{ $line->id }} }">
                            <button type="button"
                                @if($canToggle) @click="toggle($data)" @endif
                                class="badge-fade inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold {{ $canToggle ? 'cursor-pointer hover:opacity-80' : 'cursor-default' }}"
                                :class="paid ? 'bg-cpsu-green/10 text-cpsu-green' : 'bg-amber-100 text-amber-700'">
                                <i :data-lucide="paid ? 'check-circle-2' : 'clock'" class="w-3.5 h-3.5"></i>
                                <span x-text="paid ? 'Paid' : 'Unpaid'"></span>
                            </button>
                            @if ($line->is_paid && $line->paid_at)
                                <p class="text-[10px] text-gray-400 mt-0.5 print:block">{{ $line->paid_at->format('M d, Y') }} · {{ $line->payer?->name }}</p>
                            @endif
                        </div>
                    </td>
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

@push('scripts')
<script>
  function releaseView(cfg) {
    return {
      canToggle: cfg.canToggle,
      toggle(row) {
        if (!this.canToggle) return;
        CPSU.confirm({
          icon: 'question',
          title: row.paid ? 'Mark this item as Unpaid?' : 'Mark this item as Paid?',
          confirmText: row.paid ? 'Yes, mark Unpaid' : 'Yes, mark Paid',
        }).then((r) => {
          if (!r.isConfirmed) return;
          fetch(cfg.toggleBase + '/' + row.id + '/payment-status', {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          })
          .then((res) => { if (!res.ok) throw new Error(); return res.json(); })
          .then((d) => {
            row.paid = d.is_paid;
            setTimeout(() => window.lucide && lucide.createIcons(), 50);
            CPSU.toast('Payment status updated', 'success');
          })
          .catch(() => CPSU.toast('Could not update payment status', 'error'));
        });
      },
    };
  }
</script>
@endpush
@endsection
