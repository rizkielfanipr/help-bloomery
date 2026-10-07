# PRD — Memo Internal R&D Berbasis Brand dengan Sumber Data BLSS

## 1. Identitas dokumen

| Field | Nilai |
| --- | --- |
| Baseline | 6 Oktober 2026 |
| Status | Rancangan untuk review; belum mengotorisasi migrasi atau deployment production |
| Modul | Research & Development |
| Menu | `R&D → Memo Internal` |
| Perubahan utama | Mengganti pilihan `Branch Tujuan` menjadi satu pilihan `Brand` |
| Sumber data operasional | Company Code `BLSS` secara tetap |
| Acuan remediation | `docs/code-remediation-prd.md` |
| Acuan UI | `docs/ui-consistency-prd.md` |
| Acuan fitur existing | `docs/rnd-internal-memo-simplification-prd.md` |
| Dokumen yang dikoreksi | `docs/rnd-internal-memo-multi-branch-prd.md` |

## 2. Kedudukan dan prioritas dokumen

Dokumen ini menjadi sumber requirement terbaru untuk perubahan scope Brand pada Memo Internal.

Ketentuan berikut berlaku:

1. Ketentuan penyederhanaan Memo Internal tetap berlaku selama tidak bertentangan dengan dokumen ini.
2. Ketentuan multi-branch mengenai `Branch Tujuan`, company berdasarkan Branch, pembatasan akses Branch, katalog per Branch, serta pivot Menu–Branch digantikan oleh dokumen ini.
3. Ketentuan teknis umum wajib mengikuti `code-remediation-prd.md`.
4. Ketentuan tampilan, responsive behavior, state, accessibility, dan konsistensi komponen wajib mengikuti `ui-consistency-prd.md`.
5. Dokumen ini tidak mengizinkan penghapusan data legacy langsung pada rollout pertama.

## 3. Ringkasan eksekutif

Pada Memo Internal existing, pengguna memilih satu atau beberapa Branch Tujuan. Pilihan tersebut tidak hanya tampil di UI, tetapi juga memengaruhi validasi akses, company/branch context ESB, sinkronisasi katalog Menu, filter picker, dan relasi pivot.

Kebutuhan baru menyederhanakan alur tersebut:

- `Branch Tujuan` dihapus dari pengalaman pengguna Memo Internal.
- Pengguna memilih tepat satu `Brand` dari Master Brand.
- Brand berfungsi sebagai identitas bisnis dan metadata Memo.
- Brand tidak menentukan Company Code, Branch Code, credential, katalog Menu, BOM, Product, atau hasil integrasi lainnya.
- Semua data operasional Memo Internal tetap memakai Company Code `BLSS` yang ditentukan server-side.
- Karena endpoint Master Menu existing masih membutuhkan `branchCode`, sistem memakai satu technical branch context yang dikonfigurasi khusus. Nilai ini tidak ditampilkan atau dipilih oleh pengguna dan tidak berasal dari Brand.
- Data Memo existing dimigrasikan secara aman. Sistem tidak boleh menebak Brand apabila data Branch lama tidak menghasilkan satu Brand yang deterministik.

Hasil akhirnya adalah Memo Internal yang lebih sederhana: pengguna memilih Brand, mengisi informasi Memo, lalu mengelola Menu dan Minimum Order dari satu katalog BLSS yang konsisten.

## 4. Latar belakang dan masalah

### 4.1 Kondisi existing

Implementasi existing memiliki karakteristik berikut:

- Form create Memo meminta multi-select `Branch Tujuan`.
- Action create memvalidasi akses pengguna ke setiap Branch.
- Memo menyimpan snapshot Branch dan context ESB pada `rnd_internal_memo_branches`.
- Katalog Menu disinkronkan per kombinasi company dan Branch.
- Picker Menu menyediakan filter Branch dan company.
- Menu yang ditambahkan memiliki keterkaitan dengan Branch melalui pivot.
- Perubahan Branch dapat merekonsiliasi atau menghapus keterkaitan Menu.
- Policy Memo mempertimbangkan akses pengguna ke Branch terkait.
- Company Code Memo saat ini juga memiliki konstanta `BLSS`, tetapi implementasi multi-branch masih membawa context company/branch di berbagai lapisan.

### 4.2 Masalah produk

Untuk proses bisnis terbaru, Branch tidak lagi diperlukan sebagai target Memo. Pengguna sebenarnya membutuhkan pengelompokan Memo berdasarkan Brand, sedangkan data Menu dan BOM tetap berasal dari BLSS.

Jika perubahan hanya dilakukan dengan mengganti label `Branch` menjadi `Brand`, sistem akan tetap memiliki risiko berikut:

- Brand keliru diperlakukan sebagai sumber context ESB.
- Data katalog berubah mengikuti Branch di balik layar.
- Policy tetap menolak pengguna berdasarkan akses Branch.
- Relasi legacy masih ditulis dan menjadi sumber konflik.
- Brand berbeda tidak dapat memiliki Memo pada periode sama karena unique constraint lama.
- Penggantian Brand berpotensi menghapus Menu atau memicu sinkronisasi yang sebenarnya tidak diperlukan.

### 4.3 Masalah teknis

- Struktur existing terdistribusi pada page Livewire/Filament, action, query service, job, policy, model, migration, Blade, PDF, dan test.
- Sinkronisasi katalog menyimpan status per Memo–Branch, padahal target baru hanya membutuhkan satu state sinkronisasi global untuk sumber BLSS.
- Endpoint Master Menu diketahui mengirim parameter `branchCode`; BLSS adalah Company Code, bukan Branch Code.
- Tabel legacy tidak boleh langsung dihapus sebelum migrasi data, verifikasi, dan satu periode kompatibilitas selesai.

## 5. Sasaran produk

1. Mengganti input Branch Tujuan menjadi satu input Brand pada create dan edit Memo Internal.
2. Menjadikan Brand sebagai metadata bisnis, bukan context integrasi.
3. Menjamin seluruh data Menu, BOM, Assembly/WIP, Product, dan Purchase UOM menggunakan Company Code `BLSS`.
4. Menghilangkan ketergantungan aktif Memo Internal pada akses Branch.
5. Menyederhanakan picker Menu dengan menghapus filter dan informasi Branch/company yang tidak lagi relevan bagi pengguna.
6. Menjaga histori melalui snapshot nama Brand.
7. Memigrasikan data lama tanpa kehilangan Memo, Menu, Minimum Order, atau histori.
8. Menjaga sinkronisasi berat tetap asynchronous dan halaman tetap menggunakan snapshot lokal.
9. Menyediakan implementasi bertahap yang aman, teruji, observable, dan dapat di-rollback.

