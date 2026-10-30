---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-ROLLOUT
title: Rollout Runbook — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal
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
  - docs/operations/ci-cd/release-pipeline.md
supersedes: null
---

# Rollout Runbook — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal

> Jangan menulis credential, token, isi `.env`, payload PO/GR production, atau data pribadi ke dokumen maupun laporan UAT.

## 1. Prerequisite

- [ ] PR implementation sudah di-merge dan CI hijau.
- [ ] Implementation report mencatat seluruh test aktual dan tidak ada deviasi yang belum disetujui.
- [ ] PO BLSS khusus UAT tersedia pada environment staging dan branch-nya tidak memiliki mapping lokal.
- [ ] User UAT memiliki `access employee app goods receipt`, tetapi tidak bergantung pada akses branch PO.
- [ ] Owner memahami bahwa production GR nyata tidak boleh dikirim tanpa persetujuan eksplisit.

## 2. Environment dan configuration

| Variabel | Wajib | Default | Keterangan |
|---|---|---|---|
| Tidak ada variabel baru | Tidak | Not applicable | Gunakan konfigurasi ESB dan storage existing pada environment terkait |

Tidak ada perubahan permission, role, mapping Master Branch, feature flag, atau secret untuk remediasi ini.

## 3. Migration

Not applicable — tidak ada migration atau backfill. Verifikasi sebelum deploy bahwa `goods_receipts.local_branch_id` nullable pada environment target melalui mekanisme schema read-only yang tersedia.

## 4. Queue dan scheduler

Not applicable — tidak ada job, queue, command, atau jadwal baru.

## 5. External integration

Integrasi terdampak adalah ESB Core `BLSS`: daftar PO, detail PO, location, dan create Goods Receipt. UAT staging hanya memakai data uji yang disetujui. Tidak ada perluasan ke SPN/company lain. Jangan menambah automatic retry create GR. Outcome koneksi `unknown` harus direkonsiliasi sebelum retry.

## 6. Backup

Tidak ada backup khusus karena tidak ada migration/backfill. Gunakan backup rutin environment. Sebelum production, catat release/commit dan pastikan prosedur rollback aplikasi tersedia. Jangan menyalin payload transaksi atau foto ke dokumen rollout.

## 7. Deployment steps

1. Pastikan CI PR dan artifact release sesuai commit yang disetujui.
2. Deploy ke staging melalui pipeline repository.
3. Jalankan smoke test §8 dan data verification §9.
4. Observasi staging sesuai §13 dan catat hasil pada implementation report/phase report.
5. Minta persetujuan pengguna sebelum production deployment atau transaksi GR production.
6. Bila disetujui, deploy artifact yang sama ke production melalui environment approval existing.
7. Lakukan smoke test non-transaksional terlebih dahulu; transaksi GR nyata hanya dengan PO dan waktu yang disetujui owner.

## 8. Smoke test

- [ ] User tanpa permission Receiving tetap tidak dapat membuka tile/submit.
- [ ] User Receiving melihat daftar PO Authorized/Receiving dari BLSS.
- [ ] Company selain BLSS tidak muncul dan tidak dapat dipilih melalui manipulasi request.
- [ ] PO BLSS dari branch unmapped dapat dibuka dan lokasi ESB tampil.
- [ ] Submit UAT membuat receipt dengan `local_branch_id = null` dan identitas branch ESB lengkap.
- [ ] Nomor/status GR, QC item, expiry/batch, attachment, dan monitoring sesuai input UAT.
- [ ] PO mapped existing masih mengisi metadata local branch tanpa mengubah mapping.
- [ ] Back Office tetap read-only dan aksesnya tidak otomatis diberikan oleh permission Receiving.

## 9. Data verification

Gunakan query/read-only UI untuk memeriksa satu receipt UAT berdasarkan reference number fiktif yang disetujui:

- company `BLSS`;
- ESB branch ID/nama dan location sesuai PO;
- `local_branch_id` null untuk PO unmapped;
- status, submission key, nomor GR, submitter, dan timestamps tersedia;
- total item Accepted/Hold/Rejected konsisten;
- monitoring parent/attempt mengacu pada receipt/PO yang sama;
- mapping Master Branch tetap tidak berubah.

Jangan menyalin supplier, foto, dokumen, atau payload sensitif ke laporan.

## 10. Monitoring

Pantau selama observation window:

- receipt `failed` dan `unknown`;
- error create Goods Receipt BLSS;
- duplicate reference/submission key;
- monitoring attempt yang gagal disimpan;
- Vendor Compliance incident yang tidak sesuai input;
- receipt dengan local branch null yang tidak terlihat oleh viewer yang seharusnya berwenang.

Ambang rollback: regression permission, create ke company/branch salah, indikasi GR ganda, payload QC salah, atau kenaikan error Receiving yang konsisten setelah release.

## 11. Rollback trigger

- PO/company/location dikirim ke destination yang salah.
- User tanpa permission dapat submit.
- Submit menghasilkan GR ganda atau retry otomatis setelah unknown.
- Receipt/QC/incident tidak konsisten atau tidak dapat direkonsiliasi.
- Error Receiving meningkat dan memblokir operasi normal.

## 12. Rollback steps

1. Hentikan UAT/transaksi baru dan catat reference/submission key yang terdampak.
2. Rekonsiliasi setiap outcome `unknown` dengan ESB sebelum retry atau rollback operasional.
3. Rollback artifact aplikasi ke release sebelumnya melalui pipeline.
4. Jangan menghapus receipt, item, expiry, attachment, incident, monitoring attempt, atau GR ESB.
5. Verifikasi PO mapped masih dapat diproses oleh release lama dan PO unmapped kembali menampilkan guard lama.
6. Buat laporan insiden/deviasi sebelum rollout ulang.

## 13. Post-rollout observation

Observasi minimal satu siklus operasional Receiving pada staging. Untuk production, durasi ditentukan owner berdasarkan volume transaksi hari itu. Tetap pantau failure/unknown dan receipt nullable sampai tidak ada anomali pada siklus yang disepakati.

## 14. Sign-off

| Peran | Nama | Tanggal | Hasil |
|---|---|---|---|
| Owner bisnis |  |  |  |
| Technical owner |  |  |  |
