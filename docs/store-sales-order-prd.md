# PRD — Store Sales Order

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 2 Oktober 2026 |
| Status | Rancangan siap direview; belum mengotorisasi implementasi atau deployment |
| Modul | Operational |
| Aplikasi user | Tile opsional `Store Sales Order` untuk admin store |
| Back office | `Operational → Store Sales Orders` |
| Integrasi | ESB Core `GET /sales/product-sales` |
| Acuan arsitektur | `docs/code-remediation-prd.md` |
| Acuan UI | `docs/ui-consistency-prd.md` |

Dokumen ini menjadi sumber requirement fitur Store Sales Order. Kondisi repository, kontrak API ESB, pola branch access, permission, attachment, dan komponen UI wajib diaudit kembali sebelum implementasi karena dapat berubah dari baseline.

## 2. Latar belakang

Admin store sudah membuat Sales Order di ESB, tetapi informasi operasional pesanan khusus belum tersimpan secara terstruktur di Help Bloomery. Informasi seperti kontak, jenis acara, waktu pengiriman, kebutuhan produk khusus, catatan persiapan, dan bukti referensi masih berpotensi tersebar di chat atau spreadsheet.

Fitur ini bukan pengganti pembuatan Sales Order ESB. Admin memasukkan nomor Sales Order atau nomor SL yang sudah tersedia di ESB. Sistem memvalidasi nomor tersebut, menyimpan snapshot data dasarnya, lalu admin melengkapi informasi operasional yang diperlukan oleh tim.

## 3. Tujuan

1. Menyediakan form singkat untuk mencatat kebutuhan operasional dari Sales Order ESB.
2. Mengurangi penyalinan data yang sudah tersedia di ESB.
3. Menyimpan informasi pesanan khusus dalam satu tempat yang dapat dicari.
4. Menjaga data sesuai branch dan akses user.
5. Memberikan daftar kerja bagi tim Operational tanpa membuat workflow versi pertama terlalu rumit.
6. Menyediakan fondasi untuk pengembangan detail produk ESB, invoice, pembayaran, dan notifikasi pada fase berikutnya.

## 4. Prinsip produk

- **ESB tetap sumber Sales Order:** Help Bloomery hanya menyimpan referensi dan snapshot.
- **Form sederhana:** field yang sudah diperoleh dari ESB tidak diketik ulang.
- **Opsional:** tile digunakan ketika ada pesanan yang perlu dicatat; bukan form harian wajib.
- **Branch-aware:** user hanya dapat memilih dan melihat branch sesuai aksesnya.
- **Dapat diperbarui:** kebutuhan produk, catatan, dan status operasional dapat disunting setelah record dibuat.
- **Tahan gangguan ESB:** kegagalan lookup tidak menghasilkan record seolah sudah tervalidasi.
- **Dapat diaudit:** penyimpanan dan perubahan penting mencatat user serta waktu.

## 5. Scope versi pertama

### 5.1 Termasuk

- Tile opsional `Store Sales Order` pada aplikasi user.
- Form pencarian nomor Sales Order ESB.
- Pemilihan branch berdasarkan akses user.
- Lookup header Sales Order melalui ESB Core.
- Snapshot data Sales Order yang berhasil ditemukan.
- Informasi kontak dan kebutuhan operasional sederhana.
- Daftar kebutuhan produk yang dapat ditambah atau dikurangi.
- Pilihan produk standar serta opsi `Custom`.
- Attachment foto referensi atau PDF.
- Status operasional dalam bahasa Inggris.
- Riwayat record terbaru pada halaman form.
- Submenu `Operational → Store Sales Orders` pada back office.
- Index, filter, search, pagination, detail, dan edit operasional.
- Permission, Policy, branch scope, activity log minimum, dan soft delete.
- Test yang terisolasi dari network eksternal.

### 5.2 Tidak termasuk versi pertama

