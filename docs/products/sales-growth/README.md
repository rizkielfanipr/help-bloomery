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

## Data

`RndProductSalesProjection`, `RndProductRegionalPrice`, `SalesRegion`. Lihat [Data Model](../../architecture/data-model.md#research--development).
