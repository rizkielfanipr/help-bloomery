# Vendor Compliance — Keterlambatan Delivery PO

| Atribut | Nilai |
|---|---|
| Domain | Purchasing |
| Status | draft |
| Owner | Purchasing |
| Technical owner | Information Technology |
| Modul terdampak | Purchasing, Inventory Receiving |
| Target release | TBD |

## Ringkasan

Fitur ini merencanakan deteksi otomatis keterlambatan vendor ketika penerimaan PO normal dicatat setelah `requiredDate` PO. Receiving tetap dapat menyimpan QC dan memproses GR; sistem membuat kandidat insiden **Late Delivery** secara idempotent untuk diverifikasi Purchasing sebelum poin demerit diberlakukan. PO Susulan tidak dinilai karena PO dibuat setelah barang fisik diterima.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | draft | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | draft | Urutan teknis, migrasi, dan quality gate |
| [Implementation prompt](implementation-prompt.md) | draft | Prompt lengkap untuk agent implementasi |
| [Rollout](rollout.md) | draft | Deployment, UAT, monitoring, dan rollback |

## Keputusan

| ADR | Status |
|---|---|
| Belum diperlukan; keputusan bisnis terbuka dicatat pada PRD §26 | — |

## Laporan

| Laporan | Tanggal |
|---|---|
| — | — |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-03 | draft | Planning awal dibuat dari audit Receiving dan Vendor Compliance existing |
