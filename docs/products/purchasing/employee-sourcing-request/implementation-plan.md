---
document_id: PURCHASING-EMPLOYEE-SOURCING-REQUEST-PLAN
title: Implementation Plan — Request Sourcing melalui Employee App
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/employee-sourcing-request/prd.md
  - docs/products/rnd/material-sourcing-lifecycle/prd.md
supersedes: null
---

# Implementation Plan — Request Sourcing melalui Employee App

Acuan requirement: `docs/products/purchasing/employee-sourcing-request/prd.md`. Dokumen ini hanya mengatur urutan teknis; requirement final tetap berada di PRD.

## 1. Scope implementasi

- **Termasuk:** request sourcing satu produk dari Employee App, snapshot Product Detail ESB, attachment privat, history requester, antrean/detail Purchasing, transition request, notifikasi internal, conversion idempotent ke sourcing mandiri existing, link/badge sumber request, permission, test, dokumentasi, dan rollout.
- **Tidak termasuk:** produk bebas teks/produk baru, multi-product request, supplier dari requester, perubahan workflow R&D/Finance, mutation ESB saat submit, WhatsApp/Telegram/email baru, SLA scheduler, API publik, package baru, atau perubahan role production otomatis.

## 2. Baseline repository

- Baseline planning: `origin/main` commit `a4a101a9` pada 3 November 2026.
- Employee App tile berasal dari `LauncherPage` dan difilter permission.
- Request ERP menjadi referensi struktur form, success state, attachment, serta bottom navigation.
- Receiving Tanpa PO/Item Journal berbagi product picker mobile melalui `ManagesEsbStockProductPicker` dan `esb-product-picker-sheet`.
- Back Office Purchasing sudah mempunyai Sourcing Bahan serta action sourcing mandiri.
- `CreateStandaloneMaterialSourcingAction` memverifikasi ulang produk ESB, mewajibkan supplier/harga, dan membuat origin `standalone` berstatus review R&D.
- Belum ada request entity, queue, permission, atau source link dari Employee App.
- Worktree utama saat planning mempunyai perubahan ESB command milik user; implementasi wajib dimulai dari main bersih/terbaru dan tidak membawa perubahan tersebut.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Migration | `database/migrations/*_create_material_sourcing_requests_tables.php` | Baru, additive |
| Model/factory | `app/Models/MaterialSourcingRequest*.php`, `database/factories/MaterialSourcingRequestFactory.php` | Baru |
| Enum | `app/Enums/MaterialSourcingRequestStatus.php` | Baru |
| Action | `app/Actions/Purchasing/MaterialSourcingRequest/` | Baru; seluruh mutation request/conversion |
| Existing Action | `app/Actions/Rnd/CreateStandaloneMaterialSourcingAction.php` | Reuse; ubah hanya bila kontrak opsional request benar-benar diperlukan |
| Product read/picker | `app/Filament/Casual/Concerns/ManagesEsbStockProductPicker.php`, `resources/views/filament/casual/pages/partials/esb-product-picker-sheet.blade.php` | Reuse/minimal refactor no-stock mode |
| Employee App Page | `app/Filament/Casual/Pages/MaterialSourcingRequest{,History}Page.php` | Baru |
| Employee App View | `resources/views/filament/casual/pages/material-sourcing-request*.blade.php`, `resources/views/components/material-sourcing-request/` | Baru |
| Launcher/recent request | `app/Filament/Casual/Pages/LauncherPage.php` | Tile dan optional recent request |
| Back Office Resource | `app/Filament/Helpdesk/Resources/MaterialSourcingRequests/` | Baru |
| Navigation | `resources/views/vendor/filament-panels/components/layout/index.blade.php` | Menu/badge Purchasing |
| Policy | `app/Policies/MaterialSourcingRequestPolicy.php` | Baru |
| Attachment controller | `app/Http/Controllers/Helpdesk/MaterialSourcingRequestAttachmentController.php`, `routes/web.php` | Baru bila pola temporary URL tidak mencukupi |
| Permission | `config/permissions.php`, `database/seeders/RolesAndPermissionsSeeder.php` | Registry dan default development sesuai keputusan Phase 0 |
| Existing sourcing UI/model | `app/Models/RndProductEsbMaterial.php`, `app/Filament/Helpdesk/Resources/MaterialSourcings/` | Relasi/link/badge source |
| Tests | `tests/Feature/MaterialSourcingRequest*Test.php`, `tests/Feature/MaterialSourcingTest.php` | Baru dan regression |
| Docs | Purchasing/R&D README, panels, RBAC, generated data model, folder fitur | Update saat implementasi |

