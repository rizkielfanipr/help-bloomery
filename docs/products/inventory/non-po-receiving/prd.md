---
document_id: INVENTORY-NON-PO-RECEIVING-PRD
title: PRD — Penerimaan Tanpa PO dan PO Susulan
status: draft
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/inventory/README.md
  - docs/products/purchasing/README.md
  - docs/products/inventory/non-po-receiving/implementation-plan.md
  - docs/products/inventory/non-po-receiving/rollout.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
  - docs/architecture/panels.md
  - docs/architecture/rbac.md
supersedes: null
---

# PRD — Penerimaan Tanpa PO dan PO Susulan

> Dokumen ini adalah kontrak fitur exception workflow. Implementasi belum dimulai selama status dokumen masih `draft`.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Inventory dan Purchasing |
| Fitur | Penerimaan Tanpa PO / PO Susulan |
| Status | draft |
| Prioritas | P1 |
| Target pengguna | Inventory Pusat dan Purchasing Staff |
| Modul terdampak | Employee App Receiving, Back Office Purchasing, Back Office Receiving, System Monitoring |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

Inventory membutuhkan cara terkontrol untuk mencatat barang yang sudah datang ketika PO belum tersedia. Penerimaan awal disimpan hanya pada database lokal, lengkap dengan dokumen, produk, batch, dan QC, lalu diteruskan ke antrean Purchasing untuk dibuatkan PO secara manual di ESB. Setelah nomor PO dimasukkan, aplikasi memverifikasi company, branch, status, item, UOM, dan outstanding qty dari PO sebelum memproses Goods Receipt. Barang tetap berada pada hold administratif sampai PO dan GR berhasil agar stok fisik tidak dianggap tersedia sebelum tercatat di ESB.

## 3. Latar belakang

Receiving existing selalu dimulai dari PO ESB berstatus Authorized/Receiving. Dalam kondisi tertentu, barang datang lebih dahulu sementara PO masih diproses. Tanpa workflow khusus, tim berisiko mencatat di luar sistem, kehilangan bukti penerimaan/QC, menautkan PO salah, atau menggunakan barang sebelum stok ESB bertambah.

## 4. Baseline sistem

Audit repository per 2 November 2026 menemukan:

- Tile **Receiving** hanya mendukung pemilihan PO ESB company `BLSS`.
- PO menjadi sumber supplier, branch, item, Product Detail ID, UOM, ordered/outstanding qty, dan lokasi.
- `GoodsReceipt.reference_number` diasumsikan sebagai nomor PO; status model berorientasi hasil GR ESB.
- Submit existing menyimpan QC lokal, memakai `submission_key`, memanggil `EsbGoodsReceiptService::create()`, dan mencatat mutation melalui `EsbTransactionRecorder`.
- Hanya qty Accepted yang dikirim ke ESB; Hold/Rejected tetap tercatat lokal.
- `PurchaseRequest` adalah permintaan sebelum pembelian dan satu item per request; `PurchasingRequest` tidak mempunyai UI/ESB linkage yang sesuai.
- Repository dapat membaca PO dan membuat GR, tetapi tidak mempunyai kontrak API membuat PO ESB.
- Pola branch/location/Master Product tersedia pada Item Journal dan pola modal product picker tersedia pada BOM.
- Master Supplier API tidak diperlukan pada desain yang disepakati karena supplier awal berupa teks.

## 5. Problem statement

1. Penerimaan fisik tanpa PO belum mempunyai record lokal canonical dan audit trail.
2. Purchasing belum mempunyai antrean resmi untuk PO susulan.
3. Barang berisiko dipakai sebelum tercatat sebagai stok ESB.
4. Produk manual berisiko salah mapping ke PO.
5. Nomor PO dari user harus diverifikasi server-side.
6. GR otomatis harus idempotent dan menangani hasil eksternal tidak pasti.
7. Exception workflow tidak boleh mencemari Purchase Request atau status GR normal.

## 6. Goals

