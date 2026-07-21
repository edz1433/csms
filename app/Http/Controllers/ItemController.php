<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResourceResponses;
use App\Models\AccountTitle;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ItemController extends Controller
{
    use ResourceResponses;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Item::query()->with(['unit', 'accountTitle']);

            return DataTables::eloquent($query)
                ->editColumn('stock_number', fn (Item $i) => $i->stock_number
                    ? '<span class="font-mono text-xs">'.e($i->stock_number).'</span>'
                    : '<span class="text-gray-300">—</span>')
                ->addColumn('unit', fn (Item $i) => e($i->unit?->abbreviation ?? '—'))
                ->addColumn('account', fn (Item $i) => $i->accountTitle
                    ? e($i->accountTitle->name).' <span class="font-mono text-xs text-cpsu-green">'.e($i->accountTitle->rca_code).'</span>'
                    : '<span class="text-gray-300">—</span>')
                ->addColumn('on_hand', fn (Item $i) => '<span class="font-semibold '.($i->on_hand_qty <= 0 ? 'text-cpsu-danger' : 'text-cpsu-black').'">'.number_format($i->on_hand_qty, 2).'</span>')
                ->addColumn('status', fn (Item $i) => $i->is_active
                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">Active</span>'
                    : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>')
                ->addColumn('action', function (Item $i) {
                    return view('inventory.partials.actions', [
                        'item' => $i,
                        'canWrite' => auth()->user()->isAdministrator(),
                    ])->render();
                })
                ->filterColumn('unit', fn ($q, $kw) => $q->whereHas('unit', fn ($u) => $u->where('abbreviation', 'like', "%{$kw}%")->orWhere('name', 'like', "%{$kw}%")))
                ->filterColumn('account', fn ($q, $kw) => $q->whereHas('accountTitle', fn ($a) => $a->where('name', 'like', "%{$kw}%")->orWhere('rca_code', 'like', "%{$kw}%")))
                ->orderColumn('on_hand', 'on_hand_qty $1')
                ->rawColumns(['stock_number', 'account', 'on_hand', 'status', 'action'])
                ->toJson();
        }

        return view('inventory.index', [
            'units' => Unit::orderBy('name')->pluck('abbreviation', 'id'),
            'accountTitles' => AccountTitle::where('is_active', true)->orderBy('name')->get()
                ->mapWithKeys(fn ($a) => [$a->id => $a->name.' — '.$a->rca_code]),
        ]);
    }

    public function show(Item $item)
    {
        $item->load(['unit', 'accountTitle']);

        // Combined in/out transaction timeline.
        $deliveries = $item->deliveryItems()
            ->with(['delivery.supplier', 'unit'])
            ->get()
            ->map(fn ($di) => [
                'type' => 'in',
                'date' => $di->delivery->received_at,
                'ref' => $di->delivery->po_number,
                'party' => $di->delivery->supplier?->name,
                'qty' => (float) $di->quantity,
                'unit' => $di->unit?->abbreviation,
                'rca' => null,
                'link' => route('deliveries.show', $di->delivery_id),
            ]);

        $releases = $item->releaseItems()
            ->with(['release.location', 'unit'])
            ->get()
            ->map(fn ($ri) => [
                'type' => 'out',
                'date' => $ri->release->released_at,
                'ref' => $ri->release->ris_number,
                'party' => $ri->release->location?->name,
                'qty' => (float) $ri->quantity,
                'unit' => $ri->unit?->abbreviation,
                'rca' => $ri->rca_code,
                'link' => route('releases.show', $ri->release_id),
            ]);

        $timeline = $this->timeline($item); // newest first for display

        return view('inventory.show', compact('item', 'timeline'));
    }

    /** Stock card as a CPSU-letterhead PDF (opens inline / in a new tab). */
    public function pdf(Item $item)
    {
        $item->load(['unit', 'accountTitle']);

        $timeline = $this->timeline($item, chronological: true); // oldest first (ledger)

        $headerPath = public_path('images/cpsu-letterhead.png');
        $header = is_file($headerPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath))
            : null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('inventory.pdf', [
            'item' => $item,
            'timeline' => $timeline,
            'header' => $header,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('StockCard-'.($item->stock_number ?? $item->id).'.pdf');
    }

    /**
     * Combined in/out transaction timeline with a running balance.
     * Default is newest-first; pass chronological: true for oldest-first (ledger).
     */
    private function timeline(Item $item, bool $chronological = false)
    {
        $deliveries = $item->deliveryItems()
            ->with(['delivery.supplier', 'unit'])
            ->get()
            ->map(fn ($di) => [
                'type' => 'in',
                'date' => $di->delivery->received_at,
                'ref' => $di->delivery->po_number,
                'party' => $di->delivery->supplier?->name,
                'qty' => (float) $di->quantity,
                'unit' => $di->unit?->abbreviation,
                'rca' => null,
                'link' => route('deliveries.show', $di->delivery_id),
            ]);

        $releases = $item->releaseItems()
            ->with(['release.location', 'unit'])
            ->get()
            ->map(fn ($ri) => [
                'type' => 'out',
                'date' => $ri->release->released_at,
                'ref' => $ri->release->ris_number,
                'party' => $ri->release->location?->name,
                'qty' => (float) $ri->quantity,
                'unit' => $ri->unit?->abbreviation,
                'rca' => $ri->rca_code,
                'link' => route('releases.show', $ri->release_id),
            ]);

        $rows = $deliveries->concat($releases)->sortBy('date')->values();

        // Seed with an opening balance so the ledger reconciles to the current
        // on-hand qty (initial/seeded stock isn't recorded as a delivery).
        $netMovement = $deliveries->sum('qty') - $releases->sum('qty');
        $balance = (float) $item->on_hand_qty - $netMovement;

        $rows = $rows->map(function ($row) use (&$balance) {
            $balance += $row['type'] === 'in' ? $row['qty'] : -$row['qty'];
            $row['balance'] = $balance;

            return $row;
        });

        return $chronological ? $rows : $rows->reverse()->values();
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        // Item code is automated (CS00001, CS00002, …) — never taken from input.
        // Retry a couple of times in case two items are created back-to-back.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $data['stock_number'] = $this->nextItemCode();
                Item::create($data);
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($attempt === 2) {
                    throw $e;
                }
            }
        }

        return $this->ok($request, 'Item created.');
    }

    public function update(Request $request, Item $item)
    {
        // The item code is fixed once assigned; only the other fields change.
        $item->update($this->validateData($request));

        return $this->ok($request, 'Item updated.');
    }

    /** Next automated item code, formatted CS00001. */
    private function nextItemCode(): string
    {
        $last = Item::where('stock_number', 'like', 'CS%')
            ->orderByRaw('CAST(SUBSTRING(stock_number, 3) AS UNSIGNED) DESC')
            ->value('stock_number');

        $next = $last ? ((int) substr($last, 2)) + 1 : 1;

        return sprintf('CS%05d', $next);
    }

    public function destroy(Request $request, Item $item)
    {
        if ($item->deliveryItems()->exists() || $item->releaseItems()->exists()) {
            return $this->fail($request, 'Cannot delete: this item has transaction history. Deactivate it instead.', 409);
        }

        $item->delete();

        return $this->ok($request, 'Item deleted.');
    }

    /**
     * Lightweight stock lookup used by the Releasing form (on-hand hint +
     * default unit / account title on item select).
     */
    public function stock(Item $item)
    {
        return response()->json([
            'id' => $item->id,
            'on_hand_qty' => (float) $item->on_hand_qty,
            'unit_id' => $item->unit_id,
            'unit_abbr' => $item->unit?->abbreviation,
            'account_title_id' => $item->account_title_id,
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_id' => ['required', 'exists:units,id'],
            'account_title_id' => ['nullable', 'exists:account_titles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
