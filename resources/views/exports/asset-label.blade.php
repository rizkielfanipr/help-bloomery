<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label Asset</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #111827; }
        .label { padding: 24px; text-align: center; page-break-after: always; }
        .label:last-child { page-break-after: auto; }
        .qr { width: 220px; height: 220px; margin: 0 auto 14px; }
        .number { font-size: 19px; font-weight: bold; letter-spacing: .6px; }
        .name { margin-top: 5px; font-size: 15px; font-weight: bold; }
        .meta { margin-top: 4px; font-size: 11px; color: #64748b; }
        .hint { margin-top: 10px; font-size: 10px; color: #94a3b8; }
    </style>
</head>
<body>
@foreach($labels as $label)
    <div class="label">
        @if($label['qr'])<img class="qr" src="{{ $label['qr'] }}" alt="QR {{ $label['number'] }}">@endif
        <div class="number">{{ $label['number'] }}</div>
        <div class="name">{{ $label['name'] }}</div>
        <div class="meta">{{ $label['branch'] }}</div>
        <div class="hint">Scan untuk melihat identitas asset</div>
    </div>
@endforeach
</body>
</html>
