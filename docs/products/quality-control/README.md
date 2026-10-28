# Quality Control

Audit QC store berbasis checklist dan Item Journal yang dikirim ke ESB. Pemeriksaan kualitas barang masuk (QC inbound) dilakukan di Receiving — lihat [Inventory](../inventory/README.md#receiving). Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Quality Control | Audit QC | `view quality control audits` (+ create/edit) |
| | Checklist QC | `view quality control checklists` (+ create/edit/delete) |
| | Item Journal | `view quality control item journals`; semua user: `view all quality control item journals` |
| Employee App | Tile **Quality Control** | `access employee app quality control` |
| Employee App | Halaman Item Journal | `view/create/submit quality control item journals`, `delete quality control item journal attachments` |

## Audit QC

1. Auditor memulai audit di Employee App: store/branch, tanggal audit, kehadiran dan nama store leader.
2. Auditor mengisi setiap poin checklist: hasil, poin, catatan, dan foto bukti dari kamera. Poin bisa ditandai critical.
3. Auditor mengisi ringkasan: **Top 3 Findings**, kebutuhan corrective action, dan catatan keseluruhan, lalu submit.
4. Skor = poin diperoleh ÷ poin maksimal × 100. Rating: **green** ≥ 85, **yellow** ≥ 65, **red** di bawahnya.
5. Riwayat audit tersedia di Employee App. Back office menampilkan audit dan item per section beserta status, skor, dan flag critical.

**Checklist QC** adalah master poin pemeriksaan (section, poin, bobot) yang dipakai audit.

## Item Journal

- QC membuat Item Journal untuk company, branch, dan lokasi ESB: memilih tujuan (purpose), menambah produk dari picker ESB, mengisi jumlah, dan melampirkan file.
- Submit membuat Item Journal di ESB Core (`EsbItemJournalService::create`) lalu mengunggah lampiran. Lampiran yang gagal dapat dicoba ulang atau dihapus.
- Back office menampilkan journal milik user, atau semua journal untuk `view all quality control item journals`.

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/QualityControl*/` |
| Employee App | `app/Filament/Casual/Pages/{QualityControlAudits,QualityControlAuditDetail,QualityControlAuditHistory,ItemJournalPage}.php` |
| Logika | `app/Services/EsbItemJournalService.php` |
| Model | `app/Models/QualityControl*.php` |
| Test | `tests/Feature/QualityControl*Test.php` |

## Data

`QualityControlAudit`, `QualityControlAuditItem`, `QualityControlChecklistItem`, `QualityControlItemJournal`, `QualityControlItemJournalDetail`, `QualityControlItemJournalAttachment`. Lihat [Data Model](../../architecture/data-model.md#quality-control).

## Role bawaan

`QUALITY_CONTROL` melakukan audit dan Item Journal. `QUALITY_CONTROL_SUPERVISOR` melihat semua audit dan journal serta mengelola Checklist QC.

## Perubahan terencana

Item Journal masih berada pada Quality Control sesuai kondisi kode saat ini. Rencana pemindahan user-facing ke Inventory, tile Employee App mandiri, dan input attachment camera-first didokumentasikan pada [PRD Item Journal Inventory](../inventory/item-journal/prd.md).
