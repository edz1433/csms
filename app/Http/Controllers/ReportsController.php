<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReportScope;
use App\Models\AccountTitle;
use App\Models\Delivery;
use App\Models\FundCluster;
use App\Models\InspectionAcceptanceReport;
use App\Models\Item;
use App\Models\Release;
use App\Models\ReleaseItem;
use App\Models\Supplier;
use App\Support\Qr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    use ReportScope;

    /** Reports hub — Requisition and Issue Slip (RIS): pick a release to view its slip. */
    public function index(Request $request)
    {
        $releases = Release::with(['location', 'items:id,release_id,account_title_id'])
            ->latest('released_at')->get()
            ->map(fn (Release $r) => [
                'value' => $r->id,
                'text' => $r->ris_number.' — '.($r->location?->name ?? '—')
                    .' ('.$r->released_at?->format('M d, Y').')',
                'funds' => array_filter([(int) $r->fund_cluster_id]),
                'accounts' => $r->items->pluck('account_title_id')->filter()
                    ->unique()->values()->map(fn ($id) => (int) $id)->all(),
            ]);

        return view('reports.index', ['releases' => $releases] + $this->scopeLists());
    }

    /** Render a single release as its COA Requisition and Issue Slip PDF. */
    public function risPdf(Request $request)
    {
        $request->validate(['release_id' => ['required', 'exists:releases,id']]);

        $release = Release::with(['location', 'fundCluster', 'releaser', 'items.item.unit', 'items.unit'])
            ->findOrFail($request->integer('release_id'));

        $headerPath = public_path('doc-sample/requisition-slip-header.png');
        $header = is_file($headerPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath))
            : null;

        $pdf = Pdf::loadView('releasing.pdf', [
            'release' => $release,
            'header' => $header,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('RIS-'.$release->ris_number.'.pdf');
    }

    /* ---------------- Stock Card ---------------- */

    public function stockCard(Request $request)
    {
        [$from, $to] = $this->range($request, 'year');
        $item = null;
        $rows = collect();

        if ($request->filled('item_id')) {
            $item = Item::with('unit')->findOrFail($request->integer('item_id'));
            $rows = $this->stockCardRows($item, $from, $to);
        }

        return view('reports.stock-card', [
            'items' => $this->scopedItemRecords(),
            'item' => $item,
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ] + $this->scopeLists());
    }

    /**
     * Stock Card as the official COA Appendix 58 layout (same as the item PDF),
     * scoped to a date range with a Balance Forwarded opening figure.
     */
    public function stockCardPdf(Request $request)
    {
        $request->validate(['item_id' => ['required', 'exists:items,id']]);
        [$from, $to] = $this->range($request, 'year');

        $item = Item::with(['unit', 'accountTitle'])->findOrFail($request->integer('item_id'));

        $deliveries = $item->deliveryItems()->with(['delivery.supplier'])->get()->map(fn ($di) => [
            'type' => 'in',
            'date' => $di->delivery->received_at,
            'ref' => $di->delivery->po_number,
            'party' => $di->delivery->supplier?->name,
            'qty' => (float) $di->quantity,
        ]);
        $releases = $item->releaseItems()->with(['release.location'])->get()->map(fn ($ri) => [
            'type' => 'out',
            'date' => $ri->release->released_at,
            'ref' => $ri->release->ris_number,
            'party' => $ri->release->location?->name,
            'qty' => (float) $ri->quantity,
        ]);

        $all = $deliveries->concat($releases)->sortBy('date')->values();

        // Opening balance (seeded stock isn't a recorded movement), then walk the
        // movements to get the running balance and the balance forwarded into the
        // requested period.
        $balance = (float) $item->on_hand_qty - ($deliveries->sum('qty') - $releases->sum('qty'));
        $beginning = $balance;

        $timeline = collect();
        foreach ($all as $row) {
            $balance += $row['type'] === 'in' ? $row['qty'] : -$row['qty'];
            $row['balance'] = $balance;

            if ($row['date'] && $row['date']->lt($from)) {
                $beginning = $balance;
            } elseif ($row['date'] && $row['date']->gte($from) && $row['date']->lte($to)) {
                $timeline->push($row);
            }
        }

        $headerPath = public_path('images/cpsu-letterhead.png');
        $header = is_file($headerPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath))
            : null;

        $pdf = Pdf::loadView('inventory.pdf', [
            'item' => $item,
            'timeline' => $timeline,
            'beginning' => $beginning,
            'header' => $header,
            // Optional: print the item's inventory tag alongside the card.
            'qr' => $request->boolean('qr') ? Qr::dataUri(route('inventory.scan', $item), 200) : null,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('StockCard-'.($item->stock_number ?? $item->id).'.pdf');
    }

    /* ---------------- Stock Status (all items, balance + valuation) ---------------- */

    public function stockStatus(Request $request)
    {
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);

        return view('reports.stock-status', [
            'filters' => [
                'fund_cluster_id' => $fundClusterId,
                'account_title_id' => $accountTitleId,
                'with_stock' => $request->boolean('with_stock'),
            ],
        ] + $this->scopeLists());
    }

    /** Every item with its unit, on-hand balance, unit price and total cost. */
    public function stockStatusPdf(Request $request)
    {
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);

        $rows = $this->scopedItems($fundClusterId, $accountTitleId)
            ->with(['unit', 'accountTitle'])
            // Optional: skip the long tail of items that carry no stock.
            ->when($request->boolean('with_stock'), fn ($q) => $q->where('on_hand_qty', '>', 0))
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'item' => $item,
                'qty' => (float) $item->on_hand_qty,
                'unit_cost' => (float) $item->unit_cost,
                'total_cost' => (float) $item->on_hand_qty * (float) $item->unit_cost,
            ]);

        $headerPath = public_path('images/cpsu-letterhead.png');
        $header = is_file($headerPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath))
            : null;

        $pdf = Pdf::loadView('reports.stock-status-pdf', [
            'rows' => $rows,
            'total' => $rows->sum('total_cost'),
            'fund' => $fundClusterId ? FundCluster::find($fundClusterId) : null,
            'accountTitle' => $accountTitleId ? AccountTitle::find($accountTitleId) : null,
            'asOf' => now(),
            'header' => $header,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('StockStatus-'.now()->format('Ymd').'.pdf');
    }

    /* ---------------- Inventory Summary (per account title, by month) ---------------- */

    public function accountSummary(Request $request)
    {
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);

        return view('reports.account-summary', [
            'years' => range((int) now()->year, (int) now()->year - 5),
            'filters' => [
                'fund_cluster_id' => $fundClusterId,
                'account_title_id' => $accountTitleId,
                'year' => $request->integer('year') ?: (int) now()->year,
            ],
        ] + $this->scopeLists());
    }

    /**
     * Purchases and issuances for one account title, month by month over a
     * year, opening with the balance carried into that year.
     */
    public function accountSummaryPdf(Request $request)
    {
        $request->validate(['account_title_id' => ['nullable', 'exists:account_titles,id']]);

        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);

        // No account title means every one of them, rolled into a single summary.
        $accountTitle = $accountTitleId ? AccountTitle::find($accountTitleId) : null;
        $label = $accountTitle?->name ?? 'All Account Titles';

        $year = $request->integer('year') ?: (int) now()->year;
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = (clone $from)->endOfYear();

        $purchases = $this->monthlyPurchases($accountTitleId, $fundClusterId, $from, $to);
        $issues = $this->monthlyIssues($accountTitleId, $fundClusterId, $from, $to);

        // The stock these titles carry today, walked back over the year's
        // movement to get what it was worth on 1 January.
        $currentValue = (float) Item::query()
            ->when($accountTitleId, fn ($q) => $q->where('account_title_id', $accountTitleId))
            ->selectRaw('COALESCE(SUM(on_hand_qty * unit_cost), 0) as value')->value('value');
        $beginning = $currentValue - (array_sum($purchases) - array_sum($issues));

        $headerPath = public_path('images/cpsu-letterhead.png');
        $header = is_file($headerPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath))
            : null;

        $pdf = Pdf::loadView('reports.account-summary-pdf', [
            'accountTitle' => $accountTitle,
            'label' => $label,
            'fund' => $fundClusterId ? FundCluster::find($fundClusterId) : null,
            'year' => $year,
            'asOf' => $to->isFuture() ? now() : $to,
            'openingDate' => (clone $from)->subDay(),
            'beginning' => $beginning,
            'purchases' => $purchases,
            'issues' => $issues,
            'header' => $header,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('InventorySummary-'.($accountTitle?->rca_code ?? 'all').'-'.$year.'.pdf');
    }

    /** [month number => amount] received within a period, optionally per title. */
    private function monthlyPurchases(?int $accountTitleId, ?int $fundClusterId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('delivery_items as di')
            ->join('deliveries as d', 'di.delivery_id', '=', 'd.id')
            ->join('items as i', 'di.item_id', '=', 'i.id')
            ->when($accountTitleId, fn ($q) => $q->where('i.account_title_id', $accountTitleId))
            ->whereBetween('d.received_at', [$from, $to])
            ->when($fundClusterId, fn ($q) => $q->where('d.fund_cluster_id', $fundClusterId))
            ->get([
                'd.received_at as moved_at',
                DB::raw('(di.quantity * di.unit_cost) as amount'),
            ]);

        return $this->byMonth($rows);
    }

    /** [month number => amount] issued within a period, optionally per title. */
    private function monthlyIssues(?int $accountTitleId, ?int $fundClusterId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('release_items as ri')
            ->join('releases as r', 'ri.release_id', '=', 'r.id')
            ->join('items as i', 'ri.item_id', '=', 'i.id')
            // The title snapshotted on the line wins; older lines fall back to
            // the item's own title.
            ->when($accountTitleId, fn ($q) => $q->where(fn ($w) => $w->where('ri.account_title_id', $accountTitleId)
                ->orWhere(fn ($f) => $f->whereNull('ri.account_title_id')->where('i.account_title_id', $accountTitleId))))
            ->whereBetween('r.released_at', [$from, $to])
            ->when($fundClusterId, fn ($q) => $q->where('r.fund_cluster_id', $fundClusterId))
            ->get([
                'r.released_at as moved_at',
                DB::raw('(ri.quantity * (CASE WHEN ri.unit_cost > 0 THEN ri.unit_cost ELSE i.unit_cost END)) as amount'),
            ]);

        return $this->byMonth($rows);
    }

    /** Fold dated amounts into a 1-12 month map (done in PHP so it stays portable). */
    private function byMonth($rows): array
    {
        $months = array_fill(1, 12, 0.0);

        foreach ($rows as $row) {
            $month = (int) Carbon::parse($row->moved_at)->format('n');
            $months[$month] += (float) $row->amount;
        }

        return $months;
    }

    /* ---------------- Payment Status ---------------- */

    public function paymentStatus(Request $request)
    {
        [$from, $to] = $this->range($request);
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);
        $rows = $this->paymentRows($request, $from, $to);

        return view('reports.payment-status', [
            'rows' => $rows,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'supplier_id' => $request->input('supplier_id'),
                'status' => $request->input('status'),
                'fund_cluster_id' => $fundClusterId,
                'account_title_id' => $accountTitleId,
            ],
            'totals' => [
                'paid' => $rows->where('is_paid', true)->count(),
                'unpaid' => $rows->where('is_paid', false)->count(),
            ],
        ] + $this->scopeLists());
    }

    /* ---------------- Inspection and Acceptance Reports ---------------- */

    public function iar(Request $request)
    {
        [$from, $to] = $this->range($request);
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);
        $rows = $this->iarRows($request, $from, $to);

        return view('reports.iar', [
            'rows' => $rows,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'supplier_id' => $request->input('supplier_id'),
                'acceptance_status' => $request->input('acceptance_status'),
                'payment_status' => $request->input('payment_status'),
                'fund_cluster_id' => $fundClusterId,
                'account_title_id' => $accountTitleId,
            ],
            'totals' => [
                'total' => $rows->count(),
                'paid' => $rows->where('is_paid', true)->count(),
                'unpaid' => $rows->where('is_paid', false)->count(),
                'partial' => $rows->where('acceptance_status', InspectionAcceptanceReport::STATUS_PARTIAL)->count(),
            ],
        ] + $this->scopeLists());
    }

    /* ---------------- RSMI (Report of Supplies and Materials Issued, App. 64) ---------------- */

    public function rsmi(Request $request)
    {
        [$from, $to] = $this->range($request);
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);

        $rows = $this->rsmiRows($from, $to, $fundClusterId, $accountTitleId);

        return view('reports.rsmi', [
            'rows' => $rows,
            'total' => (float) $rows->sum('amount'),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'fund_cluster_id' => $fundClusterId,
                'account_title_id' => $accountTitleId,
            ],
        ] + $this->scopeLists());
    }

    public function rsmiPdf(Request $request)
    {
        [$from, $to] = $this->range($request);
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);

        $rows = $this->rsmiRows($from, $to, $fundClusterId, $accountTitleId);
        $fund = $fundClusterId ? FundCluster::find($fundClusterId) : null;
        $accountTitle = $accountTitleId ? AccountTitle::find($accountTitleId) : null;

        $headerPath = public_path('images/cpsu-letterhead.png');
        $header = is_file($headerPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath))
            : null;

        // Serial No. is the year-month when the period sits inside one month.
        $serial = $from->isSameMonth($to) ? $from->format('Y-m') : $from->format('Y-m').' → '.$to->format('Y-m');

        $pdf = Pdf::loadView('reports.rsmi-pdf', [
            'rows' => $rows,
            'total' => (float) $rows->sum('amount'),
            'from' => $from,
            'to' => $to,
            'serial' => $serial,
            'fund' => $fund,
            'accountTitle' => $accountTitle,
            'header' => $header,
            'certifiedBy' => config('ris.rsmi.certified_by'),
            'postedBy' => config('ris.rsmi.posted_by'),
        ])->setPaper('legal', 'landscape');

        return $pdf->stream('RSMI-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf');
    }

    /**
     * Flat RSMI line list for a period. The effective unit cost is the snapshot
     * taken at issue; when that is zero (older data) it falls back to the item's
     * current cost so amounts still populate.
     */
    private function rsmiRows(Carbon $from, Carbon $to, ?int $fundClusterId, ?int $accountTitleId = null)
    {
        return DB::table('release_items as ri')
            ->join('releases as r', 'ri.release_id', '=', 'r.id')
            ->join('items as i', 'ri.item_id', '=', 'i.id')
            ->join('units as u', 'ri.unit_id', '=', 'u.id')
            ->leftJoin('account_titles as at', 'ri.account_title_id', '=', 'at.id')
            ->join('locations as loc', 'r.location_id', '=', 'loc.id')
            ->whereBetween('r.released_at', [$from, $to])
            ->when($fundClusterId, fn ($q) => $q->where('r.fund_cluster_id', $fundClusterId))
            // Account title as snapshotted on the issue line, falling back to
            // the item's own title for lines saved before it was captured.
            ->when($accountTitleId, fn ($q) => $q->where(fn ($w) => $w
                ->where('ri.account_title_id', $accountTitleId)
                ->orWhere(fn ($f) => $f->whereNull('ri.account_title_id')->where('i.account_title_id', $accountTitleId))
            ))
            ->orderBy('r.ris_number')->orderBy('ri.id')
            ->get([
                'r.ris_number',
                'ri.rca_code',
                'i.stock_number',
                'i.name as item_name',
                'at.name as account_title',
                'loc.name as particular',
                'u.abbreviation as unit',
                'ri.quantity',
                DB::raw('(CASE WHEN ri.unit_cost > 0 THEN ri.unit_cost ELSE i.unit_cost END) as unit_cost'),
                'r.remarks',
            ])
            ->map(function ($row) {
                $row->amount = round((float) $row->quantity * (float) $row->unit_cost, 2);

                return $row;
            });
    }

    /* ---------------- Exports ---------------- */

    public function export(Request $request, string $report)
    {
        $format = $request->input('format', 'csv');
        [$from, $to] = $this->range($request);

        [$filename, $headers, $rows] = match ($report) {
            'payment-status' => $this->paymentExportData($request, $from, $to),
            'iar' => $this->iarExportData($request, $from, $to),
            'releases-summary' => $this->summaryExportData($from, $to),
            'stock-card' => $this->stockCardExportData($request),
            'stock-status' => $this->stockStatusExportData($request),
            'rsmi' => $this->rsmiExportData($request, $from, $to),
            default => abort(404),
        };

        return $format === 'pdf'
            ? $this->pdf($report, $filename, $headers, $rows, $from, $to)
            : $this->csv($filename, $headers, $rows);
    }

    /* ================= helpers ================= */

    /**
     * Resolve the [from, to] date range from the request. When absent, fall
     * back to the current month (default) or the current year ($default='year',
     * used by ledger-style reports that track a running balance over time).
     */
    private function range(Request $request, string $default = 'month'): array
    {
        $start = $default === 'year' ? Carbon::now()->startOfYear() : Carbon::now()->startOfMonth();

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $start;
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

    private function stockCardRows(Item $item, ?Carbon $from = null, ?Carbon $to = null)
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

        // Running balance is computed across the full movement history so the
        // figures stay correct even when a date range hides earlier rows.
        $rows = $in->concat($out)->sortBy('date')->values()->map(function ($row) use (&$balance) {
            $balance += $row['type'] === 'in' ? $row['qty'] : -$row['qty'];
            $row['balance'] = $balance;

            return $row;
        });

        if ($from && $to) {
            $rows = $rows->filter(fn ($r) => $r['date'] && $r['date']->gte($from) && $r['date']->lte($to))->values();
        }

        return $rows;
    }

    private function paymentRows(Request $request, Carbon $from, Carbon $to)
    {
        return $this->baseIarReportQuery($request, $from, $to)
            ->when($request->input('status') === 'paid', fn ($q) => $q->where('is_paid', true))
            ->when($request->input('status') === 'unpaid', fn ($q) => $q->where('is_paid', false))
            ->get()
            ->sortByDesc(fn ($iar) => $iar->iar_date)
            ->values();
    }

    private function paymentExportData(Request $request, Carbon $from, Carbon $to): array
    {
        $rows = $this->paymentRows($request, $from, $to)->map(fn ($iar) => [
            $iar->iar_number,
            $iar->iar_date?->format('Y-m-d'),
            $iar->delivery?->po_number,
            $iar->delivery?->fundCluster?->code,
            $iar->delivery?->supplier?->name,
            $iar->delivery?->items?->count(),
            $iar->delivery?->receiver?->name,
            $iar->is_paid ? 'Paid' : 'Unpaid',
            $iar->or_number,
            $iar->paid_at?->format('Y-m-d'),
            $iar->payer?->name,
        ])->toArray();

        return ['payment-status-'.now()->format('Ymd'),
            ['IAR Number', 'IAR Date', 'PO Number', 'Fund Cluster', 'Supplier', 'Items', 'Received By', 'Status', 'OR Number', 'Paid On', 'Paid By'],
            $rows];
    }

    private function iarRows(Request $request, Carbon $from, Carbon $to)
    {
        return $this->baseIarReportQuery($request, $from, $to)
            ->when($request->filled('acceptance_status'), fn ($q) => $q->where('acceptance_status', $request->input('acceptance_status')))
            ->when($request->input('payment_status') === 'paid', fn ($q) => $q->where('is_paid', true))
            ->when($request->input('payment_status') === 'unpaid', fn ($q) => $q->where('is_paid', false))
            ->get()
            ->sortByDesc(fn ($iar) => $iar->iar_date)
            ->values();
    }

    private function baseIarReportQuery(Request $request, Carbon $from, Carbon $to)
    {
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);

        return InspectionAcceptanceReport::query()
            ->with(['delivery.fundCluster', 'delivery.supplier', 'delivery.receiver', 'delivery.items.item', 'payer', 'creator'])
            ->whereBetween('iar_date', [$from->toDateString(), $to->toDateString()])
            ->when($fundClusterId, fn ($q) => $q->whereHas('delivery', fn ($d) => $d->where('fund_cluster_id', $fundClusterId)))
            ->when($accountTitleId, fn ($q) => $q->whereHas(
                'delivery.items.item',
                fn ($i) => $i->where('account_title_id', $accountTitleId)
            ))
            ->when($request->filled('supplier_id'), fn ($q) => $q->whereHas(
                'delivery',
                fn ($d) => $d->where('supplier_id', $request->integer('supplier_id'))
            ));
    }

    private function iarExportData(Request $request, Carbon $from, Carbon $to): array
    {
        $rows = $this->iarRows($request, $from, $to)->map(fn ($iar) => [
            $iar->iar_number,
            $iar->iar_date?->format('Y-m-d'),
            $iar->delivery?->po_number,
            $iar->delivery?->supplier?->name,
            $iar->delivery?->fundCluster?->code,
            $iar->invoice_number,
            $iar->invoice_date?->format('Y-m-d'),
            ucfirst($iar->acceptance_status),
            $iar->isComplete() ? '' : number_format((float) $iar->partial_quantity, 2),
            $iar->is_paid ? 'Paid' : 'Unpaid',
            $iar->or_number,
            $iar->creator?->name,
        ])->toArray();

        return ['iar-'.$from->format('Ymd').'-'.$to->format('Ymd'),
            ['IAR Number', 'IAR Date', 'PO Number', 'Supplier', 'Fund Cluster', 'Invoice Number', 'Invoice Date', 'Acceptance', 'Partial Qty', 'Payment', 'OR Number', 'Created By'],
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

    private function rsmiExportData(Request $request, Carbon $from, Carbon $to): array
    {
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);
        $rows = $this->rsmiRows($from, $to, $fundClusterId, $accountTitleId)->map(fn ($r) => [
            $r->ris_number,
            $r->rca_code,
            $r->stock_number,
            $r->item_name,
            $r->account_title,
            $r->particular,
            $r->unit,
            number_format((float) $r->quantity, 2),
            number_format((float) $r->unit_cost, 2),
            number_format((float) $r->amount, 2),
            $r->remarks,
        ])->toArray();

        return ['rsmi-'.$from->format('Ymd').'-'.$to->format('Ymd'),
            ['RIS No.', 'RSMI Code', 'Stock No.', 'Items', 'Account Title', 'Particular', 'Unit', 'Quantity Issued', 'Unit Cost', 'Amount', 'Remarks'],
            $rows];
    }

    private function stockStatusExportData(Request $request): array
    {
        [$fundClusterId, $accountTitleId] = $this->scopeFilters($request);

        $rows = $this->scopedItems($fundClusterId, $accountTitleId)
            ->with(['unit', 'accountTitle'])
            ->when($request->boolean('with_stock'), fn ($q) => $q->where('on_hand_qty', '>', 0))
            ->orderBy('name')->get()
            ->values()
            ->map(fn (Item $item, int $i) => [
                $i + 1,
                $item->stock_number,
                $item->name,
                $item->unit?->abbreviation,
                number_format((float) $item->on_hand_qty, 2),
                number_format((float) $item->unit_cost, 2),
                number_format((float) $item->on_hand_qty * (float) $item->unit_cost, 2),
                $item->accountTitle?->name,
            ])->toArray();

        return ['stock-status-'.now()->format('Ymd'),
            ['No.', 'Stock No.', 'Stock Item', 'Unit', 'Stock Balance', 'Unit Price', 'Total Cost', 'Account Title'],
            $rows];
    }

    private function stockCardExportData(Request $request): array
    {
        [$from, $to] = $this->range($request, 'year');
        $item = Item::findOrFail($request->integer('item_id'));
        $rows = $this->stockCardRows($item, $from, $to)->map(fn ($r) => [
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
        $pdf = Pdf::loadView('reports.pdf', [
            'title' => ucwords(str_replace('-', ' ', $report)),
            'headers' => $headers,
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ])->setPaper('a4', 'landscape');

        // Stream inline so it previews in the browser tab (like the ledger and
        // RSMI); users can still save it from the built-in PDF viewer.
        return $pdf->stream($filename.'.pdf');
    }
}
