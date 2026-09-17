@extends('layouts.app')

@section('title', 'New Delivery')
@section('header', 'New Delivery')
@section('subheader', 'Step 1 of 2 — open the delivery; items are added next')

@section('content')
<div class="mb-3">
    <a href="{{ route('deliveries.index') }}" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Deliveries
    </a>
</div>

@include('receiving.partials.form', ['delivery' => null])
@endsection
