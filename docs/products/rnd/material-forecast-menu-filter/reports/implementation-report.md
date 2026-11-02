---
document_id: RND-MATERIAL-FORECAST-MENU-FILTER-REPORT
title: Laporan Implementasi — Filter Menu Material Forecast
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

# Laporan Implementasi — Filter Menu Material Forecast

Acuan: `docs/products/rnd/material-forecast-menu-filter/prd.md`

## 1. Status per phase

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 — Characterization | Selesai | Branch `feature/material-forecast-menu-filter` | Baseline 13 test hijau sebelum perubahan; angka All Menu dikunci test baru |
| 1 — Service subset | Selesai | Branch yang sama | Parameter opsional `selectedProductIds` dan `menuOptions()` |
| 2 — Livewire/UI | Selesai | Branch yang sama | Pemeriksaan di browser belum dilakukan, lihat §5 |
| 3 — Export | Selesai | Branch yang sama | Validasi `menu_ids[]` dan metadata Scope Menu |
| 4 — Hardening dan delivery | Selesai | Branch yang sama | Merge dan deployment tetap keputusan user |

## 2. Ringkasan perubahan

- `RndProjectMaterialForecastService::calculate()` menerima daftar product ID opsional dan memfilter product sebelum rekursi BOM. `null` atau daftar kosong berarti All Menu. `menuOptions()` menjadi satu sumber eligibility untuk Page dan export.
- `ViewProject` menyimpan applied selection di `forecastMenuIds` (URL `menu_ids`) dan draft di `forecastMenuDraftIds`. `forecastMenuFilter()` menyelesaikan ID terhadap menu Project pada setiap pembacaan.
- Blade menambah baris filter **Menu Projection**, chip menu terpilih, pesan pilihan stale, picker berbasis `x-rnd.picker-modal`, serta copy dan empty state yang mengikuti scope.
- Export memvalidasi `menu_ids[]`, memakai service yang sama, dan menulis baris **Scope Menu** pada kedua sheet melalui `safeText()`.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| ID stale pada URL halaman dibuang dengan pesan; jika semua terbuang, halaman kembali ke All Menu | Halaman dibuka dari link lama harus tetap dapat dipakai dan tidak ada state nol menu | PRD §11 butir 6 dan 9 |
| Export dengan ID invalid ditolak `422` tanpa file dan tanpa detail ID atau nama menu | Kontrak export tidak mengizinkan file parsial | PRD §15 |
| `forecastMenuIds` tidak memakai `#[Locked]` | Livewire menulis ulang nilai URL lewat `$wire.set` pada navigasi riwayat browser; nilai tetap aman karena selalu diselesaikan di server | PRD §17.5 |
| `#[Url(except: [])]` | Parameter `menu_ids` harus hilang dari URL saat kembali ke All Menu, termasuk bila halaman dibuka dengan `menu_ids` | PRD §17.5 |
| Semua BOM Project tetap menjadi sumber resep walau menu difilter | Menu terpilih dapat memakai WIP yang resepnya terpasang pada menu lain; membatasinya akan mengubah hasil kalkulasi | PRD §8 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §17.3 dan §21 | Search picker berjalan di browser dengan Alpine, tanpa debounce dan tanpa test Livewire untuk perilaku penyaringan | Setiap round-trip menghitung ulang forecast dan dapat memicu fallback ESB; daftar hanya berisi menu satu Project. PRD §17.3 sudah diperbarui | Periksa saat UAT staging |

## 5. Test dan verifikasi

- `tests/Feature/RndProjectMaterialForecastTest.php`: 41 test lulus (269 assertion), mencakup baseline All Menu, single/multiple, draft/apply/cancel/reset, URL, stale, cross-project, permission, empty state, query regression, isi XLSX, dan formula injection.
- Query: tidak ada query tambahan pada service dengan data eager loaded; query halaman dengan selection tidak melebihi All Menu. Render time tidak diukur.
- Test Project lain yang memakai `ViewProject`, Pint, doc-link, code-map, dan `git diff --check` dicatat hasil aktualnya pada Pull Request.
- **Belum diverifikasi di browser:** fokus keyboard, Escape, search Alpine, pembaruan URL, viewport 360–1440 px, zoom 200%, dan dark mode. Sesi ini tidak membuat data maupun menjalankan server baru, sehingga pemeriksaan tersebut menjadi bagian UAT staging.

## 6. Migration dan konfigurasi

Tidak ada migration, environment variable, permission baru, queue, scheduler, dependency, atau integrasi baru. Class Tailwind baru ikut terkompilasi melalui `npm run build` pada build aplikasi biasa.

## 7. Risiko dan pekerjaan lanjutan

- Membuka dan menutup picker tetap menghitung ulang forecast karena seluruh halaman dirender ulang, sama seperti interaksi lain di halaman ini.
- Filter periode, region, channel, dan branch tetap di luar scope dan perlu direncanakan terpisah bila dibutuhkan.
