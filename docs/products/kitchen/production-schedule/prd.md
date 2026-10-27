# PRD — Kitchen: Jadwal Produksi Sederhana

## 1. Identitas dokumen

| Atribut | Nilai |
| --- | --- |
| Modul | Kitchen |
| Fitur | Jadwal Produksi |
| Status | Revisi requirement sederhana — sumber requirement terbaru |
| Prioritas | P1 |
| Target pengguna | Tim Kitchen dan pengguna berizin |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |
| Sumber BOM | Katalog BOM Assembly lokal yang sama dengan modul R&D |

Dokumen ini menggantikan rancangan Jadwal Produksi sebelumnya. Jika implementasi atau prompt lama bertentangan dengan dokumen ini, requirement dalam dokumen ini yang berlaku.

## 2. Ringkasan kebutuhan

Fitur yang dibutuhkan adalah kalender produksi sederhana:

1. pengguna membuka `Kitchen → Jadwal Produksi`;
2. halaman menampilkan kalender bulanan;
3. pengguna menekan sebuah tanggal;
4. pengguna memilih satu atau beberapa BOM Assembly yang akan diproduksi;
5. pengguna mengisi jumlah produksi masing-masing BOM;
6. pengguna menyimpan jadwal;
7. jadwal langsung tampil pada tanggal tersebut dan dapat dilihat, diedit, atau dibatalkan.

Daftar BOM menggunakan katalog BOM Assembly lokal yang sudah dipakai modul R&D. Modul Kitchen tidak mempunyai approval, publish, batas usia katalog, atau sinkronisasi BOM sendiri.

## 3. Masalah yang diselesaikan

Tim Kitchen memerlukan jawaban sederhana untuk pertanyaan:

> Pada tanggal ini, Kitchen akan memproduksi BOM Assembly apa saja dan berapa jumlahnya?

Tanpa fitur ini, rencana produksi harus dicatat di luar aplikasi atau sulit dilihat dalam konteks kalender.

## 4. Tujuan

1. Menyediakan kalender produksi yang mudah dibaca.
2. Memungkinkan beberapa BOM Assembly dijadwalkan pada satu tanggal.
3. Menggunakan sumber BOM yang sama dengan modul R&D agar tidak ada master data ganda.
4. Memungkinkan jadwal langsung disimpan dan diperbarui tanpa approval tambahan.
5. Membatasi data pada Branch ber-Brand `Kitchen` yang dapat diakses pengguna.
6. Menjaga jadwal lama tetap terbaca apabila katalog BOM berubah.
7. Menjaga implementasi ringan dan mudah dipelihara.

## 5. Prinsip kesederhanaan

Versi ini tidak menggunakan:

- status Draft dan Published;
- tombol Publish;
- approval atau rejection;
- `KITCHEN_BOM_CATALOG_MAX_AGE_HOURS`;
- pemeriksaan usia/freshness katalog;
- pemblokiran penyimpanan karena katalog belum disinkronkan hari ini;
- sync-run khusus Kitchen;
- queue atau scheduler khusus Jadwal Produksi;
- status In Progress atau Completed;
- actual quantity, variance, yield, waste, atau costing;
- inventory reservation atau pengurangan stok;
- shift, batch run, atau recurring schedule;
- mutation BOM ke ESB.

Jika mekanisme tersebut sudah terlanjur dibuat khusus untuk fitur Kitchen dan belum pernah dideploy, mekanisme tersebut harus dihapus dari implementasi sebelum merge. Jika sudah pernah dideploy, lakukan penyederhanaan dengan migration/perubahan compatibility yang aman tanpa menghapus data.

## 6. Scope MVP

### 6.1 Termasuk

- Navigation group `Kitchen`.
- Submenu `Jadwal Produksi`.
- Kalender bulanan.
- Navigasi bulan sebelumnya, hari ini, dan bulan berikutnya.
- Filter Branch.
- Klik tanggal untuk membuat jadwal.
- Pemilihan multiple BOM Assembly.
- Quantity per BOM.
- UOM hasil BOM sebagai informasi read-only.
- Catatan jadwal opsional.
- Catatan item opsional.
- Urutan item produksi.
- Simpan, lihat detail, edit, dan batalkan jadwal.
- Desktop calendar dan mobile agenda.
- Permission, Policy, validasi, dan automated tests.

