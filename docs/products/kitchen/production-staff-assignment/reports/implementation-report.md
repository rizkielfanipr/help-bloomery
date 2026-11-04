---
document_id: KITCHEN-PRODUCTION-STAFF-ASSIGNMENT-REPORT
title: Laporan Implementasi — Assignment Staff Produksi per Detail Production Order
status: draft
version: 1.0
owner: Kitchen
technical_owner: Information Technology
created_at: 2026-11-04
updated_at: 2026-11-04
target_release: TBD
related_documents:
  - docs/products/kitchen/production-staff-assignment/prd.md
  - docs/products/kitchen/production-staff-assignment/rollout.md
supersedes: null
---

# Laporan Implementasi — Assignment Staff Produksi per Detail Production Order

Acuan: `docs/products/kitchen/production-staff-assignment/prd.md`

## 1. Status per phase

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 | Selesai | `c981e15b` | OD-1–OD-3 ditutup user; audit schema, lifecycle Employee (delete tersedia di Master Employee, FK `nullOnDelete` pada `sales_report_employees`), Policy, builder, dan UI. Kontrak final dicatat di PRD §14.2a |
| 1 | Selesai | `335bb631` | Migration additive, model, enum, factory, relasi, permission, Policy `assignStaff`, guard append-only |
| 2 | Selesai | `335bb631` | `AssignProductionOrderStaffAction`: normalisasi, transaksi, lock detail, diff, stale-check, audit |
| 3 | Selesai | `44976671` | Section Staff Produksi, picker, konfirmasi, label historis, coverage detail/kalender |
| 4 | Sebagian | `29b4c09d` + commit dokumentasi | Test, regression, Pint, doc links, code map, rehearsal MySQL lokal selesai. UAT staging, rehearsal staging, dan CI PR masih pending |

## 2. Ringkasan perubahan

- **Data:** `kitchen_production_order_staff_assignments` (baris aktif) dan `kitchen_production_order_staff_assignment_events` (audit append-only) di migration `2026_11_04_091631_create_kitchen_production_order_staff_tables`. Tidak ada perubahan pada tabel snapshot ESB, `employees`, atau data existing; tanpa backfill.
- **Authorization:** permission `assign kitchen production order staff` (grup Kitchen → Jadwal Produksi) dan `EsbProductionOrderPolicy::assignStaff` = `view` + permission assign + Branch aktif. Action menegakkan Brand Kitchen dan Branch aktif lewat query sehingga Superadmin (`Gate::before`) tetap terbatas.
- **Mutation:** `AssignProductionOrderStaffAction` me-lock baris detail, membaca ulang order/Branch/employee, menolak selection non-integer/duplikat/>50, menulis diff (create + delete) dan event per perubahan; no-op tidak menulis apa pun.
- **Read model:** `ProductionOrderDetailBuilder` eager-load assignment + employee + actor dan menghitung `canAssignStaff`/`staffedDetails` di server; `staffPickerDetail`/`staffCandidates` untuk picker. `ProductionOrderCalendarQuery` menambah `staffed_details_count` lewat `withCount` (tanpa query tambahan).
- **UI:** copy ESB vs lokal, coverage, section Staff Produksi per detail, partial `kitchen-production-staff-picker.blade.php`, opsi `maxWidth="2xl"` pada `x-rnd.picker-modal`.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| OD-1 employee aktif + Branch sama; OD-2 koreksi detail selesai boleh dengan konfirmasi; OD-3 Back Office saja | Keputusan user 4 November 2026 | PRD §26 |
| Strategi current-row + audit terpisah | Unique `(detail, employee)` tetap benar di MySQL tanpa kolom `released_at` nullable | PRD §14.2a |
| Stale-check berbasis ID assignment yang dilihat saat picker dibuka | Lock baris mencegah duplikat, tetapi semantik "selection = kondisi akhir" dapat menimpa perubahan pengguna lain; stale-check menolak penyimpanan tersebut | PRD §14.2a, §18.5 |
| Baris dengan employee terhapus dilepas saat penyimpanan berikutnya | Employee tidak dapat dipilih lagi, sehingga baris tidak dapat dipertahankan lewat selection; tetap diaudit dengan `reason = employee_deleted` | PRD §14.2a |
| Method Policy pada `EsbProductionOrderPolicy`, bukan Policy detail baru | Otorisasi selalu bergantung pada order induk; satu penggunaan | PRD §14.2a |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §17.2 | Picker memakai batas 30 kandidat + pencarian, bukan pagination | Jumlah employee per Branch kecil (data lokal 15 employee); batas + pencarian cukup dan tanpa state halaman | Tambah pagination bila satu Branch melampaui ratusan employee |
| §21.2 | Concurrency diuji lewat skenario stale-check dan simpan berulang di SQLite; `lockForUpdate` tidak berefek di SQLite | Test suite memakai SQLite in-memory | Verifikasi dua sesi bersamaan saat UAT staging (MySQL) |

## 5. Test dan verifikasi

- `tests/Feature/KitchenProductionOrderStaffAssignmentTest.php`: 40 test lulus (schema/unique, relasi/cast, append-only, permission registry, Policy matrix 8 kasus, action add/multiple/replace/release/no-op/malformed/rollback/ineligible/forged/stale/duplikat/finished/employee terhapus/tanpa ESB/snapshot tetap, sync regression, UI viewer/assigner/konfirmasi/label/forged/scope/close/coverage, query count detail+picker+kalender).
- Regression terkait: `KitchenProductionOrderPageTest`, `KitchenProductionOrderSyncTest`, `EsbProductionOrderServiceTest`, `RolePermissionsMatrixTest`, `RbacResourceTest`, `StockCardTest`, `SalesReportApprovalWorkflowTest`, `BasketSizeTest` — 226 test lulus.
- Full Feature suite 4 shard: hasil dicantumkan di Pull Request.
- `vendor/bin/pint --dirty --format agent`, `bash ops/ci/check-doc-links.sh`, `bash ops/ci/check-code-map.sh`, `git diff --check`: lulus.
- Migration rehearsal MySQL lokal: `up()` membuat tabel, FK, unique, dan index sesuai rancangan; `rollback` menghapus keduanya; database lokal dikembalikan ke kondisi awal.
- Belum dilakukan: UAT staging (360/390/768/1024/1440 px, zoom 200%, dark mode, keyboard/Escape, nama panjang) dan rehearsal staging.

## 6. Migration dan konfigurasi

- Satu migration additive; tidak ada env, queue, scheduler, dependency, atau feature flag baru.
- Deploy menjalankan `permissions:sync`; `assign kitchen production order staff` tidak di-grant ke role operasional oleh seeder (hanya Superadmin yang memang menerima semua permission). Grant dilakukan melalui Role & Permission setelah UAT.
- Rollback production: cabut permission, rollback aplikasi, pertahankan tabel (lihat [rollout](../rollout.md) §12).

## 7. Risiko dan pekerjaan lanjutan

- UAT staging dan rehearsal migration staging sesuai [rollout](../rollout.md).
- Setelah rollout production sehat: ubah status PRD/README menjadi `implemented`.
- Employee App/**Tugas Produksi Saya**, notifikasi, dan pagination picker memerlukan PRD terpisah (OD-3).
