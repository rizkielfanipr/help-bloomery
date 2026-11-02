---
document_id: RND-MATERIAL-FORECAST-MENU-FILTER-PLAN
title: Implementation Plan — Filter Menu Material Forecast
status: implemented
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/material-forecast-menu-filter/prd.md
supersedes: null
---

# Implementation Plan — Filter Menu Material Forecast

Acuan requirement: `docs/products/rnd/material-forecast-menu-filter/prd.md`. Dokumen ini hanya mengatur urutan teknis; requirement tidak ditulis ulang di sini.

## 1. Scope implementasi

- **Termasuk:** filter All/single/multiple menu, applied/draft selection, validasi ownership/eligibility, subset calculation Kitchen/Store, URL state, UI picker, sinkronisasi export Excel, test, dan dokumentasi aktual.
- **Tidak termasuk:** filter bulan/region/channel/branch, percentage per menu, perubahan projection/BOM, migration, persistence preference, permission/dependency/integrasi baru, atau perubahan halaman Sales & Growth.

## 2. Baseline repository

- Baseline `main` per 2 November 2026 bersih dan sinkron dengan `origin/main` saat branch docs dibuat.
- `ViewProject` menyimpan `forecastType` dan `forecastPercentage`, lalu memanggil service tanpa scope product.
- `RndProjectMaterialForecastService` menjumlahkan projection seluruh `project->products`, melewati product dengan quantity nol, dan menggabungkan materials.
- View Material Forecast menampilkan toggle Kitchen/Store, percentage, totals, detail projection, warnings, rows, dan export.
- Export controller memuat Project lalu menghitung Kitchen/Store seluruh menu.
- Test utama berada di `tests/Feature/RndProjectMaterialForecastTest.php` dan sudah mencakup recursion, Store/Kitchen boundary, percentage, permission, export, dan render.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Livewire Page | `app/Filament/Helpdesk/Resources/Projects/Pages/ViewProject.php` | Tambah applied/draft selection, lookup options, validation, URL state |
| Forecast service | `app/Services/RndProjectMaterialForecastService.php` | Tambah optional selected product IDs dan filter sebelum kalkulasi |
| Blade | `resources/views/filament/helpdesk/rnd-projects/view.blade.php` | Tambah trigger/picker/filter context dan state UI |
| Export controller | `app/Http/Controllers/Helpdesk/RndProjectMaterialForecastExportController.php` | Validasi `menu_ids`, pass selection, metadata scope |
| Route | `routes/web.php` | Audit saja; route/name dipertahankan, kemungkinan tanpa perubahan |
| Test | `tests/Feature/RndProjectMaterialForecastTest.php` | Characterization dan coverage filter/export/security |
| Docs modul | `docs/products/rnd/README.md` | Sinkronkan perilaku setelah implementasi |
| Docs fitur | `docs/products/rnd/material-forecast-menu-filter/` | Status/report dan lifecycle prompt |

Tidak direncanakan perubahan pada model, migration, `SalesProjectionPage`, permission config, scheduler, atau integrasi.

## 4. Dependency dan integration boundary

- Tidak ada dependency baru.
- `ViewProject` bertanggung jawab pada state/filter UI dan authorization.
- Forecast service bertanggung jawab pada kalkulasi subset; tidak membaca request/Livewire state.
- Controller bertanggung jawab pada validation request export dan response file.
- Blade tidak query database atau memvalidasi ownership.
- Read ESB fallback existing tetap berada di service existing dan selalu difake pada test.
- Sebelum code change, gunakan Laravel Boost `search-docs` untuk Livewire URL array/query-string state, computed data, validation arrays, dan Laravel request array validation.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | Characterization All Menu, export, permission, query boundary | Satu PR fitur | PRD approved; branch dari latest main |
| 1 | Service API backward-compatible dan subset tests | PR sama | Baseline hijau |
| 2 | Livewire applied/draft state dan picker UI | PR sama | Service subset stabil |
| 3 | Export query validation dan scope metadata | PR sama | Selection contract final |
| 4 | Hardening, docs aktual, report, delivery | PR sama | Phase 1–3 selesai |

Target implementasi: branch `feature/material-forecast-menu-filter`, satu concern, satu Pull Request.

## 6. Migration strategy

Not applicable — tidak ada schema, index, constraint, atau backfill.

## 7. Data compatibility

- Service default tanpa IDs dan export URL lama berarti All Menu.
- Projection, branch targets, BOM, product, dan percentage tidak dimutasi.
- `null`/parameter tidak ada = All Menu; duplicate IDs dinormalisasi unik.
- Explicit IDs selalu constrained ke Project dan eligibility.
- Halaman Sales & Growth serta consumer service existing tidak berubah hasilnya.

