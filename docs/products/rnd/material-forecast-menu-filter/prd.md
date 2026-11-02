---
document_id: RND-MATERIAL-FORECAST-MENU-FILTER-PRD
title: PRD — Filter Menu Material Forecast
status: draft
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/README.md
  - docs/products/sales-growth/README.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# PRD — Filter Menu Material Forecast

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Research & Development |
| Fitur | Filter Menu Material Forecast |
| Status | draft |
| Prioritas | TBD — belum ditetapkan owner bisnis |
| Target pengguna | User Back Office yang dapat melihat Material Forecast |
| Modul terdampak | Research & Development → Project → Material Forecast |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

Material Forecast pada detail Project R&D saat ini selalu menjumlahkan Sales Projection seluruh menu dalam Project. User membutuhkan kemampuan melihat kebutuhan material untuk semua menu, satu menu, atau beberapa menu tanpa mengubah projection yang tersimpan. Fitur menambahkan filter menu yang konsisten untuk Forecast Kitchen, Forecast Store, ringkasan, warning, detail perhitungan, dan export Excel, dengan All Menu sebagai default backward-compatible.

## 3. Latar belakang

Purchasing dan R&D perlu memeriksa kebutuhan bahan secara bertahap, misalnya hanya untuk menu yang akan diuji atau dirilis lebih dulu. Hasil global seluruh Project tetap diperlukan, tetapi tidak cukup untuk menganalisis subset menu. Data relasi sudah tersedia karena setiap `RndProductSalesProjection` dimiliki satu `RndProjectProduct`; kebutuhan utama adalah membatasi collection product yang dihitung tanpa membuat salinan projection atau schema baru.

## 4. Baseline sistem

- Detail Project R&D dirender oleh `app/Filament/Helpdesk/Resources/Projects/Pages/ViewProject.php` dan `resources/views/filament/helpdesk/rnd-projects/view.blade.php`.
- Material Forecast hanya terlihat dengan permission `view material forecast`; persentase Project hanya dapat diubah dengan `edit material forecast`.
- `RndProjectMaterialForecastService::calculate(RndProject $project, string $forecastType = 'kitchen')` melakukan loop seluruh `$project->products`.
- Sales Projection per menu dijumlahkan dari seluruh row `target_quantity`; tidak ada filter periode, region, channel, atau branch pada Material Forecast.
- Product tanpa total projection positif dilewati.
- Forecast Kitchen menggunakan Main Recipe dan menelusuri komponen/WIP sampai bahan baku terakhir.
- Forecast Store menggunakan komponen langsung BOM Menu dan tidak menguraikan WIP.
- `forecast_percentage` tersimpan di `rnd_projects` dan berlaku global untuk seluruh hasil.
- Hasil memuat `rows`, `projection_details`, total projection/effective units, jumlah menu, warnings, recipe notes, dan calculation notes.
- Export route `helpdesk.rnd-projects.material-forecast-export` menghasilkan Excel dengan sheet Kitchen dan Store melalui `RndProjectMaterialForecastExportController`.
- Export saat ini selalu menghitung seluruh menu dan tidak menerima scope menu.
- Data projection global read-only juga dapat dilihat pada Sales & Growth → Sales Projection; fitur ini tidak mengubah halaman tersebut.

## 5. Problem statement

User tidak dapat mengisolasi kebutuhan material berdasarkan menu tertentu. Untuk mengetahui kebutuhan satu atau beberapa menu, user harus menghitung manual dari hasil agregat atau mengubah data projection, yang berisiko salah dan tidak sesuai tujuan data. Export juga selalu mencakup seluruh Project sehingga tidak dapat digunakan langsung untuk subset menu.

## 6. Goals

