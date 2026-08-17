<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\FundCluster;
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
            $query = Delivery::query()->with(['supplier', 'receiver', 'iar'])->withCount('items');

            return DataTables::eloquent($query)
                ->editColumn('received_at', fn (Delivery $d) => $d->received_at?->format('M d, Y'))
                ->addColumn('supplier', fn (Delivery $d) => e($d->supplier?->name ?? '—'))
                ->addColumn('receiver', fn (Delivery $d) => e($d->receiver?->name ?? '—'))
                ->addColumn('lines', fn (Delivery $d) => $d->items_count.' item'.($d->items_count == 1 ? '' : 's'))
                ->addColumn('iar', fn (Delivery $d) => $d->iar
                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">'.e($d->iar->iar_number).'</span>'
                    : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">No IAR</span>')
                ->addColumn('payment', fn (Delivery $d) => $d->is_paid
                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">Paid</span>'
                    : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Unpaid</span>')
                ->addColumn('action', fn (Delivery $d) => view('receiving.partials.actions', ['delivery' => $d])->render())
                ->filterColumn('supplier', fn ($q, $kw) => $q->whereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$kw}%")))
                ->rawColumns(['iar', 'payment', 'action'])
                ->toJson();
        }

        return view('receiving.index');
    }

    public function create()
    {
        return view('receiving.create', [
            'fundClusters' => FundCluster::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
            'items' => Item::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'stock_number', 'unit_id', 'unit_cost']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'po_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'fund_cluster_id' => ['nullable', 'exists:fund_clusters,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:items,id'],
            'lines.*.unit_id' => ['required', 'exists:units,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
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
                'fund_cluster_id' => $data['fund_cluster_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'received_by' => $request->user()->id,
                'received_at' => $data['received_at'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $unitCost = $line['unit_cost'] ?? 0;

                $delivery->items()->create([
                    'item_id' => $line['item_id'],
                    'unit_id' => $line['unit_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $unitCost,
                ]);

                // Lock the item row, then increment on-hand under the lock
                // to avoid lost updates under concurrency.
                $item = Item::whereKey($line['item_id'])->lockForUpdate()->first();
                $item->increment('on_hand_qty', $line['quantity']);

                // Keep the item's standard cost current from the latest priced
                // receipt, so releases and the RSMI/ledger reports stay populated.
                if ((float) $unitCost > 0) {
                    $item->update(['unit_cost' => $unitCost]);
                }
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
        $delivery->load(['fundCluster', 'supplier', 'receiver', 'payer', 'iar', 'items.item', 'items.unit']);

        return view('receiving.show', compact('delivery'));
    }

    /**
     * Toggle supplier-payment status on a delivery. This is the one write
     * exception for accounting_staff (registered outside deny.accounting.write).
     * Allowed for administrator + accounting_staff only.
     */
    public function togglePayment(Request $request, Delivery $delivery)
    {
        if (! $delivery->iar) {
            return response()->json([
                'ok' => false,
                'message' => 'Create an IAR for this delivery before accounting can mark it paid.',
            ], 422);
        }

        $paid = ! $delivery->is_paid;

        $data = $request->validate([
            'or_number' => ['nullable', 'string', 'max:100'],
        ]);

        $payload = [
            'is_paid' => $paid,
            'or_number' => $paid ? ($data['or_number'] ?? null) : null,
            'paid_at' => $paid ? now() : null,
            'paid_by' => $paid ? $request->user()->id : null,
        ];

        DB::transaction(function () use ($delivery, $payload) {
            $delivery->iar->update($payload);
            $delivery->update($payload);
        });

        return response()->json([
            'ok' => true,
            'is_paid' => $paid,
            'or_number' => $delivery->or_number,
            'paid_at' => $delivery->paid_at?->format('M d, Y g:i A'),
            'paid_by' => $paid ? $request->user()->name : null,
        ]);
    }
}
