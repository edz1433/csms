<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16px 20px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 8px; color: #000; }
        .appendix { text-align: left; font-style: italic; font-size: 8px; margin-bottom: 2px; }
        .header { width: 100%; text-align: center; }
        .header img { width: 60%; max-height: 60px; }
        .org { font-weight: bold; font-size: 11px; }
        .org-sub { font-size: 8.5px; }
        .title { text-align: center; font-weight: bold; font-size: 11px; letter-spacing: .3px; margin: 5px 0 0; }
        .subtitle { text-align: center; font-size: 8.5px; margin-bottom: 5px; }

        table { border-collapse: collapse; width: 100%; }
        .info td { font-size: 8.5px; padding: 1px 3px; border: 0; }
        .info .lbl { font-weight: bold; }

        table.rsmi td, table.rsmi th { border: 1px solid #000; padding: 2px 3px; font-size: 7px; }
        table.rsmi th { text-align: center; font-weight: bold; vertical-align: middle; }
        table.rsmi .grp { font-style: italic; font-weight: normal; font-size: 7px; }
        .c { text-align: center; }
        .r { text-align: right; }
        td.h { height: 12px; }
        .total td { font-weight: bold; }

        .sign { width: 100%; margin-top: 10px; }
        .sign td { font-size: 8px; padding: 2px 4px; vertical-align: bottom; border: 0; }
        .sign .name { text-align: center; font-weight: bold; border-top: 1px solid #000; padding-top: 1px; }
        .sign .role { text-align: center; font-size: 7.5px; }
    </style>
</head>
<body>
    @php
        $samePeriod = $from->isSameMonth($to);
        $period = $samePeriod
            ? $from->format('F d').'-'.$to->format('d').', '.$to->format('Y')
            : $from->format('M d, Y').' – '.$to->format('M d, Y');
        $dateLine = $samePeriod
            ? $from->format('F d').'-'.$to->format('d').', '.$to->format('Y')
            : $from->format('M d, Y').' – '.$to->format('M d, Y');

        $minRows = 12;
        $blanks = max(0, $minRows - $rows->count());
        $money = fn ($n) => number_format((float) $n, 2);
        $qtyFmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    @endphp

    <div class="appendix">Appendix 64</div>

    {{-- CPSU letterhead --}}
    <div class="header">
        @if ($header)
            <img src="{{ $header }}" alt="CPSU">
        @else
            <div class="org">CENTRAL PHILIPPINES STATE UNIVERSITY</div>
            <div class="org-sub">Kabankalan City, Negros Occidental</div>
        @endif
    </div>

    <div class="title">REPORT OF SUPPLIES AND MATERIALS ISSUED</div>
    <div class="subtitle">For the period {{ $period }}</div>

    {{-- Info block --}}
    <table class="info">
        <tr>
            <td width="62%"><span class="lbl">Entity Name :</span> CENTRAL PHILIPPINES STATE UNIVERSITY</td>
            <td width="38%"><span class="lbl">Serial No. :</span> {{ $serial }}</td>
        </tr>
        <tr>
            <td><span class="lbl">Fund Cluster :</span> {{ $fund ? $fund->code.' — '.$fund->name : '' }}</td>
            <td><span class="lbl">Date :</span> {{ $dateLine }}</td>
        </tr>
        @if ($accountTitle ?? null)
            <tr>
                <td colspan="2"><span class="lbl">Account Title :</span> {{ $accountTitle->name }} — {{ $accountTitle->rca_code }}</td>
            </tr>
        @endif
    </table>

    {{-- Report table --}}
    <table class="rsmi" style="margin-top:4px">
        <thead>
            <tr>
                <th colspan="8" class="grp">To be filled up by the Supply and/or Property Division/Unit</th>
                <th colspan="2" class="grp">To be filled up by the Accounting Division/Unit</th>
                <th rowspan="2" width="14%">Remarks</th>
            </tr>
            <tr>
                <th width="8%">RIS No.</th>
                <th width="7%">RSMI Code</th>
                <th width="4%">Stock No.</th>
                <th width="20%">Items</th>
                <th width="9%">Account Title</th>
                <th width="10%">Particular</th>
                <th width="4%">Unit</th>
                <th width="6%">Quantity Issued</th>
                <th width="7%">Unit Cost</th>
                <th width="7%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td class="c">{{ $r->ris_number }}</td>
                    <td class="c">{{ $r->rca_code }}</td>
                    <td class="c">{{ $r->stock_number }}</td>
                    <td>{{ $r->item_name }}</td>
                    <td>{{ $r->account_title }}</td>
                    <td>{{ $r->particular }}</td>
                    <td class="c">{{ $r->unit }}</td>
                    <td class="r">{{ $qtyFmt($r->quantity) }}</td>
                    <td class="r">{{ $money($r->unit_cost) }}</td>
                    <td class="r">{{ $money($r->amount) }}</td>
                    <td>{{ $r->remarks }}</td>
                </tr>
            @endforeach

            @for ($i = 0; $i < $blanks; $i++)
                <tr>
                    <td class="h"></td><td></td><td></td><td></td><td></td>
                    <td></td><td></td><td></td><td></td><td></td><td></td>
                </tr>
            @endfor

            <tr class="total">
                <td colspan="9" class="r">TOTAL:</td>
                <td class="r">{{ $money($total) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- Certification / signatories --}}
    <table class="sign">
        <tr>
            <td width="50%">I hereby certify to the correctness of the above information.</td>
            <td width="50%">Posted by:</td>
        </tr>
        <tr>
            <td style="height:26px">&nbsp;</td>
            <td style="height:26px">&nbsp;</td>
        </tr>
        <tr>
            <td class="name">{{ $certifiedBy }}</td>
            <td class="name">{{ $postedBy }}</td>
        </tr>
        <tr>
            <td class="role">Signature over Printed Name of Supply Officer</td>
            <td class="role">Signature over Printed Name of Designated Accounting Staff</td>
        </tr>
    </table>
</body>
</html>
