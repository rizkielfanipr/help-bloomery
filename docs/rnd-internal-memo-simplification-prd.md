# PRD — Penyederhanaan R&D Internal Memo

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 30 September 2026 |
| Status | Siap digunakan sebagai acuan implementasi |
| Modul | Research & Development |
| Menu | `R&D → Memo Internal` |
| Company Code | Tetap `BLSS` dan tidak ditampilkan sebagai pilihan |
| Tujuan | Menyusun daftar Menu serta kebutuhan Bahan dan WIP dari BOM ESB dalam alur yang sederhana |
| Acuan teknis | `docs/code-remediation-prd.md` |
| Acuan UI | `docs/ui-consistency-prd.md` dan halaman `R&D → Project` |
| Menggantikan scope | Alur operasional pada `docs/rnd-internal-memo-prd.md` yang memakai status, finalisasi, revisi, dan workflow PDF |

Dokumen ini menjadi sumber requirement utama untuk penyederhanaan fitur Memo Internal. PRD lama tetap disimpan sebagai riwayat dan referensi implementasi yang sudah ada, tetapi jika terdapat perbedaan alur pengguna, dokumen ini yang berlaku.

## 2. Ringkasan kebutuhan

Pengguna membutuhkan workspace Memo Internal dengan alur berikut:

1. Membuat Memo.
2. Memilih satu atau beberapa Menu dari API ESB Master Menu.
3. Sistem mengambil satu `bomID` milik setiap Menu.
4. Sistem menampilkan detail BOM Menu dan komponen langsungnya.
5. Untuk komponen yang berupa WIP/Assembly, sistem mencari dan membaca BOM Assembly terkait.
6. Sistem menampilkan hasil akhir berupa daftar Bahan dan WIP.
7. Sistem mengambil Purchase UOM setiap produk dari API Product ESB.
8. Pengguna dapat mengisi Minimum Order pada setiap item.
9. Pengguna dapat menambah atau menghapus Menu kapan saja.

Fitur tahap ini tidak memakai proses Draft, Syncing, Ready, Finalized, Archived, revisi dokumen, approval, atau penguncian Memo.

## 3. Tujuan bisnis

- Mempercepat penyusunan kebutuhan produk untuk Memo Internal bulanan.
- Mengurangi input manual dengan membaca struktur BOM langsung dari ESB.
- Memberikan satu tampilan yang menjelaskan hubungan Menu, BOM Menu, Assembly, Bahan, dan WIP.
- Menyediakan Purchase UOM serta Minimum Order sebagai dasar persiapan Purchasing.
- Membuat Memo tetap mudah diperbarui ketika daftar Menu berubah.

## 4. Prinsip produk

1. **Sederhana:** satu halaman detail menjadi workspace utama.
2. **Selalu dapat diedit:** Menu dapat ditambah dan dilepas selama Memo masih tersedia.
3. **ESB sebagai sumber struktur produk:** Menu, BOM, komponen, identitas produk, dan Purchase UOM berasal dari ESB.
4. **Database lokal sebagai snapshot kerja:** data yang sudah diambil disimpan agar halaman tidak bergantung pada network setiap kali dibuka.
5. **Minimum Order bersifat lokal:** nilainya diisi dan disimpan di aplikasi ini; tidak dikirim ke ESB.
6. **Tidak ada mutation ESB:** seluruh integrasi pada fitur ini hanya membaca data.
7. **Satu Menu satu BOM:** hanya `bomID` pada Menu yang digunakan. `menuPackages` tidak ditelusuri.

## 5. Scope

### 5.1 Termasuk

- Index Memo Internal.
- Membuat Memo sederhana.
- Melihat dan mengubah informasi dasar Memo.
- Modal pemilih Menu dari API ESB Master Menu.
- Pencarian serta pagination Menu.
- Menolak Menu dengan `bomID = 0`.
- Mencegah Menu yang sama ditambahkan dua kali dalam Memo yang sama.
- Menambah dan menghapus Menu kapan saja.
- Mengambil detail BOM Menu berdasarkan `bomID`.
- Menampilkan komponen langsung BOM Menu.
- Mendeteksi komponen WIP/Assembly.
- Mengambil BOM Assembly untuk komponen WIP yang mempunyai pasangan BOM.
- Menelusuri Assembly bertingkat dengan perlindungan circular reference.
- Menampilkan struktur penelusuran per Menu.
- Menampilkan hasil akhir Bahan dan WIP.
- Mengambil Purchase UOM produk dari API Product ESB.
- Mengisi dan mengubah Minimum Order per item.
- Menyimpan snapshot hasil pengambilan ESB.
- Menyegarkan ulang data satu Menu atau seluruh Memo secara manual.
- Permission, validasi, loading, empty state, dan error state.
- Test yang terisolasi dari network eksternal.

