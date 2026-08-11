<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReportScope;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerController extends Controller
{
    use ReportScope;

    public function index()
    {
        return view('reports.ledger', ['items' => $this->scopedItemRecords()] + $this->scopeLists());
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

        // Movements with captured unit cost (receipts priced at receiving,
        // issues at the snapshot taken when released).
        $receipts = $item->deliveryItems()->with(['delivery.supplier', 'delivery.items'])->get()->map(fn ($di) => [
            'date' => $di->delivery->received_at,
            'ref' => $this->receiptRef($di->delivery),
            'type' => 'in',
            'qty' => (float) $di->quantity,
            'cost' => (float) $di->unit_cost,
        ]);
        $issues = $item->releaseItems()->with('release.location')->get()->map(fn ($ri) => [
            'date' => $ri->release->released_at,
            'ref' => $this->issueRef($ri->release),
            'type' => 'out',
            'qty' => (float) $ri->quantity,
            'cost' => (float) $ri->unit_cost,
        ]);
        $all = $receipts->concat($issues)->sortBy('date')->values();

        // Opening stock (seeded stock isn't a recorded movement) valued at the
        // item's current standard cost as a proxy for the beginning cost.
        $openingQty = (float) $item->on_hand_qty - ($receipts->sum('qty') - $issues->sum('qty'));
        $openingCost = (float) $item->unit_cost;

        // Walk every movement in date order. The Balance Total Cost follows the
        // COA Appendix 57 spreadsheet formula (cell K):
        //   Balance Total = MAX(0, previous Balance Total + Receipt Total - Issue Total)
        // Receipts are valued at their receiving cost, issues at their snapshot cost.
        $balQty = $openingQty;
        $balValue = $openingQty * $openingCost;
        $beginningQty = $balQty;
        $beginningValue = $balValue;

        $rows = collect();
        foreach ($all as $m) {
            $recvTotal = $m['type'] === 'in' ? $m['qty'] * $m['cost'] : 0.0;
            $issueTotal = $m['type'] === 'out' ? $m['qty'] * $m['cost'] : 0.0;

            // K16 = MAX(0, N(K15) + N(E16) - N(H16))
            $balValue = max(0.0, $balValue + $recvTotal - $issueTotal);
            $balQty = max(0.0, $balQty + ($m['type'] === 'in' ? $m['qty'] : -$m['qty']));

            $m['unit_cost'] = $m['cost'];
            $m['total_cost'] = $m['type'] === 'in' ? $recvTotal : $issueTotal;
            $m['bal_qty'] = $balQty;
            $m['bal_cost'] = $balQty > 0.0 ? $balValue / $balQty : 0.0;
            $m['bal_total'] = $balValue;

            if ($m['date'] && $m['date']->lt($from)) {
                $beginningQty = $balQty;
                $beginningValue = $balValue;
            } elseif ($m['date'] && $m['date']->gte($from) && $m['date']->lte($to)) {
                $rows->push($m);
            }
        }

        $endingQty = $rows->count() ? $rows->last()['bal_qty'] : $beginningQty;
        $endingValue = $rows->count() ? $rows->last()['bal_total'] : $beginningValue;

        $headerPath = public_path('images/cpsu-letterhead.png');
        $header = is_file($headerPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath))
            : null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('ledger.pdf', [
            'item' => $item,
            'from' => $from,
            'to' => $to,
            'beginning' => $beginningQty,
            'beginningCost' => $beginningQty != 0.0 ? $beginningValue / $beginningQty : $openingCost,
            'beginningValue' => $beginningValue,
            'rows' => $rows,
            'totalReceipt' => $rows->where('type', 'in')->sum('qty'),
            'totalReceiptCost' => $rows->where('type', 'in')->sum('total_cost'),
            'totalIssue' => $rows->where('type', 'out')->sum('qty'),
            'totalIssueCost' => $rows->where('type', 'out')->sum('total_cost'),
            'ending' => $endingQty,
            'endingValue' => $endingValue,
            'header' => $header,
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('SupplyLedgerCard-'.($item->stock_number ?? $item->id).'.pdf');
    }

    /**
     * Reference line for a receipt row, e.g.
     * "DR #13744 &13914, PO No. 2025-08-495 (Ang Design 595k)".
     * The leading DR/notes come from the delivery remarks; the trailing
     * parenthetical is the supplier and the delivery's total value.
     */
    private function receiptRef(Delivery $delivery): string
    {
        $ref = trim((string) $delivery->remarks);
        $ref = $ref !== '' ? $ref.', ' : '';
        $ref .= 'PO No. '.$this->stripPrefix($delivery->po_number, 'PO');

        $total = $delivery->items->sum(fn ($di) => (float) $di->quantity * (float) $di->unit_cost);
        $note = array_filter([$delivery->supplier?->name, $this->shortAmount($total)]);

        return $note ? $ref.' ('.implode(' ', $note).')' : $ref;
    }

    /** Reference line for an issue row, e.g. "RIS No. 2026-0007 (Registrar's Office)". */
    private function issueRef(Release $release): string
    {
        $ref = 'RIS No. '.$this->stripPrefix($release->ris_number, 'RIS');

        return $release->location?->name ? $ref.' ('.$release->location->name.')' : $ref;
    }

    /**
     * Drop a document prefix already baked into the number so the label is not
     * doubled ("PO No. PO-051535" → "PO No. 051535"). Left alone if stripping
     * would leave nothing behind.
     */
    private function stripPrefix(string $number, string $prefix): string
    {
        $stripped = preg_replace('/^\s*'.preg_quote($prefix, '/').'\.?\s*(No\.?)?[\s\-#:]*/i', '', $number);

        return trim((string) $stripped) !== '' ? trim($stripped) : trim($number);
    }

    /** 595000 → "595k", 1250000 → "1.25M"; blank when there is nothing to show. */
    private function shortAmount(float $amount): string
    {
        return match (true) {
            $amount <= 0.0 => '',
            $amount >= 1_000_000 => rtrim(rtrim(number_format($amount / 1_000_000, 2), '0'), '.').'M',
            $amount >= 1_000 => rtrim(rtrim(number_format($amount / 1_000, 2), '0'), '.').'k',
            default => number_format($amount, 2),
        };
    }
}
