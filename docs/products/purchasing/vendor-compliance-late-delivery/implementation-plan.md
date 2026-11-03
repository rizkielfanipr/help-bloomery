---
document_id: PURCHASING-VENDOR-COMPLIANCE-LATE-DELIVERY-PLAN
title: Implementation Plan — Vendor Compliance Keterlambatan Delivery PO
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/vendor-compliance-late-delivery/prd.md
  - docs/products/inventory/non-po-receiving/prd.md
  - docs/products/inventory/receiving-central-blss/prd.md
supersedes: null
---

# Implementation Plan — Vendor Compliance Keterlambatan Delivery PO

Acuan requirement: `docs/products/purchasing/vendor-compliance-late-delivery/prd.md`. Dokumen ini hanya mengatur urutan teknis; requirement dan keputusan bisnis final tetap berada di PRD.

## 1. Scope implementasi

- **Termasuk:** snapshot required date PO normal pada Goods Receipt, kalkulasi keterlambatan deterministic, status assessment, peringatan non-blocking Receiving, kandidat insiden Late Delivery idempotent per company + PO, exclusion PO Susulan, liability review Purchasing, scoring setelah konfirmasi, notifikasi internal, UI/filter/detail Vendor Compliance, audit, migration kompatibilitas, test, dokumentasi, dan rollout.
- **Tidak termasuk:** mutation PO/GR ESB baru, blocking Receiving, historical backfill otomatis, scorecard/ranking vendor, SLA supplier, suspend supplier, penilaian per item, poin per partial GR, reopen keputusan terminal, WhatsApp/email eksternal, atau dependency baru.

## 2. Baseline repository

- Baseline planning: `origin/main` commit `94e89952` pada 3 November 2026.
- `GoodsReceiptPage` sudah re-fetch PO sebelum memanggil `CreateGoodsReceiptAction`, tetapi hanya menyimpan purchase/receipt date dan belum menyimpan `requiredDate`.
- `CreateGoodsReceiptAction` shared oleh PO normal dan PO Susulan, menyimpan local evidence sebelum mutation ESB, serta membuat incident item-level ketika rejected qty positif.
- `VendorComplianceIncident` belum memiliki source key/liability/late-delivery fields.
- `goods_receipt_item_id` incident wajib dan unik; relation item singular. Ini adalah compatibility hotspot utama.
- Resource Vendor Compliance dan dua permission existing dapat dipakai ulang; create/delete manual tetap dilarang.
- PO Susulan dapat dikenali melalui workflow caller/relation, tetapi implementation harus meneruskan source eksplisit agar timing relation tidak menyebabkan false positive.
- Main worktree saat planning mempunyai perubahan ESB command milik user. Implementasi wajib dimulai dari branch/worktree bersih dan tidak membawa perubahan tersebut.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Migration | `database/migrations/*_add_delivery_assessment_to_goods_receipts_table.php` | Baru, additive |
| Migration | `database/migrations/*_extend_vendor_compliance_incidents_for_late_delivery.php` | Baru; nullable/index/constraint compatibility |
| Goods Receipt model/factory | `app/Models/GoodsReceipt.php`, `database/factories/GoodsReceiptFactory.php` | Fillable/cast/fixtures |
| Incident model/factory | `app/Models/VendorComplianceIncident.php`, `database/factories/VendorComplianceIncidentFactory.php` | Fields, scopes, casts, relations |
| Item model | `app/Models/GoodsReceiptItem.php` | Singular relation menjadi plural |
| Enum/value object | `app/Enums/*Delivery*`, `app/Enums/*Liability*` | Baru bila sibling pattern mendukung |
| Domain service | `app/Services/Purchasing/VendorCompliance/DeliveryDelayCalculator.php` | Baru; pure date assessment |
| Assessment action | `app/Actions/Purchasing/VendorCompliance/AssessLateDeliveryComplianceAction.php` | Baru; idempotent upsert/aggregate |
| Review action | `app/Actions/Purchasing/VendorCompliance/ReviewLateDeliveryIncidentAction.php` | Baru; confirm/waive transaction |
| Receiving action | `app/Actions/Inventory/Receiving/CreateGoodsReceiptAction.php` | Header snapshot dan assessment orchestration |
| Receiving page/view | `app/Filament/Casual/Pages/GoodsReceiptPage.php`, view/component terkait | Re-fetch required date dan warning |
| PO Susulan caller | `app/Actions/Purchasing/NonPoReceipt/`, resource/page terkait | Source marker/exclusion, tanpa perubahan workflow |
| Vendor Compliance UI | `app/Filament/Helpdesk/Resources/VendorComplianceIncidents/` | Table/filter/detail/review actions/null-safe content |
| Notification | notification class/pattern existing hasil Phase 0 | Kandidat pending setelah commit |
| Permission/policy | `app/Policies/VendorComplianceIncidentPolicy.php`, `config/permissions.php` | Reuse permission; hanya ubah bila audit membuktikan perlu |
| Tests | `tests/Feature/{GoodsReceiptPage,NonPoReceiptPurchasing,VendorComplianceResource,ReceivingAuthorization}Test.php`, test service/action baru | Characterization dan feature/regression |
| Docs | Purchasing/Inventory README, data model generated, folder fitur | Update saat implementasi |

