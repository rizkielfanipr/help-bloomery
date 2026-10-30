---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-REMEDIATION-PRD
title: PRD Remediasi — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal
status: draft
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-30
updated_at: 2026-10-30
target_release: TBD
related_documents:
  - docs/products/inventory/receiving-central-blss/reports/audit-report.md
  - docs/products/inventory/receiving-central-blss/implementation-plan.md
  - docs/products/inventory/receiving-central-blss/rollout.md
  - docs/products/inventory/receiving-simplification/prd.md
  - docs/standards/code-remediation.md
supersedes: null
---

# PRD Remediasi — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal

> Remediasi ini memperbaiki guard branch pada workflow Receiving existing. Penyederhanaan UI dan QC pada PRD Receiving sebelumnya tetap berlaku.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Inventory |
| Scope | Employee App → Receiving, company `BLSS` |
| Status | draft |
| Audit acuan | `docs/products/inventory/receiving-central-blss/reports/audit-report.md` |
| Keputusan bisnis | Inventory Pusat dapat menerima semua PO BLSS yang receivable berdasarkan permission Receiving |

## 2. Ringkasan

Saat ini PO BLSS dapat ditampilkan dan diisi, tetapi submit Goods Receipt berhenti bila branch ESB pada PO belum dimapping ke branch lokal. Perilaku tersebut tidak sesuai model operasional: Receiving dijalankan Inventory Pusat dan permission Receiving berlaku lintas branch BLSS. Target remediasi adalah menjadikan mapping lokal sebagai metadata opsional tanpa mengubah destination branch dan lokasi ESB pada PO.

## 3. Baseline dan bukti

Baseline aplikasi: `main` commit `d750709a` pada 30 Oktober 2026. Baseline test berikut lulus sebelum implementasi: **46 test, 310 assertion** dari `GoodsReceiptPageTest`, `ReceivingAuthorizationTest`, `EsbGoodsReceiptServiceTest`, `EsbTransactionRecorderTest`, `InboundGoodsReceiptQcServiceTest`, dan `GoodsReceiptServiceTest`.

Alur aktual:

1. Daftar PO hanya diambil dari company `BLSS`.
2. Policy Employee App mengizinkan akses dan submit melalui `access employee app goods receipt`.
3. Detail dan lokasi diambil dari ESB menggunakan branch ID pada PO.
4. Submit mengambil ulang detail/status PO, lalu mewajibkan `EsbBranchMappingResolver::resolve()` menghasilkan mapping aktif.
5. Persist dan context monitoring dereference mapping sebagai nilai wajib.

Audit ini berbasis kode dan test lokal. Data, credential, serta transaksi production tidak diperiksa.

## 4. Temuan

| ID | Temuan | Severity | Bukti | Dampak |
|---|---|---|---|---|
| R-1 | Submit mewajibkan mapping branch lokal walau policy Receiving bersifat lintas branch | Tinggi | `GoodsReceiptPage.php:424–441`; `GoodsReceiptPolicy.php:24–31` | Inventory Pusat tidak dapat menyimpan GR dari branch BLSS yang belum dimapping |
| R-2 | Persist dan monitoring menganggap mapping selalu tersedia | Tinggi | `GoodsReceiptPage.php:491–498`, `606–617` | Menghapus guard saja akan menimbulkan dereference null |
| R-3 | Resolver fallback dapat mengubah `esb_branch_id` saat lookup berdasarkan branch code | Sedang | `EsbBranchMappingResolver.php:34–42` | Submit Receiving berpotensi mengubah konfigurasi Master Branch sebagai efek samping |
| R-4 | Test existing mengunci perilaku penolakan PO unmapped | Sedang | `GoodsReceiptPageTest.php:306–350` | Perubahan tanpa mengganti characterization test akan bertentangan dengan requirement baru |
| R-5 | Scope Back Office berbeda dari permission Employee App | Sedang | `GoodsReceiptPolicy.php:11–21`; `GoodsReceiptResource.php:51–77` | Mengubah Receiving tidak boleh otomatis membuka seluruh histori GR |
| R-6 | PRD lama masih memuat rencana multi-company dan branch scoping yang belum diterapkan | Rendah | `receiving-simplification/prd.md` §12–§13 | Implementasi dapat melebar ke SPN atau akses branch yang tidak diminta |

## 5. Goals dan non-goals

- **Goals:** user aktif dengan permission Receiving dapat memproses semua PO `BLSS` berstatus receivable tanpa mapping branch lokal; identitas branch ESB tetap tersimpan; mapping existing tetap dapat dipakai sebagai metadata tanpa efek samping; QC, idempotency, monitoring, dan Vendor Compliance tetap aman.
- **Non-goals:** menambah SPN/company lain; permission baru; perubahan desain form; perubahan aturan QC; mapping otomatis; branch lokal dummy; perubahan role; perluasan akses Back Office; migration atau dependency baru; transaksi production saat implementasi/test.

