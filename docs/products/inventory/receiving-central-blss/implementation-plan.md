---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-PLAN
title: Implementation Plan — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal
status: in-development
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-30
updated_at: 2026-10-30
target_release: TBD
related_documents:
  - docs/products/inventory/receiving-central-blss/prd.md
  - docs/products/inventory/receiving-central-blss/reports/audit-report.md
  - docs/products/inventory/receiving-central-blss/rollout.md
supersedes: null
---

# Implementation Plan — Receiving Terpusat BLSS Tanpa Mapping Branch Lokal

Acuan requirement: `docs/products/inventory/receiving-central-blss/prd.md`. Dokumen ini hanya mengatur urutan teknis; requirement tidak ditulis ulang di sini.

## 1. Scope implementasi

- **Termasuk:** alur Employee App Receiving BLSS; lookup mapping lokal opsional/read-only; persist `local_branch_id` nullable; context monitoring nullable; guard company/status/location; regresi QC, Vendor Compliance, idempotency, dan scope Back Office; dokumentasi dan UAT staging.
- **Tidak termasuk:** SPN/company lain; permission/role baru; redesign UI; aturan QC baru; migration/backfill; mapping otomatis; perubahan konfigurasi Master Branch; dependency, queue, atau scheduler baru; production deployment.

## 2. Baseline repository

- Baseline aplikasi: `main` commit `d750709a`.
- Branch planning: `docs/receiving-blss-central-inventory-plan`.
- Test baseline: 46 test, 310 assertion, 0 gagal pada enam file/suite terkait.
- Guard mapping, persist mandatory, resolver mutation, dan scope Back Office terdokumentasi pada audit report.
- Branch implementasi dibuat dari `main` terbaru setelah PR planning disetujui/merge. Bila planning belum merge, agent harus menyertakan commit dokumentasi secara eksplisit tanpa menduplikasi dokumen.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Employee App Receiving | `app/Filament/Casual/Pages/GoodsReceiptPage.php` | Ubah guard mapping, persist nullable, validasi context BLSS/PO/location |
| Mapping resolver | `app/Services/EsbBranchMappingResolver.php` | Tambah lookup aktif read-only tanpa mengubah method existing |
| Model | `app/Models/GoodsReceipt.php` | Audit/komentar/type bila diperlukan; tidak ada schema change |
| Monitoring call site | `app/Filament/Casual/Pages/GoodsReceiptPage.php` | Kirim `branch_id` nullable; service recorder hanya diubah bila test membuktikan perlu |
| Policy | `app/Policies/GoodsReceiptPolicy.php` | Kemungkinan tanpa perubahan; characterization/regression test |
| Back Office | `app/Filament/Helpdesk/Resources/GoodsReceipts/GoodsReceiptResource.php` | Kemungkinan tanpa perubahan; verifikasi query receipt nullable |
| Main feature test | `tests/Feature/GoodsReceiptPageTest.php` | Ganti rejection unmapped dan tambah matriks mapping/context/idempotency |
| Authorization test | `tests/Feature/ReceivingAuthorizationTest.php` | Receiving-only vs Back Office/branch scope |
| Monitoring test | `tests/Feature/Monitoring/EsbTransactionRecorderTest.php` atau test Page | Context branch null dan failure isolation |
| ESB/QC tests | `tests/Feature/EsbGoodsReceiptServiceTest.php`, `tests/Unit/{GoodsReceiptServiceTest,InboundGoodsReceiptQcServiceTest}.php` | Regresi kontrak existing |
| Dokumentasi | `docs/products/inventory/README.md`, folder fitur ini | Status final, report, rollout evidence |

File perkiraan bukan izin memperluas scope. File tambahan hanya disentuh bila audit dependency konkret membuktikan kebutuhan, lalu Peta kode diperbarui.

## 4. Dependency dan integration boundary

- `GoodsReceiptPage` mengatur state UI, validasi presentasi, policy check, dan orkestrasi use case existing.
- `EsbGoodsReceiptService` tetap menjadi batas HTTP untuk daftar/detail/location/create. Kontrak payload tidak berubah.
- `EsbBranchMappingResolver` hanya menyediakan metadata lokal opsional; resolver tidak menentukan authorization Employee App dan tidak menentukan destination ESB.
- `EsbTransactionRecorder` merekam mutasi; kegagalan monitoring tidak boleh mengulang atau menggagalkan business mutation sesuai behavior existing.
- `InboundGoodsReceiptQcService` menghitung QC/disposition; tidak diubah kecuali test menemukan dependency nyata.
- Vendor Compliance incident tetap dibuat dari rejected item yang sudah dinilai QC.
- Database lokal menyimpan receipt sebelum/sekitar mutasi ESB sesuai flow existing; submission key dan status result dipertahankan.

Semua HTTP test di-fake/mock. Create GR tidak diberi automatic retry baru karena timeout setelah request terkirim dapat menghasilkan outcome tidak pasti.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 — Characterization | Test target unmapped, resolver no-mutation, schema/context audit | PR implementasi yang sama | PRD disetujui |
| 1 — Core fix | Lookup read-only, mapping nullable, guard BLSS dan PO/location context | PR implementasi yang sama | Test Phase 0 gagal karena behavior lama |
| 2 — Downstream safety | QC, incident, monitoring, idempotency, failure classification, Back Office scope | PR implementasi yang sama | Phase 1 lulus |
| 3 — Delivery | Docs final, implementation report, CI, staging UAT plan | PR implementasi yang sama | Semua test lokal/CI lulus |

Perubahan ini cukup satu PR karena satu behavior dan tanpa migration. Commit boleh dipisah per phase agar review mudah.

## 6. Migration strategy

