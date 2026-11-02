---
document_id: RND-MATERIAL-FORECAST-MENU-FILTER-PROMPT
title: Prompt Implementasi — Filter Menu Material Forecast
status: draft
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/material-forecast-menu-filter/prd.md
  - docs/products/rnd/material-forecast-menu-filter/implementation-plan.md
supersedes: null
---

# Prompt Implementasi — Filter Menu Material Forecast

Salin blok berikut ke agent implementasi dari root repository.

```text
Implementasikan Filter Menu Material Forecast pada domain Research & Development di branch `feature/material-forecast-menu-filter`.

Ikuti Plan → Branch → Implement → Deliver. Jangan mengubah kode sebelum memastikan PRD disetujui, worktree bersih, latest `main` sudah di-pull, dan branch implementasi sudah dibuat.

## Dokumen sumber yang wajib dibaca
- `AGENTS.md`
- `.claude/skills/feature-delivery/SKILL.md`
- `docs/products/rnd/README.md`
- `docs/products/rnd/material-forecast-menu-filter/prd.md` — requirement canonical
- `docs/products/rnd/material-forecast-menu-filter/implementation-plan.md`
- `docs/products/sales-growth/README.md` — batas bahwa halaman global tidak berubah
- `docs/standards/code-remediation.md`
- `docs/standards/ui-consistency.md`

## Hasil akhir yang diharapkan
Material Forecast pada detail Project R&D default menghitung All Menu seperti baseline, tetapi user dapat memilih satu atau beberapa `RndProjectProduct` yang memiliki Sales Projection positif. Applied selection yang sama membatasi Kitchen, Store, totals, projection details, materials, warnings, notes, source count, dan kedua sheet export Excel. Filter tidak mengubah data bisnis.

## Scope
- All Menu default; single/multiple explicit selection.
- Draft picker state terpisah dari applied state.
- Search nama/product code dan context projection/BOM.
- Menu tanpa projection terlihat disabled dengan alasan.
- URL-backed applied IDs bila sesuai pola Livewire 4 resmi.
- Service subset calculation backward-compatible.
- Server validation ownership/eligibility.
- Export menerima selected IDs dan menulis scope metadata.
- Test, docs aktual, implementation report, commit/push/PR sesuai workflow.

## Non-scope
- Filter bulan, region, channel, atau branch.
- Percentage per menu atau persistence selection.
- Perubahan Sales Projection, split branch, BOM, recursion, tolerance, WIP, pricing, atau Sales & Growth page.
- Migration/model/permission/dependency/env/queue/scheduler/integrasi baru.
- Merge atau deployment.

## Modul terdampak
- `app/Filament/Helpdesk/Resources/Projects/Pages/ViewProject.php`
- `app/Services/RndProjectMaterialForecastService.php`
- `resources/views/filament/helpdesk/rnd-projects/view.blade.php`
- `app/Http/Controllers/Helpdesk/RndProjectMaterialForecastExportController.php`
- `routes/web.php` audit-only kecuali query contract memerlukan perubahan yang terbukti
- `tests/Feature/RndProjectMaterialForecastTest.php`
- `docs/products/rnd/README.md`
- `docs/products/rnd/material-forecast-menu-filter/`

## Aturan repository
- Aktifkan skill relevan yang tersedia.
- Gunakan Laravel Boost `search-docs` sebelum code change untuk Livewire URL/query-string array, computed state, array validation, dan Laravel request validation.
- Ikuti sibling conventions dan struktur existing.
- Jangan commit di `main` atau menambah dependency.
- Gunakan `php artisan make:* --no-interaction` hanya bila file baru memang diperlukan.
- Jalankan `vendor/bin/pint --dirty --format agent` setelah PHP berubah.

## Aturan arsitektur
- Blade hanya presentasi; tidak query database atau memvalidasi ownership.
- Page mengelola UI state/authorization, bukan kalkulasi BOM.
- Service menerima explicit scope independen dari HTTP/Livewire dan default tetap All Menu.
- Export controller memvalidasi request lalu memanggil service yang sama; jangan menduplikasi kalkulasi.
- Filter product sebelum recursion/material aggregation, bukan setelah hasil dihitung.
- Gunakan relasi Project untuk validation; jangan lookup product global kemudian percaya client.
- Pertahankan eager loading/cache/fallback ESB existing dan cegah N+1.
- Jangan membuat DTO/abstraction baru bila typed array/signature sederhana cukup dan konvensi sibling mendukungnya.

## Kontrak selection
- `null` atau parameter tidak ada = All Menu.
- Explicit IDs = integer unik milik Project dan memiliki total target quantity positif.
- Empty explicit array dinormalisasi All Menu; tidak ada zero-menu mode.
- All Menu mutually exclusive dengan explicit selection.
- Selected menu sums seluruh projection rows lintas month/region/channel/branch.
- Kitchen/Store/Export memakai selection sama.
- Stale/invalid selection ditangani deterministik dan tidak membocorkan data.

## Aturan authorization
- `view material forecast` untuk view/filter/export.
- `edit material forecast` tetap khusus percentage mutation.
- Visibility UI bukan kontrol tunggal; Page/controller tetap authorize.
- Tidak ada permission baru atau role grant.

## Aturan UI
- Ikuti `docs/standards/ui-consistency.md` dan audit picker/modal existing sebelum markup baru.
- Filter berada dekat Kitchen/Store/export.
- Default label All Menu; explicit selection menampilkan selected count/chips ringkas.
- Picker memiliki search, checkbox, projection total, Main Recipe/BOM Menu status, disabled reason, Apply, Cancel, All Menu.
- Draft tidak mengubah hasil sampai Apply.
- Cancel/backdrop/Escape tidak menerapkan draft.
- Export memakai applied state.
- Loading, empty, stale, validation, long content, keyboard/focus, mobile, zoom 200%, dan dark mode diperiksa.
- Jangan menambah kartu statistik atau heading duplikat.

## Aturan export dan integration
- Route lama tanpa query tetap All Menu.
- Query `menu_ids[]` opsional divalidasi dan seluruh ID invalid menyebabkan response aman tanpa file parsial.
- Kedua sheet memakai scope sama dan mencatat Scope Menu.
- Pertahankan `safeText()` formula injection protection.
- Tidak ada API/integrasi baru; semua ESB call di test wajib fake/mock.

## Aturan data safety
- Tidak ada migration/backfill/data cleanup.
- Jangan mengubah Sales Projection/BOM/Project kecuali percentage existing melalui flow existing.
- Jangan menulis credential, payload sensitif, atau data production ke log/docs.

## Tahapan implementasi
1. Audit worktree, docs, service/page/view/export/test; catat baseline.
2. Jalankan baseline test Material Forecast.
3. Tambah characterization exact All Menu Kitchen/Store/export.
4. Tambah optional selection pada service dan test single/multiple/security.
5. Tambah applied/draft state, eligible options, validation, URL behavior, dan picker UI.
6. Sinkronkan export query + scope metadata dengan applied state.
7. Tambah test permission, stale state, empty state, query regression, XLSX, dan formula safety.
8. Jalankan quality gates.
9. Update README R&D, PRD/README feature, tulis implementation report, dan hapus prompt ini bila complete.
10. Commit, push, dan buka PR ke `main` memakai template dalam Bahasa Indonesia. Jangan merge/deploy.

## Test minimum
- All Menu exact baseline.
- Single menu hanya memuat projection/material/warning menu itu.
- Multiple menu menggabungkan subset dengan count benar.
- Seluruh month/region/channel/branch selected menu tetap dijumlahkan.
- Percentage global diterapkan pada subset.
- Kitchen dan Store memakai selection sama tanpa mereset saat toggle.
- Menu tanpa projection disabled/tidak dihitung.
- Draft/apply/cancel/reset/URL/stale state benar.
- Cross-project dan invalid IDs ditolak.
- Export lama All Menu; export single/multiple sesuai UI pada dua sheet dan metadata scope.
- Permission viewer/editor/unauthorized tetap benar.
- Tidak ada N+1 baru dan formula injection protection tetap lulus.

Jalankan minimal:
- `php artisan test --compact tests/Feature/RndProjectMaterialForecastTest.php`
- test Sales Projection/R&D yang benar-benar terdampak berdasarkan audit
- `vendor/bin/pint --dirty --format agent`
- `bash ops/ci/check-doc-links.sh`
- `bash ops/ci/check-code-map.sh`
- `git diff --check`

## Format laporan akhir
1. Branch dan link PR.
2. Ringkasan phase dan perilaku final.
3. File dibuat/diubah.
4. Test/quality gate beserta hasil aktual.
5. Deviasi PRD dan alasan.
6. Migration/env/permission/queue/integration notes (seharusnya none).
7. Risiko, UAT staging, dan follow-up filter periode/region/channel/branch bila diminta.

## Larangan
- Jangan merge, approve, atau menjalankan deployment.
- Jangan force-push branch lain.
- Jangan memperluas scope ke penyimpanan selection, period filter, atau perubahan rumus forecast.
```
