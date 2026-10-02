# Laporan Implementasi — Store Sales Order (Phase 0–6)

Fitur Store Sales Order telah diimplementasikan penuh sesuai `docs/store-sales-order-prd.md`, Phase 0 sampai Phase 6. Laporan ini merangkum apa yang dibangun, asumsi konservatif yang diambil, serta panduan deployment/smoke-test/rollback.

## 1. Ringkasan per phase

| Phase | Isi | Commit |
|---|---|---|
| 0 | Audit repository, verifikasi live endpoint ESB, pembekuan 6 open decision | `1a1cdad` |
| 1 | `EsbProductSalesService` (exact-match lookup di atas `EsbCoreClient`) + unit test | `9ae4e70` |
| 2 | Schema (`store_sales_orders`, `_items`, `_activities`), enum status/event/produk, model, factory, Policy, Action domain (Lookup/Create/Update/UpdateStatus/RefreshSnapshot), permission config+seeder+migration | `14bf4db` |
| 3 | Tile Casual panel + `StoreSalesOrderPage` (lookup, konfirmasi, Informasi Operasional, repeater Kebutuhan Produk, attachment, riwayat 5 terakhir inline) | `5221924` |
| 4 | Back office `Operational → Store Sales Orders` (index, filter, detail, Edit Informasi, Ubah Status, Refresh dari ESB), controller attachment, sidebar | `efa6fc7` |
| 5 | Perbaikan deteksi duplicate-key lintas driver (MySQL vs SQLite), test attachment (auth, 404, orphan cleanup, soft delete) | `7c1a6df` |
| 6 | Review akhir, full test suite, laporan ini | commit ini |

Total test baru: 59 test, 235 assertion, seluruhnya pass (`StoreSalesOrder*`, `EsbProductSalesService*`, `CreateStoreSalesOrderActionConstraintMatchTest`).

## 2. Asumsi konservatif yang dicatat (tidak diam-diam)

Selain 6 open decision di `docs/store-sales-order-phase0-report.md` §4:

1. **Status awal `Submitted`, bukan `Draft`** — PRD §11 eksplisit "Admin Store membuat record langsung sebagai Submitted setelah form valid." Kolom `operational_status` tetap punya default schema `draft` sebagai safety-net kolom, tapi `CreateStoreSalesOrderAction` selalu mengisi eksplisit `Submitted`.
2. **`event_type_other` tidak divalidasi wajib** saat `event_type = Other` — PRD §10.2 tidak menyatakan "wajib" untuk field ini (berbeda dengan Detail Custom di §10.3 yang eksplisit wajib). Field tetap ditampilkan dinamis di UI, tapi validasi server tidak memaksanya diisi.
3. **Default sort back office disederhanakan** — PRD §13.2 meminta "required date terdekat yang belum terminal, lalu data terbaru" (aturan majemuk). Diimplementasikan sebagai `defaultSort('required_date', 'asc')` murni, karena aturan majemuk lewat `orderByRaw()` di `getEloquentQuery()` akan dievaluasi SQL lebih dulu dan diam-diam mengalahkan sort kolom yang diklik user (dibuktikan lewat pembacaan `CanSortRecords::applySortingToTableQuery()`) — trade-off ini didokumentasikan di `StoreSalesOrderResource::table()`.
4. **Refresh dari ESB digerbangi permission `update store sales orders`** — PRD §16 tidak secara eksplisit menetapkan permission untuk aksi Refresh. Dipilih permission yang sama dengan Edit Informasi karena keduanya sama-sama aksi tulis di luar status, bukan `view`.
5. **Enforcement "Custom wajib Detail Custom" dan "Cancelled wajib alasan" dipindah dari Filament field-level `->required()` ke Action-level `ValidationException`** — closure `->required()` yang bergantung pada state field lain (lewat `$get()`) meninggalkan action modal "stuck" ketika validasi gagal, memutus rangkaian pemanggilan aksi berikutnya dalam satu test/request. Action tetap menegakkan aturan ini secara otentik (sudah diuji); form hanya memakai `->visible()` untuk UX.
6. **Konkurensi insert (race condition) pada unique constraint tidak diuji lewat concurrency asli** — SQLite `:memory:` single-process tidak bisa mensimulasikan dua transaction independen yang commit terpisah. Bagian yang bisa dibuktikan (pengenalan pesan error MySQL vs SQLite untuk collision yang sama) diuji langsung di `tests/Unit/CreateStoreSalesOrderActionConstraintMatchTest.php`. Catatan ini sama persis dengan keterbatasan yang sudah terdokumentasi di `CreateCustomerComplaintAction`'s test.