### 5.2 Tidak termasuk pada versi sederhana

- Status workflow Memo.
- Finalisasi dan penguncian Memo.
- Approval.
- Revisi bernomor.
- Arsip sebagai bagian workflow.
- Forecast Quantity.
- Shelf Life.
- Rumus kebutuhan berdasarkan Sales Projection.
- Penggabungan kuantitas kebutuhan lintas Menu.
- Generate atau export PDF.
- Export Excel.
- Pengiriman data atau mutation ke ESB.
- Notifikasi approval.
- Integrasi dengan R&D Project atau Product Release.

Fitur yang dikeluarkan dari scope tidak perlu langsung dihapus dari database pada phase awal. UI dan alur pengguna disederhanakan terlebih dahulu, kemudian kode lama dibersihkan setelah regression test membuktikan bahwa tidak ada consumer lain yang masih membutuhkannya.

## 6. Aktor dan permission

| Permission | Kegunaan |
| --- | --- |
| `view any rnd internal memos` | Melihat menu dan index Memo |
| `view rnd internal memos` | Membuka detail Memo |
| `create rnd internal memos` | Membuat Memo |
| `update rnd internal memos` | Mengubah informasi Memo, menambah/menghapus Menu, refresh data, dan mengubah Minimum Order |
| `delete rnd internal memos` | Menghapus Memo sesuai perilaku delete existing |

Permission workflow seperti `sync`, `finalize`, atau `create revision` tidak digunakan oleh UI sederhana. Penghapusannya dari konfigurasi permission dilakukan hanya setelah audit memastikan tidak ada consumer yang masih menggunakannya.

## 7. Alur pengguna

### 7.1 Membuat Memo

Pengguna membuka `R&D → Memo Internal`, lalu menekan **Buat Memo**. Form ditampilkan dalam modal mengikuti pola halaman Project R&D.

Input minimum:

| Field | Aturan |
| --- | --- |
| Nama Memo | Wajib, maksimal 150 karakter |
| Bulan Memo | Wajib, format bulan dan tahun |
| Nomor Memo | Opsional |
| Catatan | Opsional, maksimal 1.000 karakter |

Setelah disimpan, pengguna langsung diarahkan ke workspace detail Memo.

### 7.2 Memilih Menu

Pengguna menekan **Tambah Menu** dan memilih Menu melalui modal.

Modal menyediakan:

- pencarian nama Menu;
- pencarian kode Menu jika API mendukung;
- pagination 10 data per halaman;
- loading spinner saat data diambil;
- label nama Menu, kode, dan nama BOM;
- informasi jelas jika Menu belum memiliki BOM;
- tombol pilih hanya untuk Menu dengan `bomID > 0`.

Ketika Menu dipilih:

1. Simpan snapshot Menu.
2. Simpan `menuID`, `menuCode`, `menuName`, dan `bomID`.
3. Ambil detail BOM Menu.
4. Simpan komponen langsung BOM Menu.
5. Telusuri BOM Assembly untuk komponen WIP.
6. Ambil detail Product untuk mendapatkan Purchase UOM.
7. Simpan hasil sebagai snapshot lokal.
8. Tampilkan hasil di workspace.

Jika sebagian API gagal, Menu tetap tercatat tetapi area yang gagal harus menampilkan pesan spesifik dan tombol **Coba Ambil Ulang**. UI tidak memakai status workflow; kondisi teknis boleh disimpan sebagai metadata internal untuk retry dan observability.

### 7.3 Melihat struktur per Menu

Setiap Menu ditampilkan sebagai section yang dapat dibuka atau ditutup:

```text
Menu
├── Informasi Menu
├── BOM Menu
│   └── Komponen langsung
├── Assembly yang ditemukan
│   ├── Assembly A
│   └── Assembly B
└── Item Akhir
    ├── Bahan
    └── WIP
```

Informasi Menu:

- nama Menu;
- kode Menu;
- `bomID` dan nama BOM;
- waktu terakhir data ESB diperbarui;
- tombol refresh;
- tombol hapus Menu.

### 7.4 Mengisi Minimum Order

