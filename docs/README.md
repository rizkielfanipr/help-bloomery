# Help Bloomery — Documentation

Portal dokumentasi sistem internal Bloomery Patisserie. Dokumentasi modul dan arsitektur ditulis dari kode yang berjalan (diperbarui 27 Oktober 2026). Aturan penulisan ada di [Standar Dokumentasi](standards/documentation-standard.md).

## Quick start

| Saya ingin… | Buka |
|---|---|
| Menjalankan aplikasi secara lokal | [Setup & Instalasi](setup.md) |
| Memahami cara kerja sebuah modul | [Dokumentasi Modul](products/README.md) |
| Mencari menu, tile, atau permission | [Panels](architecture/panels.md) dan [RBAC](architecture/rbac.md) |
| Memahami arsitektur dan scheduler | [Overview](architecture/overview.md) |
| Mencari tabel sebuah model | [Data Model](architecture/data-model.md) |
| Mengikuti standar kode atau UI | [Standards](standards/README.md) |
| Memahami CI/CD dan production | [Operations](operations/README.md) |
| Menulis dokumentasi fitur baru | [Templates](templates/README.md) |

## Modul

| Modul | Cakupan |
|---|---|
| [Workforce](products/workforce/README.md) | Casual staff, lowongan, absensi, lembur |
| [Daily Briefing](products/daily-briefing/README.md) | Poin briefing, review, nilai |
| [Driver](products/driver/README.md) | Perjalanan, BBM, uang makan |
| [Technician](products/technician/README.md) | Service request, garansi, asset QR, maintenance |
| [Information Technology](products/information-technology/README.md) | Permintaan ERP, Bulk Data, Review Data |
| [System Monitoring](products/system-monitoring/README.md) | Health, transaksi ESB, error, incident |
| [Operational](products/operational/README.md) | SOP Store, Customer Complaints, Store Sales Orders |
| [Research & Development](products/rnd/README.md) | Project, BOM, Master Bahan, Shelf Life, Memo Internal, task |
| [Kitchen](products/kitchen/README.md) | Jadwal Produksi |
| [Purchasing](products/purchasing/README.md) | Permintaan Pembelian, Vendor Compliance, Sourcing, Material Marketing |
| [Quality Control](products/quality-control/README.md) | Audit QC, Item Journal |
| [Inventory](products/inventory/README.md) | Stock Card, Receiving, Lokasi, Product List |
| [Finance](products/finance/README.md) | Sales Report, Scores, Basket Size, Product Pricelist |
| [Sales & Growth](products/sales-growth/README.md) | Sales Projection |
| [Analytics](products/analytics/README.md) | Sales/Promotion/Stock Information, Ordering Recommendation |
| [Brand Marketing](products/brand-marketing/README.md) | Permintaan Design dan Konten |
| [Access Management](products/access-management/README.md) | Pengguna, Role & Permission |
| [Master Data](products/master-data/README.md) | Brand, Branch, Employee, WhatsApp |

## Struktur

```
docs/
├── README.md          Portal ini
├── setup.md           Setup & instalasi lokal
├── architecture/      Overview, panels, RBAC, data model, integrasi ESB
├── products/          Dokumentasi per modul + PRD fitur
├── standards/         Standar kode, UI, struktur codebase, dokumentasi
├── operations/        CI/CD dan production
├── reports/           Laporan lintas modul yang masih dirujuk
└── templates/         Template PRD, plan, prompt, report, rollout, ADR
```

## Standards

| Dokumen | Cakupan |
|---|---|
| [Code Remediation](standards/code-remediation.md) | Arsitektur, keamanan data, kualitas teknis |
| [UI Consistency](standards/ui-consistency.md) | Standar tampilan dan interaksi |
| [Codebase Refactoring](standards/codebase-refactoring.md) | Struktur target codebase |
| [Documentation Standard](standards/documentation-standard.md) | Struktur, penamaan, metadata, lifecycle dokumen |

## Operations

| Dokumen | Isi |
|---|---|
| [Release Pipeline](operations/ci-cd/release-pipeline.md) | CI, release atomik staging/production, rollback |
| [Redis untuk Production](operations/production/redis.md) | Konfigurasi Redis production |
| [Stabilitas & Skalabilitas](operations/production/stability-scalability-prd.md) | Infrastruktur, backup, migrasi server |

## Membuat dokumentasi fitur baru

```bash
cp -R docs/templates/feature docs/products/{domain}/{feature}
```

1. Ganti nama file `*-template.md` menjadi nama standar (`prd.md`, `implementation-plan.md`, dan seterusnya).
2. Ganti seluruh placeholder `{...}` dan isi metadata front matter.
3. Hapus file template yang tidak diperlukan.
4. Perbarui README domain: menu, permission, alur, dan tautan PRD.
5. Jalankan `bash ops/ci/check-doc-links.sh`.

Setelah fitur selesai, perbarui README domain agar tetap sesuai kode, lalu hapus prompt implementasi yang sudah tidak dipakai.

## Aturan kontribusi

- Simpan dokumen sesuai [Standar Dokumentasi](standards/documentation-standard.md); jangan menambah file di root `docs/`.
- Pindahkan file dengan `git mv` dan perbarui seluruh rujukan dalam PR yang sama.
- Perubahan perilaku modul wajib memperbarui README domain terkait dalam PR yang sama.
- Jangan menyimpan secret, isi `.env`, payload production, atau data pribadi.
