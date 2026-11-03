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

Katalog desain reusable dengan satu Brand per bundle ([PRD](design-master-library/prd.md)). Data ini terpisah dari material marketing Project R&D dan tidak membuat order atau fulfillment.

| Status version | Arti | Aksi berikutnya |
|---|---|---|
| `draft` | Sedang disusun | Kirim untuk Review |
| `in_review` | Menunggu reviewer | Setujui, atau kembalikan dengan alasan |
| `revision_required` | Perlu diperbaiki | Perbaiki sebagai Draft |
| `approved` | Menunggu publish | Publish, atau kembalikan dengan alasan |
| `published` | Versi aktif, maksimal satu per bundle, immutable | Berubah menjadi `superseded` saat revisi baru dipublikasikan |
| `superseded` | Versi lama, read-only | Tidak ada |

**Aturan utama**

- Brand tidak dapat diubah setelah bundle dibuat. Kode bundle dinormalisasi dan unik per Brand. Kategori: reguler, seasonal, campaign, product launch, store opening, partnership, event, lainnya.
- Membuat bundle otomatis membuat Version 1. Revisi disalin dari version published dan hanya dapat dibuat bila tidak ada version lain yang masih berjalan. Item disalin, sedangkan file mereferensikan object yang sama.
- Syarat review dan publish: minimal satu item, setiap item punya preview, dan item fisik (Design Packaging, Sticker) punya dimensi, material, serta file production.
- Arsip hanya mengubah status bundle. Tidak ada hard delete; item dan file yang dilepas dari draft hanya terhapus lunak, dan object tetap tersimpan.
- Aturan status berlaku bagi semua pengguna, termasuk SUPERADMIN.
- Notifikasi database: reviewer saat dikirim, pembuat version saat dikembalikan, dan pemegang izin unduh production saat publish.

**File** disimpan privat di disk `b2` dengan path berbasis ID dan checksum SHA-256. Batas 12 MB per file; alasannya ada di [PRD §18.2](design-master-library/prd.md#182-file-role-dan-format-awal). Unduhan melewati pemeriksaan akses, memakai URL sementara 15 menit, dan unduhan production/source tercatat di audit.

| Role file | Format | Syarat akses |
|---|---|---|
| Preview | JPG, JPEG, PNG, WEBP, PDF | Dapat melihat version |
| Production | PDF, ZIP | `download design master production files` |
| Source | ZIP | `download design master source files` |
| Supporting | PDF, JPG, JPEG, PNG, WEBP, ZIP | Pengguna internal |

**Izin Master Design**

| Peran | Permission |
|---|---|
| Designer | `create design master bundles`, `edit design master bundles`, `upload design master files` |
| Reviewer | `review design master bundles` |
| Publisher | `publish design master bundles`, `archive design master bundles` |
| Purchasing | `download design master production files` |

Semua peran juga memerlukan `view design master bundles`. Pemilik salah satu permission mutasi di atas adalah pengguna internal yang dapat melihat semua version dan bundle arsip. Pengguna lain, termasuk Purchasing, hanya melihat version published dari bundle aktif; URL ke bundle lain menghasilkan 404.

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

`DesignRequest`, `DesignRequestStatusHistory`, `DesignCategory`, `ContentRequest`, `ContentRequestStatusHistory`, `MarketingDesignBundle`, `MarketingDesignBundleVersion`, `MarketingDesignItem`, `MarketingDesignFile`, `MarketingDesignAuditLog`. Lihat [Data Model](../../architecture/data-model.md#brand-marketing).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [Master Design Library](design-master-library/README.md) dan [PRD](design-master-library/prd.md) | Implemented; rollout dan UAT staging belum dijalankan |

## Role bawaan

`STORE_STAFF` mengajukan permintaan desain dan konten. `DESIGN_STAFF` mengunggah material marketing serta mengelola draft Master Design dan dapat mengunduh file production dan source. Permission review, publish, dan archive tidak termasuk role bawaan dan diberikan lewat Role & Permission. Pengelolaan back office diberikan sesuai permission.
