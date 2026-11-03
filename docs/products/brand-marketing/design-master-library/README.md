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

Master Design Library adalah penyimpanan file desain berdasarkan Brand. Designer menambah item beserta satu file desain privat dan mengisi spesifikasi produksi secara terpisah, tanpa alur persetujuan; Purchasing melihat bundle aktif serta mengunduh file desain tanpa dapat mengubah data. Upload marketing material Project R&D dan fulfillment fisik existing tetap berjalan seperti sekarang sampai fase integrasi tersendiri disetujui.

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
| Purchasing hanya membaca bundle aktif dan mengunduh file desain | Dikunci dalam PRD |
| Satu file desain per item tanpa jenis file; satu permission unduh | Dikunci dalam PRD v2.1 |
| Tanpa version, review, dan publish; fitur berupa penyimpanan file | Dikunci dalam PRD v2.0 |
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
| 2026-11-03 | implemented | PRD v2.4: card Audit Activity tidak ditampilkan; audit tetap dicatat |
| 2026-11-03 | implemented | PRD v2.3: index memakai pola list Project R&D |
| 2026-11-03 | implemented | PRD v2.2: tombol Hapus menghapus item permanen; tombol Unduh selalu tampil; Unggah File untuk item tanpa file |
| 2026-11-03 | implemented | PRD v2.1: jenis file dan card Design Files dihapus; file diunggah saat Tambah Item; spesifikasi diisi terpisah |
| 2026-11-03 | implemented | PRD v2.0: version, revisi, review, publish, dan notifikasi dihapus; fitur menjadi penyimpanan file |
| 2026-11-03 | implemented | Periode berlaku dan input ringkasan Version 1 dihapus dari form pembuatan bundle |
| 2026-11-03 | implemented | Implementasi selesai pada branch fitur; UAT staging dan rollout belum dijalankan |
| 2026-11-03 | draft | Spesifikasi UI detail diperjelas menjadi summary dan section card mengikuti pola Project Detail R&D; index tetap list/table |
| 2026-11-02 | draft | Planning dibuat berdasarkan audit Brand Marketing, Project R&D, Purchasing, Brand, permission, dan penyimpanan file existing |
