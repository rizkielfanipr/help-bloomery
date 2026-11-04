# Vendor Compliance — Keterlambatan Delivery PO

| Atribut | Nilai |
|---|---|
| Domain | Purchasing |
| Status | implemented; rollout dan UAT staging belum dijalankan |
| Owner | Purchasing |
| Technical owner | Information Technology |
| Modul terdampak | Purchasing, Inventory Receiving |
| Target release | TBD |

## Ringkasan

Fitur ini mendeteksi otomatis keterlambatan vendor ketika penerimaan PO normal dicatat setelah `requiredDate` PO. Receiving tetap dapat menyimpan QC dan memproses GR; sistem membuat kandidat insiden **Late Delivery** secara idempotent untuk diverifikasi Purchasing sebelum poin demerit diberlakukan. PO Susulan tidak dinilai karena PO dibuat setelah barang fisik diterima.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | implemented | Sumber requirement canonical; keputusan final di §26.1–26.2 |
| [Implementation plan](implementation-plan.md) | implemented | Urutan teknis, migrasi, dan quality gate |
| [Rollout](rollout.md) | draft | Deployment, UAT, monitoring, dan rollback |

## Keputusan

| ADR | Status |
|---|---|
| Belum diperlukan; keputusan bisnis dan teknis dicatat pada PRD §26.1–26.2 | Ditutup 2026-11-03 |

## Laporan

| Laporan | Tanggal |
|---|---|
| [Laporan implementasi](reports/implementation-report.md) | 2026-11-03 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-03 | implemented | Implementasi selesai pada branch fitur; UAT staging dan rollout belum dijalankan |
| 2026-11-03 | draft | Planning awal dibuat dari audit Receiving dan Vendor Compliance existing |
