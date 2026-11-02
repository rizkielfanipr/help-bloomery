---
document_id: PURCHASING-SOURCING-DURATION-TRACKING-PLAN
title: Implementation Plan — Pelacakan Durasi Sourcing
status: implemented
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/purchasing/sourcing-duration-tracking/prd.md
supersedes: null
---

# Implementation Plan — Pelacakan Durasi Sourcing

Acuan requirement: `docs/products/purchasing/sourcing-duration-tracking/prd.md`. Dokumen ini hanya mengatur urutan teknis; requirement tidak ditulis ulang di sini.

## 1. Scope implementasi

- **Termasuk:** timestamp selesai sourcing, penulisan tanggal mulai sourcing mandiri, formatter durasi, integrasi transisi status, index/detail/filter, audit metadata, migration additive, command backfill, test, dokumentasi, dan rollout.
- **Tidak termasuk:** SLA, hari kerja, overdue notification, dashboard analitik, export, perubahan workflow sourcing/production/pricing, permission baru, dependency baru, atau mutation ESB.

## 2. Baseline repository

- Baseline planning: `main` commit `8d56c876` pada 2 November 2026.
- `RndProductEsbMaterial` sudah mempunyai `sourcing_requested_at` dan `workflow_completed_at`, tetapi belum mempunyai tanggal selesai khusus sourcing.
- `MaterialPostSyncRouter` mengisi tanggal mulai untuk alur project; `CreateStandaloneMaterialSourcingAction` belum melakukannya.
- Transisi final tersebar pada `ReviewMaterialSampleAction`, `ReviewLegacyMaterialSourcingAction`, dan `ReviewLegacyMaterialSourcingFinanceAction`; reopen pada Action terpisah.
- Index Purchasing dan R&D menggunakan definisi kolom/filter bersama. Detail supplier menggunakan Blade modal bersama.
- Audit harus memastikan seluruh jalur yang dapat mengubah status terminal/aktif sudah tercakup sebelum implementasi.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Migration | `database/migrations/2026_11_02_160003_add_sourcing_completed_at_to_rnd_product_esb_materials_table.php` | Baru, additive |
| Model | `app/Models/RndProductEsbMaterial.php` | Fillable/cast dan helper yang kohesif |
| Formatter/presenter | `app/Services/Rnd/Material/MaterialSourcingTimeline.php` | Baru; tanpa query |
| Start routing | `app/Services/Rnd/Material/MaterialPostSyncRouter.php` | Verifikasi idempotency existing |
| Standalone create | `app/Actions/Rnd/CreateStandaloneMaterialSourcingAction.php` | Isi tanggal mulai server-side |
| Sample decision | `app/Actions/Rnd/Material/ReviewMaterialSampleAction.php` | Isi tanggal selesai terminal |
| Reopen | `app/Actions/Rnd/Material/{ReopenMaterialSampleApprovalAction,ReopenRejectedMaterialSourcingAction}.php` | Kosongkan selesai dan audit nilai lama |
| Legacy review | `app/Actions/Rnd/Material/{ReviewLegacyMaterialSourcingAction,ReviewLegacyMaterialSourcingFinanceAction,AddMaterialSupplierCandidatesAction,UpdateMaterialSupplierCandidateAction}.php` | Terminal/resubmit timestamp |
| WIP routing | `app/Services/Rnd/Material/MaterialPostSyncRouter.php` | Kosongkan selesai saat menjadi `not_required` |
| Header filter | `resources/views/filament/helpdesk/erp-repair-requests/table-header-cell.blade.php` | Filter tanggal dan kondisi pada header |
| Resource | `app/Filament/Helpdesk/Resources/MaterialSourcings/MaterialSourcingResource.php` | Kolom dan filter bersama |
| Detail view | `resources/views/filament/helpdesk/material-sourcings/view-sourcing.blade.php` | Ringkasan lifecycle |
| Command | `app/Console/Commands/BackfillMaterialSourcingDurationsCommand.php` | Command backfill dry-run/apply |
| Tests | `tests/Feature/MaterialWorkflow/`, `tests/Feature/MaterialSourcingTest.php` | Workflow, UI, backfill, authorization |
| Docs | `docs/products/{purchasing,rnd}/README.md`, folder fitur ini | Kontrak aktual dan laporan implementasi |

Path final mengikuti hasil audit sibling pada branch implementasi dan wajib ditambahkan ke Peta kode bila belum tercakup.

## 4. Dependency dan integration boundary

- Tidak ada package atau dependency baru.
- Database lokal menjadi satu-satunya boundary data.
- ESB tidak dipanggil oleh formatter, UI, migration, atau command backfill.
- `now()` dibaca di server dan dibekukan pada test.
- Resource/Blade hanya menyajikan data; mutation tetap berada di Action/Service.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | Audit seluruh writer `sourcing_status`, characterization test, volume/backfill source | PR implementasi yang sama | PRD disetujui |
| 1 | Migration, cast, formatter, dan timestamp pada seluruh transisi | PR implementasi yang sama | Phase 0 lulus |
| 2 | Kolom/filter index dan ringkasan detail | PR implementasi yang sama | Phase 1 lulus |
| 3 | Command backfill dry-run/apply dan audit metadata | PR implementasi yang sama | Source mapping tervalidasi |
| 4 | Test akhir, docs aktual, report, rollout verification | PR implementasi yang sama | Phase 1–3 selesai |

