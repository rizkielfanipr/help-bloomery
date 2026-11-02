---
document_id: RND-PROJECT-OVERVIEW-RICH-TEXT-ROLLOUT
title: Rollout Runbook — Project Overview Rich Text
status: draft
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/project-overview-rich-text/prd.md
  - docs/operations/ci-cd/release-pipeline.md
supersedes: null
---

# Rollout Runbook — Project Overview Rich Text

> Jangan menulis credential, token, isi `.env`, signed URL, HTML production, atau data pribadi pada bukti rollout.

## 1. Prerequisite

- [ ] PR implementasi sudah di-merge dan seluruh CI hijau.
- [ ] PRD/README/report berstatus sesuai kondisi implementasi sebenarnya.
- [ ] Schema `rnd_projects` telah diverifikasi sebelum migration.
- [ ] Migration additive direview dan backup database sesuai prosedur release tersedia.
- [ ] Disk private B2/R2 existing sehat tanpa perubahan credential/config.
- [ ] Test Project, BOM rich-text, attachment, permission, dan security lulus.
- [ ] UAT staging disetujui owner bisnis/teknis.

## 2. Environment dan configuration

Tidak ada environment variable atau konfigurasi baru. Fitur menggunakan disk `b2` existing dan konfigurasi upload/Livewire existing. Jangan mengubah credential untuk rollout ini.

## 3. Migration

- Satu migration additive: nullable LONGTEXT `rnd_projects.overview_html`.
- Tidak ada backfill atau update row massal.
- Perkiraan operasi harus diverifikasi pada staging/schema aktual; penambahan nullable column pada tabel Project diperkirakan singkat tetapi lock/engine tetap harus diamati.
- Jangan menjalankan `down()` di production setelah Overview berisi data tanpa backup/export dan approval eksplisit.

## 4. Queue dan scheduler

Not applicable — tidak ada job, queue, worker, command, atau schedule baru.

## 5. External integration

Tidak ada integrasi baru. Private B2/R2 existing digunakan untuk RichEditor image attachment. UAT hanya memakai file uji non-sensitif.

## 6. Backup

- Gunakan backup database pre-deploy sesuai pipeline production existing.
- Tidak ada object storage backup khusus yang diwajibkan sebelum enable karena fitur belum memiliki file lama.
- Setelah production mulai menyimpan Overview, rollback schema harus mempertahankan column atau mendahului dengan export/backup data dan daftar referenced paths.

## 7. Deployment steps

1. Pastikan CI/approval environment sesuai release pipeline.
2. Deploy artifact aplikasi melalui pipeline existing.
3. Jalankan migration melalui langkah deploy existing; jangan menjalankan SQL manual.
4. Verifikasi application health dan tidak ada migration error.
5. Jalankan smoke test Viewer dan Editor pada satu Project uji yang sah.
6. Verifikasi upload/render/delete gambar private serta Informasi BOM existing.
7. Observasi log/monitoring sebelum sign-off.

## 8. Smoke test

- [ ] Project lama tanpa Overview dapat dibuka dan description tetap tampil.
- [ ] Release Product, Premix, dan Partnership menampilkan section Overview.
- [ ] Viewer melihat content/empty state tetapi tidak tombol edit.
- [ ] Editor membuka modal, menyimpan heading/list/link, dan melihat hasil.
- [ ] JPEG/PNG/WebP uji dapat diunggah dan dirender private.
- [ ] File invalid/oversize ditolak dengan pesan aman.
- [ ] Hapus gambar dari content membersihkan orphan tanpa menghapus gambar lain.
- [ ] Payload script/event/javascript tidak dirender.
- [ ] List/search Project masih memakai description plain text.
- [ ] Informasi Tambahan BOM masih dapat dibuka, disimpan, dan dirender.

## 9. Data verification

- Kolom `overview_html` ada dan nullable.
- Project yang belum diedit tetap null; tidak ada backfill tak terduga.
- Konten uji tersimpan dalam sanitized HTML tanpa script/event/unsafe scheme/base64.
- Attachment uji berada pada prefix Project yang benar.
- Archive/restore Project uji tidak menghapus Overview.
- Jangan salin HTML/path production ke tiket atau dokumen.

## 10. Monitoring

Pantau selama rollout:

- error rate detail Project/ViewProject;
- validation/authorization failures abnormal;
- filesystem B2 upload/read/delete failure;
- 5xx pada save Overview;
- payload/render latency halaman detail;
- error/regression Informasi BOM;
- orphan cleanup failure.

Tidak ada ambang numerik baru yang ditetapkan tanpa baseline; gunakan perbandingan baseline aplikasi dan alert existing.

## 11. Rollback trigger

- XSS/unsafe content dapat tersimpan atau dirender.
- Attachment Project lain dapat dibaca/ditautkan.
- Save menghasilkan data loss atau orphan signifikan.
- Detail Project/BOM mengalami 5xx berulang.
- Description/list/search atau Informasi BOM regresi.
- Migration gagal/lock berdampak pada aplikasi.

## 12. Rollback steps

1. Hentikan approval rollout dan catat waktu/commit tanpa data sensitif.
2. Rollback/redeploy application code ke release sebelumnya melalui pipeline.
3. Biarkan nullable `overview_html` column dan data tetap ada.
4. Jangan menjalankan migration down production setelah data tersimpan.
5. Jika isu berasal dari shared sanitizer, verifikasi Informasi BOM kembali menggunakan behavior release sebelumnya.
6. Verifikasi list/detail/create/edit Project existing dan application health.
7. Identifikasi file baru orphan melalui evidence terkontrol; jangan bulk-delete prefix luas.
8. Buat forward fix dan ulangi staging UAT sebelum rollout baru.

## 13. Post-rollout observation

Observasi minimum satu siklus penggunaan bisnis setelah deployment: pembuatan/edit Overview, image add/remove, Project archive/restore, serta satu edit Informasi BOM. Catat error/latency/storage outcome dan konfirmasi tidak ada unsafe markup atau orphan sebelum sign-off.

## 14. Sign-off

| Peran | Nama | Tanggal | Hasil |
|---|---|---|---|
| Owner bisnis | | | |
| Technical owner | | | |
