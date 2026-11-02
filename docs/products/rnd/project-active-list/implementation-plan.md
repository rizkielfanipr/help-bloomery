---
document_id: RND-PROJECT-ACTIVE-LIST-PLAN
title: Implementation Plan — Daftar Project Aktif
status: implemented
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/project-active-list/prd.md
supersedes: null
---

# Implementation Plan — Daftar Project Aktif

Acuan requirement: `docs/products/rnd/project-active-list/prd.md`. Dokumen ini hanya mengatur urutan teknis; requirement tidak ditulis ulang di sini.

## 1. Scope implementasi

- **Termasuk:** query daftar aktif server-side, pemisahan sumber data daftar dan kalender, penggantian grid kartu menjadi list responsif, penyederhanaan filter, empty state, regression test, dan pembaruan dokumentasi aktual.
- **Tidak termasuk:** halaman arsip baru, perubahan detail Project, penghapusan kalender Task, migration, data cleanup, permission baru, integrasi ESB, dependency, queue, scheduler, atau deployment.

## 2. Baseline repository

- Baseline per 2 November 2026: branch `main` sinkron dengan `origin/main` dan worktree bersih saat branch dokumentasi dibuat.
- `ListProjects::$projectView` default bernilai `list`, tetapi Blade merender grid kartu.
- `ListProjects::projects()` melayani daftar, release calendar, dan overlay release pada kalender Task; refactor harus mencegah active-only scope bocor ke kebutuhan kalender.
- Query default saat ini memuat seluruh Project non-arsip dan baru menjadi aktif bila `projectStatus = active`.
- Custom view menangani filter, create/edit modal, list/card, release calendar, dan task calendar.
- Test terkait index/calendar berada di `tests/Feature/RndProjectTest.php`.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Livewire Page | `app/Filament/Helpdesk/Resources/Projects/Pages/ListProjects.php` | Ubah query/list state; pisahkan query kalender bila diperlukan |
| Concern kalender | `app/Filament/Helpdesk/Concerns/HasProjectTaskCalendar.php` | Ubah hanya bila perlu memanggil sumber Project kalender yang terpisah |
| Blade index | `resources/views/filament/helpdesk/rnd-projects/index.blade.php` | Ganti card grid dengan responsive list; hapus status filter daftar; tambah empty state/reset |
| Test | `tests/Feature/RndProjectTest.php` | Tambah/ubah characterization dan regression test |
| Dokumen modul | `docs/products/rnd/README.md` | Deskripsikan perilaku aktual setelah implementasi |
| PRD terkait | `docs/products/rnd/project-types/prd.md` | Catatan scope index yang digantikan dokumen canonical baru |
| Dokumen fitur | `docs/products/rnd/project-active-list/` | Status, report implementasi, dan penghapusan prompt setelah selesai |

`ProjectResource.php` diaudit tetapi tidak harus diubah karena table bawaan tidak menjadi renderer utama custom index.

## 4. Dependency dan integration boundary

Tidak ada package atau sistem eksternal yang disentuh. Boundary implementasi:

- Page/Query bertanggung jawab membatasi data aktif dan eager counts.
- Blade hanya mempresentasikan collection/state; tidak menjalankan query.
- Resource/Policy existing tetap menjadi sumber authorization.
- Kalender Task memakai query task existing dan tidak boleh dibatasi hanya karena daftar Project aktif.
- Model/schema tidak diubah.

Sebelum perubahan kode, gunakan Laravel Boost `search-docs` untuk topik Livewire computed/render state dan Filament custom resource page sesuai aturan repository.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | Audit test existing dan tambah characterization active/list/calendar | Satu PR fitur | PRD disetujui; branch dari latest `main` |
| 1 | Pisahkan query daftar aktif dari data kalender | PR yang sama | Test Phase 0 tersedia |
| 2 | Ubah filter dan Blade menjadi responsive list | PR yang sama | Query active-only lulus |
| 3 | Regression, UAT checklist, docs aktual, report, delivery | PR yang sama | Phase 1–2 selesai |

Implementasi direncanakan sebagai satu branch `feature/rnd-active-project-list` dan satu Pull Request karena satu concern dan tanpa migration.

## 6. Migration strategy

Not applicable — tidak ada perubahan schema, index database, constraint, atau backfill.

## 7. Data compatibility

- Seluruh record existing tetap disimpan.
- Active dihitung dari field tanggal dan soft delete existing.
- Upcoming, Completed, dan Archived hanya tidak muncul pada daftar utama; data dan route detail tidak dihapus.
- Count relasi per tipe mempertahankan sumber existing.
- Urutan `updated_at desc` dipertahankan.
- Restore backend tidak dihapus walaupun entry point Archived dikeluarkan dari index.

## 8. Authorization