Pada tabel Item Akhir, setiap baris mempunyai input **Minimum Order**.

Aturan:

- angka desimal, minimal `0`;
- kosong berarti belum ditentukan;
- Unit Minimum Order menggunakan Purchase UOM produk;
- perubahan disimpan eksplisit melalui tombol **Simpan Minimum Order**;
- nilai tetap tersimpan ketika data ESB di-refresh;
- nilai hanya hilang jika item benar-benar tidak lagi ada setelah Menu/BOM berubah;
- perubahan Minimum Order tidak dikirim ke ESB.

### 7.5 Menambah dan menghapus Menu

- Menu dapat ditambahkan kapan saja.
- Menu dapat dihapus kapan saja setelah konfirmasi.
- Menghapus Menu hanya menghapus snapshot dan relasi item milik Menu tersebut.
- Item dari Menu lain tidak ikut terhapus.
- Jika item yang sama digunakan beberapa Menu, data pada ringkasan tetap ada selama masih mempunyai sumber Menu.

## 8. Sumber data dan API

Seluruh request memakai Company Code `BLSS`.

| Kebutuhan | Sumber | Autentikasi | Catatan |
| --- | --- | --- | --- |
| Daftar Menu | API Master Menu ESB | Static token BLSS | Sumber tunggal pemilihan Menu |
| Detail BOM Menu | API ESB Core BOM detail | Access token ESB Core BLSS | Dipanggil memakai `bomID` dari Menu |
| Daftar/pencarian BOM Assembly | API ESB Core Browse BOM | Access token ESB Core BLSS | Mencari BOM penghasil komponen WIP |
| Detail BOM Assembly | API ESB Core BOM detail | Access token ESB Core BLSS | Mendukung penelusuran bertingkat |
| Detail Product | API Master Product ESB | Access token ESB Core BLSS | Mengambil Purchase UOM dan identitas unit |

Semua request ESB Core wajib melalui `EsbCoreClient`. Page Filament, Blade, model, dan action tidak boleh melakukan HTTP mentah.

### 8.1 Kontrak API yang wajib dibuktikan sebelum implementasi

Phase audit harus memastikan:

1. Nama endpoint dan bentuk response detail Product.
2. Field pasti untuk Purchase UOM, misalnya ID, nama, dan conversion value.
3. Identitas terbaik untuk mencari BOM Assembly: `productDetailID`, `productID`, atau `productCode`.
4. Penanda kategori produk untuk membedakan Raw Material, WIP, dan Packaging.
5. Penanda BOM aktif jika API mengembalikan lebih dari satu BOM untuk satu WIP.
6. Apakah komponen BOM sudah memuat Product Detail yang cukup sehingga request Product tambahan bisa dibatasi.

Jika Purchase UOM tidak tersedia pada endpoint Product yang ada, implementasi harus berhenti pada fallback UOM BOM dengan label **Purchase UOM belum tersedia**. Jangan menebak konversi unit.

## 9. Aturan resolusi BOM

### 9.1 BOM Menu

- Gunakan satu `bomID` dari data Menu.
- `bomID = 0` membuat Menu tidak dapat dipilih.
- Jangan membaca `menuPackages` untuk mencari BOM pengganti.
- Simpan response asli sebagai snapshot untuk audit dan debugging.

### 9.2 Komponen langsung

Semua komponen langsung BOM Menu ditampilkan apa adanya dengan:

- kode produk;
- nama produk;
- kategori;
- kuantitas pada BOM;
- UOM BOM;
- penanda Bahan, WIP, atau Packaging.

### 9.3 Assembly/WIP

Untuk setiap komponen WIP:

1. Cari BOM Assembly yang menghasilkan produk tersebut.
2. Pilih hanya BOM aktif yang cocok dengan identitas produk.
3. Ambil detail Assembly.
4. Simpan hubungan parent-child serta jalur penelusurannya.
5. Lanjutkan penelusuran jika Assembly masih mengandung WIP.

Penelusuran berhenti ketika:

- item bukan WIP;
- BOM Assembly tidak ditemukan;
- ditemukan circular reference;
- batas kedalaman aman tercapai.

Batas kedalaman awal: 10 level. Nilai ini menjadi guard teknis dan dapat dipindahkan ke config.

### 9.4 Item Akhir

Versi sederhana menampilkan dua kelompok:

| Kelompok | Isi |
| --- | --- |
| Bahan | Komponen non-WIP yang ditemukan dari BOM Menu atau Assembly |
| WIP | Semua komponen WIP yang dilalui dalam struktur BOM |

