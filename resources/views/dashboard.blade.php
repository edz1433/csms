@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Dashboard')
@section('subheader', 'Stock, receiving, releasing and payment analytics')

@section('content')
@php
    $cards = [
        ['label' => 'Active Items', 'value' => $stats['total_items'], 'icon' => 'package', 'accent' => 'bg-cpsu-green/10 text-cpsu-green', 'decimals' => 0],
        ['label' => 'Total On-Hand (units)', 'value' => $stats['on_hand_units'], 'icon' => 'layers', 'accent' => 'bg-cpsu-gold/20 text-cpsu-gold-dark', 'decimals' => 2],
        ['label' => 'Deliveries (range)', 'value' => $stats['deliveries_in_range'], 'icon' => 'truck', 'accent' => 'bg-indigo-100 text-indigo-700', 'decimals' => 0],
        ['label' => 'Releases (range)', 'value' => $stats['releases_in_range'], 'icon' => 'send', 'accent' => 'bg-blue-100 text-blue-700', 'decimals' => 0],
        ['label' => 'Pending Payments', 'value' => $stats['pending_payments'], 'icon' => 'wallet', 'accent' => 'bg-amber-100 text-amber-700', 'decimals' => 0],
    ];
@endphp

{{-- Date-range filter --}}
<form method="GET" class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
    @include('reports.partials.date-range', ['from' => $from, 'to' => $to])
    <x-ui.button variant="primary" icon="filter" type="submit">Apply</x-ui.button>
    <p class="text-xs text-gray-400 ml-auto self-center">Showing {{ \Illuminate\Support\Carbon::parse($from)->format('M d, Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('M d, Y') }}</p>
</form>

{{-- KPI cards --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
    @foreach ($cards as $i => $c)
        <div data-aos="fade-up" data-aos-delay="{{ $i * 60 }}"
             class="bg-white rounded-xl border border-cpsu-border p-5 shadow-sm transition-all duration-150 hover:shadow-lg hover:-translate-y-0.5">
            <div class="flex items-start justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide truncate">{{ $c['label'] }}</p>
                    <p class="mt-2 text-3xl font-extrabold text-cpsu-black countup"
                       data-value="{{ $c['value'] }}" data-decimals="{{ $c['decimals'] }}">0</p>
                </div>
                <span class="h-11 w-11 rounded-lg flex items-center justify-center shrink-0 {{ $c['accent'] }}">
                    <i data-lucide="{{ $c['icon'] }}" class="w-5 h-5"></i>
                </span>
            </div>
        </div>
    @endforeach
</div>

{{-- Activity trend (full width) --}}
<div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5 mt-6" data-aos="fade-up">
    <h3 class="font-bold text-sm mb-4 flex items-center gap-2"><i data-lucide="activity" class="w-4 h-4 text-cpsu-green"></i> Receiving vs Releasing Activity</h3>
    <div class="relative h-72"><canvas id="chart-trend"></canvas></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
    {{-- Stock flow --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <h3 class="font-bold text-sm mb-4 flex items-center gap-2"><i data-lucide="arrow-down-up" class="w-4 h-4 text-cpsu-green"></i> Stock Flow — Qty In vs Out</h3>
        <div class="relative h-64"><canvas id="chart-flow"></canvas></div>
    </div>

    {{-- Payment status --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up" data-aos-delay="60">
        <h3 class="font-bold text-sm mb-4 flex items-center gap-2"><i data-lucide="wallet" class="w-4 h-4 text-cpsu-green"></i> Delivery Payment Status</h3>
        <div class="relative h-64 mx-auto" style="max-width:320px"><canvas id="chart-payment"></canvas></div>
    </div>

    {{-- Releases by location --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <h3 class="font-bold text-sm mb-4 flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-cpsu-green"></i> Releases by Campus / Office</h3>
        <div class="relative h-64"><canvas id="chart-location"></canvas></div>
    </div>

    {{-- Releases by account title --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up" data-aos-delay="60">
        <h3 class="font-bold text-sm mb-4 flex items-center gap-2"><i data-lucide="book-open" class="w-4 h-4 text-cpsu-green"></i> Releases by Account Title (RCA)</h3>
        <div class="relative h-64 mx-auto" style="max-width:340px"><canvas id="chart-account"></canvas></div>
    </div>
</div>

{{-- Top items released (full width) --}}
<div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5 mt-4" data-aos="fade-up">
    <h3 class="font-bold text-sm mb-4 flex items-center gap-2"><i data-lucide="bar-chart-3" class="w-4 h-4 text-cpsu-green"></i> Most-Issued Items</h3>
    <div class="relative h-72"><canvas id="chart-top"></canvas></div>
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
  document.addEventListener('DOMContentLoaded', function () {
    // Count-up KPIs
    document.querySelectorAll('.countup').forEach(function (el) {
      var val = parseFloat(el.dataset.value) || 0;
      var dec = parseInt(el.dataset.decimals) || 0;
      var cu = new countUp.CountUp(el, val, { duration: 1.4, decimalPlaces: dec, separator: ',' });
      if (!cu.error) cu.start(); else el.textContent = val.toLocaleString();
    });

    var green = '#0B6E2E', gold = '#E6BF00', blue = '#2563EB', amber = '#F59E0B';
    var palette = ['#0B6E2E','#2563EB','#E6BF00','#16A34A','#9333EA','#0EA5E9','#F97316','#DC2626'];

    // Shared premium defaults
    if (window.Chart) {
      Chart.defaults.font.family = "'Inter','DejaVu Sans',sans-serif";
      Chart.defaults.font.size = 11;
      Chart.defaults.color = '#6b7280';
      Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17,24,39,.92)';
      Chart.defaults.plugins.tooltip.padding = 10;
      Chart.defaults.plugins.tooltip.cornerRadius = 8;
      Chart.defaults.plugins.tooltip.boxPadding = 4;
    }

    var trend = @js($trend);
    var flow = @js($flow);
    var loc = @js($byLocation->map(fn($r) => ['label' => $r->name, 'qty' => (float) $r->qty])->values());
    var acc = @js($byAccount->map(fn($r) => ['label' => $r->rca_code, 'qty' => (float) $r->qty])->values());
    var top = @js($topItems->map(fn($r) => ['label' => $r->name, 'qty' => (float) $r->qty])->values());
    var pay = @js($payment);

    var base = { responsive: true, maintainAspectRatio: false };
    var legendBottom = { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16, font: { size: 11 } } };
    var softGrid = { grid: { color: 'rgba(0,0,0,.05)', drawBorder: false } };
    var noGridX = { grid: { display: false, drawBorder: false } };

    var doughnutOpts = Object.assign({}, base, {
      cutout: '64%',
      plugins: { legend: legendBottom },
    });
    var hBarOpts = Object.assign({}, base, {
      indexAxis: 'y',
      plugins: { legend: { display: false } },
      scales: { x: Object.assign({ beginAtZero: true }, softGrid), y: noGridX },
    });

    // Receiving vs Releasing counts
    new Chart(document.getElementById('chart-trend'), {
      type: 'line',
      data: { labels: trend.labels, datasets: [
        { label: 'Deliveries', data: trend.deliveries, borderColor: blue, backgroundColor: 'rgba(37,99,235,.08)', borderWidth: 2, tension: .4, fill: true, pointRadius: 0, pointHoverRadius: 4 },
        { label: 'Releases', data: trend.releases, borderColor: green, backgroundColor: 'rgba(11,110,46,.08)', borderWidth: 2, tension: .4, fill: true, pointRadius: 0, pointHoverRadius: 4 },
      ] },
      options: Object.assign({}, base, {
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: legendBottom },
        scales: { x: noGridX, y: Object.assign({ beginAtZero: true, ticks: { precision: 0 } }, softGrid) },
      }),
    });

    // Qty in vs out
    new Chart(document.getElementById('chart-flow'), {
      type: 'bar',
      data: { labels: flow.labels, datasets: [
        { label: 'Qty In', data: flow.in, backgroundColor: green, borderRadius: 6, maxBarThickness: 28 },
        { label: 'Qty Out', data: flow.out, backgroundColor: gold, borderRadius: 6, maxBarThickness: 28 },
      ] },
      options: Object.assign({}, base, {
        plugins: { legend: legendBottom },
        scales: { x: noGridX, y: Object.assign({ beginAtZero: true }, softGrid) },
      }),
    });

    // Payment status
    new Chart(document.getElementById('chart-payment'), {
      type: 'doughnut',
      data: { labels: ['Paid', 'Unpaid'], datasets: [{ data: [pay.paid, pay.unpaid], backgroundColor: [green, amber], borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
      options: doughnutOpts,
    });

    // By location (horizontal bar)
    if (loc.length) new Chart(document.getElementById('chart-location'), {
      type: 'bar',
      data: { labels: loc.map(d => d.label), datasets: [{ label: 'Qty Issued', data: loc.map(d => d.qty), backgroundColor: green, borderRadius: 6, maxBarThickness: 22 }] },
      options: hBarOpts,
    });

    // By account (doughnut)
    if (acc.length) new Chart(document.getElementById('chart-account'), {
      type: 'doughnut',
      data: { labels: acc.map(d => d.label), datasets: [{ data: acc.map(d => d.qty), backgroundColor: palette, borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
      options: doughnutOpts,
    });

    // Top items (horizontal bar)
    if (top.length) new Chart(document.getElementById('chart-top'), {
      type: 'bar',
      data: { labels: top.map(d => d.label), datasets: [{ label: 'Qty Issued', data: top.map(d => d.qty), backgroundColor: blue, borderRadius: 6, maxBarThickness: 22 }] },
      options: hBarOpts,
    });
  });
</script>
@endpush
@endsection
