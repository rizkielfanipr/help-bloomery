# Phase 0 — Audit Receiving dan QC Inbound

Audit dilakukan 2 Oktober 2026 sebelum perubahan kode, sesuai `docs/receiving-simplification-prd.md` §17 Phase 0, dengan referensi arsitektur `docs/code-remediation-prd.md` dan `docs/esb-integration-consolidation-roadmap.md`.

## 1. Kondisi repository aktual vs PRD

Modul Receiving **bukan fitur baru** — sudah berjalan di production dengan QC inbound, Vendor Compliance, dan idempotency ESB yang matang. Audit ini memverifikasi bagian mana dari PRD yang masih berupa gap nyata vs yang sudah terselesaikan oleh pekerjaan ESB-consolidation sebelumnya.

### 1.1 Yang TERKONFIRMASI sebagai gap nyata (sesuai klaim PRD)

- **`EsbGoodsReceiptService::COMPANY_CODE = 'BLSS'`** (`app/Services/EsbGoodsReceiptService.php`) — hardcoded, dipakai di seluruh method (`purchaseOrders`, `purchaseOrder`, `locations`, `create`). Tidak ada parameter Company Code di method manapun. PRD §12 meminta signature baru yang menerima `$companyCode` eksplisit.
- **Config `esb.php` tidak punya entri SPN** dan **`.env` tidak punya `ESB_CORE_SPN_USERNAME`/`PASSWORD`** — dikonfirmasi langsung dari file, bukan asumsi.
- **Validasi akses branch terjadi lambat** — `loadPurchaseOrders()`/`selectPurchaseOrder()` tidak memfilter berdasarkan `accessibleBranchIds()` user sama sekali; satu-satunya guard branch ada di `persist()` (baris `abort_unless($user->canAccessAllBranches() || ...)`), dipanggil setelah QC sudah selesai divalidasi dan file sudah diunggah ke disk. Ini persis PRD §3.2.
- **Form produk monolitik** — `resources/views/filament/casual/pages/goods-receipt-page.blade.php` me-render SELURUH field QC (quantity, 4 visual check, cold chain, shelf life/batch, sampling, rejection, foto) untuk SETIAP baris produk tanpa syarat, dalam satu `@foreach` tanpa drawer/modal. `itemQcPreview()` dipanggil per item dari Blade, berarti untuk PO 100 produk fungsi ini berjalan hingga 100× pada setiap render yang menyentuh bagian tersebut.
- **Field dokumen belum disatukan** — `deliveryNumber`/`invoiceNumber`/`deliveryDate`/`invoiceDate`/`invoiceStatus` masih terpisah penuh, persis seperti dikeluhkan PRD §2.1-2.2.
- **Tidak ada status upload eksplisit "Berhasil"** — Blade hanya menampilkan nama file di list (implisit = berhasil) dan teks "Mengunggah foto..." yang muncul SELAMA `wire:loading` pada target upload. Begitu upload selesai, teks hilang tanpa indikator "✓ Berhasil" yang persisten.
- **Submit tidak diblokir saat upload foto masih berjalan** — tombol submit hanya `wire:loading.attr="disabled"` dengan target implisit (`submit` itu sendiri), BUKAN `wire:target="documentEvidencePhotos,items.*.evidencePhotos"`. User bisa klik Submit saat file masih di-upload, menyebabkan state yang membingungkan (persis akar masalah pain point #4 PRD §2).
- **Informasi Tutup PO masih ada di UI** — checkbox `autoClosePo` dan field terkait masih tampil (baris 340 Blade), belum dihitung otomatis.

### 1.2 Yang TERNYATA SUDAH SELESAI (PRD/roadmap OUTDATED di titik ini)

- **Idempotency mutation Goods Receipt sudah ada** (`docs/esb-integration-consolidation-roadmap.md` §4.5/Phase D2 — **terverifikasi benar**): `submissionKey` UUID + unique constraint + `payload_hash` + `attempted_at` + status `unknown` untuk reconciliation koneksi gagal. Tidak perlu dibangun ulang.
- **`EsbCoreClient` sudah dipakai** oleh `EsbGoodsReceiptService` (bukan HTTP mentah) — transport, token cache per company, retry 401 sudah seragam.
- **Mapping branch ESB untuk Receiving JAUH LEBIH FLEKSIBEL dari dugaan.** `branch_esb_codes` punya unique constraint `(branch_id, esb_branch_code, esb_comcode)` — satu branch BOLEH punya banyak baris mapping untuk Company Code berbeda, dan `EsbBranchMappingResolver::resolve(string $companyCode, int $esbBranchId, ?string $esbBranchCode)` SUDAH menerima Company Code sebagai parameter dan mencocokkan secara dinamis. **Ini berbeda dari pola Stock Card/Store Sales Order** yang memakai satu kolom pilihan tunggal (`stock_card_esb_code_id`). Artinya: pekerjaan multi-company Phase 4 PRD **tidak perlu mengubah lapisan mapping branch sama sekali** — cukup menghilangkan hardcode `COMPANY_CODE` di `EsbGoodsReceiptService` dan membawa Company Code sebagai context dari PO yang dipilih. Ini secara signifikan mengurangi scope Phase 4 dibanding yang PRD gambarkan.
- **Pemisahan state foto dokumen vs item sebenarnya sudah ada secara struktural** — `documentEvidencePhotos` (level dokumen) terpisah dari `items.*.evidencePhotos` (level item, untuk bukti exception QC). Yang BELUM ada adalah pemisahan **"Foto Dokumen" vs "Foto Barang"** sebagai DUA kelompok terpisah di level dokumen (PRD §5.3) — saat ini hanya ada SATU kelompok foto level dokumen yang mencampur keduanya.

### 1.3 Field schema — nama kolom PRD vs kolom aktual

PRD §15 menyebut nama kolom rekomendasi yang **tidak sama persis** dengan kolom existing. Tidak ada kolom `source_company_code`, `document_type`, `document_number`, `document_date`, `document_photos`, atau `goods_photos` di migration manapun. Kolom aktual: `company_code`, `delivery_number`+`invoice_number`, `delivery_date`+`invoice_date`, `document_evidence_photos`. Keputusan: tambahkan kolom BARU dengan nama persis seperti direkomendasikan PRD §15 (additive, migration baru), isi field lama secara paralel untuk kompatibilitas laporan (lihat §4 di bawah).

## 2. Keputusan atas 6 pertanyaan terbuka PRD §21

| # | Pertanyaan | Jawaban Phase 0 |
|---|---|---|
| 1 | Company Code benar `BLSS` atau `BSS`? | **`BLSS`** — dikonfirmasi dari kode (`EsbGoodsReceiptService::COMPANY_CODE`), `.env`, dan `config/esb.php`. Contoh tabel akses user Erwin di PRD §5.1 menyebut "BLSS/BSS" sebagai dua alias kemungkinan; kode production secara konsisten memakai `BLSS`. `BSS` tidak ditemukan di `config('esb.core.companies')` manapun. |
| 2 | Kredensial ESB Core SPN sudah tersedia? | **TIDAK.** Dikonfirmasi langsung: `.env` tidak punya `ESB_CORE_SPN_USERNAME`/`PASSWORD`, `config/esb.php` tidak punya entri `SPN`. **Blocker eksternal terdokumentasi** — sama persis pola Store Sales Order Phase 0 (akses ESB belum tersedia). Pekerjaan Phase 4 tetap dilanjutkan dengan membuat kode SPN-ready (parameterisasi Company Code, slot config siap diisi), TANPA bisa memverifikasi response SPN secara live. |
| 3 | Endpoint SPN memakai kontrak sama dengan BLSS? | **Tidak dapat diverifikasi** — konsekuensi langsung dari #2. Diasumsikan sama (ESB Core adalah API bersama, bukan API per-company yang berbeda kontrak), didokumentasikan sebagai asumsi, bukan fakta terverifikasi. |
| 4 | Numeric Branch ID SPN untuk Sarana? | **Tidak dapat diverifikasi** tanpa akses SPN. Akan diisi melalui mekanisme **Sync Branch ESB** existing (`docs/esb-integration-consolidation-roadmap.md` §4.3) begitu kredensial tersedia — bukan di-hardcode. |
| 5 | Metadata ESB sediakan cold chain/ED/sampling? | **Tidak tersedia** — `InboundGoodsReceiptQcService::assessItem()` memakai field yang DIISI USER per baris (`temperatureCategory`, `shelfLifeRequired`, `samplingRequired`), bukan dibaca dari metadata produk ESB. Tidak ada field metadata seperti itu pada payload PO yang diaudit. |
| 6 | Jika metadata tidak tersedia, dikelola lokal? | **Ya, sudah demikian** — pertahankan pola existing (toggle manual per baris: "Perlu Pemeriksaan Suhu", "Produk Memakai Batch/Expiry", "Sampling Test Diperlukan"), sesuai PRD §8/§10 yang memang mengizinkan "default sementara yang dapat dikoreksi user". |

## 3. Dampak terhadap scope Phase 4 (multi-company)

Karena mapping branch-ESB sudah dinamis per Company Code (§1.2), Phase 4 PRD disederhanakan menjadi:

1. Parameterisasi `EsbGoodsReceiptService` agar menerima `$companyCode` di setiap method (kontrak sama seperti PRD §12).
2. `GoodsReceiptPage` membawa Company Code dari PO yang dipilih (bukan konstanta), diteruskan ke seluruh pemanggilan service berikutnya (detail, lokasi, submit) — "satu context sumber" PRD §4.4.
3. Daftar PO digabung dari SELURUH Company Code yang punya mapping aktif ke branch yang dapat diakses user (bukan hanya BLSS).
4. Menambahkan slot config SPN di `config/esb.php` (default null, aman jika env belum diisi — konsisten dengan company lain yang belum dipakai).
5. **Tidak perlu** membangun ulang `EsbBranchMappingResolver` atau skema `branch_esb_codes`.

## 4. Strategi kompatibilitas data (PRD §15)

Kolom baru ditambahkan via migration baru (additive), kolom lama **tidak dihapus dan tetap diisi** selama masa transisi:

| Kolom baru | Sumber nilai | Kolom lama yang tetap diisi paralel |
|---|---|---|
| `document_type` (`delivery_note`\|`invoice`) | Pilihan user | — |
| `document_number` | Input tunggal user | `delivery_number` (jika `document_type=delivery_note`) atau `invoice_number` (jika `document_type=invoice`) |
| `document_date` | Input tunggal user | `delivery_date` atau `invoice_date` sesuai jenis dokumen |
| `document_photos` | Upload baru "Foto Dokumen" | `document_evidence_photos` (diisi gabungan `document_photos` + `goods_photos` agar laporan lama yang membaca kolom ini tidak kehilangan data) |
| `goods_photos` | Upload baru "Foto Barang" | — (data baru, tidak ada padanan lama) |
| `source_company_code` | Company Code asal PO (saat ini selalu `BLSS`, siap menerima `SPN` di Phase 4) | `company_code` tetap diisi nilai yang sama (kolom lama dipertahankan sebagai alias) |

Payload ESB (`EsbGoodsReceiptService::create()`) di Phase 1 **tetap memakai field lama** (`deliveryNum` dari `delivery_number`/`invoice_number` sesuai jenis dokumen yang dipilih) — PRD §17 Phase 1 eksplisit "Pertahankan payload ESB BLSS agar risiko perubahan kecil".

## 5. Test existing yang jadi characterization test safety-net

| File | Cakupan |
|---|---|
| `tests/Feature/EsbGoodsReceiptServiceTest.php` | Kontrak shared client, refresh token, pesan error |
| `tests/Feature/GoodsReceiptPageTest.php` | Persist item/expiry, label "Receiving", sort PO, filter status PO |
| `tests/Feature/ReceivingAuthorizationTest.php` | Permission akses/submit, back office read-only, permission Vendor Compliance |
| `tests/Feature/VendorComplianceResourceTest.php` | Incident muncul di menu purchasing, follow-up tercatat |
| `tests/Unit/GoodsReceiptServiceTest.php` | Auth BLSS + fetch PO, create GR pakai PO sebagai reference number |
| `tests/Unit/InboundGoodsReceiptQcServiceTest.php` | Kalkulasi QC, cold chain/sampling blocking, demerit, shelf life recalculation |

Seluruh test ini harus tetap hijau sepanjang Phase 1-5 kecuali ada perubahan kontrak yang memang disengaja dan didokumentasikan (sesuai prinsip `docs/code-remediation-prd.md` §5 "Preserve contracts").

## 6. Prinsip arsitektur yang diikuti (dari `docs/code-remediation-prd.md`)

`GoodsReceiptPage` saat ini memiliki `rules()`, `validateQc()`, `payload()`, `persist()` sebagai method PRIVATE di dalam Page — melanggar pembagian tanggung jawab target (`docs/code-remediation-prd.md` §6: "Action" seharusnya menangani transaction boundary, bukan Page). Mengingat PRD Receiving tidak secara eksplisit meminta ekstraksi Action pada Phase 1-3, dan `code-remediation-prd.md` §5 prinsip #5 "No speculative abstraction: ekstrak setelah ada tanggung jawab atau reuse yang jelas" — ekstraksi `persist()`/`payload()` ke Action terpisah dilakukan pada **Phase 4** (bersamaan dengan parameterisasi Company Code, karena `persist()` butuh diubah untuk membawa `source_company_code` dan `EsbBranchMappingResolver::resolve($companyCode, ...)` dinamis), bukan dipaksakan di Phase 1 tanpa kebutuhan nyata.

## 7. Urutan implementasi yang diambil

Mengikuti `docs/receiving-simplification-prd.md` §17 persis: Phase 1 (form + attachment) → Phase 2 (tabel produk ringkas) → Phase 3 (ED/QC dinamis) → Phase 4 (multi-company) → Phase 5 (auto-close + performa). Setiap phase di-commit terpisah dengan test dan Pint, mengikuti quality gate `docs/code-remediation-prd.md` §10.
