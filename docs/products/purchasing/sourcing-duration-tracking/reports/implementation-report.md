---
document_id: PURCHASING-SOURCING-DURATION-TRACKING-REPORT
title: Laporan Implementasi — Pelacakan Durasi Sourcing
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/purchasing/sourcing-duration-tracking/prd.md
  - docs/products/purchasing/sourcing-duration-tracking/rollout.md
supersedes: null
---

# Laporan Implementasi — Pelacakan Durasi Sourcing

Acuan: `docs/products/purchasing/sourcing-duration-tracking/prd.md`

## 1. Status per phase

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 — Audit dan characterization | Selesai | PR fitur ini | Baseline 106 test MaterialWorkflow dan MaterialSourcing lulus sebelum perubahan; seluruh writer `sourcing_status` diaudit |
| 1 — Schema dan timestamp | Selesai | PR fitur ini | Migration additive, cast, presenter, dan writer |
| 2 — UI | Selesai | PR fitur ini | Kolom, filter header, dan ringkasan modal bersama |
| 3 — Backfill dan audit | Selesai | PR fitur ini | Command dry-run/apply dan metadata audit |
| 4 — Dokumentasi dan UAT | Sebagian | PR fitur ini | Dokumentasi selesai; dry-run dan UAT staging belum dijalankan |

## 2. Ringkasan perubahan

- `sourcing_completed_at` nullable ditambahkan pada `rnd_product_esb_materials`. Kolom tidak fillable dan tidak tersedia sebagai input.
- `MaterialSourcingTimeline` menyajikan tanggal dan durasi hanya dari atribut record, tanpa query. Index dan modal memakai presenter yang sama.
- Index Sourcing Bahan dan Review Sourcing menampilkan MASUK SOURCING, SELESAI SOURCING, dan DURASI, dengan filter pada header.
- `rnd:backfill-sourcing-durations` default dry-run, chunked, idempotent, dan hanya mengeluarkan counter agregat.

### Audit writer `sourcing_status`

| Writer | Transisi | Perlakuan |
|---|---|---|
| `MaterialPostSyncRouter` | Bahan biasa ke `waiting_purchasing` | Mulai sudah terisi dan idempotent; tidak diubah |
| `MaterialPostSyncRouter` | WIP ke `not_required` | Mengosongkan selesai; **tidak ada di plan** |
| `CreateStandaloneMaterialSourcingAction` | Record mandiri dibuat | Mengisi mulai |
| `AddMaterialSupplierCandidatesAction` | Legacy ke `pending_rnd_review` | Mengosongkan selesai dan mengaudit reopen; mengisi mulai dari `not_started` |
| `UpdateMaterialSupplierCandidateAction` | Legacy ke `pending_rnd_review` | Mengosongkan selesai dan mengaudit reopen; **tidak ada di plan** |
| `ReviewMaterialSampleAction` | Approve, reject, resample | Approve dan reject mengisi selesai; resample tetap berjalan |
| `ReopenMaterialSampleApprovalAction`, `ReopenRejectedMaterialSourcingAction` | Kembali aktif | Mengosongkan selesai dan mengaudit nilai lama |
| `ReviewLegacyMaterialSourcingAction` | Approve ke Finance, reject | Reject mengisi selesai dan diaudit |
| `ReviewLegacyMaterialSourcingFinanceAction` | Approve, kembali ke R&D | Approve mengisi selesai dan diaudit; kembali ke R&D tetap berjalan |
| `HandOverMaterialSampleAction`, `ConfirmMaterialSampleReceiptAction` | Antar-status aktif | Tidak diubah; hanya berjalan dari status aktif |

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| Backfill mulai memakai `created_at` hanya untuk `sourcing_origin = standalone`; project legacy tetap null | `created_at` project legacy adalah waktu pembuatan bahan, bukan permintaan sourcing, dan PRD §20.2 melarang menebak | PRD §20.2 |
| Bahan legacy `not_started` yang menerima supplier pertama mengisi mulai | Waktunya diketahui server dan tanpa ini sourcing baru tampil Tidak Tersedia selamanya | PRD §11 butir 19 |
| Tanggal mulai yang tidak terbukti tidak menghalangi tanggal selesai yang terbukti | Tanggal selesai tetap benar; durasi tetap Tidak Tersedia | PRD §20.3 |
| Status menjadi sumber kondisi berjalan atau selesai | Tampilan dan filter konsisten, termasuk bila kolom selesai belum dibackfill | PRD §11 butir 20 |
| Backfill tidak menulis audit event dan tidak mengubah `updated_at` | Default sort antrean memakai `updated_at`; audit tetap hanya mencatat keputusan pengguna | PRD §19, Rollout §9 |
| Tidak ada indeks baru | Tidak ada bukti kebutuhan; volume lokal hanya 6 record. Evaluasi `EXPLAIN` tetap perlu dilakukan di staging | PRD §20.1 |
| Kolom `DIPERBARUI` tersembunyi default | Tabel lebih ringkas; kolom dan filternya kembali lewat pengelola kolom | PRD FR-11 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| Implementation plan §3 | Lebih banyak writer dari yang disebut plan: `UpdateMaterialSupplierCandidateAction` dan routing WIP | Prompt mewajibkan audit seluruh writer; kedua jalur dapat membuat tanggal selesai basi | Plan diperbarui |
| §19 | Keputusan legacy kini menulis ke `rnd_material_audit_logs` lewat event baru | FR-13 mewajibkan audit, sedangkan alur legacy sebelumnya hanya menulis `material_sourcing_approvals` | PRD diperbarui |
| §17.4 | Copy tambahan `Kurang dari 1 menit` | Menghindari angka 0 pada durasi yang sah | PRD diperbarui |
| §28 | Bukti dry-run dan UAT staging belum ada | Staging tidak dijalankan dalam PR ini | Dijalankan saat rollout |

