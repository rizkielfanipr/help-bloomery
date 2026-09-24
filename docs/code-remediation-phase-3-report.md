# Laporan Phase 3 — Fondasi Shared Process Stock Card

## 1. Status

| Field | Nilai |
| --- | --- |
| Acuan | `docs/code-remediation-prd.md` Phase 3 |
| Tanggal | 22 September 2026 |
| Scope | Authorization dan branch scope Stock Card |
| Status | Selesai dan tervalidasi |
| Full suite | 750 test lulus, 0 gagal, 4.350 assertions |

Scope ini sengaja dibatasi pada Stock Card agar perubahan authorization dapat direview sebagai satu concern. Query database yang masih berada di Blade Purchasing, ERP, Content, dan Design sudah diaudit, tetapi belum dipindahkan dalam scope ini.

## 2. Masalah sebelum perubahan

Aturan akses Stock Card tersebar pada beberapa lokasi:

- `StockCardResource` memeriksa permission dan membatasi query berdasarkan cabang.
- `ViewStockCard` menentukan izin review Supervisor dan Finance.
- `StockCardEsbSynchronizer` mengulang aturan permission, cabang, status, dan larangan memproses laporan sendiri.
- Belum ada Policy untuk menjadi aturan server-side yang dapat digunakan bersama oleh Filament, service, dan API pada masa depan.

Risikonya adalah perubahan pada satu entry point tidak otomatis berlaku pada entry point lain.

## 3. Perubahan kode

### 3.1 StockCardPolicy

File baru: `app/Policies/StockCardPolicy.php`.

Policy menjadi sumber aturan untuk kemampuan berikut:

| Ability | Aturan |
| --- | --- |
| `viewAny` | Memerlukan permission `view stock cards` |
| `view` | Memerlukan permission view dan akses ke cabang record |
| `create` | Memerlukan permission `create stock cards` |
| `update` | Memerlukan permission edit dan akses cabang |
| `delete` | Memerlukan permission delete dan akses cabang |
| `deleteAny` | Memerlukan permission delete; record tetap dibatasi query cabang |
| `restore` | Mengikuti aturan update |
| `forceDelete` | Mengikuti aturan delete |
| `reviewAsSupervisor` | Record harus Supervisor Review, user memiliki permission review Supervisor, dapat mengakses cabang, dan bukan submitter kecuali mempunyai akses seluruh cabang |
| `reviewAsFinance` | Record harus Finance Review, user memiliki permission review Finance, dapat mengakses cabang, dan bukan submitter kecuali mempunyai akses seluruh cabang |
| `refreshEsb` | User dapat melihat record, bukan submitter kecuali mempunyai akses seluruh cabang, dan mempunyai permission reviewer sesuai status record |

Policy ditemukan otomatis oleh Laravel melalui konvensi `StockCard` → `StockCardPolicy`; tidak diperlukan registrasi manual.

### 3.2 Query scope cabang

`StockCard` memperoleh scope:

```php
StockCard::query()->accessibleTo($user)
```

Perilakunya:

- user `access_all_branches` atau `SUPERADMIN` menerima seluruh record;
- user lain hanya menerima record dari primary branch dan additional accessible branches;
- aturan memakai helper akses cabang existing pada `User`, sehingga pengecualian administrator tidak berubah.

### 3.3 StockCardResource

Resource sekarang:

- memakai `accessibleTo()` untuk query index;
- memakai Policy untuk `canViewAny()` dan `canView()`;
- memakai Policy untuk delete record dan bulk delete;
- mengembalikan query kosong bila tidak ada user terautentikasi.

Filter, URL, tabel, permission name, serta perilaku tampilan tidak diubah.

### 3.4 Review Supervisor dan Finance

`ViewStockCard` tidak lagi menyalin aturan review. Method UI berikut sekarang mendelegasikan keputusan ke Policy:

- `canReviewAsSupervisor()`;
- `canReviewAsFinance()`.