Packaging tetap dapat ditampilkan sebagai kategori pada komponen, tetapi belum memerlukan section workflow terpisah.

Item yang sama dapat digabung untuk ringkasan berdasarkan prioritas identitas:

1. `productDetailID`;
2. `productID + Purchase UOM`;
3. `productCode + Purchase UOM`;
4. `productName + Purchase UOM` sebagai fallback terakhir.

Ringkasan harus tetap menyimpan daftar Menu sumber dan jalur BOM sehingga pengguna dapat melihat asal setiap item.

## 10. Model data target

Implementasi harus mengaudit dan sebisa mungkin menggunakan tabel existing sebelum membuat tabel baru.

### 10.1 `rnd_internal_memos`

Kolom aktif yang dibutuhkan:

- `id`;
- `company_code`, selalu `BLSS`;
- `memo_number`, nullable;
- `title`;
- `period_month`;
- `notes`, nullable;
- `created_by` dan `updated_by`;
- timestamps dan soft delete.

Kolom workflow lama boleh tetap berada di database selama masa transisi, tetapi tidak mengendalikan UI sederhana.

### 10.2 `rnd_internal_memo_menus`

Kolom minimum:

- relasi Memo;
- identitas Menu ESB;
- identitas BOM Menu;
- snapshot Menu;
- snapshot BOM;
- waktu sinkronisasi terakhir;
- pesan error/warning teknis terakhir;
- urutan tampilan.

Kolom forecast, shelf life, dan status lama tidak ditampilkan pada UI baru.

### 10.3 Item hasil BOM

Gunakan `rnd_internal_memo_materials` jika dapat menampung:

- relasi Menu;
- parent item;
- sumber BOM dan source path;
- depth;
- identitas Product ESB;
- kategori;
- kuantitas dan UOM BOM;
- Purchase UOM ID dan nama;
- tipe item Bahan/WIP/Packaging;
- snapshot Product;
- Minimum Order;
- waktu Product terakhir disegarkan.

Kolom baru dibuat melalui migration tambahan. Jangan mengubah migration yang sudah pernah dijalankan di production.

### 10.4 Kepemilikan Minimum Order

Default requirement: Minimum Order disimpan per item dalam Memo. Nilai pada satu Memo tidak otomatis mengubah Memo lain.

Jika bisnis kemudian membutuhkan Minimum Order global per produk, fitur master terpisah dapat dibuat dan nilai tersebut hanya menjadi default saat item pertama kali masuk ke Memo.

## 11. Struktur kode target

Struktur mengikuti Code Remediation dan direktori existing:

```text
app/
├── Actions/Rnd/InternalMemo/
│   ├── CreateInternalMemoAction.php
│   ├── AddMenuToInternalMemoAction.php
│   ├── RemoveMenuFromInternalMemoAction.php
│   ├── RefreshInternalMemoMenuAction.php
│   └── UpdateInternalMemoMinimumOrdersAction.php
├── Services/Rnd/InternalMemo/
│   ├── InternalMemoMenuCatalogService.php
│   ├── InternalMemoBomResolver.php
│   ├── InternalMemoProductEnricher.php
│   └── InternalMemoItemConsolidator.php
├── Filament/Helpdesk/Resources/RndInternalMemos/
├── Models/
└── Policies/

tests/
├── Unit/
│   ├── InternalMemoBomResolverTest.php
│   ├── InternalMemoProductEnricherTest.php
│   └── InternalMemoItemConsolidatorTest.php
└── Feature/
    ├── RndInternalMemoWorkflowTest.php
    ├── RndInternalMemoMinimumOrderTest.php
    └── RndInternalMemoPermissionTest.php
```

Tanggung jawab:

- Filament Page mengatur state UI dan memanggil Action.
- Action menangani satu use case serta transaction boundary.
- Service menangani pembacaan dan transformasi data ESB.
- Model menyimpan data dan relasi tanpa melakukan HTTP.
- Blade hanya menampilkan data; tidak query database dan tidak menghitung BOM.

## 12. Rancangan UI

### 12.1 Index

Gunakan pola index Project R&D:

- header ringkas;
- tombol **Buat Memo**;
- pencarian nama/nomor Memo;
- filter bulan;
- tabel atau list ringkas;
- pagination 10 data;
- tanpa kartu status workflow.

Kolom utama:

- Nama Memo;
- Bulan;
- Nomor Memo;
- Jumlah Menu;
- Terakhir diperbarui;
- aksi buka dan hapus.

### 12.2 Workspace detail

Urutan section:

1. **Informasi Memo** — nama, bulan, nomor, catatan, tombol edit.
2. **Menu Terpilih** — daftar Menu dan tombol Tambah Menu.
3. **Struktur BOM per Menu** — BOM Menu, komponen, serta Assembly.
4. **Ringkasan Item Akhir** — tab atau filter Bahan dan WIP.

Kolom Ringkasan Item Akhir:

| Kolom | Isi |
| --- | --- |
| Produk | Kode dan nama produk |
| Jenis | Bahan, WIP, atau Packaging |
| Sumber | Menu dan jalur BOM |
| UOM BOM | Unit pada resep/BOM |
| Purchase UOM | Unit pembelian dari Product API |
| Minimum Order | Input angka lokal |
| Diperbarui | Waktu snapshot Product terakhir |

### 12.3 State UI

| State | Tampilan |
| --- | --- |
| Memuat Menu | Spinner di dalam modal |
| Memuat BOM/Product | Spinner pada card Menu yang sedang diproses |
| Memo kosong | Empty state dengan tombol Tambah Menu |
| BOM tidak ditemukan | Pesan spesifik pada Menu/WIP terkait dan tombol coba ulang |
| Product/Purchase UOM gagal | Data BOM tetap tampil, Purchase UOM diberi status belum tersedia |
| Menyimpan Minimum Order | Tombol disabled dan loading indicator |
| Berhasil | Notification singkat Filament |

UI mengikuti `docs/ui-consistency-prd.md`: border tipis, tanpa shadow berlebihan, layout responsif, modal untuk form singkat, ikon dari metode yang sudah digunakan aplikasi, dan tabel lebar memiliki versi mobile yang tetap dapat digunakan.

## 13. Reliability dan keamanan

- Credential hanya berasal dari config/environment, tidak dari source code atau database plaintext.
- Static token Master Menu dan credential ESB Core BLSS tidak ditampilkan di UI/log.
- Semua HTTP mempunyai connect timeout dan request timeout.
- GET boleh mengikuti retry policy aman milik shared ESB client.
- Cache memakai key yang menyertakan Company Code dan parameter identitas.
- Refresh satu Menu memakai lock agar request ganda tidak menulis snapshot bersamaan.
- Penyimpanan snapshot dan replacement item dilakukan dalam database transaction.
- Data lama baru dihapus setelah data pengganti berhasil diambil dan divalidasi.
- Error log menyertakan Memo ID, Menu ID, endpoint, dan Company Code tanpa credential.
- Semua mutation lokal wajib melewati Policy.

## 14. Test strategy

Seluruh test HTTP memakai fake dan `Http::preventStrayRequests()` atau mekanisme isolasi existing.

### 14.1 Unit test

- Menu dengan `bomID = 0` ditolak.
- Resolver menyimpan komponen langsung BOM Menu.
- Resolver menemukan Assembly untuk WIP.
- Resolver menangani Assembly bertingkat.
- Circular reference dihentikan dan menghasilkan warning.
- BOM Assembly yang tidak ditemukan tidak menghilangkan komponen WIP.
- Product enricher memetakan Purchase UOM.
- Fallback UOM tidak mengarang Purchase UOM.
- Consolidator menggabungkan item dengan identitas dan UOM yang benar.
- Item yang sama tetapi berbeda Purchase UOM tidak digabung.

### 14.2 Feature test

- Permission index, view, create, update, dan delete.
- Membuat Memo tanpa status workflow.
- Menambah Menu dan mencegah duplikasi.
- Menghapus Menu tanpa memengaruhi Menu lain.
- Refresh Menu mempertahankan Minimum Order untuk item yang masih sama.
- Minimum Order dapat dibuat, diperbarui, dikosongkan, dan divalidasi.
- Kegagalan API tidak menghapus snapshot terakhir yang valid.
- Index dan detail tidak melakukan request ESB hanya untuk render snapshot.
- Query count tidak bertambah linear karena N+1 pada daftar Menu/item.

### 14.3 Regression test

- Resource tetap berada pada navigation group R&D.
- Company Code selalu BLSS.
- Tidak ada pilihan Company Code/cabang di UI.
- Tidak ada request mutation menuju ESB.
- UI tidak menampilkan finalisasi, revisi, forecast, shelf life, atau status lama.

