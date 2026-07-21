@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Dashboard')
@section('subheader', 'Overview of stock, releases and payments')

@section('content')
@php
    $cards = [
        ['label' => 'Active Items', 'value' => $stats['total_items'], 'icon' => 'package', 'accent' => 'bg-cpsu-green/10 text-cpsu-green', 'decimals' => 0],
        ['label' => 'Total On-Hand (units)', 'value' => $stats['on_hand_units'], 'icon' => 'layers', 'accent' => 'bg-cpsu-gold/20 text-cpsu-gold-dark', 'decimals' => 2],
        ['label' => 'Releases This Month', 'value' => $stats['releases_this_month'], 'icon' => 'send', 'accent' => 'bg-blue-100 text-blue-700', 'decimals' => 0],
        ['label' => 'Pending Payments', 'value' => $stats['pending_payments'], 'icon' => 'wallet', 'accent' => 'bg-amber-100 text-amber-700', 'decimals' => 0],
    ];
@endphp

{{-- KPI cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach ($cards as $i => $c)
        <div data-aos="fade-up" data-aos-delay="{{ $i * 60 }}"
             class="bg-white rounded-xl border border-cpsu-border p-5 shadow-sm transition-all duration-150 hover:shadow-lg hover:-translate-y-0.5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $c['label'] }}</p>
                    <p class="mt-2 text-3xl font-extrabold text-cpsu-black countup"
                       data-value="{{ $c['value'] }}" data-decimals="{{ $c['decimals'] }}">0</p>
                </div>
                <span class="h-11 w-11 rounded-lg flex items-center justify-center {{ $c['accent'] }}">
                    <i data-lucide="{{ $c['icon'] }}" class="w-5 h-5"></i>
                </span>
            </div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
    {{-- Low stock --}}
    <div data-aos="fade-up" class="bg-white rounded-xl border border-cpsu-border shadow-sm">
        <div class="px-5 py-4 border-b border-cpsu-border flex items-center gap-2">
            <i data-lucide="trending-down" class="w-4 h-4 text-cpsu-danger"></i>
            <h3 class="font-bold text-sm">Lowest Stock</h3>
        </div>
        <div class="divide-y divide-cpsu-border">
            @forelse ($lowStock as $item)
                <div class="px-5 py-3 flex items-center justify-between text-sm">
                    <div class="min-w-0">
                        <p class="font-medium truncate">{{ $item->name }}</p>
                        <p class="text-xs text-gray-400">{{ $item->stock_number }}</p>
                    </div>
                    <span class="font-bold {{ $item->on_hand_qty <= 0 ? 'text-cpsu-danger' : 'text-cpsu-black' }}">
                        {{ number_format($item->on_hand_qty, 2) }} <span class="text-xs font-normal text-gray-400">{{ $item->unit?->abbreviation }}</span>
                    </span>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-gray-400 text-center">No items yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Recent releases --}}
    <div data-aos="fade-up" data-aos-delay="60" class="bg-white rounded-xl border border-cpsu-border shadow-sm">
        <div class="px-5 py-4 border-b border-cpsu-border flex items-center gap-2">
            <i data-lucide="history" class="w-4 h-4 text-cpsu-green"></i>
            <h3 class="font-bold text-sm">Recent Releases</h3>
        </div>
        <div class="divide-y divide-cpsu-border">
            @forelse ($recentReleases as $rel)
                <div class="px-5 py-3 flex items-center justify-between text-sm">
                    <div class="min-w-0">
                        <p class="font-mono font-medium truncate">{{ $rel->ris_number }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $rel->location?->name }}</p>
                    </div>
                    <span class="text-xs text-gray-500">{{ $rel->released_at?->format('M d, Y') }}</span>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-gray-400 text-center">No releases yet.</p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.countup').forEach(el => {
      const val = parseFloat(el.dataset.value) || 0;
      const dec = parseInt(el.dataset.decimals) || 0;
      const cu = new countUp.CountUp(el, val, { duration: 1.4, decimalPlaces: dec, separator: ',' });
      if (!cu.error) cu.start(); else el.textContent = val.toLocaleString();
    });
  });
</script>
@endpush
@endsection