## 6. Non-goals

Perubahan ini tidak mencakup:

- Membuat Master Brand baru atau mengubah struktur pengelolaan Master Brand secara umum.
- Membuat akses pengguna berbasis Brand.
- Menentukan Company Code atau Branch Code berdasarkan Brand.
- Mengirim mutation apa pun ke ESB.
- Menambah approval flow, finalisasi baru, atau status workflow baru.
- Mengubah perhitungan Minimum Order existing.
- Mengubah struktur BOM, Assembly/WIP, Product, Purchase UOM, atau shelf life di luar penyesuaian context BLSS.
- Menambah multi-brand dalam satu Memo.
- Menghapus tabel legacy pada deployment yang sama dengan aktivasi fitur Brand.
- Menambah dependency/package baru tanpa persetujuan terpisah.

## 7. Terminologi

| Istilah | Definisi |
| --- | --- |
| Brand | Master `brands` yang dipilih sebagai identitas bisnis Memo |
| BLSS | Company Code tetap untuk seluruh sumber data operasional Memo Internal |
| Technical branch context | Branch Code konfigurasi server yang hanya digunakan bila endpoint Master Menu mensyaratkannya |
| Brand snapshot | Salinan nama Brand saat disimpan agar histori tidak berubah karena rename/delete Master Brand |
| Legacy Branch | Data Branch dan pivot Branch dari implementasi multi-branch sebelum fitur ini |
| Katalog global | Snapshot Master Menu yang digunakan bersama seluruh Memo karena sumber datanya sama, yaitu BLSS |
| Unresolved legacy Memo | Memo lama yang Brand-nya tidak dapat ditentukan secara aman dari data Branch lama |

## 8. Keputusan bisnis yang dikunci

### 8.1 Kardinalitas Brand

- Satu Memo wajib memiliki tepat satu Brand.
- Satu Brand dapat memiliki banyak Memo.
- Satu Memo tidak dapat memiliki lebih dari satu Brand.

### 8.2 Peran Brand

Brand hanya digunakan untuk:

- identitas dan konteks bisnis Memo;
- pencarian dan filter Memo;
- tampilan index, detail, dan PDF;
- pemisahan unique identity Memo per periode;
- histori melalui snapshot.

Brand tidak digunakan untuk:

- memilih Company Code;
- memilih Branch Code;
- memilih token atau credential;
- membatasi katalog Menu;
- memengaruhi isi BOM atau Product;
- menentukan hak akses pengguna;
- memicu sinkronisasi katalog saat Brand berubah.

### 8.3 Sumber data

- Company Code selalu `BLSS`.
- Nilai Company Code ditetapkan server-side.
- Client tidak boleh mengirim atau mengganti Company Code.
- Apabila payload client tetap mengirim company code selama masa transisi, server harus mengabaikan atau menolak nilai selain `BLSS` secara eksplisit.
- Brand yang dipilih tidak boleh memengaruhi hasil data.

### 8.4 Periode dan revisi

- Satu Brand dapat mempunyai satu Memo aktif untuk kombinasi periode dan revisi yang sama.
- Brand berbeda boleh mempunyai Memo aktif pada bulan dan nomor revisi yang sama.
- Soft-deleted Memo tidak menghalangi pembuatan Memo baru sesuai pola constraint existing.
- Nomor Memo mengikuti behavior existing kecuali hasil Phase 0 membuktikan perlunya penambahan identitas Brand agar tetap unik dan mudah dibaca.

### 8.5 Perubahan Brand

- Brand dapat diedit selama pengguna memiliki permission update Memo.
- Mengubah Brand hanya memperbarui `brand_id` dan `brand_name_snapshot`.
- Mengubah Brand tidak menghapus Menu, item, Minimum Order, attachment, atau snapshot BOM.
- Mengubah Brand tidak menjalankan sinkronisasi ESB.
- Perubahan Brand wajib tercatat dalam audit log existing bila mekanisme audit tersedia.

## 9. Persona dan hak akses

### 9.1 Pengguna utama

- Tim R&D yang membuat dan mengelola Memo Internal.
- Pengguna internal yang hanya melihat Memo sesuai permission.
- Administrator yang mengelola Master Brand dan memantau sinkronisasi.

### 9.2 Prinsip authorization

- Authorization menggunakan permission/policy Memo Internal existing.
- Tidak ada pemeriksaan `canAccessBranch()` untuk view, create, update, delete, add Menu, remove Menu, sync, atau export Memo.
- Tidak diperkenalkan relasi akses User–Brand dalam scope ini.
- Keberadaan Brand pada Master Brand tidak otomatis memberi permission Memo; permission modul tetap menjadi pengaman utama.
- Semua mutation divalidasi ulang server-side meskipun kontrol UI telah disembunyikan.

## 10. User stories

### 10.1 Membuat Memo

Sebagai pengguna berizin, saya ingin memilih satu Brand ketika membuat Memo agar Memo dapat dikelompokkan sesuai Brand tanpa harus memahami Branch atau context ESB.

### 10.2 Mengedit Brand

Sebagai pengguna berizin, saya ingin mengganti Brand Memo dengan proses sederhana agar koreksi metadata tidak mengubah Menu dan detail data yang sudah disusun.

### 10.3 Menambahkan Menu

Sebagai pengguna berizin, saya ingin memilih Menu dari katalog BLSS tanpa memilih Branch agar alur penambahan Menu lebih cepat dan konsisten.

### 10.4 Melihat histori

Sebagai pembaca Memo, saya ingin nama Brand pada Memo lama tetap terlihat walaupun Master Brand diubah atau dihapus agar histori dokumen tetap dapat dipahami.

### 10.5 Memigrasikan Memo lama

Sebagai administrator, saya ingin melihat Memo mana yang dapat dipetakan otomatis dan mana yang perlu dipilih manual agar migrasi tidak mengarang data Brand.

## 11. Alur pengguna target

