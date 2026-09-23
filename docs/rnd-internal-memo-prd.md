# PRD — R&D Internal Memo Bulanan

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 23 September 2026 |
| Status | Rancangan final untuk review; implementasi belum dimulai |
| Modul | Research & Development |
| Menu | `R&D → Memo Internal` |
| Company Code | Khusus `BLSS`; tidak dapat dipilih atau diganti dari UI/request |
| Tujuan | Membuat Memo Internal bulanan berisi Menu yang akan dirilis, Shelf Life, dan forecast bahan berdasarkan BOM Menu serta Assembly |
| Sumber Menu | API ESB Master Menu; tidak mengambil kandidat dari R&D Project |
| Output | PDF berbasis snapshot data yang sudah difinalisasi |
| Acuan | `code-remediation-prd.md`, `esb-integration-consolidation-roadmap.md`, `ui-consistency-prd.md` |

Dokumen ini menjadi acuan implementasi. Dokumen tidak mengotorisasi deployment, migration production, atau mutation data ESB. Implementasi dilakukan bertahap dengan test terisolasi dari network eksternal.

## 2. Requirement yang sudah dikunci

1. Kandidat Menu murni berasal dari API ESB Master Menu.
2. Project dan Product Release R&D tidak menjadi sumber atau syarat pemilihan Menu.
3. Fitur hanya menggunakan Company Code `BLSS`.
4. Pengguna tidak memilih Company Code atau cabang pada form Memo.
5. Satu Menu hanya mempunyai satu BOM melalui field `bomID` pada response Menu.
6. Menu dengan `bomID > 0` dapat diproses.
7. Menu dengan `bomID = 0` dianggap belum mempunyai BOM dan tidak ditelusuri melalui `menuPackages`.
8. `menuPackages`, `menuExtras`, `menuIcons`, `menuTags`, dan `relatedMenus` tidak ikut kalkulasi.
9. Shelf Life tidak tersedia dari API dan dikelola sebagai master lokal.
10. Forecast Quantity dikelola lokal pada Memo versi pertama.
11. Detail BOM Menu dan BOM Assembly diambil dari ESB Core menggunakan credential BLSS.
12. WIP/Assembly diuraikan sampai bahan dasar.
13. Data API disimpan sebagai snapshot sebelum Memo difinalisasi.
14. PDF dibuat dari snapshot lokal dan tidak melakukan request langsung ke ESB.

## 3. Tujuan bisnis

Fitur membantu tim R&D menyusun dokumen bulanan yang menjelaskan:

- Menu yang akan dirilis;
- tanggal rilis dan forecast quantity;
- Shelf Life dan kondisi penyimpanan;
- BOM Menu yang digunakan;
- bahan dasar, Assembly/WIP, dan packaging;
- kebutuhan bahan per Menu dan total konsolidasi;
- data yang belum lengkap sebelum dokumen diterbitkan.

## 4. Scope

### 4.1 Termasuk

- Index dan workspace detail Memo Internal.
- Metadata dan nomor Memo.
- Pemilihan Menu dari API Master Menu BLSS.
- Server-side pagination dan pencarian katalog Menu.
- Pengambilan detail BOM berdasarkan `bomID`.
- Penguraian BOM Assembly/WIP secara rekursif.
- Master Shelf Life lokal.
- Forecast Quantity lokal per Menu.
- Forecast bahan per Menu dan konsolidasi.
- Snapshot Menu, BOM, Shelf Life, dan kalkulasi.
- Validasi blocker dan warning.
- Finalisasi, revisi, arsip, dan PDF.
- Permission, audit user, queue, logging, dan test.

### 4.2 Tidak termasuk versi pertama

- Company Code selain BLSS.
- Kandidat Menu dari R&D Project.
- Penelusuran `menuPackages` ketika `bomID = 0`.
- Menulis atau mengubah Menu/BOM pada ESB.
- Sales Projection dari Project atau API eksternal.
- Approval bertingkat atau digital signature.
- Konversi antar-UOM tanpa faktor konversi yang sah.
- Refactor besar seluruh modul R&D.

## 5. Aktor dan permission

### 5.1 Aktor