Method mutation masih melakukan pemeriksaan authorization sebelum transaction, sehingga menyembunyikan tombol bukan satu-satunya pengamanan.

### 3.5 Refresh Stock Movement ESB

`StockCardEsbSynchronizer::canRefresh()` sekarang memakai ability `refreshEsb` dari Policy. Pemeriksaan yang sama tetap dijalankan sebelum request ESB dan kembali dijalankan di dalam database transaction setelah record dikunci.

## 4. Perilaku yang dipertahankan

- `SUPERADMIN` tetap dapat melewati pembatasan melalui `Gate::before` existing.
- User dengan `access_all_branches` tetap dapat melihat seluruh cabang.
- User biasa tetap hanya melihat cabang yang dapat diakses.
- Submitter biasa tidak dapat mereview atau refresh laporannya sendiri.
- User akses seluruh cabang tetap memperoleh pengecualian review laporan sendiri sesuai implementasi sebelumnya.
- Supervisor hanya bertindak pada status `pending_supervisor`.
- Finance hanya bertindak pada status `pending_finance`.
- Permission lama tidak diganti atau di-rename.
- Tidak ada migration, perubahan schema, dependency, route, atau format response.

## 5. Test baru

File baru: `tests/Feature/StockCardPolicyTest.php`.

Enam characterization test ditambahkan:

1. Query hanya mengembalikan Stock Card dari cabang user.
2. User akses seluruh cabang memperoleh seluruh record.
3. View memerlukan permission dan akses cabang.
4. Delete memerlukan permission dan akses cabang.
5. Review Supervisor menjaga aturan status, cabang, dan submitter.
6. Refresh ESB menjaga aturan reviewer dan pengecualian akses seluruh cabang.

## 6. Validasi

### Test Stock Card terkait

```bash
php artisan test --compact \
  tests/Feature/StockCardTest.php \
  tests/Feature/StockCardApprovalWorkflowTest.php \
  tests/Feature/StockCardSettingsTest.php
```

Hasil: **66 test lulus, 293 assertions**.

```bash
php artisan test --compact tests/Feature/StockCardPolicyTest.php
```

Hasil: **6 test lulus, 13 assertions**.

### Formatter

```bash
vendor/bin/pint --dirty --format agent
```

Hasil: **lulus**.

### Full suite

```bash
php artisan test --compact
```

| Metrik | Hasil |
| --- | ---: |
| Total test | 750 |
| Lulus | 750 |
| Gagal | 0 |
| Assertions | 4.350 |
| Durasi | 329,262 detik |

## 7. File Phase 3

- `app/Policies/StockCardPolicy.php` — policy baru.
- `app/Models/StockCard.php` — scope `accessibleTo()`.
- `app/Filament/Helpdesk/Resources/StockCards/StockCardResource.php` — delegasi query dan authorization.
- `app/Filament/Helpdesk/Resources/StockCards/Pages/ViewStockCard.php` — delegasi izin review.
- `app/Services/StockCardEsbSynchronizer.php` — delegasi izin refresh ESB.
- `tests/Feature/StockCardPolicyTest.php` — characterization test policy dan branch scope.

`ViewStockCard.php` juga telah mempunyai perubahan kategori produk dari pekerjaan Stock Card sebelumnya. Bagian tersebut bukan perubahan authorization Phase 3 dan tidak diklaim sebagai hasil scope ini.

## 8. Temuan lanjutan

Empat Blade masih menjalankan query database untuk opsi filter:

- Purchase Requests: branch.
- Content Requests: branch.
- ERP Repair Requests: branch, ERP module, request type.
- Design Requests: branch dan design category.

Pemindahan query tersebut sebaiknya dilakukan per modul dengan test filter masing-masing. Dua view, Content dan Design, juga tidak ditemukan pada render-hook aktif saat audit sehingga usage perlu diverifikasi sebelum diubah atau dihapus.

## 9. Risiko tersisa

