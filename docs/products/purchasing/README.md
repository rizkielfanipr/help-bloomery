# Purchasing

Permintaan pembelian dari store, insiden kepatuhan vendor dari penerimaan barang, sourcing bahan, Request Sourcing dari Employee App, dan pemesanan material marketing. Kondisi per 3 November 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Purchasing | Vendor Compliance | `view vendor compliance incidents`, `edit vendor compliance incidents` |
| | Permintaan Pembelian | `view purchase requests` (+ create/edit/delete) |
| | Permintaan Sourcing | `view material sourcing requests`; Mulai Proses, Minta Perbaikan, Tolak: `process material sourcing requests`; Buat Sourcing: juga `submit material sourcing` |
| | Sourcing Bahan | `view material sourcings`, `submit material sourcing`, `hand over material samples` |
| | Material Marketing (Perlu Dipesan) | `view marketing material fulfillments`, `process marketing material as purchasing` |
| | PO Susulan | `view non po purchase order requests`; aksi: `process non po purchase order requests`; batal: `cancel non po purchase order requests` |
| Back Office → Brand Marketing | Master Design (baca) | `view design master bundles`, `download design master files` |
| Employee App | Tile **Purchasing** | `access employee app purchasing` |
| | Tile **Request Sourcing** | `access employee app sourcing request` |

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

## Request Sourcing

User Employee App meminta Purchasing mencarikan supplier untuk **satu** produk aktif Master Product ESB (company `BLSS`). Spesifikasi: [PRD](employee-sourcing-request/prd.md).

1. Tile **Request Sourcing** membuka form bergaya Request ERP. Requester memilih produk lewat bottom sheet yang sama dengan Receiving Tanpa PO, tanpa informasi stok, lalu mengisi alasan/tujuan (wajib), estimasi kebutuhan, target tanggal, catatan, dan maksimal 5 lampiran JPG/PNG/WEBP/PDF (10 MB per file). User tanpa cabang tidak dapat mengirim.
2. Saat dikirim, server membaca ulang Product Detail dari ESB (hanya GET) dan menyimpan snapshot kode, nama, kategori, subkategori, dan UOM. Requester, cabang, dan company diambil dari akun. Nomor `MSR-YYYYMMDD-NNNNNN` dibuat, riwayat status dicatat, dan user dengan `process material sourcing requests` mendapat notifikasi. Kirim ganda dengan form yang sama tidak membuat request kedua. Request belum menjadi sourcing dan belum masuk Review R&D.
3. **Purchasing → Permintaan Sourcing** menampilkan antrean `submitted`, `in_review`, dan `revision_requested` secara default, dengan filter status, cabang, requester, PIC, produk/kode, tanggal submit, dan target. Badge menu menghitung status yang sama.
4. **Mulai Proses** mencatat PIC (`in_review`). Hanya PIC yang dapat **Minta Perbaikan** atau **Tolak** (alasan wajib), dan **Buat Sourcing**.
5. **Buat Sourcing** membuka form supplier yang sama dengan Sourcing Bahan, dengan produk request terkunci. `CreateStandaloneMaterialSourcingAction` membaca ulang produk dan membuat sourcing mandiri (`sourcing_origin = standalone`, langsung `pending_rnd_review`). Request menjadi `converted` dan tertaut ke satu sourcing; klik ganda mengembalikan sourcing yang sama.
6. Di Sourcing Bahan dan Review Sourcing, kolom Project untuk sourcing hasil request bertuliskan **Request User** beserta nomor `MSR-*` yang tertaut ke detail request. Sourcing mandiri biasa tetap **Mandiri**.
7. Riwayat requester hanya berisi request miliknya: status, alasan keputusan, lampiran, dan referensi sourcing; tanpa supplier atau harga. Requester dapat **Batalkan** request `submitted`/`revision_requested` (alasan wajib) dan **Perbaiki & Kirim Ulang** request `revision_requested`. Kirim ulang membaca ulang produk, melepas PIC, dan kembali ke `submitted`.

Status: `submitted`, `in_review`, `revision_requested`, `converted`, `rejected`, `cancelled`. Request dan riwayatnya tidak dapat dihapus. Lampiran tersimpan privat di `purchasing/material-sourcing-requests/{id}/` dan hanya diunduh requester atau pemegang `view material sourcing requests`. Waktu tunggu request (`submitted_at`) terpisah dari durasi sourcing yang dimulai saat Buat Sourcing.

## Material Marketing

