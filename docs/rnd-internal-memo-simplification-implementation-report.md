# Laporan Implementasi — Penyederhanaan R&D Internal Memo

| Field | Nilai |
| --- | --- |
| Tanggal | 1 Oktober 2026 |
| PRD acuan | `docs/rnd-internal-memo-simplification-prd.md` |
| Status | Phase 0–6 selesai |
| Push/deploy | Belum dilakukan — menunggu instruksi eksplisit |

## 1. Ringkasan hasil akhir

Fitur Memo Internal yang sebelumnya penuh workflow (Draft → Syncing → NeedsAttention → Ready → Finalized → Archived, dengan revisi bernomor dan generate PDF) disederhanakan menjadi workspace tunggal: buat Memo, pilih Menu dari Master Menu ESB (BLSS), BOM Menu dan Assembly-nya langsung diresolusi otomatis saat Menu ditambahkan, hasil akhir ditampilkan sebagai dua kelompok (Bahan dan WIP) lengkap dengan UOM BOM dan Purchase UOM (atau label "belum tersedia" bila kontraknya belum terbukti), dan Minimum Order bisa diisi per item serta tetap tersimpan setelah refresh.

Temuan Phase 0 paling penting: implementasi lama **sudah ada dan lengkap** (dibangun 23 September 2026), bukan rencana semata, dan dua ketentuan wajib PRD baru (BOM via `EsbCoreClient` + credential BLSS, Master Menu via static token BLSS) **sudah otomatis terpenuhi** oleh kode lama. Pekerjaan nyatanya adalah menyederhanakan UI dan menambal gap (depth limit, transaction-safety saat refresh, Minimum Order, Purchase UOM fallback) — bukan membangun dari nol.

## 2. Daftar migration

Keduanya additive-only; tidak ada migration lama yang diubah.

1. `2026_09_30_161805_add_purchase_uom_and_minimum_order_to_rnd_internal_memo_materials_table.php` — menambah `purchase_uom_id`, `purchase_uom_name`, `minimum_order`, `product_synced_at` pada `rnd_internal_memo_materials`, plus index `rnd_memo_materials_product_code_idx`.
2. `2026_09_30_163737_add_product_detail_snapshot_to_rnd_internal_memo_materials_table.php` — menambah `product_detail_snapshot` (json) pada tabel yang sama, dipisah dari `product_snapshot` milik resolver BOM agar tidak saling menimpa.

## 3. Perubahan schema

| Tabel | Kolom baru | Keterangan |
| --- | --- | --- |
| `rnd_internal_memo_materials` | `purchase_uom_id` (nullable) | Null = kontrak belum terbukti, bukan "belum di-fetch" |
| | `purchase_uom_name` (nullable) | sda |
| | `minimum_order` (decimal 18,4 nullable) | Lokal, tidak pernah dikirim ke ESB |
| | `product_synced_at` (timestamp nullable) | Waktu Product terakhir di-enrich |
| | `product_detail_snapshot` (json nullable) | Raw response Product detail, terpisah dari `product_snapshot` (raw komponen BOM) |

Tidak ada kolom lama yang dihapus. Kolom workflow lama (`status`, `revision`, `finalized_*`, `archived_*`, `forecast_quantity`, `shelf_life_*`, dll.) tetap ada di database, tidak lagi dikendalikan oleh UI sederhana.

## 4. Kontrak API final

| Kebutuhan | Endpoint | Autentikasi | Status kontrak |
| --- | --- | --- | --- |
| Daftar Menu | `GET {esb.base_url}/corev1/master/get-menu` | Static token `config('esb.tokens.BLSS')` | Terbukti (field: `menuID`, `menuCode`, `menuName`, `bomID`, `bomName`, `categoryDetail`, `flagActive`) |
| Detail BOM Menu/Assembly | `GET {esb.core.base_url}/product/bom/{bomID}` | `EsbCoreClient` + credential `esb.core.companies.BLSS` | Terbukti (field: `bomID`, `bomCode`, `bomName`, `bomTypeName`, `productID`, `productDetailID`, `productCode`, `bomDetails[]` dengan `productDetailID`/`productID`/`productCode`/`productName`/`categoryName`/`qty`/`uomName`/`uomID`) |
| Cari BOM Assembly untuk WIP | `GET {esb.core.base_url}/product/bom?productName=...` | `EsbCoreClient` + credential BLSS | Terbukti, dibatasi pencarian by name lalu dicocokkan identitas hasil detail |
| Detail Product (Purchase UOM) | `GET {esb.master_product.base_url}/corev1/master/product` | Static token `esb.master_product.token` | **Tidak terbukti untuk Purchase UOM** — field yang ada hanya `unit`, `baseUnit`, `conversionFactor`; tidak ada field "Purchase UOM" eksplisit. Fallback: tampilkan "Purchase UOM belum tersedia", gunakan UOM BOM sebagai referensi tampilan. |