- Policy baru baru mencakup Stock Card; resource lain masih menggunakan permission check masing-masing.
- Branch filter option pada beberapa tabel lain masih dapat mengambil data langsung dari Blade.
- Perubahan Phase 2, Phase 3, dan perubahan Stock Card UI existing masih berada pada working tree yang sama dan belum di-commit.

## 10. Scope lanjutan — Query opsi filter table

Setelah fondasi Policy Stock Card dipush, Phase 3 dilanjutkan dengan memindahkan query opsi filter dari Blade aktif.

### Service reusable

File `app/Services/TableFilterOptions.php` menyediakan dan mememoisasi selama satu request:

- seluruh opsi cabang yang diurutkan berdasarkan nama;
- modul ERP aktif yang diurutkan berdasarkan `sort_order`;
- tipe request IT aktif yang diurutkan berdasarkan `sort_order`.

Service hanya dipanggil untuk kolom header yang membutuhkan opsi tersebut, sehingga header lain tidak memicu query yang tidak diperlukan.

### Purchase Requests

`purchase-requests/table-header-cell.blade.php` tidak lagi menjalankan query `Branch`. Data `branchOptions` diberikan dari render hook pada `AppServiceProvider`.

### ERP Requests dan Material Sourcing

`erp-repair-requests/table-header-cell.blade.php` tidak lagi menjalankan query untuk `Branch`, `ErpModule`, dan `ItRequestType`. Render hook yang digunakan bersama Material Sourcing memasok data berdasarkan nama kolom yang sedang dirender.

### Test

`tests/Feature/TableFilterOptionsTest.php` memverifikasi:

1. cabang diurutkan berdasarkan nama;
2. pemanggilan kedua dalam request yang sama tidak mengulang query;
3. hanya modul ERP aktif yang ditampilkan sesuai `sort_order`;
4. hanya tipe request aktif yang ditampilkan sesuai `sort_order`.

Validasi scope:

```text
Purchase Requests dan service: 15 test lulus, 75 assertions
ERP/Material Sourcing dan service: 34 test lulus, 198 assertions
Full suite final: 752 test lulus, 4.355 assertions, 0 gagal
Pint: lulus
```

## 11. Dead-code removal — Header Content dan Design

Dua file berikut dihapus:

- `resources/views/filament/helpdesk/content-requests/table-header-cell.blade.php`;
- `resources/views/filament/helpdesk/design-requests/table-header-cell.blade.php`.

Bukti penghapusan:

1. tidak ada reference runtime terhadap nama view;
2. tidak ada `TablesRenderHook::HEADER_CELL` yang scope-nya mengarah ke `ListContentRequests` atau `ListDesignRequests`;
3. kedua Resource aktif menggunakan table dan filter Filament bawaan;
4. history Git menunjukkan file berasal dari implementasi UI index lama;
5. test Content/Design terkait lulus 32/32 setelah penghapusan;
6. full suite tetap lulus 752/752 dengan 4.355 assertions.

Dengan penghapusan ini, empat query Blade yang dicatat pada audit Phase 1 telah diselesaikan: query aktif Purchase/ERP dipindah ke service, sedangkan view Content/Design yang tidak aktif dihapus berdasarkan bukti penggunaan.

## 12. Authorization Item Journal

Item Journal kini mempunyai policy yang digunakan bersama oleh Employee App dan Back Office.

### Aturan terpusat

`QualityControlItemJournalPolicy` mengatur:

- `viewAny`: memerlukan permission view Item Journal;
- `view`: hanya journal milik sendiri, kecuali user memiliki permission view all;
- `create`: memerlukan view dan create;
- `submit`: memerlukan view, create, dan submit;
- `retryAttachments`: mengikuti akses view terhadap journal;
- `deleteAttachments`: mengikuti akses view dan permission delete attachment;
- update dan delete journal tetap ditolak karena Resource bersifat read-only.

### Query scope

Model menyediakan `visibleTo($user)` agar Employee App dan Back Office menggunakan aturan own-vs-all yang sama. Query tanpa user pada Resource menghasilkan hasil kosong.

