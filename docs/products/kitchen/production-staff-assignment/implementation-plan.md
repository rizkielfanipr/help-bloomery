---
document_id: KITCHEN-PRODUCTION-STAFF-ASSIGNMENT-PLAN
title: Implementation Plan — Assignment Staff Produksi per Detail Production Order
status: draft
version: 1.0
owner: Kitchen
technical_owner: Information Technology
created_at: 2026-11-04
updated_at: 2026-11-04
target_release: TBD
related_documents:
  - docs/products/kitchen/production-staff-assignment/prd.md
  - docs/products/kitchen/production-schedule/prd.md
  - docs/products/kitchen/production-staff-assignment/rollout.md
supersedes: null
---

# Implementation Plan — Assignment Staff Produksi per Detail Production Order

Acuan requirement: `docs/products/kitchen/production-staff-assignment/prd.md`. Dokumen ini hanya mengatur urutan teknis; requirement tidak ditulis ulang di sini.

## 1. Scope implementasi

- **Termasuk:** model assignment aktif dan audit event, migration additive, relasi detail/employee, permission dan Policy, Action transactional berbasis diff, section dan picker Staff Produksi, coverage assignment, authorization Branch Kitchen, test, dokumentasi, UAT, rollout, dan rollback.
- **Tidak termasuk:** Employee App, notifikasi, PIC utama, pembagian quantity, shift/jam kerja, status proses lokal, produktivitas, payroll/attendance/HRIS, mutation ESB, perubahan master BOM, dan assignment per material.

Implementasi tidak boleh dimulai sebelum OD-1–OD-3 pada PRD §26 ditutup dan status PRD diubah menjadi `approved` atau `in-development`.

## 2. Baseline repository

- Baseline planning: `origin/main` commit `0440b82d` pada 4 November 2026; implementasi wajib memulai dari `main` terbaru saat pekerjaan dimulai.
- Kalender Production Order berada di `KitchenProductionSchedulePage` dan detailnya dirender oleh Blade modal existing.
- `ProductionOrderDetailBuilder` melakukan scope dan eager loading detail snapshot.
- `StoreProductionOrderDetailAction` memakai `updateOrCreate` berdasarkan `external_detail_id`; detail yang sama mempertahankan primary key lokal.
- Snapshot Production Order, detail, dan material bersifat read-only dari ESB.
- `Employee` memiliki Branch, kode, nama, posisi, dan status aktif; belum ada assignment ke detail Production Order.
- Policy existing menegakkan permission view dan Branch ber-Brand Kitchen.
- Worktree utama saat planning mempunyai perubahan command ESB milik user. Implementasi harus memakai branch/worktree bersih dan tidak membawa perubahan tersebut.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Migration | `database/migrations/*_create_kitchen_production_order_staff_tables.php` | Baru, additive |
| Model | `app/Models/KitchenProductionOrderStaffAssignment.php` | Baru |
| Audit model | `app/Models/KitchenProductionOrderStaffAssignmentEvent.php` | Baru, append-only |
| Factory | `database/factories/KitchenProductionOrderStaffAssignmentFactory.php` | Baru |
| Existing model | `app/Models/EsbProductionOrderDetail.php`, `app/Models/Employee.php`, opsional `app/Models/User.php` | Tambah relasi |
| Action | `app/Actions/Kitchen/AssignProductionOrderStaffAction.php` | Baru; mutation berbasis diff/transaction/lock |
| Policy | `app/Policies/EsbProductionOrderPolicy.php` atau Policy detail khusus bila audit membuktikan perlu | Tambah authorization assignment |
| Page | `app/Filament/Helpdesk/Pages/KitchenProductionSchedulePage.php` | State/action modal dan refresh terkontrol |
| Builder/query | `app/Services/Kitchen/ProductionOrderDetailBuilder.php`, `app/Services/Kitchen/ProductionOrderCalendarQuery.php` | Eager loading assignment dan coverage agregat |
| View | `resources/views/filament/helpdesk/pages/kitchen-production-schedule-page.blade.php` | Section/picker/empty/warning/coverage |
| Permission | `config/permissions.php` dan mekanisme sync permission existing | Tambah permission; tanpa grant role operasional otomatis |
| Test | `tests/Feature/KitchenProductionOrderStaffAssignmentTest.php`, suite `KitchenProductionOrder*` existing | Baru dan regression |
| Docs | Folder fitur, Kitchen README, architecture data model/RBAC/panels bila relevan | Update sesuai hasil implementasi |

