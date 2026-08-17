@extends('layouts.app')

@section('title', 'Inspection and Acceptance Reports')
@section('header', 'IAR')
@section('subheader', 'Inspection and Acceptance Reports created from deliveries')

@section('content')
<div class="flex items-center justify-between gap-3 mb-4">
    <p class="text-sm text-gray-500 hidden sm:block">Supply creates the IAR from a delivery. Accounting marks payment only from an IAR.</p>
    <x-action-guard>
        <x-ui.button variant="primary" icon="plus" :href="route('iars.create')">New IAR</x-ui.button>
    </x-action-guard>
</div>

<div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5" data-aos="fade-up">
    <table id="iars-table" class="w-full text-sm"></table>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    CPSU.dataTable('#iars-table', @js(route('iars.index')), [
      { data: 'iar_number', title: 'IAR Number' },
      { data: 'po_number', title: 'PO Number' },
      { data: 'supplier', title: 'Supplier' },
      { data: 'lines', title: 'Items', orderable: false, searchable: false },
      { data: 'payment', title: 'Payment', orderable: false, searchable: false, className: 'text-center' },
      { data: 'iar_date', title: 'IAR Date' },
      { data: 'created_by', title: 'Created By' },
      { data: 'action', title: '', orderable: false, searchable: false, className: 'text-right' },
    ], { order: [[5, 'desc']] });
  });
</script>
@endpush
@endsection