- **R&D Operator:** membuat Draft, memilih Menu, melengkapi forecast/Shelf Life, dan menjalankan sinkronisasi.
- **R&D Reviewer/Manager:** memeriksa kelengkapan dan memfinalisasi.
- **Administrator:** mengelola permission dan melihat audit.

Nama role tidak di-hardcode. Otorisasi menggunakan permission dan Policy.

### 5.2 Permission

```text
view any rnd internal memo
view rnd internal memo
create rnd internal memo
update rnd internal memo
sync rnd internal memo
finalize rnd internal memo
create rnd internal memo revision
generate rnd internal memo pdf
download rnd internal memo pdf
archive rnd internal memo
delete rnd internal memo
manage rnd product shelf life
```

Aksi yang tidak diizinkan tidak tampil di UI. Policy tetap memvalidasi mutation di server.

## 6. Status Memo

| Status | Makna | Aksi utama |
| --- | --- | --- |
| `Draft` | Metadata dan Menu masih dapat diubah | Edit, tambah Menu, sync |
| `Syncing` | Sinkronisasi BOM berjalan | Lihat progress |
| `NeedsAttention` | Ada blocker atau data gagal diambil | Perbaiki, retry |
| `Ready` | Data minimum lengkap | Review, finalisasi |
| `Finalized` | Snapshot dikunci | Generate/download PDF, buat revisi |
| `Archived` | Dokumen disembunyikan dari daftar aktif | Lihat, restore bila diizinkan |

Memo berstatus `Draft`, `NeedsAttention`, atau `Ready` dapat dihapus menggunakan permission khusus. Penghapusan selalu memakai soft delete dan mempertahankan Menu, material, snapshot, dan dokumen terkait. Memo `Syncing`, `Finalized`, dan `Archived` tidak dapat dihapus; dokumen final menggunakan alur arsip.

Transition dilakukan melalui Action khusus. Status tidak diubah langsung dari form umum.

## 7. Alur pengguna

### 7.1 Membuat Memo

Pengguna membuka `R&D → Memo Internal` dan memilih **Buat Memo**. Modal awal memuat:

- Bulan Rilis;
- Tanggal Memo;
- Nomor Memo;
- Judul;
- Kepada;
- Dari;
- Perihal;
- Catatan opsional.

Server menetapkan `company_code = BLSS`. Setelah tersimpan, pengguna diarahkan ke workspace detail berstatus `Draft`.

### 7.2 Memilih Menu

1. Pengguna membuka modal **Pilih Menu ESB**.
2. Aplikasi memanggil Master Menu menggunakan static token BLSS.
3. Modal mendukung search nama/kode dan pagination.
4. Menu menampilkan kode, nama, kategori, BOM, dan status.
5. Menu dengan `bomID = 0` tetap terlihat dengan label **Belum Memiliki BOM**, tetapi tidak dapat dipilih.
6. Menu dengan `bomID > 0` dapat dipilih.
7. Snapshot ringkas Menu disimpan ketika ditambahkan.

### 7.3 Melengkapi data per Menu

Pengguna mengisi:

- Tanggal Rilis;
- Forecast Quantity;
- Shelf Life Value;
- Shelf Life Unit;
- Storage Condition;
- Catatan Shelf Life bila diperlukan.

Jika master Shelf Life lokal tersedia untuk `BLSS + menuID`, form terisi otomatis. Nilai yang dipakai tetap disalin ke snapshot ketika finalisasi.

### 7.4 Sinkronisasi BOM

1. Sistem membaca `bomID` dari snapshot Menu.
2. Sistem mengambil detail BOM Menu melalui ESB Core BLSS.
3. Sistem memeriksa setiap komponen.
4. Bahan dasar dan packaging menjadi kandidat forecast.
5. Komponen WIP/Assembly dicari BOM turunannya.
6. BOM Assembly diuraikan secara rekursif.
7. Sistem mendeteksi circular reference dan Assembly yang belum ditemukan.
8. Hasil normalisasi dan payload sumber disimpan lokal.

### 7.5 Review dan finalisasi

Workspace menampilkan status kelengkapan, rincian BOM, jalur Assembly, Shelf Life, forecast per Menu, forecast konsolidasi, warning, blocker, serta waktu sync terakhir.

Finalisasi hanya berhasil jika seluruh blocker selesai. Finalisasi menyimpan snapshot final, hash, user, dan waktu. Data Finalized tidak dapat diedit; koreksi dilakukan melalui revisi baru.

