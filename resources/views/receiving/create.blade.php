@extends('layouts.app')

@section('title', 'New Delivery')
@section('header', 'New Delivery')

@section('content')
<div class="mb-4">
    <a href="{{ route('deliveries.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Deliveries
    </a>
</div>

@include('receiving.partials.form', ['delivery' => null])
@endsection