### 11.1 Create Memo

1. Pengguna membuka `R&D → Memo Internal`.
2. Pengguna menekan `Buat Memo`.
3. Sistem menampilkan satu select `Brand` yang searchable dan required.
4. Pengguna mengisi periode dan field Memo existing lainnya.
5. Sistem memvalidasi permission, Brand, periode, dan unique identity.
6. Sistem menyimpan Memo dengan Company Code `BLSS`, `brand_id`, dan `brand_name_snapshot`.
7. Sistem tidak membuat relasi Memo–Branch.
8. Sistem mengarahkan pengguna ke detail Memo.

### 11.2 Edit Brand

1. Pengguna membuka detail Memo dan memilih aksi edit.
2. Sistem menampilkan Brand existing sebagai single select.
3. Pengguna memilih Brand baru dan menyimpan.
4. Sistem memperbarui metadata Brand saja.
5. Menu, BOM, item, Minimum Order, attachment, dan data lain tetap utuh.
6. Sistem menampilkan notifikasi berhasil yang spesifik.

### 11.3 Menambah Menu

1. Pengguna membuka picker Menu.
2. Sistem membaca katalog lokal BLSS.
3. Pengguna mencari atau memfilter berdasarkan atribut Menu yang relevan, bukan Branch/company.
4. Sistem menambahkan Menu dengan `company_code = BLSS`.
5. Sistem mengambil atau menggunakan snapshot BOM sesuai behavior existing.
6. Tidak ada pivot Menu–Branch baru yang ditulis.

### 11.4 Sinkronisasi katalog

1. Pengguna berizin atau scheduler memicu sinkronisasi.
2. Job menggunakan Company Code `BLSS` dan technical branch context dari konfigurasi.
3. Job memperbarui katalog lokal secara idempotent.
4. Status sinkronisasi global ditampilkan tanpa referensi Brand atau Branch bisnis.
5. Kegagalan sinkronisasi tidak menghapus snapshot terakhir yang masih valid.

## 12. Requirement fungsional

### 12.1 Form create

- Hapus field `Branch Tujuan`.
- Tambahkan field `Brand` berupa single select.
- Opsi berasal dari Master Brand lokal.
- Field searchable, preload bila jumlah opsi masih wajar, required, dan memiliki label yang jelas.
- Urutan opsi alfabetis berdasarkan nama.
- Brand yang sudah dihapus tidak dapat dipilih untuk Memo baru.
- Tidak ada input Company Code atau Branch Code.
- Error validasi tampil pada field Brand.

### 12.2 Form edit

- Edit Brand menggunakan komponen dan sumber opsi yang sama dengan create.
- Form tetap sederhana; tidak ada wizard atau konfigurasi mapping tambahan.
- Sistem tidak meminta konfirmasi destructive karena perubahan Brand bukan destructive.
- Jika Brand snapshot legacy belum terpetakan, field menampilkan state yang jelas dan meminta pengguna memilih Brand valid sebelum menyimpan.

### 12.3 Index Memo

- Tampilkan Brand sebagai informasi utama pada kartu/baris Memo.
- Hapus daftar Branch dari kartu/baris.
- Tambahkan filter Brand bila index existing mendukung filter.
- Pencarian dapat menemukan Memo berdasarkan nama Brand snapshot.
- Tidak tampil filter company atau Branch.
- Memo legacy unresolved menampilkan badge `Brand belum ditentukan`, bukan nilai tebakan.

### 12.4 Detail Memo

- Tampilkan Brand pada ringkasan Memo.
- Gunakan `brand_name_snapshot` sebagai histori utama dengan fallback relasi live hanya bila snapshot kosong pada data transisi.
- Hapus kartu, badge, daftar, dan aksi terkait Branch.
- Hapus filter Menu berdasarkan Branch/company.
- Tampilkan keterangan singkat `Sumber data: ESB BLSS` pada area yang relevan tanpa mengekspos credential atau technical branch context.

### 12.5 Picker Menu

- Katalog yang dibaca selalu katalog BLSS.
- Hapus input/filter Branch dan company.
- Tampilkan atribut yang berguna: kode, nama, kategori, status BOM, dan aksi pilih.
- Jangan tampilkan daftar ketersediaan per Branch.
- Menu yang sudah ditambahkan harus memiliki disabled state atau penanda yang jelas.
- Pencarian, pagination, empty state, error state, dan retry mengikuti pola UI existing.
- Picker tidak melakukan fetch seluruh katalog secara synchronous saat render.

### 12.6 Menu dan detail item

- Semua Menu baru disimpan dengan `company_code = BLSS`.
- Semua request BOM/Product untuk Menu baru menggunakan context BLSS.
- Brand tidak diteruskan sebagai parameter integrasi.
- Penambahan Menu tidak membutuhkan `memoBranchIds`.
- Penghapusan Menu tidak membutuhkan rekonsiliasi Branch.
- Minimum Order dan behavior edit item tetap seperti existing.

### 12.7 PDF/export

- PDF menampilkan nama Brand snapshot.
- PDF tidak menampilkan Branch Tujuan.
- PDF tidak bergantung pada relasi Brand live agar dokumen lama tetap stabil.
- Data Menu, item, Minimum Order, dan format lain tetap mengikuti behavior existing kecuali penyesuaian label.

### 12.8 Revisi Memo

- Pembuatan revisi menyalin `brand_id` dan `brand_name_snapshot` dari Memo sumber.
- Semua Menu hasil salinan tetap memiliki Company Code `BLSS` untuk data baru.
- Revisi tidak membuat relasi Memo–Branch baru.
- Unique validation mempertimbangkan Brand, periode, revisi, dan soft delete.

## 13. Model data target

### 13.1 Perubahan `rnd_internal_memos`

Tambahkan:

| Kolom | Tipe konseptual | Aturan |
| --- | --- | --- |
| `brand_id` | nullable foreign key ke `brands` | `nullOnDelete`; nullable untuk masa migrasi, required di application untuk create/edit baru |
| `brand_name_snapshot` | nullable string | Diisi dari nama Brand saat create/edit; menjadi histori tampilan |

Ketentuan:

