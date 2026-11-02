---
document_id: BRAND-MARKETING-DESIGN-MASTER-LIBRARY-PLAN
title: Implementation Plan — Master Design Library Berbasis Brand
status: draft
version: 1.0
owner: Brand Marketing
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/brand-marketing/design-master-library/prd.md
supersedes: null
---

# Implementation Plan — Master Design Library Berbasis Brand

Acuan requirement: `docs/products/brand-marketing/design-master-library/prd.md`. Dokumen ini mengatur urutan teknis; requirement final tetap berada di PRD.

## 1. Scope implementasi

- **Termasuk:** library per Brand, bundle/version/item/file, private storage, review/publish/supersede/archive, audit, permission Designer/Purchasing, index/detail/editor, download terotorisasi, migration, tests, docs, dan rollout.
- **Tidak termasuk:** migrasi file Project R&D, order/vendor/Receiving dari master, link master ke Project R&D, campaign multi-brand, editor desain browser, API publik, atau integrasi eksternal.

## 2. Baseline repository

- Baseline planning: `main` commit `a2b7e3d2` pada 2 November 2026.
- Master Brand hanya memiliki relasi branch.
- Marketing material existing dimiliki `RndProjectMarketingMaterial`, file disimpan pada disk `b2`, dan fulfillment fisik terkait langsung ke record project.
- Tipe material existing berada pada `RndProjectMarketingMaterial::TYPES`; tipe fisik packaging/sticker.
- Brand Marketing belum memiliki Resource/file service/policy untuk master design.
- Sidebar, permission registry, data model docs, dan role assignment mengikuti aturan project existing.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Migration | `database/migrations/*_create_marketing_design_*_tables.php` | Baru, additive |
| Model/factory | `app/Models/MarketingDesign*.php`, `database/factories/MarketingDesign*Factory.php` | Baru |
| Enum | `app/Enums/MarketingDesign*.php` | Baru; vocabulary type dievaluasi untuk reuse |
| Action | `app/Actions/BrandMarketing/DesignMaster/` | Mutation lifecycle/file |
| Service | `app/Services/BrandMarketing/DesignMaster/` | File storage, checksum, readiness/presenter |
| Policy | `app/Policies/MarketingDesign*.php` | Authorization server-side |
| Resource/UI | `app/Filament/Helpdesk/Resources/MarketingDesignBundles/`, `resources/views/filament/helpdesk/marketing-design-bundles/` | Baru |
| Navigation | `resources/views/vendor/filament-panels/components/layout/index.blade.php` | Menu canonical Brand Marketing |
| Permission | `config/permissions.php` | Permission baru |
| Existing regression | `app/Models/RndProjectMarketingMaterial.php`, `app/Filament/Helpdesk/Resources/MarketingMaterialFulfillments/` | Tidak diubah kecuali reuse type yang aman |
| Tests | `tests/Feature/MarketingDesign*Test.php`, `tests/Unit/MarketingDesign*Test.php` | Baru dan regression |
| Docs | README Brand Marketing/Purchasing, panels, RBAC, generated data model, folder fitur | Update saat implementasi |

Path final mengikuti sibling dan hasil Phase 0. Jangan membuat base folder baru di luar struktur existing.

## 4. Dependency dan integration boundary

- Tidak ada package baru.
- `Brand` existing menjadi master induk.
- Laravel Storage disk existing menjadi satu-satunya external boundary.
- Tidak ada ESB, Figma, Adobe, email, atau API eksternal.
- Project R&D/fulfillment existing adalah compatibility boundary dan tidak dimutasi.

## 5. Urutan phase

| Phase | Scope | Prasyarat |
|---|---|---|
| 0 | Audit upload limits, MIME, disk behavior, role, query, dan characterization existing | PRD direview |
| 1 | Generate migration/model/factory/enum/policy/permission/audit | Phase 0 decisions |
| 2 | Implement file service dan authorized download | Schema dan policy lulus test |
| 3 | Implement lifecycle Action dan readiness guards | File service lulus test |
| 4 | Implement Resource/index/detail/editor/navigation | Lifecycle stabil |
| 5 | Regression, performance, visual UAT, docs/report | Semua phase selesai |

Satu branch/PR implementasi disarankan agar schema, writer, reader, dan authorization tidak terpisah. Commit dibagi per phase. Jika ukuran review terlalu besar, pecah setelah Phase 2 dengan feature tetap tidak dinavigasikan sampai workflow/UI lengkap.

## 6. Migration strategy

