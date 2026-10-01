# PRD — Memo Internal R&D Multi-Branch

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 1 Oktober 2026 |
| Status | Siap untuk review; belum mengotorisasi deployment |
| Modul | Research & Development |
| Menu | `R&D → Memo Internal` |
| Tujuan | Membuat satu Memo Internal untuk satu atau beberapa branch dengan sumber Company Code dan Branch Code dari Master Branch |
| Acuan fitur existing | `docs/rnd-internal-memo-simplification-prd.md` |
| Acuan teknis | `docs/code-remediation-prd.md` dan `docs/esb-integration-consolidation-roadmap.md` |
| Acuan UI | `docs/ui-consistency-prd.md` dan halaman `R&D → Project` |

Dokumen ini mengubah batasan Memo Internal yang sebelumnya hanya memakai Company Code `BLSS` menjadi Memo berbasis Master Branch. Ketentuan penyederhanaan Memo yang tidak bertentangan dengan dokumen ini tetap berlaku. Jika terdapat konflik mengenai branch, Company Code, katalog Menu, atau credential BOM, dokumen ini menjadi acuan utama.

## 2. Ringkasan kebutuhan

Pengguna dapat membuat Memo Internal untuk satu atau beberapa branch. Pengguna hanya memilih nama branch dari Master Branch lokal. Sistem menentukan Company Code, Branch Code, ESB Branch ID, static token Master Menu, dan credential ESB Core secara otomatis dari mapping aktif branch tersebut.

Alur utama:

1. Pengguna membuat Memo.
2. Pengguna memilih satu atau beberapa Branch Tujuan.
3. Sistem membaca mapping ESB utama setiap branch.
4. Sistem mengambil katalog Menu sesuai kombinasi Company Code dan Branch Code yang terpilih.
5. Menu yang sama pada beberapa branch digabung menjadi satu pilihan dan tetap menyimpan daftar branch asal.
6. Pengguna menambahkan atau melepas Menu kapan saja.
7. Sistem mengambil BOM Menu, BOM Assembly/WIP, detail Product, Purchase UOM, dan menyimpan snapshot lokal berdasarkan Company Code asal Menu.
8. Pengguna mengisi Minimum Order setiap item seperti fitur existing.

## 3. Tujuan bisnis

- Satu Memo dapat digunakan untuk kebutuhan beberapa outlet atau unit operasional.
- Pengguna tidak perlu memahami atau mengetik Company Code dan Branch Code ESB.
- Kesalahan pemilihan credential ESB berkurang karena semua context berasal dari Master Branch.
- Menu dan BOM tetap dapat dilihat ketika ESB sedang lambat melalui snapshot lokal terakhir.
- Memo lama tetap dapat dibuka setelah fitur multi-branch diterapkan.

## 4. Prinsip produk

1. **Master Branch adalah sumber tunggal context ESB.** Company Code dan Branch Code tidak diinput manual di Memo.
2. **Branch dapat dipilih lebih dari satu.** Minimal satu branch wajib dipilih.
3. **Akses branch divalidasi server-side.** Pilihan UI bukan satu-satunya perlindungan.
4. **Static token hanya untuk Master Menu.** BOM dan Product ESB Core memakai access token dari credential Company Code terkait.
5. **Tidak ada mutation ke ESB.** Integrasi Memo hanya membaca data.
6. **Snapshot lokal menjaga stabilitas.** Halaman tidak mengambil seluruh katalog atau BOM saat render.
7. **Proses berat berjalan di background.** Request Livewire tidak boleh menunggu seluruh branch dan seluruh halaman ESB.
8. **Kegagalan parsial diperbolehkan.** Kegagalan satu branch tidak menghapus hasil branch lain.
9. **Satu Menu satu BOM.** `bomID = 0` tidak dapat dipilih dan `menuPackages` tidak ditelusuri.
10. **Tidak ada context BLSS yang di-hardcode dalam domain.** BLSS boleh menjadi hasil mapping branch, bukan asumsi global fitur.

