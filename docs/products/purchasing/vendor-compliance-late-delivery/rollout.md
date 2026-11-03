---
document_id: PURCHASING-VENDOR-COMPLIANCE-LATE-DELIVERY-ROLLOUT
title: Rollout Runbook — Vendor Compliance Keterlambatan Delivery PO
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/vendor-compliance-late-delivery/prd.md
  - docs/products/purchasing/vendor-compliance-late-delivery/implementation-plan.md
  - docs/operations/ci-cd/release-pipeline.md
supersedes: null
---

# Rollout Runbook — Vendor Compliance Keterlambatan Delivery PO

> Jangan menulis credential, token, isi `.env`, payload production, atau data vendor/transaksi nyata pada runbook dan bukti UAT.

## 1. Prerequisite

- [ ] PR implementasi sudah di-merge dan CI hijau.
- [ ] PRD §26 sudah ditutup, matrix scoring disetujui Purchasing, dan versi scoring tercatat.
- [ ] Migration rehearsal lulus dengan data incident legacy, header-level late, dan multiple incident per item.
- [ ] Targeted Receiving, PO Susulan, Vendor Compliance, authorization, serta concurrency tests lulus.
- [ ] Backup database terbaru tervalidasi sesuai prosedur operasional.
- [ ] Purchasing dan Inventory menentukan window UAT serta PIC sign-off.
- [ ] Tidak ada historical backfill atau auto repair yang ikut dalam release.

## 2. Environment dan configuration

| Variabel | Wajib | Default | Keterangan |
|---|---|---|---|
| Feature flag/config assessment | Tidak — hanya bila pola feature flag existing dipakai | Off sampai migration selesai | Nama final mengikuti konfigurasi existing; jangan membuat env baru tanpa kebutuhan |
| Timezone aplikasi | Ya | `Asia/Jakarta` sesuai konfigurasi aplikasi | Diverifikasi, tidak diubah khusus fitur |
| Credential ESB existing | Ya | Existing | Tidak ada credential/endpoint baru |
| Queue notification existing | Sesuai implementasi | Existing | Tidak ada queue baru bila notifikasi synchronous-after-commit memadai |

Tidak ada environment variable baru yang wajib pada planning ini. Bila implementasi memerlukan config matrix scoring, gunakan config versioned dalam repository dan dokumentasikan perubahan sebelum rollout.

## 3. Migration

Urutan yang diharapkan:

1. Tambah field assessment nullable pada `goods_receipts`.
2. Tambah field source/liability/date/scoring nullable/default-compatible pada `vendor_compliance_incidents`.
3. Backfill default liability incident existing tanpa mengubah poin/status.
4. Lepas unique item FK dan ubah `goods_receipt_item_id` nullable dengan foreign key tetap aman.
5. Tambah unique/index source key dan index query baru.

Sebelum production, catat durasi migration staging, lock yang terjadi, versi database, serta nama index/FK aktual. Migration tidak boleh mengambil data ESB atau menghitung histori.

## 4. Queue dan scheduler

- Tidak ada scheduler baru pada v1.
- Notifikasi internal menggunakan mekanisme existing dan dikirim setelah transaction commit.
- Reconciliation command/report, bila dibuat, read-only secara default dan dijalankan manual saat observation; tidak dijadwalkan tanpa requirement baru.

## 5. External integration

- ESB PO dan GR existing tetap digunakan.
- Tidak ada endpoint, token, webhook, mutation, atau payload baru.
- UAT wajib memakai HTTP fake/local test atau environment staging yang disetujui; jangan membuat transaksi vendor nyata untuk pengujian.
- Verifikasi bahwa `requiredDate` staging mencakup ISO datetime, null/invalid handling, dan tanggal boundary tanpa mencetak raw payload ke bukti.

## 6. Backup

- Backup tabel `goods_receipts`, `goods_receipt_items`, dan `vendor_compliance_incidents` beserta schema/index sebelum migration.
- Verifikasi restore procedure sesuai runbook backup existing.
- Jangan menyalin dump berisi data transaksi/vendor ke repository atau dokumen.

## 7. Deployment steps

1. Pastikan backup dan CI valid.
2. Deploy artifact sesuai release pipeline dengan assessment tetap off bila feature flag digunakan.
3. Jalankan migration dan catat durasi/hasil.
4. Jalankan permission sync existing; tidak ada grant role baru pada rekomendasi v1.
5. Aktifkan assessment setelah schema dan application health valid bila feature flag digunakan.
6. Jalankan smoke test Receiving on-time, late, dan PO Susulan menggunakan data staging fiktif.
7. Jalankan smoke test Purchasing pending → confirmed dan pending → waived.
8. Jalankan reconciliation read-only dan periksa tidak ada duplicate source key/gap pada data uji.
9. Mulai observation window; jangan menjalankan historical backfill.

