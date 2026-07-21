@extends('layouts.app')

@section('title', 'Receiving')
@section('header', 'Receiving — Deliveries')
@section('subheader', 'Incoming stock from suppliers (PO-based)')

@section('content')
<div class="flex items-center justify-between gap-3 mb-4">
    <p class="text-sm text-gray-500 hidden sm:block">Each delivery increases on-hand stock for its item lines.</p>
    <x-action-guard>
        <x-ui.button variant="primary" icon="plus" :href="route('deliveries.create')">New Delivery</x-ui.button>
    </x-action-guard>
</div>

<div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5" data-aos="fade-up">
    <table id="deliveries-table" class="w-full text-sm"></table>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    CPSU.dataTable('#deliveries-table', @js(route('deliveries.index')), [
      { data: 'po_number', title: 'PO Number' },
      { data: 'supplier', title: 'Supplier' },
      { data: 'lines', title: 'Items', orderable: false, searchable: false },
      { data: 'payment', title: 'Payment', orderable: false, searchable: false, className: 'text-center' },
      { data: 'received_at', title: 'Date Received' },
      { data: 'action', title: '', orderable: false, searchable: false, className: 'text-right' },
    ], { order: [[4, 'desc']] });
  });
</script>
@endpush
@endsection