1. Menyediakan pilihan All Menu, satu menu, dan multiple menu pada Material Forecast.
2. Menjaga All Menu menghasilkan kalkulasi yang sama dengan baseline existing.
3. Menerapkan scope menu yang sama pada Kitchen, Store, ringkasan, detail, warning, dan export.
4. Menjamin filter tidak mengubah data Sales Projection, BOM, atau persentase forecast.
5. Memvalidasi seluruh selected menu ID terhadap Project di server.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Pilihan scope menu | Seluruh menu saja | All, single, dan multiple | Feature/Livewire test dan UAT |
| Konsistensi UI dan export | Export selalu All Menu | Export sama dengan filter aktif | Test isi XLSX |
| Data mutation akibat filter | Tidak berlaku | 0 mutation | Test database dan code review |
| Cross-project product exposure | Belum ada input product ID | 0 ID Project lain diterima | Validation/security test |
| Regresi All Menu | Baseline existing | 0 perubahan hasil | Characterization test service/export |

## 8. Non-goals

- Tidak menambah filter bulan, region, channel, atau branch pada phase ini.
- Tidak mengubah cara Sales Projection dibuat, diedit, divalidasi, atau displit ke branch.
- Tidak mengubah rumus Kitchen, Store, tolerance, WIP resolution, atau aggregation material.
- Tidak membuat percentage per menu; `forecast_percentage` tetap global di level Project.
- Tidak menyimpan preferensi filter ke database.
- Tidak mengubah halaman Sales & Growth → Sales Projection.
- Tidak menambah migration, model, permission, dependency, queue, scheduler, API, atau integrasi ESB.
- Tidak membuat export terpisah per menu; satu file tetap berisi sheet Kitchen dan Store untuk scope yang sama.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Viewer Material Forecast | Memilih scope menu, melihat hasil, dan export | `view material forecast` |
| Editor Material Forecast | Seluruh kemampuan viewer serta mengubah percentage Project | `view material forecast`, `edit material forecast` |
| Sistem | Memvalidasi selected product, menghitung subset, dan menjaga konsistensi export | Server-side |

## 10. User journey

1. User membuka Project bertipe Release Product dan melihat Material Forecast.
2. Filter default menunjukkan **All Menu**; hasil sama dengan perilaku existing.
3. User membuka picker, mencari menu berdasarkan nama/kode, lalu memilih satu atau beberapa menu yang memiliki Sales Projection positif.
4. User menekan **Terapkan**; ringkasan, detail projection, material, warning, dan calculation notes dihitung ulang hanya dari menu terpilih.
5. User berpindah Kitchen/Store tanpa kehilangan selection.
6. User dapat kembali ke All Menu dengan satu aksi.
7. User menekan Export Excel; kedua sheet menggunakan scope menu yang sama dan mencatat nama menu terpilih.

## 11. Business rules

1. All Menu adalah default dan direpresentasikan tanpa daftar explicit product ID.
2. Menu pada fitur ini adalah `RndProjectProduct` milik Project Release Product yang sedang dibuka.
3. Hanya menu dengan total `target_quantity > 0` yang eligible untuk dipilih; menu tanpa projection tetap dapat ditampilkan disabled dengan alasan.
4. Single selection adalah multiple selection dengan satu ID; tidak memerlukan mode data terpisah.
5. All Menu dan explicit selection bersifat mutually exclusive. Memilih All Menu menghapus explicit selection.
6. State tanpa explicit selection dinormalisasi menjadi All Menu; tidak ada state “hitung nol menu”.
7. Seluruh row projection milik menu terpilih tetap dijumlahkan lintas bulan, region, channel, dan branch seperti baseline.
8. Percentage global Project diterapkan setelah total projection menu terpilih dihitung.
9. Selection yang tidak lagi valid karena menu/projection berubah harus dibuang atau ditolak secara deterministik, lalu UI memberi konteks yang jelas.
10. Selected ID dari Project lain atau ID yang tidak ada tidak boleh masuk calculation maupun export.
11. Kitchen dan Store memakai selected menu IDs yang sama, tetapi eligibility kalkulasi BOM tetap mengikuti tipe forecast.
12. Warning, recipe notes, calculation notes, totals, projection details, dan `product_count` hanya berasal dari menu terpilih.
13. Filter tidak menjalankan mutation database dan tidak memerlukan permission edit.
14. Export tanpa parameter menu berarti All Menu untuk kompatibilitas bookmark/link existing.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Material Forecast default memakai All Menu dan mempertahankan hasil existing | Must |
| FR-2 | User dapat memilih satu atau beberapa menu eligible dari Project | Must |
| FR-3 | Picker mendukung search nama/kode, selected count, apply, cancel, dan kembali ke All Menu | Must |
| FR-4 | Menu tanpa projection terlihat dengan status disabled/alasan | Should |
| FR-5 | Selection bertahan saat mengganti Kitchen/Store dan selama refresh URL state valid | Must |
| FR-6 | Service membatasi kalkulasi ke selected product IDs yang tervalidasi | Must |
| FR-7 | Summary, details, rows, warnings, notes, dan source count mengikuti scope | Must |
| FR-8 | Export Excel menerima scope yang sama, memvalidasi ID, dan menulis metadata scope | Must |
| FR-9 | Parameter export kosong mempertahankan All Menu | Must |
| FR-10 | ID menu Project lain/tidak valid ditolak tanpa membocorkan data | Must |
| FR-11 | Loading, empty, stale-selection, dan validation state mempunyai pesan actionable | Should |
| FR-12 | Permission existing tetap berlaku tanpa permission baru | Must |

