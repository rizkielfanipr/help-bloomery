# Inventory

Stock card harian store, penerimaan barang dari Purchase Order ESB beserta QC inbound, Item Journal (penyesuaian stok ESB), lokasi penyimpanan, pengaturan produk, dan penerimaan material marketing. Kondisi per 30 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Inventory | Stock Card | `view stock cards`; review: `review stock cards as supervisor`, `review stock cards as finance` |
| | Receiving | `view goods receipts` |
| | Item Journal | `view quality control item journals`; semua user: `view all quality control item journals` |
| | Stock Card Settings | `view stock card settings`, `edit stock card settings` |
| | Lokasi | `view locations` (+ create/edit/delete) |
| | Tipe Lokasi | `view location types` (+ create/edit/delete) |
| | Product List | `view product list`, `edit product list` |
| | Material Marketing (Perlu Diterima) | `view marketing material fulfillments`, `process marketing material as inventory` |
| Employee App | Tile **Stock Card** | `access employee app stock card` |
| Employee App | Tile **Receiving** | `access employee app goods receipt` |
| Employee App | Tile **Item Journal** | `access employee app item journal` + `view quality control item journals`; aksi: `create/submit quality control item journals`, `delete quality control item journal attachments` |

## Stock Card

1. Staff memilih tanggal di tile **Stock Card**. Daftar produk harian dipilih otomatis per kategori sesuai **Stock Card Settings** (`StockCardDailySelectionService`), dengan katalog dan pergerakan stok dari ESB.
2. Staff mengisi stok fisik dan staff in charge, lalu submit. Card bisa diedit pengirim selama statusnya masih mengizinkan.
3. Supervisor me-review (`pending_supervisor`), lalu Finance me-review (`pending_finance`), hingga `completed`. Saldo sistem dapat di-refresh dari ESB pada tahap review (`StockCardEsbSynchronizer`).
4. Kalender dan riwayat stock card tersedia di Employee App. Back office menampilkan selisih, revisi, dan pengirim.

Status: `draft`, `pending_supervisor`, `pending_finance`, `completed`.

**Stock Card Settings** mengatur kategori produk per company code yang ikut stock card. Sumber mapping ESB per branch ditentukan di Master Branch (*Sumber Stock Card & Memo Internal*).

## Receiving