1. Mencatat penerimaan tanpa PO melalui tile Receiving.
2. Menyimpan supplier sebagai teks lokal dan produk/branch/location sebagai reference ESB.
3. Menyediakan antrean **PO Susulan** untuk Purchasing.
4. Memverifikasi PO secara exact sebelum GR.
5. Memproses GR setelah aksi eksplisit Purchasing dan seluruh validation lulus.
6. Menjaga hold administratif, QC, audit, idempotency, dan monitoring.
7. Mempertahankan Receiving dengan PO existing tanpa regresi.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Penerimaan tanpa PO tercatat | Tidak tersedia | 100% melalui record lokal | Data non-PO receipt |
| Mutation ESB sebelum PO valid | Tidak berlaku | 0 | Test/System Monitoring |
| PO salah tertaut | Tidak ada kontrol | 0 | Validation mismatch |
| Duplicate GR | Idempotency alur normal saja | 0 | Constraint dan double-submit test |
| Audit transition | Tidak tersedia | 100% actor/time/reason tercatat | Status history |

## 8. Non-goals

- Membuat PO melalui API ESB atau memakai Master Supplier picker.
- Mengizinkan barang dipakai sebelum PO dan GR selesai.
- Membuat temporary stock/Item Journal sebelum GR.
- Mengubah Receiving dengan PO existing.
- Menggunakan `PurchaseRequest`/`PurchasingRequest` existing.
- Mengizinkan product bebas teks atau multi-PO pada MVP.
- Menambah SLA scheduler atau Finance threshold approval pada MVP.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Inventory Staff | Draft, pilih produk, isi QC, submit | `access employee app goods receipt`, `create non po receipts`, `submit non po receipts` |
| Purchasing Staff | Proses antrean, revisi, buat PO manual, verifikasi PO, memicu GR | `view non po purchase order requests`, `process non po purchase order requests` |
| Authorized Purchasing | Membatalkan dengan alasan/return evidence | `cancel non po purchase order requests` |
| Back Office viewer | Melihat hasil sesuai scope | `view goods receipts` dan view non-PO sesuai role |

## 10. User journey

1. Inventory membuka tile **Receiving** dan memilih **Dengan PO** atau **Tanpa PO**.
2. Pada Tanpa PO, company terkunci `BLSS`; user memilih branch dan location ESB.
3. User mengisi supplier teks, tanggal, alasan, dokumen, dan foto.
4. User menambah produk melalui modal Master Product seperti **Tambah Komponen** BOM.
5. User mengisi physical/accepted/hold/rejected qty, batch, expiry, QC, dan bukti item.
6. User menyimpan draft atau **Kirim ke Purchasing**.
7. Sistem membuat nomor `NPR-*`, menyimpan snapshot lokal, dan memberi notifikasi.
8. Purchasing membuka **Purchasing → PO Susulan** dan menekan **Mulai Proses PO**.
9. Purchasing membuat PO manual di ESB, memasukkan nomor PO, lalu menekan **Verifikasi PO & Proses GR**.
10. Server mengambil PO terbaru, mencocokkan Accepted item melalui Product Detail ID + UOM, dan memeriksa outstanding.
11. Supplier dan item resmi PO disimpan sebagai snapshot authoritative; teks awal tetap untuk audit.
12. Sistem membuat `GoodsReceipt`, mengirim Accepted qty ke ESB, dan menautkan record.
13. Status menjadi `completed`, `gr_failed`, atau `gr_unknown` dan hasil terlihat di dua modul.

## 11. Business rules