## 5. Scope

### 5.1 Termasuk

- Multi-select Branch Tujuan pada create dan edit Memo.
- Daftar branch sesuai akses pengguna.
- Pembacaan mapping ESB aktif dari Master Branch.
- Validasi Company Code dan Branch Code.
- Snapshot branch dan mapping ESB pada Memo.
- Katalog Menu per kombinasi Company Code dan Branch Code.
- Penggabungan Menu duplikat lintas branch.
- Informasi branch tempat Menu tersedia.
- Sinkronisasi katalog Menu secara background dan cache/snapshot lokal.
- BOM Menu, Assembly/WIP, Product, dan Purchase UOM berdasarkan Company Code asal.
- Penambahan dan penghapusan branch pada Memo.
- Rekonsiliasi Menu ketika cakupan branch berubah.
- Migrasi/fallback data Memo existing.
- Permission, Policy, branch scope, loading, progress, empty state, dan error per branch.
- Characterization, feature, integration-contract, job, policy, dan Livewire test tanpa network eksternal.

### 5.2 Tidak termasuk

- Input Company Code atau Branch Code secara manual pada form Memo.
- Mengubah mapping ESB dari halaman Memo.
- Mengirim data Memo, BOM, Minimum Order, atau attachment ke ESB.
- Approval, finalisasi, revisi bernomor, dan status workflow baru.
- Forecast Quantity, Sales Projection, Shelf Life, PDF, atau Excel baru.
- Mengubah struktur R&D Project atau Product Release.
- Menganggap seluruh branch mempunyai Company Code yang sama.

## 6. Aktor, permission, dan branch scope

Permission existing tetap digunakan:

| Permission | Kegunaan |
| --- | --- |
| `view any rnd internal memos` | Melihat menu dan index Memo |
| `view rnd internal memos` | Membuka Memo yang dapat diakses |
| `create rnd internal memos` | Membuat Memo untuk branch yang dapat diakses |
| `update rnd internal memos` | Mengubah informasi, branch, Menu, refresh, dan Minimum Order |
| `delete rnd internal memos` | Menghapus Memo sesuai perilaku existing |

Aturan akses:

- Pengguna `access_all_branches = true` dapat memilih seluruh Master Branch yang mapping Memo-nya valid.
- Pengguna biasa hanya dapat memilih branch dalam aksesnya.
- Pengguna hanya dapat membuka Memo jika mempunyai akses ke minimal satu branch Memo, kecuali role administratif existing memang mempunyai pengecualian.
- Aksi yang memengaruhi branch di luar akses pengguna harus ditolak server-side melalui Policy/Action.
- Jangan menambahkan permission baru jika tindakan sudah tercakup `update rnd internal memos`, kecuali audit menunjukkan kebutuhan pemisahan tanggung jawab.

## 7. Alur pengguna

### 7.1 Membuat Memo

Pengguna menekan **Buat Memo** dan mengisi modal:

| Field | Aturan |
| --- | --- |
| Nama Memo | Wajib, maksimal 150 karakter |
| Bulan Memo | Wajib, format bulan dan tahun |
| Nomor Memo | Mengikuti mekanisme generate/manual existing |
| Branch Tujuan | Wajib, multi-select, minimal satu branch |
| Catatan | Opsional, maksimal 1.000 karakter |

Pilihan Branch Tujuan menampilkan:

```text
Bloomery Pabelan
BLSS · BLS
```

Company Code dan Branch Code adalah informasi read-only. Pengguna tidak dapat mengubahnya dari form Memo.

Setelah disimpan:

1. Action memvalidasi akses seluruh branch.
2. Action mengunci mapping ESB utama setiap branch.
3. Sistem menyimpan relasi dan snapshot mapping.
4. Pengguna diarahkan ke detail Memo.
5. Job sinkronisasi katalog dijadwalkan untuk context baru bila snapshot belum tersedia atau sudah kedaluwarsa.

