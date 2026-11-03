---
document_id: PURCHASING-EMPLOYEE-SOURCING-REQUEST-PROMPT
title: Prompt Implementasi — Request Sourcing melalui Employee App
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/employee-sourcing-request/prd.md
  - docs/products/purchasing/employee-sourcing-request/implementation-plan.md
  - docs/products/purchasing/employee-sourcing-request/rollout.md
supersedes: null
---

# Prompt Implementasi — Request Sourcing melalui Employee App

Salin blok berikut ke agent implementasi dari root repository.

```text
Implementasikan Request Sourcing melalui Employee App pada domain Purchasing di branch feature/employee-sourcing-request.

## Dokumen sumber yang wajib dibaca
- docs/products/purchasing/employee-sourcing-request/prd.md — sumber requirement canonical.
- docs/products/purchasing/employee-sourcing-request/implementation-plan.md — urutan teknis dan quality gate.
- docs/products/purchasing/employee-sourcing-request/rollout.md — deployment, UAT, dan rollback.
- docs/products/purchasing/README.md — perilaku Purchasing existing.
- docs/products/rnd/README.md dan docs/products/rnd/material-sourcing-lifecycle/prd.md — lifecycle sourcing downstream existing.
- docs/products/inventory/non-po-receiving/prd.md — kontrak product picker Employee App.
- docs/standards/code-remediation.md — arsitektur/data safety.
- docs/standards/ui-consistency.md — standar UI.
- docs/architecture/{panels,rbac,data-model}.md dan AGENTS.md.

## Hasil akhir yang diharapkan
Employee App mempunyai tile Request Sourcing dengan form bergaya Request ERP dan product picker reusable Receiving Tanpa PO tanpa stok. Submission membuat request lokal untuk satu Product Detail ESB dan antrean Purchasing. Purchasing memproses, meminta revisi, menolak, atau mengonversi request satu kali menjadi sourcing mandiri existing setelah supplier lengkap. Requester dapat melihat riwayat tanpa memperoleh data supplier/harga yang tidak diizinkan. Workflow R&D/Finance existing tidak berubah.

## Scope
- Schema/model/factory/enum/history/Policy/permission request sourcing.
- Submit, resubmit, cancel, claim, revision, reject, dan conversion Actions.
- Snapshot produk dari server, attachment privat, serta notifikasi internal.
- Tile/form/history Employee App dan submenu/index/detail Purchasing.
- Reuse product picker no-stock serta CreateStandaloneMaterialSourcingAction.
- Link/badge Request User pada sourcing hasil conversion.
- Tests, docs, implementation report, dan rollout evidence.

## Non-scope
- Jangan menerima produk bebas teks, produk baru, atau beberapa produk per request.
- Jangan meminta requester mengisi supplier/harga/MOQ/lead time/sample.
- Jangan langsung membuat sourcing atau mengirim review R&D saat request disubmit.
- Jangan mengubah status/lifecycle R&D, Finance, Pricelist, atau ESB sync existing.
- Jangan membuat WhatsApp/Telegram/email baru, SLA scheduler, API publik, atau dependency baru.
- Jangan memberikan permission ke role production melalui seeder.

## Aturan repository
- Ikuti Plan → Branch → Implement → Deliver dari feature-delivery/AGENTS.md.
- Mulai dari main terbaru dan worktree bersih; jangan membawa perubahan ESB command yang tidak terkait.
- Gunakan `php artisan make:* --no-interaction` untuk file Laravel baru.
- Audit sibling dan gunakan Laravel/Filament/Livewire versi repository.
- Jalankan Pint setelah perubahan PHP.

## Aturan arsitektur
- Livewire Page/Resource hanya mengelola state/presentasi dan memanggil Action.
- Semua mutation/status transition berada di app/Actions/Purchasing/MaterialSourcingRequest/.
- Conversion Action melakukan authorization, transaction, row lock, idempotency, lalu memanggil CreateStandaloneMaterialSourcingAction; jangan menyalin logika sourcing ke Resource.
- Satu request maksimal satu linked RndProductEsbMaterial melalui unique constraint.
- Pertahankan sourcing_origin = standalone; relation request menjadi sumber badge Request User.
- Request history append-only; tidak ada hard delete UI.

## Aturan authorization
- Tile/form/history: access employee app sourcing request.
- Back Office view: view material sourcing requests.
- Claim/revision/reject/conversion: process material sourcing requests.
- Conversion juga wajib submit material sourcing.
- Requester hanya dapat query dan mutate request miliknya sesuai status.
- Policy dan Action mengulang server-side checks; tombol tersembunyi bukan security.

## Aturan data
- Company server-controlled BLSS; requester dan branch berasal dari auth user.
- Browser hanya mengirim product code/detail ID; submit dan conversion re-fetch Product Detail aktif dari ESB.
- Simpan snapshot product ID/detail ID/code/name/category/subcategory/UOM.
- Satu request satu produk; produk sama boleh ada di request lain.
- Timestamp request terpisah dari sourcing_requested_at existing.
- Revision/rejection/cancel wajib actor, reason, timestamp.

## Aturan UI
- Form mengikuti resources/views/filament/casual/pages/erp-request-page.blade.php: header biru, bare layout, branch/tanggal, rounded content/card/field/button, success state, dark mode.
- Bottom navigation: Buat Request dan Riwayat; tidak ada WhatsApp CTA v1.
- Product picker reuse ManagesEsbStockProductPicker + esb-product-picker-sheet dengan pickerShowsStock=false. Jangan copy markup.
- Bila concern masih terlalu stock-specific, refactor minimal dengan characterization test untuk Receiving Tanpa PO dan Item Journal.
- Back Office memakai table index dan detail section card; conversion tidak membuka product picker kedua.
- Terapkan loading/error/empty/disabled/double-submit guard dan validasi dekat field.
- Verifikasi 360/390/768/1024/1440, zoom 200%, keyboard, modal focus/Escape, dark mode, dan teks panjang.

## Aturan integration dan file
- Product catalog hanya read-only melalui service existing; tidak ada mutation ESB pada request.
- Gunakan HTTP fake di test; jangan menghubungi ESB sungguhan.
- Error/timeout ESB mempertahankan input dan tidak membuat record parsial.
- Attachment private, prefix berbasis request ID, format/jumlah/limit dikunci setelah audit Phase 0; baseline 10 MB/file.
- Download attachment wajib authorization dan tidak memaparkan raw path/signed URL ke log.
- Tangani write DB/storage parsial dengan compensation aman.

## Tahapan implementasi
1. Audit schema, product picker contract, attachment, notification, permissions, dan characterization sourcing existing; catat keputusan Phase 0.
2. Buat migration/model/factory/enum/history/Policy/permission secara additive.
3. Implementasikan submit/resubmit/cancel, product verification, attachment, dan notification.
4. Implementasikan claim/revision/reject/conversion dengan lock dan unique link.
5. Implementasikan Employee App tile/form/history dan Back Office resource/navigation/badge.
6. Tambahkan source badge/link pada sourcing existing tanpa mengubah lifecycle.
7. Jalankan regression, query/performance test, responsive UAT, dan storage UAT.
8. Perbarui README Purchasing/R&D, panels, RBAC, regenerate data-model, buat implementation report, hapus prompt ini, verify, commit, push, dan buka PR sesuai workflow.

## Test minimum
- Tile/page/history permission dan requester ownership.
- No-branch guard, form validation, submit/resubmit/cancel.
- Picker search nama/kode, pagination, no-stock mode, active detail re-fetch, forged/mismatched product, timeout.
- Attachment MIME/size/count/private download/partial failure.
- Full request transition dan reason requirements.
- Policy matrix requester/other requester/viewer/processor/submitter/unauthorized/SUPERADMIN.
- Supplier validation dan conversion happy/invalid path.
- Double/concurrent conversion hanya membuat satu sourcing.
- Requester response tidak memuat supplier/harga.
- Regression manual standalone, project sourcing, Review R&D, Finance, dan duration tracking.
- Query count index/history/badge dan no N+1.

## Quality gates
- Targeted Pest suites dan regression sourcing.
- vendor/bin/pint --dirty --format agent
- bash ops/ci/check-doc-links.sh
- bash ops/ci/check-code-map.sh
- git diff --check
- visual/attachment/authorization/ESB read-only UAT staging.

## Format laporan akhir
1. Branch, commit, dan PR.
2. Ringkasan perubahan per phase.
3. Schema/model/permission/menu/file yang dibuat atau diubah.
4. Test dan UAT beserta hasil nyata.
5. Format/limit attachment final dan deviasi PRD.
6. Migration, permission grant, ESB/storage, rollback, risiko, dan pekerjaan lanjutan.

## Larangan
Jangan merge, deploy, menjalankan migration production, mengubah role production, mengirim mutation ESB, atau membersihkan data/storage tanpa instruksi terpisah. Commit, push branch implementasi, dan pembuatan Pull Request mengikuti workflow feature-delivery.
```