- Tidak ada permission baru atau perubahan `config/permissions.php`.
- Pertahankan `view/create/edit/delete rnd projects` sesuai `HasPermissions` existing; restore memakai `edit rnd projects`.
- Pertahankan `view rnd project tasks` untuk kalender.
- Test visibility UI dan penolakan server-side pada mutation tetap diperlukan.

## 9. UI

1. Pertahankan header, CTA Buat Project, dan modal create/edit existing.
2. Hapus select status dari area filter daftar.
3. Pertahankan search dan select tipe.
4. Ganti `<section class="grid ...">` dan article card menjadi satu list container dengan header kolom desktop bila membantu.
5. Setiap item merender identitas, badge tipe, periode, count kontekstual, progress, dan aksi.
6. Gunakan responsive stacking pada mobile; tidak membuat horizontal-scroll seluruh halaman.
7. Sediakan dua empty state: tidak ada Project aktif dan tidak ada hasil filter/search.
8. Pertahankan task calendar toggle dan fallback release calendar sesuai permission existing; jangan menduplikasi heading atau membuat kartu statistik baru.
9. Gunakan Heroicons, focus state, `aria-label`, loading state, dark variant, dan token existing dari `ui-consistency.md`.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Baseline render, create/edit/archive, permission, calendar, dan `openTask` |
| 1 | Active muncul; Upcoming/Completed/Archived tidak; boundary hari ini; search/type tidak menembus active scope |
| 2 | Copy list/empty state, label/count per tipe, action visibility, reset filter, dan responsive markup yang dapat dikarakterisasi |
| 3 | Seluruh test `RndProjectTest.php` terdampak, Project Task calendar tests relevan, doc checks, Pint, dan diff check |

Perintah minimum yang direncanakan:

```bash
php artisan test --compact tests/Feature/RndProjectTest.php
php artisan test --compact tests/Feature/ProjectTaskCalendarPageTest.php tests/Feature/RndProjectTaskCalendarTest.php
vendor/bin/pint --dirty --format agent
bash ops/ci/check-doc-links.sh
bash ops/ci/check-code-map.sh
git diff --check
```

Tambahkan filter/test file lain hanya bila audit dependency menunjukkan dampak nyata. Jangan memanggil ESB sungguhan.

## 11. Observability

Tidak menambah log/audit baru. Pada implementasi, catat perbandingan query count dan waktu render secara lokal/UAT bila perubahan pemisahan query memengaruhi kalender. Error aplikasi dan regression test menjadi guardrail utama.

## 12. Rollout

- Deploy mengikuti pipeline aplikasi existing setelah PR di-merge dan staging lulus.
- Tidak ada migration, env, permission sync, worker, scheduler, atau manual data operation.
- UAT staging: viewer, creator, editor, archiver, user Task, dan user tanpa Task permission; viewport 360/390/768/1024/1440.
- Production approval tetap keputusan user/reviewer yang berwenang.

## 13. Rollback

Revert PR aplikasi. Karena tidak ada perubahan data/schema, rollback tidak memerlukan restore database. Verifikasi index kembali ke UI/query sebelumnya dan kalender tetap dapat dibuka.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Active scope dipakai ulang kalender | Buat method/query khusus daftar dan kalender; test keduanya |
| N+1 dari count per tipe | Pertahankan `withCount` existing dan uji query review |
| Aksi terpotong pada mobile | Stack content/aksi dan uji viewport minimum |
| Empty state salah saat filter aktif | Turunkan state dari search/type dan test kedua kasus |
| Restore kehilangan entry point | Jangan hapus backend; catat sebagai non-scope/follow-up |
| Custom table `ProjectResource` diedit tanpa efek | Fokus pada custom `ListProjects` + Blade; ubah Resource hanya bila terbukti dipakai |

## 15. Quality gates

- [x] PRD disetujui melalui permintaan implementasi user pada 2 November 2026.
- [x] Branch implementasi dibuat dari latest `main`; tidak commit di `main`.
- [x] Laravel Boost `search-docs` dijalankan sebelum code change.
- [x] Characterization/regression test ditulis dan lulus.
- [x] `vendor/bin/pint --dirty --format agent` lulus.
- [x] `bash ops/ci/check-doc-links.sh` lulus.
- [x] `bash ops/ci/check-code-map.sh` lulus.
- [x] `git diff --check` lulus.
- [ ] CI Pull Request hijau.
- [ ] Review authorization, data safety, responsive UI, dan kalender selesai.

## 16. Definition of Done

- [x] Semua phase selesai sesuai PRD.
- [x] Daftar aktif dan kalender mempunyai boundary query yang tidak saling merusak.
- [x] Test aktual dan hasilnya dicatat.
- [x] README R&D cocok dengan kode setelah implementasi.
- [x] PRD/README fitur berstatus sesuai lifecycle sebenarnya.
- [x] `reports/implementation-report.md` dibuat.
- [x] `implementation-prompt.md` dihapus setelah implementasi selesai.
- [ ] Pull Request berbahasa Indonesia menggunakan template repository.
