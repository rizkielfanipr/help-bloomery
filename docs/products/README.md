# Dokumentasi Modul

Satu dokumen per modul bisnis, ditulis dari kode yang berjalan saat ini. Setiap `README.md` domain memuat: lokasi menu/tile dan permission, alur kerja, status, aturan penting, otomatisasi, data, PRD terkait, dan role bawaan. Kondisi per 27 Oktober 2026.

| Modul | Cakupan |
|---|---|
| [Workforce](workforce/README.md) | Casual staff, posisi, lowongan, absensi GPS/foto, lembur |
| [Daily Briefing](daily-briefing/README.md) | Poin briefing, submission, review, auto-reject, nilai bulanan |
| [Driver](driver/README.md) | Perjalanan, check-in titik, BBM, rute, kendaraan, uang makan |
| [Technician](technician/README.md) | Service request, outsource, garansi, asset QR, maintenance bulanan |
| [Information Technology](information-technology/README.md) | Permintaan ERP, SLA dan garansi, Bulk Data ESB, Review Data Master Bahan |
| [System Monitoring](system-monitoring/README.md) | Health check, transaksi ESB, queue/scheduler, error, deployment, incident |
| [Operational](operational/README.md) | SOP Store, Customer Complaints, Store Sales Orders |
| [Research & Development](rnd/README.md) | Project, BOM, Master Bahan dan sourcing, Shelf Life WIP, Memo Internal, kalender tugas, Price Index |
| [Kitchen](kitchen/README.md) | Jadwal Produksi |
| [Purchasing](purchasing/README.md) | Permintaan Pembelian, Vendor Compliance, Sourcing Bahan, Material Marketing |
| [Quality Control](quality-control/README.md) | Audit QC, Checklist QC, Item Journal |
| [Inventory](inventory/README.md) | Stock Card, Receiving dan QC inbound, Lokasi, Product List |
| [Finance](finance/README.md) | Sales Report, Compliment/Void, Scores, Basket Size, Product Pricelist |
| [Sales & Growth](sales-growth/README.md) | Sales Projection |
| [Analytics](analytics/README.md) | Sales/Promotion/Stock Information, Ordering Recommendation |
| [Brand Marketing](brand-marketing/README.md) | Permintaan Design dan Konten |
| [Access Management](access-management/README.md) | Pengguna, Role & Permission, akses cabang |
| [Master Data](master-data/README.md) | Brand, Branch dan mapping ESB, Employee, Region, WhatsApp |

PRD fitur disimpan di subfolder domain (`{domain}/{fitur}/prd.md`) dan ditautkan dari README domain. PRD adalah catatan requirement saat fitur dirancang; perilaku sistem saat ini mengikuti README domain dan kode.

Untuk fitur baru, salin paket template: `cp -R docs/templates/feature docs/products/{domain}/{feature}`. Lihat [Template Dokumentasi](../templates/README.md).
