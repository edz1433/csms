<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\FundCluster;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationReleaseCounter;
use App\Models\Release;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class ReleaseController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Release::query()
                ->with(['location', 'fundCluster', 'releaser'])
                ->withCount('items');

            return DataTables::eloquent($query)
                ->editColumn('released_at', fn (Release $r) => $r->released_at?->format('M d, Y'))
                ->editColumn('ris_number', fn (Release $r) => '<span class="font-mono font-semibold">'.e($r->ris_number).'</span>')
                ->addColumn('location', fn (Release $r) => e($r->location?->name).' <span class="text-xs text-gray-400">['.ucfirst($r->location?->type).']</span>')
                ->addColumn('fund', fn (Release $r) => e($r->fundCluster?->code))
                ->addColumn('lines', fn (Release $r) => $r->items_count.' item'.($r->items_count == 1 ? '' : 's'))
                ->addColumn('action', fn (Release $r) => view('releasing.partials.actions', ['release' => $r])->render())
                ->filterColumn('location', fn ($q, $kw) => $q->whereHas('location', fn ($l) => $l->where('name', 'like', "%{$kw}%")))
                ->rawColumns(['ris_number', 'location', 'action'])
                ->toJson();
        }

        return view('releasing.index');
    }

    public function create()
    {
        return view('releasing.create', [
            'fundClusters' => FundCluster::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'type']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
            'accountTitles' => AccountTitle::where('is_active', true)->orderBy('name')->get(['id', 'name', 'rca_code']),
            'items' => Item::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'stock_number', 'unit_id', 'account_title_id', 'on_hand_qty']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fund_cluster_id' => ['required', 'exists:fund_clusters,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'released_at' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:items,id'],
            'lines.*.account_title_id' => ['required', 'exists:account_titles,id'],
            'lines.*.unit_id' => ['required', 'exists:units,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], [
            'lines.required' => 'Add at least one item line.',
            'lines.*.quantity.gt' => 'Quantity must be greater than zero.',
            'lines.*.account_title_id.required' => 'Select an account title for every line.',
        ]);

        $release = DB::transaction(function () use ($data, $request) {
            // 1) Lock every referenced item row and re-check stock authoritatively.
            $itemIds = collect($data['lines'])->pluck('item_id')->all();
            $locked = Item::whereIn('id', $itemIds)->lockForUpdate()->get()->keyBy('id');

            // Aggregate requested qty per item (an item could appear on 2 lines).
            $requested = [];
            foreach ($data['lines'] as $line) {
                $requested[$line['item_id']] = ($requested[$line['item_id']] ?? 0) + (float) $line['quantity'];
            }

            $insufficient = [];
            foreach ($requested as $itemId => $qty) {
                $item = $locked[$itemId] ?? null;
                if (! $item || (float) $item->on_hand_qty < $qty) {
                    $insufficient[] = $item
                        ? $item->name.' (on hand '.number_format($item->on_hand_qty, 2).', requested '.number_format($qty, 2).')'
                        : 'Item #'.$itemId;
                }
            }

            if ($insufficient) {
                // Roll back the whole release — never a partial release.
                throw ValidationException::withMessages([
                    'lines' => 'Insufficient stock for: '.implode('; ', $insufficient),
                ]);
            }

            // 2) Generate the per-location RIS sequence under a locked counter.
            $location = Location::whereKey($data['location_id'])->firstOrFail();
            $counter = LocationReleaseCounter::lockForUpdate()
                ->firstOrCreate(['location_id' => $location->id], ['last_sequence' => 0]);
            $sequence = $counter->last_sequence + 1;
            $counter->update(['last_sequence' => $sequence]);

            $releasedAt = Carbon::parse($data['released_at']);
            $risNumber = sprintf('%s-%s-%s-%d',
                $releasedAt->format('Y'),
                $releasedAt->format('m'),
                $location->code,
                $sequence
            );

            // 3) Create the release header.
            $release = Release::create([
                'ris_number' => $risNumber,
                'fund_cluster_id' => $data['fund_cluster_id'],
                'location_id' => $location->id,
                'released_by' => $request->user()->id,
                'released_at' => $releasedAt,
                'remarks' => $data['remarks'] ?? null,
            ]);

            // 4) Create lines (snapshot RCA code), decrement stock under lock.
            $accountTitles = AccountTitle::whereIn('id', collect($data['lines'])->pluck('account_title_id'))
                ->get()->keyBy('id');

            foreach ($data['lines'] as $line) {
                $rca = $accountTitles[$line['account_title_id']]->rca_code ?? '';

                $release->items()->create([
                    'item_id' => $line['item_id'],
                    'account_title_id' => $line['account_title_id'],
                    'rca_code' => $rca, // immutable snapshot at release time
                    'unit_id' => $line['unit_id'],
                    'quantity' => $line['quantity'],
                ]);

                $locked[$line['item_id']]->decrement('on_hand_qty', $line['quantity']);
            }

            return $release;
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'redirect' => route('releases.show', $release)]);
        }

        return redirect()->route('releases.show', $release)->with('success', 'Release recorded. Stock updated.');
    }

    public function show(Release $release)
    {
        $release->load(['location', 'fundCluster', 'releaser', 'items.item', 'items.unit', 'items.accountTitle']);

        return view('releasing.show', compact('release'));
    }
}
