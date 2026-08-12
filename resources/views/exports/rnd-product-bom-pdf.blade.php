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

        /* ─── PRODUCT INFORMATION TABLE ─── */
        .product-info-row td { border-color: #bfdbfe; }
        .product-release-photo { width: 90px; padding: 7px; vertical-align: middle; background: #eff6ff; text-align: center; }
        .product-release-photo img { max-width: 72px; max-height: 72px; border: 1px solid #93c5fd; border-radius: 4px; }
        .product-release-placeholder { padding: 24px 3px; color: #60a5fa; background: #ffffff; border: 1px solid #bfdbfe; font-size: 7px; }
        .product-release-info { padding: 0 !important; background: #eff6ff; vertical-align: top; }
        .product-release-label { color: #2563eb; font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
        .product-field-heading { padding: 5px 8px; border-bottom: 1px solid #cbd5e1; background: #f8fafc; color: #1e293b; font-size: 8pt; font-weight: 700; text-transform: uppercase; }
        .product-field-content { padding: 7px 9px; }
        .product-release-name { margin-top: 3px; font-size: 12px; font-weight: 700; }
        .simple-product-code { margin-top: 4px; color: #64748b; font-size: 8px; font-family: DejaVu Sans Mono, monospace; }
        .product-detail-cell { padding: 0 !important; background: #ffffff; vertical-align: top; }
        .product-detail-text { color: #475569; font-size: 8px; line-height: 1.45; }
        .simple-price-cell { width: 260px; padding: 0 !important; background: #ffffff; vertical-align: top; }
        .regional-price-line { margin-top: 5px; padding-top: 4px; border-top: 1px solid #e2e8f0; }
        .regional-price-line:first-of-type { border-top: 0; }
        .regional-price-name { color: #1e293b; font-size: 8px; font-weight: 700; }
        .regional-price-name span { color: #94a3b8; font-family: DejaVu Sans Mono, monospace; font-size: 7px; }
        .regional-price-values { margin-top: 2px; color: #64748b; font-size: 7.5px; }
        .regional-price-values strong { color: #1e293b; }
        .regional-price-empty { margin-top: 5px; color: #94a3b8; font-size: 8px; }

        /* ─── MAIN GROUP ─── */
        .main-group { margin-bottom: 14px; }
        .main-heading { padding: 8px 10px; border: 1.4px solid #1d4ed8; background: #eff6ff; }
        .main-badge { color: #1d4ed8; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .main-name { margin-top: 2px; font-size: 13px; font-weight: 700; }
        .group-label { margin: 12px 0 3px; color: #475569; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .unassigned { border-color: #d97706; }

        /* ─── SECTION DIVIDER (Kitchen / Store) ─── */
        .section-divider { margin: 18px 0 10px; padding: 7px 10px; background: #0f172a; color: #ffffff; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .section-divider:first-child { margin-top: 0; }

        /* ─── BOM SECTION ─── */
        .bom-section { margin-top: 10px; margin-bottom: 14px; page-break-inside: auto; }
        .bom-table { border: 1.2px solid #111827; }
        .bom-table td,
        .bom-table th { border: 1px solid #cbd5e1 !important; }
        .bom-table > tbody:first-child > tr:first-child > td { border-top: 0 !important; }
        .bom-table tr > :first-child { border-left: 0 !important; }
        .bom-table tr > :last-child { border-right: 0 !important; }
        .bom-table > tbody:last-child > tr:last-child > td { border-bottom: 0 !important; }
        .bom-header { padding: 6px 8px !important; background: #f8fafc; }
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
        .number-column { width: 34px !important; min-width: 34px; padding-left: 5px !important; padding-right: 5px !important; }
        .unit-badge { background: #dbeafe; color: #1d4ed8; padding: 1px 5px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; }
        .no-bom { text-align: center; color: #94a3b8; padding: 12px; }

        /* ─── INFORMASI TAMBAHAN ─── */
        .additional-info-section { border-right: 1.2px solid #111827; border-bottom: 1.2px solid #111827; border-left: 1.2px solid #111827; page-break-inside: auto; }
        .additional-info-heading { padding: 5px 8px; border-bottom: 1px solid #cbd5e1; background: #f8fafc; color: #1e293b; font-size: 8pt; font-weight: 700; text-align: left; text-transform: uppercase; page-break-after: avoid; }
        .additional-info-content { min-height: 36px; padding: 8px 10px; vertical-align: top; page-break-inside: auto; }
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

        /* ─── SYSTEM-GENERATED FOOTER ─── */
        .footer { width: 100%; margin-top: 18px; padding-top: 9px; border-top: 1px solid #cbd5e1; }
        .footer-meta { width: 100%; border-collapse: collapse; }
        .footer-meta td { width: 33.333%; padding: 0 8px; border: 0; vertical-align: top; }
        .footer-meta td:first-child { padding-left: 0; }
        .footer-meta td:last-child { padding-right: 0; text-align: right; }
        .footer-label { color: #94a3b8; font-size: 6.5pt; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
        .footer-value { margin-top: 3px; color: #334155; font-size: 7.5pt; font-weight: 700; }
        .footer-notice { margin-top: 10px; color: #94a3b8; font-size: 6.5pt; line-height: 1.4; text-align: center; }
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

    @php
        $groupLabels = [
            'component' => 'Components',
            'packaging' => 'Packaging',
        ];
    @endphp

    @if($exportScope !== 'store')
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
    @endif

    @if($exportScope !== 'kitchen')
    @forelse($menuBoms as $menuBom)
        @include('exports.partials.rnd-bom-section', ['bomModel' => $menuBom, 'bom' => $details[$menuBom->id], 'sectionLabel' => 'Menu', 'instruction' => $instructions[$menuBom->esb_bom_id] ?? null])
    @empty
        <div class="bom-section"><div class="empty">Belum ada BOM Menu pada produk ini.</div></div>
    @endforelse
    @endif

    @php
        $footerScope = match($exportScope) {
            'store' => 'STORE-',
            'kitchen' => 'KITCHEN-',
            default => '',
        };
        $footerDocument = 'BOM-'.$footerScope.str($productRecord->product_code ?: $productRecord->name)->slug()->upper();
        $generatedAt = now()->timezone('Asia/Jakarta')->translatedFormat('d M Y · H:i').' WIB';
    @endphp
    <div class="footer">
        <table class="footer-meta">
            <tr>
                <td>
                    <div class="footer-label">Generated By</div>
                    <div class="footer-value">Bloomery R&amp;D System</div>
                </td>
                <td>
                    <div class="footer-label">Document</div>
                    <div class="footer-value">{{ $footerDocument }}</div>
                </td>
                <td>
                    <div class="footer-label">Generated At</div>
                    <div class="footer-value">{{ $generatedAt }}</div>
                </td>
            </tr>
        </table>
        <div class="footer-notice">
            Dokumen ini dibuat secara otomatis oleh Bloomery R&amp;D System<br>
            dan tidak memerlukan tanda tangan manual.
        </div>
    </div>

</div>
</body>
</html>
