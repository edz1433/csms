@extends('layouts.app')

@section('title', 'IAR '.$iar->iar_number)
@section('header', 'Inspection and Acceptance Report')
@section('subheader', $iar->iar_number.' - '.$iar->delivery->po_number)

@section('content')
<div class="mb-4 flex items-center justify-between print:hidden">
    <a href="{{ route('iars.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to IAR
    </a>
    <div class="flex gap-2">
        <x-ui.button variant="ghost" icon="printer" onclick="window.print()">Print</x-ui.button>
    </div>
</div>

<div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-6 max-w-4xl mx-auto" data-aos="fade-up">
    <div class="text-right text-xs text-gray-500">Appendix 62</div>
    <h2 class="text-center font-extrabold text-lg tracking-wide mt-2">INSPECTION AND ACCEPTANCE REPORT</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 text-sm mt-6">
        <div><span class="text-gray-500">Entity Name:</span> <b>CENTRAL PHILIPPINE STATE UNIVERSITY</b></div>
        <div><span class="text-gray-500">Fund Cluster:</span> <b>{{ $iar->delivery->fundCluster?->code ?? '___________' }}</b></div>
        <div><span class="text-gray-500">Supplier:</span> <b>{{ $iar->delivery->supplier?->name ?? '-' }}</b></div>
        <div><span class="text-gray-500">IAR No.:</span> <b class="font-mono">{{ $iar->iar_number }}</b></div>
        <div><span class="text-gray-500">PO No./Date:</span> <b>{{ $iar->delivery->po_number }} / {{ $iar->delivery->received_at?->format('m-d-Y') }}</b></div>
        <div><span class="text-gray-500">IAR Date:</span> <b>{{ $iar->iar_date?->format('M d, Y') }}</b></div>
        <div><span class="text-gray-500">Requisitioning Office/Dept.:</span> <b>{{ $iar->requisitioning_office ?? '-' }}</b></div>
        <div><span class="text-gray-500">Invoice No.:</span> <b>{{ $iar->invoice_number ?? '-' }}</b></div>
        <div><span class="text-gray-500">Responsibility Center Code:</span> <b>{{ $iar->responsibility_center_code ?? '-' }}</b></div>
        <div><span class="text-gray-500">Invoice Date:</span> <b>{{ $iar->invoice_date?->format('M d, Y') ?? '-' }}</b></div>
    </div>

    <table class="w-full text-sm mt-6 border-y border-cpsu-border">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg">
                <th class="py-2 px-3">Stock / Property No.</th>
                <th class="py-2 px-3">Description</th>
                <th class="py-2 px-3">Unit</th>
                <th class="py-2 px-3 text-right">Quantity</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-cpsu-border">
            @foreach ($iar->delivery->items as $line)
                <tr>
                    <td class="py-2 px-3 font-mono text-xs">{{ $line->item?->stock_number ?? '-' }}</td>
                    <td class="py-2 px-3">{{ $line->item?->name }}</td>
                    <td class="py-2 px-3">{{ $line->unit?->abbreviation }}</td>
                    <td class="py-2 px-3 text-right font-semibold">{{ number_format((float) $line->quantity, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-6 text-sm">
        <div class="rounded-lg border border-cpsu-border p-4">
            <h3 class="font-bold text-center mb-3">INSPECTION</h3>
            <p>Date Inspected: <b>{{ $iar->inspection_date?->format('M d, Y') ?? '________________' }}</b></p>
            <p class="mt-4">Inspected, verified and found in order as to quantity and specifications.</p>
            <div class="mt-10 text-center">
                <p class="font-bold uppercase">{{ $iar->inspection_officer ?? '____________________________' }}</p>
                <p class="text-xs text-gray-500">Inspection Officer / Inspection Committee</p>
            </div>
        </div>
        <div class="rounded-lg border border-cpsu-border p-4">
            <h3 class="font-bold text-center mb-3">ACCEPTANCE</h3>
            <p>Date Received: <b>{{ $iar->acceptance_date?->format('M d, Y') ?? '________________' }}</b></p>
            <p class="mt-4">
                <span class="font-semibold">{{ $iar->isComplete() ? '[x]' : '[ ]' }}</span> Complete
                <br>
                <span class="font-semibold">{{ $iar->isComplete() ? '[ ]' : '[x]' }}</span> Partial
                @if (!$iar->isComplete())
                    <span class="text-gray-500">(quantity: {{ number_format((float) $iar->partial_quantity, 2) }})</span>
                @endif
            </p>
            <div class="mt-10 text-center">
                <p class="font-bold uppercase">{{ $iar->accepted_by ?? '____________________________' }}</p>
                <p class="text-xs text-gray-500">Supply Officer / Authorized Representative</p>
            </div>
        </div>
    </div>

    @if ($iar->remarks)
        <div class="mt-5 text-sm">
            <p class="text-xs text-gray-400 uppercase">Remarks</p>
            <p>{{ $iar->remarks }}</p>
        </div>
    @endif

    @php $canPay = auth()->user()->isAdministrator() || auth()->user()->isAccountingStaff(); @endphp
    <div
        x-data="iarPayment({
            paid: {{ $iar->is_paid ? 'true' : 'false' }},
            orNumber: @js($iar->or_number),
            paidInfo: @js($iar->is_paid && $iar->paid_at ? $iar->paid_at->format('M d, Y').' - '.$iar->payer?->name : null),
            canPay: {{ $canPay ? 'true' : 'false' }},
            url: @js(route('iars.payment', $iar)),
        })"
        class="print:hidden mt-6 rounded-lg border border-cpsu-border bg-cpsu-bg/40 p-4 flex flex-wrap items-center justify-between gap-3"
    >
        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-500 uppercase">Accounting Payment</span>
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
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold transition-all active:scale-95"
                    :class="paid ? 'bg-white border border-cpsu-border text-cpsu-black hover:bg-cpsu-bg' : 'bg-cpsu-green hover:bg-cpsu-green-dark text-white'">
                <i :data-lucide="paid ? 'rotate-ccw' : 'wallet'" class="w-4 h-4"></i>
                <span x-text="paid ? 'Mark Unpaid' : 'Mark Paid'"></span>
            </button>
        @endif
    </div>
</div>

@push('scripts')
<script>
  function iarPayment(cfg) {
    return {
      paid: cfg.paid, orNumber: cfg.orNumber, paidInfo: cfg.paidInfo, canPay: cfg.canPay,
      async toggle() {
        if (!this.canPay) return;
        if (!this.paid) {
          var res = await Swal.fire({
            title: 'Mark IAR as Paid?',
            input: 'text',
            inputLabel: 'Official Receipt (OR) number',
            inputPlaceholder: 'e.g. 0098231',
            inputValidator: function (value) { return value ? undefined : 'OR number is required.'; },
            showCancelButton: true,
            confirmButtonText: 'Mark Paid',
            confirmButtonColor: '#0B6E2E',
            cancelButtonColor: '#DC2626',
            reverseButtons: true,
          });
          if (!res.isConfirmed) return;
          this._send({ or_number: res.value });
        } else {
          var c = await CPSU.confirm({ title: 'Mark IAR as Unpaid?', confirmText: 'Yes, mark Unpaid' });
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
            self.paidInfo = d.is_paid ? (d.paid_at + ' - ' + d.paid_by) : null;
            setTimeout(function () { window.lucide && lucide.createIcons(); }, 50);
            CPSU.toast('Payment status updated', 'success');
          })
          .fail(function (x) { CPSU.toast((x.responseJSON && x.responseJSON.message) || 'Could not update payment status', 'error'); });
      },
    };
  }
</script>
@endpush
@endsection
