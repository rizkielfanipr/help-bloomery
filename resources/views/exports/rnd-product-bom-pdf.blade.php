<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bill of Material - {{ $productRecord->name }}</title>
    <style>
        @page { margin: 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; }
        .page-wrapper { width: 100%; }

        /* ─── KOP ─── */
        .kop { display: table; width: 100%; border: 1.2px solid #111827; margin-bottom: 14px; }
        .kop-logo { display: table-cell; width: 90px; vertical-align: middle; padding: 8px; border-right: 1px solid #111827; text-align: center; }
        .kop-logo img { width: 65px; }
        .kop-center { display: table-cell; vertical-align: middle; padding: 8px 12px; border-right: 1px solid #111827; }
        .kop-center .company-name { font-size: 9pt; margin-bottom: 4px; }
        .kop-center .title-sop { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
        .kop-center .project-label { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .kop-right { display: table-cell; width: 230px; vertical-align: top; }
        .kop-right-row { display: table; width: 100%; border-bottom: 1px solid #111827; }
        .kop-right-row:last-child { border-bottom: none; }
        .kop-right-label { display: table-cell; width: 110px; padding: 6px; font-size: 8pt; color: #64748b; border-right: 1px solid #111827; }
        .kop-right-value { display: table-cell; padding: 6px; font-size: 8pt; font-weight: bold; }

        /* ─── SUMMARY ─── */
        .summary { margin-bottom: 14px; border: 1px solid #cbd5e1; background: #f8fafc; }
        .summary td { padding: 7px 9px; border-right: 1px solid #cbd5e1; }
        .summary td:last-child { border-right: 0; }
        .summary-label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .summary-value { margin-top: 2px; font-weight: 700; }

        /* ─── MAIN GROUP ─── */
        .main-group { margin-bottom: 14px; }
        .main-heading { padding: 8px 10px; border: 1.4px solid #1d4ed8; background: #eff6ff; }
        .main-badge { color: #1d4ed8; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .main-name { margin-top: 2px; font-size: 13px; font-weight: 700; }
        .group-label { margin: 12px 0 3px; color: #475569; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .unassigned { border-color: #d97706; }

        /* ─── BOM SECTION ─── */
        .bom-section { margin-top: 10px; margin-bottom: 14px; page-break-inside: avoid; border: 1.2px solid #111827; }
        .bom-header { padding: 6px 8px; border-bottom: 1.2px solid #111827; background: #f8fafc; }
        .bom-title { font-size: 9pt; font-weight: bold; }
        .bom-subtitle { margin-top: 2px; color: #64748b; font-size: 8pt; }
        .bom-result { margin-top: 5px; padding: 4px 7px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-size: 8pt; }
        .bom-result strong { color: #047857; }

        /* ─── MATERIALS TABLE ─── */
        thead th { padding: 5px 8px; font-size: 8pt; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #111827; border-right: 1px solid #111827; }
        thead th:last-child { border-right: none; }
        tbody td { padding: 5px 8px; font-size: 8.5pt; vertical-align: top; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; }
        tbody td:last-child { border-right: none; }
        tbody tr:last-child td { border-bottom: none; }
        .center { text-align: center; }
        .num { color: #94a3b8; }
        .unit-badge { background: #dbeafe; color: #1d4ed8; padding: 1px 5px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; }
        .no-bom { text-align: center; color: #94a3b8; padding: 12px; }

        /* ─── INFORMASI TAMBAHAN ─── */
        .additional-cell { width: 220px; vertical-align: top; padding: 8px; border-left: 1px solid #cbd5e1; }
        .instruction-content { font-size: 8.5pt; line-height: 1.5; color: #1e293b; }
        .instruction-content p { margin: 0 0 4px; }
        .instruction-content h1 { font-size: 11pt; font-weight: bold; margin: 0 0 4px; }
        .instruction-content h2 { font-size: 10pt; font-weight: bold; margin: 0 0 4px; }
        .instruction-content h3 { font-size: 9pt; font-weight: bold; margin: 0 0 3px; }
        .instruction-content strong { font-weight: bold; }
        .instruction-content em { font-style: italic; }
        .instruction-content u { text-decoration: underline; }
        .instruction-content s { text-decoration: line-through; }
        .instruction-content ul, .instruction-content ol { margin: 2px 0 4px 16px; padding: 0; }
        .instruction-content li { margin-bottom: 2px; }
        .instruction-content blockquote { border-left: 3px solid #cbd5e1; padding-left: 8px; color: #64748b; margin: 4px 0; }
        .instruction-content a { color: #2563eb; }
        .instruction-content img { width: 195px; height: auto; margin: 4px 0; display: block; }
        .instruction-images { margin-top: 6px; }
        .instruction-image { max-width: 80px; max-height: 80px; width: auto; height: auto; margin: 0 3px 3px 0; display: inline-block; object-fit: cover; }
        .instruction-empty { color: #94a3b8; font-size: 7.5pt; }

        .empty { padding: 15px !important; text-align: center; color: #94a3b8; }

        /* ─── FOOTER (normal flow, not fixed — DomPDF mis-paginates position:fixed here) ─── */
        .footer { margin-top: 16px; border-top: 1px solid #e2e8f0; padding-top: 6px; display: table; width: 100%; }
        .footer-left { display: table-cell; font-size: 7pt; color: #94a3b8; }
        .footer-right { display: table-cell; text-align: right; font-size: 7pt; color: #94a3b8; }
    </style>
</head>
<body>
<div class="page-wrapper">

    <div class="kop">
        <div class="kop-logo"><img src="{{ $logo }}" alt="Bloomery"></div>
        <div class="kop-center">
            <div class="company-name">PT Bloomery Sekawan Sejahtera</div>
            <div class="title-sop">Standar Prosedur Operasional</div>
            <div class="project-label">Produk Baru:<br>{{ strtoupper($productRecord->name) }}</div>
        </div>
        <div class="kop-right">
            <div class="kop-right-row">
                <div class="kop-right-label">Nomor Dokumen</div>
                <div class="kop-right-value">{{ $documentNumber }}</div>
            </div>
            <div class="kop-right-row">
                <div class="kop-right-label">Tanggal Berlaku</div>
                <div class="kop-right-value">{{ $projectRecord->start_date->format('d M Y') }} – {{ $projectRecord->end_date->format('d M Y') }}</div>
            </div>
            <div class="kop-right-row">
                <div class="kop-right-label">Status Produk</div>
                <div class="kop-right-value">{{ \App\Models\RndProjectProduct::STATUSES[$productRecord->status] ?? ucfirst($productRecord->status) }}</div>
            </div>
        </div>
    </div>

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

    <div class="footer">
        <div class="footer-left">Dicetak: {{ now()->translatedFormat('d M Y, H:i') }}</div>
        <div class="footer-right">{{ $productRecord->name }} · {{ $productRecord->boms->count() }} Bill of Material</div>
    </div>

</div>
</body>
</html>
