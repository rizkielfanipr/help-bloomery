---
document_id: RND-PROJECT-ACTIVE-LIST-PROMPT
title: Prompt Implementasi — Daftar Project Aktif
status: draft
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/project-active-list/prd.md
  - docs/products/rnd/project-active-list/implementation-plan.md
supersedes: null
---

# Prompt Implementasi — Daftar Project Aktif

Salin blok berikut ke agent implementasi dari root repository.

```text
Implementasikan Daftar Project Aktif pada domain Research & Development di branch `feature/rnd-active-project-list`.

Jangan langsung mengubah kode. Jalankan workflow Plan → Branch → Implement → Deliver dari `AGENTS.md` dan `.claude/skills/feature-delivery/SKILL.md`. Pastikan PRD sudah disetujui, worktree bersih, kembali ke latest `main`, lalu buat branch implementasi sebelum perubahan kode.

## Dokumen sumber yang wajib dibaca
- `AGENTS.md` — aturan repository.
- `.claude/skills/feature-delivery/SKILL.md` — workflow delivery wajib.
- `docs/products/rnd/README.md` — kontrak modul dan Peta kode.
- `docs/products/rnd/project-active-list/prd.md` — sumber requirement canonical.
- `docs/products/rnd/project-active-list/implementation-plan.md` — urutan teknis dan quality gate.
- `docs/products/rnd/project-types/prd.md` — kontrak tipe Project; scope index yang bertentangan digantikan PRD baru.
- `docs/products/rnd/project-task-calendar/prd.md` — kontrak kalender Task yang wajib dipertahankan.
- `docs/standards/code-remediation.md` — arsitektur, keamanan data, dan kualitas teknis.
- `docs/standards/ui-consistency.md` — standar UI responsif dan aksesibel.

## Hasil akhir yang diharapkan
Index Back Office → Research & Development → Project menampilkan satu daftar baris responsif berisi hanya Project aktif (`start_date <= today <= end_date`, tidak soft-deleted). Grid kartu dan filter status dihapus dari daftar utama. Search, filter tipe, count per tipe, progress, create/open/edit/archive, permission, kalender Task, dan deep-link `openTask` tetap berfungsi.

## Scope
- Terapkan active scope di query database daftar utama.
- Pisahkan sumber data daftar aktif dari kebutuhan release/task calendar agar kalender tidak kehilangan data yang sah.
- Pertahankan eager counts `products`, `premixAssemblies`, dan `partnershipMenus`.
- Ganti card grid pada custom Blade index menjadi responsive list.
- Pertahankan search nama/deskripsi dan filter tipe; hapus filter status daftar.
- Tambahkan empty state “tidak ada Project aktif” dan “hasil filter tidak ditemukan”, termasuk reset filter.
- Pertahankan create/edit modal serta open/edit/archive actions sesuai permission.
- Tambah/update test dan sinkronkan dokumentasi aktual.

## Non-scope
- Jangan membuat halaman arsip atau entry point restore baru.
- Jangan menghapus method/backend restore existing.
- Jangan mengubah detail Project, Project Product, Premix, Partnership, BOM, atau Material workflow.
- Jangan menghapus kalender Task/release fallback atau merusak deep-link dashboard.
- Jangan menambah migration, status/field, permission, role, dependency, API/ESB call, queue, scheduler, env, atau data cleanup.
- Jangan melakukan merge atau deployment.

## Modul terdampak
- `app/Filament/Helpdesk/Resources/Projects/Pages/ListProjects.php`
- `app/Filament/Helpdesk/Concerns/HasProjectTaskCalendar.php` hanya bila pemisahan query membutuhkannya
- `resources/views/filament/helpdesk/rnd-projects/index.blade.php`
- `tests/Feature/RndProjectTest.php`
- test Project Task calendar relevan
- `docs/products/rnd/README.md`
- `docs/products/rnd/project-active-list/`

Audit `ProjectResource.php`, tetapi jangan mengubah table bawaan tanpa bukti bahwa custom index menggunakannya.

## Aturan repository
- Gunakan Laravel Boost `search-docs` sebelum code change dengan query broad tentang Livewire state/computed query dan Filament custom resource list page.
- Ikuti konvensi sibling dan struktur existing.
- Jangan commit di `main`; satu concern per branch.
- Jangan menambah atau mengubah dependency.
- Gunakan `php artisan make:* --no-interaction` bila benar-benar perlu file PHP baru; scope ini diperkirakan tidak memerlukan file baru.
- Gunakan `vendor/bin/pint --dirty --format agent` setelah mengubah PHP.

## Aturan arsitektur
- Blade hanya presentasi; jangan melakukan query atau mutation di Blade.
- Terapkan active scope server-side.
- Hindari satu method ambigu yang membuat daftar active-only tetapi juga dipakai kalender lintas waktu. Gunakan nama/tanggung jawab eksplisit untuk query/collection daftar dan kalender.
- Pertahankan `withCount` agar tidak menambah N+1.
- Pertahankan urutan `updated_at desc` dan route existing.
- Jangan membuat abstraction baru bila method query eksplisit cukup.

## Aturan authorization
- Pertahankan Resource/Policy dan prefix permission `rnd projects` existing.
- Visibility tombol mengikuti `canCreate`, `canEdit`, dan `canDelete`; mutation tetap mengotorisasi server-side.
- Kalender tetap mengikuti `view rnd project tasks` dan branch/task scope existing.
- Tambahkan regression test untuk user berhak dan tidak berhak bila coverage existing belum cukup.

## Aturan UI
- Ikuti `docs/standards/ui-consistency.md`.
- Satu list container berborder, tanpa shadow dekoratif dan tanpa grid kartu.
- Desktop mudah membandingkan Project, Tipe, Periode/Rilis, Isi, Progress, dan Aksi.
- Mobile menjadi list item bertumpuk tanpa horizontal-scroll seluruh viewport.
- Nama/deskripsi panjang harus wrap/truncate aman; aksi tetap terlihat.
- Badge/status harus memiliki teks; warna bukan satu-satunya penanda.
- Icon-only action wajib mempunyai accessible name.
- Pertahankan loading/disabled state, dark variant, notification, dan confirmation existing.
- Jangan menambah kartu statistik atau heading ganda.

## Aturan integration
- Tidak ada integrasi eksternal dalam scope.
- Jangan memanggil ESB atau internet dari render maupun test.
- Jika test existing menyentuh integration, gunakan fake existing dan jangan memperluas scope.

## Aturan data safety
- Tidak ada migration/backfill.
- Jangan mengubah atau menghapus data production.
- Jangan menghapus Upcoming, Completed, atau Archived; hanya keluarkan dari daftar utama.
- Jangan menulis credential, payload sensitif, atau isi `.env` ke log/dokumentasi.

## Tahapan implementasi
1. Audit branch/worktree, kode mapped, test index/calendar, dan kontrak docs.
2. Jalankan test baseline yang relevan; catat hasil.
3. Tambahkan characterization test active/upcoming/completed/archived serta kalender/deep-link.
4. Refactor query agar daftar aktif terpisah dari sumber Project untuk kalender.
5. Hapus state/filter status daftar yang tidak lagi digunakan tanpa merusak kalender.
6. Ganti card grid menjadi responsive list dan implementasikan dua empty state.
7. Tambahkan test kombinasi search/type, boundary date, count per tipe, permission, archive/edit disappearance, dan calendar regression.
8. Jalankan test, Pint, doc checks, code-map, dan diff check.
9. Perbarui README R&D dan status PRD/README fitur sesuai kondisi nyata.
10. Buat `reports/implementation-report.md`, hapus prompt ini setelah implementasi complete, commit, push, dan buka PR ke `main` memakai template repository dalam Bahasa Indonesia.

## Test minimum
- Active Project muncul.
- Upcoming, Completed, dan Archived tidak muncul.
- Project mulai/berakhir hari ini tetap muncul.
- Search dan filter tipe tidak dapat menampilkan Project nonaktif.
- Label/count untuk Release Product, Premix, dan Partnership benar.
- Empty state tanpa active Project berbeda dari hasil filter kosong.
- Create/open/edit/archive tetap sesuai permission.
- Archive atau edit keluar periode aktif menghilangkan Project dari list.
- Task calendar, release marker yang relevan, dan `openTask` tidak regresi.

Jalankan minimal:

`php artisan test --compact tests/Feature/RndProjectTest.php`
`php artisan test --compact tests/Feature/ProjectTaskCalendarPageTest.php tests/Feature/RndProjectTaskCalendarTest.php`
`vendor/bin/pint --dirty --format agent`
`bash ops/ci/check-doc-links.sh`
`bash ops/ci/check-code-map.sh`
`git diff --check`

Sesuaikan daftar test setelah audit; laporkan nama file dan hasil aktual, jangan mengklaim full suite bila tidak dijalankan.

## Format laporan akhir
1. Branch aktif dan link Pull Request.
2. Ringkasan perubahan per phase.
3. File yang dibuat/diubah.
4. Test/quality gate yang dijalankan beserta hasil aktual.
5. Deviasi dari PRD dan alasannya.
6. Catatan migration/env/permission/queue/integration (semuanya seharusnya none).
7. Risiko, UAT staging, dan pekerjaan lanjutan termasuk pengelolaan arsip bila masih dibutuhkan.

## Larangan
- Jangan merge Pull Request.
- Jangan approve atau menjalankan deployment staging/production.
- Jangan force-push branch yang bukan branch PR sendiri.
- Jangan memperluas scope ke halaman arsip, kalender redesign, atau workflow R&D lain.
```