Path final mengikuti sibling dan hasil Phase 0. Jangan membuat base folder/abstraction baru tanpa kebutuhan terbukti.

## 4. Dependency dan integration boundary

- Tidak ada package baru.
- Master Product ESB adalah read-only integration boundary pada picker, submit, dan conversion.
- `CreateStandaloneMaterialSourcingAction` adalah use case canonical untuk membuat sourcing tanpa Project; conversion tidak menyalin validation/create logic tersebut.
- Database lokal adalah sumber kebenaran status request.
- File memakai disk privat existing dan pola attachment Employee App.
- Notification memakai kanal internal existing; tidak menambah layanan eksternal.
- Workflow R&D/Finance setelah conversion berada di luar aggregate request dan hanya ditautkan.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | Audit schema, picker, attachment, notification, permission, dan characterization sourcing | PR implementasi yang sama | PRD disetujui |
| 1 | Migration/model/factory/enum/policy/permission/history | PR implementasi yang sama | Phase 0 decisions |
| 2 | Submit/resubmit/cancel, ESB verification, attachment, notification | PR implementasi yang sama | Domain tests lulus |
| 3 | Claim/revision/reject/conversion dan link sourcing | PR implementasi yang sama | Request workflow stabil |
| 4 | Employee App dan Back Office UI | PR implementasi yang sama | Action/Policy stabil |
| 5 | Regression, query/performance, UAT, docs/report | PR implementasi yang sama | Semua phase selesai |

Satu PR disarankan karena queue request tanpa conversion atau permission akan menghasilkan fitur setengah jadi. Commit tetap dipisah per phase. Navigation baru tidak boleh tersedia sampai workflow dan authorization lengkap.

## 6. Migration strategy

1. Gunakan `php artisan make:model -mf` dan `make:migration --no-interaction`.
2. Buat tabel request dan status history secara additive.
3. Request number dan submission/idempotency key mempunyai unique constraint.
4. Nullable linked `rnd_product_esb_material_id` dibuat unique agar satu request maksimal satu sourcing.
5. FK master/history memakai restrict/null strategy sesuai lifecycle; jangan cascade-delete request audit.
6. Index berdasarkan query nyata: status/submitted, branch/status, requester/submitted, assigned/status, product code/detail, target date.
7. Tidak ada backfill atau mutation data existing.
8. Attachment JSON dipilih hanya setelah audit menunjukkan jumlah/format/lifecycle cukup; bila tidak, gunakan child table dengan metadata dan unique path.

## 7. Data compatibility

- Sourcing Project dan standalone existing tetap utuh.
- `sourcing_origin` tidak ditambah value baru; source Request User ditentukan dari relation request.
- Sourcing manual tanpa request tetap dapat dibuat dan diberi label Mandiri.
- Timestamp request tidak mengganti `sourcing_requested_at` atau `sourcing_completed_at` existing.
- Route, menu, permission, dan test existing dipertahankan.
- Record request lama tidak ada sehingga backfill tidak diperlukan.

## 8. Authorization

- `access employee app sourcing request`: tile, form, history, submit/resubmit/cancel milik sendiri.
- `view material sourcing requests`: index/detail Back Office.
- `process material sourcing requests`: claim, revision, reject, conversion.
- Conversion juga memanggil authorization `submit material sourcing` existing.
- Policy menerapkan ownership requester dan permission processor; Action mengulang authorization serta invariant status.
- Role production diberikan melalui UI setelah permission sync; jangan memakai seeder untuk grant production.
- Query requester harus selalu scoped `requester_id = auth()->id()`.

## 9. UI

### Employee App