### 7.2 Kondisi branch yang tidak dapat dipilih

Branch tampil disabled atau tidak masuk pilihan ketika:

- tidak mempunyai mapping ESB aktif;
- Company Code kosong;
- Branch Code kosong;
- mapping utama belum dapat ditentukan;
- pengguna tidak mempunyai akses;
- static token Company Code belum dikonfigurasi.

Pesan harus spesifik, misalnya **Mapping ESB belum lengkap** atau **Static token BLO15 belum tersedia**. Credential rahasia tidak boleh ditampilkan.

### 7.3 Memilih Menu

Modal **Pilih Menu ESB** membaca snapshot katalog dari seluruh Branch Tujuan.

Filter:

- Branch;
- Company Code;
- Menu Code;
- Menu Name.

Kolom minimum:

| Kolom | Isi |
| --- | --- |
| Menu | Nama dan kode Menu |
| Company | Company Code sumber |
| Tersedia di Branch | Satu atau beberapa nama branch |
| BOM | Tersedia atau Belum Memiliki BOM |
| Aksi | Pilih; disabled jika `bomID = 0` |

Menu digabung dengan identitas:

```text
company_code + menuID
```

Fallback jika `menuID` tidak tersedia:

```text
company_code + normalized menuCode
```

Menu dengan kode/nama sama tetapi Company Code berbeda tidak otomatis dianggap sama karena BOM dan credential dapat berbeda.

### 7.4 Menambah Menu

Ketika Menu dipilih:

1. Simpan snapshot Menu dan daftar branch asal.
2. Simpan Company Code sumber.
3. Ambil detail BOM Menu memakai ESB Core Company Code yang sama.
4. Telusuri Assembly/WIP memakai context Company Code yang sama.
5. Ambil detail Product dan Purchase UOM memakai context Company Code yang sama.
6. Simpan snapshot hasil lokal.
7. Pertahankan Minimum Order existing jika identitas item tetap sama.

### 7.5 Mengubah Branch Tujuan

Menambahkan branch:

- menyimpan snapshot mapping baru;
- menjadwalkan sinkronisasi katalog;
- tidak otomatis menambahkan seluruh Menu branch baru.

Menghapus branch:

- wajib menggunakan konfirmasi yang menjelaskan dampak;
- relasi branch dilepas dari Memo;
- Menu yang masih tersedia pada branch lain tetap tersimpan;
- Menu yang hanya berasal dari branch yang dilepas ditandai untuk konfirmasi pengguna sebelum dihapus;
- penghapusan tidak boleh langsung menghapus data Menu lain dalam transaksi yang tidak dapat ditinjau.

## 8. Sumber data Master Branch

Satu Master Branch dapat mempunyai beberapa mapping ESB. Memo membutuhkan tepat satu mapping utama per branch.

Urutan resolusi mapping:

1. Mapping aktif yang secara eksplisit ditandai sebagai sumber Memo Internal, jika field tersebut tersedia.
2. Mapping aktif yang ditandai sebagai mapping utama bersama sesuai mekanisme Stock Card existing, jika bisnis menyatakan mapping itu boleh dipakai bersama.
3. Jika terdapat tepat satu mapping aktif dan lengkap, gunakan mapping tersebut.
4. Jika terdapat lebih dari satu mapping aktif tanpa penanda utama, branch tidak dapat dipilih sampai mapping utama ditentukan.

Jangan memilih mapping pertama berdasarkan urutan database.

Snapshot yang disimpan:

- `branch_id`;
- `branch_esb_code_id`;
- nama branch;
- Company Code;
- Branch Code;
- ESB Branch ID bila ada;
- waktu mapping disalin.

Perubahan Master Branch setelah Memo dibuat tidak boleh diam-diam mengubah snapshot Memo. Pengguna harus menjalankan aksi **Perbarui Mapping Branch** dengan preview dampak.