Kategorisasi Bahan/WIP/Packaging masih berbasis heuristik string (`categoryName` mengandung "wip"/"packaging"/"kemasan", atau `productCode` berpola `^BW`) — bukan field enum resmi dari ESB. Ini sudah ada sebelum pekerjaan ini dan tidak diubah.

## 5. Perubahan permission

Tidak ada permission baru dan tidak ada permission lama yang dihapus. Permission existing (`view any rnd internal memo`, `view rnd internal memo`, `create rnd internal memo`, `update rnd internal memo`, `delete rnd internal memo`, plus permission workflow lama `sync`/`finalize`/`create revision`/`generate pdf`/`download pdf`/`archive`) tetap sama persis.

Perubahan perilaku: `RndInternalMemoPolicy::update()` sekarang **permission-only** (tidak lagi mensyaratkan status Draft) — "Menu dapat ditambah dan dihapus kapan saja" sesuai PRD §7.5. Semua ability lain (`sync`, `finalize`, `createRevision`, `generatePdf`, `archive`) tidak diubah dan tetap mensyaratkan status seperti sebelumnya, meskipun UI baru tidak lagi memanggilnya.

## 6. Test dan jumlah assertion

File test baru/diubah untuk fitur ini:

| File | Keterangan |
| --- | --- |
| `tests/Unit/RndInternalMemoMaterialModelTest.php` | Baru — kolom Purchase UOM/Minimum Order |
| `tests/Unit/InternalMemoBomResolverTest.php` | +3 test baru (depth limit, transaction rollback) |
| `tests/Unit/RefreshInternalMemoMenuActionTest.php` | Baru — preservasi Minimum Order, lock, failure handling |
| `tests/Unit/RemoveMenuFromInternalMemoActionTest.php` | Baru |
| `tests/Unit/InternalMemoProductEnricherTest.php` | Baru |
| `tests/Unit/InternalMemoConsolidationServiceTest.php` | +3 test baru (`consolidateForSummary`) |
| `tests/Unit/UpdateInternalMemoMinimumOrdersActionTest.php` | Baru |
| `tests/Feature/RndInternalMemoWorkflowTest.php` | Diperbarui — Add Menu kini resolve BOM langsung; sync/forecast dipindah ke pemanggilan Action langsung |
| `tests/Feature/RndInternalMemoPermissionTest.php` | 1 test diperbarui (update permission-only) |
| `tests/Feature/RndInternalMemoFinalizationTest.php` | Ditulis ulang memanggil Action langsung (UI lama sudah tidak ada) |
| `tests/Feature/RndInternalMemoPdfTest.php` | 1 test disesuaikan |
| `tests/Feature/RndInternalMemoQueryPerformanceTest.php` | Baru — bukti tidak ada N+1 |

Hasil fokus (`InternalMemo` filter): **110 lulus, 367 assertion, 0 gagal** (dijalankan berulang kali sepanjang implementasi).

Hasil full suite (`php -d memory_limit=512M artisan test --compact`), dijalankan dua kali setelah seluruh phase selesai:

- Run pertama: 1153 lulus, 2 gagal — keduanya di `ProjectTaskCalendarPageTest.php` (fitur Task Calendar dari pekerjaan sebelumnya di sesi ini, bukan Internal Memo), disebabkan `due_date` default factory (`now()->addWeek()`) yang kebetulan jatuh di bulan kalender yang berbeda tergantung tanggal real saat test dijalankan — bug test lama yang baru termanifestasi karena tanggal berganti dari 30 September ke 1 Oktober di tengah sesi. Diperbaiki di commit `2accab2` (pin `due_date` eksplisit 6 bulan ke depan).
- Run kedua (setelah fix): **1155 lulus, 5791 assertion, 0 gagal**.

## 7. Risiko tersisa