## 8. Smoke test

- [ ] Receiving PO normal sebelum required date tersimpan tanpa kandidat.
- [ ] Receiving tepat pada required date tersimpan tanpa kandidat.
- [ ] Receiving setelah required date menampilkan warning namun tetap memproses GR.
- [ ] Kandidat Late Delivery tepat satu, pending, dan poin 0.
- [ ] Required date kosong/invalid menjadi Tidak Dapat Dinilai tanpa blok.
- [ ] PO Susulan tidak membuat Late Delivery.
- [ ] Rejection item tetap membuat insiden QC dan dapat coexist dengan Late Delivery.
- [ ] Partial GR PO yang sama tidak membuat kandidat/poin kedua dan memperbarui maximum delay.
- [ ] Purchasing unauthorized tidak dapat confirm/waive.
- [ ] Confirm menghasilkan severity/poin versi final dan audit reviewer.
- [ ] Waive mewajibkan alasan serta mempertahankan poin 0.
- [ ] Follow-up status existing tetap dapat digunakan.
- [ ] Index/filter/detail/navigation badge tidak error pada item null.

## 9. Data verification

Verifikasi agregat tanpa menyalin data sensitif:

- jumlah Goods Receipt baru per assessment status;
- jumlah receipt `late` tanpa incident source key;
- jumlah source key duplikat;
- jumlah incident pending dengan poin bukan 0;
- jumlah waived dengan poin bukan 0 atau tanpa alasan/reviewer;
- jumlah confirmed tanpa scoring rule version;
- jumlah PO Susulan dengan category `late_delivery` (harus 0);
- jumlah incident QC legacy yang kehilangan item/relation (harus 0).

Query final disiapkan pada implementation report atau runbook operasi tanpa payload/nama vendor nyata.

## 10. Monitoring

Pantau sekurangnya satu siklus operasional Receiving yang disepakati:

- exception rate pada submit Receiving dan Vendor Compliance actions;
- duration/error ESB re-fetch serta GR send dibanding baseline;
- candidate creation/upsert conflict;
- gap/duplicate reconciliation;
- notification failure;
- jumlah pending dan umur pending;
- laporan user mengenai warning palsu, required date, atau PO Susulan.

Ambang rollback/hotfix: submit Receiving gagal karena assessment, PO Susulan membuat candidate, duplicate source key/poin, incident legacy tidak dapat dibuka, atau poin aktif sebelum confirmation.

## 11. Rollback trigger

- Assessment menyebabkan Receiving/GR gagal atau memperlambat secara material.
- PO Susulan atau GR on-time menghasilkan kandidat.
- Duplicate incident/poin ditemukan untuk satu company + PO.
- Incident QC existing tidak dapat dibuat/dibuka/ditindaklanjuti.
- Authorization memungkinkan user tanpa permission memutus liability.
- Migration/data integrity failure ditemukan.

## 12. Rollback steps

1. Nonaktifkan feature flag/config assessment bila tersedia.
2. Hentikan confirm/waive baru melalui konfigurasi atau deploy application sebelumnya.
3. Rollback application artifact sesuai pipeline.
4. Pertahankan kolom/index/schema additive dan data incident yang sudah tercipta.
5. Jangan mengembalikan unique `goods_receipt_item_id`, drop kolom, menghapus kandidat, atau mengubah poin secara otomatis.
6. Catat incident/reconciliation yang perlu review manual Purchasing.
7. Siapkan hotfix pada branch baru; deployment ulang memerlukan approval normal.

## 13. Post-rollout observation

- Observation minimal: satu siklus operasional penuh atau durasi yang disepakati Purchasing dan Inventory.
- Review harian selama observation: gap/duplicate, pending, warning false positive, error Receiving, dan audit scoring.
- Setelah stabil, ubah PRD/README/report mengikuti kondisi production dan hapus implementation prompt sesuai workflow.
- Historical backfill tetap menjadi project terpisah dengan dry-run dan approval.

## 14. Sign-off

| Peran | Nama | Tanggal | Hasil |
|---|---|---|---|
| Owner bisnis Purchasing | | | |
| Perwakilan Inventory | | | |
| Technical owner | | | |
