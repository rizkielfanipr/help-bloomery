---
document_id: INVENTORY-NON-PO-RECEIVING-PLAN
title: Implementation Plan — Penerimaan Tanpa PO dan PO Susulan
status: in-development
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/inventory/non-po-receiving/prd.md
  - docs/products/inventory/non-po-receiving/rollout.md
supersedes: null
---

# Implementation Plan — Penerimaan Tanpa PO dan PO Susulan

Acuan requirement: `docs/products/inventory/non-po-receiving/prd.md`.

## 1. Scope implementasi

- **Termasuk:** aggregate lokal, draft/submit Inventory, Master Product picker, QC/batch/photo, Purchasing queue, manual PO linking, exact verification, automatic GR, monitoring, notification, permissions, migration, test, dan docs.
- **Tidak termasuk:** create PO API, Master Supplier picker, multi-PO, use-before-GR, Item Journal sementara, SLA scheduler, Finance threshold approval, atau perubahan perilaku Receiving normal.

## 2. Baseline repository

- Baseline planning: `main` commit `874d3042`.
- Mutation Receiving berada di `GoodsReceiptPage`; characterization wajib sebelum extract.
- `EsbGoodsReceiptService` mendukung GET PO/location dan POST GR; tidak ada create PO.
- `PurchaseRequest`/`PurchasingRequest` tidak dipakai ulang.
- Master Product/branch/location patterns tersedia; supplier input ditetapkan teks.
- Implementasi harus dimulai dari `main` terbaru pada `feature/non-po-receiving`.

## 3. File dan modul terdampak

| Area | Path perkiraan | Perubahan |
|---|---|---|
| Enum | `app/Enums/NonPoReceiptStatus.php` | Baru |
| Model | `app/Models/NonPoReceipt*.php` | Header, item, batch, history |
| Migration/factory | `database/{migrations,factories}/` | Schema additive dan fixtures |
| Inventory Action | `app/Actions/Inventory/Receiving/` | Save draft, submit, shared GR |
| Purchasing Action | `app/Actions/Purchasing/NonPoReceipt/` | Process/revision/verify/cancel/reconcile |
| Policy | `app/Policies/NonPoReceiptPolicy.php` | Baru |
| Employee App | `app/Filament/Casual/Pages/{GoodsReceiptPage,NonPoReceiptPage}.php` dan view | Mode Tanpa PO/history pada halaman terpisah |
| Purchasing Resource | `app/Filament/Helpdesk/Resources/NonPoReceipts/` | Submenu PO Susulan |
| Receiving Resource | `app/Filament/Helpdesk/Resources/GoodsReceipts/` | Badge/link source |
| Permission | `config/permissions.php` dan role sync convention | Permission baru |
| Test | `tests/Feature/{NonPoReceipt,GoodsReceipt,Receiving,PurchaseRequest}*Test.php` | Baru/regression |
| Docs | Inventory, Purchasing, panels, RBAC, data model | Update |

Path final mengikuti sibling/convention setelah audit implementasi.

## 4. Dependency dan integration boundary

- Tidak menambah package.
- Draft/submit lokal tidak memanggil ESB.
- Master Product/branch/location hanya read boundary.
- Purchasing membuat PO di luar aplikasi; aplikasi membaca detail PO.
- Shared GR Action menjadi write boundary create GR untuk normal/non-PO.
- Monitoring membungkus mutation eksternal, bukan persistence lokal.

## 5. Urutan phase

| Phase | Scope | Prasyarat |
|---|---|---|
| 0 | Audit dan characterization | PRD approved |
| 1 | Schema/domain/policy/local flow | Baseline hijau |
| 2 | Purchasing UI + PO verification | Phase 1 stabil |
| 3 | Shared GR Action + integration | Exact mapping terbukti |
| 4 | Regression/docs/rollout | Phase 1–3 selesai |

## 6. Migration strategy