- Reuse struktur visual Request ERP: bare layout, header biru, branch/tanggal, content card, information callout, rounded fields, primary submit, success state, bottom navigation.
- Buat komponen bottom nav khusus request sourcing bila tidak ada komponen generik yang benar-benar reusable; jangan mengubah seluruh Request ERP.
- Reuse product picker Receiving Tanpa PO dalam no-stock mode. Bila trait masih memaksa property stock-specific, lakukan refactor kecil dengan characterization tests, bukan menambah dummy business data atau menyalin partial.
- Selected product card menampilkan snapshot display dan aksi ganti sebelum submit.
- History berupa card mobile server-paginated dan hanya milik user.

### Back Office

- Resource baru **Permintaan Sourcing** di grup Purchasing.
- Table index server-side, actionable default scope, filter status/branch/requester/product/date/PIC, badge actionable.
- Detail card untuk requester, produk, kebutuhan, attachment, timeline, dan linked sourcing.
- Conversion form memakai field supplier canonical `MaterialSourcingResource::supplierFormFields()` atau hasil ekstraksi reusable yang tidak mengikat Action ke Resource.
- Hindari nested modal: produk sudah dipilih dan read-only; conversion hanya melengkapi supplier/sourcing.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Characterization tile Request ERP, no-stock picker, standalone sourcing, duration, dan role |
| 1 | Schema invariant, model relation/cast/scope, permission registry, Policy matrix |
| 2 | Submit/resubmit/cancel, ESB fake, forged product, branch guard, attachment private/failure |
| 3 | Claim/revision/reject/conversion, supplier validation, lock/idempotency/concurrency, rollback |
| 4 | Tile/form/history/index/filter/detail/badge/action visibility, responsive manual UAT |
| 5 | Regression MaterialSourcing/R&D/Finance/duration/manual create, query count, full targeted suite |

## 11. Observability

- Append-only status history menjadi audit bisnis utama.
- Application log aman untuk ESB read failure, attachment failure, dan conversion failure.
- Conversion log mencatat request/material/actor ID tanpa raw payload atau signed URL.
- Index/badge menyediakan actionable count; tidak ada dashboard monitoring baru pada v1.
- Notifikasi gagal tidak menggagalkan transaction bisnis setelah commit; mekanisme final mengikuti pola notification existing yang diaudit Phase 0.

## 12. Rollout

Ikuti [rollout.md](rollout.md): migration additive → application → permission sync → role grant via UI → Employee App UAT → Purchasing conversion UAT → R&D/Finance regression → observation.

## 13. Rollback

- Cabut permission tile/menu lebih dulu.
- Rollback application artifact; pertahankan schema, request, attachment, history, dan relation.
- Request converted tetap mempunyai sourcing valid dan tidak diputus.
- Jangan drop table atau menghapus storage sebagai rollback otomatis.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Perubahan picker merusak Receiving/Item Journal | Characterization tests dan no-stock extension minimal |
| Conversion menggandakan sourcing | Unique FK, row lock, idempotency/concurrency test |
| Resource mengulang standalone logic | Orchestration Action memanggil Action canonical |
| Harga/supplier termuat di Employee App | Query/presenter requester tidak eager-load supplier; Policy test |
| Attachment orphan | Compensation dan authorized lifecycle test |
| Badge/index berat | Aggregate/index/query-count test |
| Role tile terlalu luas | Permission terpisah dan grant manual |

## 15. Quality gates

- [ ] Targeted request/model/policy/action/Livewire/file tests lulus
- [ ] Regression `MaterialSourcingTest`, duration, Review Sourcing R&D, dan Finance lulus
- [ ] HTTP fake membuktikan tidak ada mutation ESB pada submit
- [ ] Concurrency/double conversion lulus
- [ ] Query count index/history/badge diverifikasi
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `bash ops/ci/check-doc-links.sh`
- [ ] `bash ops/ci/check-code-map.sh`
- [ ] `git diff --check`
- [ ] Responsive/dark mode/attachment/authorization UAT staging dicatat
- [ ] CI Pull Request hijau

## 16. Definition of Done

- [ ] Semua phase selesai sesuai PRD.
- [ ] Permission dan role staging diuji.
- [ ] Product picker, request, conversion, dan sourcing downstream aman dan teruji.
- [ ] README/architecture sesuai kode aktual.
- [ ] Implementation report dibuat di `reports/implementation-report.md`.
- [ ] Implementation prompt dihapus setelah implementasi selesai.
