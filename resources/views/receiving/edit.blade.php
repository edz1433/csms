@extends('layouts.app')

@section('title', 'Edit Delivery '.$delivery->po_number)
@section('header', 'Edit Delivery')
@section('subheader', 'Record a later batch on PO '.$delivery->po_number.', or correct what was entered')

@section('content')
<div class="mb-4">
    <a href="{{ route('deliveries.show', $delivery) }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Delivery
    </a>
</div>

<div class="mb-4 rounded-xl border border-cpsu-border bg-white p-4 text-sm text-gray-600 flex items-start gap-2" data-aos="fade-up">
    <i data-lucide="info" class="w-4 h-4 mt-0.5 shrink-0 text-cpsu-green"></i>
    <span>
        Partial delivery? Raise the <b>Received</b> quantity of a line to the new running total (or add the item
        that only arrived now) and set that line's <b>Date Received</b>. Only the difference is added to on-hand stock.
    </span>
</div>

@include('receiving.partials.form', ['delivery' => $delivery])
@endsection
