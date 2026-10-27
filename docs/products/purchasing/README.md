# Purchasing

Permintaan pembelian dari store, insiden kepatuhan vendor dari penerimaan barang, sourcing bahan, dan pemesanan material marketing. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Purchasing | Vendor Compliance | `view vendor compliance incidents`, `edit vendor compliance incidents` |
| | Permintaan Pembelian | `view purchase requests` (+ create/edit/delete) |
| | Sourcing Bahan | `view material sourcings`, `submit material sourcing`, `hand over material samples` |
| | Material Marketing (Perlu Dipesan) | `view marketing material fulfillments`, `process marketing material as purchasing` |
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

## Material Marketing

1. Desain mengunggah material marketing pada produk Project R&D (permission `upload marketing materials`).
2. Purchasing di **Perlu Dipesan** menandai **Dipesan**: vendor/percetakan, tanggal pesan, estimasi selesai, dan catatan.
3. Inventory di **Perlu Diterima** menandai **Diterima**: jumlah, tanggal terima, lokasi penyimpanan, dan catatan.

Status: `not_started`, `ordered`, `received`.

## Data

`PurchaseRequest`, `PurchaseRequestStatusHistory`, `VendorComplianceIncident`, `MarketingMaterialFulfillment`, `RndProjectMarketingMaterial`, `MaterialSourcing`. Lihat [Data Model](../../architecture/data-model.md#purchasing).

## Role bawaan

`PURCHASING_STAFF` mengelola semua menu di atas. `STORE_STAFF` mengajukan permintaan pembelian.
