<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\InspectionAcceptanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class InspectionAcceptanceReportController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = InspectionAcceptanceReport::query()
                ->with(['delivery.supplier', 'delivery.receiver', 'delivery.items', 'creator', 'payer']);

            return DataTables::eloquent($query)
                ->editColumn('iar_date', fn (InspectionAcceptanceReport $iar) => $iar->iar_date?->format('M d, Y'))
                ->addColumn('po_number', fn (InspectionAcceptanceReport $iar) => e($iar->delivery?->po_number ?? '—'))
                ->addColumn('supplier', fn (InspectionAcceptanceReport $iar) => e($iar->delivery?->supplier?->name ?? '—'))
                ->addColumn('created_by', fn (InspectionAcceptanceReport $iar) => e($iar->creator?->name ?? '—'))
                ->addColumn('lines', fn (InspectionAcceptanceReport $iar) => $iar->delivery?->items?->count().' item'.($iar->delivery?->items?->count() == 1 ? '' : 's'))
                ->addColumn('payment', fn (InspectionAcceptanceReport $iar) => $iar->is_paid
                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">Paid</span>'
                    : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Unpaid</span>')
                ->addColumn('action', fn (InspectionAcceptanceReport $iar) => view('iars.partials.actions', ['iar' => $iar])->render())
                ->filterColumn('po_number', fn ($q, $kw) => $q->whereHas('delivery', fn ($d) => $d->where('po_number', 'like', "%{$kw}%")))
                ->filterColumn('supplier', fn ($q, $kw) => $q->whereHas('delivery.supplier', fn ($s) => $s->where('name', 'like', "%{$kw}%")))
                ->rawColumns(['payment', 'action'])
                ->toJson();
        }

        return view('iars.index');
    }

    public function create(Request $request)
    {
        $delivery = $request->filled('delivery_id')
            ? Delivery::with(['supplier', 'fundCluster', 'items.item', 'items.unit', 'iar'])->findOrFail($request->integer('delivery_id'))
            : null;

        if ($delivery?->iar) {
            return redirect()->route('iars.show', $delivery->iar);
        }

        return view('iars.create', [
            'delivery' => $delivery,
            'deliveries' => Delivery::with(['supplier', 'iar'])
                ->doesntHave('iar')
                ->latest('received_at')
                ->limit(100)
                ->get(['id', 'po_number', 'supplier_id', 'received_at']),
            'nextIarNumber' => $this->nextIarNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $delivery = Delivery::with('iar')->findOrFail($data['delivery_id']);

        if ($delivery->iar) {
            return response()->json([
                'ok' => false,
                'message' => 'This delivery already has an IAR.',
                'redirect' => route('iars.show', $delivery->iar),
            ], 422);
        }

        $iar = InspectionAcceptanceReport::create($data + [
            'created_by' => $request->user()->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'redirect' => route('iars.show', $iar)]);
        }

        return redirect()->route('iars.show', $iar)->with('success', 'IAR created.');
    }

    public function show(InspectionAcceptanceReport $iar)
    {
        $iar->load([
            'delivery.fundCluster',
            'delivery.supplier',
            'delivery.receiver',
            'delivery.items.item',
            'delivery.items.unit',
            'creator',
            'payer',
        ]);

        return view('iars.show', compact('iar'));
    }

    public function togglePayment(Request $request, InspectionAcceptanceReport $iar)
    {
        $paid = ! $iar->is_paid;

        $data = $request->validate([
            'or_number' => [$paid ? 'required' : 'nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($iar, $paid, $data, $request) {
            $payload = [
                'is_paid' => $paid,
                'or_number' => $paid ? $data['or_number'] : null,
                'paid_at' => $paid ? now() : null,
                'paid_by' => $paid ? $request->user()->id : null,
            ];

            $iar->update($payload);
            $iar->delivery->update($payload);
        });

        $iar->refresh()->load('payer');

        return response()->json([
            'ok' => true,
            'is_paid' => $paid,
            'or_number' => $iar->or_number,
            'paid_at' => $iar->paid_at?->format('M d, Y g:i A'),
            'paid_by' => $paid ? $iar->payer?->name : null,
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'delivery_id' => ['required', 'exists:deliveries,id', Rule::unique('inspection_acceptance_reports', 'delivery_id')],
            'iar_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/]+$/', 'unique:inspection_acceptance_reports,iar_number'],
            'iar_date' => ['required', 'date', 'before_or_equal:today'],
            'requisitioning_office' => ['nullable', 'string', 'max:255'],
            'responsibility_center_code' => ['nullable', 'string', 'max:100'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date', 'before_or_equal:today'],
            'inspection_date' => ['nullable', 'date', 'before_or_equal:today'],
            'inspection_officer' => ['nullable', 'string', 'max:255'],
            'acceptance_date' => ['nullable', 'date', 'before_or_equal:today'],
            'acceptance_status' => ['required', Rule::in([InspectionAcceptanceReport::STATUS_COMPLETE, InspectionAcceptanceReport::STATUS_PARTIAL])],
            'partial_quantity' => ['nullable', 'required_if:acceptance_status,'.InspectionAcceptanceReport::STATUS_PARTIAL, 'numeric', 'min:0'],
            'accepted_by' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'iar_number.regex' => 'IAR Number may only contain letters, numbers, hyphens and slashes.',
            'partial_quantity.required_if' => 'Specify the accepted quantity when acceptance is partial.',
        ]);
    }

    private function nextIarNumber(): string
    {
        $prefix = 'IAR-'.now()->format('Y').'-';
        $last = InspectionAcceptanceReport::where('iar_number', 'like', $prefix.'%')
            ->orderByDesc('iar_number')
            ->value('iar_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
