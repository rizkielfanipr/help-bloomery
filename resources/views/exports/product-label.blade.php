<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Label Produk</title>
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

        .label .qr { width: 160px; height: 160px; margin: 0 auto 12px; }
        .label .code { font-size: 18px; font-weight: bold; letter-spacing: 1px; margin-bottom: 12px; }
        .label .barcode { width: 260px; height: auto; margin: 0 auto 8px; }
        .label .barcode-value { font-size: 11px; color: #666; letter-spacing: 1px; }
    </style>
</head>
<body>
    @foreach ($labels as $label)
        <div class="label">
            @if ($label['qr'])
                <img class="qr" src="{{ $label['qr'] }}" alt="QR {{ $label['code'] }}">
            @endif
            <div class="code">{{ $label['code'] }}</div>
            @if ($label['barcode'])
                <img class="barcode" src="{{ $label['barcode'] }}" alt="Barcode {{ $label['barcode_value'] }}">
            @endif
            <div class="barcode-value">{{ $label['barcode_value'] }}</div>
        </div>
    @endforeach
</body>
</html>