### 6.2 Tidak termasuk

- Approval/publish workflow.
- Integrasi stok dan pembelian.
- Perhitungan kebutuhan komponen BOM.
- Actual production.
- Multiple shift atau production run terpisah pada Branch dan tanggal yang sama.
- Drag-and-drop kalender.
- Notifikasi.
- Export Excel/PDF.
- Integrasi kalender eksternal.
- Pembuatan atau perubahan BOM.
- Katalog BOM per-company.
- Flag atau setting `is_kitchen`; klasifikasi memakai relasi Brand yang sudah ada.

## 7. Keputusan produk

1. Satu jadwal mewakili satu Branch pada satu tanggal.
2. Satu Branch hanya mempunyai satu jadwal per tanggal.
3. Satu jadwal dapat memuat banyak BOM Assembly.
4. BOM yang sama hanya muncul satu kali dalam jadwal yang sama.
5. Quantity berarti jumlah output yang direncanakan dalam UOM hasil BOM.
6. Jadwal langsung berstatus `scheduled` setelah disimpan; tidak ada tahap Draft/Publish.
7. Jadwal `scheduled` dapat diedit oleh pengguna berizin.
8. Jadwal persisted tidak di-hard-delete melalui UI. Tombol `Batalkan Jadwal` mengubah status menjadi `cancelled` dan mempertahankan item serta snapshot.
9. Item BOM dapat dihapus dari editor sebelum menyimpan perubahan.
10. Tidak ada sinkronisasi BOM yang dijalankan saat membuka kalender atau menyimpan jadwal.
11. Kalender menggunakan data BOM terakhir yang tersedia pada katalog R&D.
12. Untuk pengguna terbatas, Branch yang dapat dipilih harus aktif, dapat diakses, dan terhubung ke Brand bernama `Kitchen`. Pengguna dengan akses seluruh Branch dapat memilih seluruh Branch aktif; tidak diperlukan field `is_kitchen`.

## 8. Status sederhana

| Status internal | Label UI | Perilaku |
| --- | --- | --- |
| `scheduled` | Scheduled | Aktif, tampil pada kalender, dan dapat diedit |
| `cancelled` | Cancelled | Read-only, tetap dapat dilihat melalui filter |

Transisi hanya:

```text
Scheduled → Cancelled
```

Tidak ada Draft, Published, In Progress, Completed, Reopened, atau Approval.

Pembatalan menggunakan konfirmasi. Alasan pembatalan dapat diwajibkan untuk menjaga jejak operasional, maksimal 1.000 karakter. Pembatalan berulang harus aman dan tidak menghasilkan side effect ganda.

## 9. Sumber BOM Assembly

### 9.1 Sumber tunggal

Gunakan `RndBomCatalog` dan mekanisme pencarian/pagination yang sama seperti modul R&D BOM. Modul Kitchen tidak membuat master BOM baru dan tidak menyalin seluruh katalog ke tabel lain.

### 9.2 Data picker

Picker menampilkan:

- kode dan nama BOM;
- kode dan nama product result;
- UOM hasil;
- jumlah komponen bila tersedia;
- status aktif/sinkronisasi sesuai tampilan modul R&D.

### 9.3 Aturan pemilihan

- Query dasar mengikuti katalog BOM Assembly modul R&D.
- BOM yang dapat dipilih harus `bom_type_id = 1`.
- Ikuti perilaku modul R&D untuk active/synced catalog; jangan membuat aturan kedua khusus Kitchen.
- Jangan menambahkan syarat freshness, max age, sync-run, atau konfigurasi `.env` khusus Kitchen.
- Jangan memanggil ESB pada render kalender, pencarian picker, atau penyimpanan jadwal.
- ID BOM dari browser wajib di-query ulang dari database.
- Nama `Assembly` saja tidak boleh meloloskan type ID lain.
- BOM yang sudah dipilih tidak dapat ditambahkan dua kali.