- Generate melalui Artisan; satu concern per migration.
- Tabel: `non_po_receipts`, `non_po_receipt_items`, `non_po_receipt_batches`, `non_po_receipt_status_histories`.
- Index status/time, company/branch, linked PO, actor.
- Unique internal number, submission key, nullable linked PO, nullable goods receipt, dan item identity.
- Actor FK `nullOnDelete`; aggregate child cascade sesuai convention.
- Mirror default status pada model.
- `down()` reversible untuk CI/staging; production dengan data memakai forward fix.
- Regenerate data model documentation.

## 7. Data compatibility

- Tidak ada backfill.
- GR existing tetap `purchase_order` melalui default/fallback jika source field ditambah.
- Supplier local text dan authoritative PO snapshot terpisah.
- Snapshot local product tidak ditimpa; mapping PO disimpan terpisah.
- Route/status/payload Receiving existing tetap kompatibel.

## 8. Authorization

- Daftarkan permission PRD pada `config/permissions.php`.
- `INVENTORY_STAFF`: create/submit; `PURCHASING_STAFF`: view/process; cancel terpisah.
- Employee App tetap memerlukan `access employee app goods receipt`.
- Policy memeriksa permission, status, dan context BLSS.
- Test visibility dan direct Action/Livewire invocation.

## 9. UI

1. Reuse Receiving QC/batch/photos.
2. Reuse UX modal BOM, tetapi datasource Master Product purchase/inventory.
3. Server-side search/pagination dan stable keys.
4. Purchasing comparison matrix local vs PO authoritative.
5. Exact mismatch memblokir; supplier mismatch warning + confirmation.
6. Loading/disabled state mencegah double-click di UI; server tetap idempotent.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Receiving/GoodsReceipt characterization |
| 1 | Migration, constraint, draft, submit, QC, photo, policy, notification |
| 2 | Queue/badge, transition, revision/cancel, PO comparison |
| 3 | Normal/non-PO shared GR, accepted-only, idempotency, failed/unknown |
| 4 | Full affected regression, docs, Pint, CI |

## 11. Observability

- Status history pada setiap transition.
- Monitoring source/operation membedakan PO normal/PO susulan.
- Idempotency key konsisten dari local receipt ke GR.
- Database notifications untuk actionable events.
- Error log tidak memuat foto/dokumen/payload sensitif.
- Queue age terlihat tanpa scheduler.

## 12. Rollout

Ikuti [rollout.md](rollout.md): migration, permission sync, role assignment, storage/ESB verification, UAT dua role, monitoring, dan approval production oleh user.

## 13. Rollback

- Sebelum data: rollback app dan migration.
- Setelah data: sembunyikan entry point/remove permission, jangan drop tabel, lakukan forward fix.
- Reconcile record in-flight sebelum re-enable.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Refactor merusak Receiving normal | Characterization sebelum shared Action |
| Product picker salah scope | Server validation dan catalog test |
| PO/GR ganda | Unique index, row lock, idempotency |
| HTTP/DB inconsistent | Two-step status dan unknown reconciliation |
| Permission terlalu luas | Permission/action terpisah |
| Migration berisi data | Additive schema dan forward rollback |

## 15. Quality gates

- [ ] Targeted `NonPoReceipt*Test.php` lulus.
- [ ] `GoodsReceipt*`, `Receiving*`, dan Purchasing test terdampak lulus.
- [ ] Wide Feature shards dijalankan karena lintas domain/integration boundary.
- [ ] `vendor/bin/pint --dirty --format agent`.
- [ ] `bash ops/ci/check-doc-links.sh`.
- [ ] `bash ops/ci/check-code-map.sh`.
- [ ] `git diff --check`.
- [ ] Fresh/rollback migration dan permission sync staging terverifikasi.
- [ ] CI hijau dan UAT dua role lulus.

## 16. Definition of Done

- [ ] Semua phase/acceptance criteria selesai dan Receiving normal tidak regresi.
- [ ] Data model, RBAC, panels, Inventory/Purchasing README diperbarui.
- [ ] Implementation report mencatat migration, test, deviasi, dan UAT.
- [ ] Implementation prompt dihapus setelah selesai.
- [ ] PR Bahasa Indonesia dibuka; merge/deploy tetap keputusan user.
