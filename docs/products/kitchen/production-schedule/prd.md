# PRD — Kitchen: Jadwal Produksi Sederhana

## 1. Identitas dokumen

| Atribut | Nilai |
| --- | --- |
| Modul | Kitchen |
| Fitur | Jadwal Produksi |
| Status | §1–§24 (rencana produksi lokal): **`deprecated`** — fitur dihapus oleh amendment v3.0 (§38, 28 Oktober 2026); dipertahankan hanya sebagai riwayat. §25–§38 (Production Order ESB): `in-development` — keputusan K1–K22 disetujui 27–28 Oktober 2026 |
| Prioritas | P1 |
| Target pengguna | Tim Kitchen dan pengguna berizin |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |
| Acuan integrasi | `docs/architecture/integrations/esb.md` |
| Sumber BOM | Katalog BOM Assembly lokal yang sama dengan modul R&D |
| Sumber Production Order | ESB Core, dibaca read-only dan disimpan sebagai snapshot lokal (§25) |

Dokumen ini menggantikan rancangan Jadwal Produksi sebelumnya. Jika implementasi atau prompt lama bertentangan dengan dokumen ini, requirement dalam dokumen ini yang berlaku.

### Riwayat perubahan

| Tanggal | Versi | Perubahan | Status |
| --- | --- | --- | --- |
| 27 Oktober 2026 | 1.0 | Kalender rencana produksi lokal sederhana (§1–§24) | `implemented` |
| 27 Oktober 2026 | 2.0 | Amendment: Production Order ESB tampil sebagai overlay read-only pada kalender yang sama (§25–§37). Bagian lama yang terdampak diberi catatan *Amendment §25* tanpa mengubah perilaku rencana lokal | `in-development` (disetujui 27 Oktober 2026) |
| 28 Oktober 2026 | 2.1 | Mengunci tiga mapping Kitchen lintas company dan membatasi seluruh pilihan, kalender, serta sinkronisasi hanya ke Branch ber-Brand `Kitchen` | `in-development` (disetujui 28 Oktober 2026) |
| 28 Oktober 2026 | 3.0 | Rencana produksi lokal (§1–§24) dihapus total, termasuk tabelnya; halaman menjadi kalender Production Order ESB read-only yang selalu aktif (§38) | `in-development` (disetujui 28 Oktober 2026) |
| 28 Oktober 2026 | 3.1 | Mapping Production Order diambil dari Master Branch (K20); allowlist company dan daftar pasangan canonical di konfigurasi dihapus | `in-development` (disetujui 28 Oktober 2026) |
| 28 Oktober 2026 | 3.2 | Tombol **Sync Data** di kalender untuk sync manual incremental (§39) | `in-development` (disetujui 28 Oktober 2026) |

## 2. Ringkasan kebutuhan

> **Deprecated — amendment v3.0 (§38).** Bagian §1–§24 menjelaskan rencana produksi lokal yang sudah dihapus. Bagian ini tidak lagi menjadi acuan implementasi; perilaku berlaku ada di §25–§38.

Fitur yang dibutuhkan adalah kalender produksi sederhana:

1. pengguna membuka `Kitchen → Jadwal Produksi`;
2. halaman menampilkan kalender bulanan;
3. pengguna menekan sebuah tanggal;
4. pengguna memilih satu atau beberapa BOM Assembly yang akan diproduksi;
5. pengguna mengisi jumlah produksi masing-masing BOM;
6. pengguna menyimpan jadwal;
7. jadwal langsung tampil pada tanggal tersebut dan dapat dilihat, diedit, atau dibatalkan.

Daftar BOM menggunakan katalog BOM Assembly lokal yang sudah dipakai modul R&D. Modul Kitchen tidak mempunyai approval, publish, batas usia katalog, atau sinkronisasi BOM sendiri.

> *Amendment §25:* kalender yang sama juga menampilkan Production Order dari ESB sebagai data read-only. Rencana lokal di atas tidak berubah.

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

> *Amendment §25:* larangan sync-run, queue, dan scheduler di atas berlaku untuk rencana lokal dan katalog BOM. Overlay Production Order ESB mempunyai sinkronisasi read-only sendiri (§30) yang tidak pernah memblokir pembuatan, pengeditan, atau pembatalan rencana lokal.

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
12. Untuk pengguna terbatas, Branch yang dapat dipilih harus aktif, dapat diakses, dan terhubung ke Brand bernama `Kitchen`. ~~Pengguna dengan akses seluruh Branch dapat memilih seluruh Branch aktif~~ — *diganti v2.1 (§28.6, K15): pengguna dengan akses seluruh Branch, termasuk Superadmin, hanya dapat memilih seluruh Branch aktif ber-Brand `Kitchen`.* Tidak diperlukan field `is_kitchen`.

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

1. Pengguna terbatas hanya melihat jadwal dari Branch ber-Brand `Kitchen` yang dapat diakses. ~~Pengguna dengan akses seluruh Branch dapat melihat seluruh jadwal.~~ *Diganti v2.1 (§28.6, K15): pengguna dengan akses seluruh Branch melihat jadwal seluruh Branch ber-Brand `Kitchen` saja; jadwal lama pada Brand lain tidak dihapus tetapi tidak ditampilkan.*
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
- *Amendment §25:* filter Sumber dan status Production Order ESB diatur di §34.2.

### 13.4 State

- loading;
- kalender kosong;
- tidak ada hasil filter;
- tidak mempunyai Branch accessible;
- error dengan retry.

Tidak ada stale-catalog warning khusus Kitchen.

*Amendment §25:* state sinkronisasi Production Order ESB diatur di §34.4 dan tidak berlaku untuk katalog BOM.

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

*Amendment §25:* komponen tambahan untuk overlay Production Order ESB diatur di §29–§33; komponen rencana lokal di atas tetap.

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
- Tidak ada sync katalog BOM khusus Kitchen (*Amendment §25:* sync read-only Production Order ESB diizinkan sesuai §30).
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

---

# Amendment v2.0 — Production Order ESB sebagai overlay read-only

Status amendment: **`in-development`** — disetujui user pada 27 Oktober 2026. Open question §37.4 terjawab pada hari yang sama melalui verifikasi read-only ke BLSS; scheduler tetap baru diaktifkan setelah UAT staging. Urutan teknis ada di [Implementation Plan](implementation-plan.md); dokumen itu tidak menulis ulang requirement di bawah ini.

## 25. Ringkasan amendment

### 25.1 Masalah