### 7.6 Generate PDF

PDF dihasilkan dari snapshot final. Generate PDF tidak memanggil ESB. Setiap revisi menghasilkan record dokumen dan file terpisah.

## 8. Integrasi API dan autentikasi

### 8.1 Ringkasan

| Proses | Endpoint | Autentikasi | Context |
| --- | --- | --- | --- |
| Menu List/Detail | `GET {ESB_BASE_URL}/corev1/master/get-menu` | Static token `ESB_TOKEN_BLSS` | BLSS |
| Login ESB Core | `POST {ESB_CORE_BASE_URL}/auth/login` | `ESB_CORE_BLSS_USERNAME` + `ESB_CORE_BLSS_PASSWORD` | BLSS |
| Browse BOM | `GET {ESB_CORE_BASE_URL}/product/bom` | Access token hasil login BLSS | BLSS |
| Detail BOM Menu | `GET {ESB_CORE_BASE_URL}/product/bom/{bomID}` | Access token hasil login BLSS | BLSS |
| Detail BOM Assembly | `GET {ESB_CORE_BASE_URL}/product/bom/{bomID}` | Access token hasil login BLSS | BLSS |
| Shelf Life | Database lokal | Tanpa token | BLSS |
| Forecast | Database lokal | Tanpa token | BLSS |
| PDF | Snapshot lokal | Tanpa token | BLSS |

### 8.2 Master Menu

Parameter yang dipakai bila didukung endpoint:

```text
page
limit
menuName
menuCode
Boolean=1
```

Field minimum:

```text
menuID
menuCode
menuName
categoryDetail
bomID
bomName
flagActive
description
menuShortName
menuTemplates
```

Field package/extra/tag boleh disimpan pada raw snapshot untuk audit, tetapi tidak dinormalisasi atau dihitung.

### 8.3 Aturan `bomID`

```text
bomID > 0  → dapat dipilih dan disinkronkan
bomID = 0  → tidak dapat dipilih; package tidak ditelusuri
```

Satu Menu hanya memiliki satu BOM. Tidak ada pemilihan atau allocation beberapa BOM.

### 8.4 Credential

Konfigurasi wajib:

```dotenv
ESB_TOKEN_BLSS=
ESB_CORE_BLSS_USERNAME=
ESB_CORE_BLSS_PASSWORD=
```

Aturan:

- tidak ada fallback ke Company Code lain;
- tidak membaca `env()` langsung dari Page atau service domain;
- nilai diakses melalui `config/esb.php`;
- credential tidak masuk UI, log, exception, snapshot, atau PDF;
- access token Core disimpan sementara dengan cache key khusus BLSS;
- refresh sekali setelah `401`;
- static token Menu tidak digunakan sebagai access token Core.

### 8.5 Kontrak API yang perlu divalidasi

Detail BOM harus menyediakan:

- `bomID`, `bomCode`, `bomName`, dan `bomTypeName`;
- output quantity/yield dan output UOM untuk Assembly;
- `bomDetails`;
- Product ID/Product Detail ID komponen;
- kode, nama, kategori, qty, dan UOM;
- waste/tolerance bila tersedia;
- identitas untuk menemukan BOM WIP/Assembly.

Peningkatan yang membantu tetapi tidak memblokir fondasi:

- filter Menu berdasarkan `menuID`;
- server-side `menuName` search;
- parameter `limit` terdokumentasi;
- `updatedAt`, version, atau effective date;
- bulk detail BOM.

## 9. Aturan resolusi BOM

### 9.1 Identitas bahan

Prioritas pencocokan:

1. `productDetailID`;
2. `productID`;
3. `productCode`;
4. nama dan UOM sebagai fallback dengan warning.

### 9.2 Assembly/WIP

Jika komponen merupakan WIP/Assembly:

1. cari BOM aktif yang menghasilkan produk tersebut;
2. cocokkan identitas dengan urutan di atas;
3. ambil detail BOM;
4. kalikan komponen Assembly dengan kebutuhan parent;
5. lanjutkan sampai bahan dasar;
6. simpan jalur `Menu → BOM Menu → Assembly → Bahan`.

Jika BOM turunan tidak ditemukan, WIP tidak dihitung sebagai bahan dasar. Sistem membuat blocker berisi Menu, jalur BOM, kode, dan nama WIP.

