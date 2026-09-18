# Phase 1 — Audit dan Baseline Codebase

Tautan file pada audit ini mengikuti lokasi baru setelah Phase 6–7; nama route/class dalam tabel tetap merekam baseline e1c5724.

## 1. Status dan cakupan

| Field | Hasil |
| --- | --- |
| Tanggal audit | 18 September 2026 |
| Baseline Git | `e1c5724` pada branch `main` |
| Git saat mulai | Bersih; tidak ada perubahan fitur lokal yang perlu dipisahkan |
| Phase aktif | Phase 1 — Audit dan baseline |
| Modul pertama | Teknisi, Service Request, Asset QR, maintenance, dan dependensi akses/login |
| Hasil test | 39 passed, 232 assertions; durasi sekitar 20,3 detik |
| Perubahan runtime | Tidak ada; hanya dokumentasi audit/status PRD |
| Phase 2 | Belum dimulai |
| Label sidebar English | Bagian 17 tetap ditunda |

Acuan: [PRD refactoring](codebase-refactoring-prd.md). Hasil audit bersifat baseline lokal, bukan verifikasi deployment production. Inventaris struktur, route, service, action, dan komponen dilakukan lintas aplikasi; pemeriksaan perilaku mendalam dan test dibatasi pada modul pertama serta akses/login yang terkait. Tidak mengklaim seluruh fitur pada semua modul sudah diuji.

Tidak menjalankan migration, permission sync, perubahan role, transaksi ESB, modifikasi attachment remote, atau perubahan namespace. Test menggunakan environment test dan fake storage pada skenario attachment. Tidak membaca atau menyertakan credential .env.

## 2. Inventaris lintas aplikasi

Angka file berikut menghitung PHP/Blade PHP secara rekursif; jumlah file resource mencakup class halaman/schema/helper, bukan jumlah resource bisnis.

| Area | Jumlah file |
| --- | ---: |
| `app/Filament/Casual/Pages` | 49 |
| `app/Filament/Helpdesk/Pages` | 29 |
| `app/Filament/Helpdesk/Resources` | 199 |
| `app/Filament/Resources` | 8 |
| `app/Actions` | 9 |
| `app/Services` | 27 |
| `app/Models` | 106 |
| `resources/views/components` | 32 |
| `tests/Feature` | 82 |

`app/Policies`, `app/Jobs`, dan `app/Notifications` tidak ditemukan sebagai direktori pada baseline. Otorisasi tersebar pada resource, middleware, User, trait, dan service. Notifikasi database juga dipanggil langsung melalui Filament Notification. Ketiadaan direktori bukan instruksi untuk membuat folder kosong.

Daftar route lengkap dan inventaris komponen/service/action tersedia pada [lampiran inventaris](codebase-refactoring-phase1-inventory.md).

## 3. Panel dan discovery aktual

Lima provider panel didaftarkan dalam `bootstrap/providers.php`.

| Panel ID | Path/domain menurut provider | Discovery utama |
| --- | --- | --- |
| `admin` | `/admin` | `Filament/Resources`, `Filament/Pages`, `Filament/Widgets` |
| `helpdesk` | Root, `path('')` | `Filament/Helpdesk/Resources` **dan** `Filament/Resources`; Helpdesk Pages/Widgets |
| `casual` | Root pada `config('app.domain')` bila diatur; fallback `/casual` | `Filament/Casual/Resources`, Pages, Widgets |
| `driver` | Root pada `DRIVER_DOMAIN` bila diatur; fallback `/driver` | `Filament/Driver/Resources`, Pages, Widgets |
| `technician` | Root pada `TECHNICIAN_DOMAIN` bila diatur; fallback `/technician` | `Filament/Technician/Resources`, Pages, Widgets |

Local route snapshot: domain Casual `casual.help-bloomery.test`; panel Technician fallback `/technician`. Domain kosong dalam route list berarti route tidak dibatasi domain pada registrasinya, bukan bukti domain production tertentu.

