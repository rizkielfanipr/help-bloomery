# Brand Marketing

Permintaan desain dan konten dari cabang ke tim brand marketing. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Brand Marketing | Permintaan Design | `view design requests` (+ create/edit/delete) |
| | Kategori Design | `view design categories` (+ create/edit/delete) |
| | Permintaan Konten | `view content requests` (+ create/edit/delete) |
| Employee App | Tile **Desain** | `access employee app design` |
| Employee App | Tile **Konten** | `access employee app content` |

Upload material marketing pada produk Project R&D memakai permission `upload marketing materials` (grup Brand Marketing). Alurnya ada di [Purchasing](../purchasing/README.md#material-marketing).

## Permintaan Design

1. Cabang mengajukan dari tile **Desain**: kategori, judul, keterangan, dan lampiran. Riwayat tersedia di Employee App.
2. Tim desain memperbarui status di back office: `design_request → in_progress → approval`, lalu `approval → printing_process → completed` atau `approval → rejected`. Riwayat status disimpan.
3. Export Excel tersedia.

**Kategori Design**: nama, urutan, status aktif, dan jumlah permintaan.

## Permintaan Konten

1. Cabang mengajukan dari tile **Konten**: judul, jenis, platform, keterangan, dan lampiran.
2. Status: `submitted → in_progress → approval → completed/rejected`, dengan riwayat status. Export Excel tersedia.

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{DesignRequests,DesignCategories,ContentRequests}/` |
| Employee App | `app/Filament/Casual/Pages/{DesignRequestPage,DesignRequestHistoryPage,ContentRequestPage,ContentRequestHistoryPage}.php` |
| Enum | `app/Enums/{DesignRequestStatus,ContentRequestStatus}.php` |
| Model | `app/Models/{DesignRequest,DesignCategory,ContentRequest}*.php` |
| Test | `tests/Feature/ContentRequestTest.php` |

## Data

`DesignRequest`, `DesignRequestStatusHistory`, `DesignCategory`, `ContentRequest`, `ContentRequestStatusHistory`. Lihat [Data Model](../../architecture/data-model.md#brand-marketing).

## Role bawaan

`STORE_STAFF` mengajukan permintaan desain dan konten. `DESIGN_STAFF` mengunggah material marketing. Pengelolaan back office diberikan sesuai permission.
