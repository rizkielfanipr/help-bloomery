<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bill of Material - {{ $productRecord->name }}</title>
    <style>
        @page { margin: 22px 26px 42px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .document-header { margin-bottom: 18px; table-layout: fixed; page-break-inside: avoid; }
        .document-header td { border: 1.2px solid #111827; vertical-align: middle; }
        .logo-cell { width: 16%; padding: 10px; text-align: center; }
        .logo { width: 66px; height: 66px; }
        .title-cell { width: 51%; padding: 10px 14px; }
        .company { margin-bottom: 6px; font-size: 11px; }
        .document-title { font-size: 15px; font-weight: 700; line-height: 1.28; text-transform: uppercase; }
        .meta-label { width: 15%; padding: 7px 8px; }
        .meta-value { width: 18%; padding: 7px 8px; font-weight: 700; }
        .summary { margin-bottom: 16px; border: 1px solid #cbd5e1; background: #f8fafc; }
        .summary td { padding: 7px 9px; border-right: 1px solid #cbd5e1; }
        .summary td:last-child { border-right: 0; }
        .summary-label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .summary-value { margin-top: 2px; font-weight: 700; }
        .main-group { margin-bottom: 18px; }
        .main-heading { padding: 8px 10px; border: 1.4px solid #1d4ed8; background: #eff6ff; }
        .main-badge { color: #1d4ed8; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .main-name { margin-top: 2px; font-size: 13px; font-weight: 700; }
        .bom-section { margin-top: 10px; border: 1.2px solid #111827; page-break-inside: avoid; }
        .bom-title { padding: 7px 9px; border-bottom: 1.2px solid #111827; background: #f8fafc; }
        .bom-title strong { font-size: 11px; }
        .bom-subtitle { margin-top: 2px; color: #64748b; }
        .materials th { border-right: 1px solid #111827; border-bottom: 1.2px solid #111827; padding: 6px; text-align: center; font-size: 8px; text-transform: uppercase; }
        .materials th:last-child { border-right: 0; }
        .materials td { border-top: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        .materials td:last-child { border-right: 0; }
        .materials tbody tr:first-child td { border-top: 0; }
        .number { width: 6%; text-align: center; color: #64748b; }
        .code { width: 15%; font-weight: 700; }
        .material-name { width: 30%; }
        .unit { width: 10%; text-align: center; font-weight: 700; color: #1d4ed8; }
        .qty { width: 10%; text-align: right; font-weight: 700; }
        .additional { width: 29%; color: #64748b; }
        .instruction { border-top: 1.2px solid #111827; padding: 9px; }
        .instruction-title { margin-bottom: 5px; color: #4c1d95; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .instruction-content { line-height: 1.55; }
        .instruction-content p { margin: 0 0 5px; }
        .instruction-images { margin-top: 7px; }
        .instruction-image { display: inline-block; width: 31%; height: 105px; margin: 0 1.5% 5px 0; object-fit: cover; vertical-align: top; }
        .empty { padding: 15px !important; text-align: center; color: #94a3b8; }
        .group-label { margin: 12px 0 3px; color: #475569; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .unassigned { border-color: #d97706; }
        .footer { position: fixed; right: 0; bottom: -25px; left: 0; border-top: 1px solid #cbd5e1; padding-top: 7px; color: #94a3b8; font-size: 8px; }
        .footer-left { float: left; }
        .footer-right { float: right; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        <span class="footer-left">Dicetak: {{ now()->translatedFormat('d M Y, H:i') }}</span>
        <span class="footer-right">{{ $productRecord->name }} · {{ $productRecord->boms->count() }} Bill of Material · Halaman <span class="page-number"></span></span>
    </div>

    <table class="document-header">
        <tr>
            <td class="logo-cell" rowspan="3"><img class="logo" src="{{ $logo }}" alt="Bloomery"></td>
            <td class="title-cell" rowspan="3">
                <div class="company">PT Bloomery Sekawan Sejahtera</div>
                <div class="document-title">Standar Prosedur Operasional<br>Produk Baru: {{ $productRecord->name }}</div>
            </td>
            <td class="meta-label">Nomor Dokumen</td>
            <td class="meta-value">{{ $documentNumber }}</td>
        </tr>
        <tr><td class="meta-label">Tanggal Berlaku</td><td class="meta-value">{{ $projectRecord->start_date->format('d M Y') }} – {{ $projectRecord->end_date->format('d M Y') }}</td></tr>
        <tr><td class="meta-label">Status Produk</td><td class="meta-value">{{ \App\Models\RndProjectProduct::STATUSES[$productRecord->status] ?? ucfirst($productRecord->status) }}</td></tr>
    </table>

    <table class="summary">
        <tr>
            <td><div class="summary-label">Project</div><div class="summary-value">{{ $projectRecord->name }}</div></td>
            <td><div class="summary-label">Product Code</div><div class="summary-value">{{ $productRecord->product_code ?: '-' }}</div></td>
            <td><div class="summary-label">Target Rilis</div><div class="summary-value">{{ $productRecord->release_date?->format('d M Y') ?? '-' }}</div></td>
            <td><div class="summary-label">Total BOM</div><div class="summary-value">{{ $productRecord->boms->count() }}</div></td>
        </tr>
    </table>

    @php
        $groupLabels = [
            'component' => 'Components',
            'packaging' => 'Packaging',
        ];
    @endphp

    @forelse($mainBoms as $mainBom)
        <div class="main-group">
            <div class="main-heading">
                <div class="main-badge">Main Recipe · {{ $mainBom->bom_code ?: 'BOM-'.$mainBom->esb_bom_id }}</div>
                <div class="main-name">{{ $mainBom->bom_name }}</div>
            </div>

            @include('exports.partials.rnd-bom-section', ['bomModel' => $mainBom, 'bom' => $details[$mainBom->id], 'sectionLabel' => 'Main Recipe', 'instruction' => $instructions[$mainBom->esb_bom_id] ?? null])

            @if(!empty($autoWipBoms[$mainBom->id]))
                <div class="group-label">Components Otomatis · Barang WIP</div>
                @foreach($autoWipBoms[$mainBom->id] as $autoBom)
                    @include('exports.partials.rnd-bom-section', [
                        'bomModel' => (object) ['bom_name' => '', 'bom_code' => '', 'product_name' => ''],
                        'bom' => $autoBom,
                        'sectionLabel' => 'Component Otomatis',
                        'instruction' => $instructions[$autoBom['bomID']] ?? null,
                    ])
                @endforeach
            @endif

            @foreach($groupLabels as $usageType => $groupLabel)
                @php
                    $children = $productRecord->boms->filter(
                        fn ($bom) => $bom->pivot->usage_type === $usageType
                            && (int) $bom->pivot->parent_rnd_project_bom_id === $mainBom->id
                    );
                @endphp
                @if($children->isNotEmpty())
                    <div class="group-label">{{ $groupLabel }}</div>
                    @foreach($children as $child)
                        @include('exports.partials.rnd-bom-section', ['bomModel' => $child, 'bom' => $details[$child->id], 'sectionLabel' => $groupLabel, 'instruction' => $instructions[$child->esb_bom_id] ?? null])
                    @endforeach
                @endif
            @endforeach
        </div>
    @empty
        <div class="bom-section"><div class="empty">Belum ada Main Recipe pada produk ini.</div></div>
    @endforelse

    @if($unassigned->isNotEmpty())
        <div class="main-group">
            <div class="main-heading unassigned"><div class="main-badge">Belum Ditentukan</div><div class="main-name">BOM tanpa Main Recipe</div></div>
            @foreach($unassigned as $bomModel)
                @include('exports.partials.rnd-bom-section', ['bomModel' => $bomModel, 'bom' => $details[$bomModel->id], 'sectionLabel' => \App\Models\RndProjectProduct::BOM_USAGE_TYPES[$bomModel->pivot->usage_type] ?? $bomModel->pivot->usage_type, 'instruction' => $instructions[$bomModel->esb_bom_id] ?? null])
            @endforeach
        </div>
    @endif
</body>
</html>