Rencana produksi Kitchen dicatat di Bloomery Office, sedangkan Production Order yang benar-benar dibuat tim operasional tercatat di ESB. Tim Kitchen tidak dapat melihat keduanya pada satu kalender, sehingga sulit menjawab:

> Pada tanggal ini, apa yang direncanakan Kitchen, dan Production Order apa yang sudah tercatat di ESB?

### 25.2 Tujuan

1. Menampilkan Production Order ESB pada tanggal `productionOrderDate` di kalender `Kitchen → Jadwal Produksi` yang sama.
2. Mempertahankan rencana lokal (§1–§24) tanpa perubahan perilaku.
3. Membuat kedua sumber terlihat berbeda dan tidak pernah digabung otomatis.
4. Membaca data ESB di background dan menyimpannya sebagai snapshot lokal, sehingga kalender tetap dapat dibuka ketika ESB lambat atau gagal.
5. Membatasi Production Order pada Branch yang dapat diakses pengguna.

### 25.3 Scope

Termasuk: client read-only daftar dan detail Production Order; snapshot header, detail, dan material minimum; sync-run tracking; job per mapping dan rentang tanggal; command incremental dan backfill; scheduler incremental; query kalender dua sumber; detail read-only; filter sumber, Branch, status, dan rentang kalender; authorization Branch; state loading/empty/stale/partial/error; observability minimum; rollout dan rollback bertahap.

### 25.4 Tidak termasuk

- Create, edit, delete, authorize, reject, finish, result sign-off, return, atau perubahan status Production Order di ESB.
- Push rencana lokal menjadi Production Order, auto-link, atau auto-update status rencana lokal dari ESB.
- Merge record berdasarkan product code, nama BOM, tanggal, atau quantity.
- Actual production input, production result, variance, yield, waste, costing, material requirement planning, dan inventory reservation.
- Perubahan master BOM atau flow katalog BOM R&D.
- Export, notifikasi baru, dashboard monitoring baru, dependency baru, dan rewrite halaman.
- Endpoint ESB selain dua endpoint di §27.1.

## 26. Prinsip domain

1. `KitchenProductionSchedule` adalah **rencana** lokal Bloomery Office. Production Order adalah **record eksternal** milik ESB.
2. Kesamaan tanggal, Branch, BOM, product, atau quantity tidak membuktikan dua record sama. Tidak ada foreign key, status sinkronisasi, atau pencocokan otomatis antar-keduanya pada versi ini.
3. Setiap event kalender mempunyai sumber eksplisit: `local_schedule` atau `esb_production_order`. Identity event selalu menyertakan sumber, sehingga ID lokal dan ID snapshot tidak bertabrakan.
4. Nomor Production Order tidak global. Identity snapshot header adalah `company_code + production_order_num`.
5. Status ESB dan status rencana lokal tidak dipaksa memakai enum yang sama.
6. Data lintas company tidak pernah digabung berdasarkan product code.

## 27. Kontrak API ESB

### 27.1 Endpoint yang dipakai

| Endpoint (relatif terhadap `ESB_CORE_BASE_URL`, yang sudah berakhiran `/core`) | Tujuan | Kapan dipanggil |
| --- | --- | --- |
| `GET /production/production-order` | Header untuk kalender dan status | Job sync terjadwal/manual |
| `GET /production/production-order/{productionOrderNum}` | Header lengkap, detail, dan material | Job detail untuk order baru, berubah, atau belum lengkap |

Parameter daftar yang dipakai: `branchID`, `dateFrom`, `dateTo`, `statusID` (opsional), `bomTypeID` (opsional), `page`, `limit`, `sort`. `productionOrderDate` dan `productionOrderNum` hanya dipakai untuk characterization. Endpoint lain (`browse-*`, `details`, `group-production-order-material`, `print-group`, `is-finished`, `unfinished-detail`) tidak dipakai kecuali characterization membuktikan dua endpoint di atas tidak cukup dan perluasan disetujui.

Seluruh request lewat client ESB Core existing (cache token, refresh `401` satu kali, timeout); tidak ada client login kedua dan tidak ada request `POST`, `PUT`, `PATCH`, atau `DELETE`. Pemilihan credential:

- Company dalam `KITCHEN_PRODUCTION_ORDER_GLOBAL_CREDENTIAL_COMPANIES` (default `BLSS`) memakai credential global `EsbGlobalCoreClient` (`ESB_CORE_USERNAME`/`_PASSWORD`).
- Company lain memakai credential per Company Code `EsbCoreClient` (`ESB_CORE_{COMPANY}_*`).

Verifikasi 27 Oktober 2026: credential per-company BLSS mendapat HTTP `403` ("You did not have access to this resource") pada endpoint Production Order, sedangkan akun global mendapat HTTP `200`. Akun global terbukti company BLSS karena daftar `GET /branch` kedua credential identik (11 Branch, pasangan ID dan kode sama). Company lain hanya boleh ditambahkan ke daftar global setelah verifikasi yang sama.

### 27.2 Bentuk response — terverifikasi 27 Oktober 2026

Struktur diverifikasi terhadap response nyata BLSS (hanya nama field dan tipe; tidak ada data yang disalin ke repository). Fixture anonim di `tests/Pest.php` mengikuti struktur ini dengan nilai fiktif.

| Bagian | Field | Catatan |
| --- | --- | --- |
| Envelope | `path`, `timestamp`, `status` (`ok`/`fail`), `code`, `message`, `result`, `errors` | Error `403` hanya mengisi `message` |
| Pagination daftar | `result.page`, `limit`, `count`, `data`, `prev`, `next` | Hasil kosong: `data: null`, `count: 0`, `next: ""` — dibaca sebagai halaman kosong yang sah |
| Header (daftar dan detail) | `productionOrderNum`, `productionOrderDate`, `branchID` (int), `branchName`, `bomTypeID` (int), `bomTypeName`, `additionalInfo`, `statusID` (int), `statusName`, `canEdit`, `canDelete` | Tanggal ISO-8601 dengan offset (`YYYY-MM-DDTHH:MM:SS+07:00`); status yang terlihat: `Authorized`, `Result Sign Off` |
| Aktor (detail saja) | `createdBy`, `createdDate`, `editedBy`, `editedDate`, `authorizedBy`, `authorizedDate`, `creator`/`editor`/`authorizer` `{username, fullName}` | UI memakai `fullName` |
| Detail | `productionOrderDetails[]`: `ID`, `productionOrderNum`, `bomID`, `bomName`, `productDetailID`, `productID`, `productCode`, `productName`, `uomName`, `qty`, `productionOrderQty`, `stockQty`, `notes`, `flagFinished` (0/1), `productionOrderMaterials[]` | `uomName` berupa satuan resep, misalnya `Resep@210GR`: `qty` adalah isi satuan (210), **`productionOrderQty` adalah quantity produksi** (jumlah resep) |
| Material | `productionOrderMaterials[]`: `productCode`, `productName`, `uomName`, `stockQty`, `uomQty`, `bomQty`, `qtyBomDetail`, **`totalQty`**, `weightFactor`, `printGroup`, `settingDetail`, `subtitutionManufacturings` | `totalQty` = `bomQty` × quantity produksi |