Tidak ada satu pun di atas yang menyentuh data irreversible, keamanan, atau kontrak bisnis yang tidak dapat dibalik.

## 3. Deployment

Migration bersifat additive-only dan aman dijalankan di database production yang sudah berjalan:

```bash
php artisan migrate --force
```

Urutan migration baru:
1. `2026_10_02_075127_create_store_sales_orders_table.php`
2. `2026_10_02_075128_create_store_sales_order_items_table.php`
3. `2026_10_02_075129_create_store_sales_order_activities_table.php`
4. `2026_10_02_075200_grant_store_sales_order_permissions.php` — `firstOrCreate` permission + `givePermissionTo` ke `STORE_STAFF`/`SUPERVISOR_STORE` yang sudah ada, aman dijalankan berulang, `down()` sengaja kosong (role mungkin sudah diubah manual sejak itu).

Tidak ada perubahan dependency (`composer.json`/`package.json` tidak disentuh). Tidak perlu `npm run build` karena tidak ada asset frontend baru di luar Blade/Tailwind utility class yang sudah ada.

## 4. Smoke test setelah deploy

1. **Permission**: `php artisan tinker --execute 'echo \Spatie\Permission\Models\Permission::where("name","create store sales orders")->exists() ? "OK" : "MISSING";'`
2. **Tile Casual**: login sebagai user `STORE_STAFF`, buka panel Casual, pastikan tile "Store Sales Order" tampil dan dapat dibuka.
3. **Lookup ESB**: isi Branch + nomor SO nyata dari ESB, klik "Cari ESB". Jika credential ESB Core untuk Company Code terkait belum diberi akses endpoint `/sales/product-sales` (lihat Phase 0 report §2 — gap akun ESB yang terdokumentasi, bukan bug), pesan error akan muncul di field nomor SO, bukan crash.
4. **Submit**: lengkapi Informasi Operasional + minimal satu baris Kebutuhan Produk, submit, pastikan redirect ke layar konfirmasi dengan nomor SO yang benar.
5. **Back office**: login sebagai `SUPERVISOR_STORE`/admin, buka `Operational → Store Sales Orders`, pastikan record baru muncul, buka detail, coba "Ubah Status" (Submitted → In Preparation) dan "Refresh dari ESB".
6. **Duplicate guard**: submit nomor SO yang sama dua kali dari user yang sama — pastikan hanya satu record yang tercipta.

## 5. Rollback

Karena seluruh migration additive (tabel baru + grant permission, tidak ada ALTER/DROP terhadap tabel existing), rollback cukup:

```bash
php artisan migrate:rollback --step=4
```

Ini akan drop 3 tabel baru (cascade ke item/activity lewat FK) dan menjalankan `down()` kosong untuk migration permission (permission yang sudah digrant akan tetap ada di `role_has_permissions` — ini sengaja, karena mencabut permission otomatis berisiko menghapus permission yang sudah dipakai/diubah manual oleh admin sejak deploy). Jika permission perlu dicabut manual: `php artisan tinker --execute 'Spatie\Permission\Models\Role::whereIn("name",["STORE_STAFF","SUPERVISOR_STORE"])->get()->each->revokePermissionTo(["create store sales orders","view any store sales orders","view store sales orders","update store sales orders","update store sales order status"]);'`.

Tile dan menu back office otomatis hilang begitu permission dicabut (tidak perlu rollback kode terpisah untuk visibility).

## 6. Keterbatasan yang diwarisi dari Phase 0 (belum berubah)

- Endpoint `GET /sales/product-sales` baru terverifikasi *ada* (403, bukan 404) di ESB Core; field response masih berdasarkan spesifikasi PRD §9.2, bukan response asli yang pernah dilihat, karena credential yang tersedia saat Phase 0 belum punya scope akses endpoint ini. Parsing dibuat defensif (`EsbProductSalesService::normalize()`), sehingga begitu akses diberikan, risiko perubahan besar pada kode kecil.
- Kontrak line-item resmi ESB (Product Sales Detail) belum ada (PRD §9.3) — section Kebutuhan Produk tetap murni catatan operasional lokal, bukan line item ESB resmi, sesuai PRD.
