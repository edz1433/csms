<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\FundCluster;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Delivery::query()->with(['supplier', 'receiver', 'iar', 'items'])->withCount('items');

            return DataTables::eloquent($query)
                ->editColumn('received_at', fn (Delivery $d) => $d->received_at?->format('M d, Y'))
                ->addColumn('supplier', fn (Delivery $d) => e($d->supplier?->name ?? '—'))
                ->addColumn('receiver', fn (Delivery $d) => e($d->receiver?->name ?? '—'))
                ->addColumn('lines', fn (Delivery $d) => $d->items_count.' item'.($d->items_count == 1 ? '' : 's'))
                ->addColumn('status', fn (Delivery $d) => $d->isPartial()
                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700" title="Balance of '.number_format($d->outstandingQty(), 2).' still to be delivered">Partial</span>'
                    : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">Complete</span>')
                ->addColumn('iar', fn (Delivery $d) => $d->iar
                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">'.e($d->iar->iar_number).'</span>'
                    : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">No IAR</span>')
                ->addColumn('payment', fn (Delivery $d) => $d->is_paid
                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">Paid</span>'
                    : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Unpaid</span>')
                ->addColumn('action', fn (Delivery $d) => view('receiving.partials.actions', ['delivery' => $d])->render())
                ->filterColumn('supplier', fn ($q, $kw) => $q->whereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$kw}%")))
                ->rawColumns(['status', 'iar', 'payment', 'action'])
                ->toJson();
        }

        return view('receiving.index');
    }

    public function create()
    {
        return view('receiving.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateDelivery($request);

        $this->guardActiveItems($data['lines']);

        $delivery = DB::transaction(function () use ($data, $request) {
            $delivery = Delivery::create([
                'po_number' => $data['po_number'],
                'fund_cluster_id' => $data['fund_cluster_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'received_by' => $request->user()->id,
                'received_at' => $data['received_at'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->syncLines($delivery, $data['lines']);

            return $delivery;
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'redirect' => route('deliveries.show', $delivery)]);
        }

        return redirect()->route('deliveries.show', $delivery)->with('success', 'Delivery recorded. Stock updated.');
    }

    /**
     * Editing exists for partial deliveries: the balance of a PO often arrives
     * days or weeks later and is recorded by topping up the same delivery.
     */
    public function edit(Delivery $delivery)
    {
        if (! $delivery->isEditable()) {
            return redirect()->route('deliveries.show', $delivery)
                ->with('error', 'This delivery is already marked paid and can no longer be edited.');
        }

        $delivery->load(['items.item', 'items.unit', 'iar']);

        return view('receiving.edit', $this->formData() + ['delivery' => $delivery]);
    }

    /**
     * Re-states the delivery to exactly what was submitted, then moves on-hand
     * stock by the difference per item (top-ups add, corrections subtract).
     */
    public function update(Request $request, Delivery $delivery)
    {
        if (! $delivery->isEditable()) {
            $message = 'This delivery is already marked paid and can no longer be edited.';

            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('deliveries.show', $delivery)->with('error', $message);
        }

        $data = $this->validateDelivery($request, $delivery);

        $this->guardActiveItems($data['lines'], $delivery);

        DB::transaction(function () use ($data, $delivery) {
            $delivery->update([
                'po_number' => $data['po_number'],
                'fund_cluster_id' => $data['fund_cluster_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'received_at' => $data['received_at'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->syncLines($delivery, $data['lines']);
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'redirect' => route('deliveries.show', $delivery)]);
        }

        return redirect()->route('deliveries.show', $delivery)->with('success', 'Delivery updated. Stock adjusted.');
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

    /* ===================== helpers ===================== */

    /** Reference data shared by the create and edit forms. */
    private function formData(): array
    {
        return [
            'fundClusters' => FundCluster::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
            'items' => Item::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'stock_number', 'unit_id', 'unit_cost']),
        ];
    }

    private function validateDelivery(Request $request, ?Delivery $delivery = null): array
    {
        return $request->validate([
            'po_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'fund_cluster_id' => ['nullable', 'exists:fund_clusters,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            // Present = an existing line being amended (top-up or correction);
            // absent = a line added on this save.
            'lines.*.id' => [
                'nullable', 'integer',
                Rule::exists('delivery_items', 'id')->where('delivery_id', $delivery?->id ?? 0),
            ],
            'lines.*.item_id' => ['required', 'exists:items,id'],
            'lines.*.unit_id' => ['required', 'exists:units,id'],
            'lines.*.ordered_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.received_at' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'po_number.regex' => 'PO Number may only contain letters, numbers, hyphens and slashes.',
            'lines.required' => 'Add at least one item line.',
            'lines.*.id.exists' => 'One of the edited lines does not belong to this delivery.',
            'lines.*.quantity.gt' => 'Quantity must be greater than zero.',
            'lines.*.ordered_qty.min' => 'Ordered quantity cannot be negative.',
        ]);
    }

    /** Referenced items must be active — unless already on the delivery. */
    private function guardActiveItems(array $lines, ?Delivery $delivery = null): void
    {
        $existing = $delivery ? $delivery->items()->pluck('item_id') : collect();

        $itemIds = collect($lines)->pluck('item_id')->unique()->diff($existing);

        if ($itemIds->isNotEmpty() && Item::whereIn('id', $itemIds)->where('is_active', false)->exists()) {
            throw ValidationException::withMessages(['lines' => 'One or more selected items are inactive.']);
        }
    }

    /**
     * Write the submitted lines onto the delivery and move on-hand stock by the
     * net change per item. Lines dropped from the payload are removed and their
     * quantity handed back. Must run inside a transaction.
     */
    private function syncLines(Delivery $delivery, array $lines): void
    {
        $existing = $delivery->items()->get()->keyBy('id');

        // Quantity this delivery currently credits to stock, per item.
        $before = [];
        foreach ($existing as $line) {
            $before[$line->item_id] = ($before[$line->item_id] ?? 0) + (float) $line->quantity;
        }

        // Quantity it should credit after this save, per item.
        $after = [];
        foreach ($lines as $line) {
            $after[$line['item_id']] = ($after[$line['item_id']] ?? 0) + (float) $line['quantity'];
        }

        // Lock every item touched on either side before reading on-hand, so two
        // concurrent saves cannot both decide there is enough stock.
        $itemIds = array_unique(array_merge(array_keys($before), array_keys($after)));
        $locked = Item::whereIn('id', $itemIds)->lockForUpdate()->get()->keyBy('id');

        // A downward correction must never drive stock negative — the goods may
        // already have been released.
        $short = [];
        foreach ($itemIds as $itemId) {
            $delta = ($after[$itemId] ?? 0) - ($before[$itemId] ?? 0);
            $item = $locked[$itemId] ?? null;

            if ($delta < 0 && $item && (float) $item->on_hand_qty + $delta < 0) {
                $short[] = $item->name.' (on hand '.number_format($item->on_hand_qty, 2)
                    .', reduction '.number_format(abs($delta), 2).')';
            }
        }

        if ($short) {
            throw ValidationException::withMessages([
                'lines' => 'Cannot reduce below stock already released for: '.implode('; ', $short),
            ]);
        }

        // Drop lines the user removed from the form.
        $keepIds = collect($lines)->pluck('id')->filter()->all();
        $existing->whereNotIn('id', $keepIds)->each(fn (DeliveryItem $line) => $line->delete());

        foreach ($lines as $line) {
            $payload = [
                'item_id' => $line['item_id'],
                'unit_id' => $line['unit_id'],
                'ordered_qty' => $line['ordered_qty'] ?? null,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'] ?? 0,
                'received_at' => $line['received_at'] ?? $delivery->received_at?->toDateString(),
            ];

            $current = ! empty($line['id']) ? $existing->get($line['id']) : null;

            if ($current) {
                $current->update($payload);
            } else {
                $delivery->items()->create($payload);
            }

            // Keep the item's standard cost current from the latest priced
            // receipt, so releases and the RSMI/ledger reports stay populated.
            if ((float) ($line['unit_cost'] ?? 0) > 0) {
                $locked[$line['item_id']]?->update(['unit_cost' => $line['unit_cost']]);
            }
        }

        // Apply the net stock movement once per item, under the lock taken above.
        foreach ($itemIds as $itemId) {
            $delta = ($after[$itemId] ?? 0) - ($before[$itemId] ?? 0);

            if ($delta > 0) {
                $locked[$itemId]?->increment('on_hand_qty', $delta);
            } elseif ($delta < 0) {
                $locked[$itemId]?->decrement('on_hand_qty', abs($delta));
            }
        }
    }
}
