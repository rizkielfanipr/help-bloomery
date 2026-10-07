# PRD — Master Shelf Life per WIP

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Modul | Research & Development |
| Fitur | Master Shelf Life per WIP |
| Baseline audit | 6 Oktober 2026 |
| Status | Rancangan untuk review; belum mengotorisasi implementasi, migration production, atau deployment |
| Target pengguna | R&D Staff dan pengguna Project yang mempunyai izin terkait |
| Stack | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Tailwind CSS 4, Pest 4 |
| Referensi arsitektur | `docs/code-remediation-prd.md` |
| Referensi UI | `docs/ui-consistency-prd.md` |
| Referensi domain | `docs/rnd-bom-adjustment-prd.md`, `docs/rnd-internal-memo-simplification-prd.md` |

Dokumen ini menggantikan asumsi lama bahwa Shelf Life dikelola per Menu atau final Product. Keputusan bisnis terbaru adalah bahwa Shelf Life dikelola per item WIP dan master operasionalnya berada di BOM Adjustment.

PRD ini tidak mengizinkan penghapusan data production, mutation ESB production, penghapusan permission production, atau deployment. Kondisi repository dan database production wajib diaudit kembali sebelum setiap phase implementasi.

## 2. Ringkasan keputusan

1. Shelf Life menjadi data master lokal per WIP, bukan per Menu.
2. Identitas utama WIP adalah `company_code + esb_product_detail_id`.
3. BOM Adjustment menjadi workspace utama pengelolaan master Shelf Life WIP.
4. Saat WIP ditemukan di dalam Project, sistem otomatis membaca master yang sudah tersedia.
5. Jika master belum tersedia, pengguna berizin dapat mengisinya langsung dari halaman Product/BOM di dalam Project.
6. Input dari Project dan BOM Adjustment harus menulis record master yang sama.
7. Project tidak mempunyai override Shelf Life sendiri pada MVP.
8. Form, ringkasan, validasi, dan persistence Shelf Life untuk Menu/final Product dihapus dari UI.
9. Menu `Shelf Life` dan `Master Shelf Life Menu` dihapus.
10. Halaman, Resource, export, route, permission, dan consumer lama dipensiunkan secara bertahap setelah characterization test membuktikan aman.
11. Data Shelf Life Menu lama tidak otomatis dianggap sebagai data WIP.
12. Perubahan Shelf Life bersifat lokal dan tidak boleh ikut dalam payload mutation BOM ke ESB.
13. Tabel existing `rnd_esb_product_shelf_lives` direuse agar tidak terbentuk dua master yang menyimpan data serupa.

## 3. Latar belakang

Implementasi saat ini mempunyai dua konsep Shelf Life yang terpisah:

1. `rnd_project_products` menyimpan Shelf Life pada final Product/Menu di dalam Project. Data dikelola dari form Product dan diwajibkan sebelum status `Ready` atau `Released`.
2. `rnd_esb_product_shelf_lives` menjadi Master Shelf Life Menu lokal. Lookup utamanya memakai `company_code + esb_menu_id` dan dipakai oleh workflow Internal Memo lama.

Kedua konsep tersebut tidak sesuai dengan kebutuhan aktual karena Shelf Life ditentukan pada item WIP yang dihasilkan oleh BOM Assembly. Satu WIP dapat digunakan oleh beberapa Menu dan beberapa Project. Menyimpan Shelf Life pada Menu menghasilkan duplikasi, nilai yang berpotensi berbeda, dan proses pemeliharaan yang tersebar.

BOM Adjustment sudah mempunyai katalog lokal `rnd_bom_catalogs` dengan metadata Product Result, termasuk `product_detail_id`, kode, nama, unit, snapshot, status aktif, dan waktu sinkronisasi. Oleh karena itu, BOM Adjustment merupakan tempat yang tepat untuk mengelola master lokal yang melekat pada output WIP.

## 4. Temuan baseline repository

### 4.1 Shelf Life Product/Project

- Field Shelf Life berada pada `rnd_project_products`.
- Form create/edit Product berada pada `ViewProject`.
- Status `Ready` dan `Released` saat ini memerlukan Shelf Life Menu.
- Halaman `ShelfLifePage` membaca dan mengubah record `RndProjectProduct`.
- Export Shelf Life lama juga membaca `RndProjectProduct`.

### 4.2 Master Shelf Life Menu

- Model existing: `RndProductEsbShelfLife`.
- Tabel existing: `rnd_esb_product_shelf_lives`.
- Lookup aktif menggunakan `forMenu(companyCode, esbMenuId)`.
- Resource Filament mempunyai halaman list, create, dan edit tersendiri.
- Permission existing: `manage rnd product shelf life`.

### 4.3 BOM Adjustment

- Katalog lokal memiliki 1.009 row pada database development saat audit.
- Seluruh row katalog mempunyai `esb_bom_id`; 1.006 row mempunyai `product_detail_id`.
- Index menggunakan pagination database dan tidak perlu mengambil seluruh katalog ESB pada setiap render.
- Perubahan resep sudah melalui `UpdateEsbBillOfMaterialAction`.
- Katalog hanya menyimpan BOM Assembly/WIP yang sudah disinkronkan.

### 4.4 Project dan penemuan WIP

- Form create Project hanya membuat header berupa nama, deskripsi, tanggal mulai, dan tanggal selesai.
- WIP belum diketahui ketika header Project dibuat.
- WIP baru diketahui setelah Main Recipe/BOM dipasang dan mapping komponen WIP dijalankan pada halaman Product Release/BOM.
- Hasil mapping sudah membawa `productDetailID`, sehingga lookup master tidak memerlukan API tambahan.

Konsekuensinya, istilah “tarik Shelf Life saat create Project” pada implementasi berarti: tarik otomatis ketika BOM/WIP pertama kali dipasang, dipetakan, atau dimuat di dalam Project, bukan ketika header Project disimpan.

### 4.5 Internal Memo

- `AddMenuToInternalMemoAction` masih membaca Master Shelf Life Menu.
- `InternalMemoValidationService` masih mempunyai blocker Shelf Life Menu.
- UI Internal Memo yang disederhanakan tidak lagi menampilkan input Shelf Life.
- Kolom historis dan workflow lama masih tersedia untuk kompatibilitas.

Dependency ini wajib dipindahkan atau dinonaktifkan sebelum Resource Master Shelf Life Menu lama dihapus.

## 5. Masalah yang diselesaikan