Path final mengikuti sibling dan hasil audit Phase 0. Jangan membuat abstraction atau base folder baru hanya untuk satu class bila pola repository tidak mendukungnya.

## 4. Dependency dan integration boundary

- Tidak ada package baru.
- ESB hanya dibaca melalui `EsbGoodsReceiptService::purchaseOrder()` yang sudah dipanggil Receiving; tidak ada HTTP call tambahan di Action assessment.
- UI/page bertanggung jawab re-fetch PO dan membentuk header canonical; browser state bukan sumber kebenaran.
- `CreateGoodsReceiptAction` tetap write boundary Goods Receipt. Assessment action menerima model yang sudah persisted dan source PO yang eksplisit.
- `DeliveryDelayCalculator` pure dan tidak melakukan query/network call.
- Database lokal menjadi sumber snapshot dan keputusan liability.
- Notification internal dikirim setelah commit; kegagalan notifikasi tidak menggagalkan transaksi.
- Status/payload mutation GR ESB tetap tanggung jawab flow existing dan tidak bergantung pada hasil review Vendor Compliance.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | Tutup PRD §26, audit seluruh caller relasi incident, characterization test, dan migration rehearsal | PR implementasi yang sama | PRD direview Purchasing |
| 1 | Enum/value, calculator, migration, model/factory/relation compatibility | PR implementasi yang sama | Matrix dan schema decision final |
| 2 | Snapshot PO, source marker, assessment/upsert, PO Susulan exclusion, notification | PR implementasi yang sama | Domain tests Phase 1 lulus |
| 3 | Confirm/waive Action, Policy, audit/history, Filament index/detail/actions | PR implementasi yang sama | Candidate lifecycle stabil |
| 4 | Regression, concurrency/query checks, docs/report, staging UAT dan rollout readiness | PR implementasi yang sama | Seluruh flow selesai |

Satu PR disarankan karena schema tanpa null-safe UI atau assessment tanpa review menghasilkan fitur setengah jadi. Commit tetap dipisah per phase. Jangan membuat poin aktif sebelum review action dan audit tersedia.

## 6. Migration strategy