### 9.3 Circular reference

Resolver membawa daftar `bomID` yang sudah dikunjungi pada setiap jalur. Jika BOM yang sama ditemukan kembali, jalur dihentikan dan Memo mendapat blocker circular BOM.

### 9.4 Package Menu

Menu dengan `bomID = 0` tidak diuraikan melalui `menuPackages`, walaupun response menyediakan anggota package.

## 10. Rumus forecast

### 10.1 Forecast Menu

```text
Projected Menu = Forecast Quantity yang disimpan pada item Memo
```

Forecast harus lebih besar dari nol untuk finalisasi.

### 10.2 Kebutuhan komponen

```text
Net Requirement
= Forecast Menu × Qty Komponen ÷ Output Yield BOM
```

Jika kontrak BOM Menu tidak mempunyai output yield, baseline dianggap satu Menu setelah asumsi tersebut dibuktikan lewat contract test.

### 10.3 Waste dan tolerance

Jika tersedia dari ESB:

```text
Waste Quantity
= Net Requirement × Waste Percentage

Tolerance Quantity
= (Net Requirement + Waste Quantity) × Tolerance Percentage

Gross Requirement
= Net Requirement + Waste Quantity + Tolerance Quantity
```

Jika field tidak tersedia, nilainya nol dan tidak dibuat-buat oleh aplikasi.

### 10.4 Assembly bertingkat

```text
Assembly Requirement
= Parent Requirement × Qty Assembly Component ÷ Assembly Output Yield
```

### 10.5 Konsolidasi

```text
company_code + productDetailID + normalized UOM
```

Jika Product Detail ID tidak ada, fallback menghasilkan warning. Produk dengan UOM berbeda tidak dijumlahkan tanpa conversion factor yang sah. Versi pertama menampilkan UOM berbeda sebagai baris terpisah.

## 11. Shelf Life lokal

Master lokal:

```text
rnd_esb_product_shelf_lives
├── id
├── company_code
├── esb_product_id nullable
├── esb_product_detail_id nullable
├── esb_menu_id nullable
├── product_code nullable
├── product_name
├── shelf_life_value
├── shelf_life_unit
├── storage_condition
├── notes nullable
├── effective_from nullable
├── effective_until nullable
├── is_active
├── created_by
├── updated_by
├── timestamps
└── soft_deletes
```

Versi pertama mewajibkan Shelf Life Menu hasil akhir. Shelf Life bahan/Assembly ditampilkan bila tersedia. Memo final memakai snapshot, bukan membaca ulang master.

## 12. Model data

### 12.1 `rnd_internal_memos`

```text
id
company_code default BLSS
memo_number
title
period_month
memo_date
recipient
sender
subject
notes nullable
status
revision
source_synced_at nullable
snapshot_hash nullable
created_by
updated_by nullable
finalized_by nullable
finalized_at nullable
archived_by nullable
archived_at nullable
timestamps
soft_deletes
```

Nomor Memo unik dan kombinasi periode/revisi tidak boleh duplikat.

### 12.2 `rnd_internal_memo_menus`

```text
id
rnd_internal_memo_id
esb_menu_id
menu_code nullable
menu_name
category_detail nullable
esb_bom_id
bom_name nullable
release_date
forecast_quantity
shelf_life_value
shelf_life_unit
storage_condition
shelf_life_notes nullable
sync_status
synced_at nullable
sync_error nullable
menu_snapshot json
bom_snapshot json nullable
sort_order
timestamps
```

Tidak ada ketergantungan wajib ke `rnd_projects` atau `rnd_project_products`.

### 12.3 `rnd_internal_memo_materials`

```text
id
rnd_internal_memo_menu_id
parent_material_id nullable
source_bom_id
source_bom_code nullable
source_path json
depth
esb_product_id nullable
esb_product_detail_id nullable
product_code nullable
product_name
category_name nullable
uom_id nullable
uom_name
quantity_per_menu
net_quantity
waste_percentage
waste_quantity
tolerance_percentage
tolerance_quantity
gross_quantity
is_wip
is_packaging
product_snapshot json nullable
timestamps
```

### 12.4 `rnd_internal_memo_documents`