1. **Kontrak Purchase UOM belum terbukti** — ini bukan bug, tapi keterbatasan data ESB yang terdokumentasi. Jika tim bisnis/ESB mengonfirmasi field mana yang sebenarnya merepresentasikan Purchase UOM, `InternalMemoProductEnricher` perlu diperbarui.
2. **Kategorisasi Bahan/WIP/Packaging masih heuristik** (string match + regex kode produk `^BW`), bukan field resmi — risiko lama yang tidak disentuh pekerjaan ini, bisa salah klasifikasi jika ESB mengubah penamaan kategori.
3. **Cleanup lebih dalam ditunda** — kolom/status/permission workflow lama sengaja belum dihapus (Phase 5 dibuat sempit per instruksi PRD). Ini berarti codebase belum "bersih" sepenuhnya, tapi aman untuk rollback.
4. **Flaky test pre-existing tidak terkait**: `RndInternalMemoFactory`-nya sendiri punya dokumentasi bahwa `period_month` random tidak unique-safe bila banyak Memo dibuat dalam satu test tanpa override eksplisit — ditemukan berulang kali pada test lama (`RndInternalMemoPdfTest`, `RndInternalMemoPermissionTest`) sepanjang sesi ini sebagai false failure intermiten; sudah dikonfirmasi lolos saat dijalankan terisolasi, dan test baru saya sendiri (`RndInternalMemoQueryPerformanceTest`) sudah dibuat immune dengan override eksplisit.
5. **Visual/responsive review belum diverifikasi dengan browser sungguhan** — perubahan Blade mengikuti pola `docs/ui-consistency-prd.md` (border tipis, tanpa shadow, modal dengan Escape/backdrop/trap-focus) tapi belum dicek langsung di viewport 360/390/768/1024/1440 atau zoom 200%. Perlu smoke test manual sebelum/stelah deploy.

## 8. Langkah deployment

```bash
git pull origin main
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Tidak ada perubahan environment baru — `ESB_TOKEN_BLSS`, `ESB_CORE_BLSS_USERNAME`, `ESB_CORE_BLSS_PASSWORD` yang sudah ada di `.env` server tetap dipakai persis sama, tidak ada variabel baru.

Smoke test setelah deploy:
1. Buka `R&D → Memo Internal`, pastikan daftar tampil tanpa error.
2. Buat Memo baru (hanya isi Nama + Bulan).
3. Tambah Menu dari Master Menu BLSS, pastikan BOM-nya langsung ter-resolve (lihat badge status Menu, bukan "Gagal Sinkronisasi").
4. Buka detail Menu, pastikan struktur BOM dan Ringkasan Item Akhir (Bahan + WIP) tampil.
5. Isi Minimum Order satu item, refresh Menu, pastikan nilai tetap ada.
6. Hapus Menu, pastikan item Menu lain tidak ikut terhapus.

## 9. Langkah rollback

Rollback dilakukan per commit (lihat daftar commit di bawah), bukan `git reset --hard` ke titik sebelum fitur ini. Karena kedua migration bersifat additive (hanya menambah kolom nullable), **tidak perlu dirollback saat rollback kode** — kolom baru yang tidak dipakai oleh kode lama tidak mengganggu apa pun. Jika memang ingin dirollback:

```bash
php artisan migrate:rollback --step=2
```

Data yang hilang jika migration di-rollback: `purchase_uom_id`, `purchase_uom_name`, `minimum_order`, `product_synced_at`, `product_detail_snapshot` pada seluruh baris `rnd_internal_memo_materials` (termasuk Minimum Order yang sudah diisi pengguna). **Backup tabel ini dulu** sebelum rollback schema jika Minimum Order sudah mulai diisi di production.

Revert kode: revert commit-commit di bawah dalam urutan terbalik (yang paling baru dulu). Tidak ada compatibility layer yang perlu dijaga karena `EsbCoreClient`/`InternalMemoMenuCatalogService`/`InternalMemoBomResolver` tidak diubah API publiknya, hanya perilaku internal `AddMenuToInternalMemoAction`/`InternalMemoBomResolver::resolve()`.

## 10. Daftar commit (urutan implementasi)

```text
4cce822 feat(rnd-internal-memo): add Purchase UOM and Minimum Order columns
3ec0a8b feat(rnd-internal-memo): rework Add/Remove/Refresh Menu as always-available use cases
feef955 feat(rnd-internal-memo): add Product enricher for Purchase UOM/snapshot
dab936b feat(rnd-internal-memo): add Ringkasan Item Akhir consolidation and Minimum Order update
46f6bcb feat(rnd-internal-memo): simplify the workspace UI, drop old workflow controls
c19dc9f refactor(rnd-internal-memo): remove the two Jobs orphaned by Phase 4
27ea84a test(rnd-internal-memo): lock in query count for index and workspace pages
```

Belum di-push ke `main` — menunggu instruksi eksplisit.
