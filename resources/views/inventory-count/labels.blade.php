<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 10mm 8mm; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 8px; color: #111; }

        .head { text-align: center; margin-bottom: 6px; }
        .head .title { font-size: 11px; font-weight: bold; letter-spacing: .4px; color: #0B6E2E; }
        .head .sub { font-size: 7.5px; color: #666; margin-top: 1px; }

        table.sheet { width: 100%; border-collapse: separate; border-spacing: 5px; }
        td.slot { width: 50%; vertical-align: top; }

        /* One tag card */
        table.card { width: 100%; border: 1px dashed #9AA79A; border-collapse: collapse; }
        table.card td { padding: 0; vertical-align: top; }

        .band { background: #0B6E2E; color: #fff; padding: 3px 6px; font-size: 6.5px;
                font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .band .right { float: right; font-weight: normal; letter-spacing: .2px; }

        .body { padding: 6px; }
        .qrcell { width: 78px; padding: 6px 0 6px 6px; text-align: center; }
        .qr { width: 74px; height: 74px; }
        .qrhint { font-size: 5.5px; color: #777; margin-top: 1px; }

        /* Sans only — pulling in a second font family doubles the embedded font
           weight of every printed sheet. */
        .stock { font-size: 9px; font-weight: bold; letter-spacing: .5px; color: #0B6E2E; }
        .name { font-size: 9px; font-weight: bold; line-height: 1.15; margin-top: 1px; }
        .desc { font-size: 6.5px; color: #555; line-height: 1.25; margin-top: 2px; }

        table.meta { width: 100%; margin-top: 4px; border-top: 1px solid #DDE2DC; border-collapse: collapse; }
        table.meta td { font-size: 6.5px; color: #555; padding: 2px 0 0; }
        table.meta .lbl { color: #999; text-transform: uppercase; letter-spacing: .3px; font-size: 5.5px; }
        table.meta .val { font-weight: bold; color: #111; font-size: 7px; }

        .count { border-top: 1px solid #DDE2DC; margin-top: 3px; padding-top: 3px; font-size: 6px; color: #888; }
        .count .line { display: inline-block; border-bottom: 1px solid #BBB; width: 60px; }
    </style>
</head>
<body>
    @unless ($single)
        <div class="head">
            <div class="title">CPSU — INVENTORY QR TAGS</div>
            <div class="sub">Cut along the dashed line and post on the shelf, bin or cabinet.
                Scanning a tag opens its count sheet while an inventory is cast.</div>
        </div>
    @endunless

    <table class="sheet">
        @foreach ($labels->chunk(2) as $row)
            <tr>
                @foreach ($row as $label)
                    @php $item = $label['item']; @endphp
                    <td class="slot">
                        <table class="card">
                            {{-- Branded band --}}
                            <tr>
                                <td colspan="2" class="band">
                                    CPSU CSMS · Inventory Tag
                                    <span class="right">{{ $item->unit?->abbreviation ? 'Unit: '.$item->unit->abbreviation : '' }}</span>
                                </td>
                            </tr>

                            <tr>
                                {{-- QR --}}
                                <td class="qrcell">
                                    <img class="qr" src="{{ $label['qr'] }}" alt="">
                                    <div class="qrhint">SCAN TO COUNT</div>
                                </td>

                                {{-- Description --}}
                                <td class="body">
                                    <div class="stock">{{ $item->stock_number ?? '—' }}</div>
                                    <div class="name">{{ \Illuminate\Support\Str::limit($item->name, 58) }}</div>
                                    @if ($item->description)
                                        <div class="desc">{{ \Illuminate\Support\Str::limit($item->description, 110) }}</div>
                                    @endif

                                    <table class="meta">
                                        <tr>
                                            <td width="45%">
                                                <div class="lbl">Unit</div>
                                                <div class="val">{{ $item->unit?->name ?? '—' }}</div>
                                            </td>
                                            <td width="55%">
                                                <div class="lbl">Account Title</div>
                                                <div class="val">{{ \Illuminate\Support\Str::limit($item->accountTitle?->name ?? '—', 26) }}</div>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="count">
                                        Counted qty <span class="line">&nbsp;</span>
                                        &nbsp; Date <span class="line">&nbsp;</span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach

                @if ($row->count() < 2)
                    <td class="slot">&nbsp;</td>
                @endif
            </tr>
        @endforeach
    </table>

    @if ($labels->isEmpty())
        <p style="text-align:center;color:#777;margin-top:20px">No items match — nothing to print.</p>
    @endif
</body>
</html>