- `brand_id` dibuat nullable di level database pada rollout awal agar deploy tidak gagal pada data legacy.
- Setelah seluruh legacy Memo terselesaikan dan diaudit, kewajiban database-level `NOT NULL` dapat dipertimbangkan dalam perubahan terpisah.
- Panjang snapshot mengikuti batas nama Brand atau batas aman yang konsisten dengan schema.
- Model memiliki relasi `brand(): BelongsTo` dan fillable/cast sesuai konvensi project.

### 13.2 Unique constraint

Constraint active Memo existing yang hanya mempertimbangkan company/periode/revisi harus diganti agar Brand berbeda dapat memiliki Memo pada periode sama.

Target logical uniqueness:

```text
brand_id + period_month_if_active + revision
```

Ketentuan:

- Soft delete tetap tidak memblokir pembuatan Memo baru.
- Nama index harus eksplisit dan berada dalam batas panjang database.
- Migration harus memeriksa konflik data sebelum menambahkan unique index baru.
- DDL dan backfill DML dipisahkan agar deployment dapat dipantau dan diulang dengan aman.
- Memo legacy dengan `brand_id = null` tidak boleh dipakai untuk mengakali validasi create baru; application tetap mewajibkan Brand.

### 13.3 Company Code

- Kolom `company_code` existing tetap dipertahankan untuk kompatibilitas dan integritas snapshot.
- Default dan nilai untuk write baru selalu `BLSS`.
- Application menggunakan satu constant/domain source yang jelas, misalnya `RndInternalMemo::COMPANY_CODE`.
- Jangan menggandakan literal `BLSS` di banyak action/service bila constant existing dapat dipakai.

### 13.4 Tabel legacy Branch

Tabel berikut dipertahankan sementara pada rollout pertama:

- `rnd_internal_memo_branches`;
- `rnd_internal_memo_menu_branches`.

Aturan compatibility window:

- Tidak ada write baru ke tabel tersebut setelah fitur Brand aktif.
- Data lama tidak langsung dihapus.
- Relasi dapat tetap tersedia hanya untuk proses audit/backfill sementara.
- UI dan business flow baru tidak membaca tabel tersebut sebagai sumber kebenaran.
- Penghapusan tabel/kolom dilakukan melalui PRD atau change request cleanup terpisah setelah telemetry dan audit menyatakan aman.

### 13.5 Status sinkronisasi katalog

Karena katalog bersumber tunggal dari BLSS, status sinkronisasi tidak lagi tepat bila disimpan per Memo–Branch.

Target minimal adalah satu state global per context teknis, misalnya:

| Kolom | Keterangan |
| --- | --- |
| `company_code` | Selalu `BLSS` untuk scope ini |
| `technical_branch_code` | Nilai konfigurasi yang dipakai endpoint Master Menu |
| `status` | `idle`, `queued`, `running`, `success`, atau `failed` |
| `last_synced_at` | Waktu sinkronisasi sukses terakhir |
| `last_error` | Pesan aman untuk diagnosis, tanpa credential |
| `triggered_by` | User nullable bila dipicu scheduler |

Nama tabel dan implementasi final mengikuti hasil audit schema dan konvensi project. Jangan membuat tabel baru apabila state global yang setara sudah tersedia dan dapat dipakai ulang dengan aman.

## 14. Aturan integrasi BLSS

### 14.1 Fixed company context

- Semua adapter/service Memo Internal menerima atau menetapkan Company Code `BLSS` dari server.
- Company Code dari request UI tidak dipercaya.
- Credential ESB Core dipilih berdasarkan konfigurasi Company Code BLSS existing.
- Kegagalan menemukan credential BLSS menghasilkan error terkontrol dan observable; tidak fallback ke company lain.

### 14.2 Technical branch context Master Menu

Endpoint Master Menu existing masih perlu diverifikasi karena implementasi saat ini mengirim `branchCode`.

Keputusan desain:

- Sediakan konfigurasi khusus, misalnya `services.esb.internal_memo_catalog_branch_code`.
- Nilai berasal dari environment deployment, misalnya `RND_INTERNAL_MEMO_CATALOG_BRANCH_CODE`.
- Jangan memanggil `env()` di luar file config.
- Jangan mengambil nilai ini dari Brand atau Branch yang dipilih pengguna.
- Jangan menampilkan nilai ini pada form Memo.
- Jangan mengasumsikan `BLSS` sebagai Branch Code karena BLSS adalah Company Code.

Sebelum implementasi diaktifkan, Phase 0 wajib membuktikan salah satu kondisi berikut:

1. endpoint mendukung permintaan tanpa Branch Code; atau
2. satu Branch Code canonical menghasilkan katalog yang disepakati sebagai sumber BLSS; atau
3. seluruh Branch Code BLSS yang relevan menghasilkan katalog identik dan satu nilai dapat dipilih sebagai technical context.

Jika tidak satu pun terbukti, implementasi tidak boleh menebak. Tim harus menetapkan kontrak sumber katalog terlebih dahulu.

### 14.3 Katalog lokal

- Reuse tabel katalog existing bila schema mendukung satu context BLSS.
- Jangan membuat salinan katalog per Brand.
- Unique key katalog tetap didasarkan pada identitas data ESB, bukan Brand.
- `branch_code` existing boleh dipertahankan sementara sebagai technical context, tetapi tidak menjadi domain business Memo.
- Query picker hanya mengembalikan katalog aktif/valid sesuai aturan existing.

### 14.4 Job sinkronisasi

- Job menerima context teknis yang telah divalidasi atau mengambilnya dari config.
- Job tidak menerima Brand sebagai penentu sumber data.
- Job idempotent dan aman di-retry.
- Gunakan locking/unique job sesuai pola existing agar sinkronisasi context sama tidak berjalan bersamaan.
- Update katalog menggunakan upsert/chunking sesuai volume.
- Snapshot sukses terakhir tidak dihapus saat request terbaru gagal.
- Log menyertakan company, technical context, jumlah hasil, durasi, status, dan correlation identifier tanpa token.

## 15. Perubahan komponen backend

### 15.1 Create action

Create action target harus:

- menerima `brand_id`, bukan `branch_ids`;
- memvalidasi Brand tersedia;
- menetapkan `company_code = BLSS` server-side;
- menyimpan `brand_name_snapshot` dalam transaksi yang sama;
- tidak memanggil resolver mapping Branch;
- tidak menulis `rnd_internal_memo_branches`;
- tidak mendispatch sinkronisasi per Branch;
- mengembalikan validation error terstruktur mengikuti pola existing.