```text
id
rnd_internal_memo_id
revision
disk
file_path
file_size
checksum
generated_by
generated_at
expires_at nullable
timestamps
```

### 12.5 `rnd_internal_memo_sync_runs`

```text
id
rnd_internal_memo_id
company_code default BLSS
status
started_at
finished_at nullable
request_count
error_count
error_summary nullable
triggered_by
timestamps
```

## 13. Struktur kode sesuai Code Remediation

Lokasi akhir mengikuti struktur repository aktual ketika implementasi. Jangan memindahkan seluruh modul R&D hanya untuk fitur ini.

```text
app/
├── Actions/Rnd/InternalMemo/
│   ├── CreateInternalMemoAction.php
│   ├── UpdateInternalMemoAction.php
│   ├── AddMenuToInternalMemoAction.php
│   ├── SynchronizeInternalMemoAction.php
│   ├── FinalizeInternalMemoAction.php
│   ├── CreateInternalMemoRevisionAction.php
│   ├── GenerateInternalMemoPdfAction.php
│   └── ArchiveInternalMemoAction.php
├── Services/Rnd/InternalMemo/
│   ├── InternalMemoMenuCatalogService.php
│   ├── InternalMemoBomResolver.php
│   ├── InternalMemoForecastCalculator.php
│   ├── InternalMemoShelfLifeResolver.php
│   ├── InternalMemoValidationService.php
│   ├── InternalMemoSnapshotService.php
│   └── InternalMemoPdfDataService.php
├── Jobs/Rnd/
│   ├── SynchronizeInternalMemoJob.php
│   └── GenerateInternalMemoPdfJob.php
├── Models/
├── Policies/
└── Enums/
```

Tanggung jawab:

- Page: state UI, pagination, modal, feedback.
- Action: satu use case dan transaction boundary.
- Service domain: resolver, kalkulasi, validasi, snapshot.
- Integration client: HTTP, token, timeout, parsing, error mapping.
- Job: proses berat dan idempotent.
- Blade: presentasi tanpa query atau HTTP.

Integrasi Menu mengekstrak pola katalog yang sekarang dipakai `EsbPromotionService`, bukan menyalin raw HTTP. Integrasi BOM memakai `EsbCoreClient` dengan context BLSS, bukan mekanisme token global lama.

## 14. Rancangan UI

UI mengikuti `docs/ui-consistency-prd.md` dan R&D Project.

### 14.1 Index

1. Header ringkas dengan ikon, judul **Memo Internal**, deskripsi, dan tombol **Buat Memo**.
2. Filter Bulan, Tahun, Status, dan Search.
3. Ringkasan Draft, Needs Attention, Ready, dan Finalized.
4. Daftar Memo.
5. Pagination ringkas dengan arrow.

Gunakan border, `rounded-2xl`, aksen biru, dan tanpa shadow pada kartu biasa.

### 14.2 Workspace detail

Section:

1. Informasi Memo dan aksi.
2. Status kelengkapan.
3. Menu yang akan dirilis.
4. Shelf Life.
5. Forecast per Menu.
6. Forecast konsolidasi.
7. Riwayat revisi dan PDF.

### 14.3 Modal pemilih Menu

- Search nama/kode;
- server-side pagination;
- loading spinner;
- kode, nama, kategori, BOM, dan status;
- Menu `bomID = 0` disabled dengan alasan;
- tidak membawa seluruh katalog ke state Livewire;
- pilihan sementara tetap konsisten ketika pindah halaman.

### 14.4 State UI

- Loading dibedakan dari empty state.
- Error integrasi memberi pesan aman dan tombol retry.
- Sync menunjukkan progress dan mencegah submit ganda.
- Finalized read-only.
- Tabel lebar scroll hanya pada container tabel.
- Ikon memakai Heroicons Blade; icon-only button memiliki `aria-label`.

## 15. Validasi

### 15.1 Blocker finalisasi

- Memo belum memiliki Menu.
- Ada Menu dengan `bomID = 0`.
- Forecast Quantity kosong/tidak valid.
- Shelf Life Menu belum diisi.
- Detail BOM Menu belum berhasil diambil.
- WIP/Assembly tidak mempunyai BOM turunan yang cocok.
- Circular BOM ditemukan.
- Output yield wajib kosong atau nol.
- UOM yang perlu digabung tidak mempunyai conversion factor.
- Sinkronisasi berjalan atau gagal.
- Snapshot berubah setelah review tanpa sync ulang.