Path final mengikuti konvensi sibling dan hasil Phase 0. Jangan membuat abstraction, trait, event, atau repository baru tanpa kebutuhan nyata.

## 4. Dependency dan integration boundary

- Tidak ada package atau dependency baru.
- ESB hanya menjadi sumber read-only snapshot Production Order. Assignment tidak mempunyai endpoint eksternal.
- Database lokal menjadi sumber kebenaran assignment dan audit.
- Master `Employee` existing menjadi sumber kandidat Staff Produksi; tidak dibuat master duplikat.
- `StoreProductionOrderDetailAction` tetap memiliki tanggung jawab sync snapshot dan tidak menyentuh assignment.
- `AssignProductionOrderStaffAction` hanya mengelola data lokal dan tidak men-dispatch sync job.
- Tidak ada queue, scheduler, notification channel, file storage, atau environment variable baru.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | Audit schema, identity detail, Employee deletion/lifecycle, Policy pattern, query count, dan tutup OD-1–OD-3 | PR implementasi yang sama | PRD direview |
| 1 | Migration, model/factory/relasi, permission, Policy, dan append-only audit guard | PR implementasi yang sama | Keputusan Phase 0 dicatat di PRD |
| 2 | Action assignment dengan normalization, diff, transaction, row lock, idempotency, dan audit | PR implementasi yang sama | Domain tests Phase 1 lulus |
| 3 | Builder/query, section Staff Produksi, multi-select picker, warning, coverage, dan responsive UI | PR implementasi yang sama | Action/Policy stabil |
| 4 | Regression sync, concurrency, query count, migration rehearsal, docs/report, dan UAT | PR implementasi yang sama | Seluruh behavior selesai |

Satu PR implementasi disarankan agar schema, Policy, Action, dan UI tidak dikirim setengah jadi. Commit dipisah per phase.

## 6. Migration strategy

1. Buat file melalui `php artisan make:model -mf`/`make:migration --no-interaction` sesuai hasil audit.
2. Migration hanya additive dan tidak mengubah tabel snapshot ESB atau Employee.
3. Tabel assignment menyimpan FK detail, nullable FK employee sesuai keputusan audit, snapshot employee/Branch, actor, dan waktu.
4. Unique constraint mencegah assignment aktif ganda pada detail + employee. Strategi current-row versus soft-delete dikunci di Phase 0 agar constraint benar di MySQL.
5. Audit event memakai tabel append-only dan tidak cascade-delete saat employee berubah/hilang.
6. Index mengikuti query nyata: detail, employee, Branch snapshot, dan event detail/waktu.
7. Tidak ada backfill; seluruh detail existing mulai tanpa assignment.
8. Rehearsal `up()` dan rollback struktur pada database kosong wajib dilakukan; rollback production setelah data dipakai tidak menjatuhkan tabel otomatis.

## 7. Data compatibility

- Snapshot header/detail/material existing tidak berubah.
- Assignment tetap tertaut ketika detail yang sama di-sync ulang.
- Detail yang tidak terlihat pada response terakhir tetap mempertahankan assignment mengikuti retention existing.
- Employee nonaktif atau berubah Branch tetap dapat ditampilkan dari snapshot assignment.
- Employee yang dihapus tidak boleh menghapus audit; strategi FK final mengikuti Phase 0.
- Route, filter kalender, sync manual, scheduler, dan status ESB existing tetap kompatibel.
- Tidak ada default assignment, backfill, atau inferensi dari creator/authorized actor ESB.

## 8. Authorization

- Permission baru: `assign kitchen production order staff`.
- View assignment tetap mengikuti `view kitchen production schedules` dan scope Production Order existing.
- Mutation memerlukan permission view + assignment.
- Policy mengecek detail, parent order, Branch aktif ber-Brand Kitchen, dan `visibleTo()` actor.
- `view all branch kitchen production schedules` hanya membuka seluruh Branch Kitchen, bukan Brand lain dan bukan permission mutation.
- Semua detail/employee dari browser di-query ulang; employee harus memenuhi OD-1 saat transaksi.
- Tombol/modal visibility hanya presentation; Action/Policy menjadi kontrol keamanan sebenarnya.
- Role operasional diberikan melalui UI setelah UAT; tidak ada grant tersembunyi pada seeder.

## 9. UI