### Integrasi workflow

`ItemJournalPage` sekarang memakai Policy untuk membuka halaman/form, submit, retry attachment, menghapus attachment, dan mengambil journal yang diizinkan. Validasi Company Code, ESB branch, location, product, purpose, serta payload tidak diubah.

### Validasi

```text
Test Item Journal dan Policy: 10 lulus, 45 assertions
Full suite: 756 lulus, 4.365 assertions, 0 gagal
Pint: lulus
```

Satu kegagalan awal diklasifikasikan sebagai test fixture defect: record test baru belum mengisi `location_name` yang wajib. Fixture diperbaiki tanpa mengubah production schema atau requirement.

## 13. Authorization Receiving dan Vendor Compliance

### Goods Receipt

`GoodsReceiptPolicy` menjadi sumber authorization untuk:

- akses tile/page Receiving pada Employee App;
- submit Goods Receipt;
- list dan detail Receiving di Back Office;
- menjaga Back Office Receiving tetap read-only.

Method `submit()` pada Livewire sekarang melakukan authorization tersendiri sebelum validasi dan mutation. Dengan demikian, akses tidak hanya bergantung pada `mount()` atau visibilitas UI.

### Vendor Compliance

`VendorComplianceIncidentPolicy` mengatur:

- melihat daftar dan detail incident;
- mengedit tindak lanjut Purchasing;
- menolak create dan delete manual karena incident dibuat otomatis dari rejected quantity Receiving.

Resource mendelegasikan `viewAny`, `view`, dan `edit` kepada Policy.

### Batasan branch yang ditemukan

`goods_receipts.branch_id` menyimpan numeric branch ID dari ESB, bukan foreign key local `branches.id`. Karena itu `User::canAccessBranch()` tidak diterapkan pada record ini agar tidak menciptakan pembatasan yang salah. Penyelarasan akses cabang Receiving menunggu shared mapping local branch → Company Code → ESB Branch ID/Code pada scope berikutnya.

### Validasi

```text
Receiving/Vendor Compliance dan Policy: 11 test lulus, 117 assertions
Full suite: 760 test lulus, 4.379 assertions, 0 gagal
Pint: lulus
```

## 14. Mapping Cabang Lokal dan ESB untuk Receiving

Receiving sekarang membedakan identitas cabang secara eksplisit:

- `goods_receipts.esb_branch_id` menyimpan numeric Branch ID dari ESB;
- `goods_receipts.local_branch_id` menyimpan foreign key cabang lokal;
- `branch_esb_codes.esb_branch_id` melengkapi pasangan ESB Company Code dan Branch Code yang sudah ada.

Migration mempertahankan nilai `goods_receipts.branch_id` lama dengan mengganti nama kolomnya menjadi `esb_branch_id`. Tidak ada ID transaksi lama yang dibuang.

### Resolver bersama

`EsbBranchMappingResolver` mencari mapping dengan kombinasi Company Code dan numeric ESB Branch ID. Jika respons ESB juga membawa Branch Code, resolver dapat melengkapi numeric ID pada mapping lama yang sebelumnya hanya menyimpan Company Code dan Branch Code.

### Pembatasan akses Receiving

- Goods Receipt baru menyimpan local branch hasil mapping ketika dibuat.
- User cabang hanya dapat membuat Receiving untuk cabang PO yang terhubung dan dapat diakses.
- List dan detail Back Office memakai `local_branch_id`, bukan membandingkan numeric ESB ID dengan primary key cabang lokal.
- Receipt historis yang belum mempunyai `local_branch_id` tetap dapat ditemukan melalui master mapping Company Code + ESB Branch ID.
- User dengan akses seluruh cabang tetap dapat melihat receipt historis yang belum terpetakan sehingga data tersebut dapat diperbaiki.

Master Branch sekarang menyediakan kolom `ESB Branch ID` pada daftar mapping ESB. Nilainya berasal dari API Master Company - Branch.