1. Shelf Life tersimpan pada entity yang salah, yaitu Menu/final Product.
2. WIP yang sama dapat mempunyai nilai Shelf Life berbeda pada Project berbeda.
3. Pengguna harus membuka halaman Shelf Life terpisah untuk melengkapi data.
4. Terdapat dua menu Shelf Life dengan arti yang tumpang tindih.
5. Satuan Shelf Life tidak konsisten: Project memakai `day/week/month/year`, sedangkan master lama memakai `jam/hari/minggu/bulan`.
6. Internal Memo masih bergantung pada master Menu yang akan dipensiunkan.
7. Menghapus halaman lama secara langsung berisiko merusak route, permission, export, dan workflow tersembunyi.
8. Lookup per baris pada katalog BOM berisiko menghasilkan N+1 jika relasi baru tidak didesain untuk bulk loading.

## 6. Tujuan

- Menetapkan satu sumber kebenaran Shelf Life untuk setiap WIP.
- Menempatkan pengelolaan master pada BOM Adjustment tanpa membuat submenu baru.
- Menarik nilai master secara otomatis ke Project berdasarkan Product Detail ID.
- Memungkinkan pengisian master yang belum tersedia tanpa meninggalkan konteks Project.
- Menghapus seluruh input dan ketergantungan aktif Shelf Life Menu.
- Menjaga data historis sampai migration cleanup terpisah terbukti aman.
- Menjaga mutation BOM ESB tetap terpisah dari perubahan metadata lokal.
- Menyediakan authorization backend, audit pengguna, validasi, dan test yang jelas.
- Menjaga UI sederhana, responsif, mudah dipahami, dan konsisten dengan halaman R&D existing.

## 7. Non-tujuan

- Mengirim Shelf Life ke ESB.
- Mengubah formula BOM, HPP, forecast, atau harga WIP.
- Menambah submenu Shelf Life baru.
- Membuat override Shelf Life per Project pada MVP.
- Membuat histori versi Shelf Life yang kompleks.
- Mengubah seluruh flow Internal Memo selain dependency Shelf Life yang terdampak.
- Mengubah kontrak API ESB atau menebak field ESB yang belum terbukti.
- Menghapus kolom/tabel historis pada deployment pertama.
- Menambahkan dependency baru.
- Mendukung multi-company BOM Adjustment sebelum company context pada workspace tersebut terbukti.
- Membuat algoritma rekursi BOM baru khusus Shelf Life; fitur memakai hasil resolver/mapping WIP existing.

## 8. Istilah domain

| Istilah | Definisi |
| --- | --- |
| WIP | Product Result dari BOM Assembly yang digunakan sebagai bahan antara atau resep dasar |
| Master Shelf Life WIP | Record lokal tunggal yang menyimpan masa simpan dan kondisi penyimpanan suatu WIP |
| Product Detail ID | Identifier unit/detail produk dari ESB yang menjadi identitas teknis utama WIP |
| BOM Catalog | Snapshot lokal BOM Assembly pada `rnd_bom_catalogs` |
| Menu/Final Product | Produk akhir di `rnd_project_products`; bukan pemilik master Shelf Life baru |
| Missing Shelf Life | WIP valid telah ditemukan tetapi belum mempunyai master aktif |
| Unresolved WIP | Komponen diduga WIP tetapi Product Detail ID atau BOM turunannya tidak dapat dipastikan |

## 9. Keputusan bisnis yang dikunci

### 9.1 Granularitas

Satu master berlaku untuk satu kombinasi:

```text
company_code + esb_product_detail_id
```

Kode dan nama produk hanya snapshot tampilan dan bukan identity utama. Product Detail ID dipilih karena field tersebut sudah terbukti tersedia pada Product Result BOM dan hasil mapping Project.

Master disimpan pada tabel existing `rnd_esb_product_shelf_lives`. Record WIP selalu mempunyai `esb_product_detail_id`, sedangkan record legacy yang hanya mempunyai `esb_menu_id` tidak pernah ikut dalam lookup WIP.

### 9.2 Company context MVP

BOM Adjustment saat ini belum mempunyai company selector. MVP memakai `BLSS`, mengikuti context existing yang digunakan master R&D lokal. Multi-company hanya boleh ditambahkan setelah sumber company untuk setiap katalog BOM terbukti dan diuji.

### 9.3 Sumber kebenaran

Project membaca master langsung. Project tidak menyimpan salinan atau override Shelf Life pada MVP.

Akibatnya:

- perubahan master di BOM Adjustment langsung terlihat pada Project;
- input master dari Project langsung terlihat pada BOM Adjustment;
- tidak ada sinkronisasi dua arah atau konflik data antar-Project.

### 9.4 Kelengkapan Project

- Project dan Product boleh dibuat dalam status awal walaupun master WIP belum lengkap.
- Status `Draft`, `Development`, dan `Trial` tidak diblokir oleh Shelf Life.
- Sebelum Product menjadi `Ready` atau `Released`, seluruh WIP yang berhasil ditemukan harus mempunyai master aktif.
- Product tanpa WIP tidak diblokir oleh aturan ini.
- Unresolved WIP tidak dianggap lengkap secara diam-diam; tampilkan blocker atau warning yang menyebut identitas komponen terkait.

### 9.5 Editing dari Project

- Project hanya menawarkan input jika master belum tersedia.
- Master yang sudah tersedia ditampilkan read-only pada Project.
- Perubahan master existing dilakukan dari BOM Adjustment agar ownership master tetap jelas.
- Pengguna tanpa izin mengubah BOM/master hanya melihat status dan nilai.

### 9.6 Lifecycle

- Master tidak dihapus secara hard delete dari UI.
- Master dapat diaktifkan atau dinonaktifkan.
- Record yang dinonaktifkan dianggap `Belum Diisi` oleh resolver Project.
- Pembaruan mencatat `updated_by` dan aktivitas perubahan.

### 9.7 Kedalaman WIP

- Scope mencakup seluruh WIP direct dan nested yang ditemukan oleh resolver/mapping BOM existing.
- Product Detail ID dideduplikasi sebelum lookup master.
- Cycle dan batas kedalaman mengikuti guard resolver BOM existing.
- Fitur Shelf Life tidak menjalankan traversal rekursif kedua yang berpotensi menghasilkan hasil berbeda.

## 10. Alur pengguna target

### 10.1 Mengelola master dari BOM Adjustment