1. Gunakan `php artisan make:migration --no-interaction`; jangan menulis file migration manual.
2. Tambahkan kolom assessment pada `goods_receipts` sebagai nullable/default-compatible dan index status bila dipakai reconciliation.
3. Tambahkan field source/liability/date/scoring pada `vendor_compliance_incidents` sebagai nullable; `liability_status` default `not_applicable` untuk record existing.
4. Backfill default existing dalam migration atau application-safe path yang tidak mengubah status/poin existing.
5. Identifikasi nama index/foreign key aktual sebelum melepas unique `goods_receipt_item_id`; gunakan operasi eksplisit sesuai database engine target.
6. Ubah item FK menjadi nullable tanpa melepas referential integrity dan tetap cascade sesuai kontrak existing sampai keputusan deletion diaudit.
7. Buat unique source constraint yang menerima null untuk incident legacy tetapi menjamin satu `late_delivery` source key. Bila perilaku null berbeda antar engine, gunakan source key unique tunggal atau generated strategy yang teruji.
8. Tambahkan index sesuai query: `(category, liability_status)`, `(status, occurred_at)`, source key, serta Goods Receipt assessment/reference bila diperlukan.
9. Jangan drop kolom atau data. Down migration hanya untuk development dan harus menolak/menangani data yang tidak dapat kembali ke constraint singular.
10. Jalankan migration rehearsal dengan fixture: incident rejection existing, dua incident pada satu item, dan header-level late incident.

## 7. Data compatibility

- Semua incident existing dianggap `liability_status = not_applicable` dan tetap mempunyai poin/status semula.
- Rejection creation berpindah dari relasi singular ke plural tanpa mengubah payload field.
- Late Delivery memakai item null dan UI level PO; semua accessor/export/resource wajib null-safe.
- Goods Receipt historis tidak diubah dan tampil `Belum Dinilai`.
- PO Susulan existing/new tidak dinilai; status assessment opsional `not_applicable` hanya ditulis untuk record baru bila source diketahui.
- Route, navigation, permission, status penanganan, dan action follow-up existing dipertahankan.
- Required date tidak dihitung ulang setelah receipt dibuat.

## 8. Authorization

- `view vendor compliance incidents`: index/detail dan data Late Delivery.
- `edit vendor compliance incidents`: confirm/waive serta tindak lanjut existing.
- Tidak ada permission baru kecuali audit Phase 0 membuktikan pemisahan reviewer wajib; bila ditambah, perbarui PRD/config/RBAC/rollout terlebih dahulu.
- Action review memanggil Policy, mengunci record, memvalidasi kategori/status/transisi, dan mengambil reviewer dari user login.
- User Receiving tidak dapat memilih liability, poin, required date, source key, atau supplier melalui browser.
- Visibility action Filament mengikuti Policy, tetapi server-side Action tetap menjadi kontrol utama.

## 9. UI

### Receiving

- Reuse section tanggal/header existing; tampilkan required date dan callout amber hanya ketika valid dan terlambat.
- Copy menggunakan istilah **terdeteksi terlambat** dan **akan diverifikasi Purchasing**, bukan menyatakan vendor bersalah.
- Null/invalid date memakai neutral state **Tidak Dapat Dinilai**.
- Submit tetap aktif dan flow foto/QC/idempotency tidak berubah.

### Vendor Compliance

- Pertahankan Filament Resource existing, tidak membuat menu baru.
- Jadikan table/infolist/form kontekstual berdasarkan kategori: item/qty untuk rejection; required/received/delay/liability untuk Late Delivery.
- Tambahkan category/liability filters, pending badge, dan server-side search/sort yang relevan.
- Implementasikan confirm/waive sebagai Filament action yang memanggil Action class, bukan update model langsung.
- Follow-up form existing hanya mengubah field penanganan dan tidak boleh mengubah liability/scoring.
- Periksa 360/390/768/1024/1440 px, dark mode existing, focus modal, loading, dan double-submit.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Characterization rejection incident creation, GoodsReceipt send states, PO Susulan flow, VendorCompliance Resource follow-up |
| 1 | Migration fixture, casts/relations/scopes, calculator date boundaries/timezone/invalid, existing incident compatibility |
| 2 | PO normal on-time/late/not-assessable, all hold/rejected, ESB success/failed/unknown, source exclusion, duplicate/concurrent partial GR |
| 3 | Policy matrix, pending points 0, confirm matrix/version/audit, waiver reason, terminal transition, notification fake, Filament filter/detail/actions |
| 4 | Coexistence rejection + late, target suites, query count/badge, migration up/down rehearsal where safe, manual responsive UAT |

