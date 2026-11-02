---
document_id: RND-PROJECT-OVERVIEW-RICH-TEXT-PROMPT
title: Prompt Implementasi — Project Overview Rich Text
status: draft
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/project-overview-rich-text/prd.md
  - docs/products/rnd/project-overview-rich-text/implementation-plan.md
  - docs/products/rnd/project-overview-rich-text/rollout.md
supersedes: null
---

# Prompt Implementasi — Project Overview Rich Text

Salin blok berikut ke agent implementasi dari root repository.

```text
Implementasikan Project Overview Rich Text pada domain Research & Development di branch `feature/project-overview-rich-text`.

Ikuti Plan → Branch → Implement → Deliver. Pastikan PRD approved, worktree bersih, latest main sudah di-pull, dan branch implementasi dibuat sebelum code change.

## Dokumen sumber wajib
- `AGENTS.md`
- `.claude/skills/feature-delivery/SKILL.md`
- `.claude/skills/laravel-best-practices/SKILL.md` dan rule files relevan
- `.claude/skills/pest-testing/SKILL.md`
- `.claude/skills/tailwindcss-development/SKILL.md`
- `docs/products/rnd/README.md`
- `docs/products/rnd/project-overview-rich-text/prd.md` — requirement canonical
- `docs/products/rnd/project-overview-rich-text/implementation-plan.md`
- `docs/products/rnd/project-overview-rich-text/rollout.md`
- `docs/standards/code-remediation.md`
- `docs/standards/ui-consistency.md`
- `docs/architecture/data-model.md`

## Hasil akhir
Detail seluruh tipe Project mempunyai section Project Overview rich-text. Editor memakai native Filament RichEditor/TipTap dan private B2 images. HTML disanitasi server-side melalui shared rich-content service yang juga mempertahankan behavior Informasi BOM. `description` tetap plain text untuk create/edit/list/search/header.

## Scope
- Audit schema/docs/version API dengan Laravel Boost.
- Migration nullable LONGTEXT `overview_html`.
- Model fillable.
- Shared sanitizer/renderer/path extraction/orphan policy dari behavior BOM existing.
- `UpdateRndProjectOverviewAction` untuk authorize/persist/cleanup.
- Section partial read/empty state dan lazy Filament RichEditor action.
- Private JPEG/PNG/WebP max 8 MB, resizable, exact Project prefix.
- Tests security/storage/permission/regression, generated data model, docs/report/PR.

## Non-scope
- Jangan mengubah description menjadi HTML atau memindahkan list/search/header ke Overview.
- Jangan menambah Overview search, export/PDF/SOP, version/approval/history, collaboration, AI, create-modal editor, video/PDF upload.
- Jangan menambah dependency, permission, API, queue, scheduler, atau env.
- Jangan merge/deploy.

## Aturan repository dan tool
- Aktifkan skills relevan dan ikuti sub-agent requirement dari skill.
- Sebelum code change gunakan Boost `database-schema` untuk `rnd_projects` dan `search-docs` untuk Filament 5 RichEditor/attachments/renderer/action, Livewire upload/payload, validation, migration LONGTEXT, dan filesystem.
- Gunakan `php artisan make:migration ... --no-interaction`, `make:class`/`make:test --pest` sesuai kebutuhan.
- Jangan edit migration deployed atau commit di main.
- Jalankan Pint setelah PHP berubah.

## Arsitektur
- Page: state/action modal/notification; Blade: presentation only.
- Mutation di `UpdateRndProjectOverviewAction`, authorize actor + Project server-side.
- Shared service deterministic untuk sanitize/render/extract paths/allowed prefix; tidak authorize/query Project.
- Jangan copy private sanitizer regex BOM ke ViewProject.
- Characterize BOM first, lalu pindahkan consumer ke shared service tanpa output change.
- Render raw hanya sanitized content. Metadata/copy selalu escaped.
- Overview tidak ikut index query/payload.

## Data/migration
- Tambah `overview_html` nullable LONGTEXT saja setelah schema audit.
- Tidak ada backfill/copy description.
- Project legacy null tetap valid; archive/restore menjaga Overview.
- App rollback meninggalkan column/data. Jangan drop production column setelah data tanpa backup/approval.
- Regenerate data model dengan generator existing.

## Security/sanitizer
- Allowlist structure/emphasis/list/link/img sesuai PRD §16.
- Strip script/style/iframe, event handlers, arbitrary attributes, unsafe schemes dan entity/whitespace bypass.
- Link hanya relative/http/https/mailto; output external aman.
- Overview image hanya `data-id` exact prefix `rnd/project-overviews/{projectId}/rich-editor/`.
- Tolak/hapus base64, arbitrary external src, Project lain, traversal.
- Width/height terbatas aman.
- Validate upload MIME/size dan server-side authorization.

## Attachment lifecycle
- Disk `b2`, private, JPEG/PNG/WebP, 8 MB, resizable.
- Track new paths; cleanup jika save gagal.
- Diff old/new references; delete orphan hanya setelah save berhasil.
- Jangan hapus referenced active file.
- Storage failure mempertahankan content lama.

## UI
- Section setelah header/stat sebelum workspace.
- Empty state dengan CTA hanya editor; viewer hanya read.
- Modal native Filament Action/RichEditor lazy, wide, sticky, internal scroll.
- Toolbar sama dengan Informasi BOM.
- Cancel/backdrop/Escape tidak save; validation mempertahankan draft; submit loading/disabled.
- Uji mobile, dark, long content, focus/keyboard, zoom 200%.
- Create/Edit Project tetap description textarea dengan helper plain-summary copy.

## Tahapan
1. Audit docs/schema/versions/worktree dan jalankan baseline tests.
2. Tambah characterization description dan BOM sanitizer/render/file lifecycle.
3. Buat migration/model dan shared service; pindahkan BOM consumer tanpa behavior change.
4. Buat Action Overview dan attachment lifecycle.
5. Tambah partial section + lazy RichEditor action pada ViewProject.
6. Tambah security/storage/authorization/all-types/archive/regression/UI tests.
7. Jalankan quality gates dan UAT lokal sesuai rollout.
8. Update README/PRD/data model, tulis implementation report, hapus prompt ini bila complete.
9. Commit, push, buka PR Indonesia; jangan merge/deploy.

## Test minimum
- Legacy/null/migration/model/archive/restore.
- Release Product/Premix/Partnership render/edit.
- Viewer read, editor save, unauthorized forbidden/action hidden.
- Formatting/list/link and semantic-empty normalization.
- XSS tags/events/schemes/entity bypass removed; safe link retained.
- MIME/size/private render/prefix isolation/no base64/external source.
- Failed upload/save cleanup, orphan deletion, active reference retained.
- Description create/edit/list/search/header exact regression.
- BOM rich editor/sanitizer/render/image tests exact regression.
- Lazy modal, long content, responsive/dark/accessibility markup.

Jalankan minimal:
- `php artisan test --compact tests/Feature/RndProjectTest.php`
- `php artisan test --compact tests/Feature/BomRecipePageTest.php`
- `php artisan test --compact tests/Feature/BomInstructionImageUploadTest.php`
- file test baru yang dibuat
- `vendor/bin/pint --dirty --format agent`
- `bash ops/ci/check-doc-links.sh`
- `bash ops/ci/check-code-map.sh`
- `git diff --check`

## Laporan akhir
1. Branch/PR.
2. Perubahan per phase dan file.
3. Migration/storage/env/permission/queue/integration notes.
4. Test/quality gate hasil aktual.
5. Security/file lifecycle evidence.
6. Deviasi PRD.
7. UAT/rollback/follow-up.

## Larangan
- Jangan merge/deploy/force-push branch lain.
- Jangan menyimpan unsanitized HTML atau percaya attachment path client.
- Jangan drop/mengubah description atau memperluas scope ke export/search/history.
```