`docs/panels.md` masih memuat ringkasan historis `/helpdesk`, `/casual`, role lowercase, dan beberapa submenu placeholder. Provider, route snapshot, dan kode permission aktual menjadi baseline yang lebih akurat untuk refactoring. Dokumen historis tidak diubah dalam phase ini supaya audit dan perubahan acuan dapat ditinjau terpisah.

Helpdesk dan Admin sama-sama menemukan resource shared. Jangan memindahkan shared resource sebelum memeriksa kedua panel dan permission discovery.

## 4. Route dan login

Perintah audit:

```bash
php artisan route:list --json
php artisan route:list --except-vendor --json
```

Hasil: **287 route terdaftar**, **261 setelah `--except-vendor`**. Route vendor/login/framework tetap disertakan pada lampiran lengkap untuk menjaga konteks login. Jumlah menghitung baris route, bukan setiap HTTP method sebagai route terpisah.

### Registrasi file route

- `bootstrap/app.php` mendaftarkan `routes/web.php`, `routes/console.php`, dan health endpoint.
- `routes/web.php` memuat `routes/auth.php` dan `routes/driver.php`.
- `routes/helpdesk.php`, `routes/casual.php`, dan `routes/technician.php` ditemukan tetapi tidak direquire oleh bootstrap/web/provider yang diperiksa. Jangan menganggap dashboard closure di file tersebut aktif.
- Halaman modul utama diregistrasi melalui discovery Filament, bukan melalui file route modul historis tersebut.

### Kontrak login/QR

- Root Casual bernama `filament.casual.home` mengarah ke LauncherPage.
- Route lama `filament.casual.pages.launcher-page` tetap ada; test membuktikan redirect ke launcher root.
- Login Casual kustom terdapat di `app/Filament/Casual/Pages/Auth/Login.php`.
- Login biasa diarahkan ke launcher; intended URL asset scan merupakan pengecualian yang dipertahankan.
- `assets.scan` dan `assets.login` berada pada domain employee app bila dikonfigurasi.
- `assets.report` adalah POST dengan middleware auth; controller memeriksa asset active dan cakupan cabang.
- Route scan pada domain non-employee berfungsi sebagai redirect legacy bila app domain diatur.
- Route label/QR asset memakai auth dan pemeriksaan permission `print asset labels` serta cabang pada controller.

Nama route, bridge login, redirect, token QR, dan export tidak diganti pada Phase 2.

## 5. Permission, branch scope, dan sidebar

Sumber permission adalah `config/permissions.php` ditambah definisi resource melalui `PermissionRegistry::groups()`. Label/sidebar tidak otomatis berasal dari nama resource Filament.

| Area | Baseline pemeriksaan |
| --- | --- |
| Akses panel | `User::canAccessPanel()` menolak akun inactive dan memakai permission sesuai panel |
| Administrator | `Gate::before` memberi bypass SUPERADMIN |
| Multi-branch | `User::accessibleBranchIds()`, `canAccessBranch()`, `canAccessAllBranches()` |
| Tile Teknisi | `access employee app technician` |
| Tile Request Teknisi | `access employee app technician request` |
| Resource request | view/create/edit/delete service requests |
| Asset | view/create/edit/delete assets, generate asset qr codes, print asset labels |
| Maintenance | view/create/edit/delete technician monthly maintenance sesuai caller |
| Backend workflow | edit service requests + branch accessible + assigned technician/unassigned/admin sesuai service |
| Report outsource | `scheduled_by === actor.id` dan status Outsource |

Sidebar Helpdesk: `resources/views/vendor/filament-panels/components/layout/index.blade.php`. Item Teknisi disediakan `HelpdeskNavigation::technicianItems()` dengan Lucide. Ikon konten memakai Heroicons; jangan mencampur metode sidebar dan konten.

Cakupan yang **tidak seragam pada kode existing**:

