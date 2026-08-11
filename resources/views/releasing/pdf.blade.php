<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 26px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9px; color: #000; }
        .header { width: 100%; text-align: center; margin-bottom: 6px; }
        .header img { width: 100%; max-height: 90px; }
        .title { text-align: center; font-weight: bold; font-size: 12px; letter-spacing: .5px; margin: 2px 0 8px; }

        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }

        .info td { font-size: 9px; }
        .info .lbl { font-weight: bold; }
        .no-border { border: 0 !important; }

        table.items { margin-top: -1px; }
        table.items th { text-align: center; font-weight: bold; font-size: 8.5px; }
        table.items td { font-size: 8.5px; height: 15px; }
        .c { text-align: center; }
        .r { text-align: right; }

        .sign td { border: 1px solid #000; font-size: 8.5px; padding: 0; }
        .sign .role { font-weight: bold; padding: 3px 5px; border-bottom: 1px solid #000; }
        .sign .rowlbl { font-weight: bold; width: 70px; }
        .sign .cell { height: 16px; padding: 2px 5px; }
        .sign .name { text-align: center; font-weight: bold; text-transform: uppercase; }
        .sign .desig { text-align: center; }
    </style>
</head>
<body>
    {{-- Letterhead (image already contains agency block + "REQUISITION AND ISSUE SLIP") --}}
    <div class="header">
        @if ($header)
            <img src="{{ $header }}" alt="CPSU">
        @else
            <div style="font-weight:bold">CENTRAL PHILIPPINES STATE UNIVERSITY</div>
            <div class="title">REQUISITION AND ISSUE SLIP</div>
        @endif
    </div>

    {{-- Info block --}}
    <table class="info">
        <tr>
            <td width="50%"><span class="lbl">Division/Branch/Unit:</span> {{ $release->location?->name }}</td>
            <td width="50%"><span class="lbl">Fund Cluster:</span> {{ $release->fundCluster?->code }}</td>
        </tr>
        <tr>
            <td><span class="lbl">Office:</span> {{ $release->location?->code }}</td>
            <td><span class="lbl">RIS No.:</span> {{ $release->ris_number }}</td>
        </tr>
        <tr>
            <td><span class="lbl">Responsibility Center Code:</span> &nbsp;</td>
            <td><span class="lbl">Date:</span> {{ $release->released_at?->format('F d, Y') }}</td>
        </tr>
    </table>

    {{-- Items --}}
    @php
        $minRows = (int) config('ris.min_rows', 12);
        $count = $release->items->count();
        $blanks = max(0, $minRows - $count);

        // Issue price is the cost snapshotted when the item was released; older
        // lines saved before that was captured fall back to the item's cost.
        $unitPrice = fn ($line) => (float) $line->unit_cost > 0
            ? (float) $line->unit_cost
            : (float) ($line->item?->unit_cost ?? 0);
        $linePrice = fn ($line) => (float) $line->quantity * $unitPrice($line);
        $peso = fn ($n) => (float) $n != 0.0 ? number_format($n, 2) : '';

        $grandTotal = $release->items->sum(fn ($line) => $linePrice($line));
    @endphp
    <table class="items">
        <thead>
            <tr>
                <th colspan="4">REQUISITION</th>
                <th colspan="2">ISSUANCE</th>
            </tr>
            <tr>
                <th width="12%">STOCK NO.</th>
                <th width="9%">UNIT</th>
                <th width="43%">DESCRIPTION</th>
                <th width="9%">QTY</th>
                <th width="13%">UNIT PRICE</th>
                <th width="14%">TOTAL PRICE</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($release->items as $line)
                <tr>
                    <td class="c">{{ $line->item?->stock_number }}</td>
                    <td class="c">{{ $line->unit?->abbreviation ?? $line->item?->unit?->abbreviation }}</td>
                    <td>{{ $line->item?->name }}</td>
                    <td class="c">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    <td class="r">{{ $peso($unitPrice($line)) }}</td>
                    <td class="r">{{ $peso($linePrice($line)) }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < $blanks; $i++)
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
            @endfor
            <tr>
                <td colspan="5" class="r" style="font-weight:bold">TOTAL:</td>
                <td class="r" style="font-weight:bold">{{ $peso($grandTotal) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Purpose --}}
    <table style="margin-top:-1px">
        <tr>
            <td style="height:26px"><span class="lbl" style="font-weight:bold">Purpose:</span> {{ $release->remarks }}</td>
        </tr>
    </table>

    {{-- Signatories --}}
    @php
        $approved = config('ris.approved_by');
        $issued = config('ris.issued_by');
        $officeDesig = $release->location?->name;
    @endphp
    <table class="sign" style="margin-top:-1px">
        <tr>
            <td class="rowlbl" style="border-bottom:1px solid #000">&nbsp;</td>
            <td class="role">Requested By:</td>
            <td class="role">Approved By:</td>
            <td class="role">Issued By:</td>
            <td class="role">Received By:</td>
        </tr>
        <tr>
            <td class="rowlbl">Signature</td>
            <td class="cell">&nbsp;</td>
            <td class="cell">&nbsp;</td>
            <td class="cell">&nbsp;</td>
            <td class="cell">&nbsp;</td>
        </tr>
        <tr>
            <td class="rowlbl">Printed Name</td>
            <td class="cell name">&nbsp;</td>
            <td class="cell name">{{ $approved['name'] }}</td>
            <td class="cell name">{{ $issued['name'] }}</td>
            <td class="cell name">&nbsp;</td>
        </tr>
        <tr>
            <td class="rowlbl">Designation</td>
            <td class="cell desig">{{ $officeDesig }}</td>
            <td class="cell desig">{{ $approved['designation'] }}</td>
            <td class="cell desig">{{ $issued['designation'] }}</td>
            <td class="cell desig">{{ $officeDesig }}</td>
        </tr>
        <tr>
            <td class="rowlbl">Date</td>
            <td class="cell">&nbsp;</td>
            <td class="cell">&nbsp;</td>
            <td class="cell">&nbsp;</td>
            <td class="cell">&nbsp;</td>
        </tr>
    </table>
</body>
</html>
