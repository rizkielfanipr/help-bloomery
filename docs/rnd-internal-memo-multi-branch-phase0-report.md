# Phase 0 — Audit dan Kontrak: R&D Internal Memo Multi-Branch

Audit dilakukan 1 Oktober 2026 sebelum perubahan kode apa pun, sesuai `docs/rnd-internal-memo-multi-branch-prd.md`. Semua temuan dibuktikan langsung (grep kode, query database, dan live API call read-only) — tidak ada asumsi yang dipakai sebagai dasar desain.

## 1. Kondisi implementasi aktual vs PRD

Implementasi saat ini sepenuhnya single-company (BLSS), sebagaimana didokumentasikan secara eksplisit di `docs/rnd-internal-memo-simplification-prd.md`. Ini keputusan desain yang disengaja, bukan drift. PRD multi-branch ini membalik keputusan tersebut.

**Sudah multi-company-ready tanpa perlu diubah:**
- `rnd_internal_memos.company_code` sudah kolom per-row asli, sudah bagian dari unique index `(company_code, period_month_if_active, revision)`.
- `EsbCoreClient` + `config/esb.php` sudah menerima Company Code sebagai parameter; 14 company sudah terdaftar.
- `RndInternalMemoPolicy` murni permission+status based, tanpa BLSS.

**Sepenuhnya BLSS-locked (inti pekerjaan Phase 2-6):**
- `InternalMemoMenuCatalogService` — didesain eksplisit "BLSS only, never accepts a Company Code parameter".
- `InternalMemoBomResolver` — constant `COMPANY_CODE='BLSS'` terpisah sendiri, tidak reuse milik model.
- Tidak ada tabel `rnd_internal_memo_branches` / `rnd_internal_memo_menu_branches` — murni baru untuk Phase 2.
- Tidak ada Job sync sama sekali. Seluruh resolusi BOM saat ini **sinkron** dalam request Livewire (`AddMenuToInternalMemoAction`, `SynchronizeInternalMemoAction`, `RefreshInternalMemoMenuAction`). Phase 4 murni pekerjaan baru.

## 2. Daftar hardcode BLSS