- Casual/Technician ServiceRequestResource membatasi cabang accessible dan pekerjaan unassigned/assigned kepada user; admin all-branches mendapat pengecualian.
- Helpdesk ServiceRequestResource memakai query eager loading tanpa branch filter eksplisit. HasPermissions memeriksa ability, bukan branch. Ini temuan scope existing; hak baca back office global atau terbatas harus diputuskan sebelum konsolidasi, bukan diubah otomatis.
- TechnicianMonthlyMaintenanceResource legacy membatasi ke primary `branch_id`; Helpdesk legacy tidak memasang branch filter eksplisit; maintenance baru Casual memakai accessible branches saat membuat dan owner technician saat membaca detail.
- Middleware feature Employee memetakan route request/logbook/history; maintenance memakai pemeriksaan permission page/action sendiri.

Test baseline bukan bukti bahwa semua kombinasi permission/branch pada seluruh resource sudah aman. Gap tersebut masuk daftar follow-up, bukan regresi baru.

## 6. Dependency modul pertama

### Entry point dan UI

| File/area | Tanggung jawab |
| --- | --- |
| `Casual/Pages/TechnicianRequestPage.php` | Form manual, validasi, upload, create request, assignment, WhatsApp CTA |
| `Http/Controllers/AssetIssueReportController.php` | Form hasil QR, validasi photo, create request, assignment, notifikasi |
| `Http/Controllers/AssetScanController.php` | Identitas asset, active request, repair count/history sesuai cakupan user |
| `Casual/Resources/ServiceRequests` | Logbook/list/detail teknisi dalam aplikasi user |
| `Technician/Resources/ServiceRequests` | Panel teknisi legacy dengan workflow yang juga memakai service |
| `Helpdesk/Resources/ServiceRequests` | CRUD/rekap back office, export, detail, klaim garansi back office |
| `Casual/Pages/TechnicianRequestHistoryPage.php` | Riwayat requester, klaim garansi, upload report vendor |
| `Casual/Pages/TechnicianHistoryPage.php` | Riwayat pekerjaan teknisi |
| `Casual/Pages/TechnicianMaintenancePage.php` | Membuat draft bulan berjalan berdasarkan checklist active |
| `Casual/Pages/TechnicianMaintenanceDetailPage.php` | Isi item, foto, draft, submit maintenance baru |
| `Helpdesk/Pages/TechnicianSettingsPage.php` | Kuota pekerjaan harian |
| `Helpdesk/Resources/Assets` | Asset registry dan label/QR |
| Helpdesk/Technician TechnicianMonthlyMaintenanceResource | Model maintenance legacy dengan point bulanan |

### Domain dan dependensi bersama

- `ServiceRequestWorkflow`: authorize, schedule, start, complete, outsource, submit/verify vendor report, status guard, kondisi asset.
- `HasServiceRequestWorkflow`: action form schedule, awaiting parts/resume, outsource, verify report; beberapa status update masih dilakukan di trait.
- `AssetServiceRequestAssigner`: pilih teknisi active role TECHNICIAN yang dapat mengakses branch, urut workload dan waktu assignment.
- `ServiceRequest`, `ServiceRequestRepair`, `ServiceRequestStatus`: request, siklus repair, status.
- `Asset`, `AssetObserver`, `QrCodeGenerator`: identitas, token, QR file, relasi request/repair dan status asset.
- `TechnicianSettings`: singleton kuota, default 5 pekerjaan per hari.
- `TechnicianMaintenance`, Item, Checklist: maintenance baru; `TechnicianMonthlyMaintenance`: model legacy berbeda.
- `User`, Branch, user_branches: ownership dan scope.
- `WhatsappCtaBuilder`: CTA request manual; jangan mengganti dengan pengiriman pesan otomatis.
- `ExportServiceRequestsXlsxAction`: export query caller, date range inklusif, chunk 500, temporary download file.
- `AssetLabelController`: label PDF dan download QR, scope cabang.
- `AutoCompleteWarrantyCommand`: completion warranty, dipanggil scheduler daily.

