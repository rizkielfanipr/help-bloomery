---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-REPORT
title: Laporan Implementasi — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal
status: in-review
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-30
updated_at: 2026-10-30
target_release: TBD
related_documents:
  - docs/products/inventory/receiving-central-blss/prd.md
  - docs/products/inventory/receiving-central-blss/implementation-plan.md
  - docs/products/inventory/receiving-central-blss/rollout.md
  - docs/products/inventory/receiving-central-blss/reports/audit-report.md
supersedes: null
---

# Laporan Implementasi — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal

Acuan: `docs/products/inventory/receiving-central-blss/prd.md`

## 1. Status per phase

Seluruh phase dikerjakan dalam satu PR dari branch `fix/receiving-central-blss`. Branch dibuat di atas `docs/receiving-blss-central-inventory-plan` (dua commit planning di atas `main` `d750709a`) karena PR planning belum di-merge. Dengan begitu dokumen planning ikut PR yang sama tanpa diduplikasi.

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 — Characterization | Selesai | Branch `fix/receiving-central-blss` | Baseline 46 test/310 assertion lulus. Test target ditulis lebih dulu dan gagal pada kode lama: 15 gagal + 3 error (lihat §5) |
| 1 — Core fix | Selesai | Branch `fix/receiving-central-blss` | `lookup()` read-only, `local_branch_id` nullable pada persist/monitoring, context PO dikunci, guard cabang PO berubah |
| 2 — Downstream safety | Selesai | Branch `fix/receiving-central-blss` | Regresi QC, Vendor Compliance, foto, idempotency, failed/unknown, monitoring, dan Back Office lulus. Satu bug laten ditemukan dan diperbaiki (§7) |
| 3 — Delivery | Berjalan | PR ke `main` | README Inventory, status dokumen, laporan ini, dan PR selesai. UAT staging menunggu merge |

Phase report terpisah tidak dibuat: keputusan, deviasi, dan temuan seluruh phase dicatat di laporan ini karena semuanya ada dalam satu PR.

## 2. Ringkasan perubahan

- `app/Services/EsbBranchMappingResolver.php`: method baru `lookup()`. Method ini mencari mapping aktif exact company + ESB branch ID lebih dulu. Fallback kode cabang hanya dipakai bila tepat satu mapping aktif cocok, dan record mapping tidak pernah di-update. `resolve()` dan konsumennya (Policy Back Office) tidak berubah.
- `app/Filament/Casual/Pages/GoodsReceiptPage.php`:
  - Guard "Branch PO belum dimapping" dihapus. `local_branch_id` diisi dari `lookup()` bila tersedia; bila tidak, nilainya `null`. `persist()` kini menerima `?int $localBranchId`, dan context monitoring mengirim `branch_id` yang sama.
  - Submit menolak company selain `BLSS` (403). Submit juga menolak bila cabang ESB pada PO terbaru berbeda dari saat PO dibuka, dengan pesan meminta user memuat ulang PO dan memilih ulang lokasi.
  - `$purchaseOrder`, `$sourceCompanyCode`, dan `$locations` diberi `#[Locked]`, sehingga context PO dan daftar lokasi cabang PO tidak dapat dimanipulasi dari browser.
  - Import `App\Models\User` ditambahkan untuk `notifyPurchasing()` (§7).
- `app/Services/EsbGoodsReceiptService.php`: komentar class diselaraskan. Sebelumnya komentar menyebut Receiving tidak lagi terbatas BLSS; kini menyebut caller Employee App terkunci ke BLSS.
- Test: `tests/Feature/GoodsReceiptPageTest.php`, `tests/Feature/EsbBranchMappingTest.php`, `tests/Feature/ReceivingAuthorizationTest.php`.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| Nama method `lookup()` dengan signature yang sama seperti `resolve()` | Sejajar dengan sibling method; nama menunjukkan pencarian tanpa efek samping | PRD §9 |
| Match exact memakai record pertama seperti `resolve()` | `(esb_comcode, esb_branch_id)` tidak unik di schema. Memakai urutan yang sama menjaga konsistensi dengan `localBranchIdForReceipt()` yang dipakai Policy Back Office | PRD §9, audit A-5 |
| Context PO, company, dan lokasi dikunci dengan `#[Locked]` Livewire | Lokasi dibuktikan berasal dari cabang PO tanpa request ESB tambahan. Atribut ini sudah dipakai di `StockCardEntryPage` | PRD §9, Livewire docs `attribute-locked` |
| Cabang PO berubah → submit ditolak dengan pesan muat ulang | PRD §7 mengizinkan menolak atau mengambil ulang lokasi. Menolak lebih sederhana dan tidak pernah mengalihkan tujuan diam-diam | PRD §7 |
| Guard company di submit memakai 403 dan pesan yang sama dengan `selectPurchaseOrder()` | Konsisten dengan guard company existing | PRD §9 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §5 Non-goals | Perbaikan import `App\Models\User` di `GoodsReceiptPage` | Tanpa import ini, setiap submit dengan item Rejected gagal (`Class ... not found`) setelah receipt dan incident tersimpan. Pada kasus partial, create ESB tidak pernah dipanggil. PRD §6 dan §10 mewajibkan Vendor Compliance dan notifikasi Purchasing tetap benar | Tidak ada; dicakup test Rejected-only |
| Implementation plan §16 | Phase report digabung ke laporan ini | Semua phase dikerjakan dalam satu PR | Tidak ada |
| Status PRD | Status menjadi `in-development`, belum `implemented` | Standar dokumentasi mendefinisikan `implemented` sebagai sudah berjalan di production | Ubah ke `implemented` setelah rollout production sehat |

## 5. Test dan verifikasi

