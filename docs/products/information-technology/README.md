# Information Technology

Permintaan perbaikan/perubahan ERP dari cabang, master modul dan tipe request, bulk data ke ESB, dan review data Master Bahan. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Information Technology | Permintaan ERP | `view erp requests` (+ create/edit/delete) |
| | Modul ERP | `view erp modules` (+ create/edit/delete) |
| | Request Types | `view it request types` (+ create/edit/delete) |
| | Bulk Data | `view bulk product submissions` (+ create/edit) |
| | Review Data | `review rnd materials as it` |
| Employee App | Tile **ERP** | `access employee app erp` |

## Permintaan ERP

1. Staff mengajukan dari tile **ERP**: modul ERP, tipe request, keterangan, dan lampiran. Riwayat permintaan ada di halaman riwayat.
2. IT memproses di back office dengan transisi status yang dibatasi: `submitted → in_review → approved/rejected`, `approved → in_progress → warranty`. Setiap perubahan dicatat di `it_request_activities`, dan IT dapat menambah catatan.
3. **Garansi 3 hari kerja**: setelah `warranty`, pemohon dapat mengklaim garansi dari riwayat selama masa garansi berlaku. Permintaan kembali ke `submitted`. Bila tidak diklaim, `erp-requests:complete-warranty` (per jam) menutupnya menjadi `completed`.
4. **SLA** dihitung dalam jam kerja hari kerja (`ItBusinessHoursService`, `ErpRequestSlaService`) dan ditampilkan di widget KPI. `erp-requests:backfill-sla` mengisi SLA dari riwayat aktivitas.

Status: `submitted`, `in_review`, `approved`, `rejected`, `in_progress`, `warranty`, `completed`. Export Excel tersedia.

## Master

- **Modul ERP**: nama, urutan, status aktif, jumlah request.
- **Request Types**: nama, prioritas, urutan, status aktif.

## Bulk Data

- **Product**: membuat atau mengubah produk di ESB untuk satu atau beberapa comcode sekaligus (kategori, kode, nama, flag requestable/purchasable/saleable, VAT, toleransi penerimaan, BOM, custom field, dan Product Details per UOM: harga dasar, SKU, konversi, flag stock/purchase/transfer/sales/base/active). Submission dicatat per batch (`bulk_product_submissions`) dan dikirim lewat `SubmitBulkProductAction`.
- **Promotion**: membuat promosi ESB untuk comcode dan branch terpilih (tipe, kode master, periode, hari dan jam, aplikasi, kategori, target diskon, voucher, batas pemakaian, metode pembayaran, visit purpose), dengan picker menu per comcode.

## Review Data (Master Bahan)

Inbox IT untuk review Master Bahan/WIP dari R&D sebelum dibuat atau ditautkan di ESB: claim, koreksi data teknis, kembalikan ke R&D, tautkan ke produk ESB existing, approve push, dan rekonsiliasi hasil ESB yang tidak pasti. Detail alurnya ada di [Research & Development](../rnd/README.md#master-bahan-dan-sourcing).

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{ErpRepairRequests,ErpModules,ItRequestTypes,BulkProductSubmissions,MaterialReviews}/`, `app/Filament/Helpdesk/Pages/{BulkDataPage,BulkDataPromotionPage}.php`, `app/Filament/Helpdesk/Widgets/ErpRequestSlaStatsWidget.php` |
| Employee App | `app/Filament/Casual/Pages/{ErpRequestPage,ErpRequestHistoryPage}.php` |
| Logika | `app/Actions/{UpdateErpRequestStatusAction,ClaimErpRequestWarrantyAction,SubmitBulkProductAction}.php`, `app/Services/{ErpRequestSlaService,ItBusinessHoursService}.php` |
| Enum | `app/Enums/ItRequestStatus.php` |
| Model | `app/Models/{ErpRepairRequest,ErpModule,ItRequest,BulkProduct}*.php` |
| Command | `app/Console/Commands/{CompleteErpRequestWarranties,BackfillErpRequestSla}.php` |
| Test | `tests/Feature/{ErpRequest,ItRequest,BulkProduct}*Test.php` |

## Data

`ErpRepairRequest`, `ItRequestActivity`, `ErpModule`, `ItRequestType`, `BulkProductSubmission`, `BulkProductSubmissionItem`. Lihat [Data Model](../../architecture/data-model.md#information-technology).

## Role bawaan

`IT_STAFF` mengelola seluruh menu ini, termasuk System Monitoring. `STORE_STAFF` mengajukan Permintaan ERP.
