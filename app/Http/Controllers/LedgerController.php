<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('name')->get(['id', 'name', 'stock_number']);

        return view('ledger.index', compact('items'));
    }

    /** Supplies Ledger Card (COA Appendix 57) as a PDF, filtered by item + date range. */
    public function pdf(Request $request)
    {
        $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $item = Item::with(['unit', 'accountTitle'])->findOrFail($request->integer('item_id'));

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->startOfYear();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfYear();

        // Movements (quantity only — the system does not track unit cost).
        $receipts = $item->deliveryItems()->with('delivery')->get()->map(fn ($di) => [
            'date' => $di->delivery->received_at,
            'ref' => $di->delivery->po_number,
            'type' => 'in',
            'qty' => (float) $di->quantity,
        ]);
        $issues = $item->releaseItems()->with('release')->get()->map(fn ($ri) => [
            'date' => $ri->release->released_at,
            'ref' => $ri->release->ris_number,
            'type' => 'out',
            'qty' => (float) $ri->quantity,
        ]);
        $all = $receipts->concat($issues);

        // Opening stock (seeded stock isn't a recorded movement).
        $openingStock = (float) $item->on_hand_qty - ($receipts->sum('qty') - $issues->sum('qty'));

        // Beginning balance = opening + movements strictly before the period.
        $before = $all->filter(fn ($r) => $r['date'] && $r['date']->lt($from));
        $beginning = $openingStock
            + $before->where('type', 'in')->sum('qty')
            - $before->where('type', 'out')->sum('qty');

        // In-period movements with running balance.
        $period = $all->filter(fn ($r) => $r['date'] && $r['date']->gte($from) && $r['date']->lte($to))
            ->sortBy('date')->values();

        $balance = $beginning;
        $rows = $period->map(function ($r) use (&$balance) {
            $balance += $r['type'] === 'in' ? $r['qty'] : -$r['qty'];
            $r['balance'] = $balance;

            return $r;
        });

        $headerPath = public_path('images/cpsu-letterhead.png');
        $header = is_file($headerPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath))
            : null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('ledger.pdf', [
            'item' => $item,
            'from' => $from,
            'to' => $to,
            'beginning' => $beginning,
            'rows' => $rows,
            'totalReceipt' => $period->where('type', 'in')->sum('qty'),
            'totalIssue' => $period->where('type', 'out')->sum('qty'),
            'ending' => $balance,
            'header' => $header,
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('SupplyLedgerCard-'.($item->stock_number ?? $item->id).'.pdf');
    }
}