- Membuat, mengubah, membatalkan, atau menghapus Sales Order di ESB.
- Mengubah status Sales Order ESB.
- Sinkronisasi otomatis seluruh Sales Order tanpa aksi pengguna.
- Mengambil rincian line product ESB karena endpoint detail belum tersedia.
- Membuat invoice atau payment link Xendit.
- Rekonsiliasi pembayaran otomatis.
- Perhitungan stok, forecasting, produksi, atau pengiriman otomatis.
- Workflow approval bertingkat.
- WhatsApp, email, dan notifikasi eksternal.
- Dashboard analitik besar.

## 6. Aktor

| Aktor | Tanggung jawab |
| --- | --- |
| Admin Store | Mencari nomor SL, melengkapi informasi operasional, dan memperbarui record sesuai branch access |
| Operational Reviewer | Melihat, memfilter, dan memperbarui status operasional |
| Operational Manager | Melihat seluruh data yang diizinkan, mengoreksi detail, dan mengarsipkan record |
| Administrator | Mengelola permission dan tindakan administratif |

## 7. Alur utama

```text
Launcher aplikasi user
→ Store Sales Order
→ Pilih Branch
→ Masukkan Nomor Sales Order / SL
→ Cari di ESB
→ Sistem menampilkan data dasar untuk dikonfirmasi
→ Isi informasi operasional dan kebutuhan produk
→ Tambahkan attachment jika diperlukan
→ Simpan
→ Record tampil di riwayat user dan back office Operational
→ Operational memperbarui status sampai Delivered atau Cancelled
```

Jika ESB timeout atau mengembalikan error, form tetap terbuka dan input pengguna dipertahankan. Record baru tidak boleh dianggap terverifikasi sampai lookup ESB berhasil.

## 8. Sumber branch dan autentikasi ESB

1. Branch berasal dari Master Branch lokal dan dibatasi oleh branch access user.
2. Mapping ESB diambil dari mapping utama aktif pada Master Branch, mengikuti resolver branch yang berlaku di repository saat implementasi.
3. Mapping minimum harus memiliki:
   - Company Code;
   - ESB Branch Code;
   - numeric ESB Branch ID.
4. Access token ESB Core diperoleh melalui `EsbCoreClient` berdasarkan Company Code.
5. UI, Page, dan Blade tidak boleh melakukan HTTP ESB langsung.
6. Jika mapping atau credential belum lengkap, branch tidak dapat digunakan untuk lookup dan UI menampilkan penyebabnya.
7. Nomor serta nama branch disimpan sebagai snapshot agar record lama tetap dapat dibaca jika Master Branch berubah.

## 9. Integrasi API ESB

### 9.1 Endpoint versi pertama

```http
GET {esb_core_base_url}/sales/product-sales
Authorization: Bearer {access_token}
Accept: application/json
```

Parameter yang digunakan untuk lookup:

| Parameter | Sumber |
| --- | --- |
| `productSalesNum` | Nomor Sales Order / SL yang dimasukkan user |
| `branchID` | Numeric Branch ID dari mapping Master Branch |
| `page` | `1` |
| `limit` | Nilai kecil yang cukup untuk exact lookup, misalnya `10` |

Sistem harus melakukan pencocokan ulang secara exact terhadap `productSalesNum` dan branch pada response. Jangan menganggap hasil pertama selalu benar.

### 9.2 Snapshot response yang disimpan

- `productSalesNum`;
- `productSalesDate`;
- `requiredDate`;
- `branchID` dan `branchName`;
- `customerID` dan `customerName`;
- `customerAddress`;
- `productSalesTotal`;
- `currencySign`;
- `statusID` dan `statusName`;
- `createdBy`;
- `linkPurchaseNum`;
- `additionalInfo`;
- waktu terakhir diverifikasi;
- raw response terpilih untuk audit, dengan data sensitif diminimalkan.

### 9.3 Kontrak yang belum tersedia

