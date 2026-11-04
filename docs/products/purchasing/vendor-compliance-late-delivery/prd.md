---
document_id: PURCHASING-VENDOR-COMPLIANCE-LATE-DELIVERY-PRD
title: PRD — Vendor Compliance Keterlambatan Delivery PO
status: implemented
version: 1.2
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/README.md
  - docs/products/inventory/README.md
  - docs/products/inventory/non-po-receiving/prd.md
  - docs/products/inventory/receiving-central-blss/prd.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
  - docs/architecture/data-model.md
  - docs/architecture/panels.md
  - docs/architecture/rbac.md
supersedes: null
---

# PRD — Vendor Compliance Keterlambatan Delivery PO

> Dokumen ini adalah kontrak planning. Dokumen belum mengotorisasi migration production, perubahan role production, pemberian poin vendor, atau deployment. Keputusan pada §26 harus ditutup sebelum application code ditulis.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Purchasing |
| Fitur | Vendor Compliance Keterlambatan Delivery PO |
| Status | implemented |
| Prioritas | TBD — belum ditetapkan owner bisnis |
| Target pengguna | Inventory Receiving dan Purchasing |
| Modul terdampak | Inventory Receiving, Goods Receipt, Purchasing Vendor Compliance |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

Vendor Compliance saat ini hanya membuat insiden otomatis ketika item pada Receiving ditolak. Fitur ini menambah penilaian keterlambatan vendor dengan membandingkan tanggal aktual penerimaan (`goods_receipt_date`) terhadap `requiredDate` terbaru dari PO ESB yang diambil ulang saat submit. Keterlambatan tidak memblokir penerimaan atau pengiriman GR ke ESB; sistem membuat satu kandidat insiden `late_delivery` per company dan nomor PO, lalu Purchasing mengonfirmasi pelanggaran atau memberikan pengecualian dengan alasan. Poin demerit baru aktif setelah konfirmasi agar perubahan jadwal, kesalahan data, atau keterlambatan yang bukan tanggung jawab vendor tidak langsung menghukum vendor.

## 3. Latar belakang

Tanggal kebutuhan PO sudah tersedia di payload PO ESB dan dipakai Receiving untuk mengurutkan daftar PO, tetapi tidak disimpan pada Goods Receipt serta belum menjadi sumber Vendor Compliance. Akibatnya, Purchasing tidak mempunyai daftar terstruktur vendor yang mengirim setelah tanggal dibutuhkan, jumlah hari keterlambatan, keputusan liability, maupun poin yang dapat diaudit.

Deteksi harus terjadi sedekat mungkin dengan pencatatan penerimaan fisik, tetapi tidak boleh menghambat operasional Receiving. Penilaian juga harus membedakan PO normal dari PO Susulan: pada PO Susulan, barang sudah datang sebelum PO dibuat sehingga membandingkan GR terhadap tanggal kebutuhan PO dapat menghasilkan pelanggaran palsu.

## 4. Baseline sistem

Audit repository pada commit `94e89952` tanggal 3 November 2026 menemukan:

1. `GoodsReceiptPage::submit()` mengambil ulang PO terbaru melalui `EsbGoodsReceiptService::purchaseOrder()`, memvalidasi status/cabang, lalu memanggil `CreateGoodsReceiptAction::record()`.
2. Payload PO telah mempunyai `requiredDate`; halaman Receiving menggunakannya untuk sorting, tetapi tidak meneruskannya ke Action dan tidak menyimpannya pada `goods_receipts`.
3. `goods_receipt_date` dipilih user, wajib berupa tanggal, dan tidak boleh lebih besar dari hari ini.
4. `CreateGoodsReceiptAction` adalah write boundary bersama untuk Receiving PO normal dan PO Susulan. Action menyimpan QC lokal terlebih dahulu, lalu pengiriman GR ke ESB dilakukan di luar transaction.
5. Insiden Vendor Compliance existing dibuat per item ketika `rejected_qty > 0` dan poin berasal dari `InboundGoodsReceiptQcService::demeritPoints()`.
6. `vendor_compliance_incidents.goods_receipt_item_id` saat ini wajib dan unik; `GoodsReceiptItem` mempunyai relasi singular `vendorComplianceIncident()`. Struktur ini tidak dapat menyimpan insiden header-level dan juga mencegah item memiliki lebih dari satu jenis insiden.
7. `vendor_compliance_incidents.goods_receipt_id` wajib dan cascade delete. Model belum mempunyai source key, status liability, tanggal kebutuhan, tanggal aktual, maupun jumlah hari keterlambatan.
8. Resource Vendor Compliance existing mendukung view/edit tindak lanjut dengan status penanganan `open`, `in_progress`, `claim_or_return_submitted`, dan `resolved`.
9. Permission existing `view vendor compliance incidents` dan `edit vendor compliance incidents` sudah memadai untuk melihat serta memverifikasi insiden; permission baru belum terbukti diperlukan.
10. Relasi `GoodsReceipt::nonPoReceipt()` dan `sourceLabel()` dapat membedakan PO normal dari PO Susulan setelah record terbentuk, tetapi caller shared action harus meneruskan source secara eksplisit agar penilaian tidak bergantung pada timing relasi.

