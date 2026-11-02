---
document_id: RND-PROJECT-ACTIVE-LIST-REPORT
title: Laporan Implementasi — Daftar Project Aktif
status: implemented
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

# Laporan Implementasi — Daftar Project Aktif

Acuan: `docs/products/rnd/project-active-list/prd.md`

## 1. Status per phase

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 | Selesai | Branch `feature/rnd-active-project-list` | Baseline 107 test lulus sebelum perubahan; test kalender dan deep-link ditambahkan |
| 1 | Selesai | Branch yang sama | `activeProjects()` dan `calendarProjects()` menggantikan `projects()` |
| 2 | Selesai | Branch yang sama | List responsif, filter status dihapus, dua empty state |
| 3 | Selesai sebagian | Branch yang sama | Test dan dokumen selesai; CI Pull Request dan UAT staging belum dijalankan |

## 2. Ringkasan perubahan

- `ListProjects::activeProjects()` menerapkan scope aktif di query database dan mempertahankan `withCount` serta urutan `updated_at` terbaru.
- `ListProjects::calendarProjects()` menjadi sumber tanggal rilis pada kalender rilis dan kalender Task, sehingga Project Upcoming dan Completed tetap terlihat di kalender. Keduanya memakai query search/tipe yang sama.
- State `projectStatus` dan guard arsip pada `showProjectCalendar()` dihapus. `restoreProject()` tetap ada.
- Grid kartu pada `index.blade.php` diganti satu list berborder dengan header kolom mulai 1280 px dan item bertumpuk di bawahnya.
- `hasActiveProjectFilters()` dan `resetProjectFilters()` mendukung pemisahan empty state serta tombol reset.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| Permintaan implementasi user pada 2 November 2026 dicatat sebagai persetujuan PRD | PRD masih `draft` saat implementasi diminta dan keputusan scope pada §26 sudah dikonfirmasi | PRD §26 |
| Kalender tidak lagi menerima filter status, tetapi tetap mengikuti search dan tipe | Filter status dihapus dari halaman, sedangkan search/tipe pada kalender adalah perilaku existing | PRD §11, §17.7 |
| Layout kolom memakai breakpoint `xl`, bukan `lg` | Sidebar panel menyisakan ruang terlalu sempit untuk enam kolom pada 1024 px | PRD §17.3–17.4 |
| Aksi buka menjadi ikon pada desktop dan tombol berlabel pada mobile | Menjaga lebar kolom Project pada desktop dengan accessible name tetap tersedia | PRD §17.5 |
| Badge status per baris dihapus | Seluruh baris selalu Active dan PRD §17.5 tidak mensyaratkannya | PRD §17.5 |
| `calendarProjects()` tidak memuat `withCount` | Kalender tidak memakai jumlah isi, sehingga query lebih ringan | PRD §18 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §9 dan plan §8 | Nama permission dikoreksi dari `update rnd projects` menjadi `edit rnd projects`; `restore rnd projects` dihapus dari plan | Permission tersebut tidak ada di `config/permissions.php`; `HasPermissions` memakai `edit` untuk edit dan restore | Dokumen sudah dikoreksi pada branch ini |
| Prompt §Test minimum | Tiga skenario test lama ditulis ulang, tidak dihapus | Skenario tersebut menguji filter status yang dihapus PRD | Tidak ada |

## 5. Test dan verifikasi

- Baseline sebelum perubahan: 107 test lulus (`RndProjectTest`, `RndProjectTypesTest`, `ProjectTaskCalendarPageTest`, `RndProjectTaskCalendarTest`, `ProjectTaskQueryPerformanceTest`, `ProjectTaskInstructionAttachmentTest`).
- Setelah perubahan: 223 test lulus, 1.917 assertion, pada 13 file test yang menyentuh index Project (enam file di atas, `ProjectTaskDashboardTest`, `RndProjectMaterialForecastTest`, `RndProjectTaskTemplateResourceTest`, `RbacResourceTest`, `RndProjectBomExportTest`, `BomRecipePageTest`, `RndProjectTaskFollowUpDemoSeederTest`).
- Full Feature suite belum dijalankan secara lokal; CI Pull Request menjadi gate-nya.
- Halaman dirender di Chromium headless dengan data test berisi nama panjang tanpa spasi. Lebar 360, 390, 768, 1024, 1280, dan 1440 px tidak menunjukkan horizontal-scroll dokumen maupun elemen list yang meluap.
- Belum diperiksa: dark mode secara visual, zoom browser 200%, serta interaksi konfirmasi arsip di browser dengan Livewire aktif. Ketiganya masuk UAT staging.

## 6. Migration dan konfigurasi

Tidak ada migration, env, permission baru, queue, scheduler, dependency, atau integrasi eksternal.

## 7. Risiko dan pekerjaan lanjutan

- Project yang diarsipkan tidak lagi dapat dipulihkan dari UI, walaupun notifikasi arsip menyebut data dapat dipulihkan. Pemulihan memerlukan UI arsip terpisah bila dibutuhkan.
- Project Upcoming dan Completed hanya dapat dicapai dari marker kalender atau URL detail.
- Asset perlu dibangun ulang karena ada class Tailwind baru; pipeline deploy existing menjalankan langkah build tersebut.