Jika katalog kosong, tampilkan pesan untuk melakukan refresh melalui mekanisme R&D existing. Jangan membuat tombol atau job sync baru khusus Kitchen.

### 9.4 Snapshot ringan

Simpan snapshot identitas BOM setiap kali jadwal disimpan:

- `esb_bom_id`;
- `bom_code`;
- `bom_name`;
- `bom_type_id`;
- `product_detail_id`;
- `product_code`;
- `product_name`;
- `uom_name`.

Tidak perlu menyimpan seluruh `detail_snapshot` resep atau versi BOM untuk fitur sederhana ini. Jadwal lama membaca snapshot ringan jika katalog berubah atau tidak ditemukan.

## 10. Branch dan hak akses

Branch menentukan lokasi produksi dan scope akses jadwal.

Aturan:

1. Pengguna terbatas hanya melihat jadwal dari Branch ber-Brand `Kitchen` yang dapat diakses. Pengguna dengan akses seluruh Branch dapat melihat seluruh jadwal.
2. Pengguna dengan akses semua Branch tetap membutuhkan permission fitur.
3. Branch ID dari browser divalidasi ulang menggunakan mekanisme canonical seperti `canAccessBranch()`.
4. Filter Branch hanya mempersempit scope dan tidak menggantikan Policy server-side.
5. Tidak ada hardcoded role.
6. Penentuan Kitchen memakai relasi `branches.brand_id` ke Brand bernama `Kitchen`, bukan pencocokan nama Branch.

Permission minimum:

- `view kitchen production schedules`;
- `view all branch kitchen production schedules`;
- `create kitchen production schedules`;
- `update kitchen production schedules`;
- `cancel kitchen production schedules`.

Tidak diperlukan permission publish atau sync khusus Kitchen.

## 11. Struktur data

### 11.1 `kitchen_production_schedules`

| Kolom | Aturan |
| --- | --- |
| `id` | Primary key |
| `branch_id` | Branch pelaksana |
| `production_date` | Tanggal produksi |
| `status` | `scheduled` atau `cancelled`; default `scheduled` |
| `notes` | Nullable, maksimal 2.000 karakter |
| `created_by` | User pembuat |
| `updated_by` | Nullable, user terakhir mengubah |
| `cancelled_by` | Nullable |
| `cancelled_at` | Nullable datetime |
| `cancellation_reason` | Nullable; wajib ketika dibatalkan |
| timestamps | Wajib |

Constraint dan index:

- unique `(branch_id, production_date)`;
- index `(branch_id, production_date)`;
- index `(branch_id, status, production_date)`;
- foreign key tidak boleh menyebabkan jadwal terhapus ketika User atau Branch berubah.

### 11.2 `kitchen_production_schedule_items`

| Kolom | Aturan |
| --- | --- |
| `id` | Primary key |
| `kitchen_production_schedule_id` | Foreign key header |
| `rnd_bom_catalog_id` | Nullable reference katalog |
| `esb_bom_id` | Identifier BOM |
| `bom_code` | Snapshot ringan |
| `bom_name` | Snapshot ringan |
| `bom_type_id` | Snapshot, harus `1` ketika disimpan |
| `product_detail_id` | Snapshot result product |
| `product_code` | Snapshot ringan |
| `product_name` | Snapshot ringan |
| `uom_name` | Snapshot ringan |
| `planned_quantity` | Decimal `(18,4)`, lebih dari nol |
| `sequence` | Urutan tampil |
| `notes` | Nullable, maksimal 1.000 karakter |
| timestamps | Wajib |

Constraint:

- unique `(kitchen_production_schedule_id, esb_bom_id)`;
- unique `(kitchen_production_schedule_id, sequence)`;
- index pada seluruh foreign key dan `esb_bom_id`;
- `rnd_bom_catalog_id` nullable agar histori tidak rusak ketika katalog hilang.