1. Pengguna membuka `Research & Development → BOM Adjustment`.
2. Sistem menampilkan BOM Assembly dengan status Shelf Life.
3. Pengguna mencari WIP berdasarkan kode atau nama.
4. Pengguna menekan `Isi Shelf Life` atau `Edit Shelf Life`.
5. Modal menampilkan identitas WIP dan field Shelf Life.
6. Sistem memvalidasi input dan authorization di server.
7. Sistem menyimpan master lokal tanpa mutation ESB.
8. Tabel diperbarui dan menampilkan status `Lengkap`.

### 10.2 Menarik master ke Project

1. Pengguna membuat atau membuka Product di dalam Project.
2. Pengguna memasang Main Recipe atau mapping WIP dijalankan.
3. Sistem mengumpulkan seluruh Product Detail ID WIP yang ditemukan.
4. Sistem mengambil master dalam satu query bulk.
5. WIP yang mempunyai master menampilkan nilai Shelf Life dan kondisi penyimpanan.
6. WIP tanpa master menampilkan status `Shelf Life Belum Diisi`.

### 10.3 Mengisi master yang hilang dari Project

1. Pengguna menekan `Isi Shelf Life` pada WIP yang belum lengkap.
2. Modal sederhana menampilkan identitas WIP read-only.
3. Pengguna mengisi nilai, satuan, kondisi, dan catatan opsional.
4. Sistem memastikan pengguna berhak mengubah Project dan master BOM.
5. `CreateWipShelfLifeAction` membuat master global dan menolak overwrite master aktif yang sudah tersedia.
6. Section WIP Project diperbarui tanpa membuat override Project.

### 10.4 Mengubah master existing

1. Pengguna membuka BOM Adjustment.
2. Pengguna mencari WIP.
3. Pengguna membuka modal edit.
4. Setelah disimpan, seluruh Project membaca nilai terbaru.

## 11. Arsitektur target

```text
BomAdjustmentPage / ViewProjectProductPage
    → authorization + presentation validation
    → CreateWipShelfLifeAction / UpdateWipShelfLifeAction
        → transaction + uniqueness handling
        → RndProductEsbShelfLife
        → activity audit

WIP mapping / Project rendering
    → WipShelfLifeResolver
        → bulk lookup by company + Product Detail IDs
        → keyed result for presentation and readiness validation
```

Aturan tanggung jawab:

| Lapisan | Tanggung jawab |
| --- | --- |
| Livewire/Filament Page | State modal, validasi presentasi, feedback, pemanggilan Action |
| Action | Authorization bisnis, transaction, create/update, audit actor |
| Resolver | Bulk lookup dan normalisasi hasil master |
| Model | Relation, cast, scope aktif, activity configuration |
| Blade | Presentasi saja; tanpa query atau mutation |
| ESB service | Tidak berubah oleh fitur Shelf Life |

Page tidak boleh membangun query master per baris, menulis langsung ke model dari Blade, atau mencampur save Shelf Life dengan submit perubahan BOM ESB.

## 12. Model data

### 12.1 Tabel existing `rnd_esb_product_shelf_lives`

Tabel existing direuse karena sudah memiliki identity Product ESB, nilai/unit Shelf Life, kondisi penyimpanan, effective date, status aktif, audit user, dan soft delete. Membuat tabel baru akan menghasilkan dua sumber data yang hampir identik.

| Kolom | Tipe konseptual | Aturan |
| --- | --- | --- |
| `id` | bigint | Primary key |
| `company_code` | varchar(10) | Wajib; default aplikasi MVP `BLSS` |
| `esb_product_id` | unsigned bigint nullable | Diisi hanya jika field terbukti tersedia |
| `esb_product_detail_id` | unsigned bigint nullable | Nullable untuk kompatibilitas row Menu legacy; wajib pada setiap record WIP baru |
| `product_code` | varchar nullable | Snapshot display |
| `product_name` | varchar | Snapshot display |
| `shelf_life_value` | decimal(10,2) | Wajib; lebih besar dari nol |
| `shelf_life_unit` | varchar(20) | `hour`, `day`, `week`, `month`, atau `year` |
| `storage_condition` | varchar | Dibatasi aplikasi ke `dry`, `chiller`, atau `frozen` untuk record WIP |
| `notes` | text nullable | Detail suhu atau instruksi penyimpanan |
| `is_active` | boolean | Default `true` |
| `created_by` | foreign id nullable | `users`, `nullOnDelete` |
| `updated_by` | foreign id nullable | `users`, `nullOnDelete` |
| timestamps | timestamps | Audit waktu |
| `deleted_at` | soft delete | Dipertahankan dari schema existing |

Kolom legacy berikut dipertahankan untuk histori/transisi tetapi tidak dipakai consumer WIP baru:

- `esb_menu_id`;
- `effective_from`;
- `effective_until`.

### 12.2 Constraint dan index

- Unique: `company_code, esb_product_detail_id` setelah audit dan pembersihan duplicate.
- Index: `company_code, is_active`.
- Index tambahan hanya berdasarkan query plan aktual.
- Tidak memakai foreign key ke `rnd_bom_catalogs`; master harus tetap valid ketika katalog disinkronkan ulang.
- Karena tabel memakai soft delete, create untuk identity yang pernah dihapus harus merestore dan memperbarui row lama, bukan membuat row baru yang melanggar unique constraint.

### 12.3 Model

Model existing `RndProductEsbShelfLife` tetap digunakan dan semantiknya diperluas menjadi master Shelf Life Product Detail/WIP. Method `forMenu()` dipensiunkan setelah consumer lama berpindah. Tambahkan resolver/query berdasarkan Product Detail ID, bukan membuat model wrapper kedua.

Model wajib mempunyai:

- `$fillable` eksplisit;
- cast decimal dan boolean;
- relation `creator` dan `updater`;
- scope aktif;
- factory;
- activity log hanya untuk field bisnis penting;
- helper label satuan melalui Enum, bukan string yang tersebar.

### 12.4 Enum satuan

Gunakan key internal konsisten:

| Key | Label UI |
| --- | --- |
| `hour` | Jam |
| `day` | Hari |
| `week` | Minggu |
| `month` | Bulan |
| `year` | Tahun |

Nilai lama `jam/hari/minggu/bulan` tidak ditulis lagi ke master baru.

### 12.5 Kondisi penyimpanan

| Key | Label UI |
| --- | --- |
| `dry` | Dry |
| `chiller` | Chiller |
| `frozen` | Frozen |

Detail seperti `2–5°C` atau `-18°C` disimpan pada `notes`, bukan dibuat menjadi key enum baru tanpa requirement.

## 13. Identity dan resolusi WIP

### 13.1 Sumber identity

