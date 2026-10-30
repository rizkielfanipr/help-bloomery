---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-INDEX
title: Receiving Terpusat BLSS
status: draft
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-30
updated_at: 2026-10-30
target_release: TBD
related_documents:
  - docs/products/inventory/README.md
  - docs/products/inventory/receiving-simplification/prd.md
supersedes: null
---

# Receiving Terpusat BLSS

Paket remediasi untuk menghapus kewajiban mapping branch lokal pada proses Receiving yang dijalankan Inventory Pusat. Scope hanya Purchase Order company `BLSS`. Dokumen ini belum mengubah kode aplikasi, database, konfigurasi, atau deployment.

## Status

| Dokumen | Status | Keterangan |
|---|---|---|
| [Audit](reports/audit-report.md) | Selesai untuk baseline dokumentasi | Audit statis dan baseline test lokal; tidak memeriksa data production |
| [PRD remediasi](prd.md) | Draft | Menunggu review owner sebelum implementasi |
| [Implementation plan](implementation-plan.md) | Draft | Urutan teknis dan quality gate |
| [Implementation prompt](implementation-prompt.md) | Draft | Instruksi siap pakai untuk agent implementasi |
| [Rollout runbook](rollout.md) | Draft | UAT, observasi, dan rollback setelah implementasi |

## Keputusan bisnis yang sudah dikonfirmasi

- Receiving dilaksanakan Inventory Pusat.
- Permission `access employee app goods receipt` mengizinkan penerimaan PO dari seluruh branch ESB pada company `BLSS`.
- Mapping branch PO ESB ke branch lokal tidak menjadi prasyarat Receiving.
- Company selain `BLSS`, termasuk SPN, berada di luar scope remediasi ini.

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-10-30 | draft | Paket planning dibuat ulang mengikuti template proyek; belum ada perubahan kode |
