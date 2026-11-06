---
document_id: RND-BOM-PDF-CONSISTENCY-REMEDIATION-PRD
title: PRD Remediasi - Konsistensi Export BOM PDF
status: implemented
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-06
updated_at: 2026-11-06
target_release: TBD
related_documents:
  - docs/products/rnd/README.md
  - docs/standards/code-remediation.md
supersedes: null
---

# PRD Remediasi - Konsistensi Export BOM PDF

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Research & Development |
| Scope | Export BOM PDF pada halaman Project |
| Status | Implemented |
| Audit acuan | Audit kode pada branch `fix/rnd-bom-pdf-consistency` |

## 2. Ringkasan

Export Store PDF saat ini tetap dapat dipanggil ketika Project belum memiliki BOM Menu sehingga route berakhir pada halaman error 422. Dokumen gabungan multi-produk juga memakai nomor dokumen produk pertama pada header dan tidak menyebut produk asal pada tabel Sales Projection. Remediasi membuat ketersediaan export terlihat sebelum aksi dijalankan serta menyatukan identitas dan struktur data dokumen Project.

## 3. Baseline dan bukti

- `resources/views/filament/helpdesk/rnd-projects/view.blade.php` menampilkan tombol berdasarkan permission saja.
- `app/Filament/Helpdesk/Resources/Projects/Pages/ViewProject.php` langsung membuka export Store meskipun koleksi eligible kosong.
- `app/Http/Controllers/Helpdesk/RndProjectBomPdfController.php` baru menolak koleksi kosong setelah request PDF masuk.
- `resources/views/exports/rnd-product-bom-pdf.blade.php` menerima nomor dokumen per produk meskipun output-nya merupakan dokumen Project.
- `resources/views/exports/partials/rnd-project-sales-projection.blade.php` tidak menunjukkan nama produk pada baris proyeksi multi-produk.

## 4. Temuan

| ID | Temuan | Severity | Bukti | Dampak |
|---|---|---|---|---|
| R-1 | Export Store aktif saat tidak ada BOM Menu | Sedang | `ViewProject::openProjectBomExport()` | Pengguna melihat halaman error 422 |
| R-2 | Identitas header dan footer dokumen Project berbeda | Sedang | `RndProjectBomPdfController::renderDocument()` | SOP tampak tidak sinkron |
| R-3 | Sales Projection tidak memiliki konteks produk | Sedang | partial Sales Projection | Baris multi-produk ambigu |
| R-4 | Pemisahan halaman dan header tabel belum cukup defensif untuk konten panjang | Rendah | template BOM PDF | Tabel dapat tampak kurang rapi |

## 5. Goals dan non-goals

- **Goals:** empty state export yang jelas; route tetap menjaga validasi; satu identitas dokumen Project; tabel multi-produk eksplisit; layout cetak stabil dan mudah dibaca.
- **Non-goals:** mengubah permission, model data, isi BOM ESB, alur rilis SOP, atau membuat migration.

## 6. Perilaku yang wajib dipertahankan

- Permission export Kitchen dan Store tetap terpisah.
- Preview dan download memakai route serta data export yang sama.
- Store hanya memuat BOM dengan `usage_type=menu`; Kitchen memuat BOM non-menu yang dipilih.
- Direct request tanpa BOM tetap ditolak server dengan status 422.
- PDF tetap A4 portrait, memiliki page number, dan dapat dirilis sebagai SOP Store.

## 7. Target perilaku

| ID temuan | Perilaku target | Acceptance criteria |
|---|---|---|
| R-1 | Tombol Store disabled saat kosong dan aksi Livewire memberi notifikasi | Tidak ada navigasi/iframe 422; pesan menyuruh pengguna menambahkan BOM Store |
| R-2 | Header dan footer memakai nomor dokumen Project yang sama | Nomor `BOM-{SCOPE}-PROJECT-{SLUG}` tampil konsisten |
| R-3 | Setiap proyeksi menunjukkan produk asal | Kolom Produk tampil pada tabel Sales Projection |
| R-4 | Elemen penting tidak pecah secara acak | Heading menempel pada tabel, header tabel berulang, baris tidak terpotong |

## 8. Data existing dan migrasi

Tidak ada migrasi atau backfill. PDF dibuat on-demand dari relasi dan snapshot BOM yang sudah ada.

## 9. Authorization dan security

Tidak ada permission baru. Controller tetap memeriksa `view bill of materials` dan permission export per scope.

## 10. Testing strategy

- Regression test Project tanpa Store BOM: tombol disabled, pesan tampil, dan aksi tidak membuat preview.
- Regression test direct route kosong tetap 422.
- Test HTML dokumen gabungan: identitas Project konsisten dan Sales Projection memiliki konteks produk.
- Render PDF fixture multi-produk dan inspeksi visual seluruh halaman.

## 11. Phase remediasi

| Phase | Temuan | Output | Prasyarat |
|---|---|---|---|
| 1 | R-1 sampai R-4 | UI guard, konsistensi data, layout PDF, test, dokumentasi | Tidak ada |

## 12. Rollout dan rollback

Deploy mengikuti pipeline aplikasi tanpa migration atau environment baru. Rollback dilakukan dengan revert commit apabila preview/export mengalami regresi.

## 13. Risiko

| Risiko | Mitigasi |
|---|---|
| Perubahan layout membuat halaman bertambah | Render fixture multi-produk dan periksa keterbacaan, bukan mengejar jumlah halaman tetap |
| Data berubah di antara render preview dan download | Preview dan download tetap memakai builder dan route yang sama; perubahan data memang menghasilkan dokumen terbaru |

## 14. Open decisions

Tidak ada keputusan terbuka yang menghalangi implementasi.

## 15. Definition of Done

- [x] Setiap temuan memiliki test regresi.
- [x] Perilaku yang wajib dipertahankan tetap lulus characterization test.
- [x] PDF hasil fixture multi-produk lulus inspeksi visual.
- [x] README domain dan peta kode diperbarui.