### 15.2 Warning

- Product Code kosong tetapi identitas lain tersedia.
- Product Detail ID komponen tidak tersedia.
- Shelf Life Assembly/bahan belum tersedia.
- Katalog tidak mempunyai version/updated timestamp.
- Produk sama muncul dengan UOM berbeda.
- Waste/tolerance tidak tersedia dan dianggap nol.

## 16. PDF

Susunan:

1. Kop perusahaan.
2. Nomor, tanggal, kepada, dari, perihal, periode, dan revisi.
3. Ringkasan Menu rilis.
4. All Product Data.
5. All Shelf Life.
6. Forecast Product by Menu Memo.
7. Forecast bahan konsolidasi.
8. Catatan operasional.
9. Area penandatangan.
10. Footer waktu generate, revisi, dan checksum/reference.

Tabel Menu:

```text
Menu | Menu Code | Tanggal Rilis | Forecast Qty | Shelf Life | BOM
```

Tabel forecast per Menu:

```text
Bahan | Kode | Kategori | UOM | Net | Waste | Tolerance | Gross
```

Tabel konsolidasi:

```text
Bahan | Kode | Kategori | UOM | Total Gross | Digunakan di
```

File disimpan pada disk terkonfigurasi. Jika memakai object storage, download melalui route terotorisasi atau temporary URL. Setiap revisi memiliki checksum dan tidak menimpa file lama.

## 17. Queue, cache, dan reliability

- Sinkronisasi banyak Menu dan generate PDF melalui Job.
- Idempotency berdasarkan Memo, revisi, dan versi sync.
- Retry hanya untuk timeout/koneksi dan server error yang aman.
- `401` memicu refresh access token satu kali.
- Error kontrak/validasi tidak di-retry tanpa perubahan data.
- Cache katalog memasukkan BLSS, page, dan filter.
- Cache BOM memasukkan BLSS dan `bomID`.
- Finalisasi membaca database, bukan cache sebagai sumber kebenaran.
- Log memuat endpoint alias, Memo ID, duration, status, dan correlation ID tanpa credential.

## 18. Test strategy

### 18.1 Unit

```text
InternalMemoBomResolverTest
InternalMemoForecastCalculatorTest
InternalMemoShelfLifeResolverTest
InternalMemoValidationServiceTest
InternalMemoSnapshotServiceTest
```

Kasus minimum:

- satu Menu satu BOM;
- `bomID = 0` ditolak tanpa menelusuri package;
- Assembly satu/beberapa tingkat;
- WIP tanpa BOM;
- circular BOM;
- output yield;
- waste/tolerance;
- konsolidasi bahan;
- UOM berbeda;
- forecast nol;
- snapshot tidak berubah setelah master berubah.

### 18.2 Feature

```text
RndInternalMemoPageTest
RndInternalMemoWorkflowTest
RndInternalMemoPermissionTest
RndInternalMemoPdfTest
RndInternalMemoArchiveTest
RndProductShelfLifeTest
```

### 18.3 Contract/integration

Seluruh request memakai `Http::fake()`:

- static token BLSS pada Master Menu;
- access token BLSS pada ESB Core;
- token cache dan login baru;
- refresh setelah `401`;
- timeout/connection error;
- pagination Menu;
- detail BOM sukses/gagal;
- payload malformed;
- tidak fallback ke Company Code lain;
- test gagal bila terjadi network eksternal yang tidak di-fake.

### 18.4 Regression

Jalankan test R&D Project, BOM Recipe, Material Forecast, Shelf Life, export, permission, dan ESB client yang terdampak.

## 19. Urutan implementasi

### Phase 0 — Contract validation

- Dapatkan contoh response detail BOM Menu dan Assembly.
- Kunci output yield, kategori, qty, UOM, waste, tolerance.
- Kunci format nomor, kop, dan penandatangan.
- Buat contract fixture tanpa data rahasia.

### Phase 1 — Fondasi data dan akses

- Migration, Model, Factory, Enum, Policy, permission, relasi.
- Master Shelf Life lokal.
- Test schema, transition, authorization.

### Phase 2 — Katalog Menu BLSS