## 5. Test dan verifikasi

**Otomatis (2 November 2026, SQLite in-memory):**

| Perintah | Hasil |
|---|---|
| `php artisan test --compact tests/Feature/MaterialWorkflow` | 69 lulus |
| `php artisan test --compact tests/Feature/MaterialSourcingTest.php` | 37 lulus |
| Test baru, MaterialWorkflow, MaterialSourcing, RndProject, audit legacy, dan ERP request yang memakai header bersama | 270 lulus, 1.604 assertion |

Suite Feature penuh tidak dijalankan secara lokal dan mengandalkan CI.

**Manual lokal (MySQL dan Chromium headless, sebagai Super Admin):**

- Migration berjalan pada MySQL lokal; backfill tidak di-apply pada data lokal.
- Index Purchasing dan R&D pada 360, 390, 768, 1024, dan 1440 px: halaman tidak overflow horizontal dan tabel memakai scroll container.
- Modal detail pada kelima lebar tersebut, viewport 720 px setara zoom 200%, dan dark mode: ringkasan tampil sebelum supplier dan tidak meluap. Tidak ada JavaScript error.
- Filter kondisi durasi pada 6 record lokal: Masih Berjalan 4, Selesai 2.
- Sorting, filter tanggal, nama panjang, approve, resample, reopen, dan Purchasing/R&D sebagai role sebenarnya hanya diuji lewat feature test. Aksi yang mengubah data tidak dijalankan di browser.

**Dry-run backfill lokal** (bukan bukti staging):

| Counter | Jumlah |
|---|---|
| scanned | 6 |
| eligible | 2 |
| updated | 0 |
| skipped_ambiguous | 4 |
| invalid_chronology | 0 |
| unchanged | 1 |
| failed | 0 |
| requested_at_candidates / requested_at_ambiguous | 0 / 4 |
| completed_at_candidates / completed_at_ambiguous | 2 / 0 |

**Dry-run dan UAT staging:** belum dijalankan.

## 6. Migration dan konfigurasi

- Migration `2026_11_02_160003_add_sourcing_completed_at_to_rnd_product_esb_materials_table` menambah satu kolom nullable. Estimasi lock belum diukur di staging.
- Tidak ada env, permission, queue, scheduler, dependency, atau integrasi ESB baru.
- Backfill dijalankan terpisah sesuai [Rollout](../rollout.md).

## 7. Risiko dan pekerjaan lanjutan

- Bahan project legacy tanpa tanggal mulai akan menampilkan **Tidak Tersedia** secara permanen. Jumlahnya di production baru diketahui dari dry-run.
- Sampai backfill di-apply, record final lama menampilkan tanggal selesai **Tidak Tersedia**, termasuk pada filter Selesai.
- Filter tanggal selesai tidak menemukan record final yang tanggalnya masih null.
- Header tabel bersama dengan ERP Repair Request; perubahan lebar hanya berlaku untuk kolom sourcing, dan test ERP terkait lulus.
- Pekerjaan lanjutan: dry-run staging, UAT Purchasing dan R&D, evaluasi query plan, lalu sign-off rollout.
