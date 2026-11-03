---
document_id: PURCHASING-EMPLOYEE-SOURCING-REQUEST-ROLLOUT
title: Rollout Runbook — Request Sourcing melalui Employee App
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/employee-sourcing-request/prd.md
  - docs/products/purchasing/employee-sourcing-request/implementation-plan.md
  - docs/operations/ci-cd/release-pipeline.md
supersedes: null
---

# Rollout Runbook — Request Sourcing melalui Employee App

> Jangan menulis credential, token, isi `.env`, data user, atau payload production pada dokumen/evidence rollout.

## 1. Prerequisite

- [ ] PR implementasi sudah di-merge dan CI hijau.
- [ ] PRD/README/report mencerminkan implementasi final.
- [ ] Migration additive direview dan diuji pada database staging yang representatif.
- [ ] Product picker ESB read-only, attachment private, dan conversion lulus test.
- [ ] Permission baru tersinkron tetapi belum diberikan luas ke role production.
- [ ] Purchasing dan R&D menentukan user UAT.

## 2. Environment dan configuration

| Variabel | Wajib | Default | Keterangan |
|---|---|---|---|
| Variabel baru | Tidak | Not applicable | Fitur memakai konfigurasi database, storage private, dan ESB read existing |

Audit Phase 0 wajib memastikan limit Livewire/PHP/reverse proxy mendukung attachment baseline. Jangan menaikkan limit global hanya untuk fitur ini tanpa keputusan terpisah.

## 3. Migration

- Migration menambah tabel `material_sourcing_requests` dan status history dengan FK/index/unique constraint.
- Nullable unique link ke `rnd_product_esb_materials` mencegah conversion ganda.
- Tidak ada backfill atau perubahan data sourcing existing.
- Ukuran tabel baru kosong saat deploy sehingga lock diperkirakan rendah; hasil aktual staging dicatat pada implementation report.
- Rollback production tidak otomatis menjatuhkan tabel setelah fitur dipakai.

## 4. Queue dan scheduler

Not applicable — versi pertama tidak menambah scheduler atau background job wajib. Bila implementasi akhir memakai queued notification existing, deployment mengikuti worker/configuration existing dan tidak menambah queue baru.

## 5. External integration

- Master Product ESB dipakai read-only untuk search dan re-fetch Product Detail.
- Request submit tidak menjalankan mutation ESB.
- Conversion membuat record sourcing lokal existing dan tidak mengubah kontrak downstream.
- UAT memakai product non-sensitif pada environment staging dan HTTP/service configuration staging; jangan memakai credential/payload production dalam evidence.

## 6. Backup

- Pastikan backup database rutin berhasil sebelum deploy.
- Setelah fitur dipakai, request/history/linked sourcing dan prefix attachment private termasuk dalam backup normal.
- Tidak diperlukan export data lama karena migration additive dan tidak ada backfill.

## 7. Deployment steps

1. Pastikan artifact berasal dari commit/PR yang disetujui.
2. Jalankan pipeline deployment existing ke staging.
3. Jalankan migration additive melalui mekanisme pipeline.
4. Jalankan permission sync melalui mekanisme project.
5. Berikan melalui Role & Permission hanya kepada user UAT:
   - requester: `access employee app sourcing request`;
   - Purchasing viewer: `view material sourcing requests`;
   - Purchasing processor: `process material sourcing requests`;
   - converter: permission processor dan `submit material sourcing`.
6. Clear/rebuild cache hanya melalui langkah standar pipeline bila diperlukan.
7. Jalankan smoke test serta UAT staging.
8. Setelah sign-off, grant permission production secara bertahap melalui UI.
9. Observasi antrean, error, dan conversion sebelum grant diperluas.

## 8. Smoke test

- [ ] User tanpa permission tidak melihat tile dan mendapat denial bila membuka URL langsung.
- [ ] User berpermission melihat tile, form, serta history.
- [ ] User tanpa branch tidak dapat submit.
- [ ] Product picker mencari nama/kode, paginate, tidak menampilkan stok, dan dapat ditutup.
- [ ] Submit menghasilkan nomor request/status/history dan tidak memanggil mutation ESB.
- [ ] User lain tidak dapat melihat request requester.
- [ ] Purchasing melihat request pada antrean/badge.
- [ ] Claim, revision, resubmit, reject, dan cancel mengikuti status/permission.
- [ ] Conversion mewajibkan supplier/harga dan menghasilkan satu sourcing berbadge Request User.
- [ ] Double click conversion tidak membuat sourcing kedua.
- [ ] Sourcing hasil conversion muncul di Review R&D dan alur Finance existing.
- [ ] Attachment hanya dapat dibuka user yang terotorisasi.
- [ ] Sourcing manual existing tetap dapat dibuat.

## 9. Data verification

Verifikasi tanpa menyalin data pribadi:

1. request mempunyai nomor unik, requester/branch, snapshot Product Detail, status, dan submitted timestamp;
2. setiap transition mempunyai actor/timestamp serta alasan saat diwajibkan;
3. request converted mempunyai tepat satu linked material;
4. material tertaut tetap origin `standalone`, mempunyai supplier, dan `sourcing_requested_at` saat conversion;
5. request yang belum converted tidak membuat `RndProductEsbMaterial`;
6. attachment path memakai prefix fitur dan object tersedia pada private storage;
7. query index/history/badge tidak memperlihatkan N+1 pada evidence UAT.

## 10. Monitoring

Pantau selama observasi:

- error ESB read/product mismatch;
- attachment upload/download failure;
- authorization denial abnormal;
- jumlah request actionable dan request tertua;
- conversion failure atau unique violation;
- request converted tanpa linked material atau sebaliknya;
- regresi error pada Sourcing Bahan, Review Sourcing, dan Finance.

Jangan menulis signed URL, raw payload, credential, nama lengkap requester, atau attachment content ke log/evidence.

## 11. Rollback trigger

- Request dapat dilihat/mutasi lintas user tanpa permission.
- Product snapshot tidak cocok dengan hasil ESB terbaru.
- Conversion menghasilkan sourcing ganda atau record parsial.
- Submit request menjalankan mutation ESB.
- Attachment menjadi publik atau authorization download gagal.
- Sourcing manual/R&D/Finance existing mengalami regresi material.

## 12. Rollback steps

1. Cabut `access employee app sourcing request` dan permission Back Office baru melalui Role & Permission.
2. Sembunyikan navigation melalui rollback artifact aplikasi.
3. Rollback ke artifact sebelumnya menggunakan pipeline existing.
4. Pertahankan tabel, request, history, attachment, dan linked sourcing.
5. Jangan memutus linked sourcing yang sudah masuk workflow R&D/Finance.
6. Catat request `submitted/in_review/revision_requested` untuk penanganan manual setelah perbaikan.
7. Drop table atau cleanup attachment hanya melalui release terpisah setelah backup/reference audit dan persetujuan eksplisit.

## 13. Post-rollout observation

Durasi observasi ditentukan owner saat release. Selama periode itu, review harian request tertua, conversion failure, attachment failure, authorization denial, dan laporan Purchasing/R&D. Grant permission diperluas hanya setelah hasil staging dan observasi awal production diterima.

## 14. Sign-off

| Peran | Nama | Tanggal | Hasil |
|---|---|---|---|
| Owner Purchasing | | | |
| Perwakilan requester | | | |
| R&D downstream | | | |
| Technical owner | | | |
