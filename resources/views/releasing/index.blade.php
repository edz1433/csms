@extends('layouts.app')

@section('title', 'Releasing')
@section('header', 'Releasing — RIS Records')
@section('subheader', 'Stock issued to campuses and offices')

@section('content')
<div class="flex items-center justify-between gap-3 mb-4">
    <p class="text-sm text-gray-500 hidden sm:block">Each release deducts on-hand stock and records RCA codes for accounting.</p>
    <x-action-guard>
        <x-ui.button variant="primary" icon="plus" :href="route('releases.create')">New Release</x-ui.button>
    </x-action-guard>
</div>

<div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4" data-aos="fade-up">
    <div class="overflow-x-auto">
        <table id="releases-table" class="w-full text-sm"></table>
    </div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    CPSU.dataTable('#releases-table', @js(route('releases.index')), [
      { data: 'ris_number', title: 'RIS Number' },
      { data: 'location', title: 'Campus / Office' },
      { data: 'fund', title: 'Fund', orderable: false, searchable: false },
      { data: 'lines', title: 'Items', orderable: false, searchable: false, className: 'text-center' },
      { data: 'released_at', title: 'Date Released' },
      { data: 'action', title: '', orderable: false, searchable: false, className: 'text-right' },
    ], { order: [[4, 'desc']] });
  });
</script>
@endpush
@endsection