1. Hanya company `BLSS` pada MVP; entry tetap melalui tile **Receiving**.
2. Satu non-PO receipt hanya untuk satu PO dan satu Goods Receipt.
3. Supplier input adalah teks wajib; supplier authoritative mengikuti PO.
4. Supplier mismatch tampil sebagai warning dan memerlukan konfirmasi Purchasing, bukan exact blocker.
5. Produk wajib dipilih dari Master Product; input bebas teks dilarang.
6. Product Detail ID + UOM yang sama tidak boleh diduplikasi.
7. Company/branch/location/product/UOM diverifikasi dari server.
8. `accepted_qty + hold_qty + rejected_qty = physical_qty`.
9. Hanya Accepted qty yang memerlukan PO mapping dan dikirim ke ESB.
10. Accepted item wajib exact match Product Detail ID + UOM pada PO.
11. Accepted qty agregat tidak boleh melebihi outstanding PO.
12. Barang tetap hold administratif sampai GR berhasil.
13. Draft dapat diedit Inventory; setelah submit hanya melalui `revision_requested`.
14. PO harus Authorized/Receiving dan diperiksa ulang sebelum mutation.
15. PO yang sama tidak boleh tertaut ke receipt lain.
16. Mengetik nomor PO tidak memicu mutation; action eksplisit wajib.
17. Connection failure menghasilkan `gr_unknown`; retry otomatis dilarang sebelum reconciliation.
18. All-Hold tetap lokal pada `qc_hold`; all-Rejected ditutup `qc_rejected` dengan bukti return/alasan.
19. Mixed Accepted/Rejected memproses Accepted dan mempertahankan seluruh QC lokal.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Receiving menyediakan mode Dengan PO/Tanpa PO | Must |
| FR-2 | Non-PO form mendukung draft lokal | Must |
| FR-3 | Branch/location, supplier teks, alasan, dokumen, dan foto tercatat | Must |
| FR-4 | Produk dipilih melalui modal Master Product reusable | Must |
| FR-5 | Batch, expiry, QC, dan foto mengikuti Receiving existing | Must |
| FR-6 | Submit membuat nomor unik dan notifikasi Purchasing | Must |
| FR-7 | Purchasing mempunyai submenu PO Susulan dan badge antrean | Must |
| FR-8 | Purchasing dapat process/revision/cancel dengan audit | Must |
| FR-9 | PO selalu diambil terbaru dari ESB | Must |
| FR-10 | Exact item/UOM/outstanding validation memblokir mismatch | Must |
| FR-11 | PO authoritative tidak menghapus snapshot lokal | Must |
| FR-12 | Verifikasi sukses memproses GR idempotent | Must |
| FR-13 | GR final menampilkan source/link PO Susulan | Must |
| FR-14 | Failed/unknown terlihat dan dapat direkonsiliasi | Must |
| FR-15 | Receiving existing tetap lulus regression test | Must |

## 13. Status dan lifecycle

| Status | Makna | Transisi | Aktor |
|---|---|---|---|
| `draft` | Inventory mengisi | `awaiting_purchasing`, `qc_hold`, `qc_rejected`, `cancelled` | Inventory |
| `awaiting_purchasing` | Menunggu PO | `po_in_progress`, `revision_requested`, `cancelled` | Purchasing |
| `po_in_progress` | PO dibuat manual | `po_linked`, `revision_requested`, `cancelled` | Purchasing |
| `revision_requested` | Perlu perbaikan Inventory | `awaiting_purchasing`, `cancelled` | Inventory |
| `po_linked` | PO sudah verified/snapshotted | `gr_processing` | Sistem/Purchasing |
| `gr_processing` | Mutation GR diproses | `completed`, `gr_failed`, `gr_unknown` | Sistem |
| `gr_failed` | ESB menolak definitif | Manual resolution | Authorized role |
| `gr_unknown` | Hasil koneksi tidak pasti | Reconciliation | Authorized role |
| `qc_hold` | Tidak ada Accepted qty | `awaiting_purchasing`, `qc_rejected`, `cancelled` | Inventory/QC |
| `qc_rejected` | Seluruh barang ditolak/return | Terminal | Inventory/QC |
| `completed` | GR berhasil/partial sesuai Accepted | Terminal | Sistem |
| `cancelled` | Proses dihentikan dengan alasan | Terminal | Authorized role |

