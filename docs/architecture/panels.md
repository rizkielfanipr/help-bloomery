# Panel dan Navigasi

Aplikasi mendaftarkan lima panel Filament di `bootstrap/providers.php`. Dokumen ini mencerminkan kode per 27 Oktober 2026.

| Panel | ID | Path | Provider | Syarat akses (`User::canAccessPanel()`) |
|---|---|---|---|---|
| Back Office | `helpdesk` | `/` | `HelpdeskPanelProvider` | `access backoffice` |
| Employee App | `casual` | `/casual`, atau root domain bila `APP_DOMAIN` diisi | `CasualPanelProvider` | Minimal satu permission di grup *Akses Employee App* |
| Driver | `driver` | `/driver`, atau `DRIVER_DOMAIN` | `DriverPanelProvider` | `access employee app driver` |
| Technician | `technician` | `/technician`, atau `TECHNICIAN_DOMAIN` | `TechnicianPanelProvider` | `access employee app technician` |
| Admin | `admin` | `/admin` | `AdminPanelProvider` | `access backoffice` dan minimal satu permission *Management Access* |

User nonaktif (`is_active = false`) ditolak di semua panel. Route `/employee-login` mengarah ke login Employee App.

Fitur Driver dan Technician juga tersedia di Employee App melalui tile **Driver** dan **Teknisi**, dengan halaman yang setara di `app/Filament/Casual/Pages`.

## Back Office — sidebar

Sidebar Back Office tidak memakai navigation API Filament. Struktur menunya ditulis di `resources/views/vendor/filament-panels/components/layout/index.blade.php`, dan item Technician di `app/Filament/Helpdesk/Navigation/HelpdeskNavigation::technicianItems()`. Setiap item hanya tampil bila user memiliki permission di kolom *Permission*.

| Grup | Menu | Permission | Dokumen modul |
|---|---|---|---|
| Casual Staff | Casual Staff, Posisi Casual, Lowongan Posisi, Absensi Casual, Lembur Casual | `view casual staff`, `view casual positions`, `view casual openings`, `view clock records`, `view overtime requests` | [Workforce](../products/workforce/README.md) |
| Daily Briefing | Monitoring Poin, Kelola Poin, Nilai Briefing, Bobot Penilaian, Pengaturan | `view briefing items`, `view briefing records`, `view briefing scores`, `edit briefing period weights`, `edit briefing settings` | [Daily Briefing](../products/daily-briefing/README.md) |
| Driver | Perjalanan, Rute Perjalanan, Kendaraan, Jenis BBM, Uang Makan Driver, Pengaturan | `view trips`, `view trip routes`, `view vehicles`, `edit trips`, `view driver meal allowance periods`, `edit trips` | [Driver](../products/driver/README.md) |
| Technician | Permintaan Service, Rekap Maintenance, Checklist Poin, Asset QR, Pengaturan | `view service requests`, `view technician monthly maintenance`, `view assets`, `edit service requests` | [Technician](../products/technician/README.md) |
| Information Technology | Permintaan ERP, Modul ERP, Request Types, Bulk Data, Review Data | `view erp requests`, `view erp modules`, `view it request types`, `view bulk product submissions`, `review rnd materials as it` | [Information Technology](../products/information-technology/README.md) |
| System Monitoring | Overview, Service Health, ESB Transactions, Queue & Scheduler, Errors, Deployments, Incidents, Settings | `view system monitoring`, `view monitoring technical details`, `manage monitoring settings` | [System Monitoring](../products/system-monitoring/README.md) |
| Operational | SOP Store, SOP Kategori, Customer Complaints, Store Sales Orders | `view store sops`, `view store sop categories`, `view any customer complaints`, `view any store sales orders` | [Operational](../products/operational/README.md) |
| Research & Development | Project, Template Checkpoint, Recipe Adjustment, Shelf Life, Product Price Index, Prefix Category, Prefix Name, Memo Internal, Review Sourcing | `view rnd projects`, `view rnd project task templates`, `view bill of materials`, `view wip shelf life`, `view product price index`, `view prefix categories`, `view prefix names`, `view any rnd internal memo`, `review material samples as rnd` | [Research & Development](../products/rnd/README.md) |
| Kitchen | Jadwal Produksi | `view kitchen production schedules` | [Kitchen](../products/kitchen/README.md) |
| Purchasing | Vendor Compliance, Permintaan Pembelian, Sourcing Bahan, Material Marketing (Perlu Dipesan) | `view vendor compliance incidents`, `view purchase requests`, `view material sourcings`, `view marketing material fulfillments` | [Purchasing](../products/purchasing/README.md) |
| Quality Control | Audit QC, Checklist QC, Item Journal | `view quality control audits`, `view quality control checklists`, `view quality control item journals` | [Quality Control](../products/quality-control/README.md) |
| Inventory | Stock Card, Receiving, Stock Card Settings, Lokasi, Tipe Lokasi, Product List, Material Marketing (Perlu Diterima) | `view stock cards`, `view goods receipts`, `view stock card settings`, `view locations`, `view location types`, `view product list`, `view marketing material fulfillments` | [Inventory](../products/inventory/README.md) |
| Finance | Sales Report Settings, Sales Report Scores, Sales Report, Compliment Types, Basket Size, Product Pricelist | `edit sales report settings`, `view sales report scores`, `view sales reports`, `view compliment types`, `view basket sizes`, `view material pricelist` | [Finance](../products/finance/README.md) |
| Sales & Growth | Sales Projection | `view sales projections` | [Sales & Growth](../products/sales-growth/README.md) |
| Analytics | Sales Information, Ordering Recommendation, Ordering Settings, Promotion Information, Stock Information | `view sales information`, `view ordering recommendations`, `manage ordering recommendation settings`, `view promotion information`, `view stock information` | [Analytics](../products/analytics/README.md) |
| Brand Marketing | Permintaan Design, Kategori Design, Permintaan Konten | `view design requests`, `view design categories`, `view content requests` | [Brand Marketing](../products/brand-marketing/README.md) |
| Management Access | Pengguna, Role & Permission | `view users`, `view roles` | [Access Management](../products/access-management/README.md) |
| Master | Brand, Branch, Employee, WhatsApp | `view brands`, `view branches`, `view employees`, `edit whatsapp settings` | [Master Data](../products/master-data/README.md) |

