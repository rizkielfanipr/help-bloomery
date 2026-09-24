# Roadmap Konsolidasi Integrasi ESB

## 1. Identitas dokumen

| Field | Nilai |
| --- | --- |
| Baseline | 23 September 2026 |
| Status | Aktif — sebagian fondasi sudah diimplementasikan |
| Sasaran | Seluruh integrasi ESB memiliki autentikasi, timeout, error, retry, cache, observability, dan aturan mutation yang konsisten |
| Stack | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Pest 4 |
| Dokumen induk | `docs/code-remediation-prd.md` Phase 4 |
| Laporan implementasi | `docs/code-remediation-phase-3-report.md` bagian 14–17 |

Dokumen ini menjadi acuan khusus untuk pekerjaan integrasi ESB. Perubahan harus dilakukan bertahap, mempertahankan kontrak UI dan payload existing, menggunakan HTTP fake pada test, dan dibuat dalam commit kecil per service atau concern.

## 2. Tujuan

1. Menghilangkan duplikasi login, cache token, refresh `401`, timeout, dan parsing error.
2. Memisahkan transport HTTP dari proses bisnis setiap domain.
3. Mencegah retry mutation menghasilkan transaksi ganda.
4. Memberikan error operasional yang menyebut Company Code dan endpoint tanpa membocorkan credential atau token.
5. Menjaga data antar-Company Code dan cabang tidak tercampur.
6. Membuat seluruh request ESB dapat diuji tanpa network eksternal.
7. Menyediakan fondasi untuk queue, reconciliation, monitoring, dan API aplikasi pada tahap berikutnya.

## 3. Batasan arsitektur

Integrasi eksternal saat ini terbagi menjadi empat keluarga. Keluarga tersebut tidak boleh digabung hanya karena sama-sama memakai nama ESB.

| Keluarga | Contoh | Strategi |
| --- | --- | --- |
| ESB Core multi-company | Item Journal, Receiving, Stock Movement, Company Product | Gunakan `EsbCoreClient` dengan credential per Company Code |
| ESB Core credential global lama | BOM dan sebagian Master Product pada `EsbCoreService` | Audit dan migrasikan setelah multi-company stabil |
| API sales legacy | Sales, payment, promotion legacy dengan token statis | Pertahankan client terpisah; standardisasi kebijakan tanpa memaksakan login ESB Core |
| Master Product API terpisah | Picker dan katalog Master Product | Pertahankan client terpisah dengan contract test sendiri |

Target hubungan class:

```text
Filament Page / Action / Job
    → Domain Integration Service
        → EsbCoreClient
            → Laravel HTTP Client
            → token cache per Company Code
            → ESB Core API
```

`EsbCoreClient` hanya menangani transport. Filter purpose, pagination produk, pemetaan respons, payload GR, perhitungan Stock Movement, dan proses bisnis lain tetap berada pada service domain.

## 4. Perubahan yang sudah selesai

### 4.1 Baseline test eksternal

- Test suite memblokir request HTTP yang tidak di-fake.
- Seluruh test ESB menggunakan `Http::fake()` atau mock service.
- Konfigurasi memory full suite terdokumentasi sebagai `php -d memory_limit=512M artisan test --compact`.

### 4.2 Mapping cabang lokal dan ESB

- `goods_receipts.esb_branch_id` menyimpan numeric Branch ID ESB.
- `goods_receipts.local_branch_id` menyimpan foreign key cabang lokal.
- `branch_esb_codes` menyimpan Company Code, Branch Code, numeric Branch ID, status aktif, dan waktu sync.
- Data Receiving lama tetap menggunakan numeric ID lama setelah rename kolom.
- `EsbBranchMappingResolver` memetakan Company Code + Branch ID atau Branch Code ke cabang lokal.
- Policy dan query Receiving memakai cabang lokal, bukan membandingkan ID lokal dengan ID ESB.

### 4.3 Sinkronisasi Master Branch