## 6. Perilaku yang wajib dipertahankan

- Tile dan submit memerlukan `access employee app goods receipt`; `access_all_branches` tidak menggantikan permission tersebut.
- Sumber Receiving tetap company `BLSS`, dan manipulasi company dari browser ditolak server-side.
- PO harus berstatus Authorized atau Receiving saat dibuka dan diperiksa ulang sebelum write.
- Branch ID/nama, supplier, lokasi, dan produk tetap berasal dari PO ESB; user tidak dapat mengganti destination ke branch lokalnya.
- Validasi dokumen, foto, qty, ED/batch, cold chain, sampling, Accepted/Hold/Rejected, serta payload ESB tidak berubah.
- Submission key tetap mencegah duplikasi lokal; outcome koneksi tidak pasti tetap `unknown` dan harus direkonsiliasi sebelum retry.
- Item rejected tetap membuat Vendor Compliance incident dan notifikasi Purchasing sesuai aturan existing.
- Mapping aktif yang sudah ada dan data GR lama tetap dapat dibaca.
- Back Office Receiving tetap read-only dan memerlukan permission terpisah.

## 7. Target perilaku

| ID temuan | Perilaku target | Acceptance criteria |
|---|---|---|
| R-1 | Mapping lokal bukan prasyarat submit | User Receiving tanpa akses branch dan tanpa mapping dapat membuat GR dari PO BLSS yang valid |
| R-1 | Permission Receiving berlaku ke seluruh branch BLSS | Dua PO BLSS dari branch ESB berbeda dapat diproses user yang sama tanpa konfigurasi branch lokal |
| R-2 | Persist mendukung mapping nullable | Tanpa mapping, receipt menyimpan `local_branch_id = null` serta company, ESB branch ID/nama, lokasi, supplier, submitter, dan timestamp |
| R-2 | Monitoring mendukung branch lokal null | Mutasi tetap tercatat dengan receipt, company, PO, correlation/idempotency context; monitoring tidak menggagalkan atau mengulang write ESB |
| R-3 | Lookup mapping Receiving bersifat read-only | Mapping exact yang aktif boleh mengisi `local_branch_id`; lookup tidak membuat, mengaktifkan, atau memperbarui mapping |
| R-3 | Ambiguitas mapping aman | Mapping code yang ambigu atau tidak cocok diperlakukan sebagai metadata tidak tersedia, bukan blocker transaksi |
| R-4 | Test mencerminkan requirement baru | Test penolakan unmapped diganti test sukses; test negatif permission/company/status/location tetap ada |
| R-5 | Authorization Back Office tidak melebar | Permission Receiving saja tidak memberi akses Back Office; viewer all-branch dapat membaca receipt nullable; viewer terbatas tetap mengikuti scope existing |
| R-6 | Scope terkunci ke BLSS | Daftar, detail, lokasi, dan create hanya memakai `BLSS`; company lain ditolak sebelum write ESB |

Perubahan branch ID pada detail PO antara pemilihan dan submit harus diperlakukan sebagai context yang tidak lagi valid: submit tidak boleh memakai lokasi yang berasal dari branch lama. Implementasi boleh menolak dan meminta user memuat ulang, atau mengambil ulang lokasi dan memvalidasi pilihan, selama tidak mengalihkan destination secara diam-diam.

## 8. Data existing dan migrasi

Tidak ada migration atau backfill yang direncanakan karena `goods_receipts.local_branch_id` sudah nullable. Data existing, foreign key, mapping, route, permission, dan status dipertahankan. Tidak boleh membuat branch lokal dummy. Bila audit schema pada branch implementasi membuktikan asumsi nullable salah, implementasi berhenti dan rencana diperbarui sebelum migration dibuat.

Receipt dengan `local_branch_id = null` merupakan state valid setelah remediasi. Rollback kode tidak boleh menghapus receipt, item, expiry, attachment, incident, atau nomor GR yang sudah dibuat.

## 9. Authorization dan security

Policy Employee App tetap menjadi kontrol utama. Public property Livewire `sourceCompanyCode`, PO number, branch, location, dan item tidak dipercaya tanpa validasi ulang server-side. Hanya company `BLSS` yang diizinkan. PO terbaru dan statusnya dibaca ulang sebelum write; location harus terbukti berasal dari branch PO terbaru. Seluruh request invalid harus berhenti sebelum `EsbGoodsReceiptService::create()`.