1. Inventory Pusat membuka tile **Receiving**, mencari Purchase Order ESB berstatus Authorized/Receiving, lalu memilihnya. Sumber PO hanya company `BLSS`. Permission `access employee app goods receipt` berlaku untuk PO dari semua cabang ESB BLSS tanpa akses cabang user dan tanpa mapping cabang lokal; `access_all_branches` tanpa permission ini tetap ditolak. Cabang tujuan, supplier, dan lokasi selalu mengikuti PO ESB.
2. Untuk setiap item, staff mengisi jumlah diterima (atau memakai **Isi dari outstanding** / **Tandai semua OK**) dan kondisi. Item dapat dibagi menjadi beberapa batch (**Tambah batch**). **Detail Batch** berisi tanggal produksi dan kedaluwarsa.
3. QC inbound dinilai otomatis (`InboundGoodsReceiptQcService`): dokumen, sisa umur simpan, cold chain, sampling, dan disposisi (diterima/hold/ditolak). Foto dokumen, barang, dan bukti item dilampirkan.
4. Sebelum menulis, submit memeriksa ulang server-side: company harus `BLSS`, status PO terbaru masih Authorized/Receiving, cabang ESB PO terbaru sama dengan saat PO dibuka, dan lokasi termasuk daftar lokasi cabang tersebut. PO, company, dan daftar lokasi dikunci (`#[Locked]`) sehingga tidak dapat diubah dari browser. Bila cabang PO berubah, user diminta memuat ulang PO dan memilih ulang lokasi; tujuan tidak dialihkan diam-diam.
5. Cabang lokal hanya metadata opsional dari `EsbBranchMappingResolver::lookup()` yang read-only: mapping aktif exact company + ESB branch ID dipakai lebih dulu; fallback kode cabang hanya bila tepat satu mapping aktif cocok. Tanpa mapping, receipt tetap disimpan dengan `local_branch_id` kosong, sedangkan `company_code`, `esb_branch_id`, dan `branch_name` tetap terisi. Lookup tidak pernah membuat, mengaktifkan, atau mengubah mapping Master Branch.
6. Submit membuat Goods Receipt di ESB (`EsbGoodsReceiptService::create`) satu kali per `submission_key`, hanya untuk qty Accepted; Hold-only/Rejected-only tidak memanggil ESB. Mutasi tercatat di System Monitoring (`EsbTransactionRecorder`, `branch_id` kosong bila tanpa mapping). Status: `processing`, `succeeded`, `partial_succeeded`, `qc_hold`, `qc_rejected`, `failed` (ESB menolak), `unknown` (koneksi terputus; rekonsiliasi dulu, tanpa retry otomatis). Item yang ditolak membuat **insiden Vendor Compliance** dengan poin demerit dan notifikasi ke `PURCHASING_STAFF` (lihat [Purchasing](../purchasing/README.md#vendor-compliance)).

Back office **Receiving** bersifat read-only dan menampilkan nomor PO, nomor GR, tanggal, supplier, cabang, lokasi, pengirim, dan waktu. Aksesnya memakai `view goods receipts`, terpisah dari permission Employee App. Viewer `access_all_branches` melihat semua receipt termasuk yang `local_branch_id` kosong; viewer terbatas hanya melihat receipt cabang lokalnya atau yang cabang ESB-nya dimapping aktif ke cabangnya.

Remediasi: [Receiving Terpusat BLSS](receiving-central-blss/README.md) — [PRD](receiving-central-blss/prd.md), [laporan implementasi](receiving-central-blss/reports/implementation-report.md), dan [rollout runbook](receiving-central-blss/rollout.md).

## Item Journal

Penyesuaian stok (tambah/kurang) ke ESB Core. Nama model, tabel, route resource, dan permission aksi masih memakai prefix legacy `quality_control_item_journal*`/`QualityControlItemJournal*` agar data lama, deep link, dan monitoring tetap valid.

1. Staff membuka tile **Item Journal** (tidak memerlukan akses Quality Control). Halaman memerlukan `access employee app item journal` dan `view quality control item journals`; tile hanya tampil bila keduanya dimiliki. Tombol kembali mengarah ke launcher.
2. **Buat Item Journal** (`create quality control item journals`): tanggal, Company Code, cabang dan lokasi ESB, produk dari picker Master Product ESB (dengan stok saat ini), purpose, mode **Tambah/Kurangi Stok**, qty, HPP opsional, dan informasi tambahan.
3. **Foto Bukti** opsional: tombol **Ambil Foto** memakai `accept="image/*"` dan `capture="environment"` sehingga kamera belakang dibuka bila perangkat mendukung; sebagian browser tetap menawarkan galeri. Foto diambil satu per satu, ditampilkan sebagai preview dengan **Ambil Ulang** dan **Hapus**, maksimal 5 foto dan 5 MB per foto. Server hanya menerima JPEG, PNG, atau WebP yang benar-benar dapat dibaca sebagai gambar; PDF tidak lagi diterima sebagai lampiran baru. Halaman menampilkan pesan bila izin kamera ditolak, kamera tidak terdeteksi, atau upload sedang berlangsung.
4. Submit (`submit quality control item journals`) membuat Item Journal di ESB (`EsbItemJournalService::create`) satu kali per `submission_key`, lalu mengunggah lampiran. Status: `submitting`, `succeeded`, `attachment_failed` (journal dibuat, lampiran gagal; dapat **Coba Upload Lagi**), `unknown` (koneksi terputus; perlu rekonsiliasi), `verification_required`. Semua mutasi tercatat di System Monitoring (`EsbTransactionRecorder`).
5. **Hapus Semua Attachment** memerlukan `delete quality control item journal attachments`. Lampiran PDF lama tetap dapat dilihat, dicoba ulang, dan dihapus.
6. Riwayat menampilkan 50 journal terbaru milik user, atau semua journal untuk `view all quality control item journals`. Journal tidak menyimpan branch lokal (`branch_id` selalu kosong), sehingga view-all berlaku lintas cabang.

Back Office **Inventory → Item Journal** bersifat read-only (tanpa create/edit/delete) dengan scope yang sama, dan tetap menjadi tujuan link dari System Monitoring.

## Lokasi dan Tipe Lokasi

- Lokasi bertingkat per cabang (induk–anak): nama, tipe, kode segmen, status aktif, dan catatan.
- **Denah** (floor plan) untuk menyusun dan mengubah struktur lokasi secara visual.
- Cetak label lokasi (satuan atau massal, PDF).

## Product List

Daftar produk ESB dengan pengaturan lokal per produk: standar masa kedaluwarsa (hari), kode barcode, dan lokasi penyimpanan. Label produk dapat dicetak (PDF).

## Material Marketing (Perlu Diterima)

Inventory menerima material marketing yang sudah dipesan Purchasing. Alurnya ada di [Purchasing](../purchasing/README.md#material-marketing).

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{StockCards,GoodsReceipts,QualityControlItemJournals,Locations,LocationTypes}/`, `app/Filament/Helpdesk/Pages/{StockCardSettingsPage,ProductListPage}.php` |
| Employee App | `app/Filament/Casual/Pages/{StockCardPage,StockCardEntryPage,StockCardCalendarPage,StockCardHistoryPage,GoodsReceiptPage,ItemJournalPage}.php` |
| Logika | `app/Services/{StockCard*,InboundGoodsReceiptQcService,EsbGoodsReceiptService,EsbBranchMappingResolver,EsbStockMovementService,EsbItemJournalService}.php`, `app/Services/Monitoring/EsbTransactionRecorder.php`, `app/Http/Controllers/Helpdesk/{LocationLabelPdfController,ProductLabelPdfController}.php` |
| Policy | `app/Policies/{StockCardPolicy,GoodsReceiptPolicy,QualityControlItemJournalPolicy}.php` |
| Enum | `app/Enums/StockCardStatus.php` |
| Model | `app/Models/{StockCard,GoodsReceipt,QualityControlItemJournal,Location,ProductSetting}*.php` |
| Test | `tests/Feature/{StockCard,GoodsReceipt,Receiving,EsbBranchMapping,QualityControlItemJournal,Location,ProductList}*Test.php`, `tests/Unit/{GoodsReceiptServiceTest,InboundGoodsReceiptQcServiceTest}.php` |

## Data

`StockCard` dan item-nya, `GoodsReceipt`, `GoodsReceiptItem`, `GoodsReceiptExpiry`, `Location`, `LocationType`, `ProductSetting`. Lihat [Data Model](../../architecture/data-model.md#inventory). Item Journal memakai `QualityControlItemJournal`, `QualityControlItemJournalDetail`, dan `QualityControlItemJournalAttachment` (nama legacy, tercatat di [Data Model](../../architecture/data-model.md#quality-control)).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [PRD Receiving Simplification](receiving-simplification/prd.md) | Implemented — UI batch/Detail Batch dan aksi massal; requirement branch/multi-company lama diamendemen |
| [Receiving Terpusat BLSS](receiving-central-blss/README.md) | In development — kode tanpa mapping branch lokal selesai di PR; menunggu review, UAT staging, dan rollout production |
| [PRD Item Journal Inventory](item-journal/prd.md) | Implemented — tile mandiri, foto camera-first image-only, dan menu Back Office di Inventory |

Dataset: [development-todo.csv](development-todo.csv).

## Role bawaan

`STORE_STAFF` mengisi stock card dan membuat Item Journal (journal sendiri). `SUPERVISOR_STORE` dan `FINANCE_STAFF` me-review stock card. `INVENTORY_STAFF` memakai Receiving, menerima material marketing, dan membuka Back Office Item Journal tanpa view-all; `view all quality control item journals` diberikan per role lewat Role & Permission. `QUALITY_CONTROL` tetap mendapat tile dan aksi Item Journal; `QUALITY_CONTROL_SUPERVISOR` melihat semua journal di Back Office.