### 11.3 Data yang tidak diperlukan

Versi sederhana tidak membutuhkan:

- `kitchen_production_schedule_transitions`;
- actor/timestamp publish;
- full BOM detail snapshot;
- BOM version/freshness snapshot;
- tabel sync-run yang dibuat khusus sebagai prasyarat Kitchen.

Audit referensi sebelum menghapus komponen. Jika sudah pernah dideploy atau digunakan modul R&D, pertahankan compatibility dan jangan mengubah migration lama.

## 12. Alur pengguna

### 12.1 Membuat jadwal

1. Buka kalender.
2. Pilih/filter Branch bila diperlukan.
3. Tekan tanggal.
4. Form otomatis mengisi tanggal tersebut.
5. Tekan `Tambah BOM Assembly`.
6. Pilih satu atau beberapa BOM dari katalog R&D.
7. Isi quantity setiap BOM.
8. Tambahkan catatan/urutan bila perlu.
9. Tekan `Simpan`.
10. Jadwal langsung tampil pada kalender.

Tidak ada tombol `Simpan Draft` atau `Publish`.

### 12.2 Melihat dan mengedit

Klik jadwal untuk melihat tanggal, Branch, pembuat, daftar BOM, quantity, UOM, dan catatan.

Pengguna berizin dapat mengubah tanggal, Branch, BOM, quantity, sequence, dan catatan selama status masih `scheduled`. Perubahan ditolak jika Branch/tanggal tujuan sudah mempunyai jadwal.

### 12.3 Membatalkan

- Klik `Batalkan Jadwal`.
- Tampilkan dialog konfirmasi dan input alasan.
- Simpan actor, waktu, dan alasan.
- Jadwal menjadi read-only.
- Item dan snapshot tidak dihapus.
- Jadwal cancelled dapat dilihat melalui filter.

## 13. UI kalender

### 13.1 Desktop

- Grid tujuh kolom, Senin sampai Minggu.
- Navigasi bulan sebelumnya, `Hari Ini`, dan bulan berikutnya.
- Hari di luar bulan aktif ditampilkan redup.
- Hari ini mempunyai indikator jelas.
- Chip menampilkan Branch, status, dan jumlah BOM, misalnya `Kitchen Pusat · 4 BOM`.
- Jangan menjumlahkan quantity dengan UOM berbeda.
- Klik area tanggal membuat jadwal; klik chip membuka detail/editor.

### 13.2 Mobile

- Gunakan agenda/list per tanggal.
- Jangan memaksakan grid tujuh kolom yang menyebabkan horizontal scroll.
- Data dan action setara dengan desktop.

### 13.3 Filter

- Branch accessible.
- Status: Semua, Scheduled, Cancelled.
- Search BOM berada di picker, bukan pada kalender MVP.

### 13.4 State

- loading;
- kalender kosong;
- tidak ada hasil filter;
- tidak mempunyai Branch accessible;
- error dengan retry.

Tidak ada stale-catalog warning khusus Kitchen.

## 14. Picker BOM Assembly

Gunakan satu dialog aktif. Jangan membuat nested modal.

Pola yang disarankan:

- editor dan picker menjadi dua step dalam dialog yang sama; atau
- editor menggunakan page/slide-over dan picker memakai satu modal.

Ketentuan:

- search server-side;
- pagination;
- multiple selection;
- pilihan lintas halaman tetap tersimpan;
- item yang sudah dipilih diberi label/dinonaktifkan;
- tombol `Tambahkan N BOM`;
- loading, empty, dan error state;
- tidak menyimpan seluruh katalog dalam state Livewire;
- responsive dan keyboard accessible.

## 15. Validasi

### Header

- Branch wajib, aktif, dan accessible.
- Tanggal produksi wajib valid.
- Notes maksimal 2.000 karakter.
- Kombinasi Branch/tanggal unik.
- Jadwal cancelled tidak dapat diedit.

### Item

