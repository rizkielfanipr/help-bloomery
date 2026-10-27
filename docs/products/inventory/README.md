# Inventory

Stock card harian store, penerimaan barang dari Purchase Order ESB beserta QC inbound, lokasi penyimpanan, pengaturan produk, dan penerimaan material marketing. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Inventory | Stock Card | `view stock cards`; review: `review stock cards as supervisor`, `review stock cards as finance` |
| | Receiving | `view goods receipts` |
| | Stock Card Settings | `view stock card settings`, `edit stock card settings` |
| | Lokasi | `view locations` (+ create/edit/delete) |
| | Tipe Lokasi | `view location types` (+ create/edit/delete) |
| | Product List | `view product list`, `edit product list` |
| | Material Marketing (Perlu Diterima) | `view marketing material fulfillments`, `process marketing material as inventory` |
| Employee App | Tile **Stock Card** | `access employee app stock card` |
| Employee App | Tile **Receiving** | `access employee app goods receipt` |

## Stock Card

1. Staff memilih tanggal di tile **Stock Card**. Daftar produk harian dipilih otomatis per kategori sesuai **Stock Card Settings** (`StockCardDailySelectionService`), dengan katalog dan pergerakan stok dari ESB.
2. Staff mengisi stok fisik dan staff in charge, lalu submit. Card bisa diedit pengirim selama statusnya masih mengizinkan.
3. Supervisor me-review (`pending_supervisor`), lalu Finance me-review (`pending_finance`), hingga `completed`. Saldo sistem dapat di-refresh dari ESB pada tahap review (`StockCardEsbSynchronizer`).
4. Kalender dan riwayat stock card tersedia di Employee App. Back office menampilkan selisih, revisi, dan pengirim.

Status: `draft`, `pending_supervisor`, `pending_finance`, `completed`.

**Stock Card Settings** mengatur kategori produk per company code yang ikut stock card. Sumber mapping ESB per branch ditentukan di Master Branch (*Sumber Stock Card & Memo Internal*).

## Receiving

1. Staff membuka tile **Receiving**, mencari Purchase Order ESB yang statusnya masih bisa diterima, lalu memilihnya. Company code ditentukan dari mapping PO itu sendiri (`EsbBranchMappingResolver`), jadi Receiving tidak terbatas pada satu company.
2. Untuk setiap item, staff mengisi jumlah diterima (atau memakai **Isi dari outstanding** / **Tandai semua OK**) dan kondisi. Item dapat dibagi menjadi beberapa batch (**Tambah batch**). **Detail Batch** berisi tanggal produksi dan kedaluwarsa.
3. QC inbound dinilai otomatis (`InboundGoodsReceiptQcService`): dokumen, sisa umur simpan, cold chain, sampling, dan disposisi (diterima/hold/ditolak). Foto dokumen, barang, dan bukti item dilampirkan.
4. Submit membuat Goods Receipt di ESB (`EsbGoodsReceiptService::create`) dan menyimpan hasilnya secara lokal. Item yang ditolak membuat **insiden Vendor Compliance** dengan poin demerit (lihat [Purchasing](../purchasing/README.md#vendor-compliance)).

Back office **Receiving** menampilkan nomor PO, nomor GR, tanggal, supplier, cabang, lokasi, pengirim, dan waktu.

## Lokasi dan Tipe Lokasi

- Lokasi bertingkat per cabang (induk–anak): nama, tipe, kode segmen, status aktif, dan catatan.
- **Denah** (floor plan) untuk menyusun dan mengubah struktur lokasi secara visual.
- Cetak label lokasi (satuan atau massal, PDF).

## Product List

Daftar produk ESB dengan pengaturan lokal per produk: standar masa kedaluwarsa (hari), kode barcode, dan lokasi penyimpanan. Label produk dapat dicetak (PDF).

## Material Marketing (Perlu Diterima)

Inventory menerima material marketing yang sudah dipesan Purchasing. Alurnya ada di [Purchasing](../purchasing/README.md#material-marketing).

## Data

`StockCard` dan item-nya, `GoodsReceipt`, `GoodsReceiptItem`, `GoodsReceiptExpiry`, `Location`, `LocationType`, `ProductSetting`. Lihat [Data Model](../../architecture/data-model.md#inventory).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [PRD Receiving Simplification](receiving-simplification/prd.md) | Implemented — batch, Detail Batch, aksi massal, multi-company |

Dataset: [development-todo.csv](development-todo.csv).

## Role bawaan

`STORE_STAFF` mengisi stock card. `SUPERVISOR_STORE` dan `FINANCE_STAFF` me-review stock card. `INVENTORY_STAFF` memakai Receiving dan menerima material marketing.