### Validasi

```text
Mapping/Receiving/Vendor Compliance: 23 test lulus, 152 assertions
Full suite: 766 test lulus, 4.392 assertions, 0 gagal
Pint: lulus
```

## 15. Sinkronisasi Master Branch ESB

Master Branch sekarang menyediakan action `Sync Branch ESB` bagi user yang memiliki permission `edit branches`.

### Alur sinkronisasi

1. Sistem mengelompokkan mapping aktif berdasarkan Company Code.
2. Setiap Company Code mengambil data terbaru dari `GET /branch` menggunakan access token ESB Core.
3. Cache daftar branch lama dihapus sebelum request agar sync tidak memakai data stale.
4. Data dicocokkan secara case-insensitive menggunakan Company Code + Branch Code.
5. Numeric Branch ID dan waktu sinkronisasi disimpan pada `branch_esb_codes`.

Satu request `/branch` digunakan untuk seluruh mapping dalam Company Code yang sama. Kegagalan satu Company Code tidak menghentikan sinkronisasi Company Code lainnya.

### Perlindungan data

- Branch Code yang tidak ditemukan hanya dilaporkan.
- Hasil duplikat dianggap ambigu dan tidak mengubah mapping.
- Branch ID kosong atau tidak valid tidak menimpa nilai lama.
- Credential yang belum dikonfigurasi dilaporkan per Company Code.
- Action menampilkan ringkasan jumlah diperbarui, tetap, tidak ditemukan, ambigu, dan gagal.

Kolom `esb_synced_at` ditambahkan untuk menunjukkan kapan setiap mapping terakhir berhasil diverifikasi. Form Master Branch menampilkan nilai tersebut sebagai informasi read-only.

### Validasi

```text
Sync Branch ESB dan integrasi Receiving: 25 test lulus, 181 assertions
Full suite: 770 test lulus, 4.434 assertions, 0 gagal
Pint: lulus
Network eksternal dalam test: diblokir; seluruh respons ESB memakai HTTP fake
```

## 16. Fondasi Client ESB Core — Item Journal

Audit service ESB membagi integrasi menjadi empat keluarga:

| Keluarga | Contoh | Keputusan |
| --- | --- | --- |
| ESB Core multi-company | Item Journal, Goods Receipt, Stock Movement, Company Product | Dipindahkan bertahap ke `EsbCoreClient` |
| ESB Core credential global lama | BOM dan sebagian Master Product pada `EsbCoreService` | Belum diubah dalam scope ini |
| API sales legacy dengan token statis | Sales, payment, promotion legacy | Tidak dicampur dengan ESB Core |
| Master Product API terpisah | Picker katalog Master Product | Tidak dicampur dengan ESB Core |

### EsbCoreClient

`EsbCoreClient` sekarang menjadi fondasi komunikasi ESB Core multi-company untuk:

- normalisasi Company Code;
- pembacaan credential hanya melalui `config/esb.php` yang bersumber dari environment;
- login dan cache access token per Company Code;
- atomic lock saat token belum tersedia;
- refresh token maksimal satu kali setelah respons `401`;
- connect timeout dan request timeout terpusat;
- request JSON untuk GET, POST, PUT, PATCH, dan DELETE;
- request multipart yang dapat dibangun ulang setelah `401`;
- parsing respons dan error dengan context Company Code + endpoint;
- konversi connection error menjadi pesan operasional yang konsisten.

Konfigurasi `ESB_CORE_CONNECT_TIMEOUT` ditambahkan dengan default 10 detik. Credential tetap berada di environment dan tidak dipindahkan ke database.

### Migrasi Item Journal

`EsbItemJournalService` tetap mempertahankan seluruh kontrak method, filter, pagination, payload, hasil create, dan attachment. Kode login, token cache, retry `401`, HTTP request, dan parsing error dipindahkan ke client bersama.