Volume sampel: ±58–75 detail per order dan puluhan material per detail; material tetap JSON per detail (K6). Field lain seperti `stockQty` dan substitusi tidak disimpan pada versi ini.

### 27.3 Klasifikasi error

| Kondisi | Klasifikasi | Perilaku |
| --- | --- | --- |
| Timeout, connection failure, HTTP `429`, `5xx` | Retryable | Job di-retry dengan backoff (§30.5) |
| `401` setelah refresh token sekali | Authentication | Run `failed`, tidak di-retry otomatis |
| HTTP `400`/`422`, `status != ok` dengan `errors` | Validation/permanent | Run `failed`, tidak di-retry |
| Mapping tidak aktif, `esb_comcode` atau `esb_branch_id` kosong, credential company belum dikonfigurasi | Mapping/configuration | Tidak ada request; run `failed` dengan alasan yang dapat dibaca |
| HTTP `2xx` dengan envelope/field identity hilang, tanggal tidak valid, atau `branchID`/nomor order berbeda dari yang diminta | Malformed | Run `failed` (daftar) atau detail order `failed`; snapshot lama tidak diubah |
| Pagination melewati batas halaman (§30.3) | Malformed | Run `failed`; header yang sudah tersimpan tetap ada |

Pesan error yang disimpan disanitasi dengan `MonitoringSanitizer` existing. Token, header authorization, credential, dan payload penuh tidak pernah disimpan atau di-log.

## 28. Mapping Branch dan company

1. Sumber scope integrasi adalah `branch_esb_codes`. Parameter `branchID` berasal dari `esb_branch_id`, bukan primary key Branch lokal dan bukan `esb_branch_code`. Company credential dipilih dari `esb_comcode`.
2. Mapping yang diproses wajib memenuhi seluruh kondisi: `is_active = true`, `esb_comcode` terisi dan terdaftar di `config/esb.php` (`esb.core.companies`), `esb_branch_id` terisi, Branch lokal aktif, dan Branch lokal terhubung ke Brand bernama `Kitchen`. *v3.1 (K20): tidak ada lagi allowlist company atau daftar pasangan di konfigurasi; seluruh mapping yang memenuhi kondisi ini diproses.*
3. Mapping Production Order saat ini di Master Branch adalah sebagai berikut (data, bukan aturan kode; Branch Kitchen baru cukup ditambahkan melalui Master Branch — K20). Nilai `esb_branch_id` numerik wajib berasal dari **Master → Branch → Sync Branch ESB** atau lookup API Branch dalam company terkait; nilainya tidak boleh ditebak dari ID lokal atau Branch Code.

| Branch lokal | Brand wajib | `esb_comcode` | `esb_branch_code` | `esb_branch_id` |
| --- | --- | --- | --- | --- |
| Kitchen Jateng | `Kitchen` | `BLSS` | `KTJ` | Hasil sinkronisasi API company `BLSS` |
| Kitchen Surabaya | `Kitchen` | `BLO13` | `HOF` | Hasil sinkronisasi API company `BLO13` |
| Kitchen Jakarta | `Kitchen` | `BLHJ` | `TCHL` | Hasil sinkronisasi API company `BLHJ` |

4. **Multiple mapping.** Satu Branch dapat mempunyai beberapa mapping karena unique key existing adalah `branch_id + esb_branch_code + esb_comcode`. *v3.1 (K20):* setiap mapping aktif dan lengkap pada Branch Kitchen disinkronkan terpisah dengan company context masing-masing (preseden K8); tidak ada pemilihan berdasarkan urutan record, label, atau nama. Mapping yang tidak boleh dipakai untuk Production Order dinonaktifkan di Master Branch.
5. ~~Allowlist company `KITCHEN_PRODUCTION_ORDER_COMPANIES` (K13)~~ — dihapus v3.1 (K20). Company baru hanya perlu didaftarkan sekali di `config/esb.php` (`esb.core.companies`, credential dari environment) bila kodenya belum ada; `BLHJ` sudah terdaftar.
6. Scope sync **selalu hanya Branch ber-Brand `Kitchen`**. `canAccessAllBranches()` dan `view all branch kitchen production schedules` hanya memperluas akses ke seluruh Branch Kitchen; keduanya tidak pernah memasukkan Branch Patisserie, Warehouse, Head Office, atau Brand lain ke filter, pembuatan jadwal, kalender, detail, status sync, maupun planner.
7. **Konflik kepemilikan.** Jika `company_code + production_order_num` yang sama datang dari mapping lain dengan Branch lokal berbeda, snapshot tidak dipindahkan ke Branch baru. Order tersebut dihitung sebagai konflik, run berstatus `partial`, dan konflik dicatat di log untuk diperbaiki di master mapping.
8. Mapping yang dinonaktifkan atau dihapus menghentikan sync baru. Snapshot lama tetap terbaca oleh pengguna yang berhak atas Branch Kitchen lokalnya.

## 29. Data snapshot

Semua tabel baru bersifat additive. Tabel `kitchen_production_schedules` dan item-nya tidak diubah. Migration tidak memanggil ESB dan tidak melakukan backfill.

### 29.1 `esb_production_orders`