- Action **Sync Branch ESB** tersedia di Master Branch bagi user dengan permission `edit branches`.
- Sistem memanggil `GET /branch` sekali per Company Code aktif.
- Branch Code dicocokkan secara case-insensitive.
- Mapping hilang, ambigu, atau memiliki ID tidak valid tidak menimpa nilai lama.
- Kegagalan satu Company Code tidak menghentikan Company Code lain.
- Waktu keberhasilan disimpan pada `esb_synced_at`.

### 4.4 `EsbCoreClient`

Client bersama sudah menangani:

- normalisasi Company Code;
- credential dari `config/esb.php`/environment;
- token cache per Company Code;
- atomic lock ketika beberapa request membutuhkan login bersamaan;
- refresh token maksimal satu kali setelah `401`;
- `connect_timeout` dan request `timeout`;
- GET, POST, PUT, PATCH, dan DELETE JSON;
- multipart attachment yang dapat dibangun ulang setelah refresh token;
- parsing respons sukses dan error;
- error dengan context Company Code + endpoint;
- konversi connection failure menjadi error operasional.

Environment baru yang didukung:

```env
ESB_CORE_CONNECT_TIMEOUT=10
ESB_CORE_TIMEOUT=60
ESB_CORE_TOKEN_TTL=3300
```

### 4.5 Consumer yang sudah memakai client bersama

#### Item Journal

- Branch
- Location
- Purpose
- Product list
- Create Item Journal
- Upload attachment
- Delete attachment

Kontrak method, filter, pagination, payload, dan response existing dipertahankan.

#### Goods Receipt / Receiving

- Purchase Order list
- Purchase Order detail
- Location
- Create Goods Receipt

Company Code `BLSS`, endpoint, payload, raw response audit, dan perilaku UI dipertahankan.

#### Stock Movement

Phase A selesai. `EsbStockMovementService` sekarang meng-inject `EsbCoreClient` secara langsung dan tidak lagi mewarisi `EsbItemJournalService`. Transport Stock Movement dan katalog Master Product berada di service domain Stock Movement, sedangkan seluruh consumer production tetap menggunakan Laravel service container.

Kontrak berikut dipertahankan:

- cache key kategori `stock-movement.categories.{company}`;
- cache key katalog rolling `stock-movement.catalog.v4:*`;
- pagination numerik maksimal 100 halaman per request dan guard 1.000 halaman;
- mapping `productCode` ke `categoryName`;
- filter Company Code, Branch Code, unit, periode, dan batas 100 data;
- aggregation saldo, tipe transaksi, serta error operasional existing.

### 4.6 Commit terkait

| Commit | Perubahan |
| --- | --- |
| `80a9fa7` | Mapping cabang lokal dan ESB untuk Receiving |
| `5fd530d` | Sinkronisasi Master Branch ESB |
| `47aa59e` | Fondasi `EsbCoreClient` dan migrasi Item Journal |
| `f6496ad` | Migrasi Goods Receipt ke client bersama |

### 4.7 Baseline validasi terakhir

```text
Full suite: 780 test lulus
Assertions: 4.464
Failure: 0
Pint: lulus
Network eksternal: diblokir pada test
```

## 5. Kontrak yang wajib dipertahankan

Setiap scope berikutnya harus menjaga:

- URL dan route aplikasi;
- permission serta branch scope;
- Company Code yang digunakan setiap fitur;
- nama method public service selama consumer belum ikut dimigrasikan;
- parameter filter dan pagination;
- struktur payload mutation;
- format data yang dikonsumsi Page/Resource;
- raw response yang dibutuhkan audit;
- cache key existing sampai ada migration cache yang disengaja;
- pesan bisnis penting bagi pengguna;
- data historis dan attachment existing.

Perubahan kontrak hanya boleh dilakukan dengan requirement terpisah dan test migrasi yang jelas.

## 6. Pekerjaan tersisa dan urutannya

### Phase A — Pisahkan Stock Movement dari Item Journal — selesai

