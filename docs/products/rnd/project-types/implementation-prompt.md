# Prompt Implementasi — Tipe Project R&D: Release Product, Premix, dan Partnership

Salin seluruh prompt berikut ke agent implementasi dari root repository.

```text
Implementasikan perluasan modul `R&D → Project` secara end-to-end berdasarkan dokumen berikut:

- `docs/products/rnd/project-types/prd.md` — sumber requirement produk terbaru dan utama.
- `docs/standards/code-remediation.md` — guardrail arsitektur, keamanan data, performa, dan remediation.
- `docs/standards/ui-consistency.md` — guardrail UI, responsive behavior, modal, state, dan accessibility.
- `docs/products/rnd/project-task-calendar/prd.md` serta PRD checkpoint terkait — referensi Task/Checkpoint existing yang harus dipertahankan.
- dokumentasi Internal Memo yang aktif — hanya sebagai referensi katalog dan UX Add Menu; bukan domain model atau workflow Partnership.

Kerjakan sampai tuntas di repository ini. Jangan berhenti pada planning, audit, atau contoh kode. Hasil akhir harus menyediakan tiga tipe Project dalam menu yang sama:

1. `Release Product` — seluruh behavior existing tetap bekerja.
2. `Premix` — hanya berisi banyak BOM Assembly.
3. `Partnership` — berisi nama event/partnership dan banyak Menu.

Partnership harus tetap ringan. Jangan membawa resolver BOM, material expansion, forecast, shelf life, finalisasi Memo, PDF Memo, atau request ESB ke flow Partnership.

## 1. Aturan kerja wajib

1. Baca `AGENTS.md` dan seluruh dokumen sumber di atas sebelum mengubah kode.
2. Aktifkan skill Laravel, Pest, dan Tailwind project yang relevan. Ikuti instruksi skill dan konvensi repository.
3. Gunakan Laravel Boost `application-info` pada awal audit bila tersedia.
4. Gunakan Laravel Boost `search-docs` sebelum perubahan kode dengan beberapa query broad dan package scope yang tepat. Verifikasi API Laravel 13, Filament 5, Livewire 4, Tailwind CSS 4, dan Pest 4.
5. Gunakan `database-schema` sebelum memutuskan migration/constraint. Gunakan `database-query` hanya untuk inspeksi read-only.
6. Audit `git status --short --branch`, `git diff --stat`, dan diff file terkait sebelum mengedit. Pertahankan seluruh perubahan user yang sudah ada.
7. Jangan memakai `git reset`, `git checkout --`, `git clean`, stash, atau operasi destruktif lain.
8. Periksa sibling files dan reuse pola existing. Jangan memperkenalkan pola arsitektur kedua bila pola repository sudah layak.
9. Gunakan `php artisan make:* --no-interaction` untuk membuat enum, model, migration, factory, class, dan test Laravel baru.
10. Jangan menambah atau mengubah dependency tanpa persetujuan.
11. Jangan mengubah migration lama yang mungkin sudah dijalankan. Buat migration baru yang additive/reversible atau forward-compatible.
12. Jangan menghapus test existing. Update test hanya jika requirement memang berubah dan pertahankan regression coverage.
13. Jangan melakukan request HTTP/ESB nyata dalam test. Fake seluruh boundary eksternal dan cegah stray request bila pola suite mendukungnya.
14. Jangan query database di Blade.
15. Jangan memuat seluruh katalog ke state Livewire/browser.
16. Jangan memanggil HTTP eksternal dari Page, Livewire, Blade, atau action Add Menu/Add Assembly.
17. Semua mutation wajib authorize server-side. Menyembunyikan tombol bukan authorization.
18. Semua mutation multi-row wajib transaction-safe, idempotent terhadap retry/double-click, dan dilindungi unique constraint.
19. Jalankan test terfokus setiap selesai satu phase. Jangan menunggu seluruh implementasi selesai untuk mulai menguji.
20. Jika frontend source berubah, jalankan build yang digunakan repository setelah test terkait.
21. Jika PHP berubah, jalankan `vendor/bin/pint --dirty --format agent` sebelum finalisasi.
22. Jangan commit, push, membuka PR, deploy, atau menjalankan migration/data mutation pada staging/production tanpa instruksi terpisah.
23. Jangan membuat dokumentasi baru selain implementation report bila diperlukan pola repository atau diminta eksplisit.
24. Jika kode/dokumen lama bertentangan, `docs/products/rnd/project-types/prd.md` menjadi requirement produk yang berlaku untuk scope ini.
25. Jangan bertanya tentang hal yang dapat dibuktikan dari schema, kode, test, dan konvensi existing. Bila keputusan berisiko terhadap data production tidak dapat dibuktikan, hentikan hanya bagian berisiko, laporkan bukti, dan lanjutkan bagian aman.

## 2. Outcome akhir yang wajib dicapai

### Daftar Project

- Project existing tetap muncul dan dianggap `Release Product`.
- Daftar menampilkan badge tipe berlabel teks.
- User dapat memfilter: Semua, Release Product, Premix, Partnership.
- Ringkasan/count mengikuti tipe tanpa N+1.
- Route, navigation, search, filter status/waktu, pagination, archive, dan permission existing tetap bekerja.

### Create/Edit

- Create menyediakan pilihan tipe dengan default visible `Release Product`.
- Partnership memunculkan field wajib `Nama Event/Partnership`.
- Tipe disimpan sekali dan immutable.
- Edit menampilkan tipe sebagai read-only, bukan pilihan yang dapat diubah.
- Save mencegah double-submit dan mempertahankan input ketika validasi gagal.

### Release Product

- Seluruh behavior, section, kalkulasi, export, attachment, Product, BOM, forecast, shelf life, CCP, marketing, SOP, task, dan route existing tidak berubah.
- Header hanya mendapat informasi/badge tipe yang konsisten.

### Premix

- Detail hanya menampilkan konten umum Project/Task dan section BOM Assembly.
- User dapat mencari dan multi-select BOM dari katalog lokal.
- Hanya `bom_type_id = 1` yang eligible.
- Validasi tipe BOM dilakukan ulang server-side.
- Premix tidak membuat Product dummy, pivot product-BOM, forecast, material breakdown, shelf life, CCP, marketing, SOP, atau release flow.
- BOM sama dapat digunakan pada Project berbeda, tetapi tidak boleh duplikat dalam satu Project.

### Partnership

- Detail menampilkan nama event dan section Menu Partnership.
- User dapat mencari, memfilter, multi-select, menambah, dan menghapus Menu menggunakan interaksi setara Add Menu Internal Memo.
- Data dibaca dari katalog lokal dan disimpan sebagai snapshot minimum.
- Tidak ada resolver BOM/material, forecast, shelf life, quantity, finalisasi, revision, approval, atau PDF Memo.
- Detail Project dan item existing tetap terbaca walaupun katalog kosong, stale, berubah, atau item menjadi nonaktif.

## 3. Keputusan domain final

Gunakan enum:

- `ReleaseProduct` → `release_product` → `Release Product`;
- `Premix` → `premix` → `Premix`;
- `Partnership` → `partnership` → `Partnership`.

Aturan yang tidak boleh diubah:

1. `release_product` adalah default database dan default model.
2. Semua Project existing otomatis menjadi Release Product.
3. Tidak ada klasifikasi otomatis Project existing menjadi Premix/Partnership.
4. Tipe tidak dapat diubah setelah create.
5. Nama event wajib hanya untuk Partnership dan harus `null` untuk tipe lain.
6. Task Calendar/Checkpoint dan archive merupakan fasilitas umum ketiga tipe.
7. Section domain Release Product tidak tampil pada Premix/Partnership.
8. Premix hanya menerima katalog BOM dengan `bom_type_id = 1`.
9. Satu BOM hanya sekali per Project, tetapi boleh dipakai banyak Project.
10. Partnership menyimpan Menu snapshot minimum, bukan relation ke Internal Memo.
11. Satu Menu/context hanya sekali per Project.
12. Snapshot tersimpan tidak otomatis ditimpa ketika katalog berubah.
13. Remove hanya melepas item dari Project target; jangan menghapus katalog/master.
14. Seluruh ID katalog diverifikasi dari database dan scope server-side; jangan percaya snapshot dari client.
15. Jangan membuat env max-age katalog baru untuk fitur ini.
16. Jangan hard-code user, role, company, brand, Branch, atau credential.

## 4. Phase 0 — Audit dan characterization

Sebelum mengedit behavior, lakukan audit read-only berikut.

### 4.1 Worktree dan struktur

1. Jalankan `git status --short --branch` dan `git diff --stat`.
2. Petakan dengan `rg`:
   - `RndProject`, Resource, `ListProjects`, `ViewProject`, dan seluruh view Project;
   - create/edit/delete/archive Project;
   - `RndProjectBom`, `RndProjectProduct`, pivot Product-BOM, dan consumer `esb_bom_id`;
   - Task Calendar, template, checkpoint, attachment, export, forecast, shelf life, CCP, marketing, dan SOP;
   - policy/permission/navigation Project;
   - `RndBomCatalog`, eligibility `bom_type_id = 1`, picker, sync, dan test;
   - katalog Menu Internal Memo, Add Menu action/query, picker modal, scope company/brand/Branch, sync state, dan test;
   - seluruh test Project dan Internal Memo yang mungkin menjadi regression boundary.
3. Identifikasi file yang sedang diubah user dan jangan menimpanya.

### 4.2 Schema dan data contract

Inspeksi schema aktual untuk:

- `rnd_projects`;
- `rnd_project_boms`;
- `rnd_project_products` dan pivot terkait;
- `rnd_bom_catalogs`;
- tabel katalog Menu aktif;
- `brands`, `branches`, dan mapping context yang benar-benar digunakan;
- soft-delete, FK, index, dan unique constraint terkait.

Verifikasi secara eksplisit bahwa `rnd_project_boms.esb_bom_id` saat ini unique global. Audit seluruh caller yang mungkin mengasumsikan satu BOM hanya mempunyai satu row global.

### 4.3 Baseline test dan performa

1. Jalankan test Project existing yang paling relevan sebagai baseline.
2. Jalankan test katalog/BOM dan Internal Memo Add Menu yang relevan.
3. Catat failure existing; jangan menyamarkannya sebagai akibat implementasi.
4. Ukur query count/list-detail dan ukuran snapshot Livewire yang realistis bila harness existing tersedia.
5. Tambahkan characterization tests sebelum memisahkan `ViewProject` atau mengubah constraint BOM.

### 4.4 Keputusan Phase 0

Tutup keputusan implementasi berdasarkan bukti:

- metadata Menu stabil yang tersedia pada katalog aktif;
- context uniqueness Menu yang benar (company dan ID Menu, ditambah Brand hanya bila identitas katalog membutuhkannya);
- apakah query katalog Memo dapat diekstrak menjadi query domain-neutral tanpa membawa workflow Memo;
- apakah Project Task/Checkpoint sudah aman dipakai semua tipe;
- strategi migration constraint BOM yang tidak merusak consumer existing;
- kebutuhan pagination dan batas bulk selection berdasarkan baseline.

Jika extraction query Menu terlalu berisiko, buat Query Partnership tipis yang membaca tabel katalog lokal yang sama. Jangan membuat Partnership bergantung pada model/action Memo.

## 5. Characterization Release Product

Sebelum refactor detail page, tambah/pertahankan Pest tests yang membuktikan:

- create Project tanpa tipe menghasilkan Release Product;
- Project legacy dapat dibuka;
- list/search/filter/pagination tetap bekerja;
- detail menampilkan seluruh section Release Product existing;
- task/checkpoint, Product, BOM, document, forecast, shelf life, CCP, marketing, SOP, export, archive/restore, dan route penting tidak berubah sesuai scope existing;
- permission dan access scope existing tidak berubah;
- count/list tidak terkena N+1 baru.

Jangan melakukan snapshot test seluruh HTML/Tailwind. Kunci behavior dan elemen penting saja.

## 6. Phase 1 — Fondasi tipe Project

### 6.1 Enum

Buat enum `RndProjectType` mengikuti konvensi enum repository:

- backed string values sesuai keputusan domain;
- label/helper hanya bila pola enum existing menggunakannya;
- TitleCase enum cases;
- tidak menaruh aturan query atau authorization pada enum.

### 6.2 Migration `rnd_projects`

Buat migration baru, jangan edit migration lama.

Tambahkan:

- `project_type` string, non-null, default `release_product`, indexed;
- `partnership_event_name` string nullable dengan panjang yang konsisten terhadap field nama lain.

Ketentuan:

- migration additive dan reversible;
- default database menjamin data existing aman tanpa loop update per record;
- jangan mengubah row existing menjadi tipe lain;
- pertimbangkan strategi database yang aman bila tabel besar;
- jangan mencampur cleanup unrelated.

### 6.3 Model dan factory

Perbarui `RndProject`:

- `$fillable` eksplisit;
- cast enum untuk `project_type`;
- mirror default melalui `$attributes` bila konvensi model mengharuskan;
- typed relationship untuk Partnership Menu;
- helper tipe hanya jika membuat caller lebih jelas dan tidak menduplikasi enum comparison di banyak tempat.

Perbarui factory:

- default Release Product;
- state Release Product, Premix, Partnership;
- Partnership state menghasilkan event name yang valid.

### 6.4 Create/Update Action

Jangan menambah lebih banyak logika CRUD ke `ListProjects`.

Buat/refactor Action single-purpose:

- `CreateRndProjectAction` untuk authorization, normalisasi, rule lintas field, dan transaction create;
- `UpdateRndProjectAction` untuk field umum tanpa mengubah tipe.

Validation rules:

- type wajib termasuk enum pada create;
- event name wajib untuk Partnership setelah trim/normalisasi;
- event name dipaksa `null` untuk tipe lain;
- update tidak menerima perubahan tipe;
- date rules dan field existing dipertahankan.

Action harus menerima actor/context eksplisit mengikuti pola repository dan authorize server-side.

### 6.5 UI create/edit/list

- Tambahkan pilihan tipe pada create.
- Gunakan conditional field untuk event Partnership.
- Edit menampilkan tipe read-only.
- Tambahkan badge teks dan filter tipe pada list.
- Gunakan `withCount`/aggregate untuk count relevan; jangan eager-load semua children hanya untuk count.
- Pertahankan route/navigation/status/filter existing.
- Tambahkan loading/disabled state pada save.

Test Phase 1 sebelum lanjut.

## 7. Phase 2 — Pemisahan workspace detail

Refactor secara bertahap, bukan rewrite `ViewProject`.

Target struktur:

- satu entry point route/detail Project;
- header umum Project;
- Task Calendar/Checkpoint umum;
- workspace Release Product existing;
- workspace Premix;
- workspace Partnership.

Ketentuan:

1. Blade hanya merender data yang sudah dipersiapkan.
2. Jangan menaruh query atau HTTP call di Blade.
3. Jangan membuat satu Blade monolitik dengan percabangan tipe berulang.
4. Jangan membuat komponen generik spekulatif. Extract hanya bagian yang benar-benar shared.
5. Release Product harus tetap identik secara fungsional.
6. Premix/Partnership tidak boleh menginisialisasi service/query berat Release Product.
7. Authorization action tetap server-side walaupun workspace/CTA tersembunyi.

Jalankan characterization tests Release Product setelah pemisahan.

## 8. Phase 3 — Premix

### 8.1 Remediasi schema BOM

Audit dulu seluruh asumsi unique global `rnd_project_boms.esb_bom_id`.

Target domain:

- BOM yang sama boleh berada pada banyak Project;
- BOM yang sama hanya satu kali dalam Project yang sama.

Buat migration forward-compatible:

1. tambahkan `bom_type_id` nullable sebagai snapshot;
2. audit collision/data anomali sebelum constraint diubah;
3. lepas unique global `esb_bom_id` secara aman;
4. tambahkan unique composite `(rnd_project_id, esb_bom_id)`;
5. tambahkan index yang dibutuhkan query bila belum tercakup;
6. jangan menebak `bom_type_id` legacy jika tidak ada bukti aman;
7. jangan menghapus row legacy;
8. buat `down()` realistis. Jangan mencoba memasang ulang unique global bila data baru lintas Project membuat rollback destruktif; gunakan guard/forward-fix strategy yang jelas.

Jika audit membuktikan model `RndProjectBom` terlalu terikat pada Release Product untuk dipakai langsung, jangan memaksakan reuse. Laporkan bukti dan gunakan tabel junction/snapshot Premix terpisah yang memenuhi invariant. Default PRD tetap reuse `rnd_project_boms`, jadi deviasi wajib berbukti dan dicatat.

### 8.2 Model/factory

- tambahkan `bom_type_id` ke fillable/cast;
- pertahankan relation Product existing;
- jangan membuat Product/pivot untuk Premix;
- factory menyediakan state Assembly/non-Assembly untuk test.

### 8.3 Query katalog

Buat `PremixAssemblyCatalogQuery` atau nama yang mengikuti konvensi repository:

- membaca `RndBomCatalog` lokal;
- hanya `bom_type_id = 1` dan eligibility existing yang sah;
- mengikuti access/context existing yang terbukti;
- search/filter/pagination server-side;
- mengecualikan/menandai item yang sudah dipilih tanpa memuat semua row ke browser;
- memilih hanya kolom yang dibutuhkan picker;
- tidak memanggil sync/HTTP secara implisit.

### 8.4 Add/Remove Actions

`AddPremixAssembliesAction`:

- authorize actor terhadap Project target;
- pastikan Project bertipe Premix;
- normalisasi/deduplikasi array ID;
- validasi limit selection yang telah ditetapkan;
- query seluruh katalog terpilih sekali dan pastikan semuanya ditemukan/eligible/scoped;
- ambil snapshot server-side, termasuk `bom_type_id = 1`;
- simpan bulk dalam transaction;
- tangani unique race/double-submit secara idempotent atau validation result yang jelas;
- jangan membuat Product, pivot, forecast, material, shelf life, atau side effect eksternal.

`RemovePremixAssemblyAction`:

- authorize server-side;
- pastikan row benar-benar milik Project Premix target;
- hapus/lepas hanya row Project tersebut;
- jangan menghapus katalog/master atau row Project lain.

### 8.5 UI Premix

- section `BOM Assembly` dengan CTA `Tambah Assembly`;
- reuse `rnd/picker-modal` jika kontraknya sesuai;
- multi-select, selected count, search, pagination, loading, error, empty, cancel, dan disabled submit;
- desktop table ringkas, mobile list/card tanpa whole-page horizontal scroll;
- item katalog yang berubah/nonaktif tetap tampil dari snapshot dengan status informatif;
- tidak tampilkan CTA pada user tanpa permission, tetapi tetap enforce Action.

### 8.6 Test Premix

Wajib mencakup:

- Assembly dapat ditambahkan;
- non-Assembly ditolak meskipun request dimanipulasi;
- Project non-Premix ditolak;
- duplicate dan double-submit tidak membuat row kedua;
- BOM sama dapat dipakai pada dua Project berbeda;
- bulk add tidak membuat Product/pivot atau memanggil workflow berat;
- unauthorized/foreign-context ID ditolak;
- remove hanya Project target;
- snapshot tetap tampil bila katalog nonaktif/hilang;
- query count tidak linear terhadap jumlah pilihan/item.

## 9. Phase 4 — Partnership

### 9.1 Migration tabel Menu Partnership

Buat model, factory, dan migration baru mengikuti nama repository, target `rnd_project_partnership_menus`.

Kolom minimum:

- `id`;
- `rnd_project_id` FK;
- `company_code`;
- `brand_id` nullable hanya bila context katalog aktif benar-benar menyediakannya;
- `esb_menu_id`;
- `menu_code` nullable;
- `menu_name` wajib;
- `category_name` nullable;
- `menu_snapshot` JSON nullable untuk metadata minimum yang terbukti dibutuhkan;
- `sort_order` unsigned integer default 0;
- `created_by` mengikuti pola audit repository;
- timestamps.

Constraint/index:

- unique `(rnd_project_id, company_code, esb_menu_id)` atau identity setara yang dibuktikan Phase 0;
- index `(rnd_project_id, sort_order)`;
- FK mengikuti retention convention repository;
- jangan menyimpan material, forecast, shelf life, quantity, resolver output, atau sync status per row.

Jika identitas Menu pada katalog existing memerlukan context tambahan agar benar-benar unik, gunakan context minimum yang dibuktikan schema/contract test dan dokumentasikan alasan pada kode/test. Jangan menambah Brand/Branch hanya berdasarkan asumsi.

### 9.2 Model/factory

- `$fillable` eksplisit;
- casts untuk snapshot dan ID yang perlu;
- typed `project(): BelongsTo`;
- relation `RndProject::partnershipMenus(): HasMany`;
- factory memakai katalog fixture/snapshot realistis.

### 9.3 Query katalog Menu

Partnership tidak boleh memanggil model/action lifecycle Internal Memo.

Pilih salah satu berdasarkan audit:

1. extract core katalog menjadi query domain-neutral `RndMenuCatalogQuery`, lalu pertahankan adapter Internal Memo; atau
2. buat `PartnershipMenuCatalogQuery` tipis yang membaca tabel katalog lokal yang sama.

Query wajib:

- mengikuti scope company/brand/Branch dan authorization existing yang terbukti;
- menghormati akses all branch tanpa melewati feature permission;
- search/filter/pagination server-side;
- memilih kolom minimum;
- tidak membaca material/BOM detail;
- tidak memanggil ESB/sync otomatis;
- tidak membawa rule finalisasi/revision Memo.

Jika katalog tersedia tetapi stale, ikuti contract katalog existing dan tampilkan freshness bila tersedia. Jangan membuat env max-age baru. Jika katalog kosong/gagal, detail Project tetap bekerja dan modal memberi pesan actionable.

### 9.4 Add/Remove Actions

`AddPartnershipMenusAction`:

- authorize actor;
- pastikan Project bertipe Partnership;
- normalisasi/deduplikasi ID;
- query seluruh row terpilih dalam satu query terukur;
- pastikan setiap ID berada dalam scope actor/context;
- ambil snapshot dari database, bukan client;
- simpan bulk dalam transaction;
- idempotent terhadap retry/double-click/unique race;
- tidak memanggil HTTP, resolver BOM, material, forecast, shelf life, sync, finalization, atau PDF.

`RemovePartnershipMenuAction`:

- authorize actor;
- pastikan row milik Project Partnership target;
- hapus hanya snapshot relasi target;
- jangan menghapus katalog/master.

### 9.5 UI Partnership

- header menampilkan badge Partnership dan nama event tanpa mengulang informasi berlebihan;
- section `Menu Partnership` dengan CTA `Tambah Menu`;
- gunakan UX Add Menu Internal Memo sebagai referensi: search, filter context yang relevan, multi-select, selected count, submit/cancel;
- jangan tampilkan field Memo seperti forecast, material, minimum order, shelf life, revision, atau finalization;
- desktop ringkas dan mobile responsif;
- bedakan empty state “belum ada Menu” dari “katalog belum tersedia”;
- item tersimpan selalu dapat dibaca dari snapshot.

### 9.6 Test Partnership

Wajib mencakup:

- nama event required untuk Partnership dan null untuk tipe lain;
- search/filter/pagination katalog;
- authorized bulk add/remove;
- Project non-Partnership ditolak;
- out-of-scope/forged ID ditolak;
- duplicate/double-submit idempotent;
- snapshot tetap stabil setelah katalog berubah/nonaktif/hilang;
- detail tetap dapat dibuka ketika katalog kosong/gagal;
- Add Menu tidak melakukan HTTP atau memanggil BOM/material/forecast/shelf-life service;
- query count bulk add dan list tidak linear;
- user akses terbatas dan all-branch mengikuti scope yang benar.

## 10. Permission dan policy

Gunakan permission Project existing sebagai baseline. Jangan membuat permission baru kecuali audit membuktikan operasi add/remove membutuhkan permission granular dan pola repository memang menggunakannya.

Wajib:

- `viewAny`, `view`, create, update, archive/delete/restore mengikuti behavior existing;
- add/remove Assembly/Menu memanggil Policy/Gate server-side;
- user yang tidak dapat melihat Project tidak dapat mengakses child action via Livewire/direct ID;
- all-branch hanya memperluas Branch scope yang sah, bukan melewati permission modul;
- jangan melakukan permission sync massal atau menambah role mapping tanpa instruksi.

Tambahkan test forbidden menggunakan assertion spesifik dan test direct action manipulation.

## 11. UI consistency dan accessibility

Ikuti `docs/standards/ui-consistency.md`:

- gunakan Project Workspace existing sebagai baseline;
- satu header utama per konteks;
- container, typography, input, button, border, radius, dan spacing konsisten;
- kartu/section biasa tanpa decorative shadow;
- badge/status mempunyai label teks, bukan warna saja;
- petunjuk section berada di container yang relevan;
- gunakan gap untuk sibling spacing;
- reuse component existing sebelum membuat komponen baru;
- Tailwind CSS 4 saja, jangan gunakan utility deprecated;
- dark mode hanya bila halaman existing mendukungnya.

Modal picker wajib:

- judul dan deskripsi jelas;
- focus trap dan focus return;
- Escape/close/cancel tidak menyimpan;
- backdrop tidak menyebabkan save;
- tidak ada nested modal;
- loading, disabled submit, selected count, empty, error, dan retry state;
- nama panjang dan validation error tidak merusak layout;
- mobile/tablet/desktop dan zoom 200% dapat digunakan;
- tidak menyebabkan horizontal scroll seluruh halaman.

Jangan menulis test yang mengunci seluruh urutan class Tailwind. Uji state, semantics, visibility, dan interaksi penting.

## 12. Error handling dan observability

- Validation message harus actionable.
- Duplicate constraint/race tidak boleh menjadi generic 500.
- Transaction gagal tidak boleh menyisakan sebagian item.
- Error katalog tidak boleh merusak data snapshot existing.
- Jangan log credential, token, atau payload katalog penuh.
- Gunakan logging/context existing untuk project ID, project type, actor ID, item count, operation, dan outcome bila memang tersedia.
- Bedakan forbidden, validation failure, catalog unavailable, dan database conflict.
- Ukur query count, render time, dan Livewire payload sebelum/sesudah pada jalur utama.
- Jangan membangun observability framework baru hanya untuk fitur ini.

## 13. Performance guardrails

1. List menggunakan aggregate/`withCount`, bukan load relation penuh.
2. Detail eager-load hanya relation/kolom yang dibutuhkan.
3. Picker menggunakan server-side search dan pagination.
4. Jangan serialize seluruh snapshot katalog ke Livewire.
5. Bulk add menggunakan satu query katalog dan satu transaction dengan operasi batch yang sesuai.
6. Jangan query per selected ID atau per rendered row.
7. Jangan membaca JSON detail BOM pada list jika tidak ditampilkan.
8. Jangan menjalankan service Release Product untuk Premix/Partnership.
9. Tambahkan test query count pada jalur yang berisiko N+1 bila pola suite memungkinkan.
10. Laporkan baseline dan hasil akhir; target numerik mengikuti bukti baseline.

## 14. Migration dan data safety

- Seluruh Project lama harus aman tanpa command manual.
- Jangan menghapus, mengubah tipe, atau memindahkan Product/BOM/Document/Task existing.
- Jangan backfill `bom_type_id` legacy dengan tebakan nama string jika tidak dapat dibuktikan.
- Audit data sebelum melepas unique global BOM.
- Migration constraint harus mempertimbangkan rollback setelah BOM sama dipakai di lebih dari satu Project.
- Jangan menjalankan migration terhadap staging/production.
- Jangan membuat seeder yang menyentuh data user.
- Test gunakan factory; data demo hanya bila diminta terpisah.

## 15. Urutan test minimum

Jalankan test paling sempit setelah tiap perubahan, lalu modul terkait.

Minimal mencakup:

1. enum/model/factory/migration contract;
2. create/update/list/filter tiga tipe;
3. Release Product characterization/regression;
4. workspace visibility per tipe;
5. Premix Query/Action/Livewire/UI behavior;
6. Partnership Query/Action/Livewire/UI behavior;
7. Policy, forbidden, cross-project, dan context scope;
8. duplicate/idempotency/race-safe behavior;
9. no-external-request assertions;
10. query-count/N+1 regression;
11. route/Filament discovery;
12. browser/smoke test pada halaman yang berubah bila harness tersedia.

Gunakan:

- `php artisan test --compact <specific-test-file>` atau `--filter=...` untuk iterasi;
- test modul R&D terkait setelah phase selesai;
- full suite hanya pada milestone/final jika waktu dan environment memungkinkan.

Jika test gagal:

- bedakan regression baru dari pre-existing failure;
- perbaiki root cause dalam scope;
- jangan melemahkan assertion yang masih sesuai requirement;
- jangan menghapus test;
- laporkan test yang tidak dapat dijalankan beserta sebab konkret.

## 16. Quality gates final

Sebelum menyatakan selesai:

1. Jalankan `vendor/bin/pint --dirty --format agent` untuk PHP yang berubah.
2. Jalankan seluruh test terfokus dan test modul R&D terkait.
3. Jalankan `git diff --check`.
4. Jalankan route/Filament discovery check yang relevan.
5. Jalankan frontend build bila Blade/JS/CSS/source frontend berubah sesuai kebutuhan repository.
6. Lakukan pemeriksaan browser pada desktop dan mobile:
   - daftar/filter tipe;
   - create tiap tipe;
   - detail Release Product;
   - Premix picker/add/remove;
   - Partnership picker/add/remove;
   - empty/loading/error/validation state;
   - keyboard/Escape/focus return;
   - nama panjang dan zoom 200%;
   - console/JavaScript errors.
7. Bandingkan query count dan Livewire payload terhadap baseline.
8. Audit diff akhir untuk perubahan unrelated, credential, `.env`, generated build assets, dan file sementara.
9. Jangan commit/push/deploy.

## 17. Acceptance criteria final

Implementasi belum selesai sampai seluruh poin berikut terpenuhi:

- [ ] Tiga tipe Project tersedia dan tersimpan dengan enum yang benar.
- [ ] Project existing/default tetap Release Product.
- [ ] Tipe immutable setelah create.
- [ ] Partnership event name tervalidasi conditional.
- [ ] Release Product tidak mengalami perubahan workflow/data/route/permission.
- [ ] Premix hanya menerima Assembly `bom_type_id = 1`.
- [ ] BOM sama dapat dipakai lintas Project tetapi tidak duplikat dalam satu Project.
- [ ] Premix tidak membuat Product dummy atau menjalankan workflow Release Product.
- [ ] Partnership dapat menyimpan banyak Menu snapshot secara ringan.
- [ ] Add Menu konsisten dengan UX Internal Memo tanpa coupling ke lifecycle Memo.
- [ ] Tidak ada HTTP/resolver/material/forecast/shelf-life call pada Partnership.
- [ ] Katalog kosong/stale/gagal tidak merusak detail atau snapshot existing.
- [ ] Authorization dan scope diuji server-side, termasuk direct ID manipulation.
- [ ] Mutation transaction-safe dan idempotent.
- [ ] Tidak ada query di Blade, N+1, atau katalog penuh di Livewire.
- [ ] UI responsif, aksesibel, dan konsisten dengan guardrail.
- [ ] Test terfokus/modul, Pint, diff check, discovery, dan build relevan lulus.
- [ ] Tidak ada commit, push, migration remote, atau deployment tanpa instruksi.

## 18. Laporan akhir wajib

Berikan laporan ringkas tetapi berbukti yang mencakup:

1. outcome yang selesai untuk Release Product, Premix, dan Partnership;
2. daftar file utama yang dibuat/diubah;
3. keputusan schema/constraint final, khususnya unique BOM dan identity Menu;
4. bagaimana data Project lama diamankan;
5. bagaimana Partnership dipastikan tidak memakai workflow berat Internal Memo;
6. authorization/company/brand/Branch scope yang digunakan;
7. hasil test lengkap dengan command dan jumlah pass/fail;
8. hasil Pint, diff check, build, browser check, dan query/payload comparison;
9. pre-existing failure atau bagian yang tidak dapat diverifikasi;
10. langkah migration/deployment dan rollback yang perlu dilakukan kemudian, tanpa menjalankannya;
11. status git serta pernyataan eksplisit bahwa commit/push/deploy belum dilakukan.

Jangan menyatakan selesai hanya karena UI tampil atau happy path lulus. Selesai berarti data lama aman, seluruh invariant server-side teruji, Release Product tidak regresi, Premix hanya Assembly, Partnership tetap ringan, dan quality gates relevan telah dilewati.
```
