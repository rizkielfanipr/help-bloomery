---
document_id: RND-PROJECT-OVERVIEW-RICH-TEXT-PRD
title: PRD — Project Overview Rich Text
status: draft
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/README.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
  - docs/architecture/data-model.md
supersedes: null
---

# PRD — Project Overview Rich Text

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Research & Development |
| Fitur | Project Overview Rich Text |
| Status | draft |
| Prioritas | TBD — belum ditetapkan owner bisnis |
| Target pengguna | User Back Office yang melihat/mengelola Project R&D |
| Modul terdampak | Research & Development → Project → Detail Project |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

Detail Project saat ini hanya memiliki `description` plain text singkat yang juga dipakai oleh daftar dan pencarian. User membutuhkan informasi Project yang lebih lengkap dan terformat seperti Informasi Tambahan pada BOM. Fitur menambahkan field `overview_html` additive, section Project Overview, native Filament RichEditor, attachment gambar private B2/R2, sanitasi server-side, dan authorization existing tanpa mengubah kontrak deskripsi ringkas.

## 3. Latar belakang

Project R&D membutuhkan konteks yang lebih kaya daripada satu paragraf: tujuan, ruang lingkup, konsep produk, rencana trial, keputusan, referensi, dan gambar. Pola rich-text sudah tersedia pada Informasi Tambahan & Cara Pembuatan BOM melalui Filament RichEditor. Namun sanitizer dan attachment lifecycle BOM masih berada sebagai private logic pada `ViewProjectProductPage`, sehingga implementasi Overview harus mengekstrak boundary reusable yang aman, bukan menyalin regex atau mempercayai HTML browser.

## 4. Baseline sistem

- `RndProject.description` adalah nullable `TEXT` plain text dan berada di `$fillable`.
- Description dibuat/diedit melalui `ListProjects` dan `ViewProject`, diteruskan ke `CreateRndProjectAction`/`UpdateRndProjectAction`, lalu ditampilkan escaped pada header dan list.
- Search Project memakai `LIKE` terhadap nama dan `description`.
- `ProjectResource::table()` juga memakai description sebagai excerpt plain text.
- Detail Project tidak mempunyai section overview panjang atau attachment pada level Project.
- Informasi BOM memakai `Filament\Forms\Components\RichEditor`, toolbar formatting/list/link/attachment, private disk `b2`, gambar JPEG/PNG/WebP maksimum 8 MB, resizable images, dan modal scrollable.
- BOM menyimpan sanitized HTML pada `RndBomInstruction.content_html`, merender private attachment melalui `RichContentRenderer`, dan membersihkan orphaned files.
- Sanitizer BOM mengizinkan subset tag, membuang event handlers/script/unsafe scheme, membatasi image source/path, dan menjaga safe link attributes.
- Permission Project existing: `view rnd projects`, `edit rnd projects`, serta permission create/delete lain yang tidak berubah.
- Semua tipe Project (`release_product`, `premix`, `partnership`) memakai halaman detail induk yang sama.
- Audit schema melalui local Artisan tidak tersedia pada sesi planning karena sandbox/MySQL, sehingga implementasi wajib memverifikasi schema aktual dengan Laravel Boost `database-schema` sebelum migration.

## 5. Problem statement

Description tidak mendukung struktur, daftar, link, atau gambar, sehingga konteks Project tersebar di komunikasi eksternal atau catatan lain. Mengubah description langsung menjadi HTML berisiko memasukkan markup ke daftar/search, memperbesar payload index, dan membuka regresi XSS. Dibutuhkan rich overview terpisah yang aman dan backward-compatible.

## 6. Goals

