@extends('layouts.app')

@php
    // No lines yet = this is step 2 of the two-step receiving flow, not an edit.
    $entering = $delivery->items->isEmpty();
@endphp

@section('title', ($entering ? 'Add Items ' : 'Edit Delivery ').$delivery->poLabel())
@section('header', $entering ? 'Add Items Received' : 'Edit Delivery')
@section('subheader', $entering
    ? 'Step 2 of 2 — enter every item received on '.$delivery->poLabel()
    : 'Record a later batch on '.$delivery->poLabel().', or correct what was entered')

@section('content')
{{-- Back link, hint and the missing-PO warning share one line: on this screen
     the vertical space belongs to the item table. --}}
<div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs">
    <a href="{{ route('deliveries.show', $delivery) }}" class="inline-flex items-center gap-1 text-gray-500 hover:text-cpsu-green shrink-0">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Delivery
    </a>

    @if (! $delivery->po_number)
        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-700">
            <i data-lucide="alert-triangle" class="w-3 h-3"></i> No PO number yet
        </span>
    @endif

    <span class="text-gray-500">
        @if ($entering)
            Add every item received — each is credited to on-hand stock on save. Come back any time to finish the list.
        @else
            Partial delivery? Raise a line's <b>Received</b> quantity to the new running total; only the difference hits stock.
        @endif
    </span>
</div>

@include('receiving.partials.form', ['delivery' => $delivery])
@endsection