Path lengkap kandidat file untuk Phase 2 disertakan pada lampiran. Dependensi ini tidak berarti semua file perlu diubah pada satu PR.

## 7. Baseline alur bisnis Teknisi

| Alur | Kondisi/perilaku existing yang dipertahankan |
| --- | --- |
| Request manual | User primary branch wajib tersedia; tanpa asset selector dan tanpa jadwal user; source manual, status Submitted |
| Request QR | Asset token valid, active, user authenticated dan branch accessible; issue + 1–5 foto max 5 MB; source asset_qr; jadwal null |
| Assignment | Teknisi eligible dengan workload paling sedikit; jika tidak tersedia request tetap unassigned |
| Schedule | Submitted/Scheduled/ReSubmitted; edit permission, scope cabang/assignment; tanggal minimal hari ini dan kuota global |
| Start | Submitted/Scheduled/ReSubmitted; row lock; create repair cycle; InProgress; jadwal default hari ini bila kosong; unsafe asset dibuat inactive |
| Awaiting parts | InProgress → AwaitingParts; resume → InProgress; update masih di trait |
| Complete internal | InProgress; activeRepair wajib ada; after photos/notes dan completed timestamp; Warranty 30 hari |
| Outsource | Submitted/Scheduled/ReSubmitted/InProgress/AwaitingParts → Outsource; requester menerima kebutuhan report |
| Vendor report | Requester yang sama mengirim data/files; Outsource → AwaitingVerification |
| Verify revision | AwaitingVerification → Outsource, report diminta revisi; tidak mencatat completed repair |
| Verify approved | Catat/update repair selesai; kondisi akhir asset; Warranty 30 hari |
| Warranty claim user | Owner requester, status Warranty → ReSubmitted; technician null; notes/attachments tersimpan |
| Warranty claim back office | Action detail status Warranty → Submitted; technician dan warranty timestamp dibersihkan; berbeda dari flow user |
| Warranty expiry | Command daily memakai `<= now()`; model juga punya `isPast()` dan dipanggil saat mount view tertentu |
| Maintenance baru | Draft per branch/year/month; snapshot checklist active; item result/notes/photo; submit status submitted |

Status asset bukan enum/database status terpisah: `Asset::getStatusAttribute()` mengembalikan Inactive bila is_active false, Maintenance bila request belum Warranty/Completed ada, selain itu Active. Setelah repair safe, service mempertimbangkan request lain berkondisi unsafe sebelum mengaktifkan asset. Jangan menyederhanakan aturan ini menjadi satu update flag tanpa test.

## 8. Integrasi dan attachment

### Integrasi lintas aplikasi yang teridentifikasi

- ESB: EsbService, EsbCoreService, EsbItemJournalService, EsbGoodsReceiptService, EsbCompanyProductService, EsbPromotionService, EsbStockMovementService.
- Receiving: InboundGoodsReceiptQcService, PurchaseOrderPriceSyncService.
- Stock Card: StockCardCategoryFilter dan StockCardEsbSynchronizer.
- R&D: SyncRndEsbMaterialFromRemote, RndProjectMaterialForecastService, ProductPriceIndexService.
- Sales Report: SalesReportReconciliationService dan SalesReportScoreCalculator.
- IT: ItBusinessHoursService dan ErpRequestSlaService.
- Store SOP: StoreSopPublisher.

Service ESB memiliki jalur autentikasi/cache/retry yang tidak semuanya terpusat. EsbItemJournalService menyediakan request/result/token untuk Item Journal dan Stock Movement; Goods Receipt mempunyai login lock dan perusahaan khusus. Ini inventaris kandidat Phase 4, bukan izin menyatukan client atau melakukan request production.

### Storage aktual