- Integration service Master Menu BLSS.
- Pagination/search server-side.
- Index, modal create, modal picker.
- Aturan `bomID = 0`.
- Contract test tanpa network.

### Phase 3 — Sinkronisasi BOM

- Detail BOM Menu melalui Core BLSS.
- Resolver Assembly rekursif.
- Cycle detection, jalur BOM, raw snapshot, normalized rows.
- Job sync, progress, retry, error mapping.

### Phase 4 — Forecast dan Shelf Life

- Input forecast dan Shelf Life.
- Kalkulator net/waste/tolerance/gross.
- Konsolidasi berdasarkan Product Detail ID dan UOM.
- Tampilan per Menu dan gabungan.

### Phase 5 — Validation dan finalisasi

- Blocker/warning.
- Status Ready.
- Snapshot hash dan locking.
- Revisi dan audit user.

### Phase 6 — PDF

- Data builder dan template.
- Job generate, versioning, checksum, authorized download.
- Visual verification.

### Phase 7 — Hardening

- Query/payload measurement.
- Cache/invalidation.
- Full suite dengan memory configuration terdokumentasi.
- Responsive/accessibility check.
- Deployment, rollback, monitoring notes.

## 20. Quality gates

1. `vendor/bin/pint --dirty --format agent` setelah perubahan PHP.
2. Test file terfokus.
3. Test modul R&D dan ESB terkait.
4. Full suite sesuai baseline Code Remediation sebelum merge final.
5. `git diff --check`.
6. Frontend build bila source asset berubah.
7. Route dan Filament discovery check.
8. PDF render/visual verification.
9. Tidak ada HTTP eksternal pada test.
10. Tidak ada credential/token pada diff, log, fixture, atau snapshot.

## 21. Deployment dan rollback

1. Backup database.
2. Pastikan credential BLSS tersedia di environment server.
3. Deploy kode sesuai prosedur server.
4. Jalankan migration.
5. Jalankan permission seeder yang disertakan dalam commit.
6. Clear cache aplikasi.
7. Restart queue worker.
8. Smoke test Menu List, Draft, sync satu Menu, finalisasi, dan PDF.
9. Monitor log ESB, queue, timeout, dan error rate.

Rollback kode tidak menghapus tabel/data Memo. Migration destructive menjadi pekerjaan terpisah.

## 22. Acceptance criteria

1. Menu sidebar R&D tampil sesuai permission.
2. Pengguna membuat Memo tanpa memilih Company Code/cabang.
3. Seluruh Menu berasal dari Master Menu BLSS.
4. Menu `bomID = 0` tidak dapat dipilih dan package tidak ditelusuri.
5. Menu `bomID > 0` dapat disinkronkan.
6. Assembly/WIP diuraikan sampai bahan dasar dengan jalur audit.
7. Forecast per Menu dan konsolidasi memakai rumus teruji.
8. Shelf Life lokal disalin ke snapshot final.
9. Blocker mencegah finalisasi data tidak lengkap.
10. Finalized Memo tidak berubah saat data API/master berubah.
11. PDF dibuat hanya dari snapshot dan mempunyai riwayat revisi.
12. Static token hanya untuk Master Menu BLSS.
13. Access token login BLSS hanya untuk ESB Core BOM.
14. Tidak ada fallback ke Company Code lain.
15. UI konsisten dengan R&D Project dan responsif.
16. Test eksternal memakai fake dan quality gates lulus.

## 23. Open items sebelum kalkulasi final

1. Contoh response lengkap BOM Menu dan Assembly BLSS.
2. Cara pasti menemukan BOM Assembly jika parent tidak memberi child `bomID`.
3. Definisi output yield pada BOM Menu dan Assembly.
4. Ketersediaan waste/tolerance setiap tipe BOM.
5. Aturan pembulatan per UOM.
6. Apakah Shelf Life hanya wajib untuk Menu atau juga Assembly.
7. Format nomor Memo, kop, tujuan, dan penandatangan.
8. Apakah forecast satu angka per Menu atau perlu region/channel.
9. Disk penyimpanan dan masa retensi PDF.

Open items tidak menghalangi fondasi data, permission, index, Draft, katalog Menu BLSS, dan picker. Kalkulator/PDF final tidak boleh mengasumsikan field yang belum terbukti dari response API.
