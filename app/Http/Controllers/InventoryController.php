<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\InventoryCount;
use App\Models\InventorySession;
use App\Models\Item;
use App\Models\Unit;
use App\Support\Qr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Physical inventory (stock take).
 *
 * One session may be open at a time. Every item carries a QR code holding its
 * scan URL; opening that URL on a phone shows the count sheet for the item —
 * but only while a session is open. The scan pages poll `inventory.status`, so
 * closing a session takes effect on every open scanner within seconds without
 * anyone needing to refresh.
 */
class InventoryController extends Controller
{
    public function index()
    {
        $active = InventorySession::current();

        return view('inventory-count.index', [
            'active' => $active?->load(['starter', 'location']),
            'progress' => $active?->progress(),
            // Only a running count blocks starting another one.
            'hasActive' => (bool) $active,
            'sessions' => InventorySession::with(['starter', 'closer', 'location'])
                ->withCount('counts')->latest('started_at')->limit(25)->get(),
            'accountTitles' => AccountTitle::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'itemCount' => Item::where('is_active', true)->count(),
        ]);
    }

    /**
     * Add an inventory to the list. It starts as a draft — nothing is counted
     * until it is cast from the list's action column.
     */
    public function store(Request $request)
    {
        // Only a running inventory blocks a new one; once nothing is being
        // counted you can always start again.
        if (InventorySession::current()) {
            return response()->json([
                'ok' => false,
                'message' => 'An inventory is already running. Close it before starting another.',
            ], 422);
        }

        // A draft that was never cast is handed back instead of piling up
        // duplicates — it is still waiting to be started.
        if ($draft = InventorySession::whereStatus(InventorySession::STATUS_DRAFT)->latest('id')->first()) {
            $draft->seedLines();

            return response()->json([
                'ok' => true,
                'existing' => true,
                'id' => $draft->id,
                'reference' => $draft->reference,
                'lines' => $draft->counts()->count(),
                'message' => 'Inventory '.$draft->reference.' is already waiting to be cast.',
            ]);
        }

        // The sheet is built right away so a draft can be reviewed before it is
        // cast; the expected quantities are re-snapshotted at cast time.
        $session = DB::transaction(function () use ($request) {
            $session = InventorySession::create([
                'reference' => $this->nextReference(),
                'title' => 'Physical Count — '.now()->format('F d, Y'),
                'status' => InventorySession::STATUS_DRAFT,
                'started_by' => $request->user()->id,
                'started_at' => now(),
            ]);

            $session->seedLines();

            return $session;
        });

        return response()->json([
            'ok' => true,
            'id' => $session->id,
            'reference' => $session->reference,
            'lines' => $session->counts()->count(),
        ]);
    }

    /**
     * Cast (start) an inventory. A draft can always be cast, and the most
     * recent inventory can be re-cast after closing when the count needs
     * another pass; older ones stay sealed.
     */
    public function cast(InventorySession $session)
    {
        if (! $session->canBeCast()) {
            return response()->json([
                'ok' => false,
                'message' => $session->isActive()
                    ? 'This inventory is already running.'
                    : 'Only the latest inventory can be cast again — this one is closed for good.',
            ], 422);
        }

        if (InventorySession::current()) {
            return response()->json(['ok' => false, 'message' => 'Another inventory is already running.'], 422);
        }

        // Casting builds the sheet: every active item, with the stock it is
        // expected to have, waiting to be counted.
        $reopened = $session->isClosed();

        DB::transaction(function () use ($session, $reopened) {
            $session->update([
                'status' => InventorySession::STATUS_ACTIVE,
                // Re-opening keeps the original start date and every count
                // already recorded; it just clears the closure.
                'started_at' => $reopened ? $session->started_at : now(),
                'closed_by' => null,
                'closed_at' => null,
            ]);

            // Pick up items added since the sheet was built, and bring the
            // expected quantities up to date as of right now.
            $session->seedLines();
            $session->refreshExpectedQuantities();
        });

        return response()->json([
            'ok' => true,
            'reopened' => $reopened,
            'seeded' => $session->counts()->count(),
            'redirect' => route('inventory.show', $session),
        ]);
    }

    /** Uncast (close) an inventory — scanners stop accepting counts at once. */
    public function close(Request $request, InventorySession $session)
    {
        $session->update([
            'status' => InventorySession::STATUS_CLOSED,
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'status' => $session->status]);
    }

