---
document_id: BRAND-MARKETING-DESIGN-MASTER-LIBRARY-ROLLOUT
title: Rollout Runbook — Master Design Library Berbasis Brand
status: draft
version: 1.0
owner: Brand Marketing
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/brand-marketing/design-master-library/prd.md
  - docs/operations/ci-cd/release-pipeline.md
supersedes: null
---

# Rollout Runbook — Master Design Library Berbasis Brand

> Migration: `2026_11_03_081707_create_marketing_design_tables`. Batas upload final: 12 MB per file. Dokumen ini masih draft sampai UAT staging selesai dan tidak mengotorisasi production deployment.

## 1. Prerequisite

- [ ] PR implementasi merged dan CI hijau.
- [ ] Migration additive serta rollback direview.
- [ ] Brand Marketing menunjuk reviewer dan publisher. Limit dan format file sudah dikunci pada PRD §18.2.
- [ ] Batas PHP (`upload_max_filesize`, `post_max_size`) dan reverse proxy staging serta production diverifikasi minimal 12 MB.
- [ ] Backup database mengikuti prosedur release existing.
- [ ] Storage staging mempunyai ruang dan private temporary URL berfungsi.
- [ ] `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=local` terpasang seperti `.env.example`, karena checksum dihitung dari file sementara lokal.
- [ ] UAT Designer dan Purchasing dijadwalkan.

## 2. Environment dan configuration

Tidak ada environment variable baru. File memakai Storage disk `b2` existing. Konfigurasi Livewire tidak diubah, sehingga batas upload global tetap 12 MB.

## 3. Migration

- Tabel baru: `marketing_design_bundles`, `marketing_design_bundle_versions`, `marketing_design_items`, `marketing_design_files`, dan `marketing_design_audit_logs`.
- Seluruh perubahan additive; tidak mengubah tabel Project R&D/fulfillment.
- Tidak ada backfill atau pemindahan binary dalam migration.
- Audit lock/duration migration pada staging sebelum production.

## 4. Queue dan scheduler

Not applicable — versi pertama tidak merencanakan Job, queue, scheduler, atau command cleanup baru. Bila checksum/upload besar kemudian dipindah ke queue, rollout harus direvisi sebelum implementasi tersebut diterima.

## 5. External integration

Object storage melalui disk Laravel existing adalah satu-satunya boundary. Tidak ada ESB, Figma, Adobe, public CDN, atau API eksternal.

## 6. Backup

- Backup database sebelum migration.
- Pertahankan backup object storage sesuai kebijakan existing; fitur tidak memodifikasi object lama.
- Jangan menyalin file desain atau signed URL ke log/dokumentasi rollout.

## 7. Deployment steps

1. Verifikasi artifact/commit dan CI.
2. Deploy migration additive serta aplikasi melalui pipeline existing.
3. Jalankan `permissions:sync` melalui pipeline existing.
4. Berikan permission melalui UI Role & Permission:
   - Designer: `view`, `create`, dan `edit design master bundles`, `upload design master files`, serta `download design master production files` dan `download design master source files` agar dapat memeriksa file;
   - Reviewer: `view design master bundles`, `review design master bundles`, dan permission unduh yang diperlukan;
   - Publisher: `view design master bundles`, `publish design master bundles`, `archive design master bundles`, dan permission unduh yang diperlukan;
   - Purchasing: `view design master bundles` dan `download design master production files` saja.
5. Jangan menjalankan role seeder production.
6. Buat bundle/file uji nonproduction pada staging dan jalankan smoke test.
7. Setelah UAT disetujui, lakukan production rollout sesuai approval user.

## 8. Smoke test

- [ ] Menu hanya tampil untuk user dengan permission view.
- [ ] Bundle wajib memilih Brand dan code unik per Brand.
- [ ] Draft dapat disimpan tanpa publish readiness.
- [ ] Submit/revision/approve/publish berjalan sesuai Policy.
- [ ] Revisi baru men-supersede versi lama dan versi lama tetap read-only.
- [ ] Preview dapat dilihat dengan temporary URL.
- [ ] Purchasing dapat download production, tidak dapat source/mutation.
- [ ] User tanpa permission ditolak pada URL/action/download langsung.
- [ ] Archive tidak menghapus file/history.
- [ ] Project R&D upload dan Material Marketing fulfillment existing tetap berjalan.

## 9. Data verification

Verifikasi agregat tanpa mencatat nama/file sensitif:

1. Tidak ada bundle tanpa Brand.
2. Tidak ada duplicate normalized code dalam Brand.
3. Tidak ada bundle dengan lebih dari satu published version aktif.
4. Tidak ada physical item published tanpa production file/spec wajib.
5. Tidak ada stored public/signed URL pada tabel file.
6. Jumlah object uji sesuai record file dan rollback cleanup test.
7. Tabel existing tidak berubah jumlah/status akibat rollout.

## 10. Monitoring

Pantau:

- error/latency upload dan download;
- authorization denial tidak wajar;
- failed publish/concurrency;
- object storage error dan kemungkinan orphan;
- query count/latency index/detail;
- laporan Designer/Purchasing tentang versi/file salah.

Signed URL, credential, dan nama file sensitif tidak dicatat pada log monitoring.

## 11. Rollback trigger

- File source dapat diakses Purchasing/user tanpa permission.
- Published version dapat diedit atau terdapat dua published version aktif.
- Upload meninggalkan data/object tidak konsisten secara berulang.
- Menu/index/detail gagal digunakan oleh role yang benar.
- Workflow Project R&D/fulfillment existing mengalami regresi.

## 12. Rollback steps

1. Cabut permission view/mutation/download fitur baru melalui Role & Permission.
2. Rollback application artifact.
3. Pertahankan tabel dan object baru; jangan menjalankan bulk delete/null.
4. Investigasi audit/storage references.
5. Schema/object cleanup hanya melalui release terpisah setelah backup dan reference audit.

## 13. Post-rollout observation

- Observasi minimal mencakup create draft, review/revision, publish, revision kedua, archive, Designer download, dan Purchasing production download.
- Catat hasil dan limit file aktual pada implementation report.
- Integrasi Project R&D/order tidak dimulai otomatis setelah library live; memerlukan PRD dan approval terpisah.

## 14. Sign-off

| Peran | Nama | Tanggal | Hasil |
|---|---|---|---|
| Owner Brand Marketing | | | |
| Perwakilan Purchasing | | | |
| Technical owner | | | |