## 13. Status dan lifecycle

Filter tidak mempunyai lifecycle database. State UI:

| State | Makna | Transisi | Aktor |
|---|---|---|---|
| All Menu | Semua menu berprojection positif dihitung | Pilih satu/multiple menu | User |
| Draft Selection | Pilihan di picker belum diterapkan | Apply atau Cancel | User |
| Selected Menus | Satu/multiple menu tervalidasi dihitung | Ubah pilihan atau All Menu | User |
| Stale Selection | Sebagian/seluruh ID tidak lagi eligible | Normalisasi dan tampilkan pesan | Sistem |

## 14. Model data konseptual

Menggunakan data existing:

- `RndProject` memiliki banyak `RndProjectProduct` dan menyimpan `forecast_percentage`.
- `RndProjectProduct` memiliki banyak `RndProductSalesProjection` dan BOM melalui pivot usage type.
- `RndProductSalesProjection` menyimpan bulan, region, channel, target quantity/revenue, dan branch targets.

Tidak ada entitas, field, pivot, constraint, atau persistence filter baru. Selected product IDs berada pada Livewire/URL/request state dan selalu divalidasi terhadap relasi Project.

## 15. Integration contract

Tidak ada integrasi baru. Kalkulasi Kitchen existing dapat melakukan read ESB melalui fallback resolver saat snapshot lokal WIP belum cukup; perilaku timeout/cache/warning existing tidak diubah. Test wajib memakai fake/mocks existing dan tidak memanggil ESB sungguhan.

Kontrak export internal:

- route existing tetap dipertahankan;
- query `menu_ids[]` opsional membawa integer product IDs;
- parameter tidak ada berarti All Menu;
- selected IDs harus unik dan seluruhnya milik Project serta eligible;
- invalid input menghasilkan validation response aman dan tidak membuat file parsial;
- file tetap memiliki sheet Kitchen dan Store dengan selection yang sama.

## 16. Authorization dan security

- `view material forecast` tetap wajib untuk melihat section, mengubah filter read-only, dan export.
- `edit material forecast` hanya diperlukan untuk mutation percentage existing.
- UI visibility bukan kontrol tunggal; Page, service boundary, dan export controller memvalidasi akses/server input.
- Selected IDs tidak boleh dipakai untuk query global tanpa constraint `rnd_project_id`.
- Error tidak mengungkap nama/menu Project lain.
- Formula-injection protection `safeText()` pada export wajib dipertahankan.
- Tidak ada upload atau data sensitif baru.

## 17. UI/UX specification

### 17.1 Lokasi