    public function show(InventorySession $session)
    {
        $session->load(['starter', 'closer', 'location']);

        // Self-heal: any open inventory always has a sheet, including ones
        // created before seeding existed.
        if (! $session->isClosed() && $session->counts()->doesntExist()) {
            $session->seedLines();
        }

        $rows = $session->counts()->with(['item', 'unit', 'counter'])->get()
            ->sortBy(fn (InventoryCount $c) => $c->item?->name)
            ->values()
            ->map(fn (InventoryCount $c) => [
                'id' => $c->id,
                'item_id' => $c->item_id,
                'name' => $c->item?->name,
                'stock_number' => $c->item?->stock_number,
                'unit_id' => $c->unit_id,
                'unit' => $c->unit?->abbreviation,
                'system_qty' => (float) $c->system_qty,
                'counted_qty' => $c->isCounted() ? (float) $c->counted_qty : null,
                'counted' => $c->isCounted(),
                'counted_by' => $c->counter?->name,
                'counted_at' => $c->counted_at?->format('M d · g:i A'),
            ]);

        return view('inventory-count.show', [
            'session' => $session,
            'progress' => $session->progress(),
            'rows' => $rows,
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
            'scannerUrl' => route('inventory.scanner'),
            'scannerQr' => $this->qrDataUri(route('inventory.scanner'), 200),
        ]);
    }

    /** Live status for the scan pages and the session dashboard. */
    public function status(Request $request)
    {
        $session = InventorySession::current();

        return response()->json([
            'active' => (bool) $session,
            'session' => $session ? [
                'id' => $session->id,
                'reference' => $session->reference,
                'title' => $session->title,
                'started_at' => $session->started_at?->format('M d, Y · g:i A'),
            ] : null,
            'progress' => $session?->progress(),
            'server_time' => now()->format('g:i:s A'),
        ]);
    }

    /** Camera scanner (desktop or phone) — decodes a QR then counts inline. */
    public function scanner()
    {
        $session = InventorySession::current();

        return view('inventory-count.scanner', [
            'session' => $session,
            'progress' => $session?->progress(),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
        ]);
    }

    /**
     * The URL held by every item QR code. Opening it with a phone camera lands
     * here; with no session open it shows the "no active inventory" state and
     * flips over by itself as soon as one is cast.
     */
    public function scan(Item $item)
    {
        $session = InventorySession::current();

        return view('inventory-count.scan', [
            'session' => $session,
            'item' => $item->load('unit'),
            'count' => $session?->counts()->where('item_id', $item->id)->first(),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
            'qr' => $this->qrDataUri(route('inventory.scan', $item), 180),
        ]);
    }