## 5. Problem statement

1. Keterlambatan terhadap tanggal kebutuhan PO tidak dicatat atau dinilai otomatis.
2. Tanggal kebutuhan PO dapat berubah/hilang dari ESB sehingga audit memerlukan snapshot saat penerimaan.
3. Penerapan poin otomatis tanpa review dapat menghukum vendor untuk perubahan jadwal internal atau data PO yang salah.
4. Partial delivery dapat membuat beberapa GR untuk satu PO dan berisiko menghasilkan poin berulang.
5. Constraint satu insiden per item tidak mendukung insiden keterlambatan pada level PO serta koeksistensi dengan insiden QC.
6. PO Susulan berisiko salah dinilai sebagai terlambat bila mengikuti action Receiving yang sama.

## 6. Goals

1. Menyimpan snapshot tanggal kebutuhan PO dan hasil penilaian keterlambatan pada Goods Receipt PO normal.
2. Membuat satu kandidat insiden `late_delivery` per company + nomor PO secara idempotent ketika tanggal aktual melewati tanggal kebutuhan.
3. Menjaga Receiving dan proses GR tetap berjalan walaupun terlambat.
4. Memisahkan deteksi sistem dari keputusan liability serta aktivasi poin oleh Purchasing.
5. Mendukung partial delivery tanpa duplikasi poin dan tetap memperbarui keterlambatan maksimum.
6. Menampilkan informasi required date, received date, hari terlambat, liability, poin, status penanganan, dan PIC pada Vendor Compliance.
7. Menjaga insiden penolakan existing dan PO Susulan tanpa regresi.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| GR PO normal terlambat dengan required date valid yang mempunyai kandidat insiden | 0% | 100% | Query reconciliation dan feature test |
| GR tepat waktu yang menghasilkan insiden keterlambatan | Belum terukur | 0 | Test boundary date dan audit query |
| PO Susulan yang menghasilkan insiden keterlambatan | Belum terukur | 0 | Regression test PO Susulan |
| Insiden `late_delivery` aktif per company + PO | Tidak tersedia | Maksimal 1 | Unique source key dan concurrency test |
| Kandidat belum diverifikasi yang mempunyai poin | Tidak tersedia | 0 | Constraint/business rule dan test |
| Receiving gagal hanya karena vendor terlambat | Tidak tersedia | 0 | End-to-end Receiving test |

## 8. Non-goals

- Mengubah tanggal kebutuhan PO di ESB atau membuat mutation ESB baru.
- Memblokir Receiving, menolak barang, atau menahan GR karena terlambat.
- Menilai PO Susulan pada versi pertama.
- Menghitung keterlambatan per item PO atau memberi poin berulang untuk setiap partial GR.
- Membuat vendor scorecard periodik, ranking vendor, kontrak SLA per supplier, atau suspend supplier otomatis.
- Menambahkan hari libur/kalender kerja sebelum owner bisnis memilih aturan kalender.
- Mengubah status penanganan Vendor Compliance existing menjadi workflow baru.
- Mengirim WhatsApp/email eksternal atau membuat package/dependency baru.
- Backfill otomatis seluruh GR historis pada rollout pertama.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Inventory Receiving | Mencatat tanggal penerimaan fisik dan menyelesaikan QC/GR seperti biasa | Permission Receiving existing |
| Sistem | Snapshot required date, menghitung delay, dan upsert kandidat insiden | Internal Action; bukan permission UI |
| Purchasing viewer | Melihat kandidat/insiden serta detail bukti | `view vendor compliance incidents` |
| Purchasing reviewer | Mengonfirmasi pelanggaran atau memberikan pengecualian; melanjutkan penanganan existing | `edit vendor compliance incidents` |
| Administrator | Memberikan permission existing melalui Role & Permission | Permission management existing |

