# Master Design Library

| Atribut | Nilai |
|---|---|
| Domain | Brand Marketing |
| Status | implemented; rollout dan UAT staging belum dijalankan |
| Owner | Brand Marketing |
| Technical owner | Information Technology |
| Modul terdampak | Brand Marketing; Purchasing (akses baca/unduh) |
| Target release | TBD |

## Ringkasan

Master Design Library adalah katalog desain reusable berdasarkan Brand. Designer mengelola bundle, revisi, item, spesifikasi, dan file privat; Purchasing membaca versi published serta mengunduh file production tanpa dapat mengubah master. Upload marketing material Project R&D dan fulfillment fisik existing tetap berjalan seperti sekarang sampai fase integrasi tersendiri disetujui.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | implemented | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | implemented | Urutan teknis, migration, file lifecycle, dan quality gate |
| [Rollout](rollout.md) | draft | Deployment, permission, UAT, monitoring, dan rollback |

## Keputusan

| Keputusan | Status |
|---|---|
| Satu bundle dimiliki tepat satu Brand | Dikunci dalam PRD |
| Master Design dimiliki domain Brand Marketing | Dikunci dalam PRD |
| Purchasing hanya membaca published version dan mengunduh file production | Dikunci dalam PRD |
| Published version immutable; perubahan membuat revisi baru | Dikunci dalam PRD |
| File master disimpan privat dan tidak ditimpa | Dikunci dalam PRD |
| Index memakai list/table; detail memakai section card seperti Project Detail R&D | Dikunci dalam PRD |
| Integrasi Project R&D/fulfillment bukan scope versi pertama | Dikunci dalam PRD |
| Batas upload 12 MB per file; source hanya ZIP | Dikunci pada Phase 0 |

## Laporan

| Laporan | Tanggal |
|---|---|
| [Laporan implementasi](reports/implementation-report.md) | 2026-11-03 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-03 | implemented | Periode berlaku dan input ringkasan Version 1 dihapus dari form pembuatan bundle |
| 2026-11-03 | implemented | Implementasi selesai pada branch fitur; UAT staging dan rollout belum dijalankan |
| 2026-11-03 | draft | Spesifikasi UI detail diperjelas menjadi summary dan section card mengikuti pola Project Detail R&D; index tetap list/table |
| 2026-11-02 | draft | Planning dibuat berdasarkan audit Brand Marketing, Project R&D, Purchasing, Brand, permission, dan penyimpanan file existing |