`config/filesystems.php`: disks local, public, s3, **b2**. Disk bernama b2 memakai driver S3 dengan konfigurasi **R2** dan visibility private. Nama legacy b2 bukan bukti masih memakai Backblaze.

| Jenis | Disk/path existing |
| --- | --- |
| Request manual/QR | b2 / `service-request-attachments` |
| Request back office | b2 / `service-requests/attachments` |
| Repair before/after | b2 / `service-requests/before`, `service-requests/after` |
| Warranty claim user | b2 / `service-request-warranty-claims` |
| Vendor report | b2 / `service-requests/outsource` |
| Maintenance baru | b2 / `technician-maintenance/evidence` |
| Asset QR | b2 / `assets/{branch_id}/{asset_id}.svg` |
| Profile teknisi | b2, path avatar user |

Model lain seperti RndProjectDocument, StoreSop, MaterialSourcing, dan TechnicianMaintenanceItem menyediakan temporaryUrl dengan fallback url. Audit remote bucket/ACL dan seluruh jalur file tidak dilakukan. Jangan mengganti disk, memindahkan object, atau membuat file private menjadi public sebagai bagian ekstraksi action.

## 9. Reusable UI existing

Inventaris 32 komponen meliputi bottom nav per modul, technician mobile-page-header, helpdesk desktop-detail-shell, rnd picker-modal, attachment/input primitives umum, dan WhatsApp success card. Tidak menemukan satu attachment component universal pada inventaris komponen ini; form ERP menjadi pola visual yang perlu diperiksa sesuai scope Phase 3.

R&D picker modal menyediakan shell; fokus/backdrop/close ditangani caller. Modal generik components/modal mempunyai kontrak event berbeda. Jangan memilih salah satu dan mengganti semua caller tanpa audit binding.

Style/scoring/R&D bukan perubahan Phase 1. [PRD konsistensi UI](ui-consistency-prd.md) merupakan scope terkait yang dipakai saat Phase 3 atau tugas UI tersendiri.

## 10. Temuan existing dan gap

| ID | Temuan berbukti | Tindak lanjut |
| --- | --- | --- |
| A1 | docs/panels.md berbeda dari provider/permission aktual | Pembaruan dokumentasi panel terpisah, memakai route baseline |
| A2 | ServiceRequestWorkflow sudah ada; start/complete/outsource tidak perlu client/service kedua | Ekstrak proses yang belum bersama, reuse service |
| A3 | Create request manual/QR berbeda caller/validasi/path; awaiting parts/resume dan klaim masih update langsung | Kandidat action kecil; pertahankan perbedaan kontrak |
| A4 | Helpdesk scope baca tidak eksplisit per cabang; scope maintenance legacy/new berbeda | Klarifikasi intended scope sebelum menambah filter; characterization tests pada area yang disentuh |
| A5 | Maintenance legacy point bulanan dan maintenance checklist baru hidup berdampingan | Jangan merge model/tabel hanya karena nama mirip |
| A6 | Requester claim → ReSubmitted, back office claim → Submitted | Dua perilaku existing; konsolidasi memerlukan keputusan eksplisit |
| A7 | Auto-completion terjadi melalui scheduler dan saat mount view | Ekstraksi harus menjaga timestamp/boundary existing; tambah test jika disentuh |
| A8 | Assignment melakukan count/max query per kandidat dan schedule count global tanpa lock kuota | Kandidat performa/concurrency; bukan regresi yang dibuktikan di production |
| A9 | Back office create membaca scheduled_date sebelum guard empty, dan tidak memperlihatkan pengisian branch pada form yang diperiksa | Karakterisasi create tanpa jadwal/branch diperlukan sebelum mengubah create path |
| A10 | Submit maintenance baru mewajibkan foto semua item walau checklist punya requires_photo | Catat kontrak existing; jangan mengubah requirement foto melalui refactor |