## 10. User journey

### 10.1 Receiving

1. User memilih PO normal dan mengisi tanggal penerimaan, dokumen, barang, serta QC.
2. Saat submit, server mengambil ulang detail PO terbaru dari ESB.
3. Sistem membaca `requiredDate`, memvalidasi formatnya, dan menampilkan peringatan bila `goods_receipt_date` berada setelah tanggal tersebut.
4. User tetap menekan **Simpan QC & Proses Goods Receipt**; keterlambatan tidak menjadi validation error.
5. Dalam transaction penyimpanan lokal, sistem menyimpan snapshot required date dan jumlah hari keterlambatan.
6. Bila terlambat, sistem membuat atau memperbarui kandidat Vendor Compliance. Proses pengiriman GR ke ESB tetap mengikuti alur existing.

### 10.2 Purchasing

1. Purchasing menerima notifikasi internal bahwa kandidat Late Delivery perlu diverifikasi.
2. User membuka **Purchasing → Vendor Compliance** dan memfilter kategori **Late Delivery** atau status liability **Menunggu Verifikasi**.
3. Index menampilkan vendor, nomor PO, tanggal dibutuhkan, tanggal diterima, hari terlambat, status verifikasi, poin, status penanganan, dan PIC.
4. Pada detail, user melihat sumber PO/GR dan riwayat keterlambatan partial delivery.
5. User memilih **Konfirmasi Pelanggaran** atau **Berikan Pengecualian**.
6. Konfirmasi menetapkan liability `confirmed`, severity, dan poin sesuai matrix yang disetujui; pengecualian menetapkan `waived`, poin 0, serta alasan wajib.
7. Insiden confirmed selanjutnya memakai status penanganan serta tindak lanjut existing sampai resolved.

## 11. Business rules

