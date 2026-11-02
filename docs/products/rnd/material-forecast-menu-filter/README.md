# Filter Menu Material Forecast

| Atribut | Nilai |
|---|---|
| Domain | Research & Development |
| Status | implemented |
| Owner | Research & Development |
| Technical owner | Information Technology |
| Modul terdampak | Back Office → Research & Development → Project → Material Forecast |
| Target release | TBD |

## Ringkasan

Fitur ini menambahkan scope menu pada Material Forecast di detail Project R&D. User dapat memakai **All Menu** sebagai default, memilih satu menu, atau memilih beberapa menu; hasil Kitchen, Store, ringkasan, warning, cara hitung, dan export Excel mengikuti pilihan yang sama. Filter bersifat read-only terhadap data bisnis dan tidak mengubah Sales Projection, BOM, persentase forecast, maupun data Project.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | implemented | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | implemented | Urutan teknis, file terdampak, test, dan quality gate |

Rollout runbook terpisah tidak dibuat karena fitur tidak memerlukan migration, environment variable, permission baru, queue/scheduler, integrasi baru, atau langkah manual server.

## Keputusan

| Keputusan | Status |
|---|---|
| All Menu menjadi default dan menjaga hasil existing | Disetujui 2 November 2026 |
| Filter mendukung single dan multiple menu | Disetujui 2 November 2026 |
| Seluruh periode, region, channel, dan branch projection dari menu terpilih tetap dijumlahkan | Disetujui 2 November 2026 |
| Filter berlaku pada tampilan Kitchen/Store dan export Excel | Disetujui 2 November 2026 |

## Laporan

| Laporan | Tanggal |
|---|---|
| [Laporan implementasi](reports/implementation-report.md) | 2 November 2026 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-02 | draft | Paket planning dibuat berdasarkan audit kode existing; belum ada perubahan aplikasi |
| 2026-11-02 | implemented | Filter diimplementasikan; pemeriksaan UI di browser menjadi bagian UAT staging |
