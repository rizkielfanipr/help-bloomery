# Sales & Growth

Rekap target penjualan produk baru per region. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu | Permission |
|---|---|---|
| Back Office → Sales & Growth | Sales Projection | `view sales projections` |

## Sales Projection

- Membaca proyeksi penjualan yang diisi per produk Project R&D (`RndProductSalesProjection`): bulan proyeksi, region, target quantity, dan target revenue.
- Halaman menampilkan ringkasan (total quantity, revenue, jumlah produk, dan branch yang tercakup), tabel per baris proyeksi dengan pagination, serta filter.
- Halaman ini read-only. Data proyeksi dan harga regional diisi dari sisi R&D, dan region dikelola di master Region Penjualan.

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Pages/SalesProjectionPage.php` |
| Model | `app/Models/{RndProductSalesProjection,RndProductRegionalPrice,SalesRegion}.php` |
| Test | `tests/Feature/SalesProjectionPageTest.php` |

## Data

`RndProductSalesProjection`, `RndProductRegionalPrice`, `SalesRegion`. Lihat [Data Model](../../architecture/data-model.md#research--development).