Gap test yang belum dibuktikan suite pilihan: seluruh action pada Helpdesk/Technician/Casual dengan kombinasi role/cabang, kuota concurrent, auto-expiry tepat boundary, kegagalan storage/notification sesudah create, assignment concurrent, owner berbeda pada draft branch/month yang sama, serta keyboard/QR camera pada browser nyata. Ini backlog test sesuai perubahan Phase 2, bukan klaim test gagal.

## 11. Bukti test dan batas verifikasi

```bash
php artisan test --compact tests/Feature/ServiceRequestWorkflowTest.php tests/Feature/ServiceRequestQuotaTest.php tests/Feature/AssetIssueReportTest.php tests/Feature/AssetTest.php tests/Feature/ServiceRequestExportTest.php tests/Feature/UserBranchAccessTest.php tests/Feature/CasualLoginRedirectTest.php tests/Feature/LauncherPageTest.php
```

**39 test passed, 232 assertions.** Tidak ada kegagalan pada suite baseline terpilih. Rincian cakupan:

| Test file | Fokus |
| --- | --- |
| ServiceRequestWorkflowTest | Manual, schedule/start, unsafe asset, internal complete, warranty claim, outsource/revision, maintenance/history render |
| ServiceRequestQuotaTest | Kuota dan singleton settings |
| AssetIssueReportTest | Auth/scope, foto wajib, assignment/no-technician, QR/login/domain dan scan UI |
| AssetTest | Stable identity/QR, branch scope, label downloads |
| ServiceRequestExportTest | XLSX dan date range inklusif |
| UserBranchAccessTest | Primary branch dan multi-branch persistence |
| CasualLoginRedirectTest | Login menuju launcher |
| LauncherPageTest | Root/legacy redirect, permission tiles, direct URL access |

Tidak menjalankan seluruh 82 file Feature, tidak melakukan security audit menyeluruh, tidak memverifikasi production, tidak mengakses API ESB live, dan tidak memeriksa visual mobile/scan camera langsung. Baseline ini cukup untuk memilih test awal modul pertama; tambahkan test bermakna saat ekstraksi menyentuh gap di atas.

Tidak perlu npm build/Pint untuk phase ini karena hanya Markdown berubah. Referensi lokal, status phase, jumlah route, dan whitespace diperiksa secara programatis.

## 12. Rekomendasi scope pertama Phase 2

1. Pilih satu proses: pembuatan request manual/QR atau perpindahan status awaiting parts/resume.
2. Jika memilih create, karakterisasi perbedaan validasi, owner/branch, attachment path, assignment, notifikasi, dan CTA sebelum ekstraksi.
3. Reuse ServiceRequestWorkflow dan AssetServiceRequestAssigner; jangan memindahkan namespace/panel.
4. Pertahankan source, status awal, jadwal null, repair history, asset flag, route, dan nama permission.
5. Jalankan test terdampak dari baseline; tambah test scope caller jika area itu diubah.
6. Perlakukan keputusan mengubah scope akses atau menyamakan klaim garansi sebagai pekerjaan perilaku terpisah.

Phase 2 hanya dimulai pada instruksi berikutnya; dokumen ini tidak mengeksekusi roadmap otomatis.

## 13. Exit criteria Phase 1

- [x] Git awal diperiksa dan tidak ada perubahan fitur yang ditimpa.
- [x] Panel/discovery dan registrasi route aktif teridentifikasi.
- [x] Permission/sidebar dan perbedaan scope modul pertama terpetakan.
- [x] Baseline manual/QR, schedule, repair, outsource, warranty, asset, dan maintenance tercatat.
- [x] Service/action/UI/storage/integrasi terinventaris.
- [x] File/dependensi dan test minimum modul pertama tersedia.
- [x] Temuan existing/gap dipisahkan dari regresi baru.
- [x] Test baseline terpilih lolos.
- [x] Phase 2, rename panel, sidebar English, dan refactor runtime belum dimulai.