| Kolom | Aturan |
| --- | --- |
| `branch_esb_code_id` | Nullable FK ke mapping, `null on delete`, agar snapshot tidak ikut terhapus |
| `branch_id` | Branch lokal hasil mapping saat pertama disimpan; FK restrictive (tidak cascade) |
| `company_code`, `esb_branch_id` | Context ESB saat disinkronkan |
| `esb_branch_name` | Snapshot label Branch ESB, nullable |
| `production_order_num` | Identifier eksternal |
| `production_order_date` | Tanggal produksi (date) |
| `bom_type_id`, `bom_type_name` | Snapshot, nullable bila ESB tidak mengirim |
| `status_id`, `status_name` | Snapshot ID dan label status ESB |
| `additional_info` | Nullable text |
| `created_by_name`, `esb_created_at`, `edited_by_name`, `esb_edited_at`, `authorized_by_name`, `esb_authorized_at` | Nullable; hanya field yang tersedia di response |
| `source_fetched_at` | Waktu header terakhir ditulis dari ESB |
| `source_last_seen_at` | Waktu header terakhir muncul di response daftar yang lengkap |
| `details_synced_at` | Nullable; terisi setelah detail berhasil disimpan |
| `payload_hash` | Hash header yang sudah dinormalisasi |
| timestamps | Wajib |

Constraint: unique `(company_code, production_order_num)`; index `(branch_id, production_order_date)` dan `(branch_id, status_id, production_order_date)`.

### 29.2 `esb_production_order_details`

| Kolom | Aturan |
| --- | --- |
| `esb_production_order_id` | FK header, cascade on delete header (header tidak dihapus oleh aplikasi) |
| `external_detail_id` | ID detail dari ESB |
| `bom_id`, `bom_name`, `bom_type_name` | Snapshot |
| `product_detail_id`, `product_code`, `product_name` | Snapshot |
| `quantity` | Decimal `(18,4)`; disimpan dari string, bukan float |
| `uom_name` | Snapshot |
| `notes` | Nullable |
| `is_finished` | Nullable boolean bila tersedia |
| `materials` | JSON array minimum per material: kode, nama, quantity (string decimal), UOM |
| `sequence` | Urutan sesuai response |
| timestamps | Wajib |

Constraint: unique `(esb_production_order_id, external_detail_id)`. Index lain hanya ditambah untuk query yang benar-benar dipakai.

Material disimpan sebagai JSON per detail karena hanya ditampilkan read-only di detail dan tidak difilter, diagregasi, atau direkonsiliasi (keputusan K6). Child table baru dibuat bila kebutuhan itu muncul.

### 29.3 `esb_production_order_sync_runs`

| Kolom | Aturan |
| --- | --- |
| `idempotency_key` | Mapping + mode + `date_from` + `date_to`; di-index, tidak unique karena satu window mempunyai riwayat banyak eksekusi |
| `active_key` | Nullable unique; berisi `idempotency_key` selama run `queued`/`running` dan dikosongkan saat selesai, sehingga database menjamin paling banyak satu run aktif per window |
| `branch_esb_code_id`, `branch_id`, `company_code`, `esb_branch_id` | Context run; mapping nullable `null on delete` |
| `mode` | `incremental` atau `backfill` |
| `date_from`, `date_to` | Window run |
| `status` | `queued`, `running`, `succeeded`, `partial`, `failed` |
| `last_page` | Halaman terakhir yang berhasil dibaca |
| `pages_fetched`, `headers_received`, `headers_created`, `headers_updated`, `headers_unchanged`, `conflicts`, `details_expected`, `details_succeeded`, `details_failed` | Counter |
| `attempts` | Jumlah percobaan |
| `error_class`, `error_code`, `error_summary` | Sanitasi, `error_summary` maksimal 500 karakter |
| `correlation_id` | UUID per percobaan |
| `started_at`, `list_completed_at`, `finished_at` | Waktu mulai, waktu seluruh halaman daftar berhasil dibaca, dan waktu run selesai |
| timestamps | Wajib |

Setiap eksekusi planner membuat satu run baru; retry job memakai run yang sama (ID run dibawa job). Run aktif yang tidak bergerak lebih dari 2 jam dianggap terhenti, ditandai `failed`, lalu window boleh dijadwalkan ulang. `list_completed_at` menjadi dasar "Terakhir disinkronkan" dan ambang stale (K10). Riwayat run dihapus otomatis setelah 90 hari (K12); snapshot tidak pernah ikut terhapus.

## 30. Pipeline sinkronisasi

### 30.1 Planner

- Memilih setiap mapping aktif dan lengkap pada Branch Kitchen aktif sesuai §28.2–§28.6. Mapping non-Kitchen, tidak aktif, atau tidak lengkap tidak di-dispatch; command menampilkan mapping Branch Kitchen yang dilewati beserta alasannya.
- Membagi window menjadi unit maksimal 31 hari per mapping, sehingga window incremental (22 hari) adalah satu unit; memory tetap dibatasi per halaman, bukan per window.
- Kegagalan satu mapping tidak menghentikan mapping lain.

### 30.2 Job daftar (satu mapping, satu window)

1. Membaca ESB sebelum membuka transaction; request HTTP tidak pernah berada di dalam transaction.
2. Membaca halaman berurutan dan meng-upsert header per halaman dalam satu transaction kecil.
3. Header baru, header dengan `payload_hash` berubah, atau header tanpa `details_synced_at` dijadwalkan untuk job detail.
4. Order di luar window atau dengan `branchID` berbeda dianggap malformed dan tidak disimpan.

### 30.3 Batas pagination

Pembacaan berhenti ketika `next` kosong, halaman berisi data kosong, atau `page × limit ≥ count`. Batas keras 50 halaman per unit job; melewati batas berarti run `failed` (malformed), bukan loop.

### 30.4 Job detail (satu order)

- Memakai company context snapshot dan `productionOrderNum`.
- Memverifikasi nomor order, company, dan `branchID` response sebelum menyimpan.
- Menyimpan detail dalam satu transaction per order. Detail yang tidak lagi ada di response detail yang valid dihapus dari snapshot detail order tersebut hanya setelah characterization (§27.2) membuktikan response detail selalu memuat seluruh detail order; sebelum itu detail lama dipertahankan. Header tidak pernah dihapus.
- Kegagalan satu order menambah `details_failed`; run menjadi `partial`, header lain tetap valid.
- Detail tidak pernah dipanggil saat render kalender atau saat modal dibuka.

### 30.5 Queue

- Memakai koneksi queue dan worker existing (`queue:work redis --queue=default --timeout=90`); queue dapat dipisah lewat konfigurasi seperti Ordering.
- `timeout` job di bawah `retry_after` 90 detik; `tries = 3`; backoff `[60, 300, 900]` hanya untuk error retryable.
- Uniqueness window dijamin di database oleh `active_key` (§29.3): planner menolak run aktif kedua untuk mapping + mode + window yang sama. Eksekusi job daftar dan job detail diserialkan dengan `WithoutOverlapping` (per run dan per order). Unique-job lock Laravel tidak dipakai karena dispatch yang ditolak diam-diam akan membuat run menunggu hasil yang tidak pernah datang.
- Job daftar berhenti setelah ±45 detik kerja ESB dan menyerahkan halaman berikutnya ke job lanjutan (mulai dari `last_page + 1`), agar tidak terkena timeout worker.
- Payload job hanya berisi ID mapping, ID run, mode, tanggal, atau ID snapshot — tidak ada token atau response.

