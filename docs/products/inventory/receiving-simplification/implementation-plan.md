---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-PLAN
title: Rencana Implementasi — Receiving Terpusat BLSS
status: in-review
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-30
updated_at: 2026-10-30
target_release: TBD
related_documents:
  - docs/products/inventory/receiving-simplification/prd.md
  - docs/products/inventory/README.md
supersedes: null
---

# Rencana Implementasi — Receiving Terpusat BLSS

## 1. Acuan dan batas pekerjaan

Requirement canonical: [PRD §22](prd.md#22-remediasi-receiving-inventory-pusat--blss). Dokumen ini mengatur urutan pelaksanaan, bukan requirement baru. Tahap sekarang hanya dokumentasi; aplikasi belum diperbaiki.

## 2. Phase 0 — Baseline dan audit

1. Baca AGENTS.md, skill feature-delivery, README Inventory, PRD §22 dan standar dokumentasi/remediasi.
2. Mulai dari main terbaru dengan working tree bersih dan branch implementasi tersendiri. Jika branch dokumentasi belum merge, tentukan basis secara eksplisit agar perubahan docs tidak hilang/terduplikasi.
3. Jalankan baseline GoodsReceiptPageTest dan ReceivingAuthorizationTest. Baca service/unit tests sebelum menentukan regresi minimum.
4. Audit `submit`, `persist`, `payload`, `selectPurchaseOrder`; model GoodsReceipt; policy/resource; service monitoring dan schema nullable melalui schema tool/data model (migration hanya sebagai bukti historis bila diperlukan).
5. Catat pemakaian branch lokal di QC, incident dan monitoring, serta guard sourceCompanyCode dan stale PO context. Gunakan data fiktif, tanpa akses transaksi production.

Gate: peta dependency dan AC dipahami; tidak ada kebutuhan migration tersembunyi.

## 3. Phase 1 — Test regresi dan perubahan inti

1. Ubah test penolakan unmapped menjadi skenario sukses; jalankan untuk membuktikan kegagalan sebelum perbaikan.
2. Pertahankan resolver sebagai lookup opsional. Hapus early-return “Branch PO belum dimapping” di Receiving.
3. Ubah kontrak persist menjadi menerima mapping nullable dan simpan local_branch_id nullable. Sesuaikan context monitoring menggunakan hasil lokal nullable.
4. Tegakkan BLSS pada submit sebelum request ESB; jangan bergantung hanya pada selectPurchaseOrder atau hidden field.
5. Validasi ulang konteks detail PO terbaru dan lokasi. Bila company/branch berubah selama form dibuka, tolak dengan pesan muat ulang tanpa mengirim GR.
6. Jangan mengubah resolver global, membuat branch dummy, atau menelan database exception sebagai hasil null.

Gate: test unmapped/mapped/inactive mapping, company guard dan invalid context lulus.

## 4. Phase 2 — Regresi proses turunannya

Uji Accepted/partial/Hold-only/Rejected-only, incident/de-merit, foto/ED, cleanup storage, status failed/unknown, idempotency, monitoring null, dan policy/list/detail Back Office. Uji cabang yang berbeda dari branch user. Pastikan hanya Accepted dikirim ke ESB dan tanpa Accepted tidak ada create.

Gate: seluruh AC PRD §22.6 lulus, tanpa pelebaran akses viewer.

## 5. Phase 3 — Quality gate dan dokumentasi

Jalankan minimal:

```bash
php artisan test --compact tests/Feature/GoodsReceiptPageTest.php tests/Feature/ReceivingAuthorizationTest.php tests/Feature/EsbGoodsReceiptServiceTest.php tests/Unit/InboundGoodsReceiptQcServiceTest.php tests/Unit/GoodsReceiptServiceTest.php
php artisan test --compact tests/Feature/Monitoring/EsbTransactionRecorderTest.php
vendor/bin/pint --dirty --format agent
bash ops/ci/check-doc-links.sh
bash ops/ci/check-code-map.sh
git diff --check
```

Sesuaikan suite tambahan berdasarkan file yang benar-benar disentuh. Full Feature suite hanya memakai pembagian CI repository, bukan satu proses besar. Perbaiki kegagalan terkait dan laporkan baseline failure yang tidak terkait. Ulang test setelah formatting bila kode berubah.

Perbarui README Inventory menjadi perilaku aktual BLSS tanpa mapping hanya setelah implementasi. Tambahkan laporan di `reports/implementation-report.md` dengan hasil aktual; tautkan dari PRD. Status dokumen mengikuti bukti: jangan klaim implemented/production sebelum rollout terverifikasi. Hapus prompt ketika selesai sesuai standar repository. Commit, push, dan buat PR Bahasa Indonesia mengikuti template. Jangan merge atau deploy tanpa instruksi pengguna tersendiri.

## 6. UAT, rollout, dan rollback

Mengikuti PRD §22.11. Bukti minimum: user Receiving tanpa branch access dapat menerima PO BLSS unmapped; GR/QC/monitoring menyimpan identitas ESB dan local_branch_id null; viewer terbatas tetap dibatasi; user tanpa permission ditolak. Gunakan environment uji dan transaksi uji yang disetujui. Rollback tidak boleh menghapus transaksi atau membuat pengiriman ulang otomatis.