Endpoint daftar yang diberikan belum memuat line product, unit, quantity, harga per item, discount per item, pajak per item, maupun file invoice. Jika pada versi berikutnya produk harus diisi otomatis, dibutuhkan API resmi berikut:

1. **Product Sales Detail** berdasarkan `productSalesNum` atau ID yang stabil.
2. Response line item yang memuat Product ID, Product Detail ID, code, name, unit, quantity, unit price, discount, tax, dan subtotal.
3. **Invoice/Document Detail atau Download** jika dokumen invoice harus ditampilkan.
4. Kontrak status dan error yang menjelaskan Sales Order cancelled, deleted, atau tidak dapat diakses oleh Company Code terkait.

Sampai kontrak tersebut tersedia, section kebutuhan produk adalah catatan operasional lokal dan tidak diklaim sebagai line item resmi ESB.

## 10. Form aplikasi user

### 10.1 Referensi Sales Order

| Field | Aturan |
| --- | --- |
| Branch | Wajib; default branch utama user; hanya branch yang dapat diakses |
| Nomor Sales Order / SL | Wajib; maksimal 100 karakter; dinormalisasi trim tanpa mengubah karakter nomor |
| Tombol Cari ESB | Aktif setelah Branch dan nomor terisi; mempunyai loading spinner dan perlindungan klik berulang |

Setelah lookup berhasil, tampilkan kartu konfirmasi read-only:

- nomor Sales Order;
- tanggal Sales Order;
- required date;
- customer;
- alamat;
- total;
- status ESB;
- branch ESB.

User harus dapat memilih **Cari Ulang** sebelum record disimpan. Mengganti Branch atau nomor SL akan membersihkan hasil lookup sebelumnya.

### 10.2 Informasi Operasional

| Field | Aturan |
| --- | --- |
| Nomor HP | Opsional; maksimal 50 karakter |
| Order By | Opsional; nama pemesan atau PIC; maksimal 150 karakter |
| Jenis Acara | Opsional; select dengan opsi umum dan `Other` |
| Jam Pengiriman | Opsional; time |
| Catatan Persiapan | Opsional; maksimal 2.000 karakter |

Pilihan awal Jenis Acara:

- Birthday;
- Wedding;
- Corporate;
- Gathering;
- Personal Order;
- Other.

### 10.3 Kebutuhan Produk

Section ini bukan packaging dan bukan line product resmi ESB. User dapat menambah atau mengurangi baris kapan pun selama mempunyai akses edit.

| Field per baris | Aturan |
| --- | --- |
| Pilihan Produk | Wajib; select option |
| Detail Custom | Wajib hanya ketika pilihan `Custom`; maksimal 500 karakter |
| Jumlah | Wajib; bilangan lebih besar dari nol |
| Catatan | Opsional; maksimal 500 karakter |

Pilihan produk awal:

- DB50 Pack;
- DB100 Pack;
- Snack Box;
- Big Box;
- Tampah 1;
- Tampah 2;
- Dessert Cup Wedding Cake;
- Custom.

Pilihan disimpan sebagai enum/config terpusat agar dapat ditambah tanpa mengganti logika form di banyak tempat. Nama `Custom` harus menampilkan field detail secara dinamis.

### 10.4 Attachment

- Opsional, maksimal 5 file.
- Maksimal 5 MB per file.
- JPG, JPEG, PNG, WebP, atau PDF.
- Digunakan untuk foto referensi produk, desain, atau dokumen pendukung.
- UI mengikuti attachment ERP Request existing: informasi format, loading, preview, error per file, dan hapus sebelum submit.

### 10.5 Informasi pengisian

Gunakan container **Informasi Pengisian**:

> Masukkan nomor Sales Order yang sudah dibuat di ESB. Periksa kembali data customer dan tanggal yang ditemukan, lalu isi hanya kebutuhan operasional yang belum tercatat di ESB.

## 11. Status operasional

