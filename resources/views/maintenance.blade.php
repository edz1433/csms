@extends('errors.minimal')

@section('code', '503')
@section('icon', 'wrench')
@section('message', 'Under maintenance')
@section('detail', $message)

@section('extra')
    @if (!empty($until))
        <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/80 border border-cpsu-border px-4 py-1.5 text-xs font-semibold text-gray-600">
            <span class="h-1.5 w-1.5 rounded-full bg-cpsu-gold animate-pulse"></span>
            Expected back {{ \Illuminate\Support\Carbon::parse($until)->format('M d, Y · g:i A') }}
        </p>
    @endif
    <p class="text-xs text-gray-400 mt-6">
        Administrators can still sign in to finish the work.
    </p>
@endsection
