---
document_id: PURCHASING-VENDOR-COMPLIANCE-LATE-DELIVERY-PROMPT
title: Prompt Implementasi — Vendor Compliance Keterlambatan Delivery PO
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/vendor-compliance-late-delivery/prd.md
  - docs/products/purchasing/vendor-compliance-late-delivery/implementation-plan.md
supersedes: null
---

# Prompt Implementasi — Vendor Compliance Keterlambatan Delivery PO

Salin blok berikut ke agent implementasi dari root repository setelah seluruh keputusan blocking PRD §26 disetujui.

```text
Implementasikan Vendor Compliance Keterlambatan Delivery PO pada domain Purchasing dan Inventory di branch `feature/vendor-compliance-late-delivery`.

Jangan mulai application code bila `docs/products/purchasing/vendor-compliance-late-delivery/prd.md` masih berstatus draft atau keputusan §26 belum ditutup. Laporkan keputusan yang belum final kepada user terlebih dahulu.

## Dokumen sumber yang wajib dibaca
- docs/products/purchasing/vendor-compliance-late-delivery/prd.md — sumber requirement canonical.
- docs/products/purchasing/vendor-compliance-late-delivery/implementation-plan.md — phase dan quality gate.
- docs/products/purchasing/vendor-compliance-late-delivery/rollout.md — deployment/UAT/rollback.
- docs/products/purchasing/README.md — perilaku Vendor Compliance saat ini.
- docs/products/inventory/README.md — perilaku Receiving saat ini.
- docs/products/inventory/non-po-receiving/prd.md — kontrak PO Susulan yang wajib dikecualikan.
- docs/products/inventory/receiving-central-blss/prd.md — authorization/cabang Receiving.
- docs/standards/code-remediation.md — arsitektur, keamanan data, dan kualitas teknis.
- docs/standards/ui-consistency.md — standar UI.
- docs/architecture/data-model.md, panels.md, dan rbac.md.
- AGENTS.md dan .claude/skills/feature-delivery/SKILL.md — aturan repository/workflow.

## Hasil akhir yang diharapkan
Receiving PO normal mengambil ulang `requiredDate` PO dari ESB, menyimpan snapshot dan assessment keterlambatan, serta tetap memproses GR tanpa blok. GR terlambat membuat satu kandidat `late_delivery` per company + nomor PO dengan poin 0. Purchasing memverifikasi kandidat menjadi `confirmed` atau `waived`; poin hanya aktif saat confirmed. Partial GR memperbarui keterlambatan maksimum tanpa incident/poin ganda. PO Susulan dan incident rejection existing tetap aman.

## Scope
- Migration kompatibilitas Goods Receipt dan Vendor Compliance Incident.
- Kalkulator tanggal deterministic Asia/Jakarta dan assessment status.
- Source marker eksplisit PO normal vs PO Susulan.
- Snapshot required date dan delay pada Goods Receipt.
- Candidate upsert idempotent/concurrent-safe per company + PO.
- Liability lifecycle pending/confirmed/waived dan scoring version.
- Warning Receiving non-blocking.
- Vendor Compliance index/filter/detail/actions kategori-aware.
- Internal notification, audit/history, observability/reconciliation read-only.
- Tests, documentation, implementation report, dan rollout readiness.

## Non-scope
- Historical backfill/auto repair.
- Mutation ESB baru atau perubahan payload GR.
- Blocking Receiving atau auto reject barang.
- PO Susulan assessment.
- Per-item/per-GR late points, scorecard/ranking/SLA vendor, suspend supplier.
- Reopen terminal liability pada v1.
- WhatsApp/email eksternal, API publik, atau dependency baru.

## Modul terdampak
- Inventory Receiving dan `CreateGoodsReceiptAction`.
- PO Susulan caller sebagai explicit exclusion.
- GoodsReceipt/GoodsReceiptItem/VendorComplianceIncident models serta factories.
- Purchasing Vendor Compliance Resource, Policy, Action, notification, dan tests.
- Purchasing/Inventory docs dan generated architecture data model.

## Aturan repository
- Jalankan `git status --short`; stop bila branch/worktree kotor oleh perubahan yang tidak terkait.
- Branch dari latest `origin/main`; jangan membawa perubahan ESB command milik user dari main worktree lain.
- Aktifkan workflow feature-delivery dan ubah PRD ke `in-development` hanya setelah approval.
- Sebelum code change, gunakan Laravel Boost `search-docs` untuk migration, transaction/locking, Filament actions/tables/infolists, notifications, dan Pest sesuai versi package repository.
- Ikuti sibling convention dan gunakan `php artisan make:* --no-interaction` untuk file baru.
- Jangan menambah/mengubah dependency tanpa persetujuan.
- Jalankan `vendor/bin/pint --dirty --format agent` setelah mengubah PHP.

## Aturan arsitektur
- Mutation berada di Action/Service, bukan langsung di Resource, Page, atau Blade.
- `DeliveryDelayCalculator` pure: tanpa database/network; normalisasi tanggal pada Asia/Jakarta.
- `GoodsReceiptPage` melakukan re-fetch PO server-side dan meneruskan header canonical; jangan percaya required date dari browser lama.
- `CreateGoodsReceiptAction` tetap write boundary receipt. Assessment berlangsung pada local persistence dan tidak bergantung pada hasil mutation ESB.
- Gunakan source type eksplisit agar shared Action tidak menilai PO Susulan berdasarkan timing relation.
- Candidate creation/update dan liability review memakai transaction, row lock/upsert, unique source key, serta deterministic handling duplicate-key.
- Satu incident `late_delivery` per company + normalized PO; partial GR hanya memperbarui first/latest/max delay sesuai PRD.
- Jangan membuat incident/poin per item untuk Late Delivery.
- Poin pending/waived harus 0. Scoring hanya dari aturan final PRD dan simpan `scoring_rule_version`.
- Pertahankan status penanganan existing sebagai dimensi terpisah dari liability.
- Insiden rejection dan late dapat coexist; ubah relation item ke plural dengan compatibility audit seluruh caller.
- Notifikasi dikirim setelah commit dan kegagalannya tidak membatalkan bisnis.

## Aturan migration dan data safety
- Migration additive; jangan hapus atau rewrite data production.
- Tambahkan field nullable/default-compatible lebih dulu.
- Audit nama FK/index aktual dan database engine sebelum melepas unique `goods_receipt_item_id` atau membuatnya nullable.
- Pertahankan foreign key; jangan membuat orphan incident.
- Backfill liability existing ke `not_applicable` tanpa mengubah poin/status/action.
- Unique source strategy harus aman terhadap null incident legacy dan concurrency.
- Rehearsal migration dengan fixture incident existing, header-level late, serta multiple incident per item.
- Jangan menjalankan historical backfill, repair, atau query mutation production.
- Production rollback mempertahankan schema additive; jangan mencoba mengembalikan unique item constraint setelah data baru ada.

## Aturan authorization
- Reuse `view vendor compliance incidents` untuk read dan `edit vendor compliance incidents` untuk confirm/waive/follow-up, kecuali PRD diperbarui dan user menyetujui permission baru.
- Policy dan Action mengotorisasi ulang; visibility tombol bukan kontrol keamanan.
- Reviewer/actor dari user login; company, PO, required date, supplier, source key, liability, dan poin bukan input browser bebas.
- Validate category `late_delivery`, current liability, required waiver reason, dan terminal transition di server.
- Incident tidak dapat dibuat/dihapus manual melalui Resource.

## Aturan UI
- Ikuti `docs/standards/ui-consistency.md` dan komponen/sibling Filament existing.
- Receiving menampilkan required date dan callout amber non-blocking. Gunakan kata “terdeteksi”/“akan diverifikasi”, bukan langsung menyalahkan vendor.
- Empty/invalid required date menjadi state netral “Tidak Dapat Dinilai”.
- Satu menu Vendor Compliance; jangan membuat menu Late Delivery terpisah.
- Table/infolist/form kategori-aware dan null-safe: item/qty untuk QC, tanggal/delay/liability untuk Late Delivery.
- Tambah filter kategori/liability, pending badge, preview poin pada confirm, dan alasan wajib pada waiver.
- Action review memanggil Action class. Gunakan loading/double-submit protection dan modal accessible.
- Periksa viewport 360, 390, 768, 1024, 1440 serta dark mode existing.

## Aturan integration
- Gunakan `EsbGoodsReceiptService::purchaseOrder()` existing; tidak ada endpoint baru.
- Jangan menambah network call dari assessment Action.
- Parser menerima date/ISO offset/null/invalid secara fail-safe; jangan menebak required date.
- Semua test integration memakai HTTP fake; jangan memanggil ESB sungguhan.
- Keterlambatan tidak mengubah request payload GR, auto close, QC disposition, atau send/retry behavior.
- Hasil ESB failed/unknown tetap dapat mempunyai kandidat karena penerimaan fisik sudah dicatat.

## Tahapan implementasi
1. Audit status branch, PRD §26, schema/index/caller/export, lalu tulis characterization tests untuk incident rejection, Vendor Compliance follow-up, Receiving, dan PO Susulan.
2. Buat enum/value/calculator, migration, model/factory/cast/relation; jalankan migration compatibility tests.
3. Integrasikan re-fetch required date, source marker, Goods Receipt snapshot, assessment/upsert, dan notification.
4. Implementasikan review Action, Policy checks, audit/history, serta scoring dari matrix final.
5. Perbarui Receiving warning dan Vendor Compliance table/filter/detail/actions secara kategori-aware.
6. Jalankan targeted tests, concurrency/query checks, formatter, docs checks, dan UAT staging.
7. Update README Purchasing/Inventory agar sesuai code, regenerate data model, set PRD/report, hapus implementation prompt setelah fitur selesai, lalu deliver sesuai feature-delivery.

## Test minimum
- Characterization insiden rejection existing dan follow-up Purchasing.
- Unit calculator: before/equal/after, leap day, ISO offset, null/invalid.
- Receiving: on-time, late, not-assessable, all hold/rejected, ESB success/failed/unknown.
- PO Susulan tidak menghasilkan Late Delivery.
- Duplicate submission, concurrent/partial GR, unique source, max delay update.
- Rejection + late coexist; item relation plural; UI item null aman.
- Policy matrix dan unauthorized review.
- Pending 0 point; confirm matrix/version/audit; waiver reason/0 point; terminal transitions.
- Filament index/filter/search/detail/action visibility/follow-up regression.
- Notification fake dan query-count/navigation badge.
- Migration fixture legacy/header/multiple incident.

## Quality gate
- `php artisan test --compact` untuk seluruh file terdampak.
- Untuk perubahan luas, jalankan shard Feature sesuai feature-delivery.
- `vendor/bin/pint --dirty --format agent`.
- `bash ops/ci/check-doc-links.sh`.
- `bash ops/ci/check-code-map.sh`.
- `git diff --check`.
- Catat hasil manual UAT responsive/dark mode dan failure states.

## Format laporan akhir
1. Branch aktif dan commit baseline.
2. Keputusan PRD §26 yang dipakai.
3. Ringkasan perubahan per phase.
4. Migration/data compatibility dan rollback notes.
5. File dibuat/diubah.
6. Test/formatter/docs/UAT yang dijalankan beserta hasil nyata.
7. Deviasi dari PRD dan alasan.
8. Risiko, deploy notes, monitoring, dan pekerjaan lanjutan.

## Larangan
- Jangan mulai coding sebelum open decisions blocking ditutup.
- Jangan merge, approve deployment, atau mengubah production.
- Jangan force push branch milik orang lain.
- Jangan menghapus data, backfill history, atau mengaktifkan poin vendor tanpa persetujuan matrix.
- Jangan menyentuh perubahan ESB command yang tidak terkait.
```
