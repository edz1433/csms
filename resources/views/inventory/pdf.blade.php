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
    @php
        // Report "All items" passes several cards; the single-item PDF passes one item.
        $cards = $cards ?? [['item' => $item, 'timeline' => $timeline, 'beginning' => $beginning, 'qr' => $qr ?? null]];
    @endphp
    @foreach ($cards as $card)
        <div @if (! $loop->last) style="page-break-after: always" @endif>
            @include('inventory.partials.stock-card', $card)
        </div>
    @endforeach
</body>
</html>
