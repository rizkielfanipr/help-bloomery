---
document_id: INVENTORY-NON-PO-RECEIVING-ROLLOUT
title: Rollout Runbook — Penerimaan Tanpa PO dan PO Susulan
status: in-development
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/inventory/non-po-receiving/prd.md
  - docs/products/inventory/non-po-receiving/implementation-plan.md
supersedes: null
---

# Rollout Runbook — Penerimaan Tanpa PO dan PO Susulan

## 1. Tujuan

Menjalankan migration additive, permission sync, UAT Inventory/Purchasing, dan observasi awal tanpa mengganggu Receiving dengan PO existing.

## 2. Prasyarat

- PR implementation direview dan CI hijau.
- Backup database tervalidasi.
- Staging mempunyai koneksi GET PO dan POST GR yang aman untuk UAT.
- Private storage upload dapat dibaca melalui authorization existing.
- Role uji Inventory dan Purchasing tersedia.

## 3. Dampak deployment

| Area | Dampak |
|---|---|
| Migration | Empat tabel baru dan optional source/link additive |
| Permission | Permission non-PO baru; sync dan role assignment |
| Environment | Tidak ada variabel baru |
| Queue/scheduler | Tidak ada pada MVP |
| External | GET PO, cabang, lokasi, Master Product, dan POST GR existing; tidak ada create PO API |
| Storage | Private photos/documents non-PO |

## 4. Pre-deploy checks

1. Catat release application/database aktif dan pastikan backup siap.
2. Review migration, index, FK, unique, dan rollback.
3. Jalankan targeted test dan Feature shards.
4. Verifikasi normal Receiving dengan authorized PO pada staging.
5. Verifikasi Master Product picker, branch/location, dan PO read contract.
6. Rencanakan role assignment; cancel tidak otomatis diberikan kepada semua Purchasing.

## 5. Deploy staging

1. Deploy application release.
2. Jalankan migration.
3. Jalankan permission sync sesuai command repository.
4. Assign create/submit ke Inventory test dan view/process ke Purchasing test.
5. Assign cancel hanya jika disetujui.
6. Clear/rebuild cache sesuai pipeline bila diperlukan.
7. Smoke test route/menu dan migration status.

## 6. UAT staging

### Inventory

- Pastikan mode Dengan PO/Tanpa PO dan permission guard benar.
- Buat draft dengan supplier teks, branch/location, product modal, qty/batch/QC/foto.
- Pastikan duplicate dan tampering product ditolak.
- Submit dan pastikan tidak ada POST GR/stock ESB.
- Verifikasi NPR number, history, status, dan notification Purchasing.
- Uji all-Hold dan all-Rejected.

### Purchasing

- Verifikasi badge/list/detail PO Susulan.
- Uji mulai proses, revision dengan alasan, dan cancel permission.
- Buat PO manual di ESB staging.
- Uji PO salah company/status/branch/product/UOM/outstanding dan pastikan diblokir.
- Uji supplier text mismatch warning.
- Proses PO valid dan pastikan satu GR, satu monitoring transaction, link origin, dan completed.
- Ulangi action/double-click dan pastikan tidak ada duplicate.

### Regression

- Receiving Dengan PO tetap search/select/QC/submit.
- Hold/Rejected normal dan Vendor Compliance tetap berjalan.
- Back Office Receiving existing tetap list/detail/scope.

## 7. Production rollout

1. User menyetujui UAT dan window production.
2. Ambil backup, deploy release, jalankan migration dan permission sync.
3. Assign permission melalui Role & Permission.
4. Smoke test read-only menu dan create draft kecil; jangan membuat GR tanpa transaksi nyata.
5. Pantau error, unknown, constraint, storage, notification, dan Receiving normal.

## 8. Monitoring awal

- Record per status, terutama `awaiting_purchasing`, `gr_failed`, `gr_unknown`.
- Durasi submitted → PO linked/completed.
- Duplicate/idempotency conflict.
- ESB response failure/timeout.
- Upload/authorization failure.
- Error rate Receiving normal.

## 9. Rollback

### Sebelum ada data production

1. Rollback application.
2. Rollback migration setelah memastikan tabel kosong.
3. Cabut assignment permission bila perlu.

### Setelah ada data production

1. Nonaktifkan menu/action melalui rollback app atau permission removal.
2. Jangan drop tabel/record.
3. Catat seluruh record non-terminal dan manual reconcile.
4. Deploy forward fix dan UAT ulang sebelum re-enable.

### External unknown

- Jangan retry otomatis `gr_unknown`.
- Periksa ESB/System Monitoring dengan idempotency/internal number.
- Jika GR sudah ada, tautkan melalui reconciliation; jika belum, retry hanya melalui prosedur eksplisit yang diaudit.

## 10. Exit criteria

- Migration/permission benar.
- UAT Inventory/Purchasing/regression lulus.
- Tidak ada duplicate GR atau unauthorized action.
- Tidak ada peningkatan error Receiving normal.
- Implementation report mencatat hasil aktual dan follow-up.
