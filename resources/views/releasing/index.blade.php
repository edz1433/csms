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

<div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5" data-aos="fade-up">
    <table id="releases-table" class="w-full text-sm"></table>
</div>

@push('scripts')
<script>
  // Reversal (administrators only): confirm, return the issued quantities to
  // stock, delete the RIS, then refresh the table in place.
  window.CPSU.returnRelease = function (url, risNumber) {
    CPSU.confirm({
      title: 'Return stock & delete ' + risNumber + '?',
      html: 'Every quantity on this RIS goes back to on-hand inventory and the release is permanently deleted.<br><strong>This cannot be undone.</strong>',
      confirmText: 'Yes, return & delete',
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $.ajax({ url: url, method: 'POST', data: { _method: 'DELETE' }, headers: { Accept: 'application/json' } })
        .done(function (res) {
          CPSU.toast((res && res.message) || 'Release reversed', 'success');
          window.reloadTable['releases'] && window.reloadTable['releases']();
        })
        .fail(function (xhr) {
          CPSU.toast((xhr.responseJSON && xhr.responseJSON.message) || 'Could not reverse this release', 'error');
        });
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    var dt = CPSU.dataTable('#releases-table', @js(route('releases.index')), [
      { data: 'ris_number', title: 'RIS Number' },
      { data: 'location', title: 'Campus / Office' },
      { data: 'fund', title: 'Fund', orderable: false, searchable: false },
      { data: 'lines', title: 'Items', orderable: false, searchable: false, className: 'text-center' },
      { data: 'released_at', title: 'Date Released' },
      { data: 'action', title: '', orderable: false, searchable: false, className: 'text-right' },
    ], { order: [[4, 'desc']] });

    window.reloadTable['releases'] = function () { dt.ajax.reload(null, false); };
  });
</script>
@endpush
@endsection