### 15.2 Update Brand action

Gunakan action/service yang sempit dan mudah diuji:

- authorize update;
- validasi Brand;
- update `brand_id` dan snapshot;
- tidak menyentuh Menu/pivot/item;
- tidak memicu integrasi;
- mencatat audit bila tersedia.

Action `UpdateInternalMemoBranchesAction` tidak digunakan lagi oleh UI baru. Penghapusan class dilakukan hanya setelah seluruh call site dan test legacy telah diganti atau dinyatakan tidak diperlukan.

### 15.3 Add Menu action

Add Menu target harus:

- tidak menerima atau memvalidasi `memoBranchIds`;
- tidak mempercayai company dari picker;
- menetapkan `company_code = BLSS`;
- memvalidasi katalog/menu berasal dari context BLSS yang aktif;
- tidak menulis pivot Menu–Branch;
- menjaga idempotensi dan unique behavior existing;
- tetap menjalankan workflow BOM/Product yang relevan.

### 15.4 Query katalog

`InternalMemoMenuCatalogQuery` atau penggantinya harus:

- menggunakan fixed BLSS context;
- tidak membangun context dari relasi Memo–Branch;
- tidak memfilter berdasarkan Brand;
- tidak menyediakan filter Branch/company;
- tetap mendukung search, sort, pagination, dan exclude Menu yang sudah dipilih;
- menghindari N+1 query.

### 15.5 Page/resource

Page list/detail harus:

- mengganti public state `branchIds` dengan `brandId` bila masih diperlukan;
- menghapus `menuBranchFilter` dan `menuCompanyFilter`;
- menghapus pemanggilan sync per Memo Branch;
- eager-load Brand yang diperlukan;
- menjaga action tetap tipis dengan business rule berada pada action/service.

### 15.6 Policy

- `viewAny`, `view`, `create`, `update`, `delete`, dan action khusus mengikuti permission Memo existing.
- Hapus syarat akses terhadap setidaknya satu Memo Branch.
- Brand tidak menambah rule akses baru.
- Query scoping tidak boleh membocorkan Memo hanya karena relasi Branch legacy masih ada.

## 16. Migrasi dan backfill data

### 16.1 Prinsip migrasi

- Tidak ada guessing.
- Tidak ada penghapusan data legacy pada fase aktivasi.
- Backfill idempotent dan dapat dijalankan ulang.
- Backfill menggunakan chunk berdasarkan primary key.
- Tersedia mode dry-run dan apply.
- Hasil menyediakan ringkasan count dan daftar ID unresolved.
- Perubahan DDL dipisahkan dari DML/backfill.

### 16.2 Aturan pemetaan otomatis

Untuk setiap Memo legacy:

1. Ambil seluruh Branch legacy terkait.
2. Ambil `brand_id` non-null dari Branch tersebut.
3. Normalisasi menjadi daftar Brand unik.
4. Jika tepat satu Brand unik ditemukan, isi `brand_id` dan snapshot dari Brand tersebut.
5. Jika tidak ada Brand, tandai unresolved.
6. Jika terdapat lebih dari satu Brand, tandai unresolved.
7. Jangan memilih Brand pertama, Brand mayoritas, atau Brand berdasarkan urutan ID.

### 16.3 Memo tanpa relasi Branch

- Jika Memo sudah memiliki Brand dari proses lain, verifikasi dan isi snapshot bila kosong.
- Jika tidak memiliki Brand, tandai unresolved.
- Data Memo tetap dapat dibaca sesuai compatibility mode, tetapi mutation yang memerlukan Brand harus meminta resolusi terlebih dahulu.

### 16.4 Data non-BLSS historis

- Memo/Menu historis dengan company selain BLSS tidak boleh diam-diam diubah menjadi BLSS.
- Jangan menjalankan refresh BLSS ke record historis tersebut sebelum keputusan bisnis terpisah.
- Tampilkan sebagai legacy data bila masih harus dibaca.
- Semua write baru setelah cutover wajib BLSS.

### 16.5 Resolusi manual

Admin membutuhkan mekanisme operasional sederhana, dapat berupa command terkontrol atau UI existing yang diperluas, untuk:

- melihat ID/nomor Memo unresolved;
- memilih satu Brand valid;
- menyimpan snapshot;
- mencatat siapa dan kapan resolusi dilakukan.

UI admin baru bukan kewajiban bila command aman sudah mencukupi untuk volume data, tetapi prosedurnya wajib terdokumentasi pada runbook deployment.

### 16.6 Command backfill

Command mengikuti konvensi Laravel dan minimal menyediakan:

- default dry-run;
- flag eksplisit untuk apply;
- chunk size yang dapat dikontrol;
- exit code non-zero pada error fatal;
- summary `scanned`, `resolved`, `already_resolved`, `unresolved_no_brand`, `unresolved_multiple_brands`, dan `failed`;
- tanpa output credential atau payload sensitif.

## 17. Strategi kompatibilitas dan cleanup

### 17.1 Compatibility release

Pada minimal satu release:

- schema Brand sudah tersedia;
- flow baru hanya menulis Brand;
- data Branch legacy tetap tersimpan;
- pembacaan UI mengutamakan Brand snapshot;
- telemetry memantau fallback legacy dan Memo unresolved;
- tidak ada destructive cleanup.

### 17.2 Kriteria cleanup terpisah

Tabel/pivot Branch baru boleh dihapus setelah:

- seluruh Memo aktif memiliki Brand valid;
- tidak ada call site production yang membaca/menulis relasi Branch;
- tidak ada queue job lama yang masih membawa payload Branch;
- PDF/export dan policy tidak bergantung pada Branch;
- backup database terverifikasi;
- periode observasi selesai tanpa kebutuhan rollback ke flow multi-branch;
- migration cleanup mempunyai rollback realistis atau keputusan irreversible terdokumentasi dan disetujui.

## 18. UI/UX requirement

### 18.1 Konsistensi

- Reuse komponen, modal, typography, spacing, button, badge, dan empty state existing.
- Jangan membuat design language baru khusus Memo Internal.
- Label memakai bahasa yang konsisten: `Brand`, bukan `Brand Tujuan` bila tidak ada kebutuhan semantik tambahan.
- Copy harus menjelaskan bahwa Brand adalah identitas Memo, sedangkan data bersumber dari BLSS bila konteks diperlukan.

