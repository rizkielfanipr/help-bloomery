# Quality Control

Audit QC store berbasis checklist. Pemeriksaan kualitas barang masuk (QC inbound) dilakukan di Receiving dan Item Journal (penyesuaian stok ESB) dimiliki Inventory — lihat [Inventory](../inventory/README.md#item-journal). Kondisi per 28 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Quality Control | Audit QC | `view quality control audits` (+ create/edit) |
| | Checklist QC | `view quality control checklists` (+ create/edit/delete) |
| Employee App | Tile **Quality Control** | `access employee app quality control` |

## Audit QC

1. Auditor memulai audit di Employee App: store/branch, tanggal audit, kehadiran dan nama store leader.
2. Auditor mengisi setiap poin checklist: hasil, poin, catatan, dan foto bukti dari kamera. Poin bisa ditandai critical.
3. Auditor mengisi ringkasan: **Top 3 Findings**, kebutuhan corrective action, dan catatan keseluruhan, lalu submit.
4. Skor = poin diperoleh ÷ poin maksimal × 100. Rating: **green** ≥ 85, **yellow** ≥ 65, **red** di bawahnya.
5. Riwayat audit tersedia di Employee App (bottom navigation **Audit**). Back office menampilkan audit dan item per section beserta status, skor, dan flag critical.

**Checklist QC** adalah master poin pemeriksaan (section, poin, bobot) yang dipakai audit.

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{QualityControlAudits,QualityControlChecklistItems}/` |
| Employee App | `app/Filament/Casual/Pages/{QualityControlAudits,QualityControlAuditDetail,QualityControlAuditHistory}.php`, `resources/views/components/quality-control/bottom-nav.blade.php` |
| Model | `app/Models/{QualityControlAudit,QualityControlChecklistItem}*.php` |
| Test | `tests/Feature/{QualityControlAudit,QualityControlApp}*Test.php` |

## Data

`QualityControlAudit`, `QualityControlAuditItem`, `QualityControlChecklistItem`. Lihat [Data Model](../../architecture/data-model.md#quality-control). Model `QualityControlItemJournal*` tercatat di bagian yang sama karena nama legacy, tetapi dimiliki [Inventory](../inventory/README.md#item-journal).

## Role bawaan

`QUALITY_CONTROL` melakukan audit. `QUALITY_CONTROL_SUPERVISOR` melihat semua audit dan mengelola Checklist QC. Akses Item Journal kedua role dijelaskan di [Inventory](../inventory/README.md#role-bawaan).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [PRD Item Journal Inventory](../inventory/item-journal/prd.md) | Implemented — Item Journal dipindahkan dari Quality Control ke Inventory |