**Masalah:** `EsbStockMovementService` mewarisi `EsbItemJournalService` hanya untuk memakai HTTP transport dan category endpoint. Hubungan domain ini tidak tepat.

**Pekerjaan:**

1. Inject `EsbCoreClient` langsung ke `EsbStockMovementService`.
2. Pindahkan helper request Stock Movement ke service tersebut atau service katalog yang sesuai.
3. Hilangkan inheritance dari Item Journal.
4. Pertahankan cache key katalog dan rolling products.
5. Pastikan semua consumer tetap memakai container.
6. Tambahkan contract test untuk pagination hingga semua halaman, category mapping, transaksi kosong, `401`, `422`, dan connection failure.

**Hasil:** Stock Movement tidak lagi bergantung pada Item Journal. Characterization test mencakup direct dependency, pagination seluruh halaman, category mapping/cache, transaksi kosong, refresh `401`, validasi `422`, dan connection failure. Seluruh test Stock Card tetap hijau.

**Validasi:**

```text
Stock Movement dan seluruh consumer Stock Card: 57 test lulus, 233 assertions
Full suite (memory_limit=512M): 899 test lulus, 4.936 assertions, 0 gagal
Pint: lulus
Network eksternal: diblokir; seluruh request memakai HTTP fake
```

### Phase B — Migrasikan Company Product — selesai

**Target:** `EsbCompanyProductService`.

**Pekerjaan:**

1. Karakterisasi product list, create, update, dan mapping unit.
2. Ganti login/token/request/error internal dengan `EsbCoreClient`.
3. Pertahankan Company Code dinamis.
4. Verifikasi create/update tidak otomatis di-retry setelah respons tidak pasti.
5. Tambahkan test credential kosong, `401`, validation error ESB, dan raw response.

**Hasil:** `EsbCompanyProductService` sekarang meng-inject `EsbCoreClient`. Duplikasi login, token cache, refresh `401`, timeout, transport HTTP, dan parsing error telah dihapus. Public method, taxonomy/cache key, Company Code dinamis, payload `productDetails`, hasil create, dan perilaku UI tetap dipertahankan. Consumer Resource yang sebelumnya membuat service secara langsung sekarang memakai service container.

API Company Product existing tidak mempunyai public method atau endpoint read detail tersendiri. Cakupan “detail” dikarakterisasi pada `productDetails`, `productDetailID`, `uomID`, dan SKU dalam payload create/update. Tidak ada kontrak produksi baru yang ditambahkan.

Create/update hanya diulang satu kali setelah respons eksplisit `401`. Connection failure atau timeout dengan hasil mutation yang tidak pasti langsung dihentikan dan tidak di-retry.

**Validasi:**

```text
EsbCompanyProductService: 8 test lulus, 21 assertions
Seluruh test consumer R&D/Product: 197 test lulus, 1.073 assertions
Full suite (memory_limit=512M): 907 test lulus, 4.957 assertions, 0 gagal
Pint: lulus
Network eksternal: diblokir; seluruh request memakai HTTP fake
```

### Phase C — Audit dan pecah `EsbCoreService` — selesai

**Masalah:** class ini menangani terlalu banyak endpoint dengan credential global lama.

**Pekerjaan:**

1. Inventaris seluruh public method dan consumer.
2. Kelompokkan endpoint menjadi BOM, Master Product, Purchase Order, dan domain lain.
3. Pastikan apakah credential global mewakili Company Code tertentu atau akun lintas-company.
4. Buat characterization test sebelum memindahkan setiap kelompok.
5. Ekstrak domain service satu per satu.
6. Gunakan `EsbCoreClient` hanya bila model autentikasinya benar-benar kompatibel.
7. Hapus method lama setelah tidak ada reference dan full suite lulus.

**Selesai jika:** tidak ada god service ESB dan seluruh endpoint mempunyai owner domain yang jelas.

