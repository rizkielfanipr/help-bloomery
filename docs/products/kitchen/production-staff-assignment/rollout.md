---
document_id: KITCHEN-PRODUCTION-STAFF-ASSIGNMENT-ROLLOUT
title: Rollout Runbook — Assignment Staff Produksi per Detail Production Order
status: draft
version: 1.0
owner: Kitchen
technical_owner: Information Technology
created_at: 2026-11-04
updated_at: 2026-11-04
target_release: TBD
related_documents:
  - docs/products/kitchen/production-staff-assignment/prd.md
  - docs/products/kitchen/production-staff-assignment/implementation-plan.md
  - docs/operations/ci-cd/release-pipeline.md
supersedes: null
---

# Rollout Runbook — Assignment Staff Produksi per Detail Production Order

> Jangan menulis credential, token, isi `.env`, payload ESB, atau data pribadi employee pada dokumen dan evidence rollout.

## 1. Prerequisite

- [x] OD-1–OD-3 pada PRD §26 sudah ditutup (4 November 2026).
- [ ] PRD berstatus `approved`/`in-development` saat implementasi dan sesuai kode final.
- [ ] Pull Request implementasi sudah di-merge dan CI hijau.
- [ ] Implementation report, README, data model, dan permission docs sesuai implementasi.
- [ ] Migration additive direview dan rehearsal MySQL staging lulus.
- [ ] Test assignment, authorization, audit, concurrency, sync regression, dan query count lulus.
- [ ] Tim Kitchen menentukan user dan Branch untuk UAT.
- [ ] Backup database rutin terakhir berhasil.

## 2. Environment dan configuration

| Variabel | Wajib | Default | Keterangan |
|---|---|---|---|
| Variabel baru | Tidak | Not applicable | Fitur memakai database, auth, Employee, Branch, dan konfigurasi Kitchen existing |

Tidak ada feature flag, env, dependency, queue, scheduler, file storage, atau external credential baru.

## 3. Migration

- Migration final `2026_11_04_091631_create_kitchen_production_order_staff_tables` menambah `kitchen_production_order_staff_assignments` (unique `kpo_staff_assignments_detail_employee_unique`; FK detail cascade, employee/Branch/`assigned_by` set null) dan `kitchen_production_order_staff_assignment_events` (index `kpo_staff_events_detail_created_index`; seluruh FK set null). Rehearsal `up()` dan rollback struktur di MySQL lokal lulus pada 4 November 2026; rehearsal staging masih wajib.
- Migration bersifat additive dan tidak mengubah tabel `esb_production_orders`, `esb_production_order_details`, `employees`, atau data existing.
- Tidak ada backfill; seluruh detail existing mulai tanpa Staff Produksi.
- Tabel baru masih kosong saat deploy sehingga lock diperkirakan rendah; hasil rehearsal staging dicatat pada implementation report.
- Nama migration, index, FK, dan unique final diambil dari kode hasil Phase 1, bukan ditebak di runbook.
- Rollback production normal tidak menjatuhkan tabel setelah assignment dipakai.

## 4. Queue dan scheduler

Not applicable — assignment disimpan sinkron di database lokal. Sync Production Order existing tetap berjalan tanpa perubahan jadwal atau queue.

## 5. External integration

- Tidak ada endpoint ESB baru.
- Assignment tidak memanggil ESB dan tidak mengubah Production Order.
- UAT harus membuktikan save assignment tetap berhasil tanpa request eksternal dan sync ulang tidak menghapus assignment.
- Jangan memakai payload atau credential ESB production sebagai evidence.

## 6. Backup

- Pastikan backup database rutin berhasil sebelum deploy.
- Karena migration additive dan tanpa backfill, export khusus data lama tidak diperlukan.
- Setelah fitur dipakai, tabel assignment dan audit masuk backup database normal.
- Jangan menghapus assignment/audit sebagai bagian rollback otomatis.

## 7. Deployment steps

