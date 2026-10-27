# Help Bloomery — Documentation

Portal dokumentasi sistem internal Bloomery Patisserie. Setiap kebutuhan informasi mempunyai satu dokumen canonical; aturan lengkapnya ada di [Standar Dokumentasi](standards/documentation-standard.md).

## Quick start

| Saya ingin… | Buka |
|---|---|
| Menjalankan aplikasi secara lokal | [Setup & Instalasi](setup.md) |
| Memahami arsitektur aplikasi | [Architecture](architecture/README.md) |
| Membaca requirement sebuah fitur | [Products](products/README.md) |
| Mengikuti standar kode atau UI | [Standards](standards/README.md) |
| Memahami CI/CD, deployment, dan production | [Operations](operations/README.md) |
| Menulis dokumentasi fitur baru | [Templates](templates/README.md) |

## Membuat dokumentasi fitur baru

```bash
cp -R docs/templates/feature docs/products/{domain}/{feature}
```

Setelah menyalin template:

1. Ganti nama file `*-template.md` menjadi nama standar (`prd.md`, `implementation-plan.md`, dan seterusnya).
2. Ganti seluruh placeholder `{...}`.
3. Hapus file template yang tidak diperlukan.
4. Isi metadata front matter.
5. Tambahkan fitur ke README domain.
6. Pastikan seluruh link valid: `bash ops/ci/check-doc-links.sh`.

## Struktur

```
docs/
├── README.md          Portal ini
├── setup.md           Setup & instalasi lokal
├── templates/         Template PRD, plan, prompt, report, rollout, ADR
├── standards/         Standar kode, UI, struktur codebase, dokumentasi
├── architecture/      Overview, data model, panel, RBAC, integrasi
├── products/          Dokumentasi fitur per domain
├── operations/        CI/CD, production, monitoring
├── reports/           Audit dan laporan lintas modul
└── modules/           Dokumen modul lama — belum diklasifikasikan
```

## Standards

| Dokumen | Cakupan |
|---|---|
| [Code Remediation](standards/code-remediation.md) | Arsitektur, keamanan data, kualitas teknis |
| [UI Consistency](standards/ui-consistency.md) | Standar tampilan dan interaksi |
| [Codebase Refactoring](standards/codebase-refactoring.md) | Struktur target codebase |
| [Documentation Standard](standards/documentation-standard.md) | Struktur, penamaan, metadata, lifecycle dokumen |

## Architecture

| Dokumen | Isi |
|---|---|
| [Overview](architecture/overview.md) | Multi-panel, struktur folder, alur request |
| [Data Model](architecture/data-model.md) | Model, relasi, tabel |
| [Panels](architecture/panels.md) | Panel Filament dan routing |
| [RBAC](architecture/rbac.md) | Role dan permission |
| [ESB Integration](architecture/integrations/esb.md) | Roadmap konsolidasi integrasi ESB |

## Domain produk

| Domain | Fitur |
|---|---|
| [Analytics](products/analytics/README.md) | Ordering Recommendation |
| [Daily Briefing](products/daily-briefing/README.md) | Remediation, Scoring & UI |
| [Finance](products/finance/README.md) | Sales Report — Compliment & Void |
| [Inventory](products/inventory/README.md) | Receiving Simplification |
| [Kitchen](products/kitchen/README.md) | Jadwal Produksi |
| [Operational](products/operational/README.md) | Customer Complaints, Store Sales Order |
| [Research & Development](products/rnd/README.md) | BOM Adjustment, Internal Memo, Material Sourcing, Project Calendar, Project Types, WIP Shelf Life |

## Operations

| Dokumen | Isi |
|---|---|
| [Release Pipeline](operations/ci-cd/release-pipeline.md) | CI, release atomik staging/production, rollback |
| [Redis untuk Production](operations/production/redis.md) | Konfigurasi Redis production |
| [Stabilitas & Skalabilitas](operations/production/stability-scalability-prd.md) | Infrastruktur, backup, dan migrasi server |
| [System Monitoring](operations/monitoring/prd.md) | PRD modul monitoring |

## Status dokumen

Dokumen baru memakai status `draft`, `in-review`, `approved`, `in-development`, `implemented`, `deprecated`, atau `archived` di front matter. Dokumen lama belum memiliki metadata; README domain mengutip status tertulisnya dan menandai `needs-review` bila status itu tidak ada, tidak jelas, atau tidak sesuai kondisi kode.

## Aturan kontribusi

- Simpan dokumen di lokasi sesuai [Standar Dokumentasi](standards/documentation-standard.md); jangan menambah file di root `docs/`.
- Pindahkan file dengan `git mv` dan perbarui seluruh rujukan dalam PR yang sama.
- Jangan menyalin isi dokumen lain; tautkan.
- Jangan menyimpan secret, isi `.env`, payload production, atau data pribadi.
- Jalankan `bash ops/ci/check-doc-links.sh` sebelum membuka PR.

## Dokumen yang belum diklasifikasikan

| Dokumen | Alasan |
|---|---|
| [Modul: Casual Staff](modules/casual.md) | Deskripsi modul lama; domain dan apakah masih akurat belum dipastikan |
| [Modul: Driver & Trip](modules/driver.md) | Sama seperti di atas |
| [Modul: Helpdesk](modules/helpdesk.md) | Sama seperti di atas |
| [Modul: Technician](modules/technician.md) | Sama seperti di atas |

## Ringkasan aplikasi

**Help Bloomery** adalah platform intranet multi-panel yang mengelola:

- **Casual Staff** — Registrasi, clock-in/out dengan GPS & selfie, export absensi ke Excel
- **Driver** — Manajemen perjalanan, check-in waypoint, laporan BBM
- **Helpdesk** — Tiket request berbasis form template dinamis
- **Technician** — Service request perangkat dengan alur multi-siklus
- **Management** — User, role, departemen, dan pengaturan sistem

## Tech stack

| Layer | Teknologi |
|-------|-----------|
| Framework | Laravel 13, PHP 8.4 |
| Admin Panel | Filament 5 |
| Real-time UI | Livewire 4 |
| Frontend | Tailwind CSS 4, Alpine.js 3 |
| Database | MySQL |
| RBAC | Spatie Laravel Permission 7 |
| Activity Log | Spatie Activity Log 5 |
| PDF | DomPDF 3 |
| Testing | Pest 4 |