### Menambahkan menu

1. Tambahkan item di grup yang sesuai pada `layout/index.blade.php` (atau `HelpdeskNavigation::technicianItems()` untuk Technician) dengan `label`, `icon` Lucide, `perm`, dan route.
2. Daftarkan permission baru di `config/permissions.php`; deploy menjalankan `php artisan permissions:sync`.
3. Pastikan Page/Resource juga mengecek permission yang sama di `canAccess()`/Policy.
4. Perbarui tabel di atas dan dokumen modul terkait.

## Employee App — tile

Launcher Employee App (`app/Filament/Casual/Pages/LauncherPage.php`) menampilkan tile sesuai permission user.

| Tile | Permission | Tujuan | Modul |
|---|---|---|---|
| Absensi | `access employee app attendance` | Clock in/out, lowongan, lembur | [Workforce](../products/workforce/README.md) |
| Driver | `access employee app driver` | Dashboard perjalanan | [Driver](../products/driver/README.md) |
| Teknisi | `access employee app technician` | Daftar permintaan service untuk teknisi | [Technician](../products/technician/README.md) |
| Req. Teknisi | `access employee app technician request` | Permintaan service dari store | [Technician](../products/technician/README.md) |
| Briefing | `access employee app briefing` | Daily Briefing | [Daily Briefing](../products/daily-briefing/README.md) |
| Sales Report | `access employee app sales report` | Laporan sales per shift | [Finance](../products/finance/README.md) |
| Stock Card | `access employee app stock card` | Stock card harian | [Inventory](../products/inventory/README.md) |
| Receiving | `access employee app goods receipt` | Penerimaan barang dari PO | [Inventory](../products/inventory/README.md) |
| Purchasing | `access employee app purchasing` | Permintaan pembelian | [Purchasing](../products/purchasing/README.md) |
| Desain | `access employee app design` | Permintaan desain | [Brand Marketing](../products/brand-marketing/README.md) |
| Konten | `access employee app content` | Permintaan konten | [Brand Marketing](../products/brand-marketing/README.md) |
| ERP | `access employee app erp` | Permintaan ERP ke IT | [Information Technology](../products/information-technology/README.md) |
| Complain | `create customer complaints` | Form komplain pelanggan | [Operational](../products/operational/README.md) |
| Store Sales Order | `create store sales orders` | Sales order event/pesanan khusus | [Operational](../products/operational/README.md) |
| Quality Control | `access employee app quality control` | Audit QC store | [Quality Control](../products/quality-control/README.md) |
| SOP Store | `access employee app store sop` | Membaca dan konfirmasi SOP | [Operational](../products/operational/README.md) |

Halaman Employee App lain yang tidak berupa tile: Profil, Notifikasi, riwayat per fitur, dan halaman Item Journal QC.

## Halaman publik

| Route | Fungsi |
|---|---|
| `/assets/scan/{token}` | Membuka informasi asset dari label QR tanpa login; mengirim laporan kerusakan (`/assets/scan/{token}/report`) wajib login melalui Employee App |
| `/up` | Health route Laravel, dipakai System Monitoring |
| Ingestion deployment (`MonitoringDeploymentIngestionController`) | Callback bertanda tangan dari pipeline release |
