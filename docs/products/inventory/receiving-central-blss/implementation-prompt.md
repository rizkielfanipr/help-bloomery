---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-PROMPT
title: Prompt Implementasi — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal
status: draft
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
supersedes: null
---

# Prompt Implementasi — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal

Salin blok berikut ke agent implementasi dari root repository setelah PRD disetujui. Menyimpan prompt ini tidak mengotorisasi implementasi, merge, deployment, atau transaksi ESB.

```text
Implementasikan Receiving Terpusat BLSS Tanpa Mapping Branch Lokal pada domain Inventory di branch fix/receiving-central-blss.

## Dokumen sumber yang wajib dibaca
- docs/products/inventory/receiving-central-blss/prd.md — sumber requirement utama.
- docs/products/inventory/receiving-central-blss/reports/audit-report.md — baseline dan bukti temuan.
- docs/products/inventory/receiving-central-blss/implementation-plan.md — urutan teknis dan quality gate.
- docs/products/inventory/receiving-central-blss/rollout.md — UAT, observability, dan rollback.
- docs/products/inventory/README.md — perilaku modul dan Peta kode.
- docs/products/inventory/receiving-simplification/prd.md — kontrak UI/QC existing yang tetap berlaku.
- docs/standards/code-remediation.md — standar arsitektur, keamanan data, dan kualitas teknis.
- docs/standards/ui-consistency.md — standar UI.
- AGENTS.md dan .claude/skills/feature-delivery/SKILL.md — aturan repository dan delivery.

## Hasil akhir yang diharapkan
User aktif dengan permission access employee app goods receipt dapat memproses semua PO BLSS berstatus Authorized/Receiving dari branch ESB mana pun tanpa mapping branch lokal. Destination branch dan location tetap mengikuti PO ESB. Bila mapping aktif ditemukan melalui lookup read-only, local_branch_id boleh terisi; bila tidak ada, receipt valid dengan local_branch_id null. QC, Vendor Compliance, monitoring, idempotency, attachment, data historis, dan scope Back Office tetap aman.

## Scope
- Ubah guard mapping di Employee App Receiving.
- Tambah lookup mapping aktif yang read-only tanpa mengubah behavior method resolver existing.
- Dukung persist dan monitoring context dengan local branch null.
- Pertahankan guard BLSS, status PO, branch/location context, permission, QC, dan idempotency.
- Tambah test regresi lengkap dan perbarui dokumentasi/laporan implementasi.
- Commit, push, dan buat Pull Request sesuai feature-delivery setelah seluruh quality gate lulus.

## Non-scope
- SPN atau company selain BLSS.
- Permission/role baru atau perluasan Back Office.
- Redesign UI, aturan QC baru, migration/backfill, mapping otomatis, branch dummy.
- Dependency, queue, scheduler, env, feature flag, atau perubahan secret.
- Merge PR, approval deployment, transaksi GR production, atau perubahan data production.

## Modul terdampak
- app/Filament/Casual/Pages/GoodsReceiptPage.php
- app/Services/EsbBranchMappingResolver.php
- app/Models/GoodsReceipt.php bila type/komentar perlu diselaraskan
- app/Policies/GoodsReceiptPolicy.php dan GoodsReceiptResource untuk audit/regression; ubah hanya bila bukti test memerlukan
- app/Services/Monitoring/EsbTransactionRecorder.php untuk audit; context branch_id sudah nullable, jadi jangan ubah tanpa bukti
- tests/Feature/GoodsReceiptPageTest.php
- tests/Feature/ReceivingAuthorizationTest.php
- tests/Feature/EsbGoodsReceiptServiceTest.php
- tests/Feature/Monitoring/EsbTransactionRecorderTest.php atau test Page yang paling tepat
- tests/Unit/GoodsReceiptServiceTest.php dan InboundGoodsReceiptQcServiceTest.php
- dokumentasi folder receiving-central-blss dan README Inventory

## Aturan repository
- Jalankan git status; jangan menimpa perubahan user. Mulai dari main terbaru dan branch fix/receiving-central-blss.
- Ikuti konvensi file sibling dan struktur folder existing.
- Gunakan php artisan make:* untuk file baru.
- Gunakan Boost search-docs sebelum mengubah kode; scope query ke package terkait.
- Jangan menambah atau mengubah dependency tanpa persetujuan.
- Gunakan apply_patch untuk edit manual dan vendor/bin/pint --dirty --format agent setelah mengubah PHP.
- Jangan ubah generated data model secara manual.

## Aturan arsitektur
- Pertahankan alur existing; jangan melakukan refactor besar yang tidak diperlukan.
- Tambahkan method resolver read-only dengan nama sesuai konvensi. Prioritas exact esb_comcode + esb_branch_id. Fallback branch code hanya boleh menghasilkan mapping bila tepat satu record aktif cocok; jangan update/create/activate mapping.
- Method resolve() existing dan konsumennya tidak berubah kecuali audit membuktikan kebutuhan dan PRD diamendemen.
- Persist menerima BranchEsbCode nullable atau local branch ID nullable dengan type eksplisit dan tetap berada dalam transaction existing.
- Jangan memindahkan business mutation ke Blade atau Resource.
- Jangan menangkap semua Throwable untuk menyamarkan database/infrastructure failure sebagai mapping kosong.

## Aturan authorization
- Setiap aksi divalidasi server-side melalui Policy/permission; visibility tombol bukan kontrol keamanan.
- access employee app goods receipt wajib untuk load/select/submit.
- access_all_branches tanpa permission Receiving tetap ditolak.
- Permission Receiving berlaku lintas branch hanya untuk Employee App Receiving BLSS.
- view goods receipts dan scope Back Office tetap terpisah; Receiving-only tidak boleh membuka Back Office.
- Tolak company non-BLSS, status PO non-receivable, location bukan milik branch PO terbaru, atau context PO yang tidak lagi konsisten sebelum create ESB.

## Aturan UI
- Ikuti docs/standards/ui-consistency.md dan komponen existing.
- Jangan redesign form atau mengubah default QC.
- Hapus notifikasi Branch PO belum dimapping dari jalur PO BLSS valid.
- Untuk context PO/location yang berubah, gunakan pesan Bahasa Indonesia yang meminta user memuat ulang/memilih ulang; jangan mengalihkan destination diam-diam.

## Aturan integration
- Gunakan HTTP fake/mock pada seluruh test dan prevent stray request bila sesuai pola suite; jangan memanggil ESB sungguhan.
- Pertahankan timeout dan error classification existing.
- Jangan menambah retry untuk create Goods Receipt. Connection failure setelah attempt menghasilkan unknown; rejection/application failure menghasilkan failed sesuai kontrak existing.
- Pertahankan submission_key dan idempotency context. Monitoring tidak boleh menyebabkan write ESB diulang.
- Kirim context monitoring dengan branch_id null bila unmapped, tetapi tetap sertakan company_code, local_model, external_reference PO, dan idempotency_key.
- Hanya qty Accepted yang masuk payload create. Hold-only/Rejected-only tidak memanggil create tetapi tetap menyimpan QC/incident sesuai behavior existing.

## Aturan data safety
- Tidak ada migration yang direncanakan. Verifikasi local_branch_id nullable; berhenti dan amend plan bila tidak sesuai.
- Jangan membuat branch lokal dummy, mapping baru, atau backfill.
- Pertahankan data lama, foreign key, route, permission, status, attachment path, dan kolom legacy.
- Jangan mengubah atau menghapus data production.
- Jangan menulis credential, foto, payload sensitif, atau data pribadi ke log maupun dokumentasi.

## Tahapan implementasi
1. Audit repository aktual, search docs, dan catat baseline. Jalankan targeted suite sebelum perubahan.
2. Tambah/ubah test target: sukses unmapped dan lookup tidak memutasi mapping. Pastikan test target gagal pada behavior lama.
3. Implementasikan lookup read-only dan mapping nullable pada submit/persist/monitoring call site.
4. Tegakkan ulang BLSS, status PO, dan validasi branch/location context terbaru sebelum write.
5. Jalankan regresi QC, Vendor Compliance, attachment cleanup, ED/batch, idempotency, unknown/failed, monitoring, dan Back Office.
6. Perbarui README Inventory menjadi behavior aktual, set status dokumen sesuai bukti, buat reports/implementation-report.md, dan buat phase report bila ada keputusan/deviasi.
7. Hapus implementation-prompt.md setelah implementasi selesai sesuai standar dokumentasi.
8. Jalankan quality gates, commit, push, dan buka PR Bahasa Indonesia. Jangan merge atau deploy.

## Test minimum
- User Receiving tanpa branch access dan tanpa mapping berhasil memproses PO BLSS.
- Dua branch BLSS berbeda berhasil untuk user yang sama.
- Mapping exact aktif mengisi local_branch_id tanpa perubahan record mapping.
- Mapping nonaktif, company lain, tidak ada, atau branch-code ambigu tidak memblokir dan tidak dimutasi.
- User tanpa permission, termasuk all-branch, ditolak.
- Company non-BLSS, status berubah, branch context berubah, location invalid, qty/QC invalid tidak memanggil create.
- Accepted, partial, Hold-only, Rejected-only, Vendor Compliance, foto/cleanup, dan ED/batch tetap benar.
- Duplicate submission tidak menggandakan receipt/GR/incident.
- Connection failure menjadi unknown; application/ESB failure menjadi failed; tidak ada automatic retry create.
- Monitoring branch null tetap merekam context dan failure monitoring tidak mengulang business mutation.
- Viewer all-branch dapat membaca receipt nullable; viewer terbatas tetap dibatasi; Receiving-only tidak mendapat Back Office.
- Seluruh baseline suite lulus.

Perintah minimum:
php artisan test --compact tests/Feature/GoodsReceiptPageTest.php tests/Feature/ReceivingAuthorizationTest.php tests/Feature/EsbGoodsReceiptServiceTest.php tests/Feature/Monitoring/EsbTransactionRecorderTest.php tests/Unit/InboundGoodsReceiptQcServiceTest.php tests/Unit/GoodsReceiptServiceTest.php
vendor/bin/pint --dirty --format agent
bash ops/ci/check-doc-links.sh
bash ops/ci/check-code-map.sh
git diff --check

## Format laporan akhir
1. Branch aktif dan link PR.
2. Ringkasan perubahan per phase.
3. File yang dibuat/diubah.
4. Test yang dijalankan beserta jumlah test/assertion dan hasil CI.
5. Deviasi dari PRD dan alasannya.
6. Risiko, migration/env/permission notes, UAT staging, rollback, dan pekerjaan lanjutan.

## Larangan
Jangan merge, approve deployment, mengirim transaksi ESB production, mengubah data production, atau menghapus data. Jika prompt hanya diberikan untuk review, jangan commit/push/PR. Jika pengguna secara eksplisit meminta implementasi, ikuti feature-delivery untuk commit/push/PR setelah verifikasi, tetapi tetap jangan merge/deploy tanpa instruksi terpisah.
```
