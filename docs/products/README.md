# Products

Dokumentasi fitur per domain bisnis. Domain mengikuti kelompok menu/permission aplikasi; folder domain dibuat hanya bila sudah ada dokumen.

| Domain | Fitur terdokumentasi |
|---|---|
| [Analytics](analytics/README.md) | Ordering Recommendation |
| [Daily Briefing](daily-briefing/README.md) | Remediation, Scoring & UI |
| [Finance](finance/README.md) | Sales Report — Compliment & Void Remediation |
| [Inventory](inventory/README.md) | Receiving Simplification |
| [Kitchen](kitchen/README.md) | Jadwal Produksi (pilot struktur baru) |
| [Operational](operational/README.md) | Customer Complaints, Store Sales Order |
| [Research & Development](rnd/README.md) | BOM Adjustment, Internal Memo (4 PRD), Material Sourcing Lifecycle, Project Checkpoint Calendar, Project Task Calendar, Project Types, WIP Shelf Life |

Domain yang belum memiliki dokumen: `information-technology`, `purchasing`, `sales-growth`, `workforce`.

Untuk fitur baru, salin paket template: `cp -R docs/templates/feature docs/products/{domain}/{feature}`. Lihat [Template Dokumentasi](../templates/README.md).