| Nilai | Label UI | Arti |
| --- | --- | --- |
| `Draft` | Draft | Data masih disiapkan |
| `Submitted` | Submitted | Data sudah dikirim ke Operational |
| `InPreparation` | In Preparation | Pesanan sedang dipersiapkan |
| `Ready` | Ready | Pesanan siap dikirim atau diambil |
| `Delivered` | Delivered | Pesanan selesai diserahkan |
| `Cancelled` | Cancelled | Pencatatan dibatalkan |

Transisi versi pertama:

```text
Draft → Submitted → In Preparation → Ready → Delivered
   └──────────────→ Cancelled
```

Aturan:

- Admin Store membuat record langsung sebagai `Submitted` setelah form valid.
- `Cancelled` wajib mempunyai alasan.
- `Delivered` dan `Cancelled` merupakan status terminal pada versi pertama.
- Status ESB dan status operasional disimpan terpisah agar tidak tertukar.
- Perubahan status lokal tidak mengirim mutation ke ESB.

## 12. Riwayat aplikasi user

Halaman form menampilkan maksimal lima record terbaru yang dibuat user atau berada dalam branch yang dapat diakses sesuai Policy.

Informasi ringkas:

- nomor Sales Order;
- required date;
- customer;
- branch;
- status operasional;
- tombol detail/edit sesuai permission.

Tidak dibuat tile Riwayat terpisah.

## 13. Back office

### 13.1 Navigation

```text
Operational
└── Store Sales Orders
```

Menu hanya tampil jika user mempunyai permission `view any store sales orders`. Custom sidebar dan Filament Resource wajib menggunakan permission yang sama.

### 13.2 Index

Urutan UI:

1. Header ringkas dengan ikon, judul, dan deskripsi.
2. Search serta filter inline pada header tabel.
3. Tabel data.
4. Pagination 10 atau 20 data.

Kolom:

- Sales Order Number;
- Required Date;
- Customer;
- Branch;
- Event Type;
- Product Summary;
- ESB Status;
- Operational Status;
- Submitted By;
- aksi detail.

Search mencakup nomor Sales Order, nama customer, Order By, nomor HP, dan catatan persiapan. Filter mencakup branch, status operasional, status ESB, jenis acara, serta rentang required date.

Urutan default: required date terdekat yang belum terminal, lalu data terbaru. Record terminal tetap dapat dicari melalui filter.

### 13.3 Detail dan edit

Detail dibagi menjadi:

1. **Sales Order ESB** — seluruh snapshot read-only dan waktu verifikasi.
2. **Informasi Operasional** — kontak, acara, pengiriman, dan catatan.
3. **Kebutuhan Produk** — daftar pilihan, jumlah, detail Custom, dan catatan.
4. **Attachment** — preview/download terautorisasi.
5. **Activity** — pembuat, perubahan status, pengubah, serta waktu.

Edit dilakukan melalui modal atau halaman sesuai kompleksitas implementasi aktual. Form edit harus mempertahankan pola dan validation error dari UI PRD.

## 14. Aturan duplikasi dan refresh ESB

- Kombinasi `company_code + esb_branch_id + product_sales_number` harus unik untuk record aktif.
- Submit berulang tidak boleh membuat dua record.
- Jika nomor sudah tercatat, user diarahkan ke record yang ada jika mempunyai akses.
- Aksi **Refresh dari ESB** hanya memperbarui field snapshot ESB dan `last_verified_at`.
- Refresh tidak boleh menimpa informasi operasional, kebutuhan produk, attachment, atau status lokal.
- Timeout atau connection failure tidak menghapus snapshot lama.
- Lookup dan refresh adalah operasi read-only; retry terbatas diperbolehkan sesuai kebijakan `EsbCoreClient`.

## 15. Model data

### 15.1 `store_sales_orders`

Kolom minimum:

- `id`;
- `branch_id`, indexed;
- `branch_esb_code_id`, nullable dan indexed;
- `company_code_snapshot`;
- `branch_code_snapshot`;
- `esb_branch_id_snapshot`;
- `branch_name_snapshot`;
- `product_sales_number`, indexed;
- `product_sales_date`, nullable dan indexed;
- `required_date`, nullable dan indexed;
- `customer_id_snapshot`, nullable;
- `customer_name_snapshot`, nullable dan indexed;
- `customer_address_snapshot`, nullable;
- `product_sales_total`, nullable;
- `currency_sign`, nullable;
- `esb_status_id`, nullable;
- `esb_status_name`, nullable;
- `esb_created_by`, nullable;
- `link_purchase_number`, nullable;
- `esb_additional_info`, nullable;
- `esb_snapshot`, JSON nullable;
- `last_verified_at`;
- `phone_number`, nullable;
- `ordered_by`, nullable;
- `event_type`, nullable;
- `event_type_other`, nullable;
- `delivery_time`, nullable;
- `preparation_notes`, nullable;
- `attachment_paths`, JSON nullable;
- `operational_status`, indexed;
- `cancellation_reason`, nullable;
- `submitted_by`, indexed;
- `updated_by`, nullable;
- timestamps;
- soft deletes.

Unique constraint minimum:

```text
company_code_snapshot + esb_branch_id_snapshot + product_sales_number
```

### 15.2 `store_sales_order_items`

- `id`;
- `store_sales_order_id`, indexed dan cascade delete;
- `product_type`;
- `custom_detail`, nullable;
- `quantity`, decimal sesuai kebutuhan bisnis;
- `notes`, nullable;
- `sort_order`;
- timestamps.

### 15.3 `store_sales_order_activities`

- `id`;
- `store_sales_order_id`, indexed dan cascade delete;
- `activity_type`;
- `previous_status`, nullable;
- `new_status`, nullable;
- `notes`, nullable;
- `metadata`, JSON nullable;
- `created_by`, nullable;
- timestamps.

Activity bersifat append-only melalui workflow normal. Record utama, item, dan activity awal disimpan dalam transaction database yang sama.

## 16. Permission dan branch scope

| Permission | Kegunaan |
| --- | --- |
| `view any store sales orders` | Membuka index back office |
| `view store sales orders` | Melihat detail record yang diizinkan |
| `create store sales orders` | Menampilkan tile dan membuat record |
| `update store sales orders` | Mengubah informasi operasional dan kebutuhan produk |
| `update store sales order status` | Mengubah status operasional |
| `delete store sales orders` | Soft delete untuk role administratif |

Aturan:

- user biasa hanya bekerja pada branch yang dapat diakses;
- `access_all_branches` tetap membutuhkan permission fitur;
- branch divalidasi ulang di server pada lookup dan submit;
- Policy dan query scope menjadi pengaman utama;
- visibility tombol tidak dianggap sebagai authorization;
- permission ditambahkan melalui config/seeder dan migration permission yang aman untuk database existing.

## 17. Struktur kode target

Ikuti struktur repository aktual dan jangan membuat base folder baru:

```text
app/
├── Actions/StoreSalesOrder/
│   ├── LookupStoreSalesOrderAction.php
│   ├── CreateStoreSalesOrderAction.php
│   ├── UpdateStoreSalesOrderAction.php
│   └── UpdateStoreSalesOrderStatusAction.php
├── Enums/
│   ├── StoreSalesOrderStatus.php
│   ├── StoreSalesOrderEventType.php
│   └── StoreSalesOrderProductType.php
├── Models/
│   ├── StoreSalesOrder.php
│   ├── StoreSalesOrderItem.php
│   └── StoreSalesOrderActivity.php
├── Policies/
│   └── StoreSalesOrderPolicy.php
├── Services/Esb/
│   └── EsbProductSalesService.php
├── Filament/Casual/Pages/
│   └── StoreSalesOrderPage.php
└── Filament/Helpdesk/Resources/StoreSalesOrders/

resources/views/filament/
├── casual/pages/store-sales-order-page.blade.php
└── helpdesk/store-sales-orders/

tests/
├── Unit/EsbProductSalesServiceTest.php
└── Feature/
    ├── StoreSalesOrderLookupTest.php
    ├── StoreSalesOrderSubmissionTest.php
    ├── StoreSalesOrderBackOfficeTest.php
    ├── StoreSalesOrderPermissionTest.php
    └── StoreSalesOrderAttachmentTest.php
```