## 9. Model data target

### 9.1 `rnd_internal_memo_branches`

```text
id
rnd_internal_memo_id
branch_id
branch_esb_code_id
branch_name_snapshot
company_code_snapshot
branch_code_snapshot
esb_branch_id_snapshot nullable
catalog_sync_status
catalog_synced_at nullable
catalog_sync_error nullable
created_at
updated_at
```

Constraint:

```text
unique(rnd_internal_memo_id, branch_esb_code_id)
index(rnd_internal_memo_id, catalog_sync_status)
```

### 9.2 Relasi Menu dan branch

Tambahkan `company_code` pada snapshot Menu jika belum tersedia. Buat pivot:

```text
rnd_internal_memo_menu_branches
id
rnd_internal_memo_menu_id
rnd_internal_memo_branch_id
created_at
updated_at
```

Constraint:

```text
unique(rnd_internal_memo_menu_id, rnd_internal_memo_branch_id)
```

### 9.3 Snapshot katalog

Implementasi boleh memakai tabel katalog bersama atau cache yang tahan kegagalan. Jika dibuat tabel lokal:

```text
rnd_internal_memo_menu_catalogs
id
company_code
branch_code
menu_id
menu_code
menu_name
bom_id
bom_name nullable
category_detail nullable
flag_active
raw_snapshot json
synced_at
created_at
updated_at
```

Identitas unik minimum:

```text
unique(company_code, branch_code, menu_id)
```

Schema final wajib mengikuti audit data existing dan migration harus aman dijalankan ketika tabel sudah berisi data.

## 10. Integrasi ESB

| Kebutuhan | Endpoint | Autentikasi | Context |
| --- | --- | --- | --- |
| Katalog Menu | `GET /corev1/master/get-menu` | Static token berdasarkan Company Code | Branch Code snapshot |
| Detail BOM | ESB Core `/product/bom/{bomID}` | Access token Company Code | Company Code Menu |
| Browse Assembly | ESB Core `/product/bom` | Access token Company Code | Company Code Menu |
| Detail Product | Endpoint Product ESB Core existing | Access token Company Code | Company Code Menu |

Aturan:

- Filament Page dan Blade tidak melakukan HTTP mentah.
- Master Menu memakai integration service khusus katalog.
- ESB Core memakai `EsbCoreClient`.
- Credential berasal dari konfigurasi environment; tidak disimpan di database.
- Read request boleh retry terbatas hanya untuk connection error/5xx yang aman.
- Timeout dan error harus membawa context endpoint, Company Code, dan Branch Code tanpa token/password.
- Seluruh network pada test wajib menggunakan `Http::fake()` atau fake client.

## 11. Sinkronisasi, cache, dan performa

Jangan mengambil seluruh halaman seluruh branch dalam satu request Livewire.

Flow:

```text
Pilih Branch
→ simpan Memo
→ dispatch satu job per Company Code + Branch Code
→ job mengambil semua halaman Master Menu
→ simpan snapshot atomik
→ UI polling status ringan
→ modal membaca snapshot lokal
```

Job harus mempunyai:

- unique lock per Company Code dan Branch Code;
- timeout, tries, dan backoff eksplisit;
- pagination guard;
- deduplication;
- penyimpanan `last successful snapshot`;
- `failed()` yang mencatat pesan aman;
- tidak menghapus snapshot lama ketika refresh gagal.

Cache key harus memuat Company Code, Branch Code, page/filter atau versi snapshot. Jangan menyimpan katalog multi-company dalam key global tanpa context.

## 12. UI dan responsivitas

Mengikuti `docs/ui-consistency-prd.md`:

- modal create/edit sama dengan pola Project R&D;
- container berborder tipis, radius konsisten, tanpa shadow dekoratif;
- Branch Tujuan memakai searchable multi-select;
- pilihan terpilih tampil ringkas dan dapat wrap;
- status sinkronisasi ditampilkan per branch;
- modal Menu memakai server-side/local snapshot pagination 10–20 baris;
- loading spinner hanya menutup area data;
- error satu branch ditampilkan sebagai baris/status branch, bukan menutup seluruh modal;
- mobile tidak menyebabkan keseluruhan halaman scroll horizontal;
- label menggunakan kapital setiap kata;
- ikon memakai metode Heroicons existing.

## 13. Arsitektur target

```text
Filament Page
    → validasi presentasi + Policy
    → Create/UpdateInternalMemoAction
        → ResolveMemoBranchMappingsAction
        → transaction database
        → dispatch SyncInternalMemoMenuCatalogJob

Modal Menu
    → InternalMemoMenuCatalogQuery
        → snapshot lokal + pagination/filter

SyncInternalMemoMenuCatalogJob
    → InternalMemoMenuCatalogService
        → static token sesuai Company Code
        → Master Menu per Branch Code

AddMenuToInternalMemoAction
    → InternalMemoBomResolver
        → EsbCoreClient sesuai Company Code Menu
```

Page tidak boleh mengoordinasikan pagination API lintas branch atau melakukan loop HTTP.

## 14. Migrasi data existing

1. Audit seluruh Memo existing dan nilai `company_code`/snapshot Menu.
2. Untuk Memo existing BLSS, cari mapping Master Branch hanya jika hubungan branch dapat dibuktikan.
3. Jangan menebak branch hanya dari urutan API atau mapping pertama.
4. Jika branch tidak dapat dibuktikan, tandai Memo sebagai **Perlu Menentukan Branch** dan tetap dapat dibuka read-only untuk data lama.
5. Pengguna berizin dapat memilih Branch Tujuan melalui aksi migrasi dengan preview.
6. Data Menu, BOM, Minimum Order, dan snapshot lama tidak dihapus.
7. Migration harus dapat dijalankan ulang secara aman pada kondisi deployment parsial.

## 15. Error handling

| Kondisi | Perilaku |
| --- | --- |
| Mapping branch tidak lengkap | Branch tidak dapat dipilih; tampilkan alasan |
| Static token Company Code tidak tersedia | Sinkronisasi branch gagal tanpa membocorkan token |
| Credential ESB Core tidak tersedia | Katalog tetap dapat dipilih; resolusi BOM menampilkan error spesifik |
| Satu branch timeout | Branch lain tetap tersedia; snapshot lama dipertahankan |
| Menu tanpa BOM | Ditampilkan disabled |
| Menu sama lintas branch | Digabung dan seluruh branch asal ditampilkan |
| Mapping Master Branch berubah | Snapshot Memo tidak berubah otomatis |
| Job terduplikasi | Unique lock mencegah pekerjaan ganda |
| Livewire request | Tidak menunggu sinkronisasi katalog penuh |

## 16. Test minimum

### Domain dan Action

- membuat Memo dengan satu branch;
- membuat Memo dengan beberapa branch;
- menolak branch tanpa akses;
- menolak mapping tidak lengkap atau ambigu;
- snapshot mapping tidak berubah ketika Master Branch diubah;
- menambah dan melepas branch dengan rekonsiliasi Menu;
- Menu duplikat tetap satu record dengan beberapa relasi branch.

### Integration dan Job

- token dipilih berdasarkan Company Code;
- Branch Code dikirim sesuai snapshot;
- pagination seluruh halaman;
- dua Company Code tidak berbagi cache/token;
- satu branch gagal dan branch lain berhasil;
- snapshot lama dipertahankan saat refresh gagal;
- job unik dan retry-safe;
- seluruh HTTP terisolasi dari network eksternal.

### Policy dan Livewire

