<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResourceResponses;
use App\Models\AccountTitle;
use App\Models\Item;
use App\Models\Unit;
use App\Support\ItemImport;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ItemController extends Controller
{
    use ResourceResponses;

    /**
     * Most imported items an import call will hand back to its caller. A form
     * that turns each one into a row stops being usable long before this.
     */
    private const RETURNED_ITEMS_LIMIT = 300;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Item::query()->with(['unit', 'accountTitle']);

            return DataTables::eloquent($query)
                ->editColumn('stock_number', fn (Item $i) => $i->stock_number
                    ? '<span class="font-mono text-xs">'.e($i->stock_number).'</span>'
                    : '<span class="text-gray-300">—</span>')
                // Names can run long (a full specification is often the name),
                // so the cell wraps and keeps the description with it.
                ->editColumn('name', fn (Item $i) => '<div class="max-w-[26rem] min-w-[12rem]">'
                    .'<p class="font-medium leading-snug" style="overflow-wrap:anywhere">'.e($i->name).'</p>'
                    .($i->description
                        ? '<p class="text-xs text-gray-400 leading-snug mt-0.5 line-clamp-2" style="overflow-wrap:anywhere">'.e($i->description).'</p>'
                        : '')
                    .'</div>')
                ->addColumn('unit', fn (Item $i) => e($i->unit?->abbreviation ?? '—'))
                ->addColumn('account', fn (Item $i) => $i->accountTitle
                    ? e($i->accountTitle->name).' <span class="font-mono text-xs text-cpsu-green">'.e($i->accountTitle->rca_code).'</span>'
                    : '<span class="text-gray-300">—</span>')
                ->addColumn('unit_cost', fn (Item $i) => number_format($i->unit_cost, 2))
                ->addColumn('on_hand', fn (Item $i) => '<span class="font-semibold '.($i->on_hand_qty <= 0 ? 'text-cpsu-danger' : 'text-cpsu-black').'">'.number_format($i->on_hand_qty, 2).'</span>')
                ->addColumn('status', fn (Item $i) => $i->is_active
                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">Active</span>'
                    : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>')
                ->addColumn('action', function (Item $i) {
                    return view('inventory.partials.actions', [
                        'item' => $i,
                        'canWrite' => auth()->user()->canWrite(),
                    ])->render();
                })
                ->filterColumn('unit', fn ($q, $kw) => $q->whereHas('unit', fn ($u) => $u->where('abbreviation', 'like', "%{$kw}%")->orWhere('name', 'like', "%{$kw}%")))
                ->filterColumn('account', fn ($q, $kw) => $q->whereHas('accountTitle', fn ($a) => $a->where('name', 'like', "%{$kw}%")->orWhere('rca_code', 'like', "%{$kw}%")))
                ->orderColumn('on_hand', 'on_hand_qty $1')
                ->orderColumn('unit_cost', 'unit_cost $1')
                ->rawColumns(['stock_number', 'name', 'account', 'on_hand', 'status', 'action'])
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

        // Opening (Balance Forwarded) = on-hand minus net recorded movement.
        $netMovement = (float) $item->deliveryItems()->sum('quantity') - (float) $item->releaseItems()->sum('quantity');
        $beginning = (float) $item->on_hand_qty - $netMovement;

        $headerPath = public_path('images/cpsu-letterhead.png');
        $header = is_file($headerPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath))
            : null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('inventory.pdf', [
            'item' => $item,
            'timeline' => $timeline,
            'beginning' => $beginning,
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
        $data['on_hand_qty'] = $data['on_hand_qty'] ?? 0;

        $item = null;

        // Item code is automated (CS00001, CS00002, …) — never taken from input.
        // Retry a couple of times in case two items are created back-to-back.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $data['stock_number'] = $this->nextItemCode();
                $item = Item::create($data);
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($attempt === 2) {
                    throw $e;
                }
            }
        }

        // The new item goes back with the response: Receiving creates an item
        // from its own screen and puts it straight on the delivery, without a
        // round trip through the item list.
        if ($request->wantsJson() && $item) {
            return response()->json([
                'ok' => true,
                'message' => 'Item created.',
                'item' => $this->itemPayload($item),
            ]);
        }

        return $this->ok($request, 'Item created.');
    }

    /**
     * The shape every picker on the receiving / releasing forms expects of an
     * item. Kept in one place so a new field reaches all of them at once.
     */
    private function itemPayload(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'stock_number' => $item->stock_number,
            'unit_id' => $item->unit_id,
            'account_title_id' => $item->account_title_id,
            'unit_cost' => (float) $item->unit_cost,
            'on_hand_qty' => (float) $item->on_hand_qty,
        ];
    }

    public function update(Request $request, Item $item)
    {
        // The item code is fixed once assigned; only the other fields change.
        $data = $this->validateData($request);
        $data['on_hand_qty'] = $data['on_hand_qty'] ?? 0;

        $item->update($data);

        return $this->ok($request, 'Item updated.');
    }

    /**
     * Bulk item import from an uploaded CSV / TXT list.
     * Three columns are read — item name, unit cost,
     * description — matched by header name, or positionally in that order when
     * the list has no header. Only the name is required: a blank (or "NULL")
     * cost becomes 0.00 and a blank description is stored as NULL. Every
     * imported item is unit "pcs" and Active, with an automated code (CS00001…).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'update_existing' => ['nullable', 'boolean'],
            // Receiving asks for the imported items themselves, so it can put
            // them on the delivery it is in the middle of entering.
            'with_items' => ['nullable', 'boolean'],
        ], [], ['file' => 'CSV file']);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'txt', 'tsv'], true)) {
            return $this->fail($request, in_array($extension, ['xls', 'xlsx'], true)
                ? 'Excel workbooks are not read directly — in Excel choose File ▸ Save As ▸ CSV, then upload that file.'
                : 'Upload a .csv, .tsv or .txt list.');
        }

        $raw = (string) file_get_contents($file->getRealPath());

        if (trim($raw) === '') {
            return $this->fail($request, 'That file is empty — nothing to import.');
        }

        $rows = ItemImport::parse($raw);

        if ($rows === []) {
            return $this->fail($request, 'No readable rows were found in that list.');
        }

        if (count($rows) > ItemImport::MAX_ROWS) {
            return $this->fail($request, 'That list has '.number_format(count($rows)).' rows — import at most '
                .number_format(ItemImport::MAX_ROWS).' at a time and split the rest into a second file.');
        }

        $importer = new ItemImport($request->boolean('update_existing'));
        $summary = $importer->run($rows);

        $response = [
            'ok' => true,
            'summary' => $summary,
            'message' => $this->importMessage($summary),
        ];

        if ($request->boolean('with_items')) {
            $ids = $importer->affectedIds();

            // A caller that puts these on screen as form rows cannot use
            // thousands of them; hand back a workable slice and say so.
            $response['items_total'] = count($ids);
            $response['items_truncated'] = count($ids) > self::RETURNED_ITEMS_LIMIT;
            $response['items'] = Item::whereIn('id', array_slice($ids, 0, self::RETURNED_ITEMS_LIMIT))
                ->orderBy('name')
                ->get()
                ->map(fn (Item $i) => $this->itemPayload($i))
                ->all();
        }

        return response()->json($response);
    }

    /** Plain-language result line for the import modal and its toast. */
    private function importMessage(array $summary): string
    {
        $parts = [];

        foreach (['created' => 'added', 'updated' => 'updated', 'skipped' => 'skipped as duplicates', 'invalid' => 'without a name'] as $key => $label) {
            if (($summary[$key] ?? 0) > 0) {
                $parts[] = number_format($summary[$key]).' '.$label;
            }
        }

        if ($parts === []) {
            return 'Nothing to import — no usable rows in that list.';
        }

        $message = ucfirst(implode(', ', $parts)).'.';

        if (($summary['truncated'] ?? 0) > 0) {
            $message .= ' '.number_format($summary['truncated']).' very long name(s) were shortened, with the full text kept as the description.';
        }

        return $message;
    }

    /** A ready-made CSV showing exactly which columns the import understands. */
    public function importTemplate()
    {
        $rows = [
            ['Item Name', 'Unit Cost', 'Description'],
            ['Bond Paper A4 (sub. 20)', '250.00', 'Substance 20, 500 sheets'],
            ['Ballpen, black, 0.5mm', '12.50', ''],
            ['Alcohol, ethyl 70%, 500ml', '', 'Cost left blank — saved as 0.00'],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));   // BOM, so Excel opens it as UTF-8
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'item-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /** Next automated item code, formatted CS00001. */
    private function nextItemCode(): string
    {
        return Item::formatStockNumber(Item::nextStockNumberSequence());
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
            'name' => ['required', 'string', 'max:'.ItemImport::NAME_LIMIT],
            'description' => ['nullable', 'string'],
            'unit_id' => ['required', 'exists:units,id'],
            'account_title_id' => ['nullable', 'exists:account_titles,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'on_hand_qty' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