1. Pastikan artifact berasal dari commit/PR yang disetujui.
2. Deploy ke staging melalui pipeline existing.
3. Jalankan migration additive melalui pipeline.
4. Jalankan `permissions:sync` melalui mekanisme project.
5. Jangan langsung memberikan permission assignment ke role operasional luas.
6. Verifikasi Superadmin dapat membuka Production Order dan melihat UI assignment sesuai Branch Kitchen.
7. Berikan `assign kitchen production order staff` hanya kepada user UAT melalui Role & Permission.
8. Jalankan smoke test, data verification, authorization test manual, dan UAT staging.
9. Setelah sign-off, deploy ke production melalui pipeline existing.
10. Jalankan migration dan permission sync.
11. Grant permission production secara bertahap melalui UI.
12. Observasi error, audit, query, dan sync sebelum akses diperluas.

## 8. Smoke test

- [ ] Viewer dapat melihat assignment tetapi tidak melihat tombol mutation.
- [ ] User berizin melihat tombol **Atur Staff Produksi**.
- [ ] Picker hanya menampilkan kandidat sesuai OD-1.
- [ ] Search nama/kode/posisi dan multi-select bekerja.
- [ ] Satu dan beberapa staff dapat disimpan.
- [ ] Selection existing tampil tercentang saat modal dibuka kembali.
- [ ] Pelepasan seluruh staff meminta konfirmasi.
- [ ] Staff historis menampilkan label Nonaktif/Branch berubah bila relevan.
- [ ] Cross-Branch dan URL/action langsung ditolak.
- [ ] Detail selesai mengikuti OD-2.
- [ ] Coverage `X dari Y detail` akurat.
- [ ] Sync ulang Production Order mempertahankan assignment.
- [ ] Tidak ada request ESB ketika assignment disimpan.
- [ ] Kalender, filter, Sync Data, banner, material detail, dan mobile agenda existing tetap bekerja.
- [ ] Mobile, desktop, dark mode, zoom 200%, Escape, dan nama panjang tidak overflow.

## 9. Data verification

Verifikasi menggunakan ID/count agregat tanpa menyalin nama employee ke evidence:

1. setiap assignment aktif mengarah ke detail Production Order yang valid;
2. tidak ada pasangan detail + employee aktif duplikat;
3. snapshot employee dan Branch terisi sesuai kontrak;
4. setiap mutation nyata mempunyai audit event actor dan waktu;
5. no-op tidak menambah audit;
6. assignment tetap sama setelah sync detail yang sama;
7. tidak ada assignment lintas Branch;
8. detail tanpa assignment tetap tampil normal;
9. query kalender/detail/picker berada dalam baseline yang disetujui.

## 10. Monitoring

Pantau selama observasi:

- authorization denial abnormal atau cross-Branch attempt;
- unique/FK violation dan transaction rollback;
- audit event tanpa assignment yang sesuai atau sebaliknya;
- error render modal/picker/coverage;
- kenaikan query count atau response time kalender/detail;
- assignment hilang setelah sync;
- regresi job/scheduler/sync Production Order existing.

Log/evidence tidak boleh memuat payload ESB, credential, daftar nama employee, atau data pribadi lain yang tidak diperlukan.

## 11. Rollback trigger

- Assignment dapat dilihat atau diubah lintas Branch tanpa authorization.
- User tanpa permission dapat menjalankan mutation.
- Assignment hilang/duplikat setelah save atau sync.
- Mutation assignment memanggil atau mengubah ESB.
- Kalender/detail mengalami N+1 atau degradasi signifikan.
- Migration menimbulkan error pada tabel existing.
- Audit tidak dapat merekonstruksi perubahan assignment.

## 12. Rollback steps

1. Cabut `assign kitchen production order staff` dari role/user melalui Role & Permission.
2. Rollback artifact aplikasi menggunakan pipeline existing.
3. Pertahankan tabel assignment dan audit.
4. Jangan menghapus atau mengubah snapshot Production Order.
5. Verifikasi kalender, detail, sync manual, dan scheduler kembali normal.
6. Catat assignment yang dibuat selama rollout untuk rekonsiliasi setelah perbaikan.
7. Drop table hanya melalui release terpisah setelah backup, audit referensi, dan approval eksplisit.

## 13. Post-rollout observation

Durasi observasi ditentukan owner saat release. Selama periode tersebut, review harian error assignment, authorization denial, unique violation, audit consistency, query count, serta hasil sync. Permission diperluas hanya setelah UAT dan observasi awal diterima.

## 14. Sign-off

| Peran | Nama | Tanggal | Hasil |
|---|---|---|---|
| Owner Kitchen | | | |
| Perwakilan user UAT | | | |
| Technical owner | | | |
