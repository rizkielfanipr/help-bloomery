---
document_id: RND-PROJECT-OVERVIEW-RICH-TEXT-REPORT
title: Laporan Implementasi — Project Overview Rich Text
status: implemented
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/project-overview-rich-text/prd.md
  - docs/products/rnd/project-overview-rich-text/rollout.md
supersedes: null
---

# Laporan Implementasi — Project Overview Rich Text

Acuan: `docs/products/rnd/project-overview-rich-text/prd.md`

## 1. Status per phase

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 — Audit dan characterization | Selesai | Branch `feature/project-overview-rich-text` | Baseline 107 test hijau; output BOM dikunci sebelum refactor |
| 1 — Migration, model, shared service | Selesai | Branch yang sama | BOM dipindahkan ke `RndRichContentService` |
| 2 — Mutation dan UI | Selesai | Branch yang sama | Action, partial, dan RichEditor lazy |
| 3 — Hardening | Selesai pada level test | Branch yang sama | Pemeriksaan browser belum dilakukan, lihat §5 |
| 4 — Delivery | Selesai | Branch yang sama | Merge, deployment, dan UAT tetap keputusan user |

## 2. Ringkasan perubahan

- Migration menambah `rnd_projects.overview_html` nullable LONGTEXT.
- `RndRichContentService` memuat sanitasi, render, validasi path, ekstraksi path, dan deteksi orphan.
- `UpdateRndProjectOverviewAction` melakukan authorization, validation, sanitasi, persistence, dan cleanup file.
- `ViewProject` menambah action modal RichEditor, dan partial `project-overview` menampilkan konten atau empty state.
- Daftar Project memilih kolom eksplisit (`RndProject::LIST_COLUMNS`), sehingga Overview tidak ikut query daftar.
- Modal Create dan Edit Project menambah helper bahwa deskripsi adalah ringkasan.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| Overview memakai allowlist parser `symfony/html-sanitizer`, bukan regex BOM | Regex BOM meloloskan handler tanpa kutip dan traversal, sedangkan PRD §16.2 melarangnya | PRD §16.2 |
| Library sanitizer dipakai dari dependency Filament yang sudah terpasang | Tidak ada perubahan `composer.json` atau lockfile | PRD §8 |
| Output BOM dipertahankan persis melalui `sanitizeLegacy()` | Prompt mewajibkan BOM tanpa perubahan output; konten tersimpan dan PDF bergantung padanya | PRD §16.3 |
| `overview_html` masuk `$hidden` | `ViewRecord` menyalin `attributesToArray()` ke `$data`, sehingga HTML mentah ikut snapshot Livewire | PRD §18 |
| Konten disanitasi lagi saat dibaca | Baris yang berubah di luar Action tidak dapat dirender tanpa sanitasi | PRD §16 |
| HTML hasil render di-cache 15 menit per Project dan isi | Halaman dirender ulang pada setiap interaksi, dan render bergambar memanggil storage per gambar. URL sementara berlaku minimal 30 menit | PRD §18 |
| `preventFileAttachmentPathTampering()` dengan callback path ketat | Tanpa ini Filament membuat URL bertanda tangan untuk `data-id` apa pun pada disk yang sama sebelum sanitizer berjalan | PRD §16.1 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §14, §24 butir 11 | `data-model.md` tidak diregenerasi | Generator tidak ada di repository, dan dokumen hanya memuat model, tabel, dan relasi, yang semuanya tidak berubah | Tidak ada |
| §16.3 | Service memiliki dua jalur sanitasi, bukan satu aturan untuk BOM dan Overview | Aturan ketat akan mengubah output BOM | Lihat §7 |
| §21 | Test responsive, dark mode, dan modal hanya memeriksa markup dan state | Tidak ada test browser pada sesi ini | UAT staging |

## 5. Test dan verifikasi

Hasil aktual dicatat pada Pull Request. Cakupan `tests/Feature/RndProjectOverviewTest.php`: migration, tiga tipe Project, permission, normalisasi kosong, payload XSS, tautan aman, isolasi path, upload, cleanup file, archive/restore, regresi description dan daftar, modal lazy, serta snapshot Livewire. `tests/Feature/BomRecipePageTest.php` menambah characterization output sanitasi, orphan, dan render BOM yang lulus sebelum dan sesudah refactor.

**Belum diverifikasi:** upload nyata ke R2/B2 dan URL sementaranya (test memakai `Storage::fake`), serta di browser: toolbar, resize gambar, fokus keyboard, Escape, backdrop, viewport 360–1440 px, zoom 200%, dan dark mode. UAT lokal pada `rollout.md` belum dijalankan.

## 6. Migration dan konfigurasi

Satu migration additive tanpa backfill. Tidak ada env, permission, queue, scheduler, dependency, API, atau integrasi baru. Class Tailwind baru ikut terkompilasi melalui build aplikasi biasa. Jangan menjalankan `down()` di production setelah Overview berisi data tanpa backup dan persetujuan.

## 7. Risiko dan pekerjaan lanjutan

- **Sanitizer BOM menyimpan dua celah lama** yang sengaja tidak diubah pada PR ini:
  - Handler tanpa kutip seperti `<p onclick=alert(1)>` lolos dan dirender mentah pada konten tanpa gambar.
  - `data-id` dengan `../` lolos cek prefix, sehingga orphan cleanup BOM dapat menghapus file di luar prefix.
  Keduanya perlu perbaikan terpisah dengan audit konten tersimpan.
- File baru bisa tertinggal bila cleanup storage gagal; kejadian ini dicatat sebagai warning tanpa path atau HTML.
- Edit bersamaan mengikuti last-write-wins sesuai PRD.
