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

        .heading { margin: 10px 0 4px; }
        .heading .name { font-weight: bold; font-size: 10px; }
        .heading .asof { font-weight: bold; font-size: 9px; }
        .heading .fund { font-size: 8px; color: #444; margin-top: 1px; }

        table { border-collapse: collapse; width: 100%; }
        table.sum td, table.sum th { border: 1px solid #000; padding: 2px 5px; font-size: 8px; }
        table.sum th { text-align: center; font-weight: bold; background: #EFEFEF; }
        .sec { font-weight: bold; }
        .month { padding-left: 14px !important; }
        .r { text-align: right; }
        .sub td { font-weight: bold; background: #F7F7F7; }
        .end td { font-weight: bold; background: #EFEFEF; }

        .foot { margin-top: 12px; width: 100%; }
        .foot td { font-size: 8px; padding: 2px 4px; border: 0; vertical-align: bottom; }
        .foot .name { text-align: center; font-weight: bold; border-top: 1px solid #000; padding-top: 1px; text-transform: uppercase; }
        .foot .role { text-align: center; font-size: 7.5px; }
    </style>
</head>
<body>
    @php
        $money = fn ($n) => (float) $n != 0.0 ? number_format((float) $n, 2) : '-';
        $months = [1 => 'January', 'February', 'March', 'April', 'May', 'June',
                   'July', 'August', 'September', 'October', 'November', 'December'];
        $totalPurchases = array_sum($purchases);
        $totalIssues = array_sum($issues);
        $ending = $beginning + $totalPurchases - $totalIssues;
        $certified = config('ris.issued_by');
    @endphp

    {{-- CPSU letterhead (same as the Stock Status report) --}}
    <div class="header">
        @if ($header)
            <img src="{{ $header }}" alt="CPSU">
        @else
            <div class="org">CENTRAL PHILIPPINES STATE UNIVERSITY</div>
            <div class="org-sub">Kabankalan City, Negros Occidental</div>
        @endif
    </div>

    <div class="heading">
        <div class="name">{{ $label }}</div>
        <div class="asof">As of {{ $asOf->format('F d, Y') }}</div>
        <div class="fund">
            RCA {{ $accountTitle?->rca_code ?? 'All' }} · Fund Cluster: {{ $fund ? $fund->code.' — '.$fund->name : 'All' }}
        </div>
    </div>

    <table class="sum">
        <thead>
            <tr>
                <th width="26%">Particulars</th>
                <th width="44%">Period</th>
                <th width="30%">Amount</th>
            </tr>
        </thead>
        <tbody>
            {{-- Balance carried into the year --}}
            <tr>
                <td colspan="2">{{ $label }}, {{ $openingDate->format('M. d, Y') }}</td>
                <td class="r">{{ $money($beginning) }}</td>
            </tr>

            {{-- Purchases --}}
            <tr><td class="sec">Purchases</td><td></td><td></td></tr>
            @foreach ($months as $number => $monthName)
                <tr>
                    <td></td>
                    <td class="month">{{ $monthName }}</td>
                    <td class="r">{{ $money($purchases[$number] ?? 0) }}</td>
                </tr>
            @endforeach
            <tr class="sub">
                <td></td><td class="r">Total Purchases</td>
                <td class="r">{{ number_format($totalPurchases, 2) }}</td>
            </tr>

            {{-- Expenses / Issuance --}}
            <tr><td class="sec">Expenses/Issuance</td><td></td><td></td></tr>
            @foreach ($months as $number => $monthName)
                <tr>
                    <td></td>
                    <td class="month">{{ $monthName }}</td>
                    <td class="r">{{ $money($issues[$number] ?? 0) }}</td>
                </tr>
            @endforeach
            <tr class="sub">
                <td></td><td class="r">Total Expenses/Issuance</td>
                <td class="r">{{ number_format($totalIssues, 2) }}</td>
            </tr>

            {{-- Closing --}}
            <tr class="end">
                <td colspan="2">{{ $label }}, {{ $asOf->format('M. d, Y') }}</td>
                <td class="r">{{ number_format($ending, 2) }}</td>
            </tr>
        </tbody>
    </table>

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
