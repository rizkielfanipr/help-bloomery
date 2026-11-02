# Purchasing

Permintaan pembelian dari store, insiden kepatuhan vendor dari penerimaan barang, sourcing bahan, dan pemesanan material marketing. Kondisi per 2 November 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Purchasing | Vendor Compliance | `view vendor compliance incidents`, `edit vendor compliance incidents` |
| | Permintaan Pembelian | `view purchase requests` (+ create/edit/delete) |
| | Sourcing Bahan | `view material sourcings`, `submit material sourcing`, `hand over material samples` |
| | Material Marketing (Perlu Dipesan) | `view marketing material fulfillments`, `process marketing material as purchasing` |
| | PO Susulan | `view non po purchase order requests`; aksi: `process non po purchase order requests`; batal: `cancel non po purchase order requests` |
| Employee App | Tile **Purchasing** | `access employee app purchasing` |

## Permintaan Pembelian

1. Store mengajukan dari tile **Purchasing**: nama barang, alasan, jumlah, jenis pembelian (`new` atau `broken`), dan lampiran.
2. Purchasing dapat **menutup atau membuka form** pengajuan dengan alasan (`toggle_form`). Saat tertutup, store melihat alasan penutupan dan tidak bisa mengajukan.
3. Purchasing mengubah status di halaman detail: `submitted → approved/rejected` (reject wajib alasan), `approved → purchased → delivered`. Riwayat status disimpan.
4. Store **mengonfirmasi barang diterima** dari riwayat, sehingga status `delivered` menjadi `completed`.

Back office menyediakan filter status, cabang, dan jenis pembelian, serta export Excel.

## Vendor Compliance

- Insiden dibuat otomatis saat Receiving menolak item. Isinya: nomor insiden, vendor, nomor PO, produk, kategori, qty rejected, dan **poin demerit**. Bobot demerit: cold chain dan shelf life 20; sampling 15; document, quantity, quality, dan packaging 10; lainnya 5.
- Purchasing menindaklanjuti dengan status penanganan (`open`, `in_progress`, `claim_or_return_submitted`, `resolved`), jenis tindak lanjut (klaim vendor, retur barang, penggantian barang, credit note, lainnya), dan catatan.

## Sourcing Bahan

