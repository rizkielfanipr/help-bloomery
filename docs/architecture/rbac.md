# RBAC — Role, Permission, dan Branch Scope

Akses diatur dengan Spatie Laravel Permission. Kode memeriksa **permission** dan **branch scope**, bukan nama role (kecuali `SUPERADMIN`). Dokumen ini mencerminkan kode per 27 Oktober 2026.

## Sumber permission

1. `config/permissions.php` — permission per domain dan fitur. Domain di file ini juga menjadi pengelompokan matrix permission di menu **Management Access → Role & Permission**.
2. Resource Back Office yang memakai trait `App\Filament\Helpdesk\Concerns\HasPermissions` — menambahkan permission standar resource (`view/create/edit/delete …`) melalui `permissionDefinition()`. `App\Services\PermissionRegistry` menggabungkan keduanya; bila konfigurasi eksplisit sudah ada, konfigurasi yang dipakai.
3. `php artisan permissions:sync` (dijalankan otomatis saat deploy) membuat permission yang belum ada, menghapus permission usang (`PermissionSynchronizer::DEPRECATED_PERMISSIONS`), dan memberikan semua permission ke `SUPERADMIN`.

Untuk menambah permission: tambahkan di `config/permissions.php` (atau `permissionDefinition()` resource), pakai di Policy/`canAccess()`, lalu berikan ke role melalui menu Role & Permission.

## Domain permission

| Domain | Fitur |
|---|---|
| Akses Back Office | Back Office |
| Akses Employee App | Absensi, Driver, Teknisi, Request Teknisi, Briefing, Submit Daily Briefing, Sales Report, Stock Card, Penerimaan Barang, Item Journal, Purchasing, Desain, Konten, ERP, Quality Control, SOP Store, Customer Complaints, Store Sales Order |
| Human Resources | Casual Staff, Posisi Casual, Lowongan Posisi, Absensi Casual, Daily Briefing, Monitoring Poin, Review Monitoring Poin, Nilai Briefing, Pengaturan Briefing, Bobot Penilaian Briefing |
| Driver | Uang Makan Driver, Perjalanan, Rute Perjalanan, Kendaraan |
| Teknisi | Service Request, Asset QR, Maintenance Bulanan |
| Inventory | Stock Card Settings, Stock Card, Penerimaan, Product List, Proses Material Marketing, Item Journal (nama permission legacy `… quality control item journal…`) |
| Quality Control | Audit QC dan Checklist QC (dari `permissionDefinition()` resource) |
| Finance | Sales Report Settings, Sales Report Scores, Sales Report, Compliment Types, Basket Size, Sourcing Bahan, Product Pricelist |
| Purchasing | Vendor Compliance, Permintaan Pembelian, Sourcing Bahan, Proses Material Marketing |
| Brand Marketing | Permintaan Design, Kategori Design, Permintaan Konten, Marketing Material |
| Information Technology | Permintaan ERP, Modul ERP, Request Type, Bulk Data |
| Research & Development | Bill of Material, Project, Product Price Index, Sourcing Bahan, Memo Internal, Master Bahan Workflow, Recipe Adjustment, Shelf Life WIP, Material Forecast, Task Calendar, Task Template |
| Kitchen | Jadwal Produksi |
| Sales & Growth | Sales Projection |
| Operational | SOP Kategori, SOP Store, Customer Complaints, Store Sales Orders |
| System Monitoring | Monitoring |
| Management Access | Pengguna, Role & Permission |
| Analytics | Sales Information, Ordering Recommendation, Promotion Information, Stock Information |
| Master | Employee, Brand, Cabang, Shift Basket Size, WhatsApp |

Daftar permission lengkap per fitur ada di `config/permissions.php` dan dokumen modul di [Products](../products/README.md).

## Role bawaan

Role dan permission awal dibuat oleh `database/seeders/RolesAndPermissionsSeeder.php`. Seeder memakai `syncPermissions`; menjalankannya di server akan menimpa perubahan role yang dibuat lewat UI, jadi di production gunakan menu Role & Permission.

| Role | Ringkasan akses bawaan |
|---|---|
| `SUPERADMIN` | Semua permission; melewati setiap gate (`Gate::before`) dan akses semua branch |
| `HRD_STAFF` | Back office HR: Casual Staff, absensi, Daily Briefing, dan tile Employee App terkait |
| `CASUAL_STAFF` | Employee App Absensi, lihat absensi dan lembur sendiri |
| `STORE_STAFF` | Employee App store: Briefing, Sales Report, Stock Card, Item Journal (journal sendiri), Purchasing, Desain, Konten, ERP, Req. Teknisi, Complain, Store Sales Order |
| `SUPERVISOR_STORE` | Review Briefing, Sales Report, dan Stock Card sebagai supervisor; Customer Complaints, Store Sales Orders, Employee, shift cabang, Basket Size, SOP Store |
| `DRIVER` | Employee App Driver; lihat perjalanan, rute, kendaraan, jenis BBM |
| `TECHNICIAN` | Employee App Teknisi; Service Request, Maintenance Bulanan, Asset QR |
| `IT_STAFF` | Permintaan ERP, Modul ERP, Request Type, Bulk Data, System Monitoring, Review Data Master Bahan |
| `RND_STAFF` | BOM, Project, Product Price Index, Memo Internal, Recipe Adjustment, Shelf Life, Material Forecast, Task Calendar, Review Sourcing |
| `PURCHASING_STAFF` | Permintaan Pembelian, Sourcing Bahan, serah sampel, Material Marketing, Vendor Compliance |
| `DESIGN_STAFF` | Lihat Project dan Shelf Life, unggah material marketing |
| `INVENTORY_STAFF` | Employee App Receiving, lihat Goods Receipt, terima Material Marketing, Back Office Item Journal (tanpa view-all) |
| `FINANCE_STAFF` | Review Sales Report sebagai Finance, settlement, Compliment Types, Basket Size, Stock Card Finance, Product Pricelist |
| `QUALITY_CONTROL` | Employee App Quality Control, audit QC, tile dan aksi Item Journal |
| `QUALITY_CONTROL_SUPERVISOR` | Audit QC, Item Journal semua user, Checklist QC |

Permission yang sengaja tidak diberikan ke role mana pun oleh seeder, misalnya `reopen material sample approval`, harus diberikan manual sesuai kebijakan.

## Branch scope

Data operasional dibatasi per branch.

| Elemen | Perilaku |
|---|---|
| `users.access_all_branches` | User dapat mengakses semua branch |
| Akses Cabang (`branch_access_ids`) dan Cabang Utama | Daftar branch yang boleh diakses dan branch default; diatur di menu Pengguna |
| `User::canAccessBranch($id)` / `canAccessAllBranches()` / `accessibleBranchIds()` | Dipakai Policy dan query setiap modul berbasis branch |
| `SUPERADMIN` | Selalu dianggap mengakses semua branch |

Beberapa modul memiliki permission tambahan untuk memperluas scope tanpa memberi akses semua branch, misalnya `view all branch rnd project tasks`, `view all branch kitchen production schedules`, dan `view all quality control item journals`. Item Journal tidak menyimpan branch lokal, sehingga `view all quality control item journals` membuka journal semua cabang.

## Akses panel

Aturan masuk panel ada di `User::canAccessPanel()`; lihat [Panels](panels.md).
