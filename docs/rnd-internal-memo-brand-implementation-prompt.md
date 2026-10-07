# Prompt Implementasi — Memo Internal Berbasis Brand dengan Sumber Data BLSS

Salin seluruh prompt berikut ke agent implementasi dari root repository.

```text
Implementasikan perubahan modul R&D Memo Internal dari pilihan Branch Tujuan menjadi pilihan Brand berdasarkan PRD berikut:

- docs/rnd-internal-memo-brand-prd.md — sumber requirement utama perubahan ini.
- docs/code-remediation-prd.md — standar remediation dan kualitas teknis.
- docs/ui-consistency-prd.md — standar UI, responsive behavior, state, dark mode, dan accessibility.
- docs/rnd-internal-memo-simplification-prd.md — behavior Memo Internal existing yang tetap berlaku bila tidak bertentangan.
- docs/rnd-internal-memo-multi-branch-prd.md — referensi implementasi existing saja; ketentuan Branch/company scope di dokumen ini telah digantikan oleh PRD Brand.

Kerjakan implementasi sampai tuntas di repository ini, termasuk migration, model, action/service, policy, query katalog, queue job, halaman Filament/Livewire, Blade, PDF, factory, command backfill, serta automated test yang relevan. Jangan berhenti pada planning atau hanya memberi contoh kode.

## 1. Aturan kerja wajib

1. Baca `AGENTS.md` dan seluruh dokumentasi sumber di atas sebelum mengubah kode.
2. Gunakan skill Laravel project yang relevan dan ikuti konvensi repository existing.
3. Sebelum setiap perubahan kode, gunakan Laravel Boost `search-docs` dengan beberapa query broad dan package scope yang tepat untuk memastikan API Laravel 13, Filament 5, Livewire 4, dan Pest 4 yang digunakan benar.
4. Gunakan Laravel Boost untuk inspeksi schema/database bila tersedia, khususnya sebelum menulis migration.
5. Periksa sibling files dan implementasi existing; jangan memperkenalkan pola arsitektur kedua bila pola yang layak sudah tersedia.
6. Gunakan `php artisan make:* --no-interaction` untuk membuat migration, command, test, atau class Laravel baru.
7. Jangan menambah atau mengubah dependency tanpa persetujuan.
8. Jangan mengubah migration lama yang sudah mungkin berjalan di production. Buat migration baru yang additive dan reversible.
9. Jangan menghapus test existing. Perbarui test lama yang requirement-nya memang digantikan dan tambahkan test baru untuk behavior baru.
10. Jangan melakukan network request nyata dalam test; gunakan `Http::fake()` dan cegah stray request.
11. Pertahankan perubahan user yang sudah ada di worktree. Audit `git status` dan diff sebelum mulai, lalu edit hanya file yang relevan. Jangan reset, checkout, stash, atau menimpa perubahan unrelated.
12. Jangan commit, push, deploy, menjalankan migration production, atau melakukan cleanup destructive kecuali diminta terpisah.
13. Jangan membuat dokumentasi tambahan selain implementation report bila benar-benar diperlukan oleh pola repository atau diminta pada bagian hasil akhir.
14. Jangan bertanya untuk detail yang dapat ditentukan secara aman dari PRD, schema, test, atau pola existing. Jika ada keputusan yang benar-benar memengaruhi data production dan tidak dapat dibuktikan, hentikan hanya bagian yang berisiko tersebut, jelaskan buktinya, dan lanjutkan bagian aman lainnya.

## 2. Outcome yang harus dicapai

Ubah flow aktif Memo Internal menjadi:

- pengguna memilih tepat satu Brand pada create Memo;
- Brand dapat diedit dengan proses sederhana;
- Brand hanya menjadi metadata bisnis dan histori Memo;
- seluruh Menu, BOM, Assembly/WIP, Product, dan Purchase UOM baru tetap berasal dari Company Code `BLSS`;
- Brand tidak pernah menentukan company, Branch Code, credential, katalog, BOM, Product, policy, atau access scope;
- UI aktif tidak lagi menampilkan atau meminta Branch Tujuan;
- backend aktif tidak lagi membutuhkan `branch_ids`, `memoBranchIds`, atau relasi Branch untuk create, update Brand, authorization, picker, maupun Add Menu;
- data legacy tetap aman dan tidak dihapus pada rollout ini.

Jangan hanya mengganti label Branch menjadi Brand. Hilangkan ketergantungan domain Branch pada seluruh jalur aktif.

## 3. Keputusan domain yang tidak boleh diubah

1. Satu Memo mempunyai tepat satu Brand.
2. Brand diambil dari Master Brand existing.
3. Brand adalah metadata, bukan integration context.
4. Company Code seluruh write baru adalah `BLSS` dan ditetapkan server-side.
5. Client tidak dapat memilih atau mengganti Company Code.
6. Brand berbeda boleh memiliki Memo aktif pada periode dan revisi yang sama.
7. Brand yang sama tidak boleh memiliki lebih dari satu Memo aktif untuk kombinasi periode dan revisi yang sama.
8. Soft-deleted Memo tidak memblokir create baru sesuai pola generated-column/unique constraint existing.
9. Edit Brand hanya mengubah `brand_id` dan `brand_name_snapshot`.
10. Edit Brand tidak menghapus atau mengubah Menu, item, Minimum Order, attachment, BOM snapshot, dan tidak memicu sinkronisasi ESB.
11. Policy tidak menggunakan akses Branch dan tidak membuat sistem akses User–Brand baru.
12. PDF memakai Brand snapshot agar histori stabil.
13. Data legacy yang ambigu tidak boleh ditebak.
14. Tabel dan pivot Branch legacy tidak dihapus pada implementasi ini.
15. Data historis non-BLSS tidak boleh diam-diam dinormalisasi atau disinkronkan ulang sebagai BLSS.

## 4. Phase 0 — Audit dan characterization sebelum refactor

Lakukan audit read-only dan catat hasilnya dalam working notes sebelum mengubah behavior:

1. Petakan seluruh referensi berikut dengan `rg`:
   - `branch_ids` dan `branchIds`;
   - `memoBranchIds`;
   - `RndInternalMemoBranch`;
   - `rnd_internal_memo_branches`;
   - `rnd_internal_memo_menu_branches`;
   - `menuBranchFilter` dan `menuCompanyFilter`;
   - resolver mapping Branch;
   - policy atau query scope berbasis Branch;
   - job/payload katalog per Branch;
   - PDF, factory, seeder, dan tests terkait Memo Internal.
2. Inspeksi schema aktual untuk:
   - `rnd_internal_memos`;
   - tabel Menu/item Memo;
   - tabel katalog Menu;
   - tabel sync run/status;
   - `brands` dan `branches`;
   - dua tabel pivot Branch legacy.
3. Identifikasi unique index active Memo existing, termasuk generated column untuk soft delete.
4. Identifikasi bagaimana nomor Memo dan revision dibuat.
5. Identifikasi contract Master Menu, BOM, Product, dan credential BLSS dari service/config/test existing.
6. Tambahkan atau pertahankan characterization test untuk behavior yang tidak boleh rusak: Minimum Order, revision, delete/restore, PDF, permission, Add/Remove Menu, dan payload integrasi.
7. Jalankan test Memo Internal existing yang paling relevan sebelum refactor dan catat baseline failure yang memang sudah ada, bila ada.

### Verifikasi technical Branch Code

Master Menu existing mungkin masih membutuhkan parameter `branchCode`, sedangkan `BLSS` adalah Company Code dan bukan Branch Code.

- Jangan hardcode sebuah Branch Code hanya berdasarkan asumsi.
- Periksa konfigurasi, fixture, contract test, implementation report, dan client existing.
- Jika repository sudah memiliki technical Branch Code canonical yang terbukti untuk katalog BLSS, reuse melalui config.
- Jika belum ada, tambahkan config seperti `services.esb.internal_memo_catalog_branch_code` yang membaca environment `RND_INTERNAL_MEMO_CATALOG_BRANCH_CODE`.
- Panggil `env()` hanya dari file config.
- Application harus gagal secara terkontrol bila konfigurasi wajib kosong; jangan fallback ke Brand atau Branch user.
- Test harus membuktikan request menggunakan Company Code BLSS dan technical Branch Code dari config.
- Jangan menampilkan technical Branch Code pada UI.

Jika nilai production-nya tidak dapat diketahui dari repository, implementasikan config, validasi, test, dan contoh `.env.example` tanpa mengarang nilainya. Nyatakan nilai deployment tersebut sebagai satu-satunya blocker konfigurasi di laporan akhir; jangan menghentikan implementasi bagian lain.

## 5. Implementasi schema additive

Buat migration baru sesuai konvensi repository.

### `rnd_internal_memos`

Tambahkan:

- `brand_id` nullable foreign key ke `brands`, indexed, menggunakan `nullOnDelete`;
- `brand_name_snapshot` nullable string dengan panjang yang konsisten terhadap `brands.name` atau batas aman project.

Alasan nullable di database adalah compatibility dengan data legacy. Untuk create/edit baru, Brand tetap required di application.

### Unique active Memo

Ganti logical uniqueness menjadi:

`brand_id + period_month_if_active + revision`

Ketentuan:

- pertahankan semantics soft delete existing;
- audit collision sebelum mengganti unique index;
- gunakan nama index eksplisit dan aman untuk batas identifier database;
- jangan mencampur backfill DML ke migration DDL;
- buat `down()` yang realistis, tetapi jangan memasang ulang unique index lama bila data baru dapat bertabrakan menurut rule lama tanpa guard yang aman;
- jika rollback constraint lama secara otomatis tidak aman, pecah migration dan dokumentasikan alasan forward-fix pada kode/migration secukupnya sesuai konvensi.

### Legacy schema

Jangan drop atau truncate:

- `rnd_internal_memo_branches`;
- `rnd_internal_memo_menu_branches`.

Flow baru tidak boleh menulis data baru ke keduanya.

### Status sinkronisasi global

Audit dahulu apakah tabel/status sync existing dapat digunakan untuk satu context BLSS. Reuse bila aman. Bila tidak, buat state sync global minimal dengan identitas context, status, last success timestamp, last error aman, dan triggered by. Jangan membuat state per Brand.

## 6. Model dan factory

Perbarui model secara konsisten:

- tambahkan relation `brand(): BelongsTo` dengan return type;
- tambahkan field fillable/cast/attributes yang dibutuhkan sesuai pola model existing;
- tetap gunakan constant `RndInternalMemo::COMPANY_CODE` atau satu domain source setara untuk `BLSS`;
- jangan menyebarkan literal `BLSS` jika constant existing dapat digunakan;
- update factory agar record baru default ke Brand valid dan Company Code BLSS;
- tambahkan Brand factory hanya bila belum tersedia dan dibutuhkan test, mengikuti perintah Artisan dan konvensi project;
- pertahankan relation legacy hanya sejauh dibutuhkan compatibility/backfill, dan beri nama/penggunaan yang tidak membuatnya kembali menjadi source of truth.

## 7. Command backfill legacy

Buat command Laravel yang aman, idempotent, dan default-nya dry-run.

Behavior wajib:

1. Proses Memo dengan `chunkById()` atau mekanisme aman setara.
2. Untuk setiap Memo, ambil Brand non-null dari seluruh Branch legacy terkait.
3. Jika tepat satu Brand unik ditemukan, kandidat dapat diisi.
4. Jika beberapa Branch semuanya mempunyai Brand sama, hasilnya tetap satu Brand.
5. Jika tidak ada Brand, tandai `unresolved_no_brand`.
6. Jika lebih dari satu Brand unik, tandai `unresolved_multiple_brands`.
7. Jangan memilih Brand pertama, mayoritas, atau ID terkecil.
8. Jika Memo sudah memiliki Brand, jangan merusaknya; isi snapshot yang kosong secara aman bila relation valid.
9. Jangan mengubah data historis non-BLSS.
10. Mode dry-run tidak mengubah database.
11. Mode apply harus memakai flag eksplisit.
12. Sediakan summary:
    - `scanned`;
    - `resolved`;
    - `already_resolved`;
    - `unresolved_no_brand`;
    - `unresolved_multiple_brands`;
    - `failed`.
13. Berikan exit code non-zero pada fatal error.
14. Jangan mencetak credential atau payload sensitif.
15. Jangan menjalankan command apply terhadap database user secara otomatis; hanya implementasikan dan test command-nya.

Gunakan transaction dengan lingkup yang wajar per chunk/record sesuai pola project. Jangan menahan transaction besar selama seluruh tabel diproses.

## 8. Refactor create dan update Memo

### Create

Refactor `CreateInternalMemoAction` dan caller-nya agar:

- menerima `brand_id`, bukan `branch_ids`;
- memvalidasi Brand yang sah;
- melakukan authorization di boundary yang konsisten dengan project;
- menetapkan `company_code = RndInternalMemo::COMPANY_CODE` server-side;
- menyimpan `brand_name_snapshot` dari Master Brand dalam transaction yang sama;
- tidak memanggil resolver mapping Branch;
- tidak menulis relasi Memo–Branch;
- tidak mendispatch sync job per Branch;
- menangani duplicate constraint menjadi validation error yang jelas.

### Edit Brand

Buat atau refactor action kecil dengan tanggung jawab tunggal:

- authorize update;
- validate `brand_id`;
- load Brand;
- update `brand_id` dan `brand_name_snapshot`;
- tidak mengubah relation/data lain;
- tidak memicu sync atau request ESB;
- catat audit melalui mekanisme existing bila memang sudah tersedia; jangan menciptakan framework audit baru.

Hentikan pemakaian `UpdateInternalMemoBranchesAction` dari flow aktif. Jangan menghapus class sebelum memastikan tidak ada caller lain dan cleanup memang aman; leaving an unused legacy class temporarily is acceptable in compatibility release, tetapi tandai melalui struktur/test dan laporan akhir.

### Revision

Saat membuat revisi:

- copy `brand_id` dan `brand_name_snapshot`;
- jangan membuat relasi Branch baru;
- pastikan record Menu baru hasil revisi memakai BLSS sesuai rule target;
- pertahankan item, Minimum Order, dan behavior revision existing.

## 9. Authorization dan policy

Refactor policy/query scope Memo Internal agar:

- view/create/update/delete/export/add Menu/remove Menu/sync mengikuti permission Memo existing;
- tidak memanggil `canAccessBranch()`;
- tidak mensyaratkan user mengakses salah satu Branch Memo;
- tidak memperkenalkan akses User–Brand;
- tidak membocorkan data melalui route/action yang hanya disembunyikan pada UI;
- seluruh mutation tetap authorize server-side.

Tambahkan test positif dan negatif untuk permission.

## 10. Katalog Menu global BLSS

Refactor query/service/job katalog existing dengan prinsip berikut:

- satu katalog sumber BLSS digunakan seluruh Memo;
- tidak ada katalog per Brand;
- query tidak membangun context dari Memo Branch;
- query tidak memfilter berdasarkan Brand;
- `InternalMemoMenuCatalogQuery` atau penggantinya menggunakan fixed BLSS context;
- picker tetap mendukung search, sort, pagination, dan exclude Menu yang sudah dipilih;
- hindari N+1 dan jangan query di Blade;
- normal render index/detail/picker membaca snapshot lokal, bukan menarik seluruh data ESB.

### Job sync

- gunakan queue untuk proses berat;
- gunakan Company Code BLSS dan technical Branch Code dari config;
- idempotent dan aman di-retry;
- gunakan unique job/lock mengikuti pola project agar context sama tidak berjalan bersamaan;
- set timeout, retry/backoff, dan `failed()` sesuai standar queue existing;
- `retry_after` harus lebih panjang daripada timeout job;
- gunakan upsert/chunking sesuai volume;
- kegagalan tidak menghapus last-known-good snapshot;
- status sync tidak disimpan per Brand;
- log hanya context aman: company, technical branch, status, count, duration, correlation ID; jangan log token.

Jika perubahan job berada dalam transaction, dispatch setelah commit sesuai pola Laravel dan project.

## 11. Refactor Add Menu, BOM, dan Product

Refactor `AddMenuToInternalMemoAction` beserta caller/service terkait agar:

- tidak menerima atau membutuhkan `memoBranchIds`;
- tidak membaca company yang dapat dimanipulasi dari state picker;
- menetapkan Company Code BLSS server-side;
- memvalidasi Menu berasal dari katalog BLSS yang valid;
- tidak membuat pivot Menu–Branch;
- menjaga unique/idempotent behavior existing;
- BOM Menu, Assembly/WIP, Product, dan Purchase UOM menggunakan context/credential BLSS;
- Brand tidak pernah masuk request integrasi;
- remove Menu tidak melakukan reconciliation Branch;
- Minimum Order tetap bekerja sama seperti sebelumnya.

Pastikan data historis non-BLSS tetap dapat dibaca dan tidak di-refresh otomatis sebagai BLSS.

## 12. UI Filament/Livewire

Ikuti `docs/ui-consistency-prd.md` dan reuse komponen/pola existing.

### Create Memo

- hapus multi-select `Branch Tujuan`;
- tambahkan single select `Brand`;
- options dari Master Brand lokal, urut alfabetis;
- required, searchable, dan preload bila sesuai volume/pola existing;
- jangan tampilkan Company Code atau Branch Code;
- tampilkan validation error pada field;
- cegah double-submit dengan loading/disabled state.

### Edit Memo

- sediakan edit Brand yang sederhana pada flow/modal existing;
- prefill Brand saat ini;
- error tidak menutup modal atau menghapus input;
- success notification spesifik;
- jangan tampilkan progress sync setelah Brand berubah karena tidak ada sync.

### Index

- tampilkan Brand sebagai metadata utama;
- hapus daftar/badge Branch;
- gunakan Brand snapshot untuk histori, dengan fallback aman hanya untuk data transisi;
- tambahkan filter Brand bila pola index mendukung filter;
- pencarian dapat menemukan nama Brand snapshot;
- Memo unresolved menampilkan badge `Brand belum ditentukan`;
- hapus filter company/Branch.

### Detail

- tampilkan Brand snapshot pada ringkasan;
- hapus kartu, badge, daftar, tombol, dan state terkait Branch;
- hapus state seperti `branchIds`, `menuBranchFilter`, dan `menuCompanyFilter` jika tidak digunakan lagi;
- tampilkan copy ringkas `Sumber data: ESB BLSS` di area relevan;
- jangan menampilkan technical Branch Code atau credential.

### Picker Menu

- hapus filter Branch dan company;
- hapus informasi availability per Branch;
- tampilkan kode, nama, kategori, status BOM, dan action yang relevan;
- tandai/disable Menu yang sudah dipilih;
- pertahankan search, pagination, loading, retry, empty result, dan error state;
- jangan fetch semua data eksternal saat render.

### UI quality

- pastikan mobile tidak horizontal scroll pada form/modal utama;
- dark mode tetap benar;
- modal memiliki title, focus management, Escape behavior, dan return focus;
- icon-only action memiliki accessible name;
- loading/error/validation tidak hanya mengandalkan warna;
- pertahankan keyboard navigation dan focus ring;
- jangan menaruh JS/CSS baru langsung di Blade jika project sudah mempunyai lokasi/component yang tepat.

## 13. PDF/export

Perbarui template PDF/export Memo Internal agar:

- menampilkan `brand_name_snapshot` sebagai sumber utama;
- dapat fallback secara aman pada relation live hanya untuk record transisi bila snapshot kosong;
- tidak menampilkan Branch Tujuan;
- tetap dapat dibuat ketika Brand master telah dihapus tetapi snapshot masih ada;
- layout existing selain perubahan identitas Brand tidak rusak.

## 14. Data legacy dan compatibility

Pada implementasi ini:

- pertahankan tabel dan model legacy yang masih dibutuhkan backfill/read compatibility;
- hentikan seluruh write baru ke tabel Branch legacy dari flow aktif;
- jangan menghapus data lama;
- jangan mengubah Memo legacy ambigu secara otomatis;
- Memo unresolved tetap dapat dibaca;
- sebelum mutation yang secara bisnis membutuhkan Brand, minta user memilih Brand valid;
- tambahkan telemetry/log yang cukup untuk mengetahui apakah fallback legacy masih dipakai;
- jangan mengimplementasikan Phase 7 cleanup destructive dari PRD.

## 15. Automated test wajib

Gunakan Pest dan factory. Jalankan test minimum yang relevan secara bertahap.

### Migration/model

- Brand relation tersedia.
- `nullOnDelete` menjaga snapshot.
- snapshot stabil setelah Master Brand rename/delete.
- Brand berbeda dapat memiliki Memo pada periode/revisi sama.
- Brand sama tidak dapat duplikat aktif.
- soft-deleted Memo tidak memblokir create baru.

### Create/update/revision

- Brand wajib.
- Brand dan snapshot tersimpan.
- Company dipaksa BLSS.
- payload company non-BLSS tidak dapat mengubah hasil.
- tidak ada Memo Branch baru.
- edit Brand metadata-only.
- edit Brand tidak dispatch sync.
- revision copy Brand dan tidak membuat Branch relation.
- duplicate constraint menjadi validation error yang jelas.

### Backfill command

- satu Brand deterministik;
- banyak Branch dengan Brand sama;
- tidak ada Brand;
- lebih dari satu Brand;
- dry-run tidak menulis;
- apply menulis dengan benar;
- idempotent ketika diulang;
- record existing tidak rusak;
- non-BLSS historis tidak dinormalisasi.

### Catalog/job/HTTP contract

- Company Code BLSS.
- technical Branch Code berasal dari config.
- Brand tidak masuk request.
- missing config gagal terkontrol.
- timeout/retry/failure behavior sesuai kontrak.
- upsert/retry tidak duplikat.
- failure mempertahankan snapshot lama.
- lock mencegah job context sama bersamaan.
- `Http::preventStrayRequests()` bila sesuai setup test project.

### Add Menu/BOM/Product

- tidak perlu Branch atau `memoBranchIds`;
- Menu tersimpan BLSS;
- tidak ada pivot Branch baru;
- duplicate Menu aman;
- BOM/Product memakai BLSS;
- edit Brand tidak mengubah Menu/item.

### Policy/UI/PDF

- unauthorized user ditolak di server.
- user dengan permission tidak ditolak karena Branch.
- create/edit menampilkan Brand, bukan Branch.
- index/detail menampilkan Brand snapshot.
- picker tidak menampilkan filter/info Branch/company.
- unresolved/loading/empty/error state tersedia.
- PDF menampilkan Brand snapshot dan tidak menampilkan Branch.
- PDF tetap berhasil setelah Brand master dihapus.

### Regression

- Minimum Order;
- revision;
- delete/restore bila tersedia;
- attachment;
- add/remove Menu;
- PDF;
- navigation/sidebar;
- test Memo Internal existing lain yang masih relevan.

Jangan membuat verification script ad-hoc bila test Pest dapat membuktikan behavior.

## 16. Urutan eksekusi yang diharapkan

Kerjakan dalam urutan berikut agar perubahan mudah diverifikasi:

1. Audit worktree, code, schema, docs, dan test baseline.
2. Search docs versi package yang relevan.
3. Tambah characterization test bila coverage behavior penting belum ada.
4. Buat additive schema dan model/factory test.
5. Buat dan test command backfill, tetapi jangan menjalankan mode apply pada data user.
6. Refactor domain create/update/revision serta policy.
7. Refactor katalog global BLSS, job, dan sync state.
8. Refactor Add Menu/BOM/Product.
9. Refactor UI index/create/detail/edit/picker.
10. Perbarui PDF/export.
11. Perbarui test legacy yang sudah tidak sesuai requirement baru.
12. Jalankan affected tests setelah setiap kelompok perubahan.
13. Jalankan seluruh test Memo Internal dan test integrasi terkait.
14. Jalankan formatter dan build frontend bila relevan.
15. Audit diff, query/performance concern, security, dan sisa referensi Branch pada flow aktif.

## 17. Perintah verifikasi minimum

Sesuaikan nama file/filter dengan hasil implementasi, tetapi minimal lakukan:

- `php artisan test --compact` untuk file/filter Memo Internal yang berubah;
- seluruh test Memo Internal terkait setelah test fokus lulus;
- test policy, job, HTTP contract, PDF, dan backfill command;
- `vendor/bin/pint --dirty --format agent` setelah ada perubahan PHP;
- `npm run build` bila Blade/CSS/JS atau asset frontend berubah dan build dibutuhkan;
- `git diff --check`;
- `git status --short`;
- pencarian akhir dengan `rg` untuk memastikan `branch_ids`, `memoBranchIds`, filter Branch/company, dan write pivot legacy tidak tersisa pada flow aktif.

Jangan menjalankan seluruh suite sejak awal bila test terfokus cukup untuk iterasi, tetapi sebelum selesai jalankan scope regression yang proporsional terhadap luas perubahan.

## 18. Acceptance checklist implementasi

Jangan nyatakan selesai sebelum seluruh item berikut terbukti:

- [ ] Create Memo hanya meminta satu Brand.
- [ ] Edit Brand tersedia dan metadata-only.
- [ ] Semua write baru memakai BLSS server-side.
- [ ] Brand tidak memengaruhi integrasi.
- [ ] Flow aktif tidak menulis Memo–Branch atau Menu–Branch.
- [ ] Policy tidak bergantung pada akses Branch.
- [ ] Picker tidak memiliki filter/info Branch/company.
- [ ] Unique active Memo berlaku per Brand–periode–revisi.
- [ ] Snapshot Brand dipakai pada index/detail/PDF.
- [ ] Legacy deterministik dapat di-backfill.
- [ ] Legacy ambigu tidak ditebak.
- [ ] Data non-BLSS historis tidak dinormalisasi diam-diam.
- [ ] Sync global BLSS asynchronous, idempotent, locked, dan observable.
- [ ] Snapshot katalog terakhir bertahan saat sync gagal.
- [ ] Tabel legacy belum dihapus.
- [ ] Test relevan lulus.
- [ ] Pint lulus setelah memperbaiki formatting.
- [ ] Frontend build lulus bila relevan.
- [ ] Tidak ada perubahan unrelated yang tertimpa.

## 19. Batasan deployment

Implementasi kode dan test tidak sama dengan izin deployment.

- Jangan menjalankan migration pada production.
- Jangan menjalankan backfill apply pada database user/production.
- Jangan mengisi nilai production technical Branch Code dengan tebakan.
- Jangan drop tabel legacy.
- Jangan push ke remote.
- Jangan membuat commit kecuali diminta.

## 20. Format laporan akhir

Setelah selesai, berikan laporan ringkas tetapi evidence-based dengan struktur:

1. Outcome implementasi.
2. File/komponen utama yang berubah.
3. Keputusan teknis penting, termasuk bagaimana fixed BLSS dan technical Branch Code diterapkan.
4. Strategi migrasi/backfill dan apa yang sengaja belum dijalankan.
5. Test, formatter, dan build yang dijalankan beserta hasilnya.
6. Sisa risiko atau blocker nyata, terutama nilai config deployment yang belum diketahui.
7. Konfirmasi bahwa tidak ada cleanup destructive, commit, push, atau deployment yang dilakukan.

Jika menemukan konflik antara kode existing dan PRD, ikuti prioritas:

1. `docs/rnd-internal-memo-brand-prd.md` untuk requirement Brand dan BLSS.
2. `docs/code-remediation-prd.md` untuk kualitas teknis dan remediation.
3. `docs/ui-consistency-prd.md` untuk UI/UX.
4. Behavior existing yang telah dikarakterisasi untuk bagian yang tidak diubah oleh PRD.

Target akhir bukan sekadar UI yang terlihat berubah, melainkan domain Memo Internal yang benar-benar tidak lagi bergantung pada Branch pada flow aktif, dengan data legacy tetap aman dan seluruh integrasi baru konsisten memakai BLSS.
```