Antrean Purchasing untuk Master Bahan: menambah kandidat supplier (nama, merek, harga, MOQ, lead time, kontak, lampiran penawaran), menyerahkan sampel ke R&D beserta foto, dan sourcing tanpa project untuk produk ESB existing. Alur lengkap dan aturan statusnya ada di [Research & Development](../rnd/README.md#master-bahan-dan-sourcing).

Index dan modal detail menampilkan **Masuk Sourcing**, **Selesai Sourcing**, dan **Durasi** kalender ([Pelacakan Durasi Sourcing](sourcing-duration-tracking/README.md)). Tabel dapat difilter per rentang tanggal masuk atau selesai dan per kondisi Masih Berjalan atau Selesai. Timestamp hanya ditulis server; penyelesaian sourcing mengikuti aturan di [Research & Development](../rnd/README.md#durasi-sourcing). Kolom **Diperbarui** beserta filternya tetap tersedia lewat pengelola kolom dan tersembunyi secara default.

## Material Marketing

1. Desain mengunggah material marketing pada produk Project R&D (permission `upload marketing materials`).
2. Purchasing di **Perlu Dipesan** menandai **Dipesan**: vendor/percetakan, tanggal pesan, estimasi selesai, dan catatan.
3. Inventory di **Perlu Diterima** menandai **Diterima**: jumlah, tanggal terima, lokasi penyimpanan, dan catatan.

Status: `not_started`, `ordered`, `received`.

Planning [Master Design Library](../brand-marketing/design-master-library/README.md) menempatkan katalog desain reusable pada domain Brand Marketing berdasarkan Brand. Purchasing direncanakan memperoleh akses baca dan unduh file production versi published; planning ini belum mengubah antrean fulfillment di atas.

## PO Susulan

Antrean **Permintaan PO Susulan dari Receiving** untuk barang yang sudah dicatat Inventory [tanpa PO](../inventory/README.md#receiving-tanpa-po). Badge menghitung `awaiting_purchasing`, `po_in_progress`, `revision_requested`, `gr_failed`, dan `gr_unknown`; draft tidak tampil. Spesifikasi: [PRD](../inventory/non-po-receiving/prd.md).

1. **Mulai Proses PO** mengubah status ke `po_in_progress` dan mencatat PIC. PO dibuat manual di ESB; aplikasi tidak membuat PO.
2. **Minta Perbaikan** (alasan wajib) mengembalikan record ke Inventory selama PO belum tertaut. **Batalkan Proses** (alasan wajib) memerlukan permission tersendiri.
3. **Cek PO** membaca PO terbaru dan menampilkan matriks lokal vs PO tanpa mengubah data. Mengetik nomor PO tidak memicu mutasi.
4. **Verifikasi PO & Proses GR** selalu mengambil ulang PO di server. Yang memblokir: company bukan `BLSS`, status bukan Authorized/Receiving, cabang berbeda, item Accepted tanpa baris PO dengan Product Detail ID + UOM yang sama, Accepted melebihi outstanding, atau PO sudah tertaut ke penerimaan lain. Outstanding = qty PO dikurangi Accepted GR lokal pada PO itu, termasuk GR `processing` dan `unknown`. Item tanpa Accepted tidak memerlukan baris PO.
5. Supplier yang berbeda hanya peringatan dan wajib dikonfirmasi. Supplier PO menjadi data resmi; teks Inventory tetap tersimpan.
6. Setelah lulus, status berjalan `po_linked` → `gr_processing`, lalu `completed`, `gr_failed` (ESB menolak), atau `gr_unknown` (koneksi terputus). Hanya qty Accepted yang dikirim. Rejected pada penerimaan campuran membuat Vendor Compliance Incident dengan supplier PO. Klik ganda tidak membuat GR kedua, dan mutasi tercatat di System Monitoring dengan aksi `submit_non_po`.
7. **Rekonsiliasi GR** untuk `gr_failed`/`gr_unknown` tidak pernah berjalan otomatis: tautkan nomor GR yang ditemukan di ESB, atau kirim ulang payload yang sama setelah PO diperiksa ulang, dengan catatan wajib. Pembatalan dari kedua status ini menandai GR lokal gagal dan melepas PO, jadi hanya dilakukan setelah GR dipastikan tidak ada di ESB.

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{PurchaseRequests,VendorComplianceIncidents,MaterialSourcings,MarketingMaterialFulfillments,NonPoReceipts}/`, `resources/views/filament/helpdesk/non-po-receipts/` |
| Action | `app/Actions/Purchasing/NonPoReceipt/` |
| Employee App | `app/Filament/Casual/Pages/{PurchaseRequestPage,PurchaseRequestHistoryPage}.php` |
| Policy | `app/Policies/{VendorComplianceIncidentPolicy,NonPoReceiptPolicy}.php` |
| Enum | `app/Enums/{PurchaseRequestStatus,PurchaseType,MarketingMaterialFulfillmentStatus,NonPoReceiptStatus}.php` |
| Model | `app/Models/{PurchaseRequest,PurchasingRequest,VendorCompliance,MarketingMaterialFulfillment,NonPoReceipt}*.php` |
| Durasi sourcing | `app/Services/Rnd/Material/MaterialSourcingTimeline.php`, `resources/views/filament/helpdesk/material-sourcings/view-sourcing.blade.php`, `app/Console/Commands/BackfillMaterialSourcingDurationsCommand.php` |
| Test | `tests/Feature/{PurchaseRequest,MarketingMaterialFulfillment,MaterialSourcing,NonPoReceipt,BackfillMaterialSourcingDurations}*Test.php`, `tests/Unit/MaterialSourcingTimelineTest.php` |

## Data

`PurchaseRequest`, `PurchaseRequestStatusHistory`, `VendorComplianceIncident`, `MarketingMaterialFulfillment`, `RndProjectMarketingMaterial`, `MaterialSourcing`. Lihat [Data Model](../../architecture/data-model.md#purchasing).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [Pelacakan Durasi Sourcing](sourcing-duration-tracking/README.md) dan [PRD](sourcing-duration-tracking/prd.md) | Implemented; rollout dan UAT staging belum dijalankan |
| [Receiving tanpa PO](../inventory/non-po-receiving/README.md) dan [PRD](../inventory/non-po-receiving/prd.md) | Implemented |
| [Master Design Library](../brand-marketing/design-master-library/README.md) dan [PRD](../brand-marketing/design-master-library/prd.md) | Draft — planning, belum diimplementasikan |

## Role bawaan

`PURCHASING_STAFF` mengelola semua menu di atas; `cancel non po purchase order requests` tidak termasuk bawaan dan diberikan lewat Role & Permission. `STORE_STAFF` mengajukan permintaan pembelian.
