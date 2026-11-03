# Brand Marketing

Permintaan desain dan konten dari cabang ke tim brand marketing, serta katalog Master Design per Brand. Kondisi per 3 November 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Brand Marketing | Permintaan Design | `view design requests` (+ create/edit/delete) |
| | Kategori Design | `view design categories` (+ create/edit/delete) |
| | Permintaan Konten | `view content requests` (+ create/edit/delete) |
| | Master Design | `view design master bundles`; lihat [izin Master Design](#master-design) |
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

## Master Design

Penyimpanan file desain dengan satu Brand per bundle ([PRD](design-master-library/prd.md)). Index memakai pola list Project R&D: pencarian, filter Brand/kategori/tipe item/fisik-digital, tab Aktif/Diarsipkan untuk pengguna internal, dan pagination 20 bundle. Data ini terpisah dari material marketing Project R&D dan tidak membuat order atau fulfillment. Tidak ada version, review, atau publish; isi bundle aktif langsung terlihat oleh Purchasing.

| Status bundle | Arti |
|---|---|
| `active` | Dapat diubah dan terlihat oleh Purchasing |
| `archived` | Tidak dapat diubah dan hanya terlihat oleh pengguna internal; dapat dipulihkan |

**Aturan utama**

- Brand tidak dapat diubah setelah bundle dibuat. Kode bundle dinormalisasi dan unik per Brand. Kategori: reguler, seasonal, campaign, product launch, store opening, partnership, event, lainnya.
- **Tambah Item** meminta tipe (mengikuti material marketing Project R&D), nama, deskripsi, dan satu file desain yang wajib diunggah langsung. File tidak dibedakan per jenis.
- Spesifikasi produksi tidak ditanyakan saat menambah item. Item fisik (Design Packaging, Sticker) diisi lewat **Isi Spesifikasi** pada card Spesifikasi Produksi, yang menampilkan list item beserta nama, tipe, file desain, dan status kelengkapannya. Item fisik tanpa dimensi atau material ditandai **Belum Lengkap**; tanda ini hanya informasi.
- **Unduh** selalu tampil bagi pemegang permission unduh dan nonaktif bila item belum punya file. Tombol file berlabel **Unggah File** bila item belum punya file dan **Ganti File** bila sudah ada. Mengganti file menyimpan file baru pada path baru; file lama terhapus lunak dan object-nya tetap tersimpan.
- **Hapus** item bersifat permanen: item, file desain, file lama hasil penggantian, dan object-nya di penyimpanan ikut terhapus dan tidak dapat dikembalikan. Audit tetap mencatat siapa menghapus item apa. Bundle tidak dapat dihapus, hanya diarsipkan.
- Aturan status bundle berlaku bagi semua pengguna, termasuk SUPERADMIN.

**File** disimpan privat di disk `b2` dengan path berbasis ID dan checksum SHA-256. Format JPG, JPEG, PNG, WEBP, PDF, dan ZIP, maksimal 12 MB; alasannya ada di [PRD §18.2](design-master-library/prd.md#182-format-dan-batas-file). Unduhan dan preview (gambar dan PDF) melewati pemeriksaan akses dan memakai URL sementara 15 menit; setiap unduhan tercatat di audit.

**Izin Master Design**

| Peran | Permission |
|---|---|
| Designer | `create design master bundles`, `edit design master bundles`, `upload design master files` |
| Pengelola arsip | `archive design master bundles` |
| Pengunduh file (Designer dan Purchasing) | `download design master files` |

Semua peran juga memerlukan `view design master bundles`. Pemilik salah satu permission create, edit, upload, atau archive adalah pengguna internal yang dapat melihat bundle arsip. Pengguna lain, termasuk Purchasing, hanya melihat bundle aktif; URL ke bundle arsip menghasilkan 404.

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{DesignRequests,DesignCategories,ContentRequests,MarketingDesignBundles}/`, `resources/views/filament/helpdesk/marketing-design-bundles/` |
| Action dan Service Master Design | `app/Actions/BrandMarketing/DesignMaster/`, `app/Services/BrandMarketing/DesignMaster/` |
| File Master Design | `app/Http/Controllers/Helpdesk/MarketingDesignFileController.php` |
| Policy | `app/Policies/MarketingDesign*.php` |
| Employee App | `app/Filament/Casual/Pages/{DesignRequestPage,DesignRequestHistoryPage,ContentRequestPage,ContentRequestHistoryPage}.php` |
| Enum | `app/Enums/{DesignRequestStatus,ContentRequestStatus}.php`, `app/Enums/MarketingDesign*.php` |
| Model | `app/Models/{DesignRequest,DesignCategory,ContentRequest,MarketingDesign}*.php` |
| Test | `tests/Feature/ContentRequestTest.php`, `tests/Feature/MarketingDesign*Test.php` |

## Data

`DesignRequest`, `DesignRequestStatusHistory`, `DesignCategory`, `ContentRequest`, `ContentRequestStatusHistory`, `MarketingDesignBundle`, `MarketingDesignItem`, `MarketingDesignFile`, `MarketingDesignAuditLog`. Lihat [Data Model](../../architecture/data-model.md#brand-marketing).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [Master Design Library](design-master-library/README.md) dan [PRD](design-master-library/prd.md) | Implemented; rollout dan UAT staging belum dijalankan |

## Role bawaan

`STORE_STAFF` mengajukan permintaan desain dan konten. `DESIGN_STAFF` mengunggah material marketing serta mengelola Master Design dan dapat mengunduh file desain. Permission `archive design master bundles` tidak termasuk role bawaan dan diberikan lewat Role & Permission. Pengelolaan back office diberikan sesuai permission.