Not applicable — tidak ada migration. `goods_receipts.local_branch_id` sudah nullable berdasarkan model data dan schema existing. Agent wajib memverifikasi schema aktual sebelum implementasi. Bila tidak nullable, hentikan implementasi dan amend PRD/plan; jangan membuat migration tanpa review baru.

## 7. Data compatibility

- Receipt lama dengan `local_branch_id` terisi tetap valid.
- Receipt baru boleh null bila mapping tidak tersedia.
- `company_code`, `esb_branch_id`, `branch_name`, location, supplier, reference number, dan payload tetap diisi.
- Route, permission, status, attachment path, item/expiry, Vendor Compliance, dan kolom legacy dokumen tidak berubah.
- Lookup mapping exact mempertahankan metadata lokal existing tanpa mengubah record mapping.
- Tidak ada backfill; histori tidak ditafsirkan ulang.

## 8. Authorization

- Tidak ada permission baru.
- `access employee app goods receipt` tetap wajib untuk membuka tile dan submit.
- Akses seluruh branch dalam scope ini hanya berlaku pada Employee App Receiving BLSS.
- `view goods receipts` dan all-branch access Back Office tetap terpisah.
- Semua guard dijalankan server-side; visibility UI bukan kontrol keamanan.
- Manipulasi company non-BLSS, PO status, branch context, location, atau qty harus ditolak sebelum create ESB.

## 9. UI

Tidak ada redesign. Form, copy, navigasi, modal/drawer, bulk action, attachment, dan label existing dipertahankan. Notifikasi “Branch PO belum dimapping” dihapus dari jalur unmapped yang valid. Pesan error baru hanya diperlukan untuk context PO/location yang tidak lagi valid atau kegagalan ESB, memakai pola notifikasi existing dan Bahasa Indonesia.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Baseline enam suite; test sukses unmapped yang awalnya gagal; test lookup tidak memutasi mapping |
| 1 | Unmapped/mapped/nonaktif/company lain/ambigu; dua branch BLSS; guard company/status/location/context |
| 2 | Accepted/partial/Hold/Rejected; Vendor Compliance; foto/cleanup; ED/batch; duplicate; unknown vs failed; monitoring null; Back Office authorization |
| 3 | Seluruh targeted suite, doc checks, code-map checks, diff check, Pint, CI shards |

Perintah minimum:

```bash
php artisan test --compact tests/Feature/GoodsReceiptPageTest.php tests/Feature/ReceivingAuthorizationTest.php tests/Feature/EsbGoodsReceiptServiceTest.php tests/Feature/Monitoring/EsbTransactionRecorderTest.php tests/Unit/InboundGoodsReceiptQcServiceTest.php tests/Unit/GoodsReceiptServiceTest.php
vendor/bin/pint --dirty --format agent
bash ops/ci/check-doc-links.sh
bash ops/ci/check-code-map.sh
git diff --check
```

Full Feature suite mengikuti shard CI repository, bukan satu proses besar.

## 11. Observability

- Pertahankan monitoring transaction `goods_receipt` beserta company, external reference PO, local model, idempotency key, status, dan attempt.
- `branch_id` boleh null; `esb_branch_id` dan `branch_name` tetap ada pada domain record.
- Tidak menambah log berisi credential, foto, atau payload sensitif.
- Pantau jumlah receipt `unknown`/`failed`, attempt ESB, dan receipt baru dengan `local_branch_id = null` pada rollout.
- Bila recorder gagal menyimpan, business mutation tidak boleh diulang oleh monitoring.

## 12. Rollout

Ikuti `rollout.md`: merge hanya setelah CI hijau, deploy staging, gunakan PO BLSS uji yang belum dimapping, verifikasi receipt/QC/monitoring/Back Office, lalu observasi. Production dan transaksi GR nyata memerlukan persetujuan terpisah.

## 13. Rollback

Rollback aplikasi ke release sebelumnya. Jangan rollback data dengan delete. Efeknya: PO unmapped kembali ditolak. Receipt/GR/incident yang sudah ada tetap dipertahankan. Outcome `unknown` direkonsiliasi dengan ESB sebelum retry atau keputusan rollback operasional.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Null mapping tidak ditangani di semua call site | Static search dan test end-to-end unmapped |
| Submit memutasi mapping | Lookup read-only plus assertion database unchanged |
| Scope melebar ke SPN | Guard BLSS dan negative tests |
| Location lama dipakai setelah PO berubah | Validasi context terbaru sebelum create |
| GR ganda pada timeout/retry | Pertahankan submission key; unknown → reconciliation |
| Receipt null hilang dari Back Office | Authorization/query regression test; keputusan akses tetap terpisah |
| Test secara tidak sengaja mengirim ESB | Fake/mock dan stray-request guard |

## 15. Quality gates

- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Test terdampak lulus
- [ ] `bash ops/ci/check-doc-links.sh` lulus
- [ ] `bash ops/ci/check-code-map.sh` lulus
- [ ] `git diff --check` lulus
- [ ] CI Pull Request hijau
- [ ] Review authorization dan data safety
- [ ] Tidak ada request ESB nyata dari test
- [ ] Implementation report berisi hasil aktual dan deviasi

## 16. Definition of Done

- [ ] Semua phase selesai sesuai PRD.
- [ ] Semua acceptance criteria mempunyai bukti test.
- [ ] Tidak ada perubahan schema/configuration/dependency yang tidak direncanakan.
- [ ] README Inventory sesuai perilaku final.
- [ ] Phase report dibuat untuk phase yang menghasilkan keputusan/deviasi.
- [ ] Implementation report ditulis di `reports/`.
- [ ] Prompt implementasi dihapus setelah pekerjaan selesai sesuai standar proyek.
- [ ] UAT staging dicatat; production tetap menunggu persetujuan pengguna.