1. Menyediakan Project Overview rich-text pada detail seluruh tipe Project.
2. Mempertahankan description sebagai ringkasan plain text untuk create/edit/list/search/header.
3. Menggunakan editor dan pola UX existing tanpa dependency baru.
4. Menyimpan dan merender HTML hanya setelah sanitasi server-side.
5. Mendukung attachment gambar private dengan lifecycle yang aman.
6. Mengekstrak rich-content security boundary reusable agar BOM dan Overview konsisten.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Project dengan rich overview | Tidak tersedia | Dapat dibuat/dibaca pada seluruh tipe | Feature/Livewire test dan UAT |
| Payload berbahaya tersimpan/dirender | Guardrail hanya ada pada BOM | 0 payload XSS lolos | Security tests |
| Attachment lintas Project | Belum ada feature | 0 path lintas Project diterima | Path-isolation tests |
| Regresi description/list/search | Description plain text existing | 0 perubahan kontrak | Regression tests |
| Regresi Informasi BOM setelah extraction | Baseline existing | 0 perubahan hasil/test | BomRecipePageTest |

## 8. Non-goals

- Tidak mengganti, menghapus, atau mengubah `description` menjadi HTML.
- Tidak mencari Project berdasarkan isi Overview pada phase ini.
- Tidak menyediakan editor Overview pada modal Create Project.
- Tidak menyalin Overview ke PDF BOM, SOP, Excel, atau export lain.
- Tidak membuat version history, approval, revision workflow, conflict resolution, atau collaborative editing.
- Tidak membuat Overview per Product/Menu; Overview berada di level Project.
- Tidak menghasilkan konten dengan AI.
- Tidak menerima PDF/video/dokumen pada RichEditor; attachment scope adalah gambar.
- Tidak menambah role/permission, queue, scheduler, API, atau integrasi eksternal baru.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Viewer Project | Membaca Overview pada Project yang dapat diakses | `view rnd projects` |
| Editor Project | Menambah, mengubah, atau mengosongkan Overview | `edit rnd projects` |
| Sistem | Sanitasi, validasi path, persistence, render private attachment, cleanup orphan | Server-side |

## 10. User journey

1. User membuat Project dengan nama/deskripsi/periode melalui alur existing.
2. User membuka detail Project dan melihat section **Project Overview** setelah identitas/ringkasan Project.
3. Jika kosong, user berpermission edit melihat CTA **Tambahkan Overview**.
4. User membuka modal rich editor, menulis heading/paragraf/list/link dan dapat menyisipkan gambar.
5. User menyimpan; sistem authorize ulang, sanitasi HTML, validasi seluruh attachment prefix, menyimpan konten, dan membersihkan orphan.
6. Detail menampilkan sanitized rich content dan private image.
7. User dapat membuka kembali **Edit Overview**; Cancel/Escape/backdrop tidak menyimpan perubahan.

## 11. Business rules

1. `description` tetap plain text dan menjadi sumber list/search/header.
2. `overview_html` hanya ditampilkan pada detail Project dan tidak dimuat untuk index tanpa kebutuhan.
3. Overview berlaku untuk Release Product, Premix, dan Partnership.
4. Project harus tersimpan sebelum Overview dapat diedit.
5. Empty/semantic-empty rich content dinormalisasi menjadi `null`.
6. Semua mutation wajib authorize `edit rnd projects` server-side terhadap Project target.
7. HTML browser dianggap tidak tepercaya dan wajib disanitasi sebelum persistence.
8. Hanya tag/attribute/scheme pada allowlist §16 yang boleh tersimpan.
9. Gambar RichEditor hanya boleh merujuk private attachment prefix Project tersebut.
10. Base64 image, arbitrary external image, attachment Project lain, dan path traversal ditolak/dibuang.
11. Upload baru yang gagal tersimpan harus dibersihkan; attachment yang dihapus dari konten harus dihapus setelah save berhasil.
12. Mengarsipkan/restore Project mempertahankan Overview dan attachment.
13. Save Overview memperbarui timestamp Project sesuai mutation record existing; tidak menambah lifecycle status.
14. Last-write-wins mengikuti edit Project existing; concurrency/version history berada di luar scope.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Tambah nullable `overview_html` LONGTEXT pada `rnd_projects` | Must |
| FR-2 | Tampilkan section Project Overview pada detail seluruh tipe Project | Must |
| FR-3 | Viewer melihat sanitized content atau empty state | Must |
| FR-4 | Editor membuka lazy-mounted native Filament RichEditor melalui CTA | Must |
| FR-5 | Toolbar mendukung emphasis, h2/h3, ordered/bullet list, link, image, undo/redo | Must |
| FR-6 | Gambar JPEG/PNG/WebP private B2/R2 maksimum 8 MB dan resizable | Must |
| FR-7 | Mutation menggunakan Action dan shared sanitizer/renderer/attachment policy | Must |
| FR-8 | Unsafe tags, events, URL schemes, image source, dan path dibuang/ditolak | Must |
| FR-9 | Orphan/failed-upload cleanup berjalan tanpa menghapus file aktif | Must |
| FR-10 | Description/list/search/create/edit existing tetap plain dan kompatibel | Must |
| FR-11 | Informasi BOM tetap berperilaku sama setelah shared service extraction | Must |
| FR-12 | Empty/loading/error/success/permission/mobile/dark states jelas | Should |

