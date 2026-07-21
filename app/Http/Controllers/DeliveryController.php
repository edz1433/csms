<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Delivery::query()->with(['supplier', 'receiver'])->withCount('items');

            return DataTables::eloquent($query)
                ->editColumn('received_at', fn (Delivery $d) => $d->received_at?->format('M d, Y'))
                ->addColumn('supplier', fn (Delivery $d) => e($d->supplier?->name ?? '—'))
                ->addColumn('receiver', fn (Delivery $d) => e($d->receiver?->name ?? '—'))
                ->addColumn('lines', fn (Delivery $d) => $d->items_count.' item'.($d->items_count == 1 ? '' : 's'))
                ->addColumn('action', fn (Delivery $d) => view('receiving.partials.actions', ['delivery' => $d])->render())
                ->filterColumn('supplier', fn ($q, $kw) => $q->whereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$kw}%")))
                ->rawColumns(['action'])
                ->toJson();
        }

        return view('receiving.index');
    }

    public function create()
    {
        return view('receiving.create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
            'items' => Item::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'stock_number', 'unit_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'po_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:items,id'],
            'lines.*.unit_id' => ['required', 'exists:units,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], [
            'po_number.regex' => 'PO Number may only contain letters, numbers, hyphens and slashes.',
            'lines.required' => 'Add at least one item line.',
            'lines.*.quantity.gt' => 'Quantity must be greater than zero.',
        ]);

        // Guard: referenced items must be active.
        $itemIds = collect($data['lines'])->pluck('item_id')->unique();
        $inactive = Item::whereIn('id', $itemIds)->where('is_active', false)->exists();
        if ($inactive) {
            throw ValidationException::withMessages(['lines' => 'One or more selected items are inactive.']);
        }

        $delivery = DB::transaction(function () use ($data, $request) {
            $delivery = Delivery::create([
                'po_number' => $data['po_number'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'received_by' => $request->user()->id,
                'received_at' => $data['received_at'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $delivery->items()->create([
                    'item_id' => $line['item_id'],
                    'unit_id' => $line['unit_id'],
                    'quantity' => $line['quantity'],
                ]);

                // Lock the item row, then increment on-hand under the lock
                // to avoid lost updates under concurrency.
                $item = Item::whereKey($line['item_id'])->lockForUpdate()->first();
                $item->increment('on_hand_qty', $line['quantity']);
            }

            return $delivery;
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'redirect' => route('deliveries.show', $delivery)]);
        }

        return redirect()->route('deliveries.show', $delivery)->with('success', 'Delivery recorded. Stock updated.');
    }

    public function show(Delivery $delivery)
    {
        $delivery->load(['supplier', 'receiver', 'items.item', 'items.unit']);

        return view('receiving.show', compact('delivery'));
    }
}
