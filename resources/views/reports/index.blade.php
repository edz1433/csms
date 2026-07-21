@extends('layouts.app')

@section('title', 'Reports')
@section('header', 'Reports')
@section('subheader', 'Releases summary, stock cards and payment status')

@section('content')
{{-- Report navigation cards --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <a href="{{ route('reports.index') }}" class="bg-cpsu-green text-white rounded-xl p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg">
        <i data-lucide="pie-chart" class="w-6 h-6 mb-2"></i>
        <p class="font-bold">Releases Summary</p>
        <p class="text-xs text-white/70">By fund, account title (RCA), location</p>
    </a>
    <a href="{{ route('reports.stock-card') }}" class="bg-white border border-cpsu-border rounded-xl p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg">
        <i data-lucide="scroll-text" class="w-6 h-6 mb-2 text-cpsu-green"></i>
        <p class="font-bold">Stock Card</p>
        <p class="text-xs text-gray-400">Running balance per item</p>
    </a>
    <a href="{{ route('reports.payment-status') }}" class="bg-white border border-cpsu-border rounded-xl p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg">
        <i data-lucide="wallet" class="w-6 h-6 mb-2 text-cpsu-green"></i>
        <p class="font-bold">Payment Status</p>
        <p class="text-xs text-gray-400">Paid / unpaid released items</p>
    </a>
</div>

{{-- Filter bar --}}
<form method="GET" class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
        <input type="date" name="from" value="{{ $from }}" class="rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
        <input type="date" name="to" value="{{ $to }}" class="rounded-lg border border-cpsu-border px-3 py-2 text-sm focus:border-cpsu-green outline-none">
    </div>
    <x-ui.button variant="primary" icon="filter" type="submit">Apply</x-ui.button>
    <div class="ml-auto flex items-center gap-2">
        <x-ui.button variant="ghost" icon="download" :href="route('reports.export', 'releases-summary').'?format=csv&from='.$from.'&to='.$to">CSV</x-ui.button>
        <x-ui.button variant="ghost" icon="file-text" :href="route('reports.export', 'releases-summary').'?format=pdf&from='.$from.'&to='.$to">PDF</x-ui.button>
    </div>
</form>

{{-- KPIs --}}
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-cpsu-border p-5 shadow-sm" data-aos="fade-up">
        <p class="text-xs text-gray-500 uppercase">Total Releases (RIS)</p>
        <p class="text-3xl font-extrabold text-cpsu-black mt-1">{{ number_format($totalReleases) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-cpsu-border p-5 shadow-sm" data-aos="fade-up" data-aos-delay="60">
        <p class="text-xs text-gray-500 uppercase">Total Qty Issued</p>
        <p class="text-3xl font-extrabold text-cpsu-black mt-1">{{ number_format($totalQty, 2) }}</p>
    </div>
</div>

{{-- Charts --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <h3 class="font-bold text-sm mb-3">Releases by Location</h3>
        <canvas id="chart-location" height="220"></canvas>
    </div>
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up" data-aos-delay="60">
        <h3 class="font-bold text-sm mb-3">Releases by Account Title (RCA)</h3>
        <canvas id="chart-account" height="220"></canvas>
    </div>
</div>

{{-- Grouped tables --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    @php
        $tables = [
            ['title' => 'By Account Title (RCA)', 'rows' => $byAccount, 'cat' => fn($r) => $r->rca_code.' — '.$r->name],
            ['title' => 'By Location', 'rows' => $byLocation, 'cat' => fn($r) => $r->name.' ['.ucfirst($r->type).']'],
            ['title' => 'By Fund Cluster', 'rows' => $byFund, 'cat' => fn($r) => $r->code.' — '.$r->name],
        ];
    @endphp
    @foreach ($tables as $t)
        <div class="bg-white rounded-xl border border-cpsu-border shadow-sm" data-aos="fade-up">
            <div class="px-4 py-3 border-b border-cpsu-border"><h3 class="font-bold text-sm">{{ $t['title'] }}</h3></div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-cpsu-border">
                    @forelse ($t['rows'] as $r)
                        <tr>
                            <td class="px-4 py-2.5">{{ ($t['cat'])($r) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold whitespace-nowrap">{{ number_format($r->qty, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-8 text-center text-gray-400">No data in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var green = '#0B6E2E', palette = ['#0B6E2E','#FFD500','#16A34A','#2563EB','#E6BF00','#65A30D','#0EA5E9','#9333EA','#DC2626','#F97316'];

    var locData = @js($byLocation->map(fn($r) => ['label' => $r->name, 'qty' => (float) $r->qty])->values());
    var accData = @js($byAccount->map(fn($r) => ['label' => $r->rca_code, 'qty' => (float) $r->qty])->values());

    if (locData.length) new Chart(document.getElementById('chart-location'), {
      type: 'bar',
      data: { labels: locData.map(d => d.label), datasets: [{ label: 'Qty Issued', data: locData.map(d => d.qty), backgroundColor: green, borderRadius: 6 }] },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    });

    if (accData.length) new Chart(document.getElementById('chart-account'), {
      type: 'doughnut',
      data: { labels: accData.map(d => d.label), datasets: [{ data: accData.map(d => d.qty), backgroundColor: palette }] },
      options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } } },
    });
  });
</script>
@endpush
@endsection