Lookup mapping opsional harus read-only. Rekomendasi desain: tambahkan method resolver khusus lookup tanpa mutasi, dengan prioritas exact company + ESB branch ID; fallback branch code hanya diterima bila tepat satu mapping aktif ditemukan. Method `resolve()` existing dan konsumennya tidak diubah dalam remediasi ini.

## 10. Testing strategy

Characterization baseline adalah 46 test/310 assertion yang telah lulus. Implementasi wajib menambah atau memperbarui test untuk setiap temuan:

- unmapped, mapped exact, mapping nonaktif, mapping company lain, dan branch-code ambigu;
- Receiving lintas dua branch BLSS tanpa branch access;
- user tanpa permission, company non-BLSS, status PO berubah, branch context berubah, location invalid, dan qty/QC invalid;
- Accepted penuh, partial, Hold-only, Rejected-only, Vendor Compliance, foto/cleanup storage, dan ED/batch;
- duplicate submission, connection failure → `unknown`, application/ESB failure → `failed`, dan tanpa retry write otomatis;
- monitoring dengan branch null serta failure penyimpanan monitoring;
- Back Office viewer all-branch, viewer terbatas, dan user Receiving-only.

Semua HTTP ESB menggunakan fake/mock; gunakan pencegahan stray request bila sesuai pola suite. Tidak ada Goods Receipt nyata dikirim saat test.

## 11. Phase remediasi

| Phase | Temuan | Output | Prasyarat |
|---|---|---|---|
| 0 — Characterization | R-1–R-6 | Test reproduksi, audit schema/context, keputusan lookup read-only | PRD disetujui |
| 1 — Core fix | R-1–R-3, R-6 | Mapping nullable, lookup read-only, guard BLSS/context | Phase 0 lulus |
| 2 — Downstream safety | R-2, R-4, R-5 | Regresi QC, incident, monitoring, idempotency, Back Office | Phase 1 lulus |
| 3 — Delivery | Seluruhnya | README aktual, laporan implementasi, CI, PR, UAT staging | Seluruh test lulus |

Setiap phase dicatat menggunakan `docs/templates/remediation/phase-report-template.md` bila menghasilkan keputusan, temuan, atau deviasi yang perlu dipertahankan.

## 12. Rollout dan rollback

Detail berada di [rollout runbook](rollout.md). Tidak ada feature flag, env, permission, migration, queue, atau scheduler baru. Setelah PR implementasi dan CI disetujui, deploy ke staging lebih dulu. Smoke test memakai PO BLSS uji dari branch yang tidak mempunyai mapping lokal. Production dan transaksi GR nyata memerlukan persetujuan pengguna tersendiri.

Rollback mengembalikan kode ke versi sebelumnya, sehingga PO unmapped kembali ditolak. Rollback tidak menghapus data. Receipt dengan outcome `unknown` wajib direkonsiliasi terhadap ESB sebelum tindakan retry atau rollback operasional.

## 13. Risiko

| Risiko | Mitigasi |
|---|---|
| Dereference mapping null tertinggal | Audit semua pemakaian mapping dan test unmapped end-to-end |
| Lookup opsional mengubah Master Branch | Gunakan method read-only; test memastikan record mapping tidak berubah |
| Manipulasi company/location | Guard BLSS dan validasi PO/location terbaru sebelum create |
| GR ganda setelah timeout | Pertahankan idempotency; `unknown` wajib rekonsiliasi, tanpa automatic retry create |
| Receipt null tidak terlihat di Back Office | Test scope viewer; jangan memperluas authorization diam-diam |
| Scope melebar ke SPN/multi-company | Constant/guard BLSS dan test negatif company lain |
| Monitoring failure memengaruhi transaksi bisnis | Pertahankan isolasi recorder dan test failure storage monitoring |

## 14. Open decisions

Tidak ada keputusan bisnis terbuka yang memblokir planning. Pengguna telah menetapkan Inventory Pusat, seluruh branch BLSS, dan mapping lokal tidak wajib. Keputusan teknis lookup read-only dipilih untuk menjaga metadata existing tanpa mutasi konfigurasi. Implementasi baru dimulai setelah instruksi pengguna berikutnya.

## 15. Definition of Done

- [ ] Setiap R-1 sampai R-6 memiliki test regresi.
- [ ] Characterization baseline dan test tambahan lulus.
- [ ] Receiving PO BLSS unmapped berhasil di staging dengan data uji yang disetujui.
- [ ] Tidak ada migration, env, permission, dependency, queue, atau scheduler baru.
- [ ] Tidak ada perubahan mapping sebagai efek samping submit.
- [ ] Monitoring, QC, Vendor Compliance, idempotency, dan Back Office scope terverifikasi.
- [ ] README Inventory sesuai perilaku final dan implementation report tersedia.
- [ ] CI Pull Request hijau; merge dan production deployment menunggu keputusan pengguna.