`EsbStockMovementService` merupakan turunan Item Journal service sehingga memperoleh transport client yang sama. Semua instansiasi langsung di Stock Card diganti dengan container Laravel agar dependency injection bekerja dan service dapat di-mock pada test.

### Validasi

```text
Client/Item Journal/Stock Movement/Stock Card: 80 test lulus, 387 assertions
Full suite: 777 test lulus, 4.453 assertions, 0 gagal
Pint: lulus
Network eksternal: diblokir; test client memakai HTTP fake termasuk connection failure
```

Satu kelompok kegagalan sementara ditemukan setelah constructor injection: delapan test Stock Card gagal karena production dan fixture lama memakai `new EsbStockMovementService`. Kegagalan diklasifikasikan sebagai direct-instantiation dependency defect dan diperbaiki dengan resolusi melalui service container; requirement production tidak diubah.

## 17. Migrasi Goods Receipt ke EsbCoreClient

`EsbGoodsReceiptService` sekarang memakai transport bersama untuk:

- daftar Purchase Order;
- detail Purchase Order;
- daftar Location berdasarkan ESB Branch ID;
- create Goods Receipt.

Kontrak method, Company Code `BLSS`, filter, endpoint, payload, hasil create, dan raw response yang disimpan untuk audit tidak berubah. Duplikasi login, token cache, atomic lock, refresh `401`, timeout, dan parsing error dihapus dari service ini.

Test transport baru memastikan seluruh kontrak tersebut tetap sama dan error menyertakan Company Code serta endpoint terkait.

### Validasi

```text
Goods Receipt dan shared client: 19 test lulus, 134 assertions
Full suite: 780 test lulus, 4.464 assertions, 0 gagal
Pint: lulus
Network eksternal: diblokir; transport Goods Receipt memakai HTTP fake
```

## 18. Phase A — Pemisahan Stock Movement dari Item Journal

`EsbStockMovementService` tidak lagi mewarisi `EsbItemJournalService`. Service tersebut sekarang menerima `EsbCoreClient` langsung melalui constructor injection sehingga hubungan domain Stock Movement dan Item Journal sudah dipisahkan.

### Perubahan transport dan katalog

- Request `GET /report/stock-movement` dipanggil langsung melalui `EsbCoreClient`.
- Request katalog `GET /product/list` dimiliki oleh helper private Stock Movement.
- Login, cache token per Company Code, refresh satu kali setelah `401`, timeout, connection failure, dan parsing error tetap ditangani client bersama.
- Tidak ada perubahan pada consumer Filament, `StockCardEsbSynchronizer`, route, UI, atau payload.

### Kontrak yang dipertahankan

- cache kategori `stock-movement.categories.{company}` dengan TTL enam jam;
- cache rolling catalog `stock-movement.catalog.v4:*` dengan TTL lima menit;
- pagination eksplisit `page` dan `limit=100`, termasuk guard data kosong serta batas 1.000 halaman;
- category mapping `productCode → categoryName`;
- filter `flagActive=1` pada Master Product;
- pemilihan satu mapping Company Code dan Branch Code aktif dari Master Branch;
- agregasi saldo terakhir per lokasi, pemisahan unit, dan seluruh tipe transaksi;
- pesan validasi mapping, pagination, branch mismatch, respons ESB, dan connection failure.

### Characterization test

Test baru membuktikan:

1. Stock Movement meng-inject `EsbCoreClient` dan bukan subclass Item Journal.
2. Seluruh halaman Master Product diambil dan hasil kategori masuk ke cache key existing.
3. Hasil Stock Movement kosong tetap menghasilkan struktur rows, transactions, units, dan types yang kompatibel.
4. Respons `422` mempertahankan detail ESB, Company Code, dan endpoint.
5. Connection failure mempertahankan error operasional dari shared client.
6. Test `401`, pagination Stock Movement, mapping cabang, cache katalog, dan seluruh consumer existing tetap lulus.

### Validasi

