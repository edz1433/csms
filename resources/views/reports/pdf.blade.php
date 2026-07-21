<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1A1A1A; font-size: 11px; }
        .head { border-bottom: 2px solid #0B6E2E; padding-bottom: 8px; margin-bottom: 12px; }
        .head h1 { color: #0B6E2E; font-size: 15px; margin: 0; }
        .head p { color: #666; font-size: 10px; margin: 2px 0 0; }
        .meta { font-size: 10px; color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #F7F8F5; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; color: #3b4a3e; border-bottom: 1px solid #E3E6DE; }
        td { padding: 5px 8px; border-bottom: 1px solid #eef1ec; }
        .footer { margin-top: 14px; font-size: 9px; color: #999; text-align: right; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Central Philippines State University</h1>
        <p>Common Supply Management System — {{ $title }}</p>
    </div>
    <div class="meta">Period: {{ $from }} to {{ $to }} &nbsp;·&nbsp; Generated: {{ now()->format('M d, Y g:i A') }}</div>

    <table>
        <thead>
            <tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headers) }}" style="text-align:center;color:#999;padding:20px;">No data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">CPSU CSMS · {{ $title }}</div>
</body>
</html>
