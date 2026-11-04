---
document_id: KITCHEN-PRODUCTION-STAFF-ASSIGNMENT-PROMPT
title: Prompt Implementasi — Assignment Staff Produksi per Detail Production Order
status: draft
version: 1.0
owner: Kitchen
technical_owner: Information Technology
created_at: 2026-11-04
updated_at: 2026-11-04
target_release: TBD
related_documents:
  - docs/products/kitchen/production-staff-assignment/prd.md
  - docs/products/kitchen/production-staff-assignment/implementation-plan.md
  - docs/products/kitchen/production-staff-assignment/rollout.md
supersedes: null
---

# Prompt Implementasi — Assignment Staff Produksi per Detail Production Order

Salin blok berikut ke agent implementasi dari root repository setelah keputusan PRD §26 ditutup.

```text
Implementasikan Assignment Staff Produksi per Detail Production Order pada domain Kitchen sesuai workflow Plan → Branch → Implement → Deliver di AGENTS.md.

Gunakan branch baru dari main terbaru:

feature/kitchen-production-staff-assignment

Jika working tree utama tidak bersih karena pekerjaan user, jangan stash, reset, memindahkan, atau menghapus perubahan tersebut. Buat worktree terpisah dari origin/main terbaru.

## Gate wajib sebelum perubahan kode

1. Baca PRD §26.
2. Pastikan OD-1, OD-2, dan OD-3 sudah ditutup oleh user serta dicatat di PRD.
3. Pastikan status PRD sudah `approved` atau `in-development`.
4. Jika salah satu belum terpenuhi, berhenti setelah audit read-only dan minta keputusan user. Jangan membuat migration atau kode aplikasi.

## Dokumen sumber yang wajib dibaca penuh

1. `AGENTS.md` — aturan repository dan Laravel Boost.
2. `docs/products/kitchen/README.md` — perilaku Kitchen yang berjalan saat ini.
3. `docs/products/kitchen/production-staff-assignment/prd.md` — sumber requirement canonical.
4. `docs/products/kitchen/production-staff-assignment/implementation-plan.md` — urutan teknis dan quality gate.
5. `docs/products/kitchen/production-staff-assignment/rollout.md` — deployment, UAT, dan rollback.
6. `docs/products/kitchen/production-schedule/prd.md` — kontrak kalender, snapshot, sync, dan Branch scope existing.
7. `docs/products/kitchen/production-schedule/README.md` — status fitur Production Order ESB.
8. `docs/architecture/data-model.md`, `docs/architecture/rbac.md`, dan `docs/architecture/panels.md`.
9. `docs/standards/code-remediation.md`, `docs/standards/ui-consistency.md`, dan `docs/standards/documentation-standard.md`.
10. Skill repository yang relevan di `**/skills/**`.

Gunakan Laravel Boost `search-docs` sebelum mengubah kode. Cari dokumentasi versi package repository untuk Eloquent transaction/lock, migration/FK/index, Filament/Livewire actions dan modal, Policy testing, relationship loading, pagination, dan Pest concurrency/authorization.

## Hasil akhir yang diharapkan

1. Detail BOM/product pada Production Order dapat memiliki nol, satu, atau beberapa Staff Produksi.
2. Assignment disimpan lokal dan tidak mengubah snapshot atau data ESB.
3. Picker hanya menawarkan employee yang memenuhi keputusan OD-1.
4. View mengikuti Branch scope Production Order; mutation memerlukan permission khusus.
5. Assignment tetap ada setelah sinkronisasi ulang detail yang sama.
6. Staff historis tetap terbaca bila employee nonaktif, pindah Branch, berubah identitas, atau referensinya hilang.
7. Perubahan assignment atomik, idempotent, aman terhadap concurrent save, dan mempunyai audit append-only.
8. UI memakai istilah **Staff Produksi**, mendukung multi-select, empty state, warning historis, konfirmasi pelepasan, dan coverage assignment.
9. Kalender/detail/picker bebas N+1 dan responsif.
10. Seluruh test, dokumentasi, implementation report, rollout, commit, push, dan Pull Request diselesaikan sesuai AGENTS.md. Jangan merge atau deploy.

## Scope

- migration additive assignment dan audit event;
- model, factory, relasi, cast, dan append-only guard;
- permission `assign kitchen production order staff`;
- Policy/authorization per detail dan Branch Kitchen;
- Action transactional berbasis normalized diff;
- section Staff Produksi pada card detail;
- modal picker searchable dan multiple selection;
- snapshot employee/Branch untuk histori;
- warning employee nonaktif/Branch berubah;
- coverage `X dari Y detail` tanpa N+1;
- test domain, authorization, audit, sync regression, concurrency, query count, dan UI;
- update dokumentasi, rollout, report, dan PR.

## Non-scope

Jangan mengerjakan:

- Employee App atau Tugas Produksi Saya;
- notifikasi assignment;
- PIC utama, pembagian quantity, shift, jam kerja, atau persentase kontribusi;
- status mulai/selesai lokal, actual output, yield, waste, atau produktivitas;
- payroll, attendance, HRIS, costing, atau inventory reservation;
- assignment per material;
- perubahan status/BOM/quantity/material/finished flag Production Order ESB;
- endpoint atau mutation ESB baru;
- refactor besar kalender/sync yang tidak diperlukan;
- dependency/package baru;
- perubahan role operasional otomatis melalui seeder;
- data dummy production atau backfill assignment hasil tebakan.

## Baseline yang wajib dipertahankan

- `EsbProductionOrder` dan `EsbProductionOrderDetail` tetap snapshot read-only.
- `StoreProductionOrderDetailAction` tetap meng-upsert detail berdasarkan `external_detail_id`.
- Detail yang hilang dari response sync tidak dihapus oleh behavior existing.
- Kalender dan modal membaca database lokal; tidak ada ESB call saat render/open.
- Branch scope selalu dibatasi ke Branch ber-Brand Kitchen, termasuk Superadmin/all-branch.
- Sync manual, scheduler, banner stale/partial/error, filter, dan route existing tidak berubah kecuali penambahan coverage yang sudah diuji.

## Aturan repository

- Mulai dari main terbaru pada branch/worktree bersih.
- Ikuti konvensi sibling dan Peta kode Kitchen.
- Gunakan `php artisan make:* --no-interaction` untuk file Laravel baru.
- Gunakan `apply_patch` untuk edit manual.
- Jangan mengubah dependency tanpa approval.
- Jangan mengedit migration lama; buat migration additive baru.
- Jangan membawa perubahan ESB command atau pekerjaan user lain.
- Jalankan `vendor/bin/pint --dirty --format agent` setelah mengubah PHP.

## Aturan data dan migration

1. Audit schema aktual melalui Laravel Boost `database-schema` sebelum mendesain tabel.
2. Audit lifecycle delete/update Employee dan actor User.
3. Buat tabel assignment aktif dan audit event sesuai PRD §14 serta keputusan Phase 0.
4. Gunakan FK/index/unique yang benar untuk MySQL; jangan mengandalkan unique nullable yang tidak benar-benar mencegah duplicate active row.
5. Snapshot minimal employee code/name/position dan Branch disimpan saat assignment.
6. Audit event append-only tidak dapat update/delete melalui model.
7. Tidak ada backfill atau mutation data existing.
8. Rehearsal migration `up()` dan rollback struktur pada database MySQL lokal/staging.
9. Rollback production tidak boleh menjatuhkan tabel setelah data assignment dipakai.

## Aturan arsitektur dan concurrency

- Livewire Page hanya mengelola state presentasi, authorization awal, validasi UI, dan delegasi.
- Blade tidak menjalankan query dan tidak memutasi model.
- Mutation berada di `app/Actions/Kitchen/AssignProductionOrderStaffAction.php` atau nama sibling-equivalent hasil audit.
- Action menerima scalar/detail record dan daftar employee ID yang sudah dinormalisasi; tetap re-query seluruh record di dalam boundary aman.
- Gunakan database transaction, row lock pada detail/assignment terkait, diff selection, dan unique constraint.
- No-op tidak menulis row atau audit event.
- Jika satu employee invalid, seluruh mutation gagal tanpa perubahan parsial.
- Jangan membuat repository/interface/event baru bila hanya mempunyai satu penggunaan.
- Jangan menyentuh `StoreProductionOrderDetailAction` kecuali characterization test membuktikan perubahan kecil diperlukan untuk menjaga relasi; dokumentasikan alasan.

## Aturan authorization

- Daftarkan permission `assign kitchen production order staff` di registry canonical.
- Mutation memerlukan permission view dan assignment.
- Policy memvalidasi detail, parent order, Branch aktif ber-Brand Kitchen, dan scope actor.
- `view all branch kitchen production schedules` bukan permission mutation.
- Re-query detail, order, Branch, company, dan employee; jangan percaya state browser.
- Terapkan keputusan OD-1 saat transaction berjalan.
- Visibility tombol bukan kontrol keamanan; test request langsung ke method/Action.
- Jangan grant role operasional melalui seeder. Grant dilakukan melalui UI setelah UAT.

## Aturan UI

- Pertahankan modal detail Production Order existing dan komponen/style sibling.
- Tampilkan copy bahwa data order berasal dari ESB dan hanya assignment Staff Produksi yang dikelola lokal.
- Tambahkan section **Staff Produksi** pada setiap detail setelah quantity/status dan sebelum material.
- Empty state: **Belum ada Staff Produksi yang ditugaskan.**
- Tombol: **Atur Staff Produksi**.
- Modal: **Pilih Staff Produksi** dengan konteks order/BOM/Branch/quantity/status read-only.
- Search nama, employee code, atau posisi; multi-select; assignment existing tercentang.
- Konfirmasi sebelum selection kosong melepas seluruh staff.
- Terapkan keputusan OD-2 untuk detail selesai.
- Staff nonaktif diberi label **Nonaktif**; staff beda Branch diberi label **Branch berubah**.
- Coverage menghitung detail yang memiliki minimal satu assignment, bukan jumlah employee.
- UAT 360/390/768/1024/1440 px, zoom 200%, dark mode, keyboard/Escape, nama panjang, loading/empty/error.

## Aturan integration

- Tidak ada endpoint ESB baru.
- Jangan memanggil `EsbProductionOrderService` atau dispatch sync dari assignment.
- HTTP fake/spy harus membuktikan save assignment tidak memanggil ESB.
- Sync ulang detail yang sama harus mempertahankan assignment.
- Failure/stale/partial sync tetap memakai behavior UI existing.

## Tahapan implementasi

### Phase 0 — Audit dan characterization

1. Audit schema, model relation, Employee deletion/lifecycle, permission, Policy, builder/query, dan UI detail.
2. Ukur query count kalender dan detail sebelum perubahan.
3. Tambahkan characterization test untuk identity detail stabil dan scope Branch.
4. Tutup OD-1–OD-3 bersama user, perbarui PRD/README, lalu ubah status menjadi `in-development`.

### Phase 1 — Domain foundation

1. Buat migration, model, factory, relasi, permission, Policy method, dan audit guard.
2. Tambahkan test schema invariant, relation/cast, unique constraint, append-only, permission, dan Policy matrix.
3. Jalankan migration rehearsal MySQL.

### Phase 2 — Assignment action

1. Implementasikan Action normalized diff dalam transaction/lock.
2. Simpan snapshot identitas dan audit assigned/unassigned.
3. Uji add/multiple/replace/remove/no-op/invalid/forged/cross-Branch/concurrent save/rollback.

### Phase 3 — UI dan query

1. Update detail builder dengan eager loading terkontrol.
2. Tambahkan picker/action ke Page dan section ke Blade.
3. Tambahkan coverage agregat tanpa eager-load employee di kalender.
4. Uji visibility, state, warning, konfirmasi, query count, dan responsive UI.

### Phase 4 — Regression dan delivery

1. Jalankan regression Kitchen Production Order, Employee/Master Data terkait, permissions/sidebar, sync command/job, dan scheduler.
2. Jalankan Pint, doc links, code map, diff check, migration rehearsal, dan UAT staging.
3. Update Kitchen README, Peta kode, data model, RBAC/panels bila relevan, PRD status, rollout, serta implementation report.
4. Hapus `implementation-prompt.md` setelah implementasi selesai sesuai AGENTS.md.
5. Commit per concern, push branch, dan buka Pull Request Bahasa Indonesia memakai template repository. Jangan merge atau deploy.

## Test minimum

- schema/unique/FK/index dan migration rehearsal;
- model relation/cast/factory dan append-only audit;
- Policy matrix viewer/assigner/all-branch/non-Kitchen;
- add satu/multiple, replace, remove partial/all, no-op, duplicate, invalid, inactive, cross-Branch;
- concurrent save, idempotency, row lock, transaction rollback;
- employee nonaktif/pindah Branch/referensi hilang;
- sync detail berulang/partial/missing response mempertahankan assignment;
- HTTP fake/spy membuktikan tidak ada ESB call;
- Livewire/Blade button, picker, existing selection, empty/warning/confirm/coverage;
- query count kalender/detail/picker;
- regression seluruh suite `KitchenProductionOrder*` dan permission/sidebar terkait;
- UAT responsive/dark/zoom/keyboard.

## Quality gates

- seluruh keputusan PRD ditutup;
- targeted dan regression tests lulus;
- `vendor/bin/pint --dirty --format agent`;
- `bash ops/ci/check-doc-links.sh`;
- `bash ops/ci/check-code-map.sh`;
- `git diff --check`;
- CI Pull Request hijau;
- implementation report berisi hasil nyata, deviasi, dan pekerjaan lanjut;
- tidak ada secret, payload production, atau data pribadi pada log/docs/test.

## Format laporan akhir

1. Branch aktif dan Pull Request.
2. Ringkasan perubahan per phase.
3. File yang dibuat/diubah.
4. Migration, permission, dan rollout note.
5. Test/quality gate yang dijalankan beserta hasil nyata.
6. UAT yang selesai dan yang masih pending.
7. Deviasi dari PRD dan alasannya.
8. Risiko serta pekerjaan lanjutan.

## Larangan

- Jangan menulis kode sebelum OD-1–OD-3 ditutup.
- Jangan merge, approve deployment, menyentuh production, atau menghapus data.
- Jangan melakukan mutation ESB.
- Jangan mengubah pekerjaan user yang tidak terkait.
- Jangan memperluas scope ke Employee App, notification, status kerja, atau produktivitas tanpa PRD baru/persetujuan.
```
