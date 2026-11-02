# Project Overview Rich Text

| Atribut | Nilai |
|---|---|
| Domain | Research & Development |
| Status | implemented |
| Owner | Research & Development |
| Technical owner | Information Technology |
| Modul terdampak | Back Office → Research & Development → Project → Detail Project |
| Target release | TBD |

## Ringkasan

Fitur ini menambahkan **Project Overview** rich-text pada detail Project R&D untuk tujuan, ruang lingkup, konsep, referensi, catatan, daftar, tautan, dan gambar. Overview menggunakan native Filament RichEditor seperti Informasi Tambahan BOM, disimpan terpisah dari `description` plain text, disanitasi server-side, dan memakai attachment private B2/R2. Fitur berlaku untuk Project Release Product, Premix, dan Partnership.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | implemented | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | implemented | Urutan teknis, migration, security, test, dan quality gate |
| [Rollout runbook](rollout.md) | draft | Migration, UAT, monitoring, dan rollback aman; belum dijalankan |

## Keputusan

| Keputusan | Status |
|---|---|
| `description` tetap plain-text untuk ringkasan/list/search | Disetujui dalam planning 2 November 2026 |
| Overview disimpan pada field additive `overview_html` | Disetujui dalam planning 2 November 2026 |
| Editor memakai Filament RichEditor/TipTap existing, bukan dependency Quill baru | Disetujui dalam planning 2 November 2026 |
| Overview diedit setelah Project tersimpan agar attachment mempunyai Project ID | Disetujui dalam planning 2 November 2026 |

## Laporan

| Laporan | Tanggal |
|---|---|
| [Laporan implementasi](reports/implementation-report.md) | 2 November 2026 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-02 | draft | Paket planning dibuat berdasarkan audit Project dan rich-text BOM; belum ada perubahan aplikasi |
| 2026-11-02 | implemented | Overview diimplementasikan; UAT browser dan rollout belum dijalankan |
