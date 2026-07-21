<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 26px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9px; color: #000; }
        .header { width: 100%; text-align: center; margin-bottom: 4px; }
        .header img { width: 100%; max-height: 78px; }
        .title { text-align: center; font-weight: bold; font-size: 13px; letter-spacing: 1px; margin: 4px 0 8px; }

        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }

        .info td { font-size: 9px; }
        .info .lbl { font-weight: bold; }

        table.card { margin-top: 6px; }
        table.card th { text-align: center; font-weight: bold; font-size: 8.5px; background: #f2f2f2; }
        table.card td { font-size: 8.5px; height: 15px; }
        .c { text-align: center; }
        .r { text-align: right; }
        .muted { color: #555; }
        .foot { margin-top: 10px; font-size: 8px; color: #666; }
    </style>
</head>
<body>
    {{-- CPSU letterhead (RIS title cropped out) --}}
    <div class="header">
        @if ($header)
            <img src="{{ $header }}" alt="CPSU">
        @else
            <div style="font-weight:bold">CENTRAL PHILIPPINES STATE UNIVERSITY</div>
        @endif
    </div>
    <div class="title">STOCK CARD</div>

    {{-- Item info --}}
    <table class="info">
        <tr>
            <td width="50%"><span class="lbl">Item Code:</span> {{ $item->stock_number }}</td>
            <td width="50%"><span class="lbl">Unit of Measure:</span> {{ $item->unit?->name }} ({{ $item->unit?->abbreviation }})</td>
        </tr>
        <tr>
            <td colspan="2"><span class="lbl">Item Description:</span> {{ $item->name }}</td>
        </tr>
        <tr>
            <td><span class="lbl">Account Title:</span> {{ $item->accountTitle?->name ?? '—' }}</td>
            <td><span class="lbl">RCA Code:</span> {{ $item->accountTitle?->rca_code ?? '—' }}</td>
        </tr>
        <tr>
            <td><span class="lbl">Current On-Hand:</span> {{ number_format($item->on_hand_qty, 2) }} {{ $item->unit?->abbreviation }}</td>
            <td><span class="lbl">Status:</span> {{ $item->is_active ? 'Active' : 'Inactive' }}</td>
        </tr>
    </table>

    {{-- Ledger --}}
    <table class="card">
        <thead>
            <tr>
                <th width="13%">DATE</th>
                <th width="17%">REFERENCE</th>
                <th width="30%">SUPPLIER / OFFICE</th>
                <th width="12%">RCA</th>
                <th width="9%">RECEIPT</th>
                <th width="9%">ISSUE</th>
                <th width="10%">BALANCE</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($timeline as $row)
                <tr>
                    <td class="c">{{ $row['date']?->format('m/d/Y') }}</td>
                    <td>{{ $row['ref'] }}</td>
                    <td>{{ $row['party'] ?? '—' }}</td>
                    <td class="c">{{ $row['rca'] ?? '' }}</td>
                    <td class="r">{{ $row['type'] === 'in' ? number_format($row['qty'], 2) : '' }}</td>
                    <td class="r">{{ $row['type'] === 'out' ? number_format($row['qty'], 2) : '' }}</td>
                    <td class="r">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="c muted" style="height:24px">No transactions recorded for this item.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">
        Generated {{ now()->format('F d, Y g:i A') }} &nbsp;·&nbsp; CPSU Common Supply Management System
    </div>
</body>
</html>