- pilihan branch mengikuti akses pengguna;
- manipulasi ID branch melalui request ditolak;
- create/edit modal menyimpan multi-branch;
- modal Menu memfilter branch, Company Code, kode, dan nama;
- loading, progress, empty state, partial error, dan pagination;
- Memo existing tanpa branch masih dapat dibuka sesuai strategi migrasi.

## 17. Phase implementasi

### Phase 0 — Audit dan kontrak

- Audit model, migration, Action, Page, Blade, permission, branch access, mapping ESB, cache, Job, dan test existing.
- Buktikan kontrak Master Menu untuk minimal dua branch dan dua Company Code yang tersedia.
- Tentukan mekanisme mapping utama per branch.
- Inventaris seluruh hardcode `BLSS` dalam domain Memo.
- Dokumentasikan strategi migrasi record existing.

**Selesai jika:** tidak ada mapping, endpoint, atau data existing yang masih diasumsikan.

### Phase 1 — Safety net

- Tambahkan characterization test alur Memo existing.
- Tambahkan test Policy branch scope dan kontrak service existing.
- Catat baseline waktu buka detail/modal dan jumlah request ESB.

**Selesai jika:** perubahan branch context akan membuat test gagal bila kontrak lama rusak.

### Phase 2 — Schema dan domain branch

- Buat relasi Memo–Branch dan snapshot mapping.
- Buat relasi Menu–Branch.
- Tambahkan model, factory, cast, constraint, dan migration defensif.
- Implementasikan resolver mapping utama.

**Selesai jika:** satu Memo dapat menyimpan satu atau banyak context branch secara konsisten.

### Phase 3 — Form create/edit dan Policy

- Tambahkan multi-select Branch Tujuan.
- Terapkan server-side branch authorization.
- Tambahkan state mapping tidak valid.
- Tampilkan ringkasan branch pada detail Memo.

**Selesai jika:** pengguna hanya dapat menyimpan branch yang diizinkan dan valid.

### Phase 4 — Katalog lokal dan background sync

- Generalisasi service katalog dari BLSS tetap ke Company Code + Branch Code.
- Buat job sinkronisasi unik per context.
- Simpan snapshot dan status per branch.
- Modal membaca query lokal dengan pagination/filter.

**Selesai jika:** modal tidak melakukan bulk HTTP dan kegagalan parsial dapat ditampilkan.

### Phase 5 — Menu merge dan rekonsiliasi

- Gabungkan Menu lintas branch.
- Simpan daftar branch asal.
- Implementasikan aturan penambahan/penghapusan branch.
- Jaga Menu dan Minimum Order yang masih mempunyai sumber.

**Selesai jika:** perubahan cakupan branch tidak menghapus data yang masih valid.

### Phase 6 — BOM dan Product multi-company

- Hilangkan hardcode BLSS dari resolver BOM/Product.
- Gunakan Company Code snapshot Menu.
- Pastikan cache BOM/Product terisolasi per Company Code.
- Pertahankan circular guard, depth limit, Purchase UOM fallback, dan Minimum Order.

**Selesai jika:** BOM setiap Menu selalu memakai credential Company Code yang benar.

### Phase 7 — UI final dan observability

- Selaraskan modal, progress, badge, filter, empty/error state dengan UI PRD.
- Tambahkan log aman dan correlation context.
- Ukur payload Livewire, query, latency, serta ukuran DOM.

**Selesai jika:** UI responsif, informatif, dan proses berat tidak berjalan di request browser.

### Phase 8 — Migrasi existing dan cleanup

- Jalankan migrasi/fallback existing sesuai hasil Phase 0.
- Hapus hardcode/fallback branch lama setelah seluruh consumer bermigrasi.
- Hapus kode mati hanya berdasarkan audit reference dan test.
- Jalankan test terfokus, consumer R&D, Pint, dan full suite 512 MB.

**Selesai jika:** Memo existing aman dan tidak ada dua jalur context branch yang saling bertentangan.

## 18. Quality gate

Setiap phase wajib:

