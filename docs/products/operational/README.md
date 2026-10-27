# Operational

SOP store, komplain pelanggan, dan sales order khusus dari store. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Operational | SOP Store | `view store sops` (+ create/edit/delete, `publish store sops`, `view store sop reports`) |
| | SOP Kategori | `view store sop categories` (+ create/edit/delete) |
| | Customer Complaints | `view any customer complaints`, `view customer complaints`, `update customer complaints` |
| | Store Sales Orders | `view any store sales orders`, `view store sales orders`, `update store sales orders`, `update store sales order status` |
| Employee App | Tile **SOP Store** | `access employee app store sop` |
| Employee App | Tile **Complain** | `create customer complaints` |
| Employee App | Tile **Store Sales Order** | `create store sales orders` |

## SOP Store

- SOP berisi nomor, judul, kategori, brand, target branch, tanggal berlaku mulai dan sampai, ringkasan, dan dokumen.
- Status tampilan: `draft`, `ongoing` (published dan masih berlaku), `expired`.
- **Publish** membuat penugasan SOP ke user di branch tujuan (`StoreSopPublisher`). User yang kemudian bergabung ke branch tersebut juga mendapat penugasan.
- Staff membuka SOP di tile **SOP Store**, memfilter status, membaca dokumen, lalu **konfirmasi sudah membaca**.
- **Rekap** di back office menampilkan penerima dan status baca per branch.

## Customer Complaints

- Komplain hanya dibuat dari tile **Complain**: branch (divalidasi ulang di server), waktu kejadian, sumber (`in_store`, `whatsapp`, `phone`, `delivery`, `marketplace`, `social_media`, `other`), kategori (`product_quality`, `service`, `order_accuracy`, `delivery`, `cleanliness_facility`, `payment`, `other`), referensi order, data pelanggan, deskripsi, dan lampiran.
- Nomor otomatis `CMP-YYYYMMDD-NNNN`, aman untuk submit bersamaan. Notifikasi dikirim ke pihak terkait.
- Riwayat komplain milik user ada di tab Riwayat Employee App.
- Back office hanya untuk rekap (tanpa create/edit), dengan filter dan pencarian sesuai branch scope. Penghapusan adalah soft delete dan lampiran tetap disimpan.
- Komplain tidak masuk penilaian kepatuhan harian.

## Store Sales Orders

- Store memasukkan nomor **Product Sales** ESB. Sistem mencari transaksi tersebut secara exact-match di ESB Core untuk branch terpilih (read-only), lalu staff melengkapi informasi operasional (telepon, pemesan, jenis acara, waktu kirim, catatan persiapan, lampiran) dan **Kebutuhan Produk** (tipe produk, detail custom, jumlah, catatan).
- Nomor yang sudah tersimpan untuk company + branch yang sama tidak dibuat ulang; user diarahkan ke record yang ada.
- Halaman Employee App menampilkan 5 order terakhir yang dapat diakses user (tidak ada tile riwayat terpisah).
- Back office: lihat detail, **Edit Informasi** (hanya informasi operasional dan kebutuhan produk), **Ubah Status**, dan **Refresh dari ESB** (hanya menimpa kolom snapshot ESB).
- Status operasional: `draft`, `submitted`, `in_preparation`, `ready`, `delivered`, `cancelled` (pembatalan wajib alasan). Transisi diatur oleh `StoreSalesOrderStatus::canTransitionTo()`.
- Mapping ESB branch memakai sumber yang sama dengan Stock Card dan Memo Internal (`Branch::activeStockCardEsbCode()`).

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{StoreSops,StoreSopCategories,CustomerComplaints,StoreSalesOrders}/` |
| Employee App | `app/Filament/Casual/Pages/{StoreSopsPage,CustomerComplaintPage,CustomerComplaintHistoryPage,StoreSalesOrderPage}.php` |
| Logika | `app/Actions/CustomerComplaint/`, `app/Actions/StoreSalesOrder/`, `app/Services/{StoreSopPublisher,EsbProductSalesService}.php` |
| Policy | `app/Policies/{CustomerComplaintPolicy,StoreSalesOrderPolicy}.php` |
| Enum | `app/Enums/{CustomerComplaint,StoreSalesOrder}*.php` |
| Model | `app/Models/{StoreSop,CustomerComplaint,StoreSalesOrder}*.php` |
| Test | `tests/Feature/{StoreSop,CustomerComplaint,StoreSalesOrder}*Test.php` |

## Data

`StoreSop`, `StoreSopCategory`, `StoreSopAssignment`, `CustomerComplaint`, `StoreSalesOrder` beserta item dan aktivitas. Lihat [Data Model](../../architecture/data-model.md#operational).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [PRD Customer Complaints](customer-complaints/prd.md) | Implemented. Riwayat menjadi tab terpisah atas instruksi user, berbeda dari PRD awal (daftar 5 terakhir). |
| [PRD Store Sales Order](store-sales-order/prd.md) | Implemented |

## Role bawaan

`STORE_STAFF` membuat komplain dan sales order. `SUPERVISOR_STORE` melihat dan mengelola komplain serta sales order di branch-nya dan membaca SOP.