## 13. Status dan lifecycle

Tidak ada status database baru. State Overview:

| State | Makna | Transisi | Aktor |
|---|---|---|---|
| Empty | `overview_html` null/semantic-empty | Tambahkan Overview | Editor |
| Editing | Draft hanya berada di editor/modal | Save atau Cancel | Editor |
| Available | Sanitized HTML tersimpan | Edit atau kosongkan | Editor |
| Save Failed | Persistence/upload gagal; konten lama tetap | Perbaiki/coba lagi | Editor |

## 14. Model data konseptual

Migration additive baru pada `rnd_projects`:

| Field | Tipe | Null | Fungsi |
|---|---|---:|---|
| `overview_html` | `LONGTEXT` | Ya | Sanitized rich-text Project Overview |

`RndProject::$fillable` ditambah `overview_html`. Tidak ada backfill, tabel, relasi, foreign key, atau JSON attachment list baru. Attachment aktif diturunkan dari `data-id` sanitized dalam HTML; path storage tidak menjadi sumber authorization tersendiri.

Sebelum migration dibuat, implementasi wajib memeriksa schema aktual melalui Boost `database-schema`. Migration existing yang sudah deployed tidak boleh diedit. Setelah implementasi, regenerate `docs/architecture/data-model.md` memakai generator existing.

## 15. Integration contract

Tidak ada API baru. Storage memakai disk private `b2` existing:

- directory: `rnd/project-overviews/{projectId}/rich-editor`;
- visibility: private;
- accepted MIME: JPEG, PNG, WebP;
- max size: 8 MB per file;
- renderer menghasilkan temporary/private-access URL mengikuti pola Filament existing;
- tidak ada credential/env baru; gunakan konfigurasi disk existing.

Kegagalan storage atau database tidak boleh meninggalkan referensi rusak/file baru orphan. Tidak ada retry otomatis atau queue.

## 16. Authorization dan security

### 16.1 Authorization

- Read mengikuti akses detail Project existing (`view rnd projects`/Resource policy).
- Edit memakai `edit rnd projects` pada server dan visibility UI.
- Record ID/path dari browser tidak dipercaya.
- Project archived tidak dapat dibuka melalui detail route existing; data Overview tetap tersimpan.

### 16.2 Sanitasi HTML

Allowlist minimum:

- struktur: `p`, `br`, `h1`, `h2`, `h3`, `blockquote`;
- emphasis: `strong`, `b`, `em`, `i`, `u`, `s`;
- list: `ol`, `ul`, `li`;
- content: `a`, `img`.

Sanitizer membuang script/style/iframe, `on*` handlers, arbitrary style/class/data attributes, unsafe URLs, dan markup di luar allowlist. Link hanya relative, `http`, `https`, atau `mailto`, dengan `target="_blank" rel="noopener noreferrer"` untuk output yang sesuai. Image hanya `data-id` dengan prefix tepat Project target; base64 dan arbitrary `src` tidak disimpan untuk Overview baru. Width/height hanya format aman dan terbatas.

### 16.3 Shared service

Ekstrak rich-content sanitization/render/path extraction/orphan detection dari private BOM logic ke service reusable dengan attachment prefix sebagai parameter. Sebelum ekstraksi, characterization tests mengunci output BOM. Jangan menduplikasi regex pada `ViewProject` dan jangan memindahkan authorization ke Blade/service sanitizer.