## 8. Authorization

- Pertahankan `view material forecast` untuk view/filter/export.
- Pertahankan `edit material forecast` khusus percentage mutation.
- Tidak ada permission baru atau `permissions:sync`.
- Server harus menolak export/filter akses unauthorized serta invalid/cross-project IDs.

## 9. UI

1. Audit modal/picker components existing sebelum markup baru.
2. Tambahkan trigger **Menu Projection** pada header Material Forecast.
3. Default **All Menu**; applied selection diringkas dengan chips/count.
4. Picker memakai draft selection agar Cancel/backdrop/Escape tidak mengubah hasil.
5. Search nama/kode; tampilkan total projection serta status Main Recipe/BOM Menu.
6. Product tanpa projection disabled dan diberi alasan.
7. Apply mengubah URL/applied state; All Menu mengosongkan explicit IDs.
8. Selection bertahan saat toggle Kitchen/Store.
9. Copy totals/empty/warning mengikuti scope.
10. Export menggunakan applied, bukan draft selection.
11. Mobile tidak horizontal-scroll; dialog accessible dan dark variants dipertahankan.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Exact baseline Kitchen/Store All Menu, export URL lama, permissions |
| 1 | Single/multiple subset, percentage, projection details, material aggregation, WIP, warnings/notes, invalid IDs |
| 2 | Default/draft/apply/cancel/reset, search, disabled no-projection, URL state, switch type, stale selection, responsive/accessibility markup |
| 3 | Export all/single/multiple, both sheets, metadata, invalid/cross-project IDs, formula safety |
| 4 | Full affected test file, query regression, Pint, docs/code-map/diff checks |

Perintah minimum:

```bash
php artisan test --compact tests/Feature/RndProjectMaterialForecastTest.php
php artisan test --compact tests/Feature/RndProjectTest.php --filter="sales projection"
vendor/bin/pint --dirty --format agent
bash ops/ci/check-doc-links.sh
bash ops/ci/check-code-map.sh
git diff --check
```

Sesuaikan filter test berdasarkan nama aktual dan catat hasil nyata; jangan mengklaim suite yang tidak dijalankan.

## 11. Observability

Tidak menambah audit log. Catat query count/render time baseline vs All/multiple selection pada implementation report. Error validation tidak boleh memasukkan data Project lain atau credential.

## 12. Rollout

- Pipeline aplikasi existing setelah review/merge.
- Tidak ada migration, env, permission sync, worker/scheduler restart, atau manual data operation.
- UAT staging membandingkan All Menu dengan baseline lalu single/multiple menu pada Kitchen, Store, refresh URL, dan Excel.
- Approval production tetap keputusan user/reviewer.

## 13. Rollback

Revert PR aplikasi. Karena default API/service/URL tetap backward-compatible dan tidak ada data baru, rollback tidak memerlukan database/file cleanup.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| UI/export scope berbeda | Satu applied selection contract dan end-to-end XLSX tests |
| Cross-project ID tampering | Validate through project relation; reject seluruh request invalid |
| Draft selection langsung menghitung | Pisahkan draft vs applied state |
| Empty/null ambiguity | Normalize empty to All Menu secara eksplisit |
| Query/Livewire payload membesar | Project-scoped options, eager loading, server search bila perlu |
| All Menu regression | Characterization exact sebelum refactor |
| Filter dianggap membatasi bulan | Copy scope eksplisit; period filter non-scope |

## 15. Quality gates

- [x] PRD approved sebelum aplikasi diubah.
- [x] Branch implementasi dari latest `main`; tidak commit di `main`.
- [x] Laravel Boost `search-docs` dijalankan sebelum code change.
- [x] Characterization dan test baru lulus.
- [x] Authorization/data isolation direview.
- [x] `vendor/bin/pint --dirty --format agent` lulus.
- [x] `bash ops/ci/check-doc-links.sh` lulus.
- [x] `bash ops/ci/check-code-map.sh` lulus.
- [x] `git diff --check` lulus.
- [ ] CI Pull Request hijau.

## 16. Definition of Done

- [x] Semua phase dan acceptance criteria PRD selesai.
- [x] All Menu, single, multiple, Kitchen, Store, dan export terbukti konsisten.
- [x] Tidak ada mutation/filter persistence yang tidak disetujui.
- [x] README R&D cocok dengan kode aktual.
- [x] PRD/README fitur diperbarui ke lifecycle sebenarnya.
- [x] `reports/implementation-report.md` dibuat.
- [x] `implementation-prompt.md` dihapus setelah implementasi selesai.
- [x] PR berbahasa Indonesia dan belum di-merge/deploy tanpa keputusan user.