1. Scope versi pertama hanya Goods Receipt yang berasal dari PO normal.
2. PO Susulan dikecualikan walaupun memakai `CreateGoodsReceiptAction` yang sama.
3. Required date authoritative adalah `requiredDate` dari hasil re-fetch PO terbaru saat submit, bukan nilai lama di browser.
4. `requiredDate` dan `goods_receipt_date` dinormalisasi ke tanggal lokal `Asia/Jakarta`; waktu/jam tidak dihitung.
5. Tepat waktu bila `goods_receipt_date <= po_required_date`; terlambat bila lebih besar.
6. Jumlah keterlambatan adalah selisih hari positif sesuai keputusan kalender pada §26; nilai tepat waktu adalah 0.
7. Required date yang kosong atau tidak valid tidak membuat insiden. Goods Receipt disimpan sebagai `not_assessable` dengan alasan aman dan Receiving tetap berjalan.
8. Keterlambatan tidak mengubah hasil QC, qty accepted/hold/rejected, auto close PO, status GR, maupun payload ESB.
9. Keterlambatan dinilai ketika penerimaan fisik lokal pertama kali berhasil dicatat, termasuk saat seluruh item Hold/Rejected atau hasil kirim GR ke ESB `failed`/`unknown`.
10. Satu kandidat `late_delivery` aktif dibuat per kombinasi company code + nomor PO, bukan per item atau per GR.
11. Upsert memakai source key deterministik dan unique constraint; double click/concurrent submit tidak membuat kandidat kedua.
12. Partial GR berikutnya untuk PO yang sama memperbarui latest receipt date dan maximum delay bila lebih besar, tanpa menambahkan poin kedua.
13. Goods Receipt pertama yang memicu kandidat tetap menjadi anchor `goods_receipt_id`; seluruh penerimaan PO dapat ditampilkan dari company + reference number. Perubahan anchor tidak diperlukan untuk partial delivery.
14. Insiden Late Delivery adalah header-level: `goods_receipt_item_id` bernilai null. Insiden QC existing tetap item-level.
15. Satu item boleh mempunyai lebih dari satu insiden di masa depan; unique constraint existing pada `goods_receipt_item_id` harus dilepas dan relasi model menjadi plural.
16. Kandidat baru mempunyai `liability_status = pending`, poin 0, dan status penanganan `open`.
17. Hanya Purchasing reviewer yang dapat mengubah liability `pending → confirmed` atau `pending → waived`.
18. `confirmed` menetapkan reviewer/timestamp, severity, dan poin dari matrix versi tersimpan. `waived` wajib alasan, reviewer/timestamp, dan poin 0.
19. Reopen atau perubahan keputusan liability setelah confirmed/waived tidak termasuk versi pertama; koreksi dilakukan melalui prosedur admin/audit sampai requirement baru disetujui.
20. Insiden penolakan dan keterlambatan dapat hidup bersamaan untuk GR/PO yang sama.
21. Snapshot required date dan delay tidak dihitung ulang otomatis bila PO ESB berubah setelah penerimaan; perubahan tersebut menjadi bukti review/waiver, bukan rewrite histori diam-diam.
22. Semua aksi review server-authorized, transactional, dan tercatat actor/waktu/alasan.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Membaca dan memvalidasi `requiredDate` dari PO terbaru saat submit Receiving | Must |
| FR-2 | Menyimpan snapshot required date, assessment status, dan delay pada Goods Receipt | Must |
| FR-3 | Menampilkan peringatan non-blocking saat Receiving terlambat | Must |
| FR-4 | Upsert satu kandidat `late_delivery` per company + PO | Must |
| FR-5 | Mengecualikan PO Susulan secara eksplisit | Must |
| FR-6 | Mendukung coexistence insiden QC item-level dan keterlambatan header-level | Must |
| FR-7 | Menyediakan liability status pending/confirmed/waived | Must |
| FR-8 | Mengaktifkan poin hanya setelah confirmed | Must |
| FR-9 | Memperbarui maximum delay pada partial delivery tanpa poin berulang | Must |
| FR-10 | Menyediakan kolom/filter/detail/action Late Delivery pada resource existing | Must |
| FR-11 | Mengirim notifikasi internal kepada Purchasing reviewer | Should |
| FR-12 | Menyediakan audit keputusan dan alasan waiver | Must |
| FR-13 | Menyediakan reconciliation command/report read-only untuk mendeteksi gap setelah rollout | Should |

## 13. Status dan lifecycle

### 13.1 Liability

| Status | Makna | Transisi yang diizinkan | Aktor |
|---|---|---|---|
| `not_applicable` | Insiden QC existing; liability review keterlambatan tidak berlaku | Terminal untuk dimensi ini | Sistem |
| `pending` | Terlambat terdeteksi, belum diputuskan | `confirmed`, `waived` | Purchasing reviewer |
| `confirmed` | Vendor dinyatakan bertanggung jawab; poin aktif | Terminal pada v1 | Purchasing reviewer |
| `waived` | Dikecualikan dengan alasan; poin 0 | Terminal pada v1 | Purchasing reviewer |

### 13.2 Status penanganan

Status `open`, `in_progress`, `claim_or_return_submitted`, dan `resolved` tetap mengikuti workflow Vendor Compliance existing. Liability menjawab apakah pelanggaran berlaku; status penanganan menjawab progres tindak lanjut. Kandidat `pending` tidak boleh di-resolve sebagai pelanggaran sebelum liability diputuskan.

## 14. Model data konseptual

### 14.1 `GoodsReceipt`

Field additive yang direncanakan:

- `po_required_date` nullable date — snapshot `requiredDate` yang valid;
- `delivery_delay_days` nullable unsigned integer — 0 untuk tepat waktu, positif untuk terlambat, null bila tidak dapat dinilai;
- `delivery_assessment_status` string/index — `on_time`, `late`, `not_assessable`, `not_applicable`;
- `delivery_assessed_at` nullable timestamp;
- `delivery_assessment_note` nullable string untuk alasan teknis aman seperti required date kosong/invalid, tanpa raw payload.

### 14.2 `VendorComplianceIncident`

Field/constraint additive dan kompatibilitas:

