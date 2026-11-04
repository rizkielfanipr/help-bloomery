---
document_id: PURCHASING-VENDOR-COMPLIANCE-LATE-DELIVERY-REPORT
title: Laporan Implementasi — Vendor Compliance Keterlambatan Delivery PO
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/vendor-compliance-late-delivery/prd.md
  - docs/products/purchasing/vendor-compliance-late-delivery/rollout.md
supersedes: null
---

# Laporan Implementasi — Vendor Compliance Keterlambatan Delivery PO

Acuan: `docs/products/purchasing/vendor-compliance-late-delivery/prd.md`

## 1. Status per phase

| Phase | Status | Catatan |
|---|---|---|
| 0 | Selesai | Keputusan §26 ditutup user (semua rekomendasi). Hasil audit dicatat pada PRD §26.2. Characterization test Receiving, PO Susulan, dan Vendor Compliance existing lulus sebelum schema berubah (112 test) |
| 1 | Selesai | Dua migration additive, enum, kalkulator, scoring, model, factory, dan relasi plural. Rehearsal MySQL lulus |
| 2 | Selesai | Snapshot `requiredDate` hasil re-fetch, source marker wajib, upsert kandidat, pengecualian PO Susulan, notifikasi, dan peringatan Receiving |
| 3 | Selesai | Konfirmasi dan pengecualian, Policy, event log, UI Vendor Compliance, detail Goods Receipt, dan command reconciliation |
| 4 | Selesai lokal | Regression, query count, UAT visual lokal, dan dokumentasi. UAT staging belum dijalankan |

## 2. Ringkasan perubahan

- `goods_receipts` menyimpan `po_required_date`, `delivery_delay_days`, `delivery_assessment_status`, `delivery_assessed_at`, dan `delivery_assessment_note`.
- `vendor_compliance_incidents` mendapat source key unik, liability, tanggal keterlambatan, delay maksimum, dan versi scoring. `goods_receipt_item_id` dan `affected_quantity` menjadi nullable, dan unique per item dilepas. Tabel append-only baru: `vendor_compliance_incident_events`.
- `DeliveryDelayCalculator` dan `LateDeliveryScoring` di `app/Services/Purchasing/VendorCompliance/`.
- `CreateGoodsReceiptAction` mewajibkan `source` dan menilai keterlambatan di dalam transaksi lokal. `AssessLateDeliveryComplianceAction` meng-upsert satu kandidat per company + PO.
- `ReviewLateDeliveryIncidentAction` menangani konfirmasi dan pengecualian. Policy `reviewLiability` dan aksi di halaman detail Vendor Compliance.
- Index, filter, detail, dan form Vendor Compliance membedakan insiden QC dan Late Delivery. Detail Goods Receipt menampilkan hasil penilaian.
- `GoodsReceiptPage` menampilkan Tanggal Dibutuhkan, callout amber non-blocking, dan state **Tidak Dapat Dinilai**.
- Command read-only `vendor-compliance:reconcile-late-delivery`.

## 3. Keputusan yang diambil

Lihat PRD §26.1 (keputusan bisnis) dan §26.2 (keputusan teknis Phase 0).

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §23.1 dan rollout §2 | Tidak ada feature flag assessment | Repository tidak mempunyai pola feature flag; PRD menyebutnya "bila disediakan" | Penghentian darurat lewat rollback aplikasi atau pencabutan permission review |
| §11.12 | Setelah liability `confirmed`/`waived`, partial GR berikutnya hanya memperbarui tanggal terakhir dan mencatat event; delay maksimum, severity, dan poin tidak berubah | Mencegah poin confirmed tidak sesuai delay yang tersimpan, tanpa reopen (v1) | Koreksi lewat prosedur admin bila diperlukan |
| §13.2 | Pengecualian otomatis menutup status penanganan menjadi `resolved` | Tidak ada tindak lanjut vendor untuk insiden yang dikecualikan | Tidak ada |
| §17.2 | Kolom Poin, Status, dan PIC pada tabel lebar di-scroll di dalam container | Tabel existing sudah mempunyai sepuluh kolom; PRD §17.3 mengizinkan scroll container | Tidak ada |
| Temuan di luar scope | View Receiving sebelumnya crash bila `requiredDate` tidak valid | `Carbon::parse` tanpa pengaman | Diperbaiki dengan parser fail-safe yang sama |

## 5. Test dan verifikasi

- `tests/Unit/DeliveryDelayCalculatorTest.php` (22 test): sebelum, sama, setelah, leap day, offset ISO dan UTC, jam diabaikan, null, string kosong, tidak valid, tanggal mustahil, tahun placeholder, array, dan matrix poin.
- `tests/Feature/LateDeliveryAssessmentTest.php` (14 test):
  - Receiving terlambat memakai `requiredDate` hasil re-fetch, bukan data browser lama, dan key payload GR tidak berubah;
  - on-time dan tidak dapat dinilai;
  - kandidat tetap dibuat saat ESB `failed`/`unknown`;
  - insiden rejection dan Late Delivery coexist;
  - PO Susulan tidak dinilai, dan header tanpa source ditolak;
  - partial GR, submission key ganda, kandidat yang sudah diputuskan, dan kandidat yang dibuat proses lain;
  - peringatan Receiving.
- `tests/Feature/LateDeliveryReviewTest.php` (14 test):
  - matrix konfirmasi dan pengecualian, transisi final, dan user tanpa izin;
  - matrix Policy, termasuk SUPERADMIN;
  - index, filter, dan query count;
  - detail dan aksi, termasuk modal alasan;
  - guard form tindak lanjut;
  - command reconciliation dan detail Goods Receipt.
- Regression lulus (175 test): `GoodsReceiptPageTest`, `VendorComplianceResourceTest`, `NonPoReceipt*`, `ReceivingAuthorizationTest`, `OperationalEsbMonitoringTest`, `EsbBranchMappingTest`, `RolePermissionsMatrixTest`, dan suite baru.
- Rehearsal migration MySQL: data legacy utuh, FK item tetap ada, `source_key` ganda ditolak, `down()` menolak rollback saat data baru ada, dan `down()` pada data kosong berhasil.
- UAT visual lokal dengan data dummy yang sudah dihapus: index dan detail Vendor Compliance pada 360/390/768/1024/1440 px tanpa overflow halaman. Dark mode, modal konfirmasi dengan preview poin, modal pengecualian, dan Escape menutup modal. Tidak ada error JavaScript.
- Belum diuji: Receiving dengan PO ESB sungguhan di staging, dua submit bersamaan dari dua proses MySQL, dan UAT Purchasing.

## 6. Migration dan konfigurasi

- Migration additive `2026_11_03_204208_add_delivery_assessment_to_goods_receipts_table` dan `2026_11_03_204209_extend_vendor_compliance_incidents_for_late_delivery`.
- Tidak ada permission, env, queue, atau dependency baru. Review memakai `edit vendor compliance incidents`.
- Tidak ada historical backfill; GR lama tampil **Belum Dinilai**.

## 7. Risiko dan pekerjaan lanjutan

- Migrate dari nol pada MySQL gagal di migration lama `marketing_material_fulfillments` karena nama index terlalu panjang. Masalah ini tidak terkait fitur, tetapi perlu diperbaiki terpisah agar rehearsal staging dapat memakai database baru.
- Rollback schema production tidak boleh mengembalikan unique item setelah ada insiden header-level.
- Notifikasi rejection existing masih dikirim ke role `PURCHASING_STAFF`, sedangkan notifikasi Late Delivery dikirim ke permission `edit vendor compliance incidents`.
