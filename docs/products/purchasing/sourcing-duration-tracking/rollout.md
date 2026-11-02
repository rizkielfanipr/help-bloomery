---
document_id: PURCHASING-SOURCING-DURATION-TRACKING-ROLLOUT
title: Rollout Runbook — Pelacakan Durasi Sourcing
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/purchasing/sourcing-duration-tracking/prd.md
  - docs/operations/ci-cd/release-pipeline.md
supersedes: null
---

# Rollout Runbook — Pelacakan Durasi Sourcing

> Migration: `2026_11_02_160003_add_sourcing_completed_at_to_rnd_product_esb_materials_table`. Command: `php artisan rnd:backfill-sourcing-durations` (dry-run) dan `php artisan rnd:backfill-sourcing-durations --apply`. Dokumen ini masih draft sampai UAT staging selesai; jangan menjalankan langkah production hanya berdasarkan dokumen ini.

## 1. Prerequisite

- [ ] PR implementasi sudah di-merge dan CI hijau.
- [ ] Migration additive telah direview.
- [ ] Command backfill lulus test dry-run, apply, idempotency, dan chunking.
- [ ] Backup database mengikuti prosedur release existing.
- [ ] UAT staging disetujui Purchasing, R&D, dan technical owner.

## 2. Environment dan configuration

Not applicable — tidak ada environment variable atau konfigurasi baru.

## 3. Migration

- Menambah satu kolom timestamp nullable `sourcing_completed_at` pada `rnd_product_esb_materials`.
- Tidak ada rename, drop, perubahan status, atau backfill di dalam migration.
- Audit ukuran tabel dan perilaku lock pada staging sebelum production.
- Estimasi durasi dan lock aktual dari staging dicatat pada implementation report.

## 4. Queue dan scheduler

Not applicable — tidak ada Job, queue, scheduler, atau cron baru.

## 5. External integration

Not applicable — fitur tidak membaca atau memutasi ESB maupun layanan eksternal.

## 6. Backup

- Gunakan backup database release existing sebelum migration production.
- Tidak diperlukan backup object storage karena fitur tidak mengubah attachment.
- Jangan menyalin data production ke dokumen atau log rollout.

## 7. Deployment steps

1. Pastikan artifact berasal dari commit PR yang disetujui dan CI hijau.
2. Jalankan migration melalui pipeline deployment existing.
3. Pastikan aplikasi aktif dan index sourcing dapat dibuka dengan record lama bernilai null.
4. Jalankan command backfill dalam mode default dry-run.
5. Catat counter agregat: `scanned`, `eligible` (updated candidate), `skipped_ambiguous`, `invalid_chronology`, `unchanged`, `failed`, serta rincian `requested_at_candidates`, `requested_at_ambiguous`, `completed_at_candidates`, dan `completed_at_ambiguous`. `updated` selalu 0 pada dry-run.
6. Technical owner mereview hasil dry-run; jangan lanjut bila jumlah atau kategori skip tidak masuk akal.
7. Jalankan command dengan opsi apply eksplisit.
8. Jalankan dry-run sekali lagi dan pastikan `eligible` menjadi nol sebagai bukti idempotency.
9. Lakukan smoke test dan data verification.

## 8. Smoke test

- [ ] Purchasing dapat membuka Sourcing Bahan.
- [ ] R&D dapat membuka Review Sourcing.
- [ ] Index menampilkan Masuk Sourcing, Selesai Sourcing, dan Durasi.
- [ ] Record aktif menampilkan suffix **berjalan**.
- [ ] Record final menampilkan durasi tetap.
- [ ] Record ambigu menampilkan **Tidak Tersedia**.
- [ ] Modal detail menampilkan ringkasan dan daftar supplier existing.
- [ ] Filter tanggal serta kondisi Berjalan/Selesai bekerja.
- [ ] Aksi tambah supplier, serahkan sampel, terima, evaluasi, resample, dan reopen tetap mengikuti permission existing.

## 9. Data verification

Verifikasi agregat tanpa menyalin identitas record:

1. Jumlah sourcing aktif dengan `sourcing_completed_at` tidak null harus nol, kecuali ditemukan inkonsistensi yang wajib diinvestigasi.
2. Jumlah sourcing terminal dengan `sourcing_completed_at` null harus sama dengan `completed_at_ambiguous` ditambah record final yang sumbernya gagal kronologi pada `invalid_chronology`.
3. Tidak boleh ada `sourcing_completed_at < sourcing_requested_at` baru; backfill tidak menulisnya dan melaporkan nilai lama pada `invalid_chronology`.
4. WIP `not_required` tidak boleh memperoleh timestamp sourcing baru.
5. `workflow_completed_at`, `updated_at`, status, supplier, pricing, dan jumlah audit lama tidak berubah akibat backfill; backfill tidak menulis audit event.

## 10. Monitoring

Pantau selama periode observasi release existing:

- error/exception pada Resource Sourcing Bahan dan Review Sourcing;
- kegagalan cast atau formatter timestamp;
- latency index sebelum/sesudah;
- hasil counter backfill dan invalid chronology;
- laporan Purchasing/R&D mengenai status ongoing/final yang tidak sesuai.

Ambang rollback aplikasi: halaman sourcing gagal dibuka, query time meningkat material, atau transisi status gagal menyimpan karena perubahan timestamp.

## 11. Rollback trigger

- Index/detail sourcing menghasilkan error bagi pengguna berizin.
- Mutation sourcing existing gagal atau menghasilkan status/timestamp tidak konsisten.
- Backfill mencoba mengubah record di luar kriteria PRD.
- Latency/query plan memburuk secara signifikan berdasarkan monitoring release.

## 12. Rollback steps

1. Hentikan command backfill bila masih berjalan.
2. Rollback aplikasi ke artifact sebelumnya melalui pipeline existing.
3. Biarkan kolom nullable dan nilai yang sudah terisi agar histori tidak hilang.
4. Investigasi dengan counter/audit tanpa bulk delete atau bulk null.
5. Jika column drop benar-benar diperlukan, buat migration terpisah setelah seluruh kode pembaca/penulis dilepas dan backup diverifikasi.

## 13. Post-rollout observation

- Observasi mengikuti jendela release existing dan minimal mencakup satu proses ongoing serta satu keputusan final.
- Verifikasi resample dan reopen di staging; jangan membuat keputusan sourcing palsu pada production hanya untuk smoke test.
- Catat hasil, deviasi, dan record ambigu pada implementation report tanpa identitas sensitif.

## 14. Sign-off

| Peran | Nama | Tanggal | Hasil |
|---|---|---|---|
| Owner bisnis Purchasing | | | |
| Perwakilan R&D | | | |
| Technical owner | | | |
