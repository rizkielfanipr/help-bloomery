---
document_id: INVENTORY-NON-PO-RECEIVING-PROMPT
title: Prompt Implementasi — Penerimaan Tanpa PO dan PO Susulan
status: draft
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/inventory/non-po-receiving/prd.md
  - docs/products/inventory/non-po-receiving/implementation-plan.md
  - docs/products/inventory/non-po-receiving/rollout.md
supersedes: null
---

# Prompt Implementasi — Penerimaan Tanpa PO dan PO Susulan

```text
Implementasikan Penerimaan Tanpa PO / PO Susulan sesuai Plan → Branch → Implement → Deliver.

## Wajib dibaca
- AGENTS.md dan .claude/skills/feature-delivery/SKILL.md.
- .claude/skills/laravel-best-practices/SKILL.md beserta rules relevan.
- docs/products/inventory/README.md dan docs/products/purchasing/README.md.
- docs/products/inventory/non-po-receiving/{prd.md,implementation-plan.md,rollout.md}.
- docs/standards/{code-remediation.md,ui-consistency.md}.
- docs/architecture/{data-model.md,panels.md,rbac.md}.

## Branch
Mulai dari main terbaru pada feature/non-po-receiving. Jangan mengubah kode pada main atau docs/non-po-receiving-plan.

## Outcome
- Inventory mencatat barang tanpa PO dari tile Receiving; supplier teks, branch/location ESB, dan Master Product picker.
- Submit lokal membuat antrean PO Susulan tanpa POST GR.
- Purchasing membuat PO manual, memasukkan nomor, dan aplikasi exact-verify.
- Verifikasi PO & Proses GR memproses Accepted qty secara idempotent melalui shared Goods Receipt Action.
- Hold administratif berlaku sampai GR berhasil; Receiving normal tidak berubah.

## Non-scope
Jangan membuat create-PO API, Master Supplier picker, multi-PO, temporary stock/Item Journal, use-before-GR, SLA scheduler, Finance threshold, menggunakan PurchaseRequest/PurchasingRequest, produk bebas teks, atau manual remap ke produk berbeda.

## Tahap 0
1. Jalankan characterization tests Receiving/GoodsReceipt dan catat baseline.
2. Verifikasi kontrak Master Product IDs/UOM, branch/location, GET PO, POST GR, storage, dan permission.
3. Bila kontrak berbeda dari PRD, amend docs dan minta review sebelum perubahan perilaku.

## Domain/schema
- Buat enum/model/factory/migration/policy via Artisan --no-interaction.
- Header, item, batch, status history; index/unique sesuai plan.
- Supplier local text dan supplier PO authoritative terpisah.
- Transition melalui Action + transaction + lockForUpdate + audit.
- Submitted record tidak hard-delete dari UI.

## Inventory UI
- Pertahankan tile Receiving; pilihan Dengan PO/Tanpa PO.
- Reuse QC/batch/expiry/photo existing.
- Product picker memakai UX Tambah Komponen BOM dengan Master Product datasource purchase/inventory.
- Server-side search/pagination; duplicate Product Detail ID + UOM ditolak.
- Draft/submit idempotent dan terotorisasi; submit tidak memanggil POST GR.

## Purchasing UI
- Submenu PO Susulan, title Permintaan PO Susulan dari Receiving, badge pending.
- Aksi: Mulai Proses PO, Minta Perbaikan, Verifikasi PO & Proses GR, Batalkan Proses.
- Reason wajib untuk revision/cancel; PO dibuat manual di ESB.

## PO verification
- BLSS, status Authorized/Receiving, branch sama.
- Supplier PO authoritative; mismatch teks warning + explicit confirmation.
- Setiap Accepted item exact Product Detail ID + UOM.
- Accepted aggregate tidak melebihi outstanding.
- PO belum tertaut ke receipt lain; re-fetch sebelum POST GR.
- Jangan percaya snapshot client.

## Goods Receipt
- Characterize lalu extract shared CreateGoodsReceiptAction dari GoodsReceiptPage.
- Hanya Accepted qty dikirim; normal/non-PO memakai persistence/QC/idempotency/monitoring sama.
- Jangan tahan DB transaction selama HTTP.
- succeeded/partial/failed/unknown mengikuti contract existing.
- Connection failure menjadi gr_unknown tanpa blind retry.
- GR final menampilkan source/link PO Susulan.

## Authorization
- Permission baru sesuai PRD di config/permissions.php.
- INVENTORY_STAFF create/submit; PURCHASING_STAFF view/process; cancel terpisah.
- Employee App tetap memerlukan access employee app goods receipt.
- Policy/action menguji permission, status, dan context; visibility bukan security.

## Test minimum
- Draft/submit local tanpa ESB; duplicate submit aman.
- Field wajib, qty sum, duplicate/tampering product, file cleanup.
- Permission/transition/notification/badge.
- PO wrong status/company/branch/product/UOM/outstanding/duplicate ditolak.
- Supplier warning confirmation.
- Accepted-only, mixed QC, all-Hold/all-Rejected.
- Double-click satu GR; rollback; failed/unknown/monitoring.
- Receiving normal regression; Back Office source/link/scope.

## Delivery
- Update Inventory/Purchasing README, panels/RBAC, regenerate data model.
- Buat implementation report; hapus prompt setelah selesai.
- Jalankan targeted tests, Feature shards, Pint, doc-link, code-map, diff check, migration verification.
- Commit, push, dan buka PR Bahasa Indonesia. Jangan merge/deploy.

## Laporan akhir
Laporkan branch/commit/PR, phase/files, hasil test aktual, migration/permission/integration impact, UAT, deviasi, risiko, dan deploy/rollback. Jangan mengklaim test yang tidak dijalankan.
```