- kategori baru `late_delivery`;
- `source_type` nullable string dan `source_key` nullable string; unique composite/index untuk source otomatis;
- `goods_receipt_item_id` menjadi nullable dan tidak unique;
- `liability_status` default `not_applicable` untuk record existing;
- `liability_reviewed_by`, `liability_reviewed_at`, dan `waiver_reason` nullable;
- `required_date`, `first_late_receipt_date`, `latest_late_receipt_date` nullable date;
- `delay_days` nullable unsigned integer yang menyimpan keterlambatan maksimum;
- `scoring_rule_version` nullable string agar poin confirmed dapat diaudit;
- `affected_quantity` menjadi nullable hanya bila audit UI/report membuktikan kategori header-level tidak membutuhkan angka; opsi kompatibilitas aman adalah tetap non-null dengan nilai 0 dan label UI kontekstual.

`source_key` Late Delivery dibentuk dari identifier stabil seperti `late_delivery:{company_code}:{normalized_po_number}`. Normalisasi tidak boleh menggabungkan dua nomor PO berbeda.

### 14.3 Relasi

- `GoodsReceiptItem::vendorComplianceIncidents()` menjadi `HasMany`.
- Alias singular sementara hanya dipertahankan bila audit caller menunjukkan kebutuhan kompatibilitas; caller baru wajib memakai relasi plural.
- `VendorComplianceIncident::item()` tetap nullable `BelongsTo`.
- `GoodsReceipt::vendorComplianceIncidents()` tetap `HasMany`.

## 15. Integration contract

1. `EsbGoodsReceiptService::purchaseOrder(company, purchaseNumber)` tetap menjadi sumber PO terbaru.
2. Tidak ada endpoint/mutation ESB baru.
3. `requiredDate` dapat berisi ISO datetime, tanggal, null, atau data invalid; parser harus fail-safe dan tidak menebak nilai.
4. Timeout/error saat re-fetch PO tetap mengikuti perilaku Receiving existing dan tidak membuat assessment parsial baru.
5. Setelah PO berhasil diambil, snapshot required date masuk header canonical yang dikirim ke Action; Action tidak menerima nilai bebas dari browser.
6. Idempotency mengikuti `submission_key` Goods Receipt dan unique source key Vendor Compliance.
7. Notifikasi internal dikirim setelah transaction commit; kegagalan notifikasi tidak membatalkan penerimaan.

## 16. Authorization dan security

1. Tidak ada permission baru pada rekomendasi v1; view/review menggunakan permission Vendor Compliance existing.
2. Peringatan Receiving hanya tampil kepada user yang sudah berhak submit GR.
3. Assessment/action tidak mempercayai company, PO, required date, supplier, liability, reviewer, atau poin dari browser.
4. Policy mengizinkan liability review hanya dengan `edit vendor compliance incidents`.
5. Action review mengunci row, memvalidasi transition, dan menulis actor dari user login.
6. Detail supplier/PO mengikuti visibility resource existing; bila company scoping diperkenalkan kemudian, wajib diterapkan pada query dan Policy sekaligus.
7. Raw payload ESB, credential, dan exception internal tidak disimpan di description/notifikasi.
8. Insiden tidak dapat dibuat/dihapus manual melalui UI existing.

## 17. UI/UX specification

### 17.1 Receiving

- Setelah PO dipilih, tampilkan **Tanggal Dibutuhkan** bila valid.
- Bila tanggal penerimaan melewati tanggal dibutuhkan, tampilkan callout amber: jumlah hari keterlambatan dan informasi bahwa Vendor Compliance akan dibuat untuk review Purchasing.
- Callout bukan error dan tidak menonaktifkan tombol submit.
- Required date kosong/invalid menampilkan status netral **Tidak Dapat Dinilai**, bukan nilai 0 atau tanggal palsu.
- Copy tidak menyatakan vendor bersalah sebelum Purchasing mengonfirmasi.

### 17.2 Vendor Compliance index

- Pertahankan satu menu **Vendor Compliance**.
- Tambahkan filter kategori **Late Delivery** dan filter liability `pending`, `confirmed`, `waived`.
- Kolom kontekstual: vendor, PO, produk/tipe insiden, required date, latest received date, hari terlambat, liability, demerit, status penanganan, PIC.
- Kolom produk/qty menampilkan `—` atau label **Level PO** untuk Late Delivery, bukan error/null mentah.
- Default sorting tetap kejadian terbaru; kandidat pending dapat diberi badge amber.
- Search/filter/pagination tetap server-side dan query harus eager-load relasi yang digunakan.