## 14. Model data konseptual

### `NonPoReceipt`

Nomor internal, company/branch/location, receipt date, supplier text/contact, reason, document/invoice, photos, status, submission key/hash, actor/timestamp, PO number/snapshot, dan nullable unique Goods Receipt link.

### `NonPoReceiptItem`

Snapshot Master Product dan UOM, physical/Accepted/Hold/Rejected qty, optional invoice price, QC, evidence, notes, serta PO purchase-detail/outstanding mapping.

### `NonPoReceiptBatch`

Item relation, batch number, qty, manufactured date, expiry date.

### `NonPoReceiptStatusHistory`

From/to status, actor, notes/reason, timestamp.

Constraint: internal number unique, submission key unique, linked PO unique saat terisi, Goods Receipt link unique, dan product-detail/UOM unique per receipt.

## 15. Integration contract

- Branch/location dan Master Product memakai read service/pattern existing.
- PO diverifikasi melalui `EsbGoodsReceiptService::purchaseOrder()` atau shared reader.
- Tidak ada create PO API; Purchasing membuat PO manual.
- GR memakai `EsbGoodsReceiptService::create()` melalui shared Action.
- Mutation dicatat `EsbTransactionRecorder` dengan idempotency key non-PO.
- Connection failure mengikuti `unknown`; tidak blind retry.

## 16. Authorization dan security

1. Page tetap memerlukan `access employee app goods receipt` dan action permission khusus.
2. Policy membatasi create/submit ke Inventory dan process/cancel ke Purchasing.
3. Company `BLSS` server-controlled; branch/location/product/UOM di-lookup ulang.
4. PO snapshot dari browser tidak dipercaya.
5. Transition memakai transaction, `lockForUpdate()`, dan status recheck.
6. File JPEG/PNG/WebP, size mengikuti Receiving, private storage, ownership path per record.
7. Submitted record tidak hard-delete melalui UI; cancellation diaudit.

## 17. UI/UX specification

### Employee App Receiving

- Pilihan **Dengan PO** dan **Tanpa PO**, mobile-first.
- Save Draft dan Kirim ke Purchasing.
- Product picker seperti Tambah Komponen BOM: search kode/nama, filter, pagination, multi-select, loading/empty/error.
- Item menampilkan produk/UOM/qty split/batch/QC/foto.
- History menampilkan progress Purchasing.

### Purchasing → PO Susulan

- Label **PO Susulan**, title **Permintaan PO Susulan dari Receiving**, badge pending/revision.
- List menampilkan nomor, tanggal, supplier, branch, item/qty, dokumen, alasan, status, age, dan PIC.
- Detail read-only snapshot/QC dengan aksi process/revision/verify+GR/cancel.
- Comparison matrix local vs PO; exact mismatch memblokir, supplier mismatch warning+confirmation.

### Back Office Receiving

- GR final memiliki badge `PO Normal`/`PO Susulan` dan link local receipt/PO/GR.
- Awaiting PO tidak dicampur ke daftar GR final.

## 18. Non-functional requirements

- Product search server-side/paginated; tidak memuat seluruh catalog.
- Mutation status transaction + row lock; HTTP tidak di dalam transaction panjang.
- Idempotency mencegah duplicate request/GR.
- Unknown state dapat direkonsiliasi tanpa blind retry.
- Index queue pada status/time/company/branch/PO.
- Upload failure/cancel tidak meninggalkan orphan.

## 19. Observability dan audit

- Status history untuk tiap transition.
- System Monitoring membedakan source PO Normal/PO Susulan.
- Log aman memuat ID/status, bukan dokumen/foto/payload penuh.
- Queue menampilkan age tanpa scheduler.
- Notification untuk submit, revision, GR result, dan unknown.

## 20. Data migration dan kompatibilitas

