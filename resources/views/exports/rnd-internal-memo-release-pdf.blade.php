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

        /* ─── KOP (same letterhead as the R&D SOP PDF) ─── */
        .kop { display: table; width: 100%; border: 1.2px solid #111827; margin-bottom: 14px; }
        .kop-logo { display: table-cell; width: 90px; vertical-align: middle; padding: 8px; border-right: 1px solid #111827; text-align: center; }
        .kop-logo img { width: 65px; }
        .kop-center { display: table-cell; vertical-align: middle; padding: 8px 12px; border-right: 1px solid #111827; }
        .kop-center .company-name { font-size: 9pt; margin-bottom: 4px; }
        .kop-center .title-memo { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
        .kop-center .memo-label { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .kop-right { display: table-cell; width: 230px; vertical-align: top; }
        .kop-right-row { display: table; width: 100%; border-bottom: 1px solid #111827; }
        .kop-right-row:last-child { border-bottom: none; }
        .kop-right-label { display: table-cell; width: 110px; padding: 6px; font-size: 8pt; color: #64748b; border-right: 1px solid #111827; }
        .kop-right-value { display: table-cell; padding: 6px; font-size: 8pt; font-weight: bold; }

        /* ─── MEMO IDENTITY ─── */
        .identity { margin-bottom: 14px; border: 1.2px solid #111827; }
        .identity td { padding: 5px 8px; font-size: 8.5pt; border-bottom: 1px solid #cbd5e1; vertical-align: top; }
        .identity tr:last-child td { border-bottom: none; }
        .identity .label { width: 110px; color: #64748b; border-right: 1px solid #cbd5e1; }

        /* ─── MENU TABLE ─── */
        .section-divider { margin: 0 0 6px; padding: 7px 10px; background: #0f172a; color: #ffffff; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .menu-table { border: 1.2px solid #111827; }
        .menu-table thead th { padding: 5px 8px; font-size: 8pt; font-weight: bold; text-transform: uppercase; text-align: left; background: #f8fafc; border-bottom: 1px solid #111827; border-right: 1px solid #111827; }
        .menu-table thead th:last-child { border-right: none; }
        .menu-table tbody td { padding: 5px 8px; font-size: 8.5pt; vertical-align: top; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; }
        .menu-table tbody td:last-child { border-right: none; }
        .menu-table tbody tr:last-child td { border-bottom: none; }
        .menu-table tbody tr { page-break-inside: avoid; }
        .number-column { width: 30px; text-align: center; color: #94a3b8; }
        .menu-code { color: #64748b; font-size: 7.5pt; font-family: DejaVu Sans Mono, monospace; }
        .empty { padding: 15px !important; text-align: center; color: #94a3b8; }

        /* ─── NOTES ─── */
        .notes { margin-top: 14px; border: 1.2px solid #111827; }
        .notes-heading { padding: 5px 8px; border-bottom: 1px solid #cbd5e1; background: #f8fafc; font-size: 8pt; font-weight: 700; text-transform: uppercase; }
        .notes-content { padding: 8px 10px; font-size: 8.5pt; line-height: 1.5; }

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
    <div class="kop">
        <div class="kop-logo"><img src="{{ $logo }}" alt="Bloomery"></div>
        <div class="kop-center">
            <div class="company-name">PT Bloomery Sekawan Sejahtera</div>
            <div class="title-memo">Memo Internal {{ $brandLabel ?? 'Brand Belum Ditentukan' }}</div>
            <div class="memo-label">{{ $memo->title }}</div>
        </div>
        <div class="kop-right">
            <div class="kop-right-row">
                <div class="kop-right-label">Nomor Memo</div>
                <div class="kop-right-value">{{ $memo->memo_number }}</div>
            </div>
            <div class="kop-right-row">
                <div class="kop-right-label">Periode</div>
                <div class="kop-right-value">{{ $memo->period_month->translatedFormat('F Y') }}</div>
            </div>
            <div class="kop-right-row">
                <div class="kop-right-label">Tanggal Memo</div>
                <div class="kop-right-value">{{ $memo->memo_date->translatedFormat('d M Y') }}</div>
            </div>
            <div class="kop-right-row">
                <div class="kop-right-label">Revisi</div>
                <div class="kop-right-value">{{ $memo->revision }}</div>
            </div>
        </div>
    </div>

    <table class="identity">
        <tr><td class="label">Kepada</td><td>{{ $memo->recipient ?: '-' }}</td></tr>
        <tr><td class="label">Dari</td><td>{{ $memo->sender ?: '-' }}</td></tr>
        <tr><td class="label">Perihal</td><td>{{ $memo->subject ?: $memo->title }}</td></tr>
    </table>

    <div class="section-divider">Daftar Menu yang Akan Rilis ({{ $menus->count() }} Menu)</div>
    <table class="menu-table">
        <thead>
            <tr>
                <th class="number-column">No</th>
                <th>Menu</th>
                <th>Category</th>
                <th>Category Detail</th>
                <th>Tanggal Rilis</th>
            </tr>
        </thead>
        <tbody>
            @forelse($menus as $index => $menu)
                <tr>
                    <td class="number-column">{{ $index + 1 }}</td>
                    <td>
                        <div>{{ $menu['name'] }}</div>
                        <div class="menu-code">{{ $menu['code'] }}</div>
                    </td>
                    <td>{{ $menu['category'] ?? '-' }}</td>
                    <td>{{ $menu['category_detail'] ?? '-' }}</td>
                    <td>{{ $menu['release_date']?->translatedFormat('d M Y') ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Belum ada Menu pada Memo ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(filled($memo->notes))
        <div class="notes">
            <div class="notes-heading">Catatan</div>
            <div class="notes-content">{{ $memo->notes }}</div>
        </div>
    @endif

    <div class="footer">
        <table class="footer-meta">
            <tr>
                <td>
                    <div class="footer-label">Generated By</div>
                    <div class="footer-value">Bloomery R&amp;D System</div>
                </td>
                <td>
                    <div class="footer-label">Document</div>
                    <div class="footer-value">Memo Internal · {{ $memo->memo_number }}</div>
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
</body>
</html>