Sesuaikan namespace domain dengan struktur aktual hasil code remediation. Jangan memindahkan modul lain hanya untuk mengimplementasikan fitur ini.

### Pembagian tanggung jawab

- Page/Resource menangani state UI, presentation validation, authorization, dan memanggil Action.
- Action menangani satu use case dan transaction boundary.
- `EsbProductSalesService` menangani endpoint Product Sales tanpa bergantung pada Livewire.
- `EsbCoreClient` menangani login, cache token, timeout, refresh `401`, dan error transport.
- Model menangani relasi, cast, dan scope yang kohesif.
- Policy menangani permission serta branch scope.
- Blade tidak melakukan query, HTTP, atau mutation bisnis.

## 18. UI dan responsive

Ikuti `docs/ui-consistency-prd.md` dan pola Operational/Customer Complaints existing:

- container putih dengan border tipis dan tanpa shadow berlebihan;
- header ringkas dengan ikon dan satu CTA utama;
- label memakai kapital setiap kata;
- input memiliki border tipis dan state focus/error yang jelas;
- section **Referensi Sales Order**, **Informasi Operasional**, **Kebutuhan Produk**, dan **Attachment**;
- lookup menampilkan loading spinner, empty state, error state, serta tombol coba lagi;
- item repeater disusun per baris pada desktop dan menjadi card ringkas pada mobile;
- `Custom` menampilkan field detail hanya pada baris terkait;
- attachment mengikuti pola ERP Request;
- modal memiliki body scroll dan tidak membuat halaman belakang ikut scroll;
- search/filter back office inline seperti pola tabel sourcing yang sudah disepakati;
- pagination sederhana dengan arrow dan informasi rentang;
- tidak ada horizontal scroll pada keseluruhan halaman.

Viewport minimum: 360, 390, 768, 1024, dan 1440 px serta zoom 200%.

## 19. Reliability, keamanan, dan observability

- Seluruh input divalidasi server-side.
- Nomor Sales Order dan branch dari hasil lookup diverifikasi ulang saat submit.
- Jangan mempercayai snapshot yang dikirim dari browser; hasil canonical berasal dari service/server.
- HTTP ESB menggunakan timeout eksplisit dan error context yang aman.
- Log mencatat endpoint, Company Code, Branch ID, durasi, status, dan correlation identifier tanpa access token atau data customer lengkap.
- Access token dan credential tidak disimpan dalam source code atau database record Sales Order.
- Attachment memakai disk privat dan route/temporary URL terautorisasi.
- Output teks di-escape oleh Blade.
- Mutation lokal memakai transaction dan perlindungan double submit.
- Query index memakai eager loading dan server-side pagination.
- Tidak ada network eksternal dalam test.
- Endpoint lookup dapat diberi rate limit per user untuk mencegah spam tanpa mengganggu penggunaan normal.

## 20. Test strategy

### 20.1 Integrasi ESB

- request memakai Company Code dan branch mapping yang benar;
- access token berasal dari `EsbCoreClient`;
- parameter exact lookup benar;
- response pagination dinormalisasi;
- hasil pertama yang nomornya berbeda ditolak;
- `401` mengikuti refresh token client;
- timeout, connection failure, unauthorized, malformed response, dan no data dipetakan menjadi error aman;
- seluruh HTTP difake dan stray request dilarang.

### 20.2 Form dan submission

