---
document_id: RND-PROJECT-OVERVIEW-RICH-TEXT-PLAN
title: Implementation Plan — Project Overview Rich Text
status: draft
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

# Implementation Plan — Project Overview Rich Text

Acuan requirement: `docs/products/rnd/project-overview-rich-text/prd.md`. Dokumen ini hanya mengatur urutan teknis; requirement tidak ditulis ulang di sini.

## 1. Scope implementasi

- **Termasuk:** schema audit, migration `overview_html`, model, shared rich-content security service, BOM compatibility refactor, Project Overview Action/UI, private image lifecycle, tests, generated data model, docs, dan rollout evidence.
- **Tidak termasuk:** mengganti description, Overview search/export/versioning/approval/collaboration/AI, editor create modal, attachment selain gambar, permission/dependency/API/queue/scheduler baru.

## 2. Baseline repository

- `description` plain text dipakai ListProjects, ViewProject, ProjectResource, Create/Update Actions, list/header/search, dan tests.
- `ViewProject` custom Blade belum memiliki rich overview.
- Informasi BOM adalah referensi editor/security/storage, tetapi sanitizer/render/path/orphan logic masih private pada `ViewProjectProductPage`.
- Native Filament RichEditor/TipTap sudah tersedia; tidak perlu package/CDN Quill.
- Disk private `b2` dan tests `Storage::fake('b2')` sudah digunakan.
- Schema live belum berhasil dibaca dari sandbox planning; Boost `database-schema` menjadi gate sebelum migration.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Migration | `database/migrations/*_add_overview_html_to_rnd_projects_table.php` | Baru, additive |
| Model | `app/Models/RndProject.php` | Tambah fillable field |
| Action | `app/Actions/Rnd/Project/UpdateRndProjectOverviewAction.php` | Baru, authorize/sanitize/persist/cleanup |
| Rich content service | `app/Services/Rnd/RndRichContentService.php` | Baru, sanitize/render/path extraction/policy |
| Detail Page | `app/Filament/Helpdesk/Resources/Projects/Pages/ViewProject.php` | RichEditor action/state/read model |
| Detail View | `resources/views/filament/helpdesk/rnd-projects/view.blade.php` | Include section Overview |
| Partial | `resources/views/filament/helpdesk/rnd-projects/partials/project-overview.blade.php` | Baru, read/empty/action presentation |
| BOM editor | `app/Filament/Helpdesk/Pages/ViewProjectProductPage.php` | Gunakan shared service tanpa behavior change |
| Test Project | `tests/Feature/RndProjectTest.php` atau file khusus sesuai convention | Overview/auth/regression |
| Test BOM | `tests/Feature/BomRecipePageTest.php` | Characterization/shared service regression |
| Docs | `docs/products/rnd/README.md`, folder fitur, generated `docs/architecture/data-model.md` | Update |

## 4. Dependency dan integration boundary

- Tidak ada package/dependency baru.
- Page/Filament Action menangani presentation state dan memanggil mutation Action.
- Update Action menangani authorization/persistence/cleanup orchestration.
- Shared service menangani sanitasi deterministic, rendering private RichEditor attachment, allowed-prefix validation, dan path extraction; tidak authorize atau query Project.
- Blade hanya presentasi sanitized HTML.
- Storage disk `b2` existing adalah satu-satunya external boundary.
- Gunakan Laravel Boost `search-docs` sebelum code change untuk Filament 5 RichEditor attachments/renderer, Action modal, Livewire uploads, validation, migration LONGTEXT, dan filesystem failure handling.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | Boost schema/docs audit dan characterization Project/BOM | Satu PR fitur | PRD approved; latest main |
| 1 | Migration/model/shared service extraction | PR sama | Baseline tests hijau |
| 2 | Overview mutation, editor, read section, attachment lifecycle | PR sama | Shared boundary stabil |
| 3 | Security/storage/regression/responsive hardening | PR sama | Feature end-to-end bekerja |
| 4 | Data model/docs/report/delivery | PR sama | Test dan UAT lokal lulus |

Target branch: `feature/project-overview-rich-text` dari latest `main`.

## 6. Migration strategy

1. Verifikasi `rnd_projects` melalui Boost `database-schema`.
2. Buat migration dengan `php artisan make:migration add_overview_html_to_rnd_projects_table --table=rnd_projects --no-interaction`.
3. Tambah nullable LONGTEXT `overview_html`; tidak ada default/backfill/DDL lain.
4. `down()` dapat drop field untuk local/test, tetapi production rollback setelah data terisi tidak menjalankan schema down tanpa backup/approval.
5. Regenerate data model setelah code/schema final.

## 7. Data compatibility

