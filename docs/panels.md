# Panel Filament

Panduan struktur aktual setelah refactoring modul dan identitas Employee, 18 September 2026. Provider di `bootstrap/providers.php` merupakan sumber registrasi panel; route aktual diperiksa melalui `php artisan route:list`.

## Panel dan Akses

| ID panel | Provider | Domain/path | Pemeriksaan akses |
| --- | --- | --- | --- |
| admin | AdminPanelProvider | `/admin` | User aktif, `access backoffice`, dan permission Management Access |
| helpdesk | HelpdeskPanelProvider | Root domain back office | User aktif dengan `access backoffice` |
| employee | EmployeePanelProvider | `config('app.domain')` pada root; fallback `/casual` untuk kompatibilitas | User aktif dengan permission dari grup Akses Employee App |
| driver | DriverPanelProvider | DRIVER_DOMAIN pada root; fallback `/driver` | User aktif dengan `access employee app driver` |
| technician | TechnicianPanelProvider | TECHNICIAN_DOMAIN pada root; fallback `/technician` | User aktif dengan `access employee app technician` |

Domain environment dan role existing tidak diganti. Akses panel mengikuti `User::canAccessPanel`; akses fitur aplikasi user juga diperiksa oleh `EnsureEmployeeAppFeaturePermission` dan guard halaman/resource existing. SUPERADMIN mengikuti Gate existing.

## Helpdesk

- Halaman bersama: `app/Filament/Helpdesk/Pages`, termasuk Dashboard dan Auth/Login.
- Halaman/resource/widget modul: `app/Filament/Helpdesk/Modules/{Module}/{Pages,Resources,Widgets}`.
- Resource bersama yang juga dipakai Admin: `app/Filament/Resources`. Kepemilikannya dipertahankan; jangan memindahkan tanpa audit penggunaan lintas panel.
- Modul tersedia: Analytics, Briefing, Casual, Content, Design, Driver, Erp, Finance, Inventory, MasterData, Purchasing, QualityControl, Rnd, SalesPlanning, Technician, StoreSop.
- Struktur resource tetap menggunakan Pages, Schemas, Tables, dan RelationManagers sesuai kebutuhan existing.

Discovery module dilakukan oleh HelpdeskPanelProvider. Root discovery existing tetap didukung untuk halaman bersama. Jangan meregistrasikan kembali class yang sudah ditemukan tanpa kebutuhan.

- View modul Helpdesk: `resources/views/filament/helpdesk/{module}/{pages,widgets,...}`; detail/partial berada di modul pemiliknya.
- Actions: `app/Actions/{Module}`. Services integrasi: `app/Services/Esb`; service bisnis: `app/Services/{Domain}`.

## Employee App

Seluruh aplikasi user menggunakan `app/Filament/Employee`, bukan hanya tenaga casual. Casual merupakan satu modul di dalam aplikasi ini.

- Halaman bersama dan autentikasi: `app/Filament/Employee/Pages`.
- Modul: `app/Filament/Employee/Modules/{Module}/{Pages,Resources}`.
- Modul tersedia: Briefing, Casual, Content, Design, Driver, Erp, Purchasing, QualityControl, Receiving, SalesReport, StockCard, StoreSop, Technician.
- View bersama: `resources/views/filament/employee/{pages,layouts}`.
- View modul: `resources/views/filament/employee/{module}/{pages,...}`.
- Theme: `resources/css/filament/employee/theme.css`.

Login diarahkan ke launcher pada root aplikasi user. Nama route aktif menggunakan `filament.employee.*`. Domain dan URL lama tetap sama, termasuk fallback `/casual`; nama route PHP lama bukan URL eksternal. QR asset dan export mempertahankan URI existing. Endpoint export briefing menggunakan nama `employee.exports.briefing-score-pdf` dengan URI kompatibilitas existing.

Theme localStorage menggunakan `employee-theme` dan membaca `casual-theme` sebagai fallback untuk preferensi yang sudah tersimpan. Manifest/service worker tidak memerlukan perubahan URL karena domain, scope, dan start URL tidak diubah.

File dashboard historis `routes/employee.php`, `routes/helpdesk.php`, dan `routes/technician.php` sudah dihapus karena tidak pernah dimuat. Placeholder `routes/auth.php` dan scaffold controller/form/view Breeze yang tidak terhubung ke route aktif juga dihapus. Halaman Employee aktif berasal dari provider Filament; route scan QR dan export berasal dari `routes/web.php`. File `routes/driver.php` tetap digunakan dan tidak dihapus.

## Sidebar dan Permission

Sidebar Helpdesk dirender oleh `resources/views/vendor/filament-panels/components/layout/index.blade.php`. Layout bersama tambahan berada di `resources/views/components/layouts/helpdesk-app.blade.php`. Menambahkan resource/permission tidak otomatis menambahkan item sidebar kustom.

Item Technician lintas layout dipusatkan di `app/Filament/Helpdesk/Navigation/HelpdeskNavigation.php`. Perbarui definisi item yang digunakan caller, dengan label, icon Lucide, permission, dan nama route aktif. Ikon Lucide menggunakan `data-lucide`; komponen Heroicon tetap dipakai pada UI yang mengikuti metode tersebut.

Permission tidak dibuat berdasarkan nama folder modul. Registry mengambil konfigurasi `config/permissions.php` serta permissionDefinition resource yang ditemukan. Guard backend tetap wajib meskipun sidebar atau tombol sudah disembunyikan.

## Verifikasi dan Deployment

Jalankan test terkait panel, login, launcher, route langsung dengan/tanpa permission, dan scope cabang. Periksa `php artisan route:list` setelah mengubah discovery atau identitas panel; pastikan tidak ada endpoint hilang atau duplikat.

Deployment pemindahan class membutuhkan seluruh file baru beserta penghapusan file lama, build manifest terbaru, autoload, serta pembersihan cache route/view/Filament. Jangan mengunggah hanya file baru dan meninggalkan class lama. Gunakan `composer dump-autoload`, `php artisan optimize:clear`, lalu rebuild cache sesuai proses deployment existing. Tidak ada migration/seeder baru pada refactoring ini.
