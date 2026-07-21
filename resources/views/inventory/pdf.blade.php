<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 26px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9px; color: #000; }
        .appendix { text-align: right; font-style: italic; font-size: 9px; margin-bottom: 2px; }

        table { border-collapse: collapse; width: 100%; }
        .box td, .box th { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }

        .head-cell { text-align: center; }
        .head-cell img { width: 62%; max-height: 60px; }
        .title { font-weight: bold; font-size: 13px; letter-spacing: 1px; margin-top: 3px; }

        .info td { font-size: 9px; height: 15px; }
        .info .lbl { font-weight: bold; }

        table.card { margin-top: -1px; }
        table.card th { text-align: center; font-weight: bold; font-size: 8.5px; }
        table.card th.grp { font-style: italic; }
        table.card td { font-size: 8.5px; height: 16px; }
        .c { text-align: center; }
        .r { text-align: right; }
        .bf { font-style: italic; }
    </style>
</head>
<body>
    <div class="appendix">Appendix 58</div>

    {{-- Header box: letterhead + STOCK CARD title --}}
    <table class="box">
        <tr>
            <td class="head-cell">
                @if ($header)
                    <img src="{{ $header }}" alt="CPSU">
                @else
                    <div style="font-weight:bold">CENTRAL PHILIPPINES STATE UNIVERSITY</div>
                    <div>Kabankalan City, Negros Occidental</div>
                @endif
                <div class="title">STOCK CARD</div>
            </td>
        </tr>
    </table>

    {{-- Info block --}}
    <table class="box info" style="margin-top:-1px">
        <tr>
            <td width="60%"><span class="lbl">Entity Name:</span> CENTRAL PHILIPPINES STATE UNIVERSITY</td>
            <td width="40%"><span class="lbl">Fund Cluster:</span> &nbsp;</td>
        </tr>
        <tr>
            <td><span class="lbl">Item :</span> {{ $item->name }}</td>
            <td><span class="lbl">Stock No. :</span> {{ $item->stock_number }}</td>
        </tr>
        <tr>
            <td><span class="lbl">Description :</span> {{ $item->description }}</td>
            <td><span class="lbl">Re-order Point :</span> &nbsp;</td>
        </tr>
        <tr>
            <td colspan="2"><span class="lbl">Unit of Measurement :</span> {{ $item->unit?->abbreviation }}</td>
        </tr>
    </table>

    {{-- Stock card table --}}
    @php
        $minRows = 16;
        $blanks = max(0, $minRows - $timeline->count());
        $fmt = fn ($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
    @endphp
    <table class="box card">
        <thead>
            <tr>
                <th rowspan="2" width="9%">Date</th>
                <th rowspan="2" width="18%">Reference</th>
                <th class="grp" width="10%">Receipt</th>
                <th class="grp" colspan="2">Issue</th>
                <th class="grp" width="10%">Balance</th>
                <th rowspan="2" width="13%">No. of Days to Consume</th>
            </tr>
            <tr>
                <th>Qty.</th>
                <th width="9%">Qty.</th>
                <th width="21%">Office</th>
                <th>Qty.</th>
            </tr>
        </thead>
        <tbody>
            {{-- Balance Forwarded (beginning balance) --}}
            <tr>
                <td></td>
                <td class="bf">Balance Forwarded (beg.bal)</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="r">{{ $fmt($beginning) }}</td>
                <td></td>
            </tr>

            @foreach ($timeline as $row)
                <tr>
                    <td class="c">{{ $row['date']?->format('m/d/Y') }}</td>
                    <td>{{ $row['ref'] }}</td>
                    <td class="r">{{ $row['type'] === 'in' ? $fmt($row['qty']) : '' }}</td>
                    <td class="r">{{ $row['type'] === 'out' ? $fmt($row['qty']) : '' }}</td>
                    <td>{{ $row['type'] === 'out' ? $row['party'] : '' }}</td>
                    <td class="r">{{ $fmt($row['balance']) }}</td>
                    <td></td>
                </tr>
            @endforeach

            @for ($i = 0; $i < $blanks; $i++)
                <tr>
                    <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                </tr>
            @endfor
        </tbody>
    </table>
</body>
</html>
