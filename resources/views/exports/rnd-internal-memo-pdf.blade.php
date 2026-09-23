<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Memo Internal - {{ $memo->memo_number }}</title>
    <style>
        @page { margin: 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; }
        h2 { margin: 16px 0 6px; padding: 7px 9px; background: #0f172a; color: #ffffff; font-size: 10pt; font-weight: 700; text-transform: uppercase; }

        .kop { display: table; width: 100%; border: 1.2px solid #111827; margin-bottom: 14px; }
        .kop-logo { display: table-cell; width: 90px; vertical-align: middle; padding: 8px; border-right: 1px solid #111827; text-align: center; }
        .kop-logo img { width: 65px; }
        .kop-center { display: table-cell; vertical-align: middle; padding: 8px 12px; border-right: 1px solid #111827; }
        .kop-center .company-name { font-size: 9pt; margin-bottom: 4px; }
        .kop-center .title-sop { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
        .kop-right { display: table-cell; width: 230px; vertical-align: top; }
        .kop-right-row { display: table; width: 100%; border-bottom: 1px solid #111827; }
        .kop-right-row:last-child { border-bottom: none; }
        .kop-right-label { display: table-cell; width: 100px; padding: 5px 6px; font-size: 7.5pt; color: #64748b; border-right: 1px solid #111827; }
        .kop-right-value { display: table-cell; padding: 5px 6px; font-size: 7.5pt; font-weight: bold; }

        .identity { display: table; width: 100%; border: 1px solid #cbd5e1; margin-bottom: 10px; }
        .identity-col { display: table-cell; width: 50%; vertical-align: top; padding: 8px 10px; }
        .identity-col:first-child { border-right: 1px solid #cbd5e1; }
        .identity-row { margin-bottom: 3px; font-size: 8pt; }
        .identity-row strong { display: inline-block; width: 80px; color: #64748b; font-weight: 600; }

        table.grid { border: 1px solid #111827; margin-bottom: 4px; }
        table.grid th { background: #f8fafc; color: #334155; font-size: 7pt; text-transform: uppercase; padding: 5px 6px; border: 1px solid #cbd5e1; text-align: left; }
        table.grid td { font-size: 7.5pt; padding: 5px 6px; border: 1px solid #cbd5e1; }
        table.grid td.num { text-align: right; white-space: nowrap; }
        .menu-block-title { margin: 10px 0 3px; font-size: 8pt; font-weight: 700; color: #1e293b; }
        .badge-wip { color: #7c3aed; font-weight: 700; }
        .badge-pkg { color: #0284c7; font-weight: 700; }

        .notes-box { margin-top: 6px; padding: 8px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-size: 8pt; min-height: 30px; }

        .signature-area { display: table; width: 100%; margin-top: 26px; }
        .signature-col { display: table-cell; width: 50%; text-align: center; font-size: 8pt; }
        .signature-space { height: 55px; }
        .signature-name { margin-top: 4px; font-weight: 700; border-top: 1px solid #111827; display: inline-block; padding-top: 3px; min-width: 160px; }
        .signature-label { color: #64748b; font-size: 7.5pt; }

        .pdf-footer { margin-top: 18px; padding-top: 6px; border-top: 1px solid #cbd5e1; color: #94a3b8; font-size: 6.8pt; }
    </style>
</head>
<body>
<div class="page-wrapper">

    <div class="kop">
        <div class="kop-logo"><img src="{{ 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/bloomery-icon-pdf.png'))) }}" alt="Bloomery"></div>
        <div class="kop-center">
            <div class="company-name">PT Bloomery Sekawan Sejahtera</div>
            <div class="title-sop">Memo Internal R&amp;D</div>
        </div>
        <div class="kop-right">
            <div class="kop-right-row"><div class="kop-right-label">Nomor Memo</div><div class="kop-right-value">{{ $memo->memo_number }}</div></div>
            <div class="kop-right-row"><div class="kop-right-label">Periode</div><div class="kop-right-value">{{ $memo->period_month->translatedFormat('F Y') }}</div></div>
            <div class="kop-right-row"><div class="kop-right-label">Revisi</div><div class="kop-right-value">{{ $memo->revision }}</div></div>
        </div>
    </div>

    <div class="identity">
        <div class="identity-col">
            <div class="identity-row"><strong>Tanggal</strong> {{ $memo->memo_date->translatedFormat('d F Y') }}</div>
            <div class="identity-row"><strong>Kepada</strong> {{ $memo->recipient }}</div>
            <div class="identity-row"><strong>Dari</strong> {{ $memo->sender }}</div>
        </div>
        <div class="identity-col">
            <div class="identity-row"><strong>Perihal</strong> {{ $memo->subject }}</div>
            <div class="identity-row"><strong>Judul</strong> {{ $memo->title }}</div>
            <div class="identity-row"><strong>Status</strong> {{ $memo->status->getLabel() }}</div>
        </div>
    </div>

    <h2>Ringkasan Menu Rilis</h2>
    <table class="grid">
        <thead>
            <tr><th>Menu</th><th>Kode Menu</th><th>Tanggal Rilis</th><th>Forecast Qty</th><th>Shelf Life</th><th>BOM</th></tr>
        </thead>
        <tbody>
            @foreach($menus as $menu)
                <tr>
                    <td>{{ $menu->menu_name }}</td>
                    <td>{{ $menu->menu_code ?: 'ID '.$menu->esb_menu_id }}</td>
                    <td>{{ $menu->release_date->format('d M Y') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $menu->forecast_quantity, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ $menu->hasShelfLife() ? rtrim(rtrim(number_format((float) $menu->shelf_life_value, 2, '.', ''), '0'), '.').' '.$menu->shelf_life_unit : '-' }}</td>
                    <td>{{ $menu->bom_name ?: 'BOM-'.$menu->esb_bom_id }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Forecast Bahan per Menu</h2>
    @foreach($menus as $menu)
        <div class="menu-block-title">{{ $menu->menu_name }} ({{ $menu->menu_code ?: 'ID '.$menu->esb_menu_id }})</div>
        <table class="grid">
            <thead>
                <tr><th>Bahan</th><th>Kode</th><th>Kategori</th><th>UOM</th><th>Qty / Menu</th><th>Net Qty (Forecast)</th></tr>
            </thead>
            <tbody>
                @forelse($menu->materials as $material)
                    <tr>
                        <td>
                            {{ $material->product_name }}
                            @if($material->is_wip) <span class="badge-wip">[WIP/Assembly]</span> @endif
                            @if($material->is_packaging) <span class="badge-pkg">[Packaging]</span> @endif
                        </td>
                        <td>{{ $material->product_code ?: '-' }}</td>
                        <td>{{ $material->category_name ?: '-' }}</td>
                        <td>{{ $material->uom_name }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $material->quantity_per_menu, 4, '.', ''), '0'), '.') }}</td>
                        <td class="num">{{ $material->is_wip ? '-' : rtrim(rtrim(number_format((float) $material->net_quantity, 4, '.', ''), '0'), '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Tidak ada data bahan.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <h2>Forecast Bahan Konsolidasi</h2>
    <table class="grid">
        <thead>
            <tr><th>Bahan</th><th>Kode</th><th>UOM</th><th>Total Net Qty</th><th>Digunakan di</th></tr>
        </thead>
        <tbody>
            @forelse($consolidatedRows as $row)
                <tr>
                    <td>{{ $row['product_name'] }}</td>
                    <td>{{ $row['product_code'] ?: '-' }}</td>
                    <td>{{ $row['uom_name'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($row['net_quantity'], 4, '.', ''), '0'), '.') }}</td>
                    <td>{{ $row['menu_count'] }} Menu</td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada data konsolidasi.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Catatan Operasional</h2>
    <div class="notes-box">{{ $memo->notes ?: '-' }}</div>

    <div class="signature-area">
        <div class="signature-col">
            <div class="signature-space"></div>
            <div class="signature-name">{{ $memo->sender }}</div>
            <div class="signature-label">Dibuat oleh</div>
        </div>
        <div class="signature-col">
            <div class="signature-space"></div>
            <div class="signature-name">{{ $memo->recipient }}</div>
            <div class="signature-label">Diketahui oleh</div>
        </div>
    </div>

    <div class="pdf-footer">
        Dibuat {{ $generatedAt->translatedFormat('d F Y H:i') }} WIB · Revisi {{ $memo->revision }} · Referensi {{ $memo->memo_number }}
    </div>
</div>
</body>
</html>