Fitur direncanakan dalam satu PR agar schema, writer, reader, dan backfill tidak terpisah. Commit boleh dibagi per phase untuk review.

## 6. Migration strategy

1. Buat migration additive nullable dengan `php artisan make:migration`.
2. Tambahkan `sourcing_completed_at` tanpa default dan tanpa mengubah kolom existing.
3. Jangan menjalankan backfill berat di dalam migration.
4. Buat command terpisah dengan default dry-run dan opsi apply eksplisit.
5. Proses record dalam chunk terurut ID, hanya update field null, dan laporkan counter agregat.
6. Evaluasi kebutuhan indeks memakai volume/query plan; jangan menambah indeks tanpa bukti.

## 7. Data compatibility

- Aplikasi baru wajib tetap menampilkan record dengan timestamp null.
- Aplikasi lama tetap dapat berjalan setelah kolom nullable ditambahkan.
- Backfill mempertahankan nilai existing dan tidak mengubah status atau relasi.
- Record ambigu tetap null dan tampil **Tidak Tersedia**.
- Reopen setelah rollout mengosongkan tanggal selesai aktif tetapi mempertahankan bukti lama di audit log.
- `workflow_completed_at` tidak dimigrasikan atau ditafsirkan ulang.

## 8. Authorization

- Tidak ada permission baru.
- Pertahankan Policy/Resource scope existing.
- Uji Purchasing, R&D, dan pengguna tanpa akses.
- Command backfill hanya tersedia melalui akses CLI deployment; tidak dibuat sebagai action UI.

## 9. UI

- Gunakan `TextColumn`, `Filter`, `DatePicker`, badge, modal, dan pola shared columns existing.
- Tambahkan tiga kolom bisnis tanpa membuat kartu statistik atau halaman baru.
- Pertahankan default sort existing.
- Detail memakai section ringkas sebelum daftar supplier dan tetap memiliki scroll internal.
- Uji mobile 360/390 px, desktop, dark mode yang tersedia, nama panjang, null, ongoing, selesai, dan zoom 200%.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Characterization scope, status writer, index/filter/action existing |
| 1 | Start/final/resample/reopen/resubmit untuk workflow baru dan legacy; unit formatter |
| 2 | Livewire index, filter, sorting, modal detail, null/invalid chronology, permission |
| 3 | Command dry-run/apply/idempotency/chunk/source priority/reopen ambiguity |
| 4 | Seluruh test MaterialWorkflow dan MaterialSourcing terkait, doc checks, diff check |

## 11. Observability

- Perkaya audit event final dan reopen dengan timestamp yang relevan.
- Command mengeluarkan counter agregat dan exit code non-zero hanya pada kegagalan proses, bukan karena record ambigu.
- Jangan mencetak nama produk, supplier, user, atau payload production pada output backfill.
- Tidak ada monitoring transaction baru.

## 12. Rollout

Ikuti [rollout.md](rollout.md): migration → aplikasi → dry-run → review → apply → UAT → observasi. Backfill production dan deployment tetap memerlukan keputusan user/owner operasional.

## 13. Rollback

- Rollback aplikasi terlebih dahulu; biarkan kolom dan nilai audit tetap ada.
- Hentikan command jika counter/error tidak sesuai ekspektasi.
- Jangan melakukan bulk null/delete sebagai rollback otomatis.
- Drop column hanya melalui migration terpisah setelah seluruh consumer dilepas.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Writer status terlewat | Audit repository terarah pada seluruh assignment `sourcing_status` dan test transisi |
| Backfill salah memilih siklus | Cocokkan status terminal, cycle status, chronological guard, dan bukti reopen |
| UI N+1 | Formatter hanya memakai atribut yang sudah dimuat |
| Filter memperlambat tabel | Evaluasi query plan dan indeks berdasar data |
| Clock/test flaky | Bekukan waktu dan timezone pada test |
| Perilaku legacy tidak konsisten | Test terpisah per origin/workflow |

## 15. Quality gates

- [x] `php artisan test --compact tests/Feature/MaterialWorkflow`
- [x] `php artisan test --compact tests/Feature/MaterialSourcingTest.php`
- [x] Test command backfill dan test baru spesifik fitur lulus
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `bash ops/ci/check-doc-links.sh`
- [x] `bash ops/ci/check-code-map.sh`
- [x] `git diff --check`
- [ ] CI Pull Request hijau
- [ ] Review authorization, migration, backfill, responsive UI, dan data safety

## 16. Definition of Done

- [x] Semua phase selesai sesuai PRD.
- [x] Seluruh writer status relevan tercakup test.
- [ ] Backfill dry-run staging direview sebelum apply.
- [x] Implementation report ditulis di `reports/implementation-report.md`.
- [x] README fitur, Purchasing, dan R&D diperbarui sesuai kode aktual.
- [x] Tidak ada implementation prompt tersisa setelah fitur benar-benar selesai.
