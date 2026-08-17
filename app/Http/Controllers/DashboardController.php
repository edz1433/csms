<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\InspectionAcceptanceReport;
use App\Models\Item;
use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        // Group the trend by month for long ranges, otherwise by day.
        $byMonth = $from->diffInDays($to) > 62;

        $stats = [
            'total_items' => Item::where('is_active', true)->count(),
            'on_hand_units' => (float) Item::sum('on_hand_qty'),
            'releases_in_range' => Release::whereBetween('released_at', [$from, $to])->count(),
            'deliveries_in_range' => Delivery::whereBetween('received_at', [$from, $to])->count(),
            'pending_payments' => InspectionAcceptanceReport::where('is_paid', false)->count(),
        ];

        $trend = $this->trend($from, $to, $byMonth);
        $flow = $this->stockFlow($from, $to, $byMonth);

        return view('dashboard', [
            'stats' => $stats,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'trend' => $trend,          // deliveries vs releases (counts) per period
            'flow' => $flow,            // qty in vs qty out per period
            'byLocation' => $this->byLocation($from, $to),
            'byAccount' => $this->byAccount($from, $to),
            'topItems' => $this->topItems($from, $to),
            'payment' => [
                'paid' => InspectionAcceptanceReport::whereBetween('iar_date', [$from->toDateString(), $to->toDateString()])->where('is_paid', true)->count(),
                'unpaid' => InspectionAcceptanceReport::whereBetween('iar_date', [$from->toDateString(), $to->toDateString()])->where('is_paid', false)->count(),
            ],
            'lowStock' => Item::with('unit')->where('is_active', true)->orderBy('on_hand_qty')->limit(6)->get(),
            'recentReleases' => Release::with('location')->latest('released_at')->limit(6)->get(),
        ]);
    }

    /* ================= helpers ================= */

    private function range(Request $request): array
    {
        // Default to year-to-date so the trend charts have something to show.
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->startOfYear();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfDay();

        return [$from, $to];
    }

    /** Ordered [key => label] buckets across the range, by day or month. */
    private function buckets(Carbon $from, Carbon $to, bool $byMonth): array
    {
        $keyFmt = $byMonth ? 'Y-m' : 'Y-m-d';
        $labelFmt = $byMonth ? 'M Y' : 'M d';

        $cursor = $byMonth ? $from->copy()->startOfMonth() : $from->copy()->startOfDay();
        $end = $to->copy();
        $out = [];

        // Cap the number of buckets so a huge range never explodes the chart.
        while ($cursor <= $end && count($out) < 370) {
            $out[$cursor->format($keyFmt)] = $cursor->format($labelFmt);
            $byMonth ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        return [$out, $keyFmt];
    }

    /** Deliveries vs releases counts per period. */
    private function trend(Carbon $from, Carbon $to, bool $byMonth): array
    {
        [$labels, $keyFmt] = $this->buckets($from, $to, $byMonth);
        $del = array_fill_keys(array_keys($labels), 0);
        $rel = array_fill_keys(array_keys($labels), 0);

        foreach (Delivery::whereBetween('received_at', [$from, $to])->pluck('received_at') as $d) {
            $k = Carbon::parse($d)->format($keyFmt);
            if (isset($del[$k])) {
                $del[$k]++;
            }
        }
        foreach (Release::whereBetween('released_at', [$from, $to])->pluck('released_at') as $r) {
            $k = Carbon::parse($r)->format($keyFmt);
            if (isset($rel[$k])) {
                $rel[$k]++;
            }
        }

        return [
            'labels' => array_values($labels),
            'deliveries' => array_values($del),
            'releases' => array_values($rel),
        ];
    }

    /** Quantity received (in) vs released (out) per period. */
    private function stockFlow(Carbon $from, Carbon $to, bool $byMonth): array
    {
        [$labels, $keyFmt] = $this->buckets($from, $to, $byMonth);
        $in = array_fill_keys(array_keys($labels), 0.0);
        $out = array_fill_keys(array_keys($labels), 0.0);

        $received = DB::table('delivery_items')
            ->join('deliveries', 'delivery_items.delivery_id', '=', 'deliveries.id')
            ->whereBetween('deliveries.received_at', [$from, $to])
            ->get(['deliveries.received_at as d', 'delivery_items.quantity as q']);
        foreach ($received as $row) {
            $k = Carbon::parse($row->d)->format($keyFmt);
            if (isset($in[$k])) {
                $in[$k] += (float) $row->q;
            }
        }

        $issued = DB::table('release_items')
            ->join('releases', 'release_items.release_id', '=', 'releases.id')
            ->whereBetween('releases.released_at', [$from, $to])
            ->get(['releases.released_at as d', 'release_items.quantity as q']);
        foreach ($issued as $row) {
            $k = Carbon::parse($row->d)->format($keyFmt);
            if (isset($out[$k])) {
                $out[$k] += (float) $row->q;
            }
        }

        return [
            'labels' => array_values($labels),
            'in' => array_map(fn ($v) => round($v, 2), array_values($in)),
            'out' => array_map(fn ($v) => round($v, 2), array_values($out)),
        ];
    }

    /** Total quantity released per receiving campus/office. */
    private function byLocation(Carbon $from, Carbon $to)
    {
        return DB::table('release_items')
            ->join('releases', 'release_items.release_id', '=', 'releases.id')
            ->join('locations', 'releases.location_id', '=', 'locations.id')
            ->whereBetween('releases.released_at', [$from, $to])
            ->groupBy('locations.id', 'locations.name')
            ->selectRaw('locations.name, SUM(release_items.quantity) as qty')
            ->orderByDesc('qty')->limit(8)->get();
    }

    /** Total quantity released per account title (RCA). */
    private function byAccount(Carbon $from, Carbon $to)
    {
        return DB::table('release_items')
            ->join('releases', 'release_items.release_id', '=', 'releases.id')
            ->join('account_titles', 'release_items.account_title_id', '=', 'account_titles.id')
            ->whereBetween('releases.released_at', [$from, $to])
            ->groupBy('release_items.rca_code', 'account_titles.name')
            ->selectRaw('release_items.rca_code, account_titles.name, SUM(release_items.quantity) as qty')
            ->orderByDesc('qty')->limit(8)->get();
    }

    /** Most-issued items by quantity. */
    private function topItems(Carbon $from, Carbon $to)
    {
        return DB::table('release_items')
            ->join('releases', 'release_items.release_id', '=', 'releases.id')
            ->join('items', 'release_items.item_id', '=', 'items.id')
            ->whereBetween('releases.released_at', [$from, $to])
            ->groupBy('items.id', 'items.name')
            ->selectRaw('items.name, SUM(release_items.quantity) as qty')
            ->orderByDesc('qty')->limit(6)->get();
    }
}
