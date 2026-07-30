<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 9px; }
        h1 { margin: 0 0 4px; color: #1d4ed8; font-size: 18px; }
        .meta { margin-bottom: 16px; color: #64748b; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 7px 5px; background: #2563eb; color: #fff; text-align: left; }
        td { padding: 6px 5px; border: 1px solid #dbe2ea; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .status { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Daftar Bahan Baru R&D</h1>
    <div class="meta">
        Project: <strong>{{ $projectRecord->name }}</strong><br>
        Product Release: <strong>{{ $productRecord->name }}</strong><br>
        Dicetak: {{ now()->format('d M Y H:i') }}
    </div>
    <table>
        <thead>
        <tr>
            <th>No</th><th>Product Code</th><th>Product Name</th><th>Category</th>
            <th>Sub Category</th><th>Unit</th><th>SKU</th><th>Conv.</th>
            <th>Base Price</th><th>Status</th><th>ESB ID</th><th>Keterangan</th>
        </tr>
        </thead>
        <tbody>
        @forelse($materials as $index => $material)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $material->product_code }}</td>
                <td>{{ $material->product_name }}</td>
                <td>{{ $material->category_name ?: $material->category_id }}</td>
                <td>{{ $material->sub_category_name ?: $material->sub_category_id }}</td>
                <td>@foreach($material->units as $unit){{ $unit->uom_name }} (ID {{ $unit->uom_id }}){{ $unit->is_base ? ' - Base' : '' }}<br>@endforeach</td>
                <td>@foreach($material->units as $unit){{ $unit->sku }}<br>@endforeach</td>
                <td>@foreach($material->units as $unit)1 {{ $unit->uom_name }} = {{ rtrim(rtrim($unit->conversion_factor, '0'), '.') }} {{ $material->uom_name }}<br>@endforeach</td>
                <td>{{ number_format((float) $material->base_price, 0, ',', '.') }}</td>
                <td class="status">{{ \App\Models\RndProductEsbMaterial::STATUSES[$material->status] ?? ucfirst($material->status) }}</td>
                <td>{{ $material->esb_product_id ?: '-' }}</td>
                <td>{{ $material->sync_error ?: ($material->notes ?: '-') }}</td>
            </tr>
        @empty
            <tr><td colspan="12" style="text-align:center;padding:24px">Belum ada bahan baru.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