## 11. Observability

- Assessment fields pada Goods Receipt menyediakan reconciliation source.
- Incident menyimpan deterministic source key, snapshots, maximum delay, scoring version, reviewer, dan alasan waiver.
- Gunakan audit/history mechanism existing; bila tidak ada yang menangkap field review, buat append-only history minimal untuk liability decisions.
- Tambahkan structured log aman untuk assessment failure/duplicate recovery, tanpa raw ESB payload.
- Sediakan query/command read-only untuk jumlah `late` tanpa incident, incident duplicate, pending age, confirmed, dan waived.
- Navigation badge existing tetap menghitung incident unresolved; liability pending ditampilkan lewat filter/badge UI, tidak menambah query per row.

## 12. Rollout

Ikuti [rollout.md](rollout.md): rehearsal migration → deploy schema/code → permission sync → smoke test Receiving → UAT review Purchasing → monitor reconciliation. Historical backfill dan auto repair tidak dijalankan.

## 13. Rollback

- Bila assessment bermasalah, nonaktifkan feature flag/config bila dibuat dan rollback application artifact.
- Pertahankan schema additive agar versi lama tetap berjalan dengan kolom tambahan.
- Jangan mengembalikan item unique constraint selama sudah ada header/multiple incident.
- Jangan menghapus kandidat atau mengubah poin confirmed otomatis.
- Bila UI baru bermasalah, permission/menu existing tetap mengakses resource lama hanya setelah null-safety versi rollback dipastikan; utamakan hotfix/flag dibanding destructive rollback.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Constraint/relation change merusak QC incident | Characterization test dan migration fixture sebelum implementasi assessment |
| Caller PO Susulan tidak membawa source | Kontrak header/source wajib dan regression test shared Action |
| Partial GR race membuat duplicate | Unique source key, transaction, row lock/upsert, retry duplicate-key yang terkontrol |
| Resource mengakses item null | Audit seluruh caller/export dan kategori-aware presentation |
| Matrix poin berubah | Scoring rule version dan central calculator/config |
| Peringatan UI memakai stale PO | Hitung final dari re-fetch server; UI preview boleh berubah saat submit |
| Down migration tidak aman | Schema additive dipertahankan saat rollback production |

## 15. Quality gates

- [ ] PRD §26 ditutup dan PRD berstatus approved/in-development sebelum coding
- [ ] Characterization test incident existing lulus sebelum schema change
- [ ] Migration rehearsal dengan data legacy/header/multi-incident lulus
- [ ] Targeted service/action/Receiving/PO Susulan/Vendor Compliance tests lulus
- [ ] Concurrency/idempotency/partial GR tests lulus
- [ ] Authorization dan terminal transition tests lulus
- [ ] HTTP fake membuktikan tidak ada mutation ESB baru
- [ ] Query count index/navigation badge diverifikasi
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `bash ops/ci/check-doc-links.sh`
- [ ] `bash ops/ci/check-code-map.sh`
- [ ] `git diff --check`
- [ ] Responsive, dark mode, warning, confirm/waive, dan failure-state UAT staging dicatat
- [ ] CI Pull Request hijau

## 16. Definition of Done

- [ ] Semua phase dan acceptance criteria PRD selesai.
- [ ] Receiving normal/PO Susulan serta incident rejection tidak mengalami regresi.
- [ ] Candidate, review, scoring, audit, dan partial delivery teruji end-to-end.
- [ ] README Purchasing/Inventory dan generated data model sesuai kode aktual.
- [ ] Implementation report dibuat di `reports/implementation-report.md`.
- [ ] Implementation prompt dihapus setelah implementasi selesai.