    /** Resolve a scanned payload (scan URL, item id or stock number) to an item. */
    public function lookup(Request $request)
    {
        $session = InventorySession::current();

        if (! $session) {
            return response()->json(['ok' => false, 'active' => false, 'message' => 'No active inventory.'], 409);
        }

        $code = trim((string) $request->input('code'));
        $item = $this->resolveItem($code);

        if (! $item) {
            return response()->json(['ok' => false, 'active' => true, 'message' => 'That code does not match any item.'], 404);
        }

        $count = $session->counts()->where('item_id', $item->id)->first();

        return response()->json([
            'ok' => true,
            'active' => true,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'stock_number' => $item->stock_number,
                'description' => $item->description,
                'unit_id' => $item->unit_id,
                'unit' => $item->unit?->abbreviation,
                'system_qty' => (float) $item->on_hand_qty,
                // Null when the line is still uncounted, so the scanner offers
                // the system figure rather than a misleading zero.
                'counted_qty' => $count?->isCounted() ? (float) $count->counted_qty : null,
                'counted_at' => $count?->counted_at?->format('g:i A'),
            ],
            'progress' => $session->progress(),
        ]);
    }

    /**
     * Record (or correct) the counted quantity for one item. The chosen unit is
     * stored on the count line and also becomes the item's unit when it differs
     * — counting is when the real packaging gets discovered.
     */
    public function count(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'counted_qty' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $session = InventorySession::current();

        if (! $session) {
            return response()->json([
                'ok' => false, 'active' => false,
                'message' => 'No active inventory — this count was not saved.',
            ], 409);
        }

        $item = Item::findOrFail($data['item_id']);

        $count = DB::transaction(function () use ($session, $item, $data, $request) {
            $count = $session->counts()->where('item_id', $item->id)->first();

            $count = $session->counts()->updateOrCreate(
                ['item_id' => $item->id],
                [
                    'unit_id' => $data['unit_id'],
                    // Snapshot the system figure on the first scan only, so a
                    // correction later in the day keeps the original variance.
                    'system_qty' => $count?->system_qty ?? $item->on_hand_qty,
                    'counted_qty' => $data['counted_qty'],
                    'counted_by' => $request->user()->id,
                    'counted_at' => now(),
                    'remarks' => $data['remarks'] ?? null,
                ]
            );

            if ((int) $data['unit_id'] !== (int) $item->unit_id) {
                $item->update(['unit_id' => $data['unit_id']]);
            }

            return $count;
        });

        $count->load(['unit', 'counter']);

        return response()->json([
            'ok' => true,
            'active' => true,
            'count' => [
                'item_id' => $count->item_id,
                'counted_qty' => (float) $count->counted_qty,
                'system_qty' => (float) $count->system_qty,
                'variance' => $count->variance,
                'unit_id' => $count->unit_id,
                'unit' => $count->unit?->abbreviation,
                'counted' => true,
                'counted_by' => $count->counter?->name,
                'counted_at' => $count->counted_at->format('g:i A'),
            ],
            'progress' => $session->progress(),
        ]);
    }

    /**
     * Printable QR tag cards. Prints every active item by default, one item
     * with ?item_id=, or a subset narrowed by account title / stock on hand.
     */
    public function labels(Request $request)
    {
        $items = Item::with(['unit', 'accountTitle'])
            ->when($request->filled('item_id'), fn ($q) => $q->whereKey($request->integer('item_id')))
            ->when($request->filled('account_title_id'), fn ($q) => $q->where('account_title_id', $request->integer('account_title_id')))
            ->when($request->boolean('with_stock'), fn ($q) => $q->where('on_hand_qty', '>', 0))
            ->when(! $request->filled('item_id'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        $labels = $items->map(fn (Item $item) => [
            'item' => $item,
            'qr' => $this->qrDataUri(route('inventory.scan', $item), 240),
        ]);

        $pdf = Pdf::loadView('inventory-count.labels', [
            'labels' => $labels,
            'single' => $request->filled('item_id'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($request->filled('item_id')
            ? 'qr-label-'.($items->first()?->stock_number ?? $request->integer('item_id')).'.pdf'
            : 'inventory-qr-labels.pdf');
    }

    /** Count sheet as CSV, for filing alongside the COA forms. */
    public function export(InventorySession $session)
    {
        $rows = $session->counts()->with(['item', 'unit', 'counter'])->get()
            ->sortBy(fn (InventoryCount $c) => $c->item?->name)
            ->map(fn (InventoryCount $c) => [
                $c->item?->stock_number,
                $c->item?->name,
                $c->unit?->abbreviation,
                number_format((float) $c->system_qty, 2),
                $c->isCounted() ? number_format((float) $c->counted_qty, 2) : '',
                $c->isCounted() ? number_format($c->variance, 2) : '',
                $c->isCounted() ? 'Counted' : 'Not counted',
                $c->counter?->name,
                $c->counted_at?->format('Y-m-d H:i'),
                $c->remarks,
            ]);

        $filename = 'inventory-'.$session->reference.'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Stock No.', 'Item', 'Unit', 'System Qty', 'Counted Qty', 'Variance', 'Status', 'Counted By', 'Counted At', 'Remarks']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /* ================= helpers ================= */

    /**
     * INV-2026-0001, sequential within the year. Derived from the highest
     * reference already issued rather than a row count, so deleting an
     * inventory can never hand out a reference that is already taken.
     */
    private function nextReference(): string
    {
        $year = now()->format('Y');
        $prefix = 'INV-'.$year.'-';

        $last = InventorySession::where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** A scanned payload may be the full scan URL, a bare id, or a stock number. */
    private function resolveItem(string $code): ?Item
    {
        if ($code === '') {
            return null;
        }

        if (preg_match('#/inventory/scan/(\d+)#', $code, $m)) {
            return Item::with('unit')->find((int) $m[1]);
        }

        if (ctype_digit($code)) {
            return Item::with('unit')->find((int) $code);
        }

        return Item::with('unit')->where('stock_number', $code)->first();
    }

    private function qrDataUri(string $data, int $size): string
    {
        return Qr::dataUri($data, $size);
    }
}
