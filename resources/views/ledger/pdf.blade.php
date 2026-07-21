<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16px 22px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 8px; color: #000; }
        .appendix { text-align: right; font-style: italic; font-size: 8px; margin-bottom: 2px; }
        .header { width: 100%; text-align: center; }
        .header img { width: 86%; max-height: 64px; }
        .title { text-align: center; font-weight: bold; font-size: 12px; letter-spacing: .5px; margin: 4px 0 0; }
        .subtitle { text-align: center; font-size: 8.5px; margin-bottom: 6px; }

        table { border-collapse: collapse; width: 100%; }
        .info td { font-size: 8.5px; padding: 2px 3px; border: 0; }
        .info .lbl { font-weight: bold; }

        table.ledger td, table.ledger th { border: 1px solid #000; padding: 2px 3px; font-size: 7.5px; }
        table.ledger th { text-align: center; font-weight: bold; }
        .c { text-align: center; }
        .r { text-align: right; }
        td.h { height: 13px; }
    </style>
</head>
<body>
    <div class="appendix">Appendix 57</div>

    {{-- CPSU letterhead --}}
    <div class="header">
        @if ($header)
            <img src="{{ $header }}" alt="CPSU">
        @else
            <div style="font-weight:bold">CENTRAL PHILIPPINES STATE UNIVERSITY</div>
        @endif
    </div>
    <div class="title">SUPPLIES LEDGER CARD</div>
    <div class="subtitle">For the month of {{ $from->format('F') }} to {{ $to->format('F') }}, {{ $to->format('Y') }}</div>

    {{-- Info block --}}
    <table class="info">
        <tr>
            <td width="62%"><span class="lbl">Entity Name :</span> CENTRAL PHILIPPINES STATE UNIVERSITY</td>
            <td width="38%"><span class="lbl">Fund Cluster :</span> &nbsp;</td>
        </tr>
        <tr>
            <td><span class="lbl">Item :</span> {{ $item->name }}</td>
            <td><span class="lbl">Item Code :</span> {{ $item->stock_number }}</td>
        </tr>
        <tr>
            <td><span class="lbl">Description :</span> {{ $item->description }}</td>
            <td><span class="lbl">Re-order Point :</span> &nbsp;</td>
        </tr>
        <tr>
            <td><span class="lbl">Unit of Measurement :</span> {{ $item->unit?->abbreviation }}</td>
            <td>&nbsp;</td>
        </tr>
    </table>

    {{-- Ledger table --}}
    @php
        $minRows = 22;
        $used = $rows->count();
        $blanks = max(0, $minRows - $used);
        $fmt = fn ($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
    @endphp
    <table class="ledger" style="margin-top:4px">
        <thead>
            <tr>
                <th rowspan="2" width="9%">Date</th>
                <th rowspan="2" width="22%">Reference</th>
                <th colspan="3">Receipt</th>
                <th colspan="3">Issue</th>
                <th colspan="3">Balance</th>
                <th rowspan="2" width="7%">No. of Days to Consume</th>
            </tr>
            <tr>
                <th>Qty.</th><th>Unit Cost</th><th>Total Cost</th>
                <th>Qty.</th><th>Unit Cost</th><th>Total Cost</th>
                <th>Qty.</th><th>Unit Cost</th><th>Total Cost</th>
            </tr>
        </thead>
        <tbody>
            {{-- Beginning balance --}}
            <tr>
                <td class="c"></td>
                <td>Beginning Balance</td>
                <td></td><td></td><td></td>
                <td></td><td></td><td></td>
                <td class="r">{{ $fmt($beginning) }}</td><td></td><td></td>
                <td></td>
            </tr>

            @foreach ($rows as $row)
                <tr>
                    <td class="c">{{ $row['date']?->format('m/d/Y') }}</td>
                    <td>{{ $row['ref'] }}</td>
                    <td class="r">{{ $row['type'] === 'in' ? $fmt($row['qty']) : '' }}</td>
                    <td></td><td></td>
                    <td class="r">{{ $row['type'] === 'out' ? $fmt($row['qty']) : '' }}</td>
                    <td></td><td></td>
                    <td class="r">{{ $fmt($row['balance']) }}</td>
                    <td></td><td></td>
                    <td></td>
                </tr>
            @endforeach

            @for ($i = 0; $i < $blanks; $i++)
                <tr>
                    <td class="h"></td><td></td>
                    <td></td><td></td><td></td>
                    <td></td><td></td><td></td>
                    <td></td><td></td><td></td>
                    <td></td>
                </tr>
            @endfor

            {{-- Totals --}}
            <tr style="font-weight:bold">
                <td colspan="2" class="c">TOTAL</td>
                <td class="r">{{ $fmt($totalReceipt) }}</td><td></td><td></td>
                <td class="r">{{ $fmt($totalIssue) }}</td><td></td><td></td>
                <td class="r">{{ $fmt($ending) }}</td><td></td><td></td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
