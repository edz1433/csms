<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16px 22px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 8px; color: #000; }

        .header { width: 100%; text-align: center; }
        .header img { width: 86%; max-height: 64px; }
        .org { font-weight: bold; font-size: 11px; }
        .org-sub { font-size: 8.5px; }
        .title { text-align: center; font-weight: bold; font-size: 12px; letter-spacing: .5px; margin: 4px 0 0; }
        .subtitle { text-align: center; font-size: 8.5px; margin-bottom: 6px; }

        table { border-collapse: collapse; width: 100%; }
        .info td { font-size: 8.5px; padding: 2px 3px; border: 0; }
        .info .lbl { font-weight: bold; }

        table.stock td, table.stock th { border: 1px solid #000; padding: 2px 4px; font-size: 7.5px; }
        table.stock th { text-align: center; font-weight: bold; background: #EFEFEF; }
        table.stock thead { display: table-header-group; }   /* repeat header on every page */
        table.stock tr { page-break-inside: avoid; }
        .c { text-align: center; }
        .r { text-align: right; }
        .total td { font-weight: bold; background: #F5F5F5; }

        .foot { margin-top: 10px; width: 100%; }
        .foot td { font-size: 8px; padding: 2px 4px; border: 0; vertical-align: bottom; }
        .foot .name { text-align: center; font-weight: bold; border-top: 1px solid #000; padding-top: 1px; text-transform: uppercase; }
        .foot .role { text-align: center; font-size: 7.5px; }
    </style>
</head>
<body>
    @php
        $qty = fn ($n) => (float) $n != 0.0 ? number_format((float) $n, 2) : '-';
        $peso = fn ($n) => (float) $n != 0.0 ? number_format((float) $n, 2) : '-';
        $certified = config('ris.issued_by');
    @endphp

    {{-- CPSU letterhead --}}
    <div class="header">
        @if ($header)
            <img src="{{ $header }}" alt="CPSU">
        @else
            <div class="org">CENTRAL PHILIPPINES STATE UNIVERSITY</div>
            <div class="org-sub">Kabankalan City, Negros Occidental</div>
        @endif
    </div>

    <div class="title">STOCK STATUS REPORT</div>
    <div class="subtitle">Inventory balance and valuation as of {{ $asOf->format('F d, Y') }}</div>

    {{-- Info block --}}
    <table class="info">
        <tr>
            <td width="62%"><span class="lbl">Entity Name :</span> CENTRAL PHILIPPINES STATE UNIVERSITY</td>
            <td width="38%"><span class="lbl">Fund Cluster :</span> {{ $fund ? $fund->code.' — '.$fund->name : 'All' }}</td>
        </tr>
        <tr>
            <td><span class="lbl">Account Title :</span> {{ $accountTitle ? $accountTitle->name.' — '.$accountTitle->rca_code : 'All' }}</td>
            <td><span class="lbl">Items Listed :</span> {{ number_format($rows->count()) }}</td>
        </tr>
    </table>

    {{-- Stock listing --}}
    <table class="stock" style="margin-top:4px">
        <thead>
            <tr>
                <th width="4%">NO.</th>
                <th width="44%">STOCK ITEM</th>
                <th width="8%">UNIT</th>
                <th width="13%">STOCK BALANCE</th>
                <th width="14%">UNIT PRICE</th>
                <th width="17%">TOTAL COST</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                <tr>
                    <td class="r">{{ $i + 1 }}</td>
                    <td>{{ $row['item']->name }}</td>
                    <td class="c">{{ $row['item']->unit?->abbreviation }}</td>
                    <td class="r">{{ $qty($row['qty']) }}</td>
                    <td class="r">{{ $peso($row['unit_cost']) }}</td>
                    <td class="r">{{ $peso($row['total_cost']) }}</td>
                </tr>
            @endforeach

            @if ($rows->isEmpty())
                <tr><td colspan="6" class="c" style="height:20px">No items match the selected filters.</td></tr>
            @endif

            <tr class="total">
                <td colspan="5" class="r">TOTAL</td>
                <td class="r">{{ number_format((float) $total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Certification --}}
    <table class="foot">
        <tr>
            <td width="60%">&nbsp;</td>
            <td width="40%" style="height:26px">Certified Correct by:</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td class="name">{{ $certified['name'] ?? '' }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td class="role">{{ $certified['designation'] ?? '' }}</td>
        </tr>
    </table>
</body>
</html>