**Status implementasi:** subphase C1 Purchase Order, C2 Master Product, dan C3 Bill of Material selesai. Ketiga domain memakai `EsbGlobalCoreClient` dengan credential, cache token, dan perilaku autentikasi global existing. `EsbCoreService` tidak lagi memiliki HTTP transport atau proses bisnis dan hanya menjadi compatibility facade sampai seluruh consumer dimigrasikan.

Kelompok yang masih berada pada `EsbCoreService`:

1. Compatibility delegation untuk Purchase Order, Master Product, dan Bill of Material.
2. Migrasi consumer ke service domain masing-masing.
3. Penghapusan `EsbCoreService` setelah reference audit dan full suite lulus.

**Hasil C4:** seluruh production consumer dan test sudah memakai `EsbPurchaseOrderService`, `EsbMasterProductService`, atau `EsbBillOfMaterialService`. Compatibility facade `EsbCoreService` telah dihapus dan reference audit pada `app/` serta `tests/` bersih.

```text
Migrasi consumer: 78 test lulus, 461 assertions
Full suite (memory_limit=512M): 927 test lulus, 5.008 assertions, 0 gagal
Pint: lulus
```

Validasi C3:

```text
Bill of Material dan seluruh consumer R&D/BOM utama: 87 test lulus, 389 assertions
Full suite (memory_limit=512M): 927 test lulus, 5.008 assertions, 0 gagal
Pint: lulus
```

Validasi C1:

```text
Purchase Order, compatibility facade, dan Product Price consumer: 14 test lulus, 44 assertions
Full suite (memory_limit=512M): 913 test lulus, 4.972 assertions, 0 gagal
Pint: lulus
```

Implementasi C2 mempertahankan concurrent pool taxonomy per 10 halaman, cache key `esb_core.product_taxonomy`, batas pagination, algoritma code suggestion, payload mutation, dan response mapping existing.

Validasi C2:

```text
Master Product dan seluruh consumer Product/R&D terkait: 68 test lulus, 416 assertions
Full suite (memory_limit=512M): 921 test lulus, 4.993 assertions, 0 gagal
Pint: lulus
```

### Phase D — Standardisasi retry mutation dan idempotency — berjalan

**Target awal:** Create Item Journal, Create Goods Receipt, Create/Update Product, Create/Update BOM.

**Aturan:**

- GET boleh di-retry untuk connection failure dan error server dengan batas rendah.
- Mutation tidak boleh di-retry otomatis hanya karena timeout; server mungkin sudah memproses transaksi.
- `401` boleh mengulang maksimal sekali karena request pertama ditolak sebelum diproses.
- Setiap mutation membutuhkan local reference/idempotency key bila API mendukung.
- Jika API tidak mendukung idempotency, simpan status `processing`, payload hash, waktu request, dan hasil reconciliation.

**Pekerjaan:**

1. Definisikan klasifikasi read vs mutation pada client.
2. Tambahkan payload hash dan duplicate guard lokal.
3. Tambahkan status `unknown` atau reconciliation state bila hasil timeout tidak pasti.
4. Buat command/job untuk memeriksa transaksi yang hasilnya tidak diketahui.
5. Uji double-click, duplicate job, timeout sesudah request terkirim, dan retry worker.

**Status implementasi:** D1 Item Journal selesai. Setiap form memperoleh UUID submission baru, database menahan submission key duplikat, dan record menyimpan hash payload serta waktu percobaan. Submission kedua dengan key yang sama tidak mengirim mutation ulang. Connection failure sebelum hasil ESB dapat dipastikan diberi status `unknown` untuk rekonsiliasi manual. D2 Goods Receipt dan mutation Product/BOM masih tersisa.

### Phase E — Error taxonomy dan result object

**Pekerjaan:**