```text
EsbStockMovementService: 14 test lulus, 46 assertions
Stock Movement dan seluruh consumer Stock Card: 57 test lulus, 233 assertions
Full suite (php -d memory_limit=512M artisan test --compact): 899 test lulus, 4.936 assertions, 0 gagal
Pint: lulus
Network eksternal: diblokir; seluruh request ESB pada test memakai HTTP fake
```

Tidak ada migration, perubahan environment, atau langkah deployment khusus pada Phase A. Deployment mengikuti prosedur aplikasi biasa dan rollback dapat dilakukan dengan me-revert commit Phase A tanpa mengubah data.

## 19. Phase B — Migrasi Company Product ke EsbCoreClient

`EsbCompanyProductService` sekarang menerima `EsbCoreClient` melalui constructor injection. Login, cache access token per Company Code, refresh satu kali setelah `401`, timeout, transport JSON, connection failure, serta parsing respons/error tidak lagi diduplikasi di service Company Product.

### Kontrak yang dipertahankan

- public method `taxonomy`, `suggestNextProductCode`, `create`, dan `update`;
- Company Code dinamis dengan allowlist yang sama;
- endpoint `GET /product/list`, `POST /product`, dan `PUT /product/{id}`;
- pagination katalog `limit=100`, `flagActive=1`, serta guard maksimum 100 halaman;
- cache key `esb_core.product_taxonomy.v3.{comcode}` dengan TTL enam jam;
- pemetaan category, subcategory, product code, dan algoritma suggestion;
- payload create/update termasuk `productDetails`, `productDetailID`, `uomID`, dan SKU;
- hasil create berbentuk `productID` dan `isTemp`, serta return `void` untuk update;
- UI dan alur `SubmitBulkProductAction`.

Resource Filament sebelumnya memakai `new EsbCompanyProductService`. Pemanggilan tersebut diubah ke Laravel service container agar dependency injection bekerja tanpa mengubah perilaku form.

Service existing tidak memiliki endpoint read detail produk tersendiri. Requirement detail diuji pada isi `productDetails` create/update dan ID detail per Company Code. Tidak ada public method baru yang dibuat.

### Kebijakan mutation dan error

- Respons eksplisit `401` tetap memicu refresh token dan satu pengiriman ulang.
- Connection failure atau timeout tidak memicu retry mutation karena hasil create/update dapat tidak pasti.
- Error validasi ESB mempertahankan seluruh pesan dan sekarang memiliki context Company Code serta endpoint.
- Credential kosong gagal sebelum request produk dikirim.
- Seluruh network eksternal diblokir pada test.

### Validasi

```text
EsbCompanyProductService: 8 test lulus, 21 assertions
Bulk Product consumer langsung: 17 test lulus, 124 assertions
Seluruh test consumer R&D/Product: 197 test lulus, 1.073 assertions
Full suite (php -d memory_limit=512M artisan test --compact): 907 test lulus, 4.957 assertions, 0 gagal
Pint: lulus
```

Tidak ada migration atau perubahan environment pada Phase B. Deployment mengikuti prosedur aplikasi biasa. Rollback dilakukan dengan me-revert commit Phase B; tidak ada data yang perlu dikembalikan.

## 20. Phase C1 — Ekstraksi Purchase Order dari EsbCoreService

Audit `EsbCoreService` menemukan tiga kelompok domain dengan credential global lama:

| Domain | Public method |
| --- | --- |
| Purchase Order | `getPurchaseOrders`, `getPurchaseOrder` |
| Master Product | `getProducts`, `getAllProducts`, `findProductByExactName`, `findProductById`, `getProductTaxonomy`, `suggestNextProductCode`, `createProduct`, `updateProduct` |
| Bill of Material | `getBillOfMaterials`, `getAllBillOfMaterials`, `getBillOfMaterial`, `createAssembly`, `updateBillOfMaterial` |

Credential global `esb.core.username/password` belum terbukti mewakili Company Code tertentu. Karena itu credential tersebut tidak dipaksakan ke `EsbCoreClient` multi-company.

### Perubahan C1