### 30.6 Incremental, backfill, dan scheduler

| Mode | Window | Pemicu |
| --- | --- | --- |
| Incremental | Hari ini −7 hari sampai +14 hari (K2) | `kitchen:sync-production-orders` oleh scheduler setiap 60 menit (K3), `withoutOverlapping()` dan `onOneServer()`; `--dry-run` untuk pratinjau |
| Backfill | Eksplisit `--from` dan `--to`, dengan scope `--branch` atau `--mapping` | `kitchen:backfill-production-orders`, dry-run secara default; `--execute` untuk dispatch |

- Backfill awal: 90 hari ke belakang sampai 14 hari ke depan (K1).
- Kedua command menampilkan jumlah mapping, unit job, dan mapping yang dilewati beserta alasannya.
- Range lebih dari 120 hari ditolak kecuali dengan flag `--allow-long-range`.
- Scheduler tidak pernah menjalankan backfill.
- Scheduler dan layer kalender masing-masing mempunyai toggle konfigurasi yang default nonaktif sampai rollout (§36). Nama variabel final ditetapkan di `rollout.md`.

## 31. Lifecycle snapshot

1. Upsert payload yang sama idempotent: tidak ada duplicate dan counter `headers_unchanged` bertambah.
2. Perubahan status atau detail di ESB memperbarui snapshot yang sama.
3. Order yang tidak muncul pada satu response tidak dihapus. Bila order tidak terlihat pada sync daftar yang lengkap untuk window tanggalnya, detail menampilkan "Tidak terlihat pada sinkronisasi terakhir" berdasarkan `source_last_seen_at`; kalender tetap menampilkannya (K11).
4. Aplikasi tidak pernah hard-delete header snapshot. Retention: snapshot dipertahankan tanpa pruning pada versi ini; volume ditinjau setelah 12 bulan (K4).
5. Label Branch, status, BOM, product, dan UOM disimpan sebagai snapshot agar histori tetap terbaca ketika master ESB berubah.

## 32. Authorization

1. Menu dan halaman tetap memerlukan `view kitchen production schedules`. **Tidak ada permission baru** (K7).
2. Scope Production Order sama dengan scope rencana lokal (`KitchenProductionSchedule::scopeVisibleTo` dan `KitchenProductionSchedulePolicy::view`) dan **selalu** dibatasi ke Branch ber-Brand `Kitchen`. Pengguna terbatas hanya melihat Branch Kitchen yang dapat diakses; `canAccessAllBranches()` atau `view all branch kitchen production schedules` memperluas scope hanya ke seluruh Branch Kitchen dan tetap memerlukan permission fitur.
3. Scope Branch diterapkan di SQL pada query kalender, daftar status filter, dan detail.
4. Detail dibuka berdasarkan sumber dan ID snapshot yang di-query ulang di server, lalu diotorisasi ulang. Source, Branch ID, company code, ESB branch ID, nomor order, dan ID record dari browser tidak dipercaya.
5. Create/update/cancel hanya berlaku untuk `local_schedule`. Action mutation yang diarahkan ke event ESB ditolak di server; event ESB tidak menampilkan tombol mutation.
6. Command, scheduler, dan job tidak memakai user browser; scope mapping ditentukan server-side.
7. Tidak ada hardcoded role.

## 33. Query kalender

1. `ProductionScheduleCalendarQuery::schedules()` dipertahankan untuk sumber `local_schedule` tanpa perubahan semantics.
2. Query snapshot ESB memakai visible range, Branch scope, dan filter Branch yang sama, dengan filter status ESB sendiri.
3. Setiap hari kalender memuat dua daftar bertipe yang terpisah: `schedules` (sumber `local_schedule`, struktur existing) dan `orders` (sumber `esb_production_order`). Setiap event ESB menyediakan: identity snapshot, tanggal, Branch lokal (ID dan nama), nomor, status (ID dan label), jumlah item, dan waktu sinkronisasi terakhir; event ESB tidak pernah editable. Action, `wire:key`, dan detail resolver dibedakan per sumber, sehingga ID kedua sumber tidak dapat tertukar.
4. Jumlah item ESB berasal dari `withCount` detail; tidak ada quantity yang dijumlahkan lintas UOM.
5. Query kalender tidak memuat detail atau material. Detail dimuat saat event dibuka.
6. Jumlah query kalender tetap konstan terhadap jumlah event (tanpa N+1).

## 34. UI

Mengikuti `docs/standards/ui-consistency.md` dan pola kalender existing (§13).

### 34.1 Kalender

- Grid desktop tujuh kolom dan agenda mobile tetap; tidak ada horizontal scroll di mobile.
- Chip rencana lokal tetap seperti sekarang. Chip ESB memakai gaya berbeda dari token yang sama, badge teks `ESB`, nomor Production Order, Branch, status, dan jumlah item. Warna bukan satu-satunya pembeda.
- Legend singkat menjelaskan *Rencana* (dibuat di Bloomery Office, dapat diubah) dan *Production Order ESB* (dari ESB, hanya baca).
- Klik area tanggal tetap membuat rencana lokal; klik chip lokal tetap membuka detail/editor existing; klik chip ESB membuka detail read-only.

### 34.2 Filter

- Sumber: `Semua`, `Rencana`, `Production Order ESB`. Default `Semua`.
- Brand: context halaman selalu `Kitchen`. Jika kontrol Brand ditampilkan untuk konsistensi UI, nilainya otomatis `Kitchen` dan tidak menawarkan Brand lain; kontrol boleh dihilangkan karena menu sudah berada di domain Kitchen.
- Branch: filter existing berlaku untuk kedua sumber dan hanya memuat Branch ber-Brand `Kitchen`. Opsi default `Semua Kitchen`, kemudian Kitchen Jakarta, Kitchen Jateng, dan Kitchen Surabaya sesuai akses pengguna.
- Status: ketika sumber `Rencana`, opsi status lokal existing; ketika sumber `Production Order ESB`, opsi berasal dari status snapshot yang ada di Branch scope pengguna; ketika `Semua`, filter status tidak memaksa satu enum untuk dua sumber (status lokal hanya menyaring rencana, status ESB hanya menyaring Production Order).
- Seluruh filter diterapkan server-side dan tersimpan di URL seperti filter existing.