1. Definisikan exception terpisah untuk configuration, authentication, connection, validation, rate limit, server error, dan unknown response.
2. Simpan context aman: Company Code, method, endpoint, HTTP status, ESB code, correlation ID.
3. Jangan menyimpan token, password, attachment binary, atau payload sensitif ke log.
4. Petakan exception ke pesan UI yang singkat dan pesan log yang detail.
5. Gunakan result object hanya pada workflow yang membutuhkan status parsial; jangan membungkus semua array tanpa nilai.

### Phase F — Observability dan audit integrasi

**Pekerjaan:**

1. Tambahkan correlation/request ID lokal per call.
2. Catat durasi, Company Code, endpoint alias, status, dan attempt tanpa credential.
3. Tambahkan tabel/log terstruktur untuk mutation penting bila diperlukan.
4. Buat metrik jumlah sukses, gagal, timeout, `401`, dan latency.
5. Buat halaman monitoring read-only setelah data observability tersedia.
6. Tentukan retention log agar shared hosting tidak kehabisan inode.

### Phase G — Cache master data

**Target:** Branch, Purpose, Location, Product, Category, UOM, dan transaction type.

**Pekerjaan:**

1. Definisikan key dengan Company Code dan parameter cabang/lokasi.
2. Tetapkan TTL per jenis data.
3. Sediakan force refresh dan timestamp terakhir berhasil.
4. Pertahankan last-known-good ketika ESB sementara gagal untuk data read-only yang aman.
5. Jangan memakai fallback cache untuk mutation atau validation yang harus real-time.
6. Uji isolasi Company Code dan invalidation.

### Phase H — Queue dan background processing

**Kandidat:**

- refresh Stock Movement;
- sinkronisasi katalog produk/category;
- upload attachment setelah transaksi utama;
- reconciliation mutation;
- sync seluruh Master Branch;
- export besar.

**Pekerjaan:**

1. Tentukan job idempotent per use case.
2. Atur timeout, tries, exponential backoff, dan `failed()`.
3. Gunakan unique lock untuk sync yang sama.
4. Simpan progress dan error yang dapat dibaca UI.
5. Pastikan request browser tidak menunggu pekerjaan berat.
6. Siapkan scheduler dan worker sesuai kemampuan hosting/VPS.

### Phase I — API legacy dan Master Product

**Pekerjaan:**

1. Audit `EsbService`, `EsbPromotionService`, dan Master Product client secara terpisah.
2. Buat client per keluarga autentikasi, bukan memakai `EsbCoreClient` secara paksa.
3. Standardisasi timeout, error context, logging, dan HTTP fake.
4. Pertahankan token statis dan header khusus sesuai kontrak API masing-masing.
5. Hapus HTTP mentah dari Page setelah seluruh consumer memakai service domain.

### Phase J — Cleanup dan enforcement

1. Cari sisa `/auth/login`, `withToken`, base URL, dan parsing error yang berada di luar client yang sah.
2. Hapus helper lama setelah reference audit.
3. Tambahkan architecture test agar Page/Blade tidak memakai Laravel HTTP facade.
4. Tambahkan test agar service ESB domain menggunakan client yang sesuai.
5. Perbarui `docs/architecture.md`, `docs/setup.md`, dan laporan remediation.
6. Jalankan full suite dan audit route sebelum menutup phase.

## 7. Strategi commit

Gunakan satu concern per commit:

```text
1. Decouple Stock Movement from Item Journal transport
2. Use shared ESB client for Company Product
3. Extract BOM integration service
4. Add mutation idempotency guard for Item Journal
5. Add mutation reconciliation for Goods Receipt
6. Add ESB integration observability
7. Queue Stock Movement catalog refresh
8. Enforce ESB architecture boundaries
```

Setiap commit wajib:

1. Dimulai dari working tree bersih.
2. Mempunyai characterization test untuk kontrak yang dipindahkan.
3. Menjalankan test file terkait.
4. Menjalankan Pint bila ada PHP yang berubah.
5. Menjalankan full suite dengan memory 512 MB.
6. Memperbarui laporan implementasi.
7. Dipush hanya setelah seluruh test lulus.

