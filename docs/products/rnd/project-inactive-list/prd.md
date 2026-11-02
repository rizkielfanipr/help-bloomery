---
document_id: RND-PROJECT-INACTIVE-LIST-PRD
title: PRD — Daftar Project Nonaktif
status: implemented
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/README.md
  - docs/products/rnd/project-active-list/prd.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# PRD — Daftar Project Nonaktif

> Dokumen ini melanjutkan pekerjaan lanjutan pada §25–26 [PRD Daftar Project Aktif](../project-active-list/prd.md). Kontrak tab Daftar pada dokumen tersebut tetap berlaku.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Research & Development |
| Fitur | Daftar Project Nonaktif |
| Status | implemented |
| Prioritas | TBD — belum ditetapkan owner bisnis |
| Target pengguna | User Back Office dengan akses Project R&D |
| Modul terdampak | Research & Development → Project |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

Daftar utama Project hanya memuat Project aktif, sehingga Project Upcoming dan Completed hanya dapat dicapai melalui kalender atau URL, sedangkan Project yang diarsipkan tidak dapat dilihat maupun dipulihkan dari UI. Fitur ini menambah tab Nonaktif dengan filter status dan aksi pulihkan, tanpa mengubah scope tab Daftar.

## 3. Latar belakang

Saat merilis daftar aktif, entry point filter Diarsipkan ikut hilang walaupun method `restoreProject` tetap tersedia. Notifikasi arsip menyatakan data dapat dipulihkan, tetapi pengguna tidak memiliki jalur untuk melakukannya.

## 4. Baseline sistem

- `ListProjects::activeProjects()` menjadi sumber tab Daftar; `calendarProjects()` menjadi sumber tanggal rilis kalender.
- `ListProjects::restoreProject()` tersedia dan diotorisasi dengan `ProjectResource::canRestore()`, yaitu permission `edit rnd projects`.
- `openEditProjectModal()` dan `archiveProject()` hanya menemukan record yang tidak terarsip.
- Resource tidak memuat record soft-deleted, sehingga halaman detail Project yang diarsipkan tidak dapat dibuka.

## 5. Problem statement

User R&D tidak dapat menelusuri Project yang belum dimulai, sudah selesai, atau diarsipkan, dan tidak dapat memulihkan arsip dari aplikasi.

## 6. Goals

1. Menampilkan Project nonaktif dalam satu tab dengan filter status.
2. Memulihkan Project yang diarsipkan dari UI sesuai permission.
3. Mempertahankan tab Daftar yang hanya menampilkan Project aktif.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Project aktif pada tab Nonaktif | Belum ada tab | 0 | Feature test dengan tanggal terkontrol |
| Project nonaktif pada tab Daftar | 0 | 0 | Feature test |
| Project arsip yang dapat dipulihkan dari UI | Tidak ada | Seluruh Project arsip bagi user berizin | Feature test dan UAT |

## 8. Non-goals

- Tidak menambah status, field, tabel, migration, atau backfill.
- Tidak menambah permission atau role.
- Tidak membuat route atau halaman baru.
- Tidak membuka halaman detail untuk Project yang diarsipkan.
- Tidak menyediakan penghapusan permanen.
- Tidak mengubah kalender Task, kalender rilis, atau isi workspace Project.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Viewer Project R&D | Melihat, mencari, dan memfilter Project nonaktif | `view rnd projects` |
| Editor Project R&D | Mengubah Project Upcoming/Completed dan memulihkan arsip | `edit rnd projects` |
| Archiver Project R&D | Mengarsipkan Project Upcoming/Completed | `delete rnd projects` |

## 10. User journey

1. User membuka Research & Development → Project lalu memilih tab Nonaktif.
2. Sistem menampilkan Project Upcoming, Completed, dan Archived, diurutkan dari yang terbaru diubah.
3. User memfilter berdasarkan status, tipe, atau kata kunci.
4. User membuka atau mengubah Project Upcoming/Completed, atau memulihkan Project yang diarsipkan setelah konfirmasi.
5. Project yang dipulihkan kembali ke tab Daftar bila periodenya mencakup hari ini; selain itu tetap di tab Nonaktif dengan status waktunya.

## 11. Business rules

1. Project nonaktif adalah record soft-deleted, atau record dengan `start_date > today`, atau record dengan `end_date < today`.
2. Status Archived mengalahkan status waktu: Project yang diarsipkan selalu berlabel Archived, apa pun periodenya.
3. Filter Upcoming dan Completed hanya memuat record yang tidak diarsipkan; filter Diarsipkan hanya memuat record soft-deleted.
4. Nilai filter status yang tidak dikenal diperlakukan sebagai Semua Nonaktif dan tidak boleh menampilkan Project aktif.
5. Search dan filter tipe memakai state yang sama dengan tab Daftar dan kalender.
6. Pemulihan hanya mengembalikan record; periode dan data lain tidak berubah.
7. Project yang diarsipkan hanya memiliki aksi pulihkan dan tidak memiliki tautan detail.
8. Scope dihitung server-side dan aksi tetap diotorisasi server-side.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Tab Nonaktif tersedia bagi user dengan `view rnd projects` | Must |
| FR-2 | Tab Nonaktif hanya memuat Project sesuai §11 | Must |
| FR-3 | Filter status: Semua Nonaktif, Upcoming, Completed, Diarsipkan; hanya tampil pada tab Nonaktif | Must |
| FR-4 | Search dan filter tipe berlaku di dalam scope nonaktif | Must |
| FR-5 | Setiap baris menampilkan identitas, tipe, status teks, periode, jumlah isi, dan aksi | Must |
| FR-6 | User berizin dapat memulihkan Project arsip setelah konfirmasi | Must |
| FR-7 | Project Upcoming/Completed dapat dibuka, diubah, dan diarsipkan sesuai permission | Must |
| FR-8 | Daftar memakai pagination 20 record per halaman | Should |
| FR-9 | Empty state membedakan tidak ada Project nonaktif dan hasil filter kosong, termasuk reset filter | Should |
| FR-10 | Tab Daftar dan kalender tidak mengalami regresi | Must |