Filter ditempatkan pada header Material Forecast dekat toggle Forecast Kitchen/Store dan Export Excel. Struktur halaman existing, warna semantic Kitchen/Store, percentage form, warnings, dan tabel dipertahankan.

### 17.2 Trigger dan ringkasan selection

- Default label: **All Menu**.
- Explicit selection menampilkan chip/nama ringkas dan teks “N dari M menu dipilih”.
- Nama panjang wrap/truncate aman dan nilai lengkap tersedia pada picker.
- Satu aksi jelas mengembalikan ke All Menu.

### 17.3 Picker

- Judul dan deskripsi konteks.
- Search nama atau product code dengan debounce wajar.
- Checkbox multiple selection.
- Baris menu menampilkan nama, code bila ada, total projection, status Main Recipe, dan status BOM Menu.
- Menu tanpa projection disabled dengan label **Belum memiliki Sales Projection**.
- Selected count, **Terapkan**, **Batal**, dan **All Menu**.
- Cancel/backdrop/Escape tidak menerapkan draft selection.
- Apply disabled bila tidak ada explicit selection; user diarahkan memakai All Menu.
- Fokus, keyboard, focus trap, scroll internal, dan mobile mengikuti `ui-consistency.md` serta komponen modal existing.

### 17.4 Hasil

- Copy header menyebut “seluruh menu” atau “N menu terpilih”.
- Total projection/effective quantity dan jumlah menu mengikuti filter.
- Projection detail hanya memuat selected menus.
- Empty state membedakan: tidak ada menu berprojection, selection stale, dan menu eligible tetapi BOM tipe aktif tidak tersedia.
- Warning hanya memuat masalah selected menus.

### 17.5 State dan URL

Selection direkomendasikan URL-backed agar bertahan saat refresh, dapat dibagikan, dan konsisten dengan export. Format akhir mengikuti kemampuan Livewire 4 existing, tetapi URL tidak boleh membawa label/nama, hanya integer IDs yang divalidasi. All Menu memakai URL tanpa `menu_ids`.

### 17.6 Export

Tombol export membangun URL dari applied selection, bukan draft selection. Metadata setiap sheet memuat **Scope Menu: All Menu** atau daftar nama menu terpilih. Nama file existing dipertahankan kecuali suffix scope terbukti diperlukan saat implementasi; perubahan nama file bukan requirement.

## 18. Non-functional requirements

- Filtering dilakukan pada collection/query product sebelum recursion BOM; jangan menghitung semua lalu membuang hasil.
- Hindari query per menu dengan eager loading existing dan aggregate projection yang terukur.
- Selected IDs unik, integer, dan dibatasi jumlah menu dalam Project.
- Tidak menambah seluruh katalog/menu lintas Project ke Livewire payload.
- Uji viewport minimum 360, 390, 768, 1024, 1440 px dan zoom 200%.
- Switching filter/type menampilkan loading state tanpa double submit.
- All Menu harus mempertahankan performa dan hasil baseline dalam toleransi test exact existing.

## 19. Observability dan audit

Tidak ada audit log bisnis baru karena filter tidak menyimpan data. Saat implementasi, ukur query count/render time All Menu dan multiple selection, serta pastikan error log tidak memuat data Project lain. Mutation percentage mempertahankan observability existing.

## 20. Data migration dan kompatibilitas

- Tidak ada migration/backfill.
- Data Project, Product, Sales Projection, branch targets, BOM, dan percentage tidak berubah.
- Route export existing tanpa query tetap valid dan berarti All Menu.
- Service call existing tanpa selected IDs tetap menghasilkan All Menu.
- Halaman Sales & Growth tidak berubah.

## 21. Testing strategy

- Characterization service untuk exact All Menu baseline Kitchen/Store.
- Service test single/multiple menu, percentage, material aggregation, WIP, warnings, notes, `product_count`, dan menu tanpa projection.
- Livewire test default All Menu, picker/search, draft/apply/cancel, switching Kitchen/Store, reset, loading/empty copy, URL state, stale selection, dan permissions.
- Export test tanpa filter, single/multiple selection, metadata scope, kedua sheet, invalid/cross-project IDs, dan formula injection protection.
- Query/performance regression agar selection tidak menambah N+1.
- HTTP/ESB selalu difake sesuai test existing.