## 15. Phase implementasi

### Phase 0 — Audit dan contract freeze — **Selesai (30 September 2026)**

- Audit implementasi Memo existing beserta seluruh consumer.
- Petakan kolom/tabel/action/job lama yang masih dipakai.
- Validasi response nyata API Menu, BOM, Assembly, dan Product.
- Buktikan lokasi field Purchase UOM.
- Rekam characterization test perilaku yang perlu dipertahankan.
- Tentukan data lama yang tetap dibaca pada masa transisi.

**Output:** laporan audit, kontrak response, keputusan migration, dan daftar cleanup aman.

**Hasil:** Implementasi lama (status/finalize/revisi/PDF/sync) ternyata sudah lengkap dan baru dibangun 23 September 2026, bukan sekadar rencana. BOM Menu/Assembly sudah memakai `EsbCoreClient` + credential BLSS (`InternalMemoBomResolver`), dan Master Menu sudah memakai static token BLSS (`InternalMemoMenuCatalogService`) — kedua ketentuan wajib PRD baru sudah otomatis terpenuhi. Kontrak Purchase UOM **tidak terbukti** (field yang ada hanya `unit`/`baseUnit`/`conversionFactor`, tidak ada field "Purchase UOM" sama sekali) — diputuskan pakai fallback "Purchase UOM belum tersedia" sesuai §8.1, bukan menebak. Resolver WIP/Assembly tidak mempunyai batas kedalaman (hanya guard circular-bomID) — ditambahkan di Phase 2.

### Phase 1 — Model data sederhana — **Selesai**

- Tambahkan kolom Purchase UOM, Minimum Order, snapshot Product, dan waktu refresh jika belum ada.
- Sesuaikan model/factory.
- Pertahankan kompatibilitas data existing.
- Tambahkan index database untuk foreign key dan identitas produk.

**Output:** fondasi penyimpanan sederhana tanpa mengubah UI terlebih dahulu.

**Hasil:** Migration additive menambahkan `purchase_uom_id`, `purchase_uom_name`, `minimum_order`, `product_synced_at` pada `rnd_internal_memo_materials`, plus index pada `product_code`. Commit `4cce822`.

### Phase 2 — Pipeline Menu dan BOM — **Selesai**

- Rapikan Add Menu sebagai use case tunggal.
- Pisahkan refresh satu Menu ke Action.
- Resolve BOM Menu dan Assembly melalui service.
- Simpan snapshot secara atomik.
- Pertahankan snapshot valid ketika refresh gagal.

**Output:** satu Menu dapat menghasilkan struktur BOM lengkap dan dapat di-refresh.

**Hasil:** `AddMenuToInternalMemoAction` kini resolve BOM langsung saat Menu ditambahkan (bukan lewat step Sync terpisah); gate status Draft dihapus dari `RndInternalMemoPolicy::update()` ("Menu dapat ditambah dan dihapus kapan saja"). `RefreshInternalMemoMenuAction` baru untuk refresh per-Menu dengan lock + preservasi Minimum Order berdasarkan identitas stabil. **Bug nyata ditemukan dan diperbaiki**: `InternalMemoBomResolver::resolve()` menghapus Material lama SEBELUM fetch BOM baru — kegagalan fetch akan mengosongkan snapshot valid. Diperbaiki dengan fetch-dulu-baru-hapus dibungkus `DB::transaction()`. Depth limit Assembly (10 level) ditambahkan. Commit `3ec0a8b`.

### Phase 3 — Product dan Purchase UOM — **Selesai**

- Buat Product enricher.
- Ambil Product detail hanya untuk identitas unik.
- Simpan Purchase UOM dan raw snapshot.
- Tampilkan fallback yang jelas ketika data tidak tersedia.

**Output:** setiap item mempunyai Purchase UOM yang dapat dibuktikan atau status tidak tersedia.

**Hasil:** `InternalMemoProductEnricher` baru, fetch Product detail sekali per `esb_product_detail_id` unik per Menu, disimpan di kolom `product_detail_snapshot` baru (terpisah dari `product_snapshot` milik resolver BOM). Purchase UOM tetap `null` sesuai temuan Phase 0 — tidak ditebak. Commit `feef955`.

### Phase 4 — Workspace UI sederhana — **Selesai**

