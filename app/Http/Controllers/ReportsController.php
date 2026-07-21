<?php

namespace App\Http\Controllers;

use App\Models\FundCluster;
use App\Models\Item;
use App\Models\Location;
use App\Models\Release;
use App\Models\ReleaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    /** Reports hub + releases summary (grouped + charts). */
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        $summary = $this->releasesSummary($from, $to);

        return view('reports.index', array_merge($summary, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]));
    }

    /* ---------------- Stock Card ---------------- */

    public function stockCard(Request $request)
    {
        $items = Item::orderBy('name')->get(['id', 'name', 'stock_number']);
        $item = null;
        $rows = collect();

        if ($request->filled('item_id')) {
            $item = Item::with('unit')->findOrFail($request->integer('item_id'));
            $rows = $this->stockCardRows($item);
        }

        return view('reports.stock-card', compact('items', 'item', 'rows'));
    }

    /* ---------------- Payment Status ---------------- */

    public function paymentStatus(Request $request)
    {
        [$from, $to] = $this->range($request);
        $rows = $this->paymentRows($request, $from, $to);

        return view('reports.payment-status', [
            'rows' => $rows,
            'locations' => Location::orderBy('name')->get(['id', 'name', 'type']),
            'fundClusters' => FundCluster::orderBy('code')->get(['id', 'code']),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'location_id' => $request->input('location_id'),
                'fund_cluster_id' => $request->input('fund_cluster_id'),
                'status' => $request->input('status'),
            ],
            'totals' => [
                'paid' => $rows->where('is_paid', true)->count(),
                'unpaid' => $rows->where('is_paid', false)->count(),
            ],
        ]);
    }

    /* ---------------- Exports ---------------- */

    public function export(Request $request, string $report)
    {
        $format = $request->input('format', 'csv');
        [$from, $to] = $this->range($request);

        [$filename, $headers, $rows] = match ($report) {
            'payment-status' => $this->paymentExportData($request, $from, $to),
            'releases-summary' => $this->summaryExportData($from, $to),
            'stock-card' => $this->stockCardExportData($request),
            default => abort(404),
        };

        return $format === 'pdf'
            ? $this->pdf($report, $filename, $headers, $rows, $from, $to)
            : $this->csv($filename, $headers, $rows);
    }

    /* ================= helpers ================= */

    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfDay();

        return [$from, $to];
    }

    private function releasesSummary(Carbon $from, Carbon $to): array
    {
        $base = ReleaseItem::query()
            ->join('releases', 'release_items.release_id', '=', 'releases.id')
            ->whereBetween('releases.released_at', [$from, $to]);

        $byLocation = (clone $base)
            ->join('locations', 'releases.location_id', '=', 'locations.id')
            ->groupBy('locations.id', 'locations.name', 'locations.type')
            ->selectRaw('locations.name, locations.type, SUM(release_items.quantity) as qty, COUNT(DISTINCT releases.id) as ris_count')
            ->orderByDesc('qty')->get();

        $byFund = (clone $base)
            ->join('fund_clusters', 'releases.fund_cluster_id', '=', 'fund_clusters.id')
            ->groupBy('fund_clusters.id', 'fund_clusters.code', 'fund_clusters.name')
            ->selectRaw('fund_clusters.code, fund_clusters.name, SUM(release_items.quantity) as qty, COUNT(DISTINCT releases.id) as ris_count')
            ->orderByDesc('qty')->get();

        // Grouped/subtotaled by RCA code (snapshot on the line).
        $byAccount = (clone $base)
            ->join('account_titles', 'release_items.account_title_id', '=', 'account_titles.id')
            ->groupBy('release_items.rca_code', 'account_titles.name')
            ->selectRaw('release_items.rca_code, account_titles.name, SUM(release_items.quantity) as qty')
            ->orderByDesc('qty')->get();

        return [
            'byLocation' => $byLocation,
            'byFund' => $byFund,
            'byAccount' => $byAccount,
            'totalReleases' => Release::whereBetween('released_at', [$from, $to])->count(),
            'totalQty' => (float) (clone $base)->sum('release_items.quantity'),
        ];
    }

    private function stockCardRows(Item $item)
    {
        $in = $item->deliveryItems()->with(['delivery', 'unit'])->get()->map(fn ($d) => [
            'date' => $d->delivery->received_at,
            'ref' => $d->delivery->po_number,
            'type' => 'in',
            'qty' => (float) $d->quantity,
        ]);

        $out = $item->releaseItems()->with('release')->get()->map(fn ($r) => [
            'date' => $r->release->released_at,
            'ref' => $r->release->ris_number,
            'type' => 'out',
            'qty' => (float) $r->quantity,
        ]);

        $balance = 0;

        return $in->concat($out)->sortBy('date')->values()->map(function ($row) use (&$balance) {
            $balance += $row['type'] === 'in' ? $row['qty'] : -$row['qty'];
            $row['balance'] = $balance;

            return $row;
        });
    }

    private function paymentRows(Request $request, Carbon $from, Carbon $to)
    {
        return ReleaseItem::query()
            ->with(['item', 'accountTitle', 'unit', 'payer', 'release.location', 'release.fundCluster'])
            ->whereHas('release', function ($q) use ($request, $from, $to) {
                $q->whereBetween('released_at', [$from, $to]);
                if ($request->filled('location_id')) {
                    $q->where('location_id', $request->integer('location_id'));
                }
                if ($request->filled('fund_cluster_id')) {
                    $q->where('fund_cluster_id', $request->integer('fund_cluster_id'));
                }
            })
            ->when($request->input('status') === 'paid', fn ($q) => $q->where('is_paid', true))
            ->when($request->input('status') === 'unpaid', fn ($q) => $q->where('is_paid', false))
            ->get()
            ->sortByDesc(fn ($r) => $r->release->released_at)
            ->values();
    }

    private function paymentExportData(Request $request, Carbon $from, Carbon $to): array
    {
        $rows = $this->paymentRows($request, $from, $to)->map(fn ($r) => [
            $r->release->ris_number,
            $r->release->released_at?->format('Y-m-d'),
            $r->release->location?->name,
            $r->release->fundCluster?->code,
            $r->item?->name,
            $r->rca_code,
            number_format($r->quantity, 2),
            $r->unit?->abbreviation,
            $r->is_paid ? 'Paid' : 'Unpaid',
            $r->paid_at?->format('Y-m-d'),
            $r->payer?->name,
        ])->toArray();

        return ['payment-status-'.now()->format('Ymd'),
            ['RIS', 'Date', 'Location', 'Fund', 'Item', 'RCA', 'Qty', 'Unit', 'Status', 'Paid On', 'Paid By'],
            $rows];
    }

    private function summaryExportData(Carbon $from, Carbon $to): array
    {
        $s = $this->releasesSummary($from, $to);
        $rows = [];
        foreach ($s['byAccount'] as $r) {
            $rows[] = ['By Account Title', $r->rca_code.' — '.$r->name, number_format($r->qty, 2)];
        }
        foreach ($s['byLocation'] as $r) {
            $rows[] = ['By Location', $r->name.' ['.ucfirst($r->type).']', number_format($r->qty, 2)];
        }
        foreach ($s['byFund'] as $r) {
            $rows[] = ['By Fund Cluster', $r->code.' — '.$r->name, number_format($r->qty, 2)];
        }

        return ['releases-summary-'.now()->format('Ymd'), ['Grouping', 'Category', 'Total Qty Issued'], $rows];
    }

    private function stockCardExportData(Request $request): array
    {
        $item = Item::findOrFail($request->integer('item_id'));
        $rows = $this->stockCardRows($item)->map(fn ($r) => [
            $r['date']?->format('Y-m-d'),
            $r['ref'],
            $r['type'] === 'in' ? number_format($r['qty'], 2) : '',
            $r['type'] === 'out' ? number_format($r['qty'], 2) : '',
            number_format($r['balance'], 2),
        ])->toArray();

        return ['stock-card-'.$item->id.'-'.now()->format('Ymd'),
            ['Date', 'Reference', 'In', 'Out', 'Balance'], $rows];
    }

    private function csv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function pdf(string $report, string $filename, array $headers, array $rows, Carbon $from, Carbon $to)
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', [
            'title' => ucwords(str_replace('-', ' ', $report)),
            'headers' => $headers,
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename.'.pdf');
    }
}