- Pertahankan modal detail Production Order existing; jangan mengubah halaman menjadi Resource baru.
- Tambahkan copy yang membedakan snapshot ESB read-only dari assignment lokal.
- Setiap detail card menampilkan section **Staff Produksi**, chip assignment, empty state, dan tombol **Atur Staff Produksi**.
- Picker memakai modal/bottom-sheet pattern existing, server-side search, pagination bila jumlah employee besar, dan multi-select.
- Konteks order/BOM/Branch/quantity/status tetap read-only di modal.
- Selection kosong setelah sebelumnya terisi memerlukan konfirmasi.
- Staff nonaktif/Branch berubah ditampilkan sebagai histori/warning, bukan kandidat baru.
- Coverage `X dari Y detail` dihitung tanpa memuat seluruh employee pada query kalender.
- UAT 360/390/768/1024/1440 px, zoom 200%, keyboard/Escape, nama panjang, empty/loading/error, dan dark mode.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Characterization ID detail stabil, sync read-only, Policy Branch, modal/detail, dan query baseline |
| 1 | Schema/unique/FK/index, relasi/cast/factory, append-only guard, permission registry, Policy matrix |
| 2 | Tambah satu/multiple, diff, lepas sebagian/semua, no-op, forged ID, inactive/cross-Branch, rollback, concurrency/idempotency |
| 3 | Button visibility, picker, selection existing, warning histori, konfirmasi, coverage, responsive manual UAT, query count |
| 4 | Regression seluruh `KitchenProductionOrder*`, Employee/Master Data terkait, sync command/job, permission/sidebar, migration MySQL |

## 11. Observability

- Audit event append-only menjadi sumber perubahan bisnis assignment.
- Log error terstruktur mencatat actor, order/detail, Branch, dan klasifikasi error tanpa payload ESB atau data sensitif berlebih.
- No-op tidak membuat event atau log mutation palsu.
- Query-count assertions menjaga kalender/detail/picker dari N+1.
- Tidak ada monitoring dashboard atau notification baru pada MVP.

## 12. Rollout

Ikuti [rollout.md](rollout.md): migration additive → aplikasi → `permissions:sync` → smoke test Superadmin → grant user UAT via UI → UAT tiap Branch Kitchen → observasi → grant bertahap.

## 13. Rollback

- Cabut permission assignment untuk menghentikan mutation.
- Rollback aplikasi ke artifact sebelumnya; kalender dan sync tetap berfungsi.
- Pertahankan tabel assignment dan audit agar histori tidak hilang.
- Schema cleanup hanya melalui release terpisah setelah backup dan approval eksplisit.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Assignment hilang saat sync | FK ke detail lokal stabil dan regression sync wajib |
| FK Employee memblokir lifecycle Master Data | Audit delete behavior; gunakan nullable reference + snapshot tanpa menghapus audit |
| Multiple save lost update | Transaction, row lock, unique constraint, dan concurrency test |
| Coverage membuat kalender berat | Aggregate/subquery dan query-count test |
| Mutation lintas Branch | Policy + re-query + negative authorization tests |
| User mengira order ESB editable | Copy boundary ESB versus lokal pada modal |

## 15. Quality gates

- [ ] OD-1–OD-3 ditutup dan PRD diperbarui.
- [ ] Characterization test lulus sebelum schema/code behavior berubah.
- [ ] Targeted assignment, Policy, audit, Livewire, sync regression, dan concurrency tests lulus.
- [ ] Query count kalender/detail/picker diverifikasi.
- [ ] Migration `up()` dan rollback rehearsal MySQL lulus.
- [ ] `vendor/bin/pint --dirty --format agent`.
- [ ] `bash ops/ci/check-doc-links.sh`.
- [ ] `bash ops/ci/check-code-map.sh`.
- [ ] `git diff --check`.
- [ ] UAT responsive, dark mode, authorization, dan sync dicatat.
- [ ] CI Pull Request hijau.

## 16. Definition of Done

- [ ] Semua phase selesai sesuai PRD.
- [ ] Tidak ada request atau mutation ESB dari assignment.
- [ ] Permission dan Branch security diuji positif/negatif.
- [ ] Assignment dan audit aman terhadap sync serta lifecycle Employee.
- [ ] README Kitchen, data model, RBAC/panels bila relevan, dan Peta kode sesuai kode aktual.
- [ ] Implementation report dibuat di `reports/implementation-report.md`.
- [ ] Implementation prompt dihapus setelah implementasi selesai.
