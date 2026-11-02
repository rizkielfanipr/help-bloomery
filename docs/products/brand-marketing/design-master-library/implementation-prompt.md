---
document_id: BRAND-MARKETING-DESIGN-MASTER-LIBRARY-PROMPT
title: Prompt Implementasi — Master Design Library Berbasis Brand
status: draft
version: 1.0
owner: Brand Marketing
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/brand-marketing/design-master-library/prd.md
  - docs/products/brand-marketing/design-master-library/implementation-plan.md
supersedes: null
---

# Prompt Implementasi — Master Design Library Berbasis Brand

Salin blok berikut ke agent implementasi dari root repository.

```text
Implementasikan Master Design Library Berbasis Brand pada domain Brand Marketing di branch feature/design-master-library.

## Dokumen sumber yang wajib dibaca
- docs/products/brand-marketing/design-master-library/prd.md — sumber requirement canonical.
- docs/products/brand-marketing/design-master-library/implementation-plan.md — urutan teknis.
- docs/products/brand-marketing/design-master-library/rollout.md — deployment, permission, storage UAT, dan rollback.
- docs/products/brand-marketing/README.md — perilaku Brand Marketing existing.
- docs/products/purchasing/README.md — consumer Purchasing dan fulfillment existing.
- docs/products/rnd/README.md — material marketing Project R&D existing.
- docs/standards/code-remediation.md — standar arsitektur/data safety.
- docs/standards/ui-consistency.md — standar UI.
- docs/architecture/{panels,rbac,data-model}.md dan AGENTS.md.

## Hasil akhir yang diharapkan
Brand Marketing memiliki Master Design berbasis satu Brand per bundle, versioning immutable, item/spec/file privat, review/publish/supersede/archive, dan audit. Purchasing dapat melihat published version serta mengunduh production file secara terotorisasi tanpa dapat mengubah master. Project R&D dan fulfillment existing tidak berubah.

## Scope
- Schema/model/factory/enum/policy/permission/audit baru.
- Private file service untuk source, preview, production, dan supporting.
- Lifecycle draft → review → approved/revision required → published → superseded.
- Archive/restore bundle tanpa hard delete.
- Filament index/detail/editor serta read-only Purchasing.
- Download production/source dengan permission terpisah.
- Tests, docs, rollout evidence, dan implementation report.

## Non-scope
- Jangan memigrasikan file atau record Project R&D.
- Jangan mengubah MarketingMaterialFulfillment.
- Jangan membuat order/vendor/Receiving atau link Project dari master.
- Jangan membuat bundle multi-brand, public CDN, editor desain, API, atau integrasi eksternal.
- Jangan menambah dependency.

## Aturan repository
- Ikuti Plan → Branch → Implement → Deliver dari AGENTS.md/feature-delivery.
- Mulai dari main terbaru; jangan commit di main.
- Gunakan `php artisan make:* --no-interaction` untuk file Laravel baru.
- Audit sibling dan versi package docs sebelum coding.
- Gunakan Action untuk mutation, Policy untuk authorization, Service untuk file/readiness.
- Jalankan Pint setelah perubahan PHP.

## Aturan data
- Bundle wajib tepat satu Brand existing.
- Unique normalized code per Brand; version number unik per bundle.
- Published version immutable dan maksimal satu aktif.
- Publish/supersede atomic dengan transaction dan row lock.
- Tidak ada backfill/dual-write Project R&D.
- Tidak ada hard delete UI.

## Aturan file
- Gunakan disk private existing; jangan menulis credential/provider ke kode/docs.
- Path server-side berbasis ID sesuai PRD §18.
- File role: source, preview, production, supporting.
- Source V1 berupa ZIP; preview aman; production PDF/ZIP sesuai keputusan Phase 0.
- Simpan metadata/checksum; jangan simpan public/signed URL.
- Download authorize file role dan version status sebelum temporary URL.
- Tangani DB/storage partial failure dengan compensation yang aman.
- Publish/archive tidak menghapus atau memindahkan object.

## Aturan authorization
- Permission baru mengikuti PRD §9/§16 dan config/permissions.php.
- Designer manage/upload, reviewer review, publisher publish/archive.
- Purchasing hanya view dan download production.
- Source download terpisah.
- Jangan menjalankan seeder role production; grant lewat Role & Permission.

## Aturan UI
- Menu canonical Brand Marketing → Master Design; jangan buat master kedua di Purchasing.
- Index mode list/table, filter Brand default Semua Brand.
- Purchasing melihat published version default dan UI read-only.
- Preview lazy, ZIP/source tidak dirender.
- Terapkan loading/error/empty/disabled/double-submit guard.
- Verifikasi viewport 360/390/768/1024/1440, zoom 200%, keyboard, dark mode, nama panjang, failed upload.

## Tahapan implementasi
1. Phase 0 audit upload config/infra, role reviewer/publisher, MIME, limit, query, dan characterization existing; catat keputusan.
2. Buat schema/model/factory/enum/policy/permission/audit secara additive.
3. Implementasikan file service dan authorized download dengan fake-disk tests.
4. Implementasikan lifecycle Actions dan readiness guard.
5. Implementasikan Resource/index/detail/editor/navigation.
6. Jalankan regression Project R&D/fulfillment dan query/performance test.
7. Perbarui README Brand Marketing/Purchasing, panels, RBAC, dan regenerate data-model sesuai mekanisme project.
8. Buat implementation report, hapus prompt ini setelah fitur selesai, verify, commit, push, dan buka PR sesuai workflow.

## Test minimum
- Brand required dan code uniqueness per Brand.
- Full version lifecycle, immutable published, supersede, archive/restore.
- Concurrency/double publish.
- Policy matrix Designer/Reviewer/Publisher/Purchasing/unauthorized/SUPERADMIN.
- Fake disk upload, MIME/size, checksum, DB failure cleanup, tampered ID/path.
- Production vs source download permission.
- Index/filter/pagination/detail/editor/read-only Purchasing.
- Regression RndProjectMarketingMaterial dan MarketingMaterialFulfillment.
- Query count dan no N+1.

## Quality gates
- Targeted Pest suites.
- vendor/bin/pint --dirty --format agent
- bash ops/ci/check-doc-links.sh
- bash ops/ci/check-code-map.sh
- git diff --check
- visual/storage/authorization UAT staging.

## Format laporan akhir
1. Branch dan commit.
2. Ringkasan perubahan per phase.
3. File/schema/permission yang dibuat atau diubah.
4. Test dan UAT beserta hasil.
5. Limit/format file final dan deviasi PRD.
6. Migration, permission grant, storage, rollback, risiko, dan pekerjaan lanjutan.

## Larangan
Jangan merge, deploy, menjalankan migration production, mengubah role production, memindahkan file existing, atau cleanup object storage tanpa instruksi terpisah.
```