Urutan sumber identity:

1. `productDetailID` dari Product Result BOM detail.
2. `product_detail_id` pada `rnd_bom_catalogs`.
3. `productDetailID` dari hasil mapping WIP Project.

Kode produk tidak boleh menjadi identity utama karena dapat diubah atau tidak unik lintas company.

### 13.2 WIP tanpa Product Detail ID

Jika WIP tidak mempunyai Product Detail ID:

- jangan membuat master berbasis nama;
- tampilkan status `Identitas WIP Belum Lengkap`;
- blokir direct save;
- catat informasi kode, nama, BOM, dan jalur mapping untuk troubleshooting;
- jangan melakukan request ESB tambahan dari render UI.

### 13.3 Bulk resolution

Resolver menerima:

```text
company_code
list<Product Detail ID>
```

Resolver mengembalikan collection yang di-key oleh Product Detail ID. Duplicate ID di input harus dinormalisasi sebelum query.

## 14. Action bisnis

### 14.1 `CreateWipShelfLifeAction`

Input minimum:

- company code;
- Product Detail ID;
- snapshot kode/nama WIP;
- value;
- unit;
- storage condition;
- notes;
- actor.

Alur:

1. Authorize actor untuk source context terkait.
2. Pastikan identity WIP valid dan berasal dari katalog/Project yang dapat diakses.
3. Validasi value, unit, kondisi, dan notes.
4. Buka database transaction.
5. Cari record dengan unique identity dan gunakan lock yang sesuai bila perlu.
6. Tolak overwrite jika master aktif sudah tersedia.
7. Jika identity mempunyai row soft-deleted, restore dan isi ulang row tersebut.
8. Create record jika belum pernah tersedia.
9. Isi `created_by` dan `updated_by` sesuai actor.
10. Commit.
11. Kembalikan model terbaru.

Action ini digunakan BOM Adjustment dan Project untuk membuat master yang belum tersedia.

### 14.2 `UpdateWipShelfLifeAction`

Action ini hanya digunakan BOM Adjustment untuk:

- memperbarui master existing;
- mengaktifkan kembali master inactive;
- menonaktifkan master;
- mencatat `updated_by` dan activity diff.

Project tidak memanggil Action update pada MVP.

Alur update:

1. Authorize actor.
2. Resolve record berdasarkan identity server-side.
3. Validasi input.
4. Lock record di dalam transaction.
5. Update hanya field allowlist Shelf Life.
6. Commit.
7. Kembalikan model terbaru.

Unique constraint tetap menjadi perlindungan terakhir terhadap duplicate submit atau race condition.

### 14.3 Source context
Create Action harus mengetahui apakah dipanggil dari:

- BOM Adjustment; atau
- Project.

Source context dipakai untuk authorization, bukan untuk membuat dua jenis record master.

### 14.4 Deactivate

Deactivate hanya tersedia dari BOM Adjustment. Project tidak menyediakan aksi deactivate. Deactivate memerlukan konfirmasi dan tidak menghapus histori aktivitas.

## 15. Authorization

### 15.1 BOM Adjustment

| Operasi | Permission |
| --- | --- |
| Melihat nilai/status Shelf Life | `view bill of materials` |
| Membuat atau mengubah master | `edit bill of materials` |
| Menonaktifkan master | `edit bill of materials` |

### 15.2 Project

| Operasi | Syarat |
| --- | --- |
| Melihat nilai WIP | Dapat melihat Project terkait |
| Mengisi master yang belum tersedia | `edit rnd projects` dan `edit bill of materials` |
| Mengubah master existing | Tidak tersedia dari Project pada MVP |

Authorization wajib ditegakkan di backend. Menyembunyikan tombol bukan kontrol keamanan yang cukup. Direct Livewire invocation dengan Product Detail ID yang dimanipulasi harus ditolak.

### 15.3 Permission lama

Permission berikut dipensiunkan setelah seluruh reference hilang:

- `view shelf life`;
- `edit shelf life`;
- `manage rnd product shelf life`.

Permission tidak langsung dihapus dari database pada phase UI. Urutan aman:

1. pindahkan seluruh consumer;
2. perbarui role assignment;
3. jalankan regression test;
4. masukkan permission ke mekanisme deprecated permission;
5. hapus record permission hanya melalui proses sinkronisasi resmi.

### 15.4 Dampak role Design

- Akses khusus role Design ke daftar `Shelf Life` lama berakhir ketika halaman tersebut dipensiunkan.
- Role Design tidak otomatis memperoleh `view bill of materials` atau `edit bill of materials` sebagai pengganti.
- Pengguna Design tetap dapat melihat nilai/status Shelf Life WIP secara read-only di Project yang memang dapat mereka akses.
- Setiap perubahan assignment role harus melalui audit kebutuhan bisnis dan test permission; rollout ini tidak boleh memperluas hak akses secara diam-diam.

## 16. UI BOM Adjustment

### 16.1 Daftar

Tambahkan kolom:

| Kolom | Isi |
| --- | --- |
| Shelf Life | Nilai dan satuan atau `Belum Diisi` |
| Storage | Kondisi penyimpanan atau `—` |
| Status Data | `Lengkap`, `Belum Diisi`, `Tidak Aktif`, atau `Identitas Tidak Lengkap` |

Tambahkan filter:

- Semua;
- Lengkap;
- Belum Diisi;
- Tidak Aktif.

Status menggunakan teks dan warna:

- green/emerald untuk `Lengkap`;
- amber untuk `Belum Diisi`;
- gray untuk `Tidak Aktif`;
- red untuk identity/error yang tidak dapat diproses.

### 16.2 Aksi baris

- `Isi Shelf Life` jika master belum tersedia.
- `Edit Shelf Life` jika master tersedia dan pengguna berizin.
- Tidak ada aksi jika Product Detail ID tidak tersedia.

### 16.3 Modal

Modal mengikuti pola R&D existing:

- title dan description singkat;
- identity WIP read-only;
- satu kolom pada mobile;
- dua kolom hanya pada viewport yang cukup;
- error dekat field;
- tombol submit mempunyai loading state dan perlindungan double submit;
- Escape, tombol tutup, backdrop, focus trap, dan focus return mengikuti `docs/ui-consistency-prd.md`;
- validasi gagal mempertahankan input dan modal tetap terbuka.

### 16.4 Pemisahan dari mutation BOM

Form Shelf Life tidak diletakkan di dalam preview `Konfirmasi & Kirim ke ESB`. Notification harus menyebut bahwa data Shelf Life tersimpan lokal.