## 8. Matriks test minimum

| Area | Skenario wajib |
| --- | --- |
| Token | cache hit, login baru, concurrent lock, TTL |
| Authentication | `401` lalu berhasil, `401` kedua tetap gagal |
| Configuration | Company Code kosong, credential tidak tersedia |
| Network | connect failure, timeout |
| Response | sukses, JSON tidak valid, `status=fail`, error array, HTTP 4xx/5xx |
| Pagination | satu halaman, banyak halaman, next invalid, batas maksimum |
| Isolation | token/cache/data tidak tercampur antar-Company Code |
| Multipart | upload berhasil, `401`, retry membangun ulang file |
| Mutation | duplicate submit, timeout tidak pasti, reconciliation |
| Authorization | permission dan branch scope server-side |
| Regression | UI, payload, route, status, dan data historis tetap sama |

## 9. Deployment

### Deployment commit client saja

Commit `47aa59e` dan `f6496ad` tidak membawa migration database. Deployment:

```bash
git pull origin main
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pastikan environment berikut tersedia untuk setiap Company Code yang digunakan:

```env
ESB_CORE_BASE_URL=https://services.esb.co.id/core
ESB_CORE_CONNECT_TIMEOUT=10
ESB_CORE_TIMEOUT=60
ESB_CORE_TOKEN_TTL=3300

ESB_CORE_BLSS_USERNAME=
ESB_CORE_BLSS_PASSWORD=
ESB_CORE_BLO6_USERNAME=
ESB_CORE_BLO6_PASSWORD=
```

Company Code lain mengikuti key pada `config/esb.php`.

### Deployment mapping cabang sebelumnya

Jika server belum menerima commit mapping cabang, migration tetap harus dijalankan:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Setelah itu jalankan **Master → Branch → Sync Branch ESB**.

## 10. Rollback

- Revert satu commit consumer bila terjadi regresi; jangan menghapus `EsbCoreClient` selama masih digunakan consumer lain.
- Jangan mengganti `APP_KEY` atau menghapus credential environment saat rollback.
- Cache token boleh dihapus dengan `php artisan cache:clear`, tetapi perhatikan cache aplikasi lain pada shared cache store.
- Migration mapping cabang menyimpan data historis dan tidak boleh di-rollback tanpa backup serta review schema.
- Untuk error produksi, simpan log, Company Code, endpoint, waktu, dan reference number sebelum rollback.

## 11. Definition of done keseluruhan

Konsolidasi ESB selesai ketika:

- setiap endpoint mempunyai owner domain dan client autentikasi yang tepat;
- tidak ada login/token/parsing error duplikat di domain service;
- tidak ada HTTP call mentah di Blade atau Filament Page;
- retry mutation aman dan mempunyai reconciliation;
- cache terisolasi per Company Code/cabang;
- observability tidak membocorkan secret;
- background job idempotent dan dapat dimonitor;
- seluruh network test di-fake;
- architecture test mencegah pola lama kembali;
- full suite, Pint, dan deployment checklist lulus;
- dokumentasi setup dan operasi production sudah diperbarui.

## 12. Langkah berikutnya yang direkomendasikan

Lanjutkan ke **Phase D2 — Rekonsiliasi mutation Goods Receipt**. Phase D1 telah menambahkan duplicate guard dan status hasil tidak pasti pada Item Journal.

Prompt kerja yang dapat digunakan:

```text
Implementasikan Phase C dari docs/esb-integration-consolidation-roadmap.md.
Audit seluruh public method dan consumer EsbCoreService, lalu kelompokkan endpoint berdasarkan domain tanpa mengubah kontrak produksi.
Buat characterization test sebelum mengekstrak satu kelompok domain dan pastikan model credential-nya kompatibel sebelum memakai EsbCoreClient.
Jalankan test terkait, Pint, lalu full suite dengan memory 512 MB.
Perbarui laporan implementasi dan buat commit terpisah setelah seluruh test lulus.
```
