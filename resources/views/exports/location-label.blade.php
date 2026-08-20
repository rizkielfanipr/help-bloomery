<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Label Lokasi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; }

        .label {
            width: 100%;
            padding: 24px;
            text-align: center;
            page-break-after: always;
        }
        .label:last-child { page-break-after: auto; }

        .label .qr { width: 220px; height: 220px; margin: 0 auto 16px; }
        .label .code { font-size: 20px; font-weight: bold; letter-spacing: 1px; margin-bottom: 6px; }
        .label .name { font-size: 14px; margin-bottom: 2px; }
        .label .type { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    @foreach ($labels as $label)
        <div class="label">
            @if ($label['qr'])
                <img class="qr" src="{{ $label['qr'] }}" alt="QR {{ $label['code'] }}">
            @endif
            <div class="code">{{ $label['code'] }}</div>
            <div class="name">{{ $label['name'] }}</div>
            <div class="type">{{ $label['type'] }}</div>
        </div>
    @endforeach
</body>
</html>