## 13. Status dan lifecycle

Tidak ada status tersimpan baru. Label Upcoming, Completed, dan Archived memakai derivasi existing pada [PRD Daftar Project Aktif §13](../project-active-list/prd.md).

## 14. Model data konseptual

Menggunakan `RndProject` existing beserta soft delete. Tidak ada perubahan schema, cast, relasi, atau constraint.

## 15. Integration contract

Not applicable — fitur membaca dan memulihkan record pada database lokal.

## 16. Authorization dan security

- Tab mengikuti akses halaman `view rnd projects`.
- Pemulihan memerlukan `ProjectResource::canRestore()` dan ditolak server-side bila tidak berizin.
- Edit dan arsip mengikuti `canEdit` dan `canDelete` existing.
- Tidak ada perubahan company/branch scope.

## 17. UI/UX specification

- Toggle halaman menjadi Daftar, Nonaktif, dan Kalender.
- Filter status berada di samping filter tipe dan hanya tampil pada tab Nonaktif.
- Baris memakai pola list yang sama dengan tab Daftar; kolom Progress digantikan Status.
- Badge status memakai teks; warna mengikuti palet existing pada halaman Project.
- Aksi Project arsip: tombol Pulihkan dengan konfirmasi, loading/disabled state, dan accessible name.
- Tanpa data: "Belum ada project nonaktif." Hasil filter kosong: "Project nonaktif tidak ditemukan." dengan Reset Filter.
- Pagination memakai komponen `x-rnd.numbered-pagination`.

## 18. Non-functional requirements

- Scope, filter, dan pagination dijalankan di query database.
- Jumlah isi memakai `withCount` agar tidak terjadi N+1.
- View diperiksa minimal pada viewport 360, 390, 768, 1024, dan 1440 px tanpa horizontal-scroll halaman.

## 19. Observability dan audit

Not applicable — perubahan ini tidak menambah log atau audit; pemulihan memakai perilaku soft delete existing.

## 20. Data migration dan kompatibilitas

Tidak ada migration atau backfill. Project lama yang diarsipkan langsung terlihat pada filter Diarsipkan.

## 21. Testing strategy

Feature test Livewire dengan tanggal terkontrol untuk scope, kombinasi filter, label status, aksi per status dan permission, penolakan server-side, pemulihan, pagination, empty state, serta regresi tab Daftar dan kalender.

## 22. Rollout

Satu PR aplikasi. Tidak memerlukan feature flag, migration, env, permission sync, queue restart, atau langkah data manual. UAT staging dilakukan sebelum approval production.

## 23. Rollback

Revert PR. Project yang sudah dipulihkan tetap aktif sebagai record biasa dan tidak memerlukan rollback data.

## 24. Acceptance criteria

1. Tab Nonaktif hanya menampilkan Project Upcoming, Completed, dan Archived.
2. Filter status, tipe, dan search tidak dapat menampilkan Project aktif.
3. Tab Daftar tetap hanya menampilkan Project aktif.
4. User berizin dapat memulihkan Project arsip; user tanpa izin tidak melihat tombol dan ditolak server-side.
5. Project arsip tidak memiliki aksi buka, edit, atau arsip.
6. Pagination dan kedua empty state berfungsi.
7. Test terdampak, Pint, doc-link, code-map, dan diff check lulus.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Tab Nonaktif menampilkan Project aktif | Duplikasi dengan tab Daftar | Scope server-side dan test filter tidak dikenal |
| Jumlah arsip besar | Halaman berat | Pagination di database |
| Halaman tertinggal setelah pemulihan record terakhir | Daftar tampak kosong | Halaman disesuaikan ke halaman terakhir yang tersedia |
| Toggle tiga tombol sempit di mobile | Filter meluap dari kartu | Ikon toggle disembunyikan di bawah 640 px dan pemeriksaan viewport |

## 26. Open decisions

Tidak ada keputusan yang memblokir implementasi.

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 1 | Query, state, pemulihan, UI, test, dan dokumentasi | Satu PR aplikasi |

## 28. Definition of Done

- [x] Seluruh acceptance criteria terpenuhi.
- [x] Test terdampak ditambahkan dan lulus.
- [x] Authorization diuji untuk user berhak dan tidak berhak.
- [x] Tidak ada migration, dependency, permission, atau integrasi baru.
- [x] README domain diperbarui.
- [x] Pull Request ke `main` siap direview dan belum di-merge/deploy tanpa keputusan user.
