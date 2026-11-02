# Penerimaan Tanpa PO / PO Susulan

| Atribut | Nilai |
|---|---|
| Domain | Inventory dan Purchasing |
| Status | draft |
| Owner | Inventory |
| Technical owner | Information Technology |
| Modul terdampak | Employee App Receiving, Back Office Purchasing, Goods Receipt ESB |
| Target release | TBD |

## Ringkasan

Workflow exception untuk mencatat barang yang sudah datang tanpa Purchase Order. Inventory menyimpan penerimaan dan QC secara lokal melalui tile **Receiving**, Purchasing membuat PO secara manual di ESB melalui submenu **PO Susulan**, lalu aplikasi memverifikasi item PO dan memproses Goods Receipt secara idempotent.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | draft | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | draft | Urutan teknis dan quality gate |
| [Implementation prompt](implementation-prompt.md) | draft | Instruksi siap pakai untuk agent implementasi |
| [Rollout runbook](rollout.md) | draft | Migration, UAT, rollback, dan monitoring |

## Keputusan

| Keputusan | Status |
|---|---|
| Entry tetap melalui tile **Receiving**, tanpa tile baru | Disepakati |
| Submenu Purchasing bernama **PO Susulan** | Disepakati |
| Supplier awal berupa teks dan supplier PO menjadi data authoritative | Disepakati |
| Produk dipilih dari Master Product melalui modal seperti Tambah Komponen BOM | Disepakati |
| Matching memakai Product Detail ID dan UOM dari item PO | Disepakati |
| Purchasing membuat PO manual di ESB pada MVP | Disepakati |
| **Verifikasi PO & Proses GR** memproses GR setelah validasi lulus | Disepakati |

## Laporan

| Laporan | Tanggal |
|---|---|
| Belum ada — implementasi belum dimulai | — |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-02 | draft | Planning dibuat berdasarkan audit Receiving dan Purchasing existing |
