---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-INDEX
title: Receiving Terpusat BLSS
status: in-development
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

Paket remediasi untuk menghapus kewajiban mapping branch lokal pada proses Receiving yang dijalankan Inventory Pusat. Scope hanya Purchase Order company `BLSS`. Kode diimplementasikan tanpa migration, permission, env, atau dependency baru; deployment menunggu persetujuan pengguna.

## Status

| Dokumen | Status | Keterangan |
|---|---|---|
| [Audit](reports/audit-report.md) | Selesai untuk baseline dokumentasi | Audit statis dan baseline test lokal; tidak memeriksa data production |
| [PRD remediasi](prd.md) | In development | Kode selesai; menunggu review PR, UAT staging, dan production |
| [Implementation plan](implementation-plan.md) | In development | Phase 0–3 dikerjakan dalam satu PR |
| [Laporan implementasi](reports/implementation-report.md) | In review | Hasil phase, keputusan, deviasi, dan test aktual |
| [Rollout runbook](rollout.md) | In review | UAT, observasi, dan rollback setelah merge |

## Keputusan bisnis yang sudah dikonfirmasi

- Receiving dilaksanakan Inventory Pusat.
- Permission `access employee app goods receipt` mengizinkan penerimaan PO dari seluruh branch ESB pada company `BLSS`.
- Mapping branch PO ESB ke branch lokal tidak menjadi prasyarat Receiving.
- Company selain `BLSS`, termasuk SPN, berada di luar scope remediasi ini.

## Uji lokal (sandbox)

Untuk mencoba Receiving di mesin developer tanpa membuat Goods Receipt nyata:

1. Set `APP_ENV=local` dan `ESB_RECEIVING_SANDBOX=true` di `.env`. Sandbox diabaikan di environment selain `local`.
2. Jalankan `php artisan db:seed --class=ReceivingSandboxSeeder`. Jalankan ulang kapan saja untuk menghapus semua receipt `PO-DUMMY-*` dan mengembalikan qty outstanding.
3. Login Employee App sebagai `receiving.pusat` (password `password`). User ini hanya memiliki permission Receiving tanpa akses cabang PO. Back Office: `receiving.viewer` (semua cabang) atau `receiving.purchasing` (menerima notifikasi rejection).

Saat sandbox aktif, `SandboxEsbGoodsReceiptService` menggantikan `EsbGoodsReceiptService`, sehingga tidak ada request ke ESB. Disk `b2` diarahkan ke `storage/app/private/receiving-sandbox`, sehingga foto tidak masuk bucket bersama; fitur lain yang memakai `b2` ikut memakai disk lokal ini selama sandbox aktif.

| PO dummy | Cabang ESB | Skenario |
|---|---|---|
| `PO-DUMMY-001` | Dummy Kemang (tanpa mapping) | Penerimaan normal, 3 item |
| `PO-DUMMY-002` | Dummy Senopati (tanpa mapping), status Receiving | Uji Hold/Rejected/partial |
| `PO-DUMMY-003` | Dummy Cabang Termapping | `local_branch_id` terisi dari mapping |
| `PO-DUMMY-FAIL` | Dummy Kemang | ESB menolak → `failed` |
| `PO-DUMMY-TIMEOUT` | Dummy Senopati | Koneksi terputus → `unknown` |
| `PO-DUMMY-MOVED` | Dummy Kemang → Senopati saat submit | Pesan "Konteks PO berubah" |
| `PO-DUMMY-CLOSED` | Dummy Senopati, status Closed saat submit | Submit ditolak (422) |

Skenario MOVED/CLOSED berlaku sekali per pemuatan daftar PO; muat ulang halaman untuk mengulang.

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-10-30 | draft | Paket planning dibuat ulang mengikuti template proyek; belum ada perubahan kode |
| 2026-10-30 | in-development | Implementasi di `fix/receiving-central-blss`; prompt implementasi dihapus |