| # | Lokasi | Bentuk |
|---|---|---|
| 1 | `app/Models/RndInternalMemo.php:24` | `const COMPANY_CODE = 'BLSS'` |
| 2 | `app/Services/Rnd/InternalMemo/InternalMemoBomResolver.php:25` | `private const COMPANY_CODE = 'BLSS'` (constant kedua, independen dari #1) |
| 3 | `InternalMemoBomResolver.php:228,275` | literal `blss` di dua cache key |
| 4 | `InternalMemoMenuCatalogService.php` (6 titik) | via `RndInternalMemo::COMPANY_CODE`; API publik `page()` tidak menerima parameter Company Code — butuh refactor signature |
| 5 | `CreateInternalMemoAction.php:19` | dipaksa server-side saat create |
| 6 | `ListRndInternalMemos.php:117` | cek duplikat periode di-scope ke constant, bukan per-context |
| 7-8 | `index.blade.php:15,77`; `view.blade.php:17,52,105,277` | copy UI literal "BLSS" (6 titik total) |
| 9 | `config/esb.php.master_menu_branch_codes` | hanya ada key `BLSS` |

Di luar domain Memo (dicatat, tidak disentuh): `BulkProductSubmission::COMCODES` (preseden multi-company yang sudah bekerja), `GoodsReceiptPage`/`EsbGoodsReceiptService` (fitur lain).

## 3. Mekanisme mapping utama Master Branch saat ini

Tidak ada mekanisme "primary mapping" generik di `branch_esb_codes` — tidak ada kolom `is_primary`/`priority`/`is_internal_memo_source`. Satu branch **sah dan nyata digunakan** (ada test eksplisit, bukan teoretis) punya 2+ mapping aktif sekaligus.

Satu-satunya preseden "pilih satu mapping" adalah Stock Card: kolom `stock_card_esb_code_id` (FK di `branches`, bukan flag di `branch_esb_codes`), diisi manual admin lewat `Select` di halaman Edit Branch dengan helper text eksplisit "Hanya mapping ini yang digunakan oleh Stock Card." Backfill migration-nya hanya otomatis mengisi untuk branch dengan tepat satu mapping aktif; begitu ada 2+, dibiarkan `null` menunggu keputusan admin.

Konsumen lain (Receiving, BasketSize, Sales/Promotion Information, Bulk Data Promotion) sengaja menjumlahkan/iterasi SEMUA mapping aktif — bukan preseden yang relevan.

## 4. Risiko terhadap Memo existing

5 Memo existing di database yang diaudit, semuanya `company_code='BLSS'`. Isi `recipient`/`title`/`notes` tiap baris diperiksa langsung — tidak satu pun mengandung identitas branch yang bisa dibuktikan (recipient: "All Store"/"ALL DIVISI"/"RND"/"test1"/kosong; title generik; notes kosong atau salinan title).

**Seluruh 5 Memo existing wajib ditandai "Perlu Menentukan Branch"** — tidak ada yang bisa dimigrasikan otomatis.

Catatan: database yang diaudit hanya punya 4 branch lokal bernama demo/testing dan hanya 1 baris di `branch_esb_codes` total (branch_id=1 → BLO6, bukan BLSS). Ini mengindikasikan data lokal tidak representatif terhadap branch production sebenarnya. Kredensial ESB di `.env` tetap live (dibuktikan lewat API call sungguhan). **Strategi migrasi Phase 8 harus diverifikasi ulang terhadap database production sebelum dijalankan di sana.**

## 5. Kontrak API: terbukti vs belum terbukti

**Terbukti (live API call, bukan tebakan):**
- `GET /corev1/master/get-menu` dibandingkan 3 branchCode BLSS (BLS/BLP/BLEV): payload halaman 1 byte-identik, total=1386.
- Sama untuk BLO6 (BLE vs HOF): byte-identik, total=450.
- **Katalog Menu berbeda per Company Code, TIDAK berbeda per branch dalam Company Code yang sama.**
- Token statis Master Menu: SET untuk 13/14 company; **kosong untuk BLO18**.
- Credential ESB Core (BOM): hanya SET untuk BLSS dan BLO6 di environment yang diaudit; 12 company lain tidak punya credential meski token Master Menu-nya ada.

**Belum terbukti:**
- Apakah "byte-identik lintas branch" berlaku di SEMUA 14 company (hanya BLSS & BLO6 yang dites).
- Data branch/mapping production sebenarnya (lihat §4).

## 6. File dan test terdampak

- **Risiko tinggi:** `InternalMemoMenuCatalogService.php` + `tests/Unit/InternalMemoMenuCatalogServiceTest.php` (hampir seluruh test BLSS-coupled by design, termasuk satu test berpremis "tidak ada fallback ke Company Code lain").
- **Risiko sedang:** `InternalMemoBomResolver.php`, `tests/Unit/RndInternalMemoBomContractTest.php` (butuh fixture company kedua, bukan rewrite — `EsbCoreClient` sudah multi-company).
- **Risiko rendah:** `CreateInternalMemoAction`, `ListRndInternalMemos`, Blade copy, 1 assertion di `RndInternalMemoWorkflowTest.php`.
- **Tidak terdampak:** `RndInternalMemoPolicy`, permission strings, `RndInternalMemoMaterialModelTest.php`, PDF export blade.
- `memo_number` unique global (bukan per company_code) — dipertahankan apa adanya kecuali ditemukan masalah nyata.

## 7. Keputusan Open Decision Phase 0

| # | Keputusan | Hasil |
|---|---|---|
| 1 | Mapping utama Stock Card dipakai langsung? | **Tidak.** Kolom `stock_card_esb_code_id` eksplisit Stock-Card-only secara nama dan UX. |
| 2 | Perlu field `is_internal_memo_source` sendiri? | **Ya.** Tambah FK baru `internal_memo_esb_code_id` di `branches`, meniru pola Stock Card persis. |
| 3 | Branch migrasi tiap Memo BLSS existing? | **Tidak ada yang bisa dibuktikan — seluruh 5 masuk "Perlu Menentukan Branch".** |
| 4 | Katalog Menu beda antarbranch untuk Company Code sama? | **Tidak (terbukti).** Beda hanya lintas Company Code. |
| 5 | Static token tersedia untuk semua Company Code? | **Tidak.** BLO18 kosong; ESB Core credential cuma ada untuk BLSS+BLO6. |
| 6 | User akses sebagian branch: lihat Memo penuh atau ringkasan? | **Keputusan user: Memo penuh** (selama akses ke minimal satu branch + permission view). |
| 7 | TTL snapshot & batas concurrency? | **Keputusan user: TTL 15 menit, maks 5 Company Code sync bersamaan.** |
| — | Granularitas Job sync (tambahan, muncul dari bukti #4) | **Keputusan user: ikuti PRD literal — per Company Code + Branch Code**, meski terbukti redundan untuk company yang sama hari ini. Dipertahankan untuk forward-compatibility. |

**Selesai jika** (kriteria PRD): tidak ada mapping, endpoint, atau data existing yang masih diasumsikan. ✅ Terpenuhi — seluruh keputusan di atas berbasis bukti langsung atau keputusan eksplisit user, bukan tebakan.

## 8. Phase 1 — Safety net: hasil dan baseline

Characterization test: `tests/Feature/RndInternalMemoMultiBranchCharacterizationTest.php`. Baseline yang dicatat (dan dikunci sebagai assertion, bukan sekadar prosa):

- **Jumlah request ESB untuk tambah 1 Menu dengan BOM yang berhasil di-resolve: tepat 2** (login + 1 fetch detail BOM). Ini acuan untuk Phase 6 — begitu resolusi BOM multi-company ditambahkan, angka ini boleh berubah tapi HARUS lewat commit Phase 6 itu sendiri, bukan drift diam-diam.
- **Membuka halaman detail Memo existing (tanpa refresh): 0 request ESB** — murni baca snapshot lokal. Baseline ini yang tidak boleh diregresi oleh Phase 4/7.
- Query count N+1 di index/detail sudah punya baseline sebelumnya di `tests/Feature/RndInternalMemoQueryPerformanceTest.php` (tidak diduplikasi di sini).

**Temuan tambahan di luar scope awal, ditemukan sebagai efek samping menulis characterization test di atas:** safety net `Http::preventStrayRequests()` yang didaftarkan di `tests/Pest.php` ternyata **tidak pernah benar-benar berjalan** untuk seluruh test suite (dikonfirmasi lewat probe file-write yang tidak pernah tertulis) — akar masalahnya adalah pemanggilan `beforeEach()` berdiri sendiri setelah `pest()->extend()->in('Feature')`, yang di Pest 4 tidak ter-attach ke binding tersebut. Diperbaiki dengan merantai `.beforeEach(...)` langsung ke binding yang sama (commit `d7bc4f8`). Full suite (1111 Feature + 109 Unit) tetap hijau tanpa perubahan setelah perbaikan ini, karena `StrayRequestException` adalah turunan `RuntimeException` yang sudah ditangkap secara graceful oleh `InternalMemoProductEnricher` — jadi perbaikan ini hanya mengubah **apakah ada request jaringan sungguhan yang keluar saat test**, bukan hasil lulus/gagal test mana pun. Ini temuan serius yang berlaku untuk seluruh test suite proyek, bukan cuma domain Memo, dan layak diaudit lebih lanjut di luar scope task ini (test lain mungkin masih diam-diam bergantung pada koneksi nyata tanpa disadari).