- `EsbGlobalCoreClient` menjadi transport kompatibel untuk credential global lama.
- Cache token tetap memakai `esb_core.access_token` dan lock `esb_core.login_lock`.
- Login, timeout, bearer token, refresh satu kali setelah `401`, parsing result, dan error text Purchase Order dipertahankan.
- `EsbPurchaseOrderService` memiliki endpoint list dan detail Purchase Order.
- `PurchaseOrderPriceSyncService` sekarang meng-inject `EsbPurchaseOrderService`.
- Method Purchase Order pada `EsbCoreService` tetap tersedia sebagai compatibility delegation selama Phase C berjalan.
- Tidak ada perubahan route, UI, schema, payload, filter, pagination, atau data lokal.

### Characterization test C1

Test mencakup:

1. credential global dan bearer token;
2. filter, default sort, batas pagination, dan normalisasi response list;
3. raw detail Purchase Order dan URL encoding nomor PO;
4. cache key token existing;
5. refresh token satu kali setelah `401`;
6. validation error `422` tanpa retry;
7. credential kosong sebelum request;
8. connection failure tanpa retry tersembunyi;
9. consumer Product Price Index dengan dependency domain baru;
10. compatibility method pada `EsbCoreService`.

Phase C belum selesai. Langkah berikutnya adalah mengekstrak Master Product, lalu Bill of Material, memigrasikan seluruh consumer, dan menghapus compatibility facade setelah reference audit serta full suite lulus.

### Validasi C1

```text
Purchase Order, compatibility facade, dan Product Price consumer: 14 test lulus, 44 assertions
Full suite (php -d memory_limit=512M artisan test --compact): 913 test lulus, 4.972 assertions, 0 gagal
Pint: lulus
Network eksternal: diblokir; seluruh request test memakai HTTP fake
```

## 21. Phase C2 — Ekstraksi Master Product dari EsbCoreService

`EsbMasterProductService` sekarang menjadi pemilik kontrak Master Product credential global lama:

- list dan filter produk;
- pagination seluruh produk maksimal 500 halaman;
- lookup exact name dan Product ID;
- taxonomy category/subcategory;
- suggestion kode produk dan perlindungan terhadap numeric outlier;
- create dan update Master Product.

`EsbGlobalCoreClient` memperoleh operasi concurrent GET pool. Perilaku taxonomy existing dipertahankan: halaman pertama menentukan jumlah halaman, halaman berikutnya diambil dalam kelompok maksimal sepuluh request, halaman pool yang gagal dilewati, dan hasil disimpan selama enam jam dengan cache key `esb_core.product_taxonomy`.

Method Product pada `EsbCoreService` sekarang hanya compatibility delegation. Consumer production belum dipindahkan dalam C2 agar perubahan domain ownership dan migrasi consumer dapat direview terpisah. Tidak ada perubahan route, UI, payload, response, credential, atau environment.

### Characterization test C2

Test baru mengunci:

1. seluruh filter, batas limit, pagination, dan normalization list;
2. compatibility delegation dari `EsbCoreService`;
3. pagination semua produk, exact-name lookup, dan ID lookup;
4. concurrent taxonomy pool dan cache key existing;
5. create/update payload dan response mapping;
6. refresh token satu kali setelah `401`;
7. connection failure dan validation `422` tanpa retry mutation;
8. respons sukses create tanpa Product ID tetap ditolak.

Phase C masih berjalan. Berikutnya adalah C3 ekstraksi Bill of Material, lalu C4 migrasi seluruh consumer serta penghapusan compatibility facade.

### Validasi C2

```text
EsbMasterProductService dan compatibility facade: 12 test lulus, 34 assertions
Master Product dan seluruh consumer Product/R&D terkait: 68 test lulus, 416 assertions
Full suite (php -d memory_limit=512M artisan test --compact): 921 test lulus, 4.993 assertions, 0 gagal
Pint: lulus
Network eksternal: diblokir; seluruh request test memakai HTTP fake
```
