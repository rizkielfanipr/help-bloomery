---
document_id: RND-BOM-PDF-CONSISTENCY-REMEDIATION-PRD
title: PRD Remediasi - Konsistensi Export BOM PDF
status: implemented
version: 1.1
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

Export Store PDF harus mencerminkan semua menu yang sudah diinput pada Project, bukan hanya menu yang kebetulan sudah memiliki BOM. Setiap menu menjadi satu unit dokumen dengan urutan Detail Produk, Sales Projection, lalu BOM Store. Jika BOM belum diinput, menu dan proyeksinya tetap tampil dengan keterangan eksplisit tanpa membuat tabel BOM kosong atau menghilangkan menu dari dokumen.

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
| R-5 | Sales Projection dipisahkan sebagai ringkasan global | Sedang | `rnd-project-sales-projection.blade.php` | Sulit mencocokkan projection, detail, dan BOM menu yang sama |
| R-6 | Filter Project Store membuang menu tanpa BOM | Tinggi | `RndProjectBomPdfController::renderDocument()` | PDF tidak merepresentasikan semua menu yang sudah diinput |

## 5. Goals dan non-goals

- **Goals:** satu alur data per menu; semua menu Project tercermin; empty state BOM berada di dalam menu terkait; satu identitas dokumen Project; layout cetak stabil dan mudah dibaca.
- **Non-goals:** mengubah permission, model data, isi BOM ESB, alur rilis SOP, atau membuat migration.

## 6. Perilaku yang wajib dipertahankan

- Permission export Kitchen dan Store tetap terpisah.
- Preview dan download memakai route serta data export yang sama.
- Store memuat seluruh menu Project; hanya BOM dengan `usage_type=menu` yang boleh tampil pada bagian BOM Store.
- Project tanpa menu tetap ditolak server dengan status 422; Project yang memiliki menu tetapi belum memiliki BOM Store tetap dapat menghasilkan PDF.
- PDF tetap A4 portrait, memiliki page number, dan dapat dirilis sebagai SOP Store.

## 7. Target perilaku

| ID temuan | Perilaku target | Acceptance criteria |
|---|---|---|
| R-1 | Tombol Store disabled hanya jika Project belum memiliki menu | Menu tanpa BOM tetap dapat diekspor dan mendapat keterangan BOM belum diinput |
| R-2 | Header dan footer memakai nomor dokumen Project yang sama | Nomor `BOM-{SCOPE}-PROJECT-{SLUG}` tampil konsisten |
| R-3, R-5 | Sales Projection berada di blok menu pemiliknya | Urutan per menu adalah Detail Produk, Sales Projection, lalu BOM Store |
| R-4 | Elemen penting tidak pecah secara acak | Heading menempel pada tabel, header tabel berulang, baris tidak terpotong |
| R-6 | Menu tanpa BOM tidak hilang | Detail dan projection tetap tampil; bagian BOM diganti empty state yang eksplisit |

## 8. Data existing dan migrasi

Tidak ada migrasi atau backfill. PDF dibuat on-demand dari relasi dan snapshot BOM yang sudah ada.

## 9. Authorization dan security

Tidak ada permission baru. Controller tetap memeriksa `view bill of materials` dan permission export per scope.

## 10. Testing strategy

- Regression test Project tanpa menu: tombol disabled, pesan tampil, dan aksi tidak membuat preview.
- Regression test menu tanpa BOM: Store PDF berhasil dan menampilkan keterangan BOM belum diinput.
- Regression test direct route Project tanpa menu tetap 422.
- Test HTML dokumen gabungan: identitas Project konsisten dan Sales Projection memiliki konteks produk.
- Render PDF fixture multi-produk dan inspeksi visual seluruh halaman.

## 11. Phase remediasi

| Phase | Temuan | Output | Prasyarat |
|---|---|---|---|
| 1 | R-1 sampai R-4 | UI guard, konsistensi data, layout PDF, test, dokumentasi | Tidak ada |
| 2 | R-5 dan R-6 | Struktur dokumen per menu dan fallback BOM kosong | Phase 1 |

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
- [x] PDF hasil fixture multi-produk dan menu tanpa BOM lulus inspeksi visual.
- [x] README domain dan peta kode diperbarui.