1. Gunakan `php artisan make:model -mf` dan `make:migration` sesuai kebutuhan.
2. Buat tabel additive untuk bundle, version, item, file, dan audit.
3. Tambahkan FK/restrict-delete untuk master yang sudah direferensikan.
4. Tambahkan unique bundle code per Brand dan version number per bundle.
5. Published uniqueness dijaga dengan constraint yang kompatibel serta transaction/lock; audit database engine sebelum final.
6. Jangan memindahkan binary atau menjalankan backfill di migration.
7. Tambahkan index hanya untuk query/filter yang dibuktikan pada Phase 0.

## 7. Data compatibility

- Seluruh tabel existing tetap utuh.
- Tidak ada dual-write dari upload Project R&D ke Master Design.
- Type vocabulary boleh diekstrak ke Enum shared hanya jika characterization test membuktikan label/value existing tidak berubah.
- Published/archived records tidak dihapus saat rollback aplikasi.
- File master tidak memakai path project existing.

## 8. Authorization

- Buat Policy per aggregate utama dan file download.
- Pisahkan create/edit/upload, review, publish/archive, view, download production, dan download source.
- SUPERADMIN tetap mengikuti Gate existing.
- Purchasing hanya menerima view dan production download melalui Role & Permission.
- Role production tidak diubah lewat seeder.

## 9. File lifecycle

1. Service menerima temporary upload yang sudah divalidasi.
2. Path dibentuk server-side dari numeric/UUID identity.
3. Object ditulis ke disk privat; metadata/checksum dihitung tanpa membawa binary ke state panjang.
4. Record file dibuat setelah write berhasil.
5. Bila DB gagal, object baru yang belum direferensikan dihapus sebagai compensation.
6. Replacement pada draft membuat record/path baru; object lama baru dapat dibersihkan setelah reference audit.
7. Publish/archive tidak memindahkan atau menghapus object.
8. Download action membuat temporary URL setelah Policy/file-role check dan mencatat audit aman.

## 10. UI

- Resource list/table untuk comparison; tidak memakai card sebagai default index.
- Brand filter default Semua Brand.
- Designer memakai create/edit/revision actions sesuai status.
- Purchasing mendapatkan read-only detail dan production download.
- File preview lazy; source/ZIP tidak dirender.
- Form menjaga input saat validation/upload gagal dan mencegah double submit.
- Verifikasi responsive dan keyboard mengikuti PRD §17.

## 11. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Characterization upload/temporary URL/fulfillment existing; config limits |
| 1 | Schema invariant, model relation/cast, Policy matrix, permission registry |
| 2 | Fake disk upload/download/rollback/tamper/role/size/MIME/checksum |
| 3 | Lifecycle happy/invalid paths, immutable published, concurrency publish, archive |
| 4 | Filament list/filter/action/detail/editor, read-only Purchasing, responsive manual UAT |
| 5 | Regression Project R&D/fulfillment, query count, docs checks, full targeted suite |

## 12. Observability

- Append-only audit untuk lifecycle/file/download.
- Storage failure dicatat tanpa credential, path public, atau signed URL.
- Audit actor/status/reason dapat dibaca pada detail oleh permission view.
- Tidak ada command/scheduler/monitoring transaction baru pada versi pertama.

## 13. Rollout

Ikuti [rollout.md](rollout.md): schema → application → permission sync → role grant via UI → staging storage smoke → workflow UAT → Purchasing download UAT → regression existing → observation.

## 14. Rollback

- Cabut permission/menu terlebih dahulu.
- Rollback application artifact; pertahankan schema dan object.
- Jangan hard delete/bulk cleanup sebagai rollback.
- Cleanup hanya melalui release terpisah setelah backup/reference audit.

## 15. Risiko

| Risiko | Mitigasi |
|---|---|
| Upload besar melebihi infra | Phase 0 audit dan limit eksplisit |
| Object orphan | Compensation + future audit command, bukan delete spekulatif |
| Source bocor ke Purchasing | Policy/file-role permission terpisah |
| Published mutable | Action guard, DB transaction, concurrency test |
| UI payload berat | Server pagination, lazy preview, no binary state |
| Regression Project R&D | No schema/backfill existing + regression test |

## 16. Quality gates

- [ ] Targeted model/policy/workflow/file/Filament tests lulus
- [ ] Regression marketing material Project R&D dan fulfillment lulus
- [ ] Query count dan storage failure paths diverifikasi
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `bash ops/ci/check-doc-links.sh`
- [ ] `bash ops/ci/check-code-map.sh`
- [ ] `git diff --check`
- [ ] Responsive/keyboard/file UAT staging dicatat
- [ ] CI Pull Request hijau

## 17. Definition of Done

- [ ] Semua acceptance criteria PRD terpenuhi.
- [ ] Permission dan role staging diuji.
- [ ] Published/download/file lifecycle aman dan teruji.
- [ ] README/architecture sesuai kode aktual.
- [ ] Implementation report dibuat.
- [ ] Implementation prompt dihapus setelah implementasi selesai.