1. Mempertahankan perubahan existing di working tree.
2. Menggunakan test yang terisolasi dari network.
3. Menjalankan test file terdampak sebelum full suite.
4. Menjalankan `vendor/bin/pint --dirty --format agent` bila PHP berubah.
5. Melaporkan test gagal beserta klasifikasi: regression, stale expectation, environment, atau unrelated existing failure.
6. Tidak mengubah requirement production hanya agar test stale lulus.
7. Tidak commit atau push kecuali diminta.
8. Menjelaskan deployment dan rollback jika phase menghasilkan migration/job/config baru.

Konfigurasi full suite:

```bash
php -d memory_limit=512M artisan test --compact
```

## 19. Deployment dan rollback

Deployment bertahap:

1. Backup database.
2. Deploy schema tambahan yang backward-compatible.
3. Jalankan migration dengan `--force`.
4. Deploy domain/action/job tanpa mengaktifkan UI multi-branch jika feature flag diperlukan.
5. Jalankan migrasi/rekonsiliasi existing.
6. Aktifkan UI multi-branch.
7. Jalankan worker queue dan scheduler yang diperlukan.
8. `php artisan optimize:clear` lalu bangun cache sesuai prosedur server.
9. Smoke test satu Memo satu branch, multi-branch, dan partial failure.

Rollback aplikasi harus tetap dapat membaca Memo existing. Jangan menghapus kolom/tabel lama dalam release yang sama. Job baru dapat dihentikan, UI multi-branch dinonaktifkan, dan snapshot baru dipertahankan untuk investigasi.

## 20. Acceptance criteria

1. Form Create Memo memiliki Branch Tujuan multi-select.
2. Branch berasal dari Master Branch dan dibatasi akses pengguna.
3. Company Code dan Branch Code tidak dapat diinput manual.
4. Satu Memo dapat menyimpan lebih dari satu branch.
5. Mapping ambigu atau tidak lengkap ditolak dengan alasan jelas.
6. Katalog Menu mengikuti seluruh branch Memo.
7. Menu sama pada beberapa branch tampil satu kali dengan daftar branch asal.
8. Menu tanpa BOM tidak dapat dipilih.
9. BOM/Product memakai Company Code asal Menu.
10. Kegagalan satu branch tidak menutup data branch lain.
11. Modal tidak menunggu sinkronisasi seluruh katalog ESB.
12. Snapshot lama tetap tersedia ketika refresh gagal.
13. Penghapusan branch tidak menghapus Menu yang masih tersedia dari branch lain.
14. Memo existing tetap dapat dibuka dan tidak kehilangan data.
15. Tidak ada token/password pada UI, log, exception, atau database.
16. Seluruh test integrasi terisolasi dari network eksternal.
17. UI mengikuti `docs/ui-consistency-prd.md`.
18. Implementasi mengikuti batas lapisan pada `docs/code-remediation-prd.md`.

## 21. Open decisions Phase 0

Hal berikut wajib diputuskan berdasarkan audit sebelum Phase 2:

1. Apakah mapping utama Stock Card boleh dipakai langsung sebagai mapping utama Memo Internal.
2. Apakah perlu field `is_internal_memo_source` tersendiri pada `branch_esb_codes`.
3. Branch mana yang harus dipilih untuk migrasi setiap Memo existing BLSS.
4. Apakah katalog Menu benar-benar berbeda antarbranch untuk Company Code yang sama.
5. Apakah static token tersedia untuk seluruh Company Code yang boleh dipilih.
6. Apakah user yang mempunyai akses sebagian branch dapat melihat Memo multi-branch secara penuh atau hanya ringkasan branch yang dapat diakses.
7. Berapa TTL snapshot katalog dan batas concurrency yang aman untuk server production.

Open decisions tidak boleh diselesaikan dengan memilih record pertama, menebak Company Code, atau menyalin mapping dari branch lain.