### 17.3 Detail dan action

- Section **Informasi Keterlambatan** hanya untuk kategori `late_delivery`.
- Tampilkan required date, first/latest late receipt, maximum delay, PO, GR pemicu, vendor, scoring rule, serta status liability.
- Action **Konfirmasi Pelanggaran** menampilkan preview severity/poin dan confirmation modal.
- Action **Berikan Pengecualian** mewajibkan alasan.
- Aksi memiliki loading state, mencegah double submit, dan hilang setelah keputusan terminal.
- Form tindak lanjut existing tetap tersedia setelah keputusan sesuai rule §13.
- Mobile menggunakan stack; tabel lebar hanya scroll pada container tabel.

## 18. Non-functional requirements

1. Assessment dilakukan dalam transaction local receipt tanpa network call tambahan di Action.
2. Upsert kandidat harus aman terhadap concurrent partial GR.
3. Index dan navigation badge tidak menimbulkan N+1 dan mempunyai index pada category/liability/status/source key.
4. Perhitungan tanggal deterministic pada timezone aplikasi `Asia/Jakarta` dan diuji dengan ISO offset.
5. Penambahan assessment tidak boleh memperpanjang request dengan query per item; late delivery dinilai sekali per receipt header.
6. Migration additive dan aman untuk data existing; perubahan constraint dilakukan dengan urutan yang dapat di-rollback secara terkontrol.

## 19. Observability dan audit

1. Goods Receipt menyimpan assessment status, delay, dan timestamp.
2. Incident menyimpan source key, snapshot tanggal, maximum delay, scoring version, reviewer, waktu, dan alasan waiver.
3. Review liability dicatat melalui audit log/history existing bila tersedia; bila tidak, buat history/event append-only khusus perubahan penting, bukan hanya mutable columns.
4. Log aplikasi hanya mencatat identifier lokal, company, PO, assessment outcome, dan error class aman.
5. Monitoring pascarilis membandingkan jumlah GR `late` dengan kandidat incident dan mendeteksi duplicate source key.
6. Reconciliation command/report bersifat read-only secara default; mode repair memerlukan requirement dan instruksi terpisah.

## 20. Data migration dan kompatibilitas

1. Tambahkan field Goods Receipt dan Incident secara nullable/default-compatible.
2. Backfill `liability_status = not_applicable` untuk seluruh insiden existing tanpa mengubah poin/status.
3. Lepas unique index dan ubah `goods_receipt_item_id` menjadi nullable menggunakan migration eksplisit yang diuji pada database engine target.
4. Ubah relasi item menjadi plural setelah schema kompatibel; perbarui seluruh caller/test yang menggunakan relasi singular.
5. Insiden rejection existing tetap mempunyai item, affected quantity, severity, poin, dan status yang sama.
6. Tidak ada backfill required date/hari terlambat dari ESB pada deployment pertama.
7. GR historis tampil **Belum Dinilai**; historical backfill memerlukan dry-run, rate limit ESB, snapshot report, dan persetujuan terpisah.
8. PO Susulan existing maupun baru mempunyai assessment `not_applicable` dan tidak dibuatkan kandidat.

## 21. Testing strategy

1. Characterization test insiden QC existing sebelum schema/relation diubah.
2. Unit test parser tanggal dan kalkulator delay: sebelum, sama, setelah, leap day, timezone offset, null, dan invalid.
3. Feature test Receiving PO normal on-time, late, not-assessable, all rejected/hold, GR success/failed/unknown.
4. Regression PO Susulan membuktikan tidak ada insiden Late Delivery.
5. Idempotency/concurrency test untuk double submit dan dua partial GR pada PO yang sama.
6. Test coexistence rejection incident dan late incident.
7. Policy/action test pending → confirmed/waived, alasan waiver wajib, actor/timestamp, poin 0 sebelum confirmed, unauthorized/terminal transition.
8. Filament test kolom, filter, badge, detail level PO, visibility action, dan tindak lanjut existing.
9. Migration compatibility test dengan fixture insiden existing.
10. Notification test menggunakan fake; HTTP ESB selalu fake.
11. Query-count test untuk index/badge setelah eager loading/index ditambah.