## 17. UI/UX specification

### 17.1 Section read mode

- Posisi setelah header/stat Project dan sebelum workspace domain.
- Section berborder tanpa shadow dekoratif, heading **Project Overview**, deskripsi singkat, dan action di header.
- Sanitized content dirender dengan `prose`, dark variant, responsive images, safe wrapping, dan tanpa viewport horizontal-scroll.
- Empty state: “Project Overview belum ditambahkan” dengan penjelasan tujuan konten.
- CTA **Tambahkan Overview** atau **Edit Overview** hanya untuk editor.

### 17.2 Modal editor

- Filament Action/RichEditor existing, bukan custom Quill/CDN.
- Modal heading **Edit Project Overview**, lebar yang cukup, sticky header/footer, body max 90vh, dan editor internal scroll.
- Toolbar: bold/italic/underline; h2/h3; bullet/ordered list; link/attachFiles; undo/redo.
- `min-height` sekitar 16rem dan `max-height` 60vh.
- Cancel/backdrop/Escape tidak menyimpan; validation failure mempertahankan draft.
- Submit mempunyai loading/disabled state dan success/error feedback.

### 17.3 Create/edit Project existing

- Modal Create/Edit tetap memakai textarea **Deskripsi Project** plain text.
- Copy menjelaskan bahwa deskripsi adalah ringkasan; Overview lengkap diisi setelah Project tersimpan.
- Tidak ada editor rich-text pada index/modal create sehingga attachment selalu memiliki Project ID.

### 17.4 Accessibility/responsive

- Focus masuk/terkunci/kembali sesuai modal Filament.
- Icon-only controls memiliki accessible name.
- Link, heading, list, dan gambar dapat dipahami tanpa hanya warna.
- Uji 360, 390, 768, 1024, 1440 px dan zoom 200%.

## 18. Non-functional requirements

- Overview tidak ikut eager-load/select pada daftar Project bila tidak diperlukan.
- Editor hanya dimount ketika action modal dibuka.
- Tidak ada query/storage call di Blade.
- HTML dibatasi oleh validation sesuai kapasitas LONGTEXT dan batas payload Livewire; nilai final ditentukan setelah verifikasi dokumentasi/version stack.
- File tidak disimpan base64 di database.
- Sanitizer deterministic dan mempunyai unit/feature security coverage.
- Save tidak menurunkan availability detail Project bila storage gagal; konten lama tetap utuh.

## 19. Observability dan audit

- `updated_at` Project mencatat mutation Overview; user actor tersedia dari request/action logs existing.
- Tidak menambah audit table pada phase ini.
- Log error menyimpan Project ID, actor ID, operation, dan outcome aman tanpa HTML penuh, signed URL, atau credential.
- Monitor validation/storage/database error dan orphan cleanup failure selama rollout.

## 20. Data migration dan kompatibilitas

- Migration additive nullable; tidak ada data lama yang ditulis ulang.
- Project lama tetap mempunyai description/list/search/header yang sama dan Overview empty.
- Tidak ada automatic copy/backfill description ke Overview untuk mencegah duplikasi tampilan.
- Archive/restore mempertahankan field dan file.
- Rollback aplikasi sebaiknya membiarkan kolom nullable tetap ada. Jangan menjalankan destructive `down` di production setelah Overview berisi data tanpa export/backup dan approval.
- Informasi BOM tetap kompatibel setelah shared service extraction.

## 21. Testing strategy

- Migration/model: up/down pada test, nullable legacy, fillable, persistence, archive/restore.
- Authorization: viewer read, editor save, viewer mutation forbidden, action hidden.
- Rich content: formatting/list/link saved; semantic-empty becomes null.
- Security: strip script/style/iframe/events/unsafe attributes/javascript/entity/whitespace bypass; safe relative/http/https/mailto retained.
- Image: MIME/size, project prefix isolation, no base64/external source, private render, safe dimensions.
- Lifecycle: failed-save cleanup, orphan delete, active reference retained, storage failure leaves previous content.
- Regression: create/edit/description/search/list/header, all Project types, BOM sanitizer/render/tests.
- UI: lazy editor, empty/loading/error/success, long content, mobile, dark mode, zoom.
- Storage tests use `Storage::fake('b2')`; no external request.