- Migration additive membuat empat tabel dan optional source link pada Goods Receipt bila diperlukan.
- Tidak ada backfill; GR existing default/fallback `purchase_order`.
- Permission baru disinkronkan dan role diberikan melalui UI/seed sesuai kebijakan.
- Route/status/data Receiving existing tetap kompatibel.
- Data model diregenerasi setelah implementasi.

## 21. Testing strategy

- Characterization Receiving existing sebelum shared Action refactor.
- Draft/submit local dan no-ESB-before-PO test.
- Policy/transition/branch/product tampering test.
- Product picker search/pagination/duplicate test.
- PO mismatch matrix dan duplicate link test.
- Accepted-only/mixed QC/idempotency/double-click/failed/unknown/monitoring test.
- Transaction rollback, upload cleanup, notification, menu/badge/link test.

## 22. Rollout

Migration, permission sync, private storage verification, staging UAT dua role, dan validasi GET PO/POST GR mengikuti [rollout.md](rollout.md). Tidak ada env, queue, scheduler, atau create-PO integration baru.

## 23. Rollback

Application dapat di-rollback tanpa menghapus record. Migration rollback hanya sebelum data dipakai; sesudah production gunakan forward fix dan sembunyikan entry point. Record in-flight tetap disimpan untuk audit/manual resolution.

## 24. Acceptance criteria

1. Receiving existing tetap berjalan.
2. Inventory dapat draft/submit non-PO dari tile Receiving.
3. Supplier teks; product/branch/location terstruktur.
4. Product picker mengikuti pola BOM dan mencegah duplicate identity.
5. Submit tidak memanggil GR ESB dan hanya membuat satu queue item.
6. Purchasing dapat process/revision/cancel dengan audit.
7. PO selalu diverifikasi dari ESB.
8. Accepted item exact match Product Detail ID + UOM dan tidak melebihi outstanding.
9. Supplier PO authoritative dan mismatch terlihat/terkonfirmasi.
10. Verifikasi+GR menghasilkan maksimal satu Goods Receipt/mutation.
11. Hanya Accepted qty dikirim; Hold/Rejected tidak menambah stok.
12. `gr_unknown` tidak auto-retry.
13. GR final menunjukkan source/link PO Susulan.
14. Permission, scope, files, transaction, dan status guard diuji.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Pembelian retroaktif tanpa kontrol | Audit/keuangan | Permission, alasan/dokumen, immutable history |
| Barang digunakan lebih awal | Selisih stok | Hold administratif |
| Supplier teks berbeda | PO salah | PO authoritative + warning/confirmation |
| Produk/UOM salah | GR salah | Master Product + exact match |
| Qty kurang | Over-receipt | Outstanding validation terbaru |
| Duplicate PO/GR | Double stock | Unique link, idempotency, row lock |
| Koneksi putus | Hasil tidak pasti | `gr_unknown`, monitoring, reconciliation |
| Logic terduplikasi | Workflow drift | Shared Action setelah characterization |

## 26. Open decisions

Tidak ada blocker dokumentasi. Default MVP: hold sampai GR; PO manual; satu receipt/satu PO; exact product match tanpa manual remap; price optional; PO untuk Accepted qty; cancel memerlukan alasan/bukti; create-PO API dan Finance threshold di luar scope.

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Characterization dan kontrak | Baseline + schema/API/picker contract |
| 1 | Aggregate lokal dan Inventory flow | Model/migration/policy/draft/submit/QC/notification |
| 2 | Purchasing queue dan PO verify | Menu, transition, exact comparison |
| 3 | Shared GR Action | Idempotent GR, monitoring, unknown handling |
| 4 | Regression dan rollout | Test, UAT, docs, report, PR |

## 28. Definition of Done

- [ ] Acceptance criteria terpenuhi dan Receiving normal tidak regresi.
- [ ] Permission, migration, transaction, idempotency, QC, dan unknown state diuji.
- [ ] Product picker/PO matching lulus UAT.
- [ ] README Inventory/Purchasing, architecture, data model, dan report diperbarui.
- [ ] Rollout/rollback staging terverifikasi.