### 18.2 Responsive behavior

- Form dapat digunakan pada mobile tanpa horizontal scroll.
- Select Brand, action footer, picker, tabel/kartu Menu, dan modal mengikuti breakpoint pada UI PRD.
- Informasi penting tidak hanya disampaikan melalui hover.
- Tabel lebar menggunakan pola responsive existing, bukan memampatkan teks hingga tidak terbaca.

### 18.3 Accessibility

- Semua input memiliki label yang terasosiasi.
- Dialog memiliki title, focus management, escape behavior, dan return focus yang benar.
- Icon-only action memiliki accessible name.
- Loading dan validation state dapat dipahami tanpa hanya mengandalkan warna.
- Kontras, focus ring, keyboard navigation, dan reduced motion mengikuti UI PRD.

### 18.4 State wajib

Setiap permukaan relevan menangani:

- initial loading;
- refreshing/syncing;
- empty catalog;
- no search result;
- validation error;
- recoverable integration error;
- stale snapshot dengan timestamp;
- permission denied;
- unresolved legacy Brand.

### 18.5 Feedback edit

- Tombol save disabled/loading selama request aktif untuk mencegah double submit.
- Notifikasi berhasil menyebut `Brand Memo berhasil diperbarui` atau copy setara.
- Error tidak menutup modal dan input pengguna tidak hilang.
- Perubahan Brand tidak menampilkan progress sinkronisasi karena tidak ada sync yang dijalankan.

## 19. Security dan integritas

- Semua action sensitif memanggil authorization server-side.
- `brand_id` divalidasi dengan existence rule dan batasan yang sesuai soft-delete behavior Master Brand.
- Company Code tidak diambil dari state Livewire yang dapat dimanipulasi.
- Technical Branch Code tidak menerima input user.
- Token/credential tidak pernah ditampilkan di UI, log, notification, atau exception message.
- Query memakai Eloquent/query builder dan binding.
- Mass assignment dibatasi secara eksplisit.
- Race condition create Memo ditahan oleh database unique constraint, bukan hanya validation query.
- Sinkronisasi menggunakan timeout, retry, backoff, dan locking sesuai pola integrasi existing.

## 20. Performance dan reliability

- Index eager-load Brand dan count yang diperlukan.
- Filter Brand menggunakan indexed foreign key.
- Pencarian katalog dilakukan server-side dan dipaginasi.
- Tidak ada request ESB pada render normal detail/index.
- Sinkronisasi penuh dijalankan melalui queue.
- Query Menu tidak melakukan per-row lookup Branch/Brand.
- Backfill menggunakan `chunkById` atau mekanisme setara.
- Job retry tidak membuat duplikat katalog atau relasi.
- Kegagalan integrasi mempertahankan last-known-good snapshot.
- Target performa mengikuti baseline aplikasi existing dan harus diukur sebelum/sesudah pada dataset representatif.

## 21. Observability

Minimal metric/log yang diperlukan:

- jumlah Memo baru per Brand;
- jumlah Memo unresolved;
- jumlah write baru dengan company selain BLSS, yang targetnya selalu nol;
- jumlah sync queued/running/success/failed;
- durasi dan jumlah record sinkronisasi;
- umur snapshot katalog terakhir;
- error rate picker/add Menu/BOM;
- jumlah fallback yang masih membaca relasi Branch legacy;
- duplicate-key failure pada create Memo.

Log wajib memakai identifier aman seperti Memo ID, company code, status, dan correlation ID. Payload besar, token, credential, dan data sensitif tidak dicatat.

## 22. Error handling

| Kondisi | Perilaku |
| --- | --- |
| Brand tidak dipilih | Validation error pada field Brand |
| Brand tidak ditemukan/tidak valid | Tolak mutation; tampilkan pesan aman |
| Duplicate Brand–periode–revisi | Tolak dengan pesan bahwa Memo Brand pada periode/revisi tersebut sudah ada |
| Config technical Branch Code kosong | Sync gagal secara terkontrol; katalog lama tetap tersedia |
| Credential BLSS tidak tersedia | Job/action gagal terkontrol dan tercatat |
| ESB timeout | Retry/backoff; tampilkan snapshot terakhir bila tersedia |
| Katalog kosong | Empty state dengan opsi refresh sesuai permission |
| Memo legacy unresolved | Tampil badge; minta pemilihan Brand sebelum edit tertentu |
| Brand master dihapus | Tampilkan snapshot; `brand_id` boleh null karena `nullOnDelete` |
| Edit Brand gagal | Tidak ada Menu/item yang berubah |

## 23. Strategi pengujian

Semua perubahan implementasi wajib mempunyai test otomatis menggunakan Pest dan HTTP fake untuk integrasi eksternal.

### 23.1 Characterization test sebelum refactor

- Create Memo existing dan field wajibnya.
- Add/remove Menu dan Minimum Order.
- Revisi, delete, PDF, dan permission.
- Bentuk payload Master Menu/BOM/Product existing.
- Unique behavior active Memo dan soft delete.

### 23.2 Migration/model test

- Kolom dan foreign key Brand tersedia.
- `nullOnDelete` bekerja.
- Relasi Brand bekerja.
- Snapshot tidak berubah ketika nama Brand master berubah.
- Unique active Memo berlaku per Brand–periode–revisi.
- Brand berbeda dapat memiliki Memo pada periode/revisi sama.
- Soft-deleted Memo tidak memblokir create baru.

### 23.3 Create/update action test

- Create membutuhkan Brand.
- Create menyimpan Brand dan snapshot.
- Create memaksa Company Code BLSS.
- Payload company non-BLSS ditolak/diabaikan sesuai kontrak final.
- Create tidak menulis Memo Branch.
- Update Brand hanya mengubah metadata Brand.
- Update Brand tidak mengubah jumlah Menu/item/pivot existing.
- Update Brand tidak mendispatch sync job.
- Unauthorized user ditolak.

### 23.4 Backfill test