- Minimal satu BOM Assembly.
- BOM ditemukan pada katalog lokal.
- `bom_type_id = 1`.
- BOM tidak duplikat.
- Quantity decimal lebih dari nol, maksimal empat angka desimal.
- Sequence unik dan dinormalisasi server-side.
- Notes maksimal 1.000 karakter.

Snapshot dibuat server-side dari record katalog, bukan input browser.

## 16. Arsitektur minimum

Gunakan arsitektur paling ringan yang konsisten dengan repository:

```text
Filament/Livewire Page
    → Policy dan form validation
    → Save atau Cancel Action
        → database transaction
        → schedule + items
```

Komponen minimum:

- Schedule dan ScheduleItem model;
- status enum sederhana `Scheduled` dan `Cancelled`;
- factories;
- Policy;
- `SaveProductionScheduleAction` untuk create/update;
- `CancelProductionScheduleAction`;
- calendar query service;
- BOM picker query yang memakai `RndBomCatalog`;
- Filament/Livewire page dan Blade;
- Pest tests.

Tidak perlu service, event, job, atau tabel tambahan yang tidak mempunyai kebutuhan nyata.

## 17. Data safety dan concurrency

1. Save header dan items berjalan dalam database transaction.
2. Update/cancel me-requery record dan menggunakan authorization.
3. Update/cancel memakai row lock atau mekanisme concurrency existing.
4. Unique constraint mencegah jadwal ganda pada Branch/tanggal.
5. Duplicate-key diterjemahkan menjadi validation error yang jelas.
6. Gunakan `updated_at`/version untuk mencegah tab lama menimpa perubahan baru bila pola tersedia.
7. Quantity diperlakukan sebagai decimal string, bukan float.
8. Pembatalan tidak menghapus header, item, atau master BOM.
9. Perubahan jadwal tidak melakukan mutation ESB.
10. Kegagalan satu item me-rollback seluruh save.

## 18. Performa

- Query kalender hanya mengambil visible range bulan.
- Terapkan Branch scope di SQL.
- Gunakan `withCount()` atau aggregate untuk jumlah item.
- Jangan memuat `detail_snapshot` katalog pada kalender.
- Detail item baru dimuat ketika jadwal dibuka.
- Picker memakai search dan pagination database.
- Tidak ada query di Blade.
- Hindari N+1 melalui eager loading terbatas.

## 19. Acceptance criteria

Fitur diterima jika:

1. Menu `Kitchen → Jadwal Produksi` tampil untuk pengguna berizin.
2. Kalender bulanan tampil pada desktop dan agenda pada mobile.
3. Pengguna hanya melihat Branch yang dapat diakses.
4. Klik tanggal membuka form dengan tanggal terisi.
5. Picker mengambil BOM Assembly dari katalog lokal yang sama dengan modul R&D.
6. Tidak ada requirement `KITCHEN_BOM_CATALOG_MAX_AGE_HOURS`.
7. Tidak ada pemblokiran karena freshness atau sync-run Kitchen.
8. Pengguna dapat memilih beberapa BOM dan mengisi quantity.
9. Klik Simpan langsung membuat jadwal `scheduled` tanpa publish/approval.
10. Jadwal tampil pada kalender dan dapat diedit.
11. Jadwal dapat dibatalkan tanpa menghapus item/histori.
12. BOM yang sama tidak dapat ditambahkan dua kali.
13. Quantity nol/negatif ditolak.
14. Jadwal Branch/tanggal ganda ditolak.
15. Snapshot ringan menjaga jadwal lama tetap terbaca.
16. Direct request lintas Branch atau tanpa permission ditolak.
17. Kalender tidak memuat full BOM detail atau mengalami N+1.
18. Test relevan, Pint, build, dan UI verification lulus.

## 20. Testing wajib

### Domain dan database

- create jadwal dengan beberapa BOM;
- unique Branch/tanggal;
- duplicate BOM;
- decimal quantity;
- snapshot ringan;
- update item secara atomic;
- menghapus salah satu item melalui editor;
- menolak save tanpa item;
- cancel mempertahankan header/items;
- cancelled tidak dapat diedit;
- repeated cancel idempotent.