- tile hanya tampil dengan permission create;
- branch hanya berasal dari akses user;
- Branch ID dan Company Code tidak dapat dimanipulasi melalui state Livewire;
- submit wajib mempunyai lookup valid;
- mengganti nomor atau branch membatalkan hasil lookup lama;
- produk Custom mewajibkan detail;
- quantity harus positif;
- item dapat ditambah dan dihapus;
- duplicate submit menghasilkan satu record;
- informasi ESB disimpan sebagai snapshot;
- status awal adalah `Submitted`.

### 20.3 Back office

- navigation mengikuti permission;
- index dan detail mengikuti branch scope;
- search, filter, sort, dan pagination bekerja;
- status transition valid diterima dan invalid ditolak;
- Cancelled membutuhkan alasan;
- Refresh ESB tidak menimpa data lokal;
- activity tercatat bersama mutation utama.

### 20.4 Attachment dan UI

- MIME, ukuran, dan jumlah file tervalidasi;
- unauthorized download ditolak;
- kegagalan transaction tidak meninggalkan file orphan;
- modal, loading, validation error, dan responsive state mempunyai regression test yang masuk akal;
- tidak membuat test yang hanya menduplikasi detail implementasi CSS.

## 21. Phase implementasi

### Phase 0 — Audit dan contract freeze

- Audit struktur panel, launcher, branch access, permission, custom sidebar, attachment, dan pola Operational existing.
- Audit `EsbCoreClient`, resolver mapping branch, konfigurasi Company Code, dan error object.
- Verifikasi response aktual endpoint Product Sales memakai environment non-production yang diizinkan.
- Konfirmasi format nomor SL dan apakah exact lookup selalu mengembalikan satu record.
- Catat perbedaan dokumentasi dengan response aktual.

**Selesai jika:** kontrak request/response, mapping branch, permission awal, dan behavior error telah dibuktikan tanpa membuat data production.

### Phase 1 — Safety net dan client ESB

- Tambahkan characterization test pada branch access, launcher, sidebar, attachment, dan client ESB.
- Buat `EsbProductSalesService` di atas `EsbCoreClient`.
- Implementasikan list/exact lookup dan normalisasi response.
- Uji token, `401`, timeout, connection failure, invalid response, dan no data.

**Selesai jika:** service dapat melakukan exact lookup melalui HTTP fake tanpa ketergantungan UI.

### Phase 2 — Domain dan database

- Buat enum, migration, model, factory, relation, cast, Policy, permission, dan Action.
- Terapkan unique constraint dan transaction.
- Implementasikan snapshot serta activity awal.
- Tambahkan test domain, duplicate guard, dan branch authorization.

**Selesai jika:** lookup terverifikasi dapat disimpan dengan item lokal secara aman.

### Phase 3 — Form aplikasi user

- Tambahkan tile opsional dan page.
- Buat lookup, kartu konfirmasi, field operasional, item repeater, attachment, dan riwayat terbaru.
- Pertahankan state form saat lookup gagal.
- Tambahkan loading, validation, success, error, serta duplicate-submit guard.

**Selesai jika:** Admin Store dapat membuat satu record lengkap pada mobile dan desktop.

### Phase 4 — Back office

- Tambahkan Resource dan submenu Operational.
- Buat index dengan filter inline, search, sort, pagination, serta detail.
- Implementasikan edit data lokal, refresh snapshot, perubahan status, pembatalan, dan activity timeline.
- Selaraskan custom sidebar dan discovery Filament.

**Selesai jika:** Operational dapat menemukan serta menindaklanjuti record sesuai branch scope.

### Phase 5 — Attachment dan hardening

- Lindungi preview/download.
- Terapkan cleanup file pada kegagalan.
- Audit query count, payload Livewire, dan log integrasi.
- Uji concurrent submit serta refresh ketika ESB gagal.

**Selesai jika:** lifecycle file, duplikasi, kegagalan eksternal, dan observability tervalidasi.

### Phase 6 — UI review dan release