## 17. UI Project

### 17.1 Lokasi

Section ditempatkan pada halaman detail Product/BOM Project, dekat hasil mapping WIP. Jangan menambah seluruh logic ke `ViewProject` yang sudah besar.

### 17.2 Tampilan setiap WIP

Setiap item menampilkan:

- kode dan nama WIP;
- unit Product Detail;
- BOM/jalur asal bila relevan;
- nilai Shelf Life;
- kondisi penyimpanan;
- catatan singkat;
- status kelengkapan.

### 17.3 State

| State | Perilaku |
| --- | --- |
| Loading mapping | Tampilkan indikator pada area WIP; jangan dianggap kosong |
| Master tersedia | Tampilkan nilai read-only |
| Master belum tersedia | Badge amber dan tombol isi bila diizinkan |
| Tidak berizin | Tampilkan status tanpa tombol mutation |
| Identity tidak lengkap | Tampilkan error informatif tanpa tombol save |
| Tidak ada WIP | Empty state netral; tidak menjadi blocker Shelf Life |
| Save berhasil | Refresh hanya area terkait dan tampilkan notification |

### 17.4 Mobile

- Item WIP menggunakan card/stack, bukan tabel lebar yang memaksa page scroll horizontal.
- Aksi tetap terlihat dan tidak hanya tersedia melalui hover.
- Nama panjang boleh wrap.
- Modal dibatasi tinggi viewport dengan body scroll.

## 18. Penghapusan Shelf Life Menu dari Project

Hapus dari form final Product/Menu:

- nilai Shelf Life;
- unit Shelf Life;
- kondisi penyimpanan;
- catatan penyimpanan;
- section `Shelf Life & Storage`;
- summary card Shelf Life;
- payload create/update Product;
- reset state terkait;
- validasi Shelf Life Menu pada status `Ready` atau `Released`.

Ganti validation gate `Ready/Released` dengan pemeriksaan kelengkapan master seluruh WIP yang sudah terdeteksi.

Kolom berikut pada `rnd_project_products` dipertahankan sementara sebagai legacy read-only database fields:

- `shelf_life_value`;
- `shelf_life_unit`;
- `storage_condition`;
- `storage_notes`.

Kolom tidak boleh dihapus pada migration yang sama dengan perubahan behavior. Penghapusan fisik memerlukan audit production, backup, dan approval terpisah.

## 19. Internal Memo

### 19.1 Behavior baru

- `AddMenuToInternalMemoAction` tidak lagi mencari Shelf Life berdasarkan `esb_menu_id`.
- Internal Memo yang disederhanakan tidak meminta atau memvalidasi Shelf Life Menu.
- `InternalMemoValidationService` tidak lagi menambahkan blocker `Shelf Life Menu belum diisi`.
- Tidak ada input Shelf Life baru pada UI Internal Memo dalam scope ini.

### 19.2 Data historis

- Kolom Shelf Life pada menu memo lama dipertahankan.
- Memo historis/finalized tetap menampilkan atau mengekspor snapshot lamanya jika export lama masih memakai field tersebut.
- Data historis tidak ditimpa oleh master WIP.
- Tidak ada backfill otomatis dari Shelf Life Menu lama ke WIP.

### 19.3 Pengembangan lanjutan

Jika Internal Memo kembali membutuhkan Shelf Life, sumbernya adalah master WIP untuk setiap material WIP. Requirement tersebut bukan bagian MVP dan memerlukan keputusan tampilan/export terpisah.

### 19.4 Dokumentasi yang disupersede

PRD ini menggantikan requirement Shelf Life Menu pada `docs/rnd-internal-memo-prd.md`, khususnya §7.3, §11, aturan blocker/finalization, dan kontrak PDF/export yang bergantung pada Shelf Life Menu. Bagian Internal Memo lain tetap berlaku.

Item `Shelf Life dari WIP` dan `All Shelf Life Memo` pada `docs/rnd-development-todo.csv` harus ditandai superseded atau dirumuskan ulang ketika implementasi dimulai. Formula “Shelf Life Menu berdasarkan WIP/bahan dengan masa simpan paling pendek” tidak boleh diimplementasikan secara implisit karena MVP ini menghapus Shelf Life Menu dan tidak melakukan agregasi menjadi satu nilai Menu.

## 20. Navigation, route, dan fitur lama

### 20.1 Navigation yang dihapus

```text
Research & Development
├── Shelf Life
└── Master Shelf Life Menu
```

BOM Adjustment tetap menjadi satu-satunya entry navigation untuk master Shelf Life WIP.

### 20.2 Kandidat yang dipensiunkan

- `ShelfLifePage`;
- view `shelf-life-page`;
- `ShelfLifeExportController`;
- route `helpdesk.exports.shelf-life`;
- `RndProductEsbShelfLifeResource`;
- halaman create/edit/list Resource;
- schema dan table Resource lama;
- test yang hanya mengunci behavior Shelf Life Menu lama.

Penghapusan dilakukan setelah audit seluruh reference PHP, Blade, route, navigation, permission, test, Filament discovery, dan string-based route call.

### 20.3 Route lama

Navigation lama dihapus ketika pengganti di BOM Adjustment tersedia. Untuk satu compatibility release, entry URL Page/Resource lama diarahkan ke BOM Adjustment dengan filter Shelf Life yang paling relevan tanpa membawa identity Menu sebagai identity WIP. Redirect harus mempertahankan authorization tujuan dan mencatat penggunaan route lama.

Route lama baru di-unregister setelah telemetry dan audit string reference menunjukkan tidak ada consumer aktif. Setelah itu bookmark lama mendapat 404 sesuai behavior aplikasi. Redirect tidak boleh dipertahankan jika menimbulkan makna data yang menyesatkan.

### 20.4 Export lama

Export Shelf Life Product/Menu dipensiunkan tanpa replacement pada MVP. Jika dibutuhkan export WIP, requirement baru harus mendefinisikan kolom, filter, authorization, dan formatnya.

Route export lama tidak diarahkan ke halaman BOM Adjustment. Pada compatibility release, request diberi respons retirement yang jelas sesuai konvensi aplikasi; route kemudian dihapus bersama route legacy lain setelah audit consumer selesai.

## 21. Data migration

### 21.1 Prinsip

- Migration schema bersifat additive dan reversible.
- Jangan mengubah migration lama yang sudah pernah dijalankan.
- Jangan mencampur schema creation dan data backfill dalam migration yang sama.
- Jangan mengubah data production berdasarkan tebakan nama/kode.
- Perubahan DDL dan backfill DML dipisahkan agar dapat diaudit dan di-rollback secara independen.