- Sederhanakan modal create/edit Memo.
- Hapus kontrol workflow lama dari UI.
- Rapikan modal Menu.
- Buat struktur BOM per Menu.
- Buat Ringkasan Item Akhir Bahan/WIP.
- Tambahkan input Minimum Order.
- Tambahkan loading, empty, error, retry, dan responsive states.

**Output:** alur utama dapat diselesaikan dari satu workspace.

**Hasil:** Form Buat Memo disederhanakan jadi 4 field (Nama/Bulan/Nomor opsional/Catatan). Index tanpa filter status/kartu ringkasan workflow. Workspace detail: tombol Sync/Finalize/Revisi/Arsip/PDF dihapus dari UI; ditambah modal Edit Info Memo (baru) dan Ringkasan Item Akhir Bahan+WIP dengan Minimum Order yang bisa diedit inline (`InternalMemoConsolidationService::consolidateForSummary()` + `UpdateInternalMemoMinimumOrdersAction`, commit `dab936b`). Setiap Menu punya tombol Refresh/"Coba Ambil Ulang" sendiri. Commit UI utama `46f6bcb`.

### Phase 5 — Cleanup workflow lama — **Selesai (scope terbatas, evidence-based)**

- Audit ulang route, policy, enum, action, job, PDF, dan test lama.
- Hapus kode yang terbukti tidak lagi mempunyai consumer.
- Jangan menghapus kolom production dalam migration yang sama dengan perubahan UI.
- Buat cleanup schema sebagai pekerjaan terpisah setelah satu periode stabil.

**Output:** codebase lebih kecil tanpa mengorbankan rollback atau data lama.

**Hasil:** Hanya `SynchronizeInternalMemoJob` dan `GenerateInternalMemoPdfJob` yang dihapus — keduanya terbukti nol consumer (tidak ada lagi `::dispatch()` call site setelah UI Phase 4 dilepas). Action/Policy/Enum/permission/route PDF lain **sengaja tidak dihapus** karena masih punya consumer nyata (test Phase 4 yang memanggil Action secara langsung, Policy test, route download PDF untuk dokumen lama). Commit `c19dc9f`. Cleanup lebih dalam (hapus kolom/status/permission) didokumentasikan sebagai pekerjaan terpisah, bukan bagian pass ini.

### Phase 6 — Hardening dan release — **Selesai**

- Jalankan test terfokus per scope.
- Jalankan Pint pada PHP yang berubah.
- Jalankan seluruh test dengan memory 512 MB.
- Review query count, log context, timeout, cache, dan authorization.
- Dokumentasikan deployment, rollback, serta konfigurasi yang dibutuhkan.

**Output:** fitur siap dipull dan dimigrasikan dengan langkah deployment yang jelas.

**Hasil:** Test query-count baru (`RndInternalMemoQueryPerformanceTest.php`, commit `27ea84a`) membuktikan tidak ada N+1 pada index maupun workspace. Audit otorisasi: semua method mutating di kedua Page sudah ber-`abort_unless`. Route/Filament discovery dicek bersih. Lihat laporan implementasi terpisah untuk hasil full suite, langkah deployment, dan rollback.

## 16. Quality gates

Setiap phase wajib memenuhi:

1. Tidak ada HTTP mentah dari Filament Page atau Blade.
2. Tidak ada network eksternal pada test.
3. Tidak ada perubahan production requirement demi mempertahankan test stale.
4. Public contract service yang masih dipakai consumer lain tetap kompatibel atau dimigrasikan dalam commit yang sama.
5. Test terfokus lulus sebelum full suite.
6. `vendor/bin/pint --dirty --format agent` lulus untuk perubahan PHP.
7. Full suite dijalankan dengan `php -d memory_limit=512M artisan test --compact`.
8. File untracked atau perubahan lain di working tree tidak ikut commit.
9. Setiap commit hanya memuat satu scope kecil yang dapat direview dan di-rollback.

## 17. Acceptance criteria

Fitur dianggap selesai jika:

- pengguna dapat membuat Memo sederhana;
- Company Code terkunci ke BLSS tanpa pilihan di UI;
- pengguna dapat mencari dan memilih Menu dari API Master Menu;
- Menu tanpa BOM tidak dapat dipilih;
- setiap Menu memakai satu `bomID` miliknya;
- detail BOM Menu serta komponen langsung tampil;
- Assembly/WIP dapat ditelusuri dan ditampilkan;
- hasil akhir dipisahkan menjadi Bahan dan WIP;
- setiap item menampilkan UOM BOM dan Purchase UOM;
- Minimum Order dapat disimpan per item;
- Minimum Order tetap ada setelah refresh jika identitas item masih sama;
- Menu dapat ditambah dan dihapus kapan saja;
- UI tidak menampilkan workflow status, finalisasi, revisi, forecast, atau shelf life;
- kegagalan ESB tidak menghapus snapshot valid terakhir;
- permission dan policy diterapkan pada setiap tindakan;
- semua test terkait dan full suite lulus tanpa akses network eksternal.