- Review seluruh viewport serta state UI berdasarkan UI PRD.
- Jalankan test terfokus per scope kecil.
- Jalankan Pint untuk PHP yang berubah.
- Jalankan full suite dengan memory 512 MB.
- Dokumentasikan migration, env/config, queue bila ada, deployment, smoke test, dan rollback.

**Selesai jika:** seluruh quality gate lulus dan perubahan dapat direview sebagai unit deployable.

## 22. Quality gates

1. Audit kondisi repository sebelum setiap phase.
2. Gunakan dokumentasi versi package yang terpasang sebelum mengubah kode.
3. Pertahankan perubahan existing lain pada working tree.
4. Jangan mengubah migration production lama; buat migration baru.
5. Jangan memanggil HTTP dari Page, Resource, atau Blade.
6. Jangan menyimpan credential atau access token dalam source code, log, snapshot, atau database domain.
7. Jangan membuat mutation ESB pada fitur ini.
8. Setiap perubahan perilaku mempunyai test bermakna.
9. Jalankan test file terkait sebelum test modul dan full suite.
10. Gunakan full suite dengan `memory_limit=512M` sesuai baseline repository.
11. Jalankan `vendor/bin/pint --dirty --format agent` jika PHP berubah.
12. Jangan mengubah requirement production hanya agar test lama lulus.

## 23. Deployment

Urutan deployment setelah seluruh phase selesai:

1. Backup database.
2. Pull commit release.
3. Jalankan `php artisan migrate --force`.
4. Sinkronkan permission untuk database existing melalui mekanisme migration/seeder yang disediakan implementasi.
5. Verifikasi credential ESB Core untuk Company Code branch target.
6. Jalankan `php artisan optimize:clear`.
7. Restart queue hanya jika implementasi memakai Job.
8. Smoke test satu branch, satu lookup valid, lookup tidak ditemukan, timeout ESB, submit, detail, edit, dan attachment.

Rollback aplikasi tidak boleh langsung menghapus tabel atau attachment. Pertahankan data untuk investigasi; rollback schema dilakukan hanya setelah backup dan aplikasi lama dipastikan tidak membaca struktur baru.

## 24. Acceptance criteria

- Admin Store dapat memilih branch yang diizinkan dan mencari nomor SL.
- Data header yang ditemukan cocok dengan nomor dan branch yang dipilih.
- User tidak mengetik ulang data dasar yang sudah tersedia dari ESB.
- User dapat menambahkan produk standar atau Custom beserta jumlahnya.
- Record tersimpan dengan snapshot ESB, informasi operasional, item, attachment, pembuat, dan status `Submitted`.
- Nomor SL yang sama pada company/branch yang sama tidak menghasilkan duplikasi.
- Back office menampilkan submenu Store Sales Orders sesuai permission.
- Operational dapat mencari, memfilter, membuka detail, dan memperbarui status tanpa mutation ke ESB.
- Branch scope berlaku pada UI dan server-side.
- Kegagalan ESB ditampilkan jelas tanpa kehilangan input atau merusak snapshot lama.
- UI konsisten, responsif, dan seluruh test terisolasi dari network eksternal.

## 25. Open decisions sebelum implementasi

1. Format nomor yang disebut user sebagai “SL” harus dikonfirmasi terhadap nilai `productSalesNum` aktual.
2. Role awal yang otomatis menerima permission create dan review harus ditentukan dari role production existing.
3. Apakah Admin Store boleh mengedit record setelah status `In Preparation`.
4. Apakah attachment membutuhkan dua kategori khusus, misalnya Foto Referensi dan Dokumen Pendukung, atau satu field sudah cukup.
5. Apakah required date kosong pada ESB boleh dilengkapi lokal atau harus dibetulkan di ESB.
6. Apakah daftar pilihan produk awal bersifat global atau perlu pengaturan master pada pengembangan berikutnya.

Open decision tidak boleh diselesaikan dengan hardcode role, branch, Company Code, atau response API yang belum dibuktikan.