### 21.2 Audit sebelum constraint

Sebelum unique constraint ditambahkan, audit seluruh row `rnd_esb_product_shelf_lives` untuk:

- duplicate `company_code + esb_product_detail_id`;
- `esb_product_detail_id` null;
- row legacy yang hanya mempunyai `esb_menu_id`;
- row yang mempunyai identity Menu dan Product Detail sekaligus;
- unit lama/free-text di luar `hour/day/week/month/year`;
- row soft-deleted yang berbagi identity dengan row aktif;
- Product Detail ID yang tidak dapat divalidasi sebagai Product Result WIP.

Hasil audit harus berupa report dry-run. Data ambigu tidak boleh dinormalisasi atau dihapus otomatis.

### 21.3 Master Menu lama

Record legacy Menu pada `rnd_esb_product_shelf_lives` tidak otomatis dianggap sebagai WIP karena:

- identity existing dapat hanya mempunyai `esb_menu_id`;
- Menu bukan WIP;
- satu Menu dapat memakai beberapa WIP;
- Shelf Life Menu tidak membuktikan Shelf Life setiap WIP.

Jika production mempunyai record dengan `esb_product_detail_id` yang terbukti merupakan Product Result WIP, row dapat dinormalisasi melalui command/backfill terpisah dengan dry-run report dan persetujuan pengguna. Normalisasi unit harus memakai mapping eksplisit; nilai asing tetap dilaporkan sebagai exception.

### 21.4 Shelf Life Project lama

Nilai pada `rnd_project_products` tidak otomatis dimigrasikan ke WIP karena satu final Product dapat memakai lebih dari satu WIP. Data tetap disimpan selama masa transisi.

### 21.5 Penambahan constraint

Setelah duplicate dan identity conflict diselesaikan:

1. tambahkan unique constraint `company_code + esb_product_detail_id`;
2. pertahankan nullable pada schema selama row Menu legacy masih berada di tabel, tetapi wajibkan Product Detail ID melalui validasi domain untuk setiap record WIP baru;
3. pastikan create menemukan row soft-deleted dengan `withTrashed()`, lalu restore/update row tersebut;
4. verifikasi query plan resolver dan filter BOM Adjustment.

### 21.6 Baseline development

Saat audit database development:

- `rnd_esb_product_shelf_lives`: 0 record;
- `rnd_project_products`: 6 record, 1 mempunyai Shelf Life;
- `rnd_bom_catalogs`: 1.009 record, 1.006 mempunyai Product Detail ID;
- `rnd_project_boms`: 14 record, seluruhnya mempunyai Product Detail ID pada snapshot.

Angka ini bukan jaminan kondisi production. Pre-deployment audit wajib diulang.

## 22. Query dan performa

### 22.1 BOM Adjustment

- Pagination tetap database-driven.
- Shelf Life dimuat melalui eager load atau bulk map.
- Filter lengkap/belum lengkap dilakukan pada query database.
- Jangan memanggil resolver di dalam loop Blade.
- Jangan melakukan request ESB untuk setiap baris Shelf Life.

### 22.2 Project

- Kumpulkan unique Product Detail ID sebelum query.
- Satu query master untuk seluruh WIP pada satu Product/Project view.
- Reuse hasil resolver dalam request/render yang sama.
- Jangan menyimpan seluruh katalog BOM dalam public Livewire state.

### 22.3 Baseline pengukuran

Catat sebelum/sesudah:

- jumlah query pada BOM Adjustment index 20 row;
- duplicate query;
- ukuran snapshot Livewire modal;
- render time section WIP Project;
- jumlah request ESB saat membuka halaman.

Target: fitur Shelf Life tidak menambah request ESB ketika hanya membaca master lokal dan tidak menghasilkan query per row.

## 23. Security dan audit

- Setiap read mengikuti akses Project/BOM existing.
- Setiap mutation melakukan authorization backend.
- Product Detail ID dari Livewire state diverifikasi terhadap katalog atau WIP Project yang dapat diakses.
- Jangan menerima `company_code`, nama, atau kode sebagai identity tepercaya dari browser tanpa validasi server.
- Output Blade menggunakan escaping default.
- Exception teknis, credential, dan payload ESB tidak ditampilkan pada notification.
- Activity log mencatat before/after hanya untuk field Shelf Life yang relevan.
- Actor dan source context tersedia dalam audit.
- Tidak ada mutation ESB akibat penyimpanan Shelf Life.

## 24. Standar UI

Implementasi mengikuti `docs/ui-consistency-prd.md`:

- container `bg-white`, border abu, `rounded-2xl`, tanpa shadow biasa;
- section menggunakan `space-y-6`/`gap-6` dan padding existing;
- aksi utama biru, aksi sekunder netral, deactivate merah/konfirmasi;
- Heroicons existing, tanpa SVG manual;
- label field terlihat dan helper ditempatkan dekat field;
- loading tidak dianggap empty state;
- status tidak hanya dibedakan dengan warna;
- tidak ada scroll horizontal pada keseluruhan halaman;
- dark mode mengikuti panel existing;
- modal dapat digunakan dengan keyboard dan zoom 200%;
- tidak menambah custom CSS/JavaScript bila komponen existing cukup.

## 25. Error handling

| Kondisi | Respons |
| --- | --- |
| Master duplicate karena race | Ambil record existing atau tampilkan error aman; jangan membuat dua record |
| Product Detail ID tidak valid | Inline error dan mutation dibatalkan |
| WIP tidak lagi ada pada Project | Tolak save dari Project |
| Pengguna tidak berizin | 403 backend |
| Database save gagal | Modal tetap terbuka, input dipertahankan, notification aman |
| ESB sedang gagal | Read/write master lokal tetap tersedia jika identity katalog lokal valid |
| Master inactive | Tampilkan `Tidak Aktif`; Project memperlakukannya sebagai belum lengkap |
| WIP mapping gagal | Tampilkan warning spesifik dan jangan menebak master dari nama |

## 26. Strategi test

Semua test menggunakan Pest dan factory. Test lama tidak dihapus hanya untuk membuat suite hijau; behavior lama yang dipensiunkan diganti dengan regression test retirement.

### 26.1 Model dan migration