## 22. Rollout

Rollout bertahap: migration kompatibilitas → deploy code assessment → permission sync existing → UAT Receiving dan Purchasing → observasi/reconciliation. Historical backfill tidak dijalankan. Detail operasional berada di [rollout.md](rollout.md).

## 23. Rollback

1. Nonaktifkan pembuatan/review kandidat melalui configuration/feature flag bila disediakan.
2. Rollback application ke versi sebelumnya dengan schema additive tetap dipertahankan.
3. Jangan drop kolom, mengembalikan unique item constraint, atau menghapus incident otomatis saat rollback aplikasi.
4. Kandidat yang sudah dibuat tetap dapat diaudit; poin confirmed tidak diubah tanpa keputusan bisnis.

## 24. Acceptance criteria

1. GR PO normal dengan tanggal aktual sesudah required date tersimpan sebagai `late` dan mempunyai tepat satu kandidat per company + PO.
2. GR pada/sebelum required date tidak menghasilkan kandidat.
3. Required date kosong/invalid tidak memblokir Receiving dan ditandai `not_assessable`.
4. PO Susulan tidak pernah menghasilkan kandidat Late Delivery.
5. Keterlambatan tidak mengubah payload atau hasil pengiriman GR ke ESB.
6. Kandidat pending mempunyai 0 poin dan dapat diverifikasi hanya oleh Purchasing berpermission.
7. Konfirmasi mengaktifkan severity/poin sesuai matrix versi yang disetujui; waiver menyimpan alasan dan 0 poin.
8. Partial GR memperbarui maximum delay tanpa membuat incident/poin kedua.
9. Insiden rejection existing tetap berfungsi dan dapat coexist dengan Late Delivery.
10. Index/detail/filter/action menyajikan data header-level tanpa error null item.
11. Audit actor/waktu/alasan dan source key tersedia.
12. Seluruh test minimum, formatter, doc checks, dan UAT staging lulus.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Required date ESB berubah setelah delivery | Histori/poin diperdebatkan | Snapshot saat submit; review/waiver; jangan recalculate diam-diam |
| Partial GR menggandakan poin | Vendor dihukum berulang | Unique source key per company + PO; lock/upsert/concurrency test |
| Shared Action ikut menilai PO Susulan | False incident | Source type eksplisit dan regression test PO Susulan |
| Pelepasan unique item merusak rejection flow | Regresi QC | Characterization test, migration kompatibel, relasi plural |
| Auto point terlalu agresif | Konflik vendor | Pending 0 poin; manual liability review |
| Item-null merusak resource/export | Runtime error | UI kontekstual dan null-safe test seluruh caller |
| Invalid date memblokir operasional | Receiving terhenti | Parser fail-safe dan `not_assessable` |
| Re-fetch ESB timeout | Assessment tidak tersedia | Pertahankan error handling Receiving existing; tidak menebak snapshot |

## 26. Open decisions

| No | Keputusan | Rekomendasi / opsi | Pemilik keputusan | Batas waktu |
|---|---|---|---|---|
| 1 | Kalender keterlambatan | **Rekomendasi:** hari kalender, tanpa grace period. Opsi: hari kerja atau grace N hari | Purchasing | Sebelum PRD menjadi approved |
| 2 | Matrix poin/severity | **Rekomendasi:** 1–2 hari = low/5; 3–7 = medium/10; >7 = high/20. Opsi: matrix lain atau poin 0 pada v1 | Purchasing | Sebelum implementasi scoring |
| 3 | Unit insiden partial delivery | **Rekomendasi:** satu insiden per company + PO dengan maximum delay. Opsi: per GR, berisiko poin berulang | Purchasing | Sebelum migration/source key |
| 4 | Review manual sebelum poin | **Rekomendasi:** wajib; pending 0 poin. Opsi auto-confirm, tidak direkomendasikan | Purchasing | Sebelum workflow Action |
| 5 | Perubahan keputusan terminal | **Rekomendasi:** tidak tersedia di v1; koreksi admin berjejak. Opsi reopen dengan permission baru | Purchasing & IT | Sebelum UAT |
| 6 | Affected quantity header-level | **Rekomendasi:** jadikan nullable setelah audit semua consumer. Opsi tetap 0 untuk kompatibilitas | Information Technology | Phase 0 implementasi |

