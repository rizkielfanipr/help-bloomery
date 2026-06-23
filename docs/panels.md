# Panel Filament

Aplikasi memiliki **5 panel Filament** yang masing-masing melayani domain berbeda.

## Ringkasan Panel

| Panel | Path | Role Akses | Warna | Keterangan |
|-------|------|-----------|-------|-----------|
| Admin | `/admin` | super_admin | Amber | Panel default Filament |
| Helpdesk | `/helpdesk` | super_admin, helpdesk_manager, helpdesk_staff, hr_staff | Blue | Panel utama manajemen operasional |
| Casual | `/casual` | super_admin, hr_staff, casual_staff | Blue | Portal mobile untuk pegawai casual |
| Driver | `/driver` | super_admin, driver | Green | Portal manajemen perjalanan |
| Technician | `/technician` | super_admin, technician | Orange | Portal servis & perbaikan |

---

## Panel Admin (`/admin`)

**Provider:** `app/Providers/Filament/AdminPanelProvider.php`

Panel default Filament untuk administrasi sistem tingkat tinggi. Hanya diakses oleh `super_admin`.

**Resources:**
- User Management
- Role & Permission
- Department

---

## Panel Helpdesk (`/helpdesk`)

**Provider:** `app/Providers/Filament/HelpdeskPanelProvider.php`

Panel utama yang digunakan staff helpdesk untuk mengelola semua operasional. Menggunakan **sidebar kustom** dengan desain yang lebih rapi (Alpine.js + Lucide icons).

**Layout:** `resources/views/vendor/filament-panels/components/layout/index.blade.php`
> Layout ini meng-override sidebar default Filament dengan implementasi kustom. Navigasi sidebar didefinisikan secara hardcode dalam file tersebut, bukan via Filament navigation API.

**Grup Navigasi Sidebar:**

| Grup | Menu Item |
|------|-----------|
| Human Resources | Casual Staff, Posisi Casual, Lowongan Posisi, Absensi Casual, Daily Briefing |
| Master | Branch |
| Driver | Perjalanan, Rute Perjalanan, Kendaraan, Pengaturan Trip |
| Technician | Permintaan Servis, Pengaturan Teknisi |
| Purchasing | *(placeholder — belum aktif)* |
| Information Technology | *(placeholder — belum aktif)* |
| Finance | *(placeholder — belum aktif)* |
| Helpdesk | Semua Permintaan, Template Form |
| Management Access | Pengguna, Role & Permission, Department |

> Sidebar menggunakan Lucide icons via `data-lucide="..."`. Nama icon harus menggunakan konvensi Lucide (mis. `users`, `building-2`), bukan Heroicon.

**Resources (auto-discover dari `app/Filament/Helpdesk/Resources/`):**
- `CasualStaffResource`
- `CasualPositionResource`
- `CasualPositionOpeningResource`
- `CasualClockRecordResource`
- `BranchResource`
- `BriefingRecordResource`
- `FormTemplateResource`
- `HelpdeskRequestResource`
- `ServiceRequestResource`
- `TripResource`
- `TripRouteResource`
- `VehicleResource`

**Route Tambahan:**
- `GET /helpdesk/exports/casual-clock-records` — Export absensi casual ke XLSX (`CasualClockRecordExportController`)

**Pages:**
- `Dashboard`
- `DriverTripSettingsPage`
- `TechnicianSettingsPage`

---

## Panel Casual (`/casual`)

**Provider:** `app/Providers/Filament/CasualPanelProvider.php`

Portal **mobile-first** untuk pegawai casual. Tidak ada sidebar atau topbar Filament standar — semua halaman menggunakan layout bare kustom.

**Custom Domain:** Dapat dikonfigurasi via `CASUAL_DOMAIN` environment variable.

**Auth Custom:**
- `app/Filament/Casual/Pages/Auth/Login.php` — Login menggunakan **Nomor HP** sebagai identifier
- `app/Filament/Casual/Pages/Auth/Register.php` — Register dengan nama, HP, nama bank, nomor rekening, password

Tidak ada token registrasi. Pendaftaran terbuka langsung. Email internal di-generate otomatis (`{phone}@casual.app`).

**Pages:**
| Halaman | Route Name | Fungsi |
|---------|-----------|--------|
| `SelectPosition` | `filament.casual.pages.select-position` | Pilih posisi & lowongan tersedia |
| `ClockPage` | `filament.casual.pages.clock-page` | Clock-in / Clock-out dengan selfie & GPS |
| `AttendancePage` | `filament.casual.pages.attendance-page` | Riwayat absensi |
| `ReportPage` | `filament.casual.pages.report-page` | Laporan pendapatan/absensi |
| `NotificationPage` | `filament.casual.pages.notification-page` | Notifikasi sistem |
| `ProfilePage` | `filament.casual.pages.profile-page` | Edit profil & ganti password |

**Alur Pengguna Baru:**
```
Register → SelectPosition → (hari kerja) → ClockPage
```

---

## Panel Driver (`/driver`)

**Provider:** `app/Providers/Filament/DriverPanelProvider.php`

Portal untuk driver mengelola perjalanan harian.

**Custom Domain:** Dapat dikonfigurasi via `DRIVER_DOMAIN` environment variable.

**Pages:**
| Halaman | Fungsi |
|---------|--------|
| `TripDashboard` | Dashboard utama driver, status trip hari ini |
| `StartTrip` | Mulai perjalanan baru, pilih rute & kendaraan |
| `ActiveTrip` | Perjalanan aktif: check-in waypoint, isi BBM |
| `TripHistory` | Riwayat perjalanan selesai |

**Route Tambahan:**
- `GET /trip-report/pdf` — Generate PDF laporan trip (controller: `TripReportController`)

---

## Panel Technician (`/technician`)

**Provider:** `app/Providers/Filament/TechnicianPanelProvider.php`

Portal untuk teknisi melihat dan menangani permintaan servis yang ditugaskan.

**Custom Domain:** Dapat dikonfigurasi via `TECHNICIAN_DOMAIN` environment variable.

**Resources (auto-discover dari `app/Filament/Technician/Resources/`):**
- `ServiceRequestResource` — View-only untuk request yang ditugaskan ke teknisi tersebut

---

## Menambah Menu ke Sidebar Helpdesk

Karena sidebar Helpdesk menggunakan layout kustom, menu **tidak** ditambahkan via Filament navigation API. Cara menambah menu:

1. Buka `resources/views/vendor/filament-panels/components/layout/index.blade.php`
2. Temukan array `$navGroups` di bagian `@php`
3. Tambahkan item ke grup yang sesuai:

```php
['label' => 'Nama Menu', 'href' => $r('filament.helpdesk.resources.nama-resource.index'), 'active' => request()->is('helpdesk/nama-resource*')],
```

4. Jika menambah grup baru, ikuti struktur yang sudah ada dengan `id`, `label`, `icon`, dan `items`.