- Model dapat dibuat melalui factory.
- Cast value dan active benar.
- Unique company + Product Detail ID berlaku.
- Company berbeda dapat memakai Product Detail ID yang sama.
- Row Menu legacy dengan Product Detail ID null tidak ikut lookup WIP.
- Row soft-deleted dengan identity WIP yang sama direstore, bukan diduplikasi.
- Mapper satuan lama mengubah hanya nilai yang terdaftar dan melaporkan nilai asing.
- Relation creator/updater benar.
- Migration dapat rollback pada test environment.

### 26.2 Action dan resolver

- Create Action membuat master baru.
- Create Action menolak overwrite master aktif yang sudah tersedia.
- Update Action memperbarui master existing, bukan membuat duplicate.
- Duplicate submission tetap menghasilkan satu record.
- Value nol/negatif ditolak.
- Unit dan storage condition tidak valid ditolak.
- Resolver hanya mengembalikan master aktif.
- Resolver melakukan bulk lookup untuk ID unik.
- Resolver mengisolasi hasil berdasarkan company.
- Product Detail ID yang dimanipulasi ditolak.

### 26.3 BOM Adjustment

- Viewer melihat status Shelf Life.
- Editor dapat create/edit/deactivate.
- Viewer tidak dapat memanggil mutation langsung.
- Filter lengkap/belum lengkap bekerja dengan pagination.
- Identity yang tidak lengkap tidak mempunyai tombol save.
- Save Shelf Life tidak memanggil HTTP ESB.
- Update BOM ESB tidak membawa field Shelf Life.
- Sinkronisasi ulang katalog BOM tidak menimpa master Shelf Life.
- Query index tidak mengalami N+1.

### 26.4 Project

- WIP yang ditemukan otomatis menampilkan master.
- WIP tanpa master menampilkan tombol isi untuk pengguna berizin.
- Input dari Project membuat master global yang sama.
- Pengguna tanpa `edit bill of materials` ditolak.
- Master existing tidak dapat diedit dari Project.
- Product tanpa WIP tidak diblokir.
- Product dengan master WIP lengkap dapat `Ready/Released`.
- Product dengan WIP missing atau unresolved ditolak sesuai pesan.
- WIP direct dan nested menggunakan hasil resolver BOM existing, dideduplikasi, serta mematuhi cycle/depth guard existing.
- Perubahan master BOM Adjustment langsung terbaca pada Project.

### 26.5 Menu/final Product cleanup

- Form Product tidak menampilkan Shelf Life Menu.
- Save Product tidak menulis field Shelf Life legacy.
- Status awal tidak meminta Shelf Life Menu.
- Gate `Ready/Released` memakai kelengkapan WIP.

### 26.6 Internal Memo

- Add Menu tidak melakukan lookup Master Shelf Life Menu.
- Validator tidak mempunyai blocker Shelf Life Menu.
- Memo existing dengan snapshot lama tetap dapat dibuka.
- Export historis, jika masih aktif, tetap membaca snapshot lama.

### 26.7 Navigation dan route

- Sidebar tidak menampilkan `Shelf Life`.
- Sidebar tidak menampilkan `Master Shelf Life Menu`.
- Route export dan Resource lama tidak terdaftar setelah retirement.
- BOM Adjustment tetap ditemukan oleh Filament.
- Permission BOM dan Project tetap bekerja.
- Role Design tidak otomatis memperoleh permission BOM baru.
- Route Page/Resource lama redirect pada compatibility release, lalu tidak terdaftar setelah retirement.

### 26.8 UI/browser

- Modal buka/tutup/cancel/save.
- Validasi mempertahankan input.
- Submit ganda dicegah.
- Tidak ada JavaScript error.
- Viewport 360, 390, 768, 1024, dan 1440 px.
- Zoom 200%.
- Nama dan notes panjang tidak merusak layout.

## 27. Phase implementasi

### Phase 0 — Characterization dan baseline

- Audit route, navigation, permission, model, migration, Page, Resource, export, Internal Memo, dan test terkait.
- Tambahkan characterization test untuk behavior yang akan dipindahkan.
- Rekam jumlah query dan request ESB pada halaman target.
- Audit data production tanpa mutation.
- Jangan mengubah behavior.

**Selesai jika:** seluruh consumer lama terpetakan dan tidak ada file yang diklaim dead code tanpa bukti.

### Phase 1 — Fondasi master WIP

- Buat migration additive untuk constraint/index yang diperlukan pada tabel existing setelah audit data.
- Perbarui model dan factory existing; tambahkan enum, policy/gate, dan mapper kompatibilitas satuan.
- Buat `CreateWipShelfLifeAction`, `UpdateWipShelfLifeAction`, dan `WipShelfLifeResolver`.
- Tambahkan unique constraint hanya setelah duplicate dan soft-deleted conflict diselesaikan.
- Tambahkan unit/feature test domain.

**Selesai jika:** master dapat disimpan dan di-resolve secara aman tanpa UI.

### Phase 2 — BOM Adjustment

- Tambahkan relation/query master ke katalog.
- Tambahkan kolom, status, filter, dan modal.
- Pisahkan mutation lokal dari flow update ESB.
- Tambahkan authorization, performance, dan Livewire test.

**Selesai jika:** master dapat dikelola seluruhnya dari BOM Adjustment dan tidak ada N+1.

### Phase 3 — Project integration

- Hubungkan hasil mapping WIP ke resolver.
- Tambahkan section kelengkapan WIP pada halaman Product/BOM.
- Tambahkan create-missing modal.
- Terapkan gate `Ready/Released`.
- Tambahkan test Project dan permission.

**Selesai jika:** Project otomatis menampilkan master dan missing master dapat dibuat tanpa override Project.

### Phase 4 — Menu Shelf Life cleanup

- Hapus field/state/payload/summary/validation Shelf Life Menu dari UI Project.
- Pertahankan kolom database legacy.
- Ganti test lama dengan kontrak behavior baru.

**Selesai jika:** tidak ada input atau kewajiban Shelf Life pada Menu/final Product.

### Phase 5 — Internal Memo decoupling

- Hapus lookup `forMenu` dari create/add Menu.
- Hapus blocker Shelf Life Menu.
- Verifikasi record dan export historis.
- Pertahankan field database lama.

**Selesai jika:** Internal Memo tidak memerlukan master Menu dan histori tetap dapat dibaca.

### Phase 6 — Retire legacy UI

- Hapus kedua navigation entry.
- Aktifkan temporary redirect untuk entry URL Page/Resource lama dan respons retirement untuk export lama.
- Retire Page, Resource, controller, export, dan route lama setelah telemetry/reference audit bersih.
- Audit Filament discovery dan string reference.
- Pindahkan role assignment dan deprecate permission lama.