Baseline sebelum perubahan (enam file PRD §3): **46 test, 310 assertion, lulus**.

Phase 0 pada kode lama: 50 test di `GoodsReceiptPageTest`, `EsbBranchMappingTest`, dan `ReceivingAuthorizationTest` menghasilkan 32 lulus, 15 gagal, dan 3 error. Semua kegagalan sesuai perilaku lama:

- notifikasi "Branch PO belum dimapping" muncul;
- `resolve()` mengubah `esb_branch_id` mapping dari 31 menjadi 30, yang membuktikan R-3;
- property belum terkunci;
- `lookup()` belum ada.

Test Rejected-only juga diverifikasi gagal tanpa import `User`.

Setelah implementasi:

| Perintah | Hasil |
|---|---|
| Enam file PRD §3 (`GoodsReceiptPageTest`, `ReceivingAuthorizationTest`, `EsbGoodsReceiptServiceTest`, `Monitoring/EsbTransactionRecorderTest`, `InboundGoodsReceiptQcServiceTest`, `GoodsReceiptServiceTest`) | 63 test, 439 assertion, lulus |
| `tests/Feature/EsbBranchMappingTest.php` | 10 test, 25 assertion, lulus |
| `vendor/bin/pint --dirty --format agent` | Lulus |
| `bash ops/ci/check-doc-links.sh`, `bash ops/ci/check-code-map.sh`, `git diff --check` | Lulus |

Cakupan test baru dan yang diperbarui:

| Requirement | Test |
|---|---|
| Unmapped berhasil, `local_branch_id` null, context monitoring lengkap dengan `branch_id` null | `receiving saves an unmapped BLSS purchase order with a null local branch and monitoring context` |
| Dua cabang BLSS untuk user yang sama | `one receiving user processes purchase orders from two unmapped BLSS branches` |
| Mapping exact, nonaktif, company lain, tidak ada, ambigu, atau cocok satu kode → tidak memblokir dan tidak memutasi mapping | Dataset `optional branch mapping metadata never blocks receiving or changes the mapping`; `lookup` tests di `EsbBranchMappingTest` |
| Cabang PO berubah, status PO berubah, lokasi invalid, qty invalid → tanpa create | `receiving rejects ...` (tiga test) |
| Manipulasi company, cabang PO, atau daftar lokasi dari browser | Dataset `receiving keeps the purchase order source context locked against client tampering` |
| Rejected-only, Hold-only, partial, Vendor Compliance, notifikasi Purchasing, foto | `a rejected-only ...`, `a hold-only ...`, `a partial ...` |
| ESB menolak → `failed`; submission key yang sama tidak menggandakan receipt, GR, incident, atau file | `a partial unmapped receipt ... never duplicates on resubmit` |
| Koneksi terputus → `unknown` | Test existing `receiving guards duplicate submissions and marks an uncertain connection result for reconciliation` |
| Monitoring gagal tidak mengulang mutasi | Test existing `never fails or repeats the business mutation when monitoring storage is unavailable` |
| Receiving-only tanpa Back Office; viewer terbatas tidak melihat receipt null; viewer all-branch melihatnya | `does not open Back Office Receiving ...`, `keeps unmapped central Receiving records out of a limited viewer scope`, test existing `allows global branch access to include historical unmapped receipts` |
| `access_all_branches` tanpa permission Receiving ditolak | Test existing `all branch access without the receiving tile permission remains forbidden` |
| ED/batch | Test existing `receiving saves calculated shelf life for form batches` dan `a quick ED on a Sesuai item ...` |

Semua integrasi ESB di test memakai mock `EsbGoodsReceiptService`, sehingga tidak ada request ESB nyata. UAT staging belum dijalankan dan mengikuti [rollout runbook](../rollout.md).

Catatan test: di environment test, `hashName()` upload Livewire bersifat deterministik. Karena itu test duplikat memakai komponen kedua (skenario tab lain) dengan foto berbeda, bukan menyimpan ulang file yang sama.

## 6. Migration dan konfigurasi

Not applicable — tidak ada migration, backfill, env, permission, role, dependency, queue, scheduler, atau feature flag baru. Schema lokal `goods_receipts.local_branch_id` sudah terverifikasi nullable dengan FK `on delete set null`.

## 7. Risiko dan pekerjaan lanjutan

- **Temuan baru (sudah diperbaiki):** `notifyPurchasing()` memakai `User` tanpa import. Bug ini sudah ada sebelum remediasi dan tidak tertangkap karena belum ada test yang menjalankan item Rejected melalui halaman. Bila versi sebelumnya sudah berjalan di production, receipt partial-rejected yang tertahan di status `processing` tanpa nomor GR perlu dicek owner dan direkonsiliasi terhadap ESB; laporan ini tidak memeriksa data production.
- Receipt dengan `local_branch_id` null hanya terlihat oleh viewer Back Office `access_all_branches`, atau oleh viewer yang cabangnya kemudian dimapping aktif ke cabang ESB tersebut. Perilaku ini sesuai PRD §7 R-5. Bila Inventory Pusat perlu melihat histori di Back Office, berikan `view goods receipts` beserta akses semua cabang melalui Role & Permission.
- Lookup exact memilih record pertama bila ada lebih dari satu mapping aktif untuk `(company, ESB branch ID)` yang sama, sama seperti `resolve()`. Data Master Branch sebaiknya dijaga unik.
- Setelah merge: deploy staging, jalankan UAT dengan PO BLSS uji dari cabang tanpa mapping, pantau receipt `unknown`/`failed` dan `local_branch_id` null, lalu minta persetujuan production. Setelah production sehat, ubah status PRD menjadi `implemented`.