### 34.3 Detail Production Order

- Modal existing (`x-rnd.picker-modal` dengan trap fokus), lebar seperti detail jadwal, tinggi maksimal sekitar 90vh, satu area scroll body, footer hanya tombol `Tutup`.
- Header: nomor Production Order, tanggal, Branch, tipe BOM, status, informasi tambahan, dibuat/diubah/diotorisasi oleh bila tersedia, dan "Terakhir disinkronkan".
- Tabel detail responsif: BOM/product, kode, quantity, UOM, status selesai, catatan. Material tampil sebagai bagian yang dapat dibuka per detail.
- Tidak ada form control atau tombol yang terkesan dapat mengubah ESB.

### 34.4 State dan copy

| State | Copy yang disarankan |
| --- | --- |
| Tidak ada Production Order pada filter | "Tidak ada Production Order ESB pada periode dan filter ini." |
| Sync belum pernah berjalan | "Production Order ESB belum pernah disinkronkan untuk cabang ini." |
| Branch belum mempunyai mapping ESB | "Cabang ini belum terhubung ke ESB. Hubungi IT untuk mengatur mapping cabang." |
| Mapping tidak lengkap | "Mapping ESB cabang ini belum lengkap, sehingga Production Order belum dapat diambil." |
| Sync sedang berjalan | "Sinkronisasi sedang berjalan. Data akan diperbarui otomatis." |
| Sync terakhir berhasil | "Terakhir disinkronkan {waktu}." |
| Snapshot stale (sync sukses terakhir > 3 jam, K10) | "Data Production Order mungkin belum terbaru. Terakhir disinkronkan {waktu}." |
| Sync partial | "Sebagian detail Production Order belum berhasil diambil." |
| Sync gagal, snapshot lama tersedia | "Sinkronisasi terakhir gagal. Menampilkan data terakhir yang tersimpan." |
| Detail belum tersedia | "Detail Production Order ini sedang diambil dari ESB." |

Status sinkronisasi diringkas per Branch dalam scope pengguna. Istilah teknis seperti payload, hydration, cursor, atau malformed tidak muncul di UI. Kalender rencana lokal tetap tampil penuh pada semua state di atas.

## 35. Observability

- Setiap run menyimpan status, counter (§29.3), durasi, dan error sanitasi.
- Log terstruktur berisi run ID, mapping ID, Branch lokal, company code, window, halaman, durasi, dan klasifikasi error — tanpa token, password, header authorization, atau payload.
- Scheduler command tercatat otomatis di riwayat scheduled task System Monitoring existing. Tidak ada dashboard baru; tampilan last-success/failure di System Monitoring dipertimbangkan setelah pipeline stabil.

## 36. Rollout dan rollback

Rollout:

1. Deploy schema dan client dengan toggle layer dan scheduler nonaktif; tidak ada backfill otomatis.
2. Staging: sync manual satu mapping dengan range pendek, rekonsiliasi header, status, tanggal, detail, quantity, UOM, dan material dengan ESB.
3. Staging: backfill sesuai K1, lalu UAT desktop dan mobile.
4. Production: deploy lewat atomic release existing, sync manual satu Branch dan range pendek, verifikasi health, queue, log, run, memory, disk, dan kalender.
5. Aktifkan scheduler bertahap dan pantau minimal satu siklus penuh.

Rollback:

- Toggle layer menyembunyikan Production Order ESB tanpa mematikan rencana lokal.
- Toggle scheduler menghentikan dispatch job Production Order tanpa menghentikan queue global.
- Tabel snapshot tidak di-drop dan snapshot tidak dihapus pada emergency rollback; rollback kode tetap kompatibel dengan schema additive.
- Bila health check gagal, gunakan atomic release rollback existing.

Langkah server, nama variabel environment, dan checklist UAT ditulis di `rollout.md` (dibuat dari `docs/templates/feature/rollout-runbook-template.md`).

## 37. Acceptance criteria, testing, dan keputusan

### 37.1 Acceptance criteria

1. Menu tetap `Kitchen → Jadwal Produksi`; rencana lokal dapat dibuat, diedit, dan dibatalkan seperti §19.
2. Production Order ESB tampil pada `productionOrderDate` dengan badge `ESB` dan berbeda secara visual serta tekstual dari rencana.
3. Klik Production Order membuka detail read-only berisi header, status, detail BOM/product, quantity, UOM, dan material bila tersedia.
4. Filter Sumber, Branch, status, dan rentang kalender bekerja server-side.
5. Kalender dan detail membaca snapshot lokal; tidak ada request ESB saat render atau membuka detail.
6. Sinkronisasi per mapping idempotent, retryable sesuai klasifikasi, bounded, dan tercatat di sync run.
7. Pengguna tidak dapat melihat Production Order dari Branch yang tidak dapat diakses, termasuk lewat request palsu.
8. ESB gagal tidak menghilangkan snapshot terakhir; UI menampilkan waktu sinkronisasi dan peringatan.
9. Tidak ada mutation Production Order ESB.
10. Test, Pint, build, cek dokumentasi, UAT staging, dan rekonsiliasi lulus.
11. Filter Branch, editor jadwal, kalender Production Order, detail, status sync, dan planner tidak pernah memuat Branch non-Kitchen, termasuk untuk Superadmin atau user all-branch.
12. Setiap mapping Branch Kitchen pada §28.3 diproses terpisah menggunakan company context masing-masing; `BLHJ` dikenali oleh konfigurasi ESB. Branch Kitchen baru dengan mapping aktif dan lengkap ikut tersinkron tanpa perubahan kode atau konfigurasi (K20).

### 37.2 Testing wajib