1. Desain mengunggah material marketing pada produk Project R&D (permission `upload marketing materials`).
2. Purchasing di **Perlu Dipesan** menandai **Dipesan**: vendor/percetakan, tanggal pesan, estimasi selesai, dan catatan.
3. Inventory di **Perlu Diterima** menandai **Diterima**: jumlah, tanggal terima, lokasi penyimpanan, dan catatan.

Status: `not_started`, `ordered`, `received`.

Purchasing juga dapat membuka [Master Design](../brand-marketing/README.md#master-design) di grup Brand Marketing dengan permission di atas: melihat bundle aktif dan mengunduh file desain tanpa aksi mutasi. Master Design tidak terhubung dengan antrean fulfillment di atas.

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
| Back Office | `app/Filament/Helpdesk/Resources/{PurchaseRequests,VendorComplianceIncidents,MaterialSourcings,MaterialSourcingRequests,MarketingMaterialFulfillments,NonPoReceipts}/`, `resources/views/filament/helpdesk/{non-po-receipts,material-sourcing-requests}/` |
| Action | `app/Actions/Purchasing/{NonPoReceipt,MaterialSourcingRequest}/` |
| Service | `app/Services/Purchasing/`, `app/Services/Rnd/Material/ActiveEsbProductDetailResolver.php` |
| Employee App | `app/Filament/Casual/Pages/{PurchaseRequestPage,PurchaseRequestHistoryPage,MaterialSourcingRequestPage,MaterialSourcingRequestHistoryPage}.php`, `resources/views/filament/casual/pages/material-sourcing-request*.blade.php`, `resources/views/components/material-sourcing-request/` |
| Lampiran | `app/Http/Controllers/MaterialSourcingRequestAttachmentController.php` |
| Policy | `app/Policies/{VendorComplianceIncidentPolicy,NonPoReceiptPolicy,MaterialSourcingRequestPolicy}.php` |
| Enum | `app/Enums/{PurchaseRequestStatus,PurchaseType,MarketingMaterialFulfillmentStatus,NonPoReceiptStatus,MaterialSourcingRequestStatus}.php` |
| Model | `app/Models/{PurchaseRequest,PurchasingRequest,VendorCompliance,MarketingMaterialFulfillment,NonPoReceipt,MaterialSourcingRequest}*.php` |
| Durasi sourcing | `app/Services/Rnd/Material/MaterialSourcingTimeline.php`, `resources/views/filament/helpdesk/material-sourcings/view-sourcing.blade.php`, `app/Console/Commands/BackfillMaterialSourcingDurationsCommand.php` |
| Test | `tests/Feature/{PurchaseRequest,MarketingMaterialFulfillment,MaterialSourcing,MaterialSourcingRequest,NonPoReceipt,BackfillMaterialSourcingDurations}*Test.php`, `tests/Unit/MaterialSourcingTimelineTest.php` |

## Data

`PurchaseRequest`, `PurchaseRequestStatusHistory`, `VendorComplianceIncident`, `MarketingMaterialFulfillment`, `RndProjectMarketingMaterial`, `MaterialSourcing`, `MaterialSourcingRequest`, `MaterialSourcingRequestStatusHistory`. Lihat [Data Model](../../architecture/data-model.md#purchasing).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [Pelacakan Durasi Sourcing](sourcing-duration-tracking/README.md) dan [PRD](sourcing-duration-tracking/prd.md) | Implemented; rollout dan UAT staging belum dijalankan |
| [Receiving tanpa PO](../inventory/non-po-receiving/README.md) dan [PRD](../inventory/non-po-receiving/prd.md) | Implemented |
| [Master Design Library](../brand-marketing/design-master-library/README.md) dan [PRD](../brand-marketing/design-master-library/prd.md) | Implemented; rollout dan UAT staging belum dijalankan |
| [Request Sourcing melalui Employee App](employee-sourcing-request/README.md) dan [PRD](employee-sourcing-request/prd.md) | Implemented; rollout dan UAT staging belum dijalankan |

## Role bawaan

`PURCHASING_STAFF` mengelola semua menu di atas kecuali Permintaan Sourcing dan membaca Master Design; `cancel non po purchase order requests` tidak termasuk bawaan dan diberikan lewat Role & Permission. Permission Request Sourcing (`access employee app sourcing request`, `view material sourcing requests`, `process material sourcing requests`) tidak diberikan ke role mana pun oleh seeder. `STORE_STAFF` mengajukan permintaan pembelian.