### 26.1 Keputusan final (3 November 2026)

| No | Keputusan |
|---|---|
| 1 | Hari kalender tanpa grace period. Penerimaan H+1 sudah terlambat 1 hari. |
| 2 | Matrix `late-delivery-v1`: 1–2 hari = `low`/5 poin, 3–7 hari = `medium`/10 poin, lebih dari 7 hari = `high`/20 poin. Poin hanya aktif saat `confirmed`. |
| 3 | Satu insiden per company + nomor PO; partial GR memperbarui keterlambatan maksimum. |
| 4 | Review manual wajib; kandidat `pending` selalu 0 poin. |
| 5 | Keputusan `confirmed`/`waived` final pada v1, tanpa reopen; koreksi lewat prosedur admin berjejak. |
| 6 | `affected_quantity` menjadi nullable. Late Delivery menyimpan null dan UI menampilkan **Level PO**. Audit Phase 0 menemukan satu-satunya pemakai kolom ini adalah resource Vendor Compliance. |

### 26.2 Keputusan teknis Phase 0

| Area | Keputusan |
|---|---|
| Source marker | Header `CreateGoodsReceiptAction::record()` wajib membawa `source`: `purchase_order` dari `GoodsReceiptPage`, `non_po` dari `ProcessNonPoGoodsReceiptAction`. Header tanpa source ditolak. PO Susulan disimpan `not_applicable`. |
| Snapshot | `GoodsReceiptPage::submit()` meneruskan `requiredDate` dari PO hasil re-fetch sebagai `poRequiredDate`; Action tidak membaca nilai browser. |
| Upsert | `createOrFirst` pada unique `source_key` (`late_delivery:{COMPANY}:{PO}`, PO di-trim dan di-uppercase) di dalam transaksi Receiving, lalu `lockForUpdate` sebelum memperbarui tanggal dan delay. |
| Partial GR setelah keputusan | Bila liability sudah `confirmed`/`waived`, GR terlambat berikutnya hanya memperbarui `latest_late_receipt_date` dan mencatat event; `delay_days`, severity, dan poin tetap seperti saat diputuskan. |
| Severity kandidat | Kandidat `pending` menyimpan severity preview dari matrix dengan poin 0; konfirmasi menghitung ulang dari `delay_days` saat itu. |
| Pengecualian | `waived` juga menutup status penanganan menjadi `resolved` karena tidak ada tindak lanjut vendor. |
| Status penanganan | Form tindak lanjut menolak `resolved` selama liability masih `pending`. |
| Audit | Tabel append-only `vendor_compliance_incident_events` mencatat `candidate_created`, `delay_updated`, `confirmed`, dan `waived` beserta actor, alasan, dan metadata. |
| Notifikasi | Filament database notification setelah commit kepada user aktif dengan `edit vendor compliance incidents` saat kandidat baru dibuat. Notifikasi rejection existing tidak lagi terpicu oleh insiden Late Delivery. |
| Reconciliation | Command read-only `vendor-compliance:reconcile-late-delivery`. |

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Tutup keputusan bisnis dan characterization audit | Matrix final, schema/consumer map, baseline tests |
| 1 | Migration/model/domain service | Schema additive, relasi kompatibel, kalkulator deterministic |
| 2 | Integrasi Receiving dan idempotent assessment | Snapshot GR, exclusion PO Susulan, candidate upsert |
| 3 | Review Purchasing dan notifikasi | Confirm/waive Action, audit, resource/filter/detail |
| 4 | Regression, UAT, rollout readiness | Test suite, query/performance check, docs/report/runbook final |

## 28. Definition of Done

- [ ] Seluruh open decision blocking ditutup dan dicatat.
- [ ] Seluruh acceptance criteria terpenuhi.
- [ ] Test Receiving, PO Susulan, Vendor Compliance, concurrency, dan authorization lulus.
- [ ] Migration additive serta kompatibilitas insiden existing terbukti.
- [ ] Poin tidak aktif sebelum liability confirmed.
- [ ] Tidak ada mutation ESB baru atau payload Receiving yang berubah.
- [ ] Dokumentasi Purchasing/Inventory, data model generated, PRD, rollout, dan implementation report diperbarui.
- [ ] UAT staging ditandatangani Purchasing dan Inventory.