- **Client:** path dan query daftar; company context; `branchID` dari `esb_branch_id`; satu dan banyak halaman; hasil kosong; nullable; detail dengan material; `400`; `401` lalu berhasil; `401` berulang; `500` dan timeout; envelope rusak; batas halaman.
- **Snapshot:** unique company + nomor; nomor sama di company berbeda diizinkan; upsert header dan detail idempotent; update status tanpa duplicate; quantity decimal akurat; label snapshot tetap terbaca; sync parsial tidak menghapus snapshot; konflik mapping tidak memindahkan Branch; counter dan status run konsisten.
- **Queue dan command:** job per mapping dan window; isolasi multi-company dan multi-branch; mapping tidak lengkap/nonaktif dilewati; uniqueness; retry hanya untuk error retryable; partial detail failure; guard range backfill dan `--dry-run`; scheduler hanya incremental; retry tanpa duplicate.
- **Authorization:** permission + Branch access; tanpa permission ditolak; Branch A tidak dapat membuka detail Branch B; all-branch tetap butuh permission fitur dan hanya mendapat seluruh Branch Kitchen; Branch non-Kitchen ditolak pada filter, create/update, query kalender, detail, status sync, dan planner; source/ID/Branch/company/nomor palsu ditolak; action mutation lokal tidak dapat diarahkan ke event ESB.
- **Kalender dan UI:** regression suite Kitchen existing; visible range; order tampil pada tanggal produksi; filter sumber, Branch, dan status per sumber; identity tidak bertabrakan; jumlah item; detail tidak dimuat di query kalender; tanpa N+1; detail ESB read-only; seluruh state §34.4; modal dapat di-scroll; mobile tanpa horizontal scroll; sidebar Kitchen tetap terbuka.
- **Regression:** Kitchen, R&D BOM catalog, Branch ESB mapping, `EsbCoreClient`, permission/sidebar, dan scheduler smoke.

### 37.3 Keputusan Phase 0 (diusulkan, menunggu persetujuan)

| # | Keputusan | Usulan | Alasan |
| --- | --- | --- | --- |
| K1 | Histori backfill awal | 90 hari ke belakang sampai 14 hari ke depan, manual per mapping | Selaras dengan sync Product Price Index `--days=90`; cukup untuk konteks operasional tanpa membebani ESB |
| K2 | Window incremental | Hari ini −7 sampai +14 hari | Menangkap perubahan status order minggu berjalan dan order yang direncanakan dua minggu ke depan |
| K3 | Frekuensi | Setiap 60 menit | Kalender bersifat perencanaan; beban ESB rendah. Dapat dipercepat setelah volume terukur |
| K4 | Retention | Tanpa pruning; ditinjau setelah 12 bulan | Default aman; volume header/detail diperkirakan kecil |
| K5 | Status ESB yang ditampilkan | Semua status, dengan label snapshot | Daftar status ESB belum terverifikasi; menyembunyikan status tanpa data berisiko menyesatkan |
| K6 | Material | JSON per detail | Hanya dibaca di detail; tidak difilter atau diagregasi |
| K7 | Permission | Reuse permission Jadwal Produksi existing | Data berada di halaman dan scope Branch yang sama |
| K8 | Multiple mapping | Sinkronkan setiap mapping aktif yang lengkap; konflik kepemilikan tidak memindahkan Branch (§28.5) | Mengikuti preseden Ordering Recommendation; tidak menebak mapping utama |
| K9 | Branch yang disinkronkan | **Hanya Branch aktif ber-Brand `Kitchen`**, termasuk untuk user all-branch (keputusan terbaru 28 Oktober 2026; menggantikan keputusan 27 Oktober 2026 yang sempat mengizinkan Brand lain) | Menu ini khusus Kitchen; mapping canonical kini sudah ditetapkan pada tiga Branch Kitchen |
| K10 | Ambang stale | 3 jam sejak sync sukses terakhir mapping | Tiga kali interval K3 |
| K11 | Order yang tidak lagi terlihat di ESB | Tetap tampil, detail diberi catatan "Tidak terlihat pada sinkronisasi terakhir" | Semantik penghapusan di ESB belum diketahui |
| K12 | Retention riwayat sync run | Dihapus otomatis setelah 90 hari lewat `model:prune` | Run dibuat per eksekusi (±24 per mapping per hari); snapshot tidak terdampak |

| K13 | Company Code yang disinkronkan | ~~`BLSS`, `BLO13`, dan `BLHJ` melalui `KITCHEN_PRODUCTION_ORDER_COMPANIES`~~ → digantikan K20 | — |
| K14 | Mapping canonical | ~~Pasangan tetap di konfigurasi~~ → digantikan K20. Data saat ini: Kitchen Jateng=`BLSS/KTJ`; Kitchen Surabaya=`BLO13/HOF`; Kitchen Jakarta=`BLHJ/TCHL`; `esb_branch_id` selalu hasil sync API | — |
| K20 | Sumber mapping Production Order | Master Branch: setiap mapping aktif dan lengkap pada Branch ber-Brand `Kitchen` disinkronkan; allowlist company dan daftar pasangan di konfigurasi dihapus (v3.1, 28 Oktober 2026) | Branch Kitchen baru cukup ditambahkan di Master Branch tanpa mengubah kode atau konfigurasi |
| K15 | Filter Brand | Context Brand selalu `Kitchen`; user all-branch tidak melihat Brand lain | Menjaga halaman dan data sesuai domain Kitchen |

Status: K1–K8 dan K10–K12 disetujui 27 Oktober 2026. K9 dan K13 diperbarui serta K14–K15 disetujui user pada 28 Oktober 2026; keputusan terbaru menggantikan bagian yang bertentangan.

### 37.4 Open questions — terjawab 27 Oktober 2026

1. **Kontrak response nyata (§27.2).** ✅ Terverifikasi dengan request read-only ke BLSS atas izin user; normalizer dan fixture disesuaikan.
2. **Akses credential.** ✅ Credential per-company BLSS ditolak (`403`); credential global terbukti company BLSS dan dipakai lewat `KITCHEN_PRODUCTION_ORDER_GLOBAL_CREDENTIAL_COMPANIES` (§27.1).
3. **Scope Branch (K9/K15).** ✅ Hanya Branch ber-Brand `Kitchen`, termasuk untuk user all-branch (§28.6).
4. ~~Keputusan K1–K11~~ — disetujui 27 Oktober 2026.
5. **Mapping tiga Kitchen (K13/K14).** ✅ `BLSS/KTJ`, `BLO13/HOF`, dan `BLHJ/TCHL`; ID numerik tetap diambil dari API.

Catatan verifikasi lokal: mapping `branch_esb_codes` dapat menyimpan Branch ID ESB yang usang (ditemukan `KTJ` = 2, padahal ESB = 4). Jalankan **Master → Branch → Sync Branch ESB** sebelum sync Production Order pertama di setiap environment. Scheduler tetap tidak diaktifkan sebelum UAT staging (§36).

---

# Amendment v3.0 — Kalender khusus Production Order ESB