## 22. Rollout

Satu PR aplikasi setelah PRD approved. Deploy migration additive sebelum/bersama code melalui pipeline existing. Tidak ada env, permission sync, queue, scheduler, atau external sandbox. UAT staging mencakup tiga tipe Project, editor/viewer roles, security payload, image lifecycle, archive/restore, dan BOM regression. Detail di [rollout runbook](rollout.md).

## 23. Rollback

Revert application code while leaving nullable `overview_html` column/data in place. Disable/hide Overview UI through code rollback; existing description workflows tetap berjalan. Destructive column rollback hanya dilakukan sebelum data production tersimpan atau setelah backup/export dan approval eksplisit.

## 24. Acceptance criteria

1. Detail seluruh tipe Project memiliki section Project Overview.
2. Description tetap plain text pada create/edit/list/search/header.
3. Editor berpermission dapat menyimpan formatted content melalui Filament RichEditor.
4. Viewer dapat membaca sanitized content/private images tetapi tidak mutate.
5. Unsafe HTML/URL/event/image/path tidak tersimpan atau dirender.
6. Attachment hanya berada pada prefix Project terkait dan lifecycle cleanup benar.
7. Overview kosong tidak merusak Project lama dan mempunyai empty state.
8. Archive/restore mempertahankan Overview.
9. Informasi BOM tidak berubah setelah shared sanitizer extraction.
10. Tidak ada dependency, permission, env, queue, scheduler, atau API baru.
11. Test, Pint, doc-link, code-map, data-model generation, diff check, dan CI lulus.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| XSS melalui rich content | Session/data compromise | Server allowlist sanitizer + security tests; render hanya sanitized HTML |
| Attachment lintas Project/path traversal | Data leak | Prefix-bound validation dan reject invalid data-id |
| Extraction sanitizer mengubah BOM | Regression workflow/export | Characterization tests sebelum refactor dan jalankan BomRecipePageTest |
| Upload sukses tetapi DB gagal | Orphan storage | Track new paths dan cleanup pada exception |
| Orphan cleanup menghapus file aktif | Broken content | Diff extracted paths sebelum/after dan tests |
| Rich content masuk index/search | Payload/UX/regression | Field terpisah; index select/search tetap description |
| Destructive rollback menghapus data | Data loss | App rollback tanpa drop column; backup/approval untuk schema down |
| Concurrent edit overwrite | Kehilangan update | Last-write-wins dinyatakan; revision/conflict menjadi follow-up |

## 26. Open decisions

Tidak ada keputusan yang memblokir dokumentasi. Planning menetapkan Overview sebagai field terpisah dari description, berlaku untuk semua tipe Project, dan memakai pola RichEditor BOM existing. Version history, export, search Overview, dan collaboration berada di luar scope.

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Schema audit dan characterization | Baseline Project/BOM/security/storage tests |
| 1 | Migration/model/shared rich-content boundary | `overview_html`, service sanitizer/renderer/path lifecycle |
| 2 | Mutation dan UI | Action, section, modal editor, permissions, private render |
| 3 | Hardening | Security/storage/concurrency/regression/responsive tests |
| 4 | Delivery | Data model/docs/report, quality gates, PR dan UAT runbook |

## 28. Definition of Done

- [ ] Seluruh acceptance criteria terpenuhi.
- [ ] Schema production diverifikasi sebelum migration.
- [ ] Migration additive/reversible dipahami dan rollback production aman.
- [ ] Authorization, XSS, attachment isolation/lifecycle, dan regression diuji.
- [ ] Description/list/search dan Informasi BOM tidak regresi.
- [ ] Dokumentasi fitur, README R&D, dan generated data model diperbarui.
- [ ] Implementation report mencatat hasil dan deviasi.
- [ ] Pull Request siap direview; merge/deployment tetap keputusan user.