## 22. Rollout

Satu PR aplikasi setelah PRD disetujui. Tidak memerlukan feature flag, migration, env, permission sync, queue restart, scheduler, atau manual data operation. UAT staging membandingkan angka All Menu sebelum/sesudah, lalu memeriksa single/multiple menu pada Kitchen, Store, dan Excel.

## 23. Rollback

Revert PR aplikasi. Route export tanpa parameter dan service signature default tetap backward-compatible, sehingga rollback tidak memerlukan perubahan data. Tidak ada data baru yang harus dibersihkan.

## 24. Acceptance criteria

1. Material Forecast membuka dengan All Menu dan hasil sama dengan baseline.
2. User dapat memilih satu atau beberapa menu eligible.
3. Seluruh bulan, region, channel, dan branch projection menu terpilih tetap dijumlahkan.
4. Selection bertahan saat mengganti Kitchen/Store.
5. Summary, detail, rows, warnings, notes, dan source count hanya berasal dari selected menus.
6. Kembali ke All Menu menghapus explicit selection dan memulihkan hasil global.
7. Menu tanpa projection diberi status jelas dan tidak dihitung.
8. Export Excel menggunakan applied selection yang sama pada sheet Kitchen dan Store serta menulis scope.
9. ID invalid/Project lain ditolak server-side tanpa data leak.
10. Filter tidak mengubah data bisnis dan tidak memerlukan permission/migration baru.
11. Test terdampak, Pint, doc-link, code-map, diff check, dan CI lulus.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| UI dan export memakai selection berbeda | Angka layar tidak sama dengan Excel | Applied selection menjadi satu sumber; test kedua sheet |
| ID tampering mengakses Product Project lain | Data leak/kalkulasi salah | Constraint relasi Project dan reject invalid IDs |
| `null` dan array kosong ambigu | Forecast tiba-tiba nol/all | Kontrak eksplisit: null/kosong dinormalisasi All Menu |
| Menu berubah setelah URL dibagikan | Stale selection | Revalidasi setiap render/export dan pesan actionable |
| Multiple selection menambah N+1 | Halaman lambat | Filter sebelum recursion, eager loading, query regression |
| Current all-period aggregation disalahartikan | User mengira filter juga membatasi bulan | Copy menjelaskan scope; month filter tetap non-goal |
| Picker memuat terlalu banyak state | Livewire payload besar | Hanya menu Project, server-side search/pagination bila volume menuntut |

## 26. Open decisions

Tidak ada keputusan yang memblokir dokumentasi. User telah menyetujui All Menu default, single/multiple selection, dan scope filter menu. Filter periode/region/channel/branch berada di luar scope dan dapat direncanakan terpisah bila dibutuhkan.

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Characterization dan kontrak selection | Baseline All Menu, security/query contract, test awal |
| 1 | Service subset calculation | Signature backward-compatible dan hasil single/multiple menu |
| 2 | Livewire/UI filter | Picker, URL/applied state, empty/loading/stale handling |
| 3 | Export consistency | Query validation, scope metadata, Kitchen/Store subset |
| 4 | Hardening dan delivery | Regression/performance test, docs aktual, implementation report, PR |

## 28. Definition of Done

- [ ] Seluruh acceptance criteria terpenuhi.
- [ ] Test service, Livewire, authorization, security, export, dan regression lulus.
- [ ] All Menu terbukti backward-compatible.
- [ ] Tidak ada migration, dependency, permission, atau integrasi baru.
- [ ] UI responsif dan aksesibel diperiksa.
- [ ] Dokumentasi fitur dan README R&D diperbarui sesuai kode.
- [ ] Implementation report mencatat hasil dan deviasi aktual.
- [ ] Pull Request siap direview; merge/deployment tetap keputusan user.