Status: **`in-development`** — disetujui user 28 Oktober 2026. Amendment ini menggantikan seluruh §1–§24 dan setiap bagian §25–§37 yang menyebut rencana lokal.

## 38. Penghapusan rencana produksi lokal

### 38.1 Keputusan

| # | Keputusan | Detail |
| --- | --- | --- |
| K16 | Rencana lokal dihapus total | Buat, ubah, batalkan jadwal, picker BOM Assembly, chip rencana, dan detail jadwal tidak ada lagi. Kode Action, validator, Policy, enum, model, factory, dan test rencana lokal dihapus |
| K17 | Tabel dihapus | Migration baru men-drop `kitchen_production_schedule_items`, `kitchen_production_schedule_transitions` (sisa versi lama bila ada), dan `kitchen_production_schedules`. **Backup wajib** sebelum migration dijalankan di environment bersama; data tidak dapat dipulihkan dari aplikasi |
| K18 | Toggle layer dihapus | `KITCHEN_PRODUCTION_ORDERS_ENABLED` tidak dipakai lagi; kalender Production Order selalu tampil. Toggle scheduler `KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED` tetap |
| K19 | Menu dan permission | Menu tetap `Kitchen → Jadwal Produksi`, URL tetap. Permission `view kitchen production schedules` dan `view all branch kitchen production schedules` tetap; `create`, `update`, dan `cancel kitchen production schedules` dihapus melalui daftar permission usang `permissions:sync` |

### 38.2 Perilaku halaman

- Judul halaman tetap *Jadwal Produksi*; isi halaman adalah kalender Production Order ESB read-only dari snapshot lokal.
- Desktop: grid bulanan tujuh kolom; mobile: agenda per tanggal. Klik tanggal tidak membuat apa pun; klik chip membuka detail read-only (§34.3).
- Filter: Branch (`Semua Kitchen` dan Branch ber-Brand Kitchen sesuai akses, §28.6) dan Status ESB. Filter Sumber dan Status Rencana dihapus karena hanya ada satu sumber.
- Banner status sinkronisasi (§34.4) dan seluruh aturan authorization (§32), sync (§30), dan snapshot (§29, §31) tetap berlaku.
- Empty state: "Tidak ada Production Order ESB pada periode dan filter ini." Tidak ada ajakan membuat jadwal.

### 38.3 Bagian yang digantikan

| Bagian | Pengganti |
| --- | --- |
| §1–§24 | Dihapus (riwayat) |
| §25.2 butir 2–3, §26 butir 1–3, §33.1, §33.3 (`schedules`) | Hanya satu sumber: `esb_production_order` |
| §32 butir 5 | Tidak ada mutation sama sekali di halaman |
| §34.1 (chip rencana, klik tanggal), §34.2 (Sumber, Status Rencana) | §38.2 |
| §36 rollback "toggle layer" | §38.4 |
| §37.1 butir 1 | Menu tetap; tidak ada rencana lokal |

### 38.4 Rollout dan rollback

1. Backup tabel `kitchen_production_schedules`, `kitchen_production_schedule_items`, dan `kitchen_production_schedule_transitions` (bila ada) sebelum deploy.
2. Deploy menjalankan migration drop dan `permissions:sync` (menghapus tiga permission usang).
3. Rollback aplikasi ke release sebelum v3.0 **membutuhkan tabel lama**: jalankan `php artisan migrate:rollback --step=1` (membuat ulang struktur kosong) lalu pulihkan backup. Tanpa langkah ini halaman lama akan error.
4. Menyembunyikan halaman tanpa rollback dilakukan dengan mencabut `view kitchen production schedules` dari role terkait; scheduler dihentikan dengan `KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED=false`.

### 38.5 Acceptance criteria tambahan

1. Halaman tidak menampilkan tombol, form, atau aksi pembuatan/perubahan apa pun.
2. Tabel jadwal lokal tidak ada lagi setelah migration; tidak ada kode yang mereferensikannya.
3. Kalender Production Order tampil tanpa toggle environment.
4. Permission `create/update/cancel kitchen production schedules` tidak terdaftar lagi.

---

# Amendment v3.2 — Sync manual dari kalender

Status: **`in-development`** — disetujui user 28 Oktober 2026.

## 39. Tombol Sync Data

### 39.1 Keputusan

| # | Keputusan | Detail |
| --- | --- | --- |
| K21 | Akses tombol | Permission baru `sync kitchen production orders` (domain Kitchen → Jadwal Produksi), additive dan default tidak diberikan ke role mana pun selain `SUPERADMIN` melalui `permissions:sync`. Tetap memerlukan `view kitchen production schedules` |
| K22 | Cooldown | 5 menit per mapping (`kitchen.production_orders.manual_sync_cooldown_minutes`), dihitung dari run terakhir mapping tersebut, baik dari tombol maupun scheduler |

### 39.2 Perilaku

1. Tombol **Sync Data** berada di toolbar kalender bersama filter dan hanya tampil bagi pengguna dengan permission K21.
2. Scope: Branch yang dipilih di filter Branch, atau seluruh Branch Kitchen yang dapat diakses pengguna bila filter `Semua Kitchen`. Branch ID dari browser divalidasi ulang terhadap daftar Branch Kitchen pengguna; Branch lain ditolak.
3. Rentang selalu incremental (§30.6, hari ini −7 s.d. +14 hari). Backfill tetap hanya lewat command.
4. Tombol hanya mengantrekan job melalui planner yang sama dengan scheduler (§30.1); request browser tidak memanggil ESB. Mapping yang mempunyai run aktif dilewati (`active_key`), mapping dalam cooldown dilewati, dan mapping yang tidak lengkap dilewati dengan alasan dari planner.
5. Feedback: notifikasi jumlah cabang yang diantrekan, atau alasan bila tidak ada yang diantrekan (sedang berjalan / tunggu beberapa menit / mapping belum lengkap).
6. Selama ada run aktif dalam scope, banner menampilkan "Sinkronisasi sedang berjalan" dan halaman memperbarui diri setiap 5 detik sampai run selesai.
7. Tombol tidak mengubah data ESB.

### 39.3 Acceptance criteria tambahan

1. Pengguna tanpa permission K21 tidak melihat tombol dan request langsung ke action ditolak di server.
2. Klik tombol mengantrekan satu job per mapping eligible dalam scope dan tidak pernah menyertakan Branch non-Kitchen atau Branch yang tidak dapat diakses.
3. Klik berulang dalam cooldown atau selama run aktif tidak membuat job baru.