- Satu Brand deterministik dipetakan otomatis.
- Beberapa Branch dengan Brand sama tetap menghasilkan satu Brand.
- Tidak ada Brand menjadi unresolved.
- Lebih dari satu Brand menjadi unresolved.
- Dry-run tidak mengubah database.
- Apply idempotent saat dijalankan ulang.
- Memo yang sudah memiliki Brand tidak rusak.
- Data non-BLSS historis tidak dinormalisasi diam-diam.

### 23.5 Catalog/job integration-contract test

- Request memakai Company Code BLSS.
- Request memakai technical Branch Code dari config.
- Brand tidak masuk request/payload.
- Missing config menghasilkan failure terkontrol.
- Retry/upsert tidak menduplikasi katalog.
- Failed sync mempertahankan snapshot sukses sebelumnya.
- Lock mencegah job context sama berjalan bersamaan.

### 23.6 Add Menu test

- Add Menu tidak membutuhkan Branch.
- Menu selalu tersimpan dengan Company Code BLSS.
- Pivot Menu–Branch baru tidak dibuat.
- Duplicate Menu ditangani sesuai rule existing.
- BOM/Product memakai context BLSS.
- Menu milik Memo tetap utuh setelah Brand diubah.

### 23.7 Livewire/Filament/UI test

- Form create menampilkan Brand dan tidak menampilkan Branch Tujuan.
- Form edit memuat Brand existing.
- Index/detail menampilkan Brand snapshot.
- Filter Brand bekerja bila disediakan.
- Filter Branch/company tidak tersedia.
- Picker tidak menampilkan Branch availability.
- Validation, loading, empty, error, stale, dan unresolved state ter-render.
- Permission mengatur visibilitas dan server action.

### 23.8 PDF test

- PDF menampilkan Brand snapshot.
- PDF tidak menampilkan Branch Tujuan.
- PDF tetap dapat dibuat ketika Brand master telah dihapus tetapi snapshot tersedia.

### 23.9 Regression test

- Minimum Order tidak berubah.
- Revision flow tetap bekerja.
- Delete/restore behavior sesuai existing.
- Attachment dan data lain tidak hilang.
- Tidak ada network call nyata pada test suite.

## 24. Rencana implementasi bertahap

### Phase 0 — Discovery dan contract verification

1. Petakan semua referensi `branch_ids`, relasi Memo Branch, pivot Menu Branch, filter Branch/company, policy Branch, dan job payload.
2. Jalankan characterization test pada flow kritis.
3. Verifikasi kontrak endpoint Master Menu untuk parameter Branch Code.
4. Tentukan technical Branch Code canonical melalui bukti respons, bukan asumsi.
5. Audit data existing: distribusi Brand per Memo, Memo tanpa Branch, multi-brand, company non-BLSS, dan collision calon unique key.
6. Catat baseline query count dan waktu render/sync.

Exit criteria:

- Contract BLSS/technical Branch Code terbukti.
- Daftar call site lengkap.
- Konflik data diketahui.
- Rollout dapat dilanjutkan tanpa menebak data.

### Phase 1 — Additive schema

1. Tambahkan `brand_id` nullable dan `brand_name_snapshot`.
2. Tambahkan index pendukung.
3. Siapkan model relation/factory.
4. Siapkan state sinkronisasi global bila belum ada fasilitas setara.
5. Jangan hapus constraint/tabel legacy sebelum audit collision selesai.

Exit criteria:

- Migration up/down teruji.
- Versi aplikasi lama masih dapat berjalan selama deployment bertahap bila dibutuhkan.

### Phase 2 — Backfill tooling

1. Buat command dry-run/apply.
2. Jalankan dry-run pada salinan data production.
3. Selesaikan Memo unresolved melalui keputusan manual.
4. Verifikasi snapshot dan jumlah data.
5. Simpan laporan hasil deployment.

Exit criteria:

- Seluruh Memo aktif yang akan dimutasi memiliki Brand valid.
- Tidak ada mapping ambigu yang dipaksakan.

### Phase 3 — Domain write path Brand + BLSS

1. Refactor create/update/revision action.
2. Tetapkan Company Code BLSS server-side.
3. Refactor policy dari Branch scope menjadi permission-only.
4. Hentikan write baru ke tabel Memo Branch.
5. Terapkan unique constraint per Brand secara aman.

Exit criteria:

- Semua test domain dan authorization lulus.
- Write baru tidak menciptakan relasi Branch.

### Phase 4 — Katalog global dan Add Menu

1. Refactor query katalog ke fixed BLSS context.
2. Refactor job sync dan status global.
3. Hilangkan dependency Branch pada Add Menu/BOM/Product.
4. Tambahkan locking, retry, observability, dan last-known-good behavior.

Exit criteria:

- Contract test membuktikan Brand tidak memengaruhi request.
- Picker dan Add Menu berfungsi tanpa Branch.

### Phase 5 — UI dan PDF

1. Ganti form Branch menjadi Brand single-select.
2. Perbarui index/detail/filter/picker.
3. Hapus copy dan state Branch/company yang tidak relevan.
4. Perbarui PDF dengan Brand snapshot.
5. Verifikasi mobile, dark mode, keyboard, loading, empty, error, dan unresolved state.

Exit criteria:

- Tidak ada Branch Tujuan pada flow aktif.
- UI sesuai acuan konsistensi.

### Phase 6 — Compatibility release dan observasi

1. Deploy additive schema lebih dahulu bila strategi deployment membutuhkannya.
2. Jalankan backfill terkontrol.
3. Aktifkan flow Brand.
4. Pantau unresolved, fallback legacy, sync, queue, error, dan data non-BLSS.
5. Pastikan worker lama selesai sebelum menghapus dukungan payload lama.

Exit criteria:

- Tidak ada write Branch baru.
- Tidak ada regression kritis selama periode observasi.

### Phase 7 — Cleanup terpisah

1. Hapus class, relation, test, dan UI legacy yang tidak terpakai.
2. Hapus tabel/pivot Branch hanya setelah approval dan backup.
3. Hapus kolom teknis lama hanya jika tidak dipakai integrasi lain.
4. Perbarui dokumentasi arsitektur final.

Phase ini tidak termasuk deployment awal fitur dan membutuhkan review tersendiri.

## 25. Deployment plan

Urutan aman yang direkomendasikan:

1. Backup database dan verifikasi restore procedure.
2. Deploy migration additive.
3. Deploy kode yang kompatibel dengan schema lama dan baru bila diperlukan.
4. Jalankan backfill dry-run.
5. Review unresolved dan collision.
6. Jalankan apply setelah hasil disetujui.
7. Terapkan constraint baru setelah data bersih.
8. Deploy/aktifkan flow Brand dan BLSS.
9. Restart worker secara terkendali agar tidak ada job lama tertahan.
10. Jalankan smoke test create, edit Brand, add Menu, BOM, PDF, dan permission.
11. Pantau metric dan log.

Tidak ada `migrate:fresh`, truncate, atau penghapusan tabel pada production.

## 26. Rollback plan

### 26.1 Rollback aplikasi

- Feature dapat dinonaktifkan atau aplikasi dikembalikan ke build kompatibel selama schema additive masih dipertahankan.
- Jangan hapus data Brand yang sudah ditulis.
- Queue payload lama dan baru harus diperhitungkan sebelum restart/rollback worker.

### 26.2 Batas rollback schema

Setelah Brand berbeda diperbolehkan memiliki Memo pada periode/revisi sama, mengembalikan unique index global lama mungkin gagal karena data baru sudah valid menurut rule baru tetapi konflik menurut rule lama.

Karena itu:

- rollback utama adalah rollback aplikasi sambil mempertahankan schema/data additive; atau
- lakukan forward-fix;
- jangan otomatis memasang kembali unique index lama tanpa audit collision;
- destructive rollback memerlukan backup, prosedur terpisah, dan persetujuan eksplisit.

## 27. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Brand dianggap sumber ESB | Data katalog berbeda/keliru | Kunci fixed BLSS server-side dan contract test |
| Technical Branch Code salah | Katalog tidak lengkap | Phase 0 verification dan config eksplisit |
| Memo lama mencakup beberapa Brand | Backfill ambigu | Tandai unresolved; resolusi manual tanpa guessing |
| Constraint baru gagal | Deployment tertahan | Audit collision sebelum DDL dan pisahkan migration |
| Job lama masih menulis Branch | Data legacy bertambah | Drain/restart worker, version payload, telemetry write |
| Edit Brand menghapus Menu | Kehilangan data | Action metadata-only dan regression test |
| Brand dihapus dari master | Histori hilang | `brand_name_snapshot` + `nullOnDelete` |
| Cleanup terlalu cepat | Rollback sulit | Compatibility release dan cleanup terpisah |
| Data non-BLSS lama tertimpa | Histori rusak | Exclude dari normalisasi otomatis |
| Policy Branch tersisa | User valid ditolak | Audit seluruh policy/query scope dan test permission |

## 28. Acceptance criteria

Fitur dinyatakan memenuhi kebutuhan apabila seluruh kondisi berikut terpenuhi:

1. Create Memo menampilkan satu field Brand dan tidak menampilkan Branch Tujuan.
2. Brand wajib dipilih dan tersimpan sebagai relation serta snapshot.
3. Edit Brand sederhana dan tidak mengubah Menu, item, Minimum Order, attachment, atau BOM snapshot.
4. Semua write Memo/Menu baru menggunakan Company Code BLSS yang ditetapkan server.
5. Brand tidak memengaruhi request katalog, BOM, Product, atau credential.
6. Picker tidak memiliki filter Branch/company atau informasi availability per Branch.
7. Create/Add Menu tidak menulis tabel pivot Branch legacy.
8. View/update Memo tidak bergantung pada akses Branch.
9. Brand berbeda dapat mempunyai Memo pada periode/revisi yang sama.
10. Brand yang sama tidak dapat mempunyai dua Memo aktif pada periode/revisi yang sama.
11. Soft-deleted Memo tidak menghalangi create baru sesuai rule.
12. PDF menampilkan Brand snapshot dan tidak menampilkan Branch Tujuan.
13. Memo legacy deterministik ter-backfill dengan benar.
14. Memo legacy ambigu tidak ditebak dan terlihat sebagai unresolved.
15. Sinkronisasi katalog berjalan asynchronous, idempotent, dan mempertahankan snapshot terakhir saat gagal.
16. Technical Branch Code berasal dari config dan tidak dapat diubah pengguna.
17. Tidak ada credential atau token di UI/log.
18. Test relevan lulus dan tidak ada network eksternal pada test.
19. UI lolos pemeriksaan responsive, dark mode, keyboard, loading, empty, error, dan accessibility dasar.
20. Tidak ada tabel legacy yang dihapus pada rollout pertama.

## 29. Definition of Done

- Requirement dan keputusan terbuka Phase 0 telah diselesaikan.
- Migration additive, backfill, constraint, dan rollback telah diuji pada database representatif.
- Seluruh action/query/job/policy tidak lagi bergantung pada Branch untuk flow aktif.
- Semua data baru menggunakan BLSS secara server-side.
- Test suite minimum yang relevan lulus.
- PHP yang berubah telah diformat dengan Pint sesuai aturan project.
- Tidak ada N+1 baru pada index/detail/picker.
- Build frontend berhasil bila aset berubah.
- Smoke test production-like berhasil.
- Dashboard/log memungkinkan pemantauan sync dan unresolved Memo.
- Runbook deployment, backfill, verifikasi, dan rollback tersedia sebelum production rollout.
- Cleanup legacy belum dilakukan kecuali melalui approval terpisah.

## 30. Keputusan terbuka yang wajib ditutup pada Phase 0

1. Technical Branch Code mana yang sah untuk katalog Master Menu BLSS, atau apakah endpoint dapat dipanggil tanpa Branch Code?
2. Berapa jumlah Memo legacy yang tidak memiliki Brand atau memiliki lebih dari satu Brand?
3. Apakah ada Memo/Menu historis dengan company selain BLSS yang masih aktif digunakan?
4. Apakah format nomor Memo existing perlu memasukkan kode Brand, atau uniqueness database saja sudah cukup?
5. Berapa lama compatibility window sebelum cleanup tabel Branch?
6. Apakah resolusi manual legacy cukup melalui command atau memerlukan UI admin berdasarkan volume data?

Pertanyaan tersebut tidak mengubah keputusan utama bahwa pilihan pengguna adalah Brand dan seluruh sumber data baru tetap BLSS. Pertanyaan hanya menentukan detail teknis rollout yang aman.
