# Prompt Implementasi — Kitchen: Jadwal Produksi Sederhana

Salin seluruh prompt berikut ke agent implementasi dari root repository.

```text
Implementasikan dan sederhanakan modul `Kitchen → Jadwal Produksi` secara end-to-end berdasarkan dokumen berikut:

- `docs/products/kitchen/production-schedule/prd.md` — sumber requirement terbaru dan utama.
- `docs/standards/code-remediation.md` — standar arsitektur, keamanan data, dan kualitas teknis.
- `docs/standards/ui-consistency.md` — standar UI, responsive behavior, modal, state, dark mode, dan accessibility.
- `docs/products/rnd/bom-adjustment/prd.md` — referensi katalog BOM Assembly existing.
- `docs/products/rnd/project-task-calendar/prd.md` — referensi mekanisme kalender existing saja.

Kerjakan sampai tuntas di repository ini. Jangan berhenti pada planning atau contoh kode. Implementasi akhir harus berupa kalender produksi sederhana: klik tanggal, pilih beberapa BOM Assembly dari katalog lokal yang sama seperti modul R&D, isi quantity, lalu simpan. Jadwal langsung aktif dan dapat diedit atau dibatalkan. Tidak ada Draft, Publish, approval, freshness max-age, atau sinkronisasi khusus Kitchen.

## 1. Aturan kerja wajib

1. Baca `AGENTS.md` dan seluruh dokumen sumber sebelum mengubah kode.
2. Aktifkan skill Laravel project yang relevan dan baca rule architecture, migration, Eloquent, database performance, security, validation, testing, Blade, dan style.
3. Gunakan Laravel Boost `application-info` pada awal audit serta `search-docs` sebelum perubahan kode. Verifikasi API Laravel 13, Filament 5, Livewire 4, dan Pest 4.
4. Gunakan Laravel Boost `database-schema` sebelum memutuskan perubahan migration. Gunakan `database-query` hanya untuk inspeksi read-only.
5. Audit `git status`, branch, dan seluruh diff terlebih dahulu. Worktree sudah mungkin berisi implementasi Kitchen yang belum selesai. Pertahankan perubahan user dan jangan memakai reset, checkout, clean, atau stash.
6. Periksa sibling files dan reuse pola existing. Jangan membuat arsitektur kedua bila pola project sudah tersedia.
7. Gunakan `php artisan make:* --no-interaction` untuk file Laravel baru.
8. Jangan mengubah dependency tanpa persetujuan.
9. Jangan mengubah migration yang sudah pernah dijalankan di environment bersama. Gunakan migration forward-compatible.
10. Jangan menghapus test existing. Perbarui test Kitchen yang requirement-nya berubah dan pertahankan regression tests R&D BOM.
11. Jangan melakukan request ESB nyata dalam tests.
12. Jangan commit, push, deploy, atau menjalankan migration/data mutation pada staging/production tanpa instruksi terpisah.
13. Jangan membuat dokumentasi tambahan selain laporan akhir bila memang diperlukan.
14. Setiap mutation harus authorize server-side; visibility tombol bukan pengganti Policy.
15. Jangan query di Blade, jangan memuat seluruh katalog ke Livewire, dan jangan memuat JSON detail BOM pada kalender.
16. Jalankan test terfokus setelah setiap fase.
17. Bila dokumen lama atau kode existing bertentangan, `docs/products/kitchen/production-schedule/prd.md` terbaru menjadi requirement produk yang berlaku.

## 2. Outcome yang wajib dicapai

Flow akhir:

1. Buka `Kitchen → Jadwal Produksi`.
2. Kalender bulanan ditampilkan.
3. Klik tanggal.
4. Pilih Branch bila context/filter belum menentukan Branch.
5. Pilih satu atau beberapa BOM Assembly dari `RndBomCatalog` menggunakan pencarian dan pagination seperti modul R&D.
6. Isi quantity masing-masing BOM dalam UOM hasilnya.
7. Klik `Simpan`.
8. Jadwal langsung tersimpan sebagai `scheduled` dan tampil di kalender.
9. Jadwal dapat dilihat dan diedit.
10. Jadwal dapat dibatalkan; data header, item, dan snapshot tetap disimpan.

Tidak boleh ada:

- `Simpan Draft`;
- tombol atau Action Publish;
- approval/rejection;
- status Draft/Published/In Progress/Completed;
- `KITCHEN_BOM_CATALOG_MAX_AGE_HOURS`;
- freshness blocker;
- sync-run/job/queue khusus Kitchen;
- mutation BOM ke ESB;
- field `is_kitchen` yang diwajibkan oleh fitur ini;
- nested modal.

## 3. Keputusan domain final

1. Satu jadwal adalah satu Branch pada satu tanggal.
2. Satu Branch hanya boleh mempunyai satu jadwal per tanggal.
3. Satu jadwal dapat berisi banyak BOM Assembly.
4. BOM yang sama hanya boleh satu kali dalam jadwal yang sama.
5. `planned_quantity` adalah output yang direncanakan dalam UOM hasil BOM.
6. Quantity decimal `(18,4)`, lebih dari nol, dan tidak dihitung menggunakan float.
7. Status hanya `scheduled` dan `cancelled`.
8. Create langsung menghasilkan `scheduled`.
9. Hanya `scheduled` yang dapat diedit.
10. Jadwal persisted tidak di-hard-delete lewat UI. Gunakan `Batalkan Jadwal` dan pertahankan items.
11. Cancelled bersifat read-only.
12. Untuk user terbatas, Branch berasal dari Branch aktif yang dapat diakses dan terhubung ke Brand bernama `Kitchen`. User dengan akses seluruh Branch dapat memakai seluruh Branch aktif. Tidak perlu field `is_kitchen`.
13. Katalog BOM adalah katalog lokal global yang sama dengan modul R&D.
14. Pemilihan mengikuti eligibility modul R&D dan wajib `bom_type_id = 1`, tanpa usia maksimum katalog.
15. Jika katalog kosong, arahkan pengguna melakukan refresh melalui mekanisme R&D existing; jangan membuat mekanisme refresh Kitchen.
16. Simpan snapshot identitas BOM ringan pada setiap save, bukan full recipe snapshot.

## 4. Phase 0 — Audit worktree dan deployment state

Lakukan audit read-only sebelum mengedit:

1. Jalankan `git status --short --branch`, `git diff --stat`, dan audit diff setiap file Kitchen/R&D BOM.
2. Petakan referensi dengan `rg` untuk:
   - `KITCHEN_BOM_CATALOG_MAX_AGE_HOURS`;
   - `config('kitchen`;
   - Draft/Published dan `KitchenProductionScheduleStatus`;
   - `PublishProductionScheduleAction`;
   - transition model/table;
   - `RndBomCatalogSyncRun` dan `last_seen_sync_run_id`;
   - `is_kitchen`;
   - permission publish/cancel/sync Kitchen;
   - navigation/sidebar Kitchen;
   - page, Blade, services, actions, models, factories, migrations, dan tests Kitchen.
3. Periksa implementasi kalender Project Task existing sebagai referensi visible range, desktop grid, mobile agenda, dan query scope.
4. Periksa `RndBomCatalog`, halaman R&D BOM, query picker, dan sync existing untuk memastikan sumber katalog yang akan direuse.
5. Gunakan `php artisan migrate:status` dan schema inspection untuk menentukan apakah migration Kitchen/sync-run sudah pernah dijalankan pada database lokal.
6. Jangan menganggap status lokal membuktikan migration belum pernah dijalankan pada staging/production. Periksa riwayat git/deployment atau informasi yang tersedia.
7. Jalankan test Kitchen/R&D BOM yang ada sebagai baseline. Catat pre-existing failure.

### Decision rule migration

- Jika migration masih untracked/uncommitted dan terbukti belum pernah dibagikan/dijalankan di environment bersama, sederhanakan migration awal sebelum merge.
- Jika migration sudah committed atau mungkin sudah dijalankan pada staging/production, jangan mengedit atau menghapusnya. Buat migration forward-compatible yang mengubah schema tanpa kehilangan data.
- Jangan drop tabel/kolom berisi data tanpa backup dan persetujuan eksplisit.
- Jika status deployment tidak dapat dibuktikan, pilih jalur forward-compatible.

## 5. Audit dan cleanup implementasi berlebihan

Worktree mungkin sudah memiliki komponen dari rancangan lama. Audit referensi satu per satu.

### 5.1 Hapus dari flow Kitchen

- dependency terhadap `KITCHEN_BOM_CATALOG_MAX_AGE_HOURS`;
- `config/kitchen.php` bila hanya berisi max age;
- entry `.env.example` max age;
- freshness warning dan blokir save/publish;
- Publish action, button, permission, actor, timestamp, dan tests;
- Draft state dan flow save Draft;
- transition timeline/table bila belum pernah dideploy dan tidak digunakan pihak lain;
- permission sync khusus Kitchen;
- `is_kitchen` dan perubahan form Branch bila hanya dibuat untuk fitur ini dan belum dideploy;
- durable sync-run, unseen reconciliation, atau perubahan sync R&D yang dibuat hanya sebagai prasyarat Kitchen dan belum dibutuhkan modul R&D.

### 5.2 Jangan hapus secara buta

- Jangan menghapus hardening R&D BOM jika audit membuktikan sudah menjadi requirement atau dipakai modul R&D lain.
- Jangan menghapus migration yang sudah dijalankan.
- Jangan menghapus class/file sebelum `rg` membuktikan seluruh caller sudah dipindahkan.
- Jangan menghapus data jadwal yang mungkin sudah dibuat.
- Jangan menimpa perubahan user yang tidak terkait.

### 5.3 Refactor yang dipertahankan

Pertahankan dan sederhanakan bagian yang masih berguna:

- navigation group Kitchen;
- calendar page dan responsive view;
- Schedule/Item model;
- Branch authorization;
- BOM catalog query/picker;
- create/update transaction;
- cancellation;
- factories dan tests yang dapat diperbarui.

## 6. Schema target

### 6.1 `kitchen_production_schedules`

Target minimum:

- `id`;
- `branch_id` foreign key;
- `production_date` date;
- `status` string default `scheduled`;
- `notes` nullable text;
- `created_by` foreign key user;
- `updated_by` nullable foreign key user;
- `cancelled_by` nullable foreign key user;
- `cancelled_at` nullable timestamp;
- `cancellation_reason` nullable text;
- timestamps.

Constraints/indexes:

- unique `(branch_id, production_date)`;
- index `(branch_id, production_date)`;
- index `(branch_id, status, production_date)`;
- Branch/User deletion tidak boleh menghapus histori schedule.

### 6.2 `kitchen_production_schedule_items`

Target minimum:

- `id`;
- `kitchen_production_schedule_id` foreign key;
- `rnd_bom_catalog_id` nullable foreign key dengan retention aman;
- `esb_bom_id`;
- `bom_code`;
- `bom_name`;
- `bom_type_id`;
- `product_detail_id`;
- `product_code`;
- `product_name`;
- `uom_name`;
- `planned_quantity` decimal `(18,4)`;
- `sequence` unsigned integer;
- `notes` nullable text;
- timestamps.

Constraints/indexes:

- unique `(kitchen_production_schedule_id, esb_bom_id)`;
- unique `(kitchen_production_schedule_id, sequence)`;
- index foreign keys dan `esb_bom_id`.

### 6.3 Tidak diperlukan

- full `detail_snapshot`;
- `bom_edited_at_snapshot`;
- published fields;
- transition table untuk workflow sederhana;
- Kitchen-specific sync-run table;
- `last_seen_sync_run_id` hanya demi Kitchen;
- Branch `is_kitchen` hanya demi Kitchen.

Sesuaikan schema dengan jalur migration aman dari Phase 0. Jangan memaksakan drop bila deployment state tidak pasti.

## 7. Enum, model, factory, dan permission

### Status

Gunakan enum sederhana:

- `Scheduled` → `scheduled` → label `Scheduled`;
- `Cancelled` → `cancelled` → label `Cancelled`.

Hapus varian Draft/Published dari flow dan tests. Mirror default database pada model `$attributes`.

### Model

- `$fillable` eksplisit;
- typed relationships;
- cast date/datetime/status/decimal/array sesuai kebutuhan;
- tidak ada global scope tersembunyi untuk Branch;
- snapshot identitas ringan disimpan pada item;
- cancelled immutable melalui Action/Policy, bukan hanya UI.

### Factory

- factory schedule default `scheduled`;
- state `cancelled`;
- item factory Assembly dengan quantity decimal;
- reuse Branch/RndBomCatalog factory existing.

### Permission

Permission minimum:

- `view kitchen production schedules`;
- `view all branch kitchen production schedules`;
- `create kitchen production schedules`;
- `update kitchen production schedules`;
- `cancel kitchen production schedules`.

Hapus permission publish/sync Kitchen bila belum dipakai environment bersama. Bila sudah ada, boleh dibiarkan untuk compatibility tetapi jangan digunakan flow aktif dan laporkan cleanup lanjutan. Jangan mass-assign permission ke role tanpa mapping yang sudah disepakati.

## 8. Policy dan Branch scope

Policy/action wajib menggunakan permission dan mekanisme Branch existing:

- `view`: permission + accessible Branch;
- all-branch hanya bypass Branch filter, bukan feature permission;
- create: permission + Brand `Kitchen` + Branch aktif + `canAccessBranch()`;
- update: permission + Brand `Kitchen` + accessible Branch + status Scheduled;
- cancel: permission + Brand `Kitchen` + accessible Branch + status Scheduled;
- Cancelled tidak dapat diedit;
- direct URL, modal, dan Livewire action di-authorize ulang.

Jangan hardcode role atau daftar/ID Branch. Gunakan relasi `branches.brand_id` ke master Brand bernama `Kitchen`; jangan membutuhkan `is_kitchen` atau mencocokkan nama Branch.

## 9. Katalog dan query BOM Assembly

Reuse `RndBomCatalog` serta perilaku query/picker pada modul R&D.

Aturan server-side:

- re-query semua catalog IDs;
- `bom_type_id === 1`;
- ikuti aturan active/synced yang memang digunakan picker R&D;
- jangan menerima code/name/UOM/type/snapshot dari browser;
- BOM IDs harus unik;
- jangan memakai freshness age;
- jangan memanggil ESB;
- jangan membuat job sync Kitchen.

Picker harus:

- search server-side untuk kode/nama BOM dan product result;
- pagination 10/20 sesuai pola project;
- multi-select;
- mempertahankan selection lintas halaman;
- menandai item yang sudah ditambahkan;
- menampilkan code/name, product result, UOM, component count, dan status relevan;
- memberi empty state yang mengarahkan refresh melalui modul R&D existing.

## 10. Application Actions

### 10.1 `SaveProductionScheduleAction`

Gunakan satu Action untuk create/update jika konsisten dengan project; jika pemisahan create/update lebih sesuai sibling code, keduanya boleh terpisah tetapi harus berbagi validation/domain service yang sama.

Action wajib:

1. menerima actor secara eksplisit;
2. authorize create/update;
3. re-query dan validasi Branch;
4. memastikan status current Scheduled untuk update;
5. memvalidasi tanggal dan unique Branch/date;
6. me-requery seluruh BOM catalog;
7. memvalidasi type ID 1, uniqueness, dan eligibility yang sama dengan R&D;
8. memvalidasi minimal satu item;
9. memvalidasi quantity decimal > 0 dengan scale maksimal 4;
10. menormalisasi sequence server-side;
11. menjalankan header dan item replace/upsert dalam satu transaction;
12. menggunakan `lockForUpdate()` pada update;
13. menyimpan snapshot identitas dari database;
14. menangkap duplicate constraint menjadi validation error yang jelas;
15. mendeteksi stale edit melalui `updated_at`/version bila pola tersedia;
16. tidak memanggil ESB atau queue.

Jika semua item dihapus pada editor, tolak save dan arahkan pengguna menggunakan `Batalkan Jadwal`.

### 10.2 `CancelProductionScheduleAction`

Action wajib:

- authorize actor;
- re-query dengan row lock;
- hanya Scheduled yang dapat dibatalkan;
- alasan wajib, trim, maksimal 1.000 karakter;
- set status Cancelled, actor, waktu, dan alasan;
- tidak menghapus item;
- idempotent untuk request berulang;
- tidak membuat transition table bila tidak diperlukan.

## 11. Calendar query dan detail

### Calendar query

- visible range dimulai Senin yang mencakup awal bulan dan berakhir Minggu yang mencakup akhir bulan;
- Branch authorization diterapkan di SQL;
- filter Branch/status hanya mempersempit scope;
- select kolom header yang dibutuhkan;
- gunakan `withCount('items')` atau aggregate;
- jangan select/eager-load catalog `detail_snapshot`;
- eager-load Branch summary secukupnya;
- deterministic ordering;
- test query count/N+1.

### Detail/editor query

- authorize record;
- eager-load items dan actor secukupnya;
- tampilkan snapshot ringan;
- current catalog hanya opsional untuk editor Scheduled;
- Cancelled memakai snapshot dan read-only;
- tidak ada query di Blade.

## 12. UI Filament/Livewire

### Navigation

```text
Kitchen
└── Jadwal Produksi
```

- tampil hanya untuk user berizin;
- navigation group tetap expanded saat submenu aktif;
- gunakan pola sidebar existing dan regression test.

### Header/filter

- judul `Jadwal Produksi`;
- prev month, `Hari Ini`, next month;
- filter Branch accessible;
- filter status Semua/Scheduled/Cancelled;
- tombol `Buat Jadwal` bila authorized.

### Desktop

- grid Senin–Minggu;
- outside-month days diredupkan;
- today indicator;
- chip menampilkan Branch, status, dan `N BOM`;
- jangan menjumlahkan mixed UOM;
- klik tanggal membuat jadwal;
- klik chip membuka detail/editor.

### Mobile

- agenda/list per tanggal;
- tidak membuat horizontal scroll;
- parity data/action dengan desktop.

### State

- loading;
- empty calendar;
- no filter results;
- no accessible Branch;
- catalog empty;
- error + retry.

Hapus stale catalog warning dan pesan max-age.

## 13. Editor dan picker tanpa nested modal

Gunakan salah satu pola yang paling sesuai UI existing:

- satu modal dengan step `Jadwal` dan `Pilih BOM Assembly`; atau
- editor page/slide-over dan satu picker modal.

Jangan membuka picker modal di atas editor modal.

Editor Scheduled berisi:

- tanggal;
- Branch;
- notes;
- daftar BOM;
- planned quantity + read-only UOM;
- notes item;
- reorder;
- remove item;
- `Simpan` / `Simpan Perubahan`;
- `Batalkan Jadwal` dengan confirmation.

Tidak ada tombol Draft atau Publish.

Cancelled:

- read-only;
- tampilkan alasan, actor, dan waktu pembatalan;
- tidak menampilkan action edit.

## 14. Edge cases wajib

Implementasikan dan test:

- Branch inactive/inaccessible/forged;
- duplicate Branch/date;
- perubahan tanggal/Branch ke slot terisi;
- empty items;
- duplicate/forged BOM;
- BOM type selain 1 atau nama Assembly dengan type salah;
- inactive/unsynced bila picker R&D juga menolaknya;
- quantity null, zero, negatif, terlalu presisi, atau bukan angka;
- mixed UOM;
- dua user create slot yang sama;
- stale concurrent edit;
- cancel bersamaan/repeated;
- edit Cancelled;
- catalog rename/delete setelah jadwal disimpan;
- user kehilangan Branch access saat modal terbuka;
- direct Livewire call tanpa permission;
- katalog kosong tanpa membuat sync Kitchen.

## 15. Automated tests

Gunakan Pest 4, factories, dan database trait sesuai suite existing. Buat test melalui Artisan bila menambah file.

### Schema/model

- status default Scheduled;
- casts dan relationships;
- unique Branch/date;
- unique schedule/BOM dan schedule/sequence;
- decimal precision;
- nullable catalog FK dan snapshot survival;
- FK retention.

### Action/workflow

- create dengan multiple BOM;
- update header/items secara atomic;
- remove satu item;
- reject zero items;
- snapshot berasal dari database;
- duplicate constraint/race;
- stale edit;
- cancel menyimpan actor/reason/time dan items;
- repeated cancel aman;
- Cancelled immutable;
- rollback seluruh mutation ketika satu item gagal.

### Authorization

- accessible Branch;
- other Branch denied;
- all-branch tetap membutuhkan permission;
- forged Branch/catalog IDs;
- permission create/update/cancel;
- direct page/Livewire actions.

### Picker/calendar/UI

- hanya BOM Assembly ID 1;
- behavior active/synced konsisten dengan R&D;
- search, pagination, dan cross-page selection;
- no freshness config requirement;
- visible calendar range;
- filter Branch/status;
- desktop/mobile dataset parity;
- navigation active state;
- no N+1 dan no full detail JSON;
- loading/empty/error states bila dapat diuji.

### Regression

Jalankan tests existing yang terdampak untuk:

- `RndBomCatalog`;
- `SyncBomCatalogAction` dan job existing;
- R&D BOM page/picker;
- Branch access;
- sidebar/navigation.

Jangan menyesuaikan test R&D hanya agar cleanup Kitchen lolos jika behavior R&D seharusnya tetap sama.

## 16. Urutan implementasi

Kerjakan dalam urutan berikut:

1. Audit worktree dan deployment/migration state.
2. Tambah/perbarui characterization tests behavior sederhana.
3. Hapus dependency max-age/freshness dari Kitchen.
4. Sederhanakan enum/schema/model/factory ke Scheduled/Cancelled.
5. Audit dan hapus `is_kitchen` hanya jika aman dan khusus fitur ini.
6. Audit dan rollback selektif hardening sync R&D yang hanya dibutuhkan rancangan lama.
7. Sederhanakan permission dan Policy.
8. Implement/refactor Save dan Cancel Actions.
9. Refactor BOM picker query agar reuse R&D catalog tanpa freshness.
10. Refactor calendar/detail query.
11. Sederhanakan UI: Save/Edit/Cancel tanpa Draft/Publish.
12. Perbarui tests Kitchen dan jalankan regression R&D/Branch/sidebar.
13. Jalankan Pint, build, UI verification, dan final diff audit.

## 17. Verification commands

Sesuaikan nama file/filter aktual hasil audit. Jalankan secara bertahap, misalnya:

```bash
php artisan test --compact tests/Feature/KitchenProductionScheduleSchemaTest.php
php artisan test --compact tests/Feature/KitchenProductionBomEligibilityTest.php
php artisan test --compact tests/Feature/KitchenProductionScheduleWorkflowTest.php
php artisan test --compact tests/Feature/KitchenProductionSchedulePageTest.php
php artisan test --compact --filter=KitchenProductionSchedule
```

Lalu regression tests R&D BOM, Branch access, dan sidebar yang benar-benar terdampak.

Setelah PHP berubah:

```bash
vendor/bin/pint --dirty --format agent
```

Jalankan ulang focused tests setelah Pint.

Jika Blade/Tailwind/JavaScript berubah:

```bash
npm run build
```

Jangan memasukkan generated `public/build` secara membabi buta. Ikuti kebijakan repository dan audit `git status` setelah build.

Final checks:

```bash
git diff --check
git status --short
```

Gunakan `rg` untuk memastikan tidak ada active flow yang masih merujuk:

- `KITCHEN_BOM_CATALOG_MAX_AGE_HOURS`;
- Publish/Draft Kitchen;
- max-age/freshness blocker;
- `is_kitchen` khusus flow ini;
- sync permission/action khusus Kitchen;
- query database/HTTP dalam Blade.

## 18. UI verification

Gunakan URL lokal dari Laravel Boost `get-absolute-url`; aplikasi dilayani Laravel Herd, jadi jangan menjalankan server baru.

Verifikasi minimal:

- menu dan sidebar active state;
- calendar desktop;
- mobile agenda;
- dark mode bila panel mendukung;
- create dari tanggal;
- multiple BOM selection;
- pagination/search picker;
- quantity validation;
- edit schedule;
- cancel schedule;
- cancelled read-only;
- Branch filter dan cross-Branch denial;
- katalog kosong;
- loading/error state;
- tidak ada pesan max-age.

Periksa browser log terbaru setelah interaksi.

## 19. Larangan eksplisit

Jangan:

- mempertahankan workflow kompleks hanya karena sudah sempat dibuat;
- menghapus kode/migration/data tanpa reference dan deployment audit;
- mengedit migration yang sudah dideploy;
- hard-delete schedule persisted;
- memanggil ESB dari kalender, picker, save, atau cancel;
- membuat sync job khusus Kitchen;
- membutuhkan environment variable freshness;
- hardcode role, Branch ID, atau nama Branch;
- percaya snapshot/Branch/BOM/status dari browser;
- meloloskan BOM dari nama Assembly tanpa type ID 1;
- memakai float untuk quantity;
- menjumlahkan quantity berbeda UOM;
- query di Blade;
- memuat seluruh catalog atau detail JSON ke calendar/Livewire;
- membuat nested modal;
- menambah scope actual production, stock, shift, batch, export, notification, atau approval;
- menghapus test agar suite hijau;
- commit, push, deploy, atau migrate production tanpa instruksi terpisah.

## 20. Final review dan laporan

Sebelum menyatakan selesai:

1. Cocokkan hasil dengan seluruh acceptance criteria PRD.
2. Audit setiap file pada `git status --short`.
3. Pastikan tidak ada perubahan unrelated atau debug/demo file.
4. Jalankan `git diff --check`.
5. Catat command dan hasil test aktual.
6. Jalankan Pint dan build bila relevan.
7. Tegaskan migration mana yang dibuat/diubah dan apakah pernah dijalankan di environment bersama.
8. Tegaskan tidak ada data atau migration production yang dimutasi.
9. Tegaskan modul R&D BOM tetap berfungsi dan regression tests lulus.

Format laporan akhir:

1. **Outcome** — flow sederhana yang selesai.
2. **Simplifikasi** — komponen Draft/Publish/freshness/sync/is_kitchen yang dihapus atau dipertahankan karena compatibility.
3. **File penting** — model, migration, action, policy, query, page/view, tests.
4. **Schema/data safety** — unique constraint, snapshot ringan, cancellation retention, concurrency.
5. **Authorization** — permission dan Branch isolation.
6. **BOM source** — bukti reuse katalog R&D dan tidak ada ESB call dari Kitchen.
7. **Verification** — hasil tests, Pint, build, browser/UI checks.
8. **Migration/deployment notes** — langkah staging yang aman dan configuration yang diperlukan. Tidak boleh ada max-age config.
9. **Known limitations** — hanya keterbatasan nyata sesuai out-of-scope.
10. **Git status** — perubahan/untracked tersisa; jangan commit atau push.

Jika ada blocker deployment state yang tidak dapat dibuktikan, pilih perubahan forward-compatible, selesaikan bagian aman lainnya, lalu laporkan bukti dan input minimum yang dibutuhkan. Jangan menyelesaikan ketidakpastian dengan menghapus migration atau data secara spekulatif.
```