## 18. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Field Purchase UOM belum pasti | Validasi kontrak pada Phase 0; tampilkan tidak tersedia tanpa menebak |
| Lebih dari satu BOM ditemukan untuk WIP | Gunakan identity matching dan hanya BOM aktif; catat warning jika ambigu |
| Circular BOM | Simpan visited BOM IDs dan hentikan pada jalur yang berulang |
| ESB lambat atau gagal | Snapshot lokal, timeout, retry aman untuk GET, serta refresh manual |
| Refresh menghapus Minimum Order | Rekonsiliasi item berdasarkan stable identity sebelum mengganti snapshot |
| Cleanup memutus fitur lama | Cleanup setelah audit consumer dan dikerjakan terpisah |
| Tabel menjadi terlalu lebar di mobile | Gunakan susunan card/list per item pada viewport kecil sesuai UI PRD |

## 19. Deployment dan rollback

Urutan deployment:

1. Pull commit phase terkait.
2. Pastikan environment BLSS untuk Master Menu dan ESB Core tersedia.
3. Jalankan `php artisan migrate --force` jika phase memiliki migration.
4. Jalankan `php artisan optimize:clear`.
5. Jalankan queue worker restart jika ada perubahan job.
6. Smoke test index, create Memo, pilih Menu, refresh BOM, dan simpan Minimum Order.

Rollback aplikasi dilakukan per commit. Migration penambahan kolom bersifat backward compatible dan tidak perlu langsung dihapus ketika rollback kode. Penghapusan kolom lama hanya dilakukan melalui release cleanup terpisah setelah backup database tersedia.

## 20. Prompt implementasi seluruh phase

```text
Implementasikan seluruh phase pada docs/rnd-internal-memo-simplification-prd.md secara berurutan.

Baca dan ikuti:
- AGENTS.md
- docs/rnd-internal-memo-simplification-prd.md
- docs/rnd-internal-memo-prd.md sebagai referensi implementasi lama saja
- docs/code-remediation-prd.md
- docs/esb-integration-consolidation-roadmap.md
- docs/ui-consistency-prd.md

Audit repository dan working tree terlebih dahulu. Pertahankan perubahan existing yang tidak berhubungan dan jangan memasukkannya ke commit.

Ketentuan wajib:
- fitur hanya memakai Company Code BLSS;
- Menu hanya berasal dari API ESB Master Menu;
- satu Menu hanya memakai satu bomID;
- Menu dengan bomID = 0 tidak dapat dipilih;
- menuPackages tidak ditelusuri;
- BOM dan Product ESB bersifat read-only;
- tampilkan BOM Menu, komponennya, jalur Assembly/WIP, Item Akhir Bahan dan WIP, Purchase UOM, serta Minimum Order lokal;
- Menu dapat ditambah dan dihapus kapan saja;
- jangan tampilkan status workflow, finalisasi, revisi, forecast, shelf life, atau PDF pada UI sederhana;
- jangan melakukan HTTP mentah dari Filament Page, Blade, model, atau action;
- jangan menebak Purchase UOM jika kontrak API belum terbukti;
- refresh gagal tidak boleh menghapus snapshot valid atau Minimum Order;
- seluruh test harus terisolasi dari network eksternal;
- jangan mengubah requirement production hanya untuk mempertahankan test stale.

Kerjakan phase secara berurutan. Setelah setiap phase:
1. jalankan test terfokus;
2. jalankan quality gate yang relevan;
3. laporkan file dan perilaku yang berubah;
4. laporkan penyimpangan atau kontrak API yang belum terbukti;
5. buat commit kecil khusus phase tersebut jika seluruh test lulus.

Setelah seluruh phase:
1. jalankan vendor/bin/pint --dirty --format agent;
2. jalankan php -d memory_limit=512M artisan test --compact;
3. laporkan seluruh hasil test dan klasifikasi kegagalan jika ada;
4. perbarui status implementasi pada PRD;
5. berikan langkah deployment dan rollback;
6. jangan push kecuali diminta.
```