**Selesai jika:** hanya BOM Adjustment menjadi entry master Shelf Life dan tidak ada broken link.

### Phase 7 — Final remediation dan verification

- Audit duplikasi logic, class responsibility, query, dan payload Livewire.
- Jalankan Pint, test terfokus, test modul, route/discovery check, build bila asset berubah, dan `git diff --check`.
- Review visual desktop/mobile dan keyboard.
- Susun deployment/rollback note.

**Selesai jika:** seluruh acceptance criteria dan quality gate lulus.

## 28. Deployment

Urutan deployment yang disarankan:

1. Backup database.
2. Audit jumlah record, duplicate identity, unit, mixed Menu/Product Detail identity, dan soft-deleted conflict pada tabel existing.
3. Jalankan hanya normalisasi/backfill yang sudah disetujui melalui command terpisah dengan dry-run; selesaikan duplicate dan soft-deleted conflict.
4. Deploy migration additive constraint/index untuk `rnd_esb_product_shelf_lives` setelah data dinyatakan aman.
5. Deploy pembaruan model existing, action, resolver, dan compatibility mapper dalam keadaan belum dipakai UI.
6. Deploy BOM Adjustment integration.
7. Deploy Project integration.
8. Verifikasi data dan permission.
9. Deploy cleanup Menu dan Internal Memo.
10. Hapus navigation lama, aktifkan compatibility route, lalu unregister route setelah telemetry/reference audit bersih.
11. Jalankan smoke test production.

Tidak ada migration yang drop kolom/tabel pada rollout awal.

## 29. Rollback

### Sebelum legacy UI dipensiunkan

- Nonaktifkan UI master WIP baru.
- Rollback constraint/index additive bila diperlukan tanpa menghapus row master yang sudah tersimpan.
- Kembalikan penggunaan UI lama tanpa mengubah atau menghapus tabel existing.

### Setelah master WIP dipakai

- Jangan drop tabel existing karena sudah menjadi sumber data WIP baru sekaligus menyimpan histori Menu lama.
- Rollback aplikasi dilakukan dengan feature/release revert sambil mempertahankan data.
- Jika navigation lama sudah dihapus, dapat dikembalikan sementara tanpa memindahkan data master WIP ke Menu.

### Setelah permission lama dideprecate

- Restore permission hanya melalui PermissionSynchronizer/role seeder yang sesuai.
- Jangan insert permission manual tanpa audit role.

## 30. Observability dan pemeriksaan operasional

Pantau:

- jumlah master aktif;
- jumlah katalog WIP tanpa master;
- jumlah identity tanpa Product Detail ID;
- error save master;
- unauthorized mutation attempt;
- query count BOM Adjustment;
- jumlah Project yang gagal `Ready/Released` karena kelengkapan WIP;
- error Internal Memo setelah decoupling;
- broken route/navigation setelah retirement.

Log tidak boleh memuat credential atau token ESB.

## 31. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Data Menu lama dianggap WIP | Lookup WIP wajib memakai Product Detail ID; row Menu legacy dikecualikan dan tidak di-auto-migrate |
| Duplicate master akibat submit bersamaan | Unique constraint, transaction, lock/error handling |
| Product Detail ID hilang | Block save dan tampilkan identity error |
| N+1 pada 1.009 katalog BOM | Eager load/bulk query dan performance test |
| Shelf Life ikut mutation ESB | Action dan modal terpisah; HTTP assertion test |
| Project mengubah master global tanpa sadar | Copy UI eksplisit dan permission ganda |
| Perubahan master mengubah Project lama | Diterima pada MVP; snapshot release menjadi fase terpisah |
| Internal Memo rusak setelah Resource lama dihapus | Decouple consumer sebelum retirement |
| Permission Design/R&D berubah | Jangan grant permission BOM otomatis; Project tetap menampilkan read-only sesuai akses |
| Cleanup menghapus data historis | Tidak ada drop pada rollout awal |
| Worktree feature lain overlap | Implementasi per phase dan jangan mencampur perubahan kalender/task |

## 32. Acceptance criteria

- [ ] Satu master aktif tersedia per `company_code + esb_product_detail_id`.
- [ ] Master dapat dikelola dari BOM Adjustment tanpa mutation ESB.
- [ ] BOM Adjustment menampilkan status lengkap/belum lengkap dengan pagination dan tanpa N+1.
- [ ] Project otomatis membaca master ketika WIP ditemukan.
- [ ] WIP tanpa master dapat diisi dari halaman Project oleh pengguna berizin.
- [ ] BOM Adjustment dan Project membuat record pada sumber master yang sama; hanya BOM Adjustment yang dapat mengubah master existing.
- [ ] Tidak ada override Shelf Life per Project pada MVP.
- [ ] WIP direct dan nested memakai hasil mapping existing, dideduplikasi, dan mengikuti cycle/depth guard existing.
- [ ] Product tanpa WIP tidak diblokir.
- [ ] Product dengan WIP missing/unresolved tidak dapat `Ready/Released`.
- [ ] Form, card, payload, dan validation Shelf Life Menu telah dihapus.
- [ ] Menu `Shelf Life` dan `Master Shelf Life Menu` telah dihapus.
- [ ] Entry URL Page/Resource lama menjalani compatibility redirect sebelum dihapus; export lama dipensiunkan tanpa redirect semantik yang keliru.
- [ ] Internal Memo tidak lagi bergantung pada Master Shelf Life Menu.
- [ ] Memo dan data historis tetap dapat dibaca.
- [ ] Permission ditegakkan di backend, termasuk direct Livewire invocation.
- [ ] Tidak ada query database atau HTTP ESB di Blade.
- [ ] UI responsif, aksesibel, dan mengikuti standar visual existing.
- [ ] Pest test terdampak, Pint, route/discovery check, build relevan, dan `git diff --check` lulus.

## 33. Definition of done

Fitur dianggap selesai ketika master Shelf Life WIP menjadi satu-satunya sumber aktif, BOM Adjustment menjadi workspace pengelolaannya, Project dapat membaca serta melengkapi master yang hilang, seluruh behavior Shelf Life Menu tidak lagi digunakan, consumer lama sudah dipindahkan, dan tidak ada regresi pada BOM, Project, Internal Memo, permission, route, atau UI.

Penyelesaian PRD ini sendiri hanya menghasilkan dokumen. Implementasi, migration, test execution, commit, push, dan deployment dilakukan melalui instruksi terpisah.
