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

    {{-- Payment / Official Receipt --}}
    @php $canPay = auth()->user()->isAdministrator() || auth()->user()->isAccountingStaff(); @endphp
    <div
        x-data="deliveryPayment({
            paid: {{ $delivery->is_paid ? 'true' : 'false' }},
            orNumber: @js($delivery->or_number),
            paidInfo: @js($delivery->is_paid && $delivery->paid_at ? $delivery->paid_at->format('M d, Y').' · '.$delivery->payer?->name : null),
            canPay: {{ $canPay ? 'true' : 'false' }},
            url: @js(route('deliveries.payment', $delivery)),
        })"
        class="mt-5 rounded-lg border border-cpsu-border bg-cpsu-bg/40 p-4 flex flex-wrap items-center justify-between gap-3"
    >
        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-500 uppercase">Supplier Payment</span>
            <span class="badge-fade inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold"
                  :class="paid ? 'bg-cpsu-green/10 text-cpsu-green' : 'bg-amber-100 text-amber-700'">
                <i :data-lucide="paid ? 'check-circle-2' : 'clock'" class="w-3.5 h-3.5"></i>
                <span x-text="paid ? 'Paid' : 'Unpaid'"></span>
            </span>
            <template x-if="paid && orNumber">
                <span class="text-xs text-gray-600">OR #<b x-text="orNumber"></b></span>
            </template>
            <template x-if="paid && paidInfo">
                <span class="text-[11px] text-gray-400" x-text="paidInfo"></span>
            </template>
        </div>
        @if ($canPay)
            <button type="button" @click="toggle()"
                    class="print:hidden inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold transition-all active:scale-95"
                    :class="paid ? 'bg-white border border-cpsu-border text-cpsu-black hover:bg-cpsu-bg' : 'bg-cpsu-green hover:bg-cpsu-green-dark text-white'">
                <i :data-lucide="paid ? 'rotate-ccw' : 'wallet'" class="w-4 h-4"></i>
                <span x-text="paid ? 'Mark Unpaid' : 'Mark Paid'"></span>
            </button>
        @endif
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

@push('scripts')
<script>
  function deliveryPayment(cfg) {
    return {
      paid: cfg.paid, orNumber: cfg.orNumber, paidInfo: cfg.paidInfo, canPay: cfg.canPay,
      async toggle() {
        if (!this.canPay) return;
        var self = this;
        if (!this.paid) {
          // Marking Paid — capture the Official Receipt (OR) number.
          var res = await Swal.fire({
            title: 'Mark delivery as Paid?',
            input: 'text', inputLabel: 'Official Receipt (OR) number — optional',
            inputPlaceholder: 'e.g. 0098231',
            showCancelButton: true, confirmButtonText: 'Mark Paid',
            confirmButtonColor: '#0B6E2E', cancelButtonColor: '#DC2626', reverseButtons: true,
          });
          if (!res.isConfirmed) return;
          this._send({ or_number: res.value || '' });
        } else {
          var c = await CPSU.confirm({ title: 'Mark delivery as Unpaid?', confirmText: 'Yes, mark Unpaid' });
          if (!c.isConfirmed) return;
          this._send({});
        }
      },
      _send(body) {
        var self = this;
        $.ajax({ url: cfg.url, method: 'PATCH', data: body })
          .done(function (d) {
            self.paid = d.is_paid;
            self.orNumber = d.or_number;
            self.paidInfo = d.is_paid ? (d.paid_at + ' · ' + d.paid_by) : null;
            setTimeout(function () { window.lucide && lucide.createIcons(); }, 50);
            CPSU.toast('Payment status updated', 'success');
          })
          .fail(function () { CPSU.toast('Could not update payment status', 'error'); });
      },
    };
  }
</script>
@endpush
@endsection