- Project lama: `overview_html = null`, description dan seluruh workflow tetap.
- Tidak copy description otomatis.
- List/search/header tetap memakai description dan tidak select/render Overview.
- Archive/restore mempertahankan content/files.
- App rollback meninggalkan column nullable agar data tidak hilang.
- BOM content/output/path existing dikunci tests saat shared service extraction.

## 8. Authorization

- Read mengikuti Resource/detail access existing.
- Save/clear Action authorize `edit rnd projects` dengan actor dan Project.
- Tombol hidden untuk viewer, tetapi method/action tetap abort/deny server-side.
- Tidak ada permission baru, role grant, atau `permissions:sync`.

## 9. UI

1. Section setelah header/stat Project dan sebelum workspace domain.
2. Empty/read state memakai partial dan prose styling.
3. CTA Tambahkan/Edit Overview hanya editor.
4. Native Filament Action + RichEditor lazy-mounted.
5. Toolbar/attachment/modal mengikuti BOM existing.
6. Create/Edit modal tetap description textarea dengan helper copy.
7. Loading/error/success, long content, mobile, dark, focus, keyboard, and zoom diperiksa.
8. Sanitized HTML saja yang dirender raw; seluruh copy/metadata tetap escaped.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Description create/edit/list/search/header dan BOM sanitizer/render/orphan baseline |
| 1 | Migration/model/null legacy/shared sanitizer exact output |
| 2 | All Project types, read/edit permission, formatting, empty/null, private render/upload/orphan/failure cleanup |
| 3 | XSS/URL/entity/event/path isolation/base64/external image, archive/restore, lazy editor, responsive markup, BOM full regression |
| 4 | Affected suites, Pint, docs/code-map/data-model/diff checks |

Perintah minimum yang direncanakan:

```bash
php artisan test --compact tests/Feature/RndProjectTest.php
php artisan test --compact tests/Feature/BomRecipePageTest.php
php artisan test --compact tests/Feature/BomInstructionImageUploadTest.php
vendor/bin/pint --dirty --format agent
bash ops/ci/check-doc-links.sh
bash ops/ci/check-code-map.sh
git diff --check
```

Jika test Overview dibuat terpisah, jalankan file tersebut dan catat seluruh hasil aktual.

## 11. Observability

- Gunakan logging/error recorder existing dengan Project ID, actor ID, operation, dan outcome aman.
- Jangan log HTML, signed URL, path penuh yang sensitif, file bytes, atau credential.
- Implementation report mencatat query count/load behavior detail vs index dan storage cleanup scenarios.

## 12. Rollout

- Migration additive melalui pipeline existing.
- Tidak ada env/permission/worker/scheduler operation.
- UAT staging tiga tipe Project, viewer/editor, malicious input, images, archive/restore, dan BOM regression.
- Ikuti `rollout.md`; production approval tetap milik user/reviewer.

## 13. Rollback

Revert UI/action/service integration sambil mempertahankan nullable column dan data. Jika shared sanitizer extraction menyebabkan regression, revert consumer ke behavior sebelumnya tanpa menghapus Overview data. Schema down production dilarang setelah data tersimpan kecuali backup/export dan approval eksplisit.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| XSS/unsafe links/images | Shared allowlist sanitizer dan adversarial tests |
| Cross-project attachment | Exact prefix validation per Project |
| BOM regression | Characterization sebelum extraction + full BOM tests |
| Orphan/broken file | Transaction-like orchestration, new-path cleanup, after-save orphan diff |
| Large Livewire/index payload | Lazy modal; overview tidak dimuat index |
| Data loss rollback | Leave additive column; no production down after data |
| Duplicate sanitization policy | Satu reusable service, no regex copy in Page |

## 15. Quality gates

- [ ] PRD approved sebelum aplikasi berubah.
- [ ] Latest main dan branch fitur terpisah.
- [ ] Boost `database-schema` dan `search-docs` dijalankan.
- [ ] Characterization tests sebelum shared refactor.
- [ ] Migration additive/backward-compatible direview.
- [ ] Authorization/XSS/path/file lifecycle tests lulus.
- [ ] `vendor/bin/pint --dirty --format agent` lulus.
- [ ] Targeted tests lulus dengan hasil aktual.
- [ ] `bash ops/ci/check-doc-links.sh` dan `check-code-map.sh` lulus.
- [ ] Generated data model diperbarui dengan mekanisme existing.
- [ ] `git diff --check` dan CI PR hijau.

## 16. Definition of Done

- [ ] Seluruh phase/acceptance criteria selesai.
- [ ] Description dan BOM tidak regresi.
- [ ] Security/storage lifecycle teruji.
- [ ] README/PRD/data model/report cocok dengan kode.
- [ ] `implementation-prompt.md` dihapus setelah complete.
- [ ] UAT/rollback evidence dicatat.
- [ ] PR berbahasa Indonesia siap review dan belum di-merge/deploy tanpa keputusan user.