### Authorization

- accessible Branch berhasil;
- Branch lain ditolak;
- all-branch tetap membutuhkan permission;
- forged Branch ID ditolak;
- create/update/cancel permission diuji;
- direct Livewire action di-authorize.

### BOM picker

- menggunakan katalog R&D existing;
- hanya menerima type ID Assembly `1`;
- search dan pagination;
- multiple selection;
- forged/duplicate/type lain ditolak;
- active/sync behavior konsisten dengan R&D;
- tidak membutuhkan freshness environment variable.

### Kalender

- visible date range Senin–Minggu;
- prev/today/next month;
- filter Branch/status;
- jumlah BOM benar;
- desktop/mobile dataset setara;
- sidebar group tetap terbuka saat submenu aktif;
- tidak ada N+1 atau full detail JSON.

## 21. Rollout

1. Audit implementation worktree terhadap PRD terbaru ini.
2. Hapus dependency terhadap `KITCHEN_BOM_CATALOG_MAX_AGE_HOURS`.
3. Hapus tombol dan aturan Draft/Publish.
4. Hapus permission publish dan sync khusus Kitchen yang belum digunakan production.
5. Ganti create/update workflow menjadi save langsung sebagai `scheduled`.
6. Pertahankan cancel sederhana untuk data safety.
7. Reuse refresh katalog R&D; jangan buat sync Kitchen.
8. Audit perubahan `RndBomCatalog`/sync yang dibuat hanya untuk Kitchen dan revert secara selektif bila belum dibutuhkan modul lain.
9. Jika migration transitions/sync-run belum dideploy dan tidak digunakan pihak lain, bersihkan sebelum merge.
10. Jika migration sudah dideploy, buat forward compatibility migration; jangan mengedit migration lama.
11. Jalankan focused tests dan regression tests BOM R&D.
12. UAT staging: create, edit, cancel, multiple BOM, cross-Branch denial, desktop, dan mobile.

## 22. Rollback

- Sembunyikan navigation/feature bila diperlukan.
- Jangan menghapus jadwal yang sudah tersimpan.
- Jangan drop tabel berisi data tanpa backup dan persetujuan.
- Rollback kode harus kompatibel dengan schema additive.
- Katalog BOM dan modul R&D tidak boleh ikut terpengaruh.

## 23. Definition of Done

- UI hanya mempunyai kalender dan flow simpan sederhana.
- Tidak ada Draft/Publish/approval.
- Tidak ada freshness environment variable.
- Tidak ada sync khusus Kitchen.
- Semua BOM berasal dari katalog R&D existing.
- Branch authorization berjalan server-side.
- Jadwal dapat dibuat, dilihat, diedit, dan dibatalkan.
- Snapshot identitas ringan dibuat dari source server-side.
- Automated tests relevan lulus.
- PHP diformat dengan Pint.
- Frontend build dan UI desktop/mobile diverifikasi.
- Tidak ada mutation ESB, migration production, commit, push, atau deploy tanpa instruksi terpisah.

## 24. Catatan implementasi yang sedang berjalan

Sebelum melanjutkan kode, audit komponen berikut karena tidak lagi menjadi kebutuhan:

- status Draft/Published;
- `PublishProductionScheduleAction`;
- transition model/table;
- `config/kitchen.php` untuk max age;
- `.env.example` entry `KITCHEN_BOM_CATALOG_MAX_AGE_HOURS`;
- hardening sync-run yang dibuat semata-mata sebagai syarat publish Kitchen;
- permission publish/sync khusus Kitchen;
- stale catalog blocker dan warning khusus Kitchen;
- field `is_kitchen` bila tidak digunakan kebutuhan lain dan belum dideploy.

Jangan menghapus file atau migration secara buta. Lakukan reference audit, pertahankan perubahan user yang tidak terkait, dan gunakan forward migration jika schema sudah pernah dijalankan di environment bersama.
