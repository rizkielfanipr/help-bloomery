# Arsitektur Sistem

## Gambaran Umum

Help Bloomery menggunakan arsitektur **multi-panel Filament** di atas Laravel 13. Setiap panel mewakili satu domain fungsional dengan hak akses berbeda, tetapi semua berbagi satu database dan satu codebase.

```
┌─────────────────────────────────────────────────────┐
│                   Laravel Application                │
│                                                     │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────┐ │
│  │ /helpdesk│  │ /casual  │  │ /driver /technician│ │
│  │ Panel    │  │ Panel    │  │ Panels             │ │
│  └──────────┘  └──────────┘  └────────────────────┘ │
│                                                     │
│  ┌─────────────────────────────────────────────────┐ │
│  │         Shared: Models, RBAC, Database          │ │
│  └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

## Struktur Folder

```
app/
├── Console/Commands/          # Artisan commands terjadwal
├── Enums/                     # PHP Enums (TripStatus, ServiceRequestStatus, dll)
├── Filament/
│   ├── Casual/Pages/          # Halaman panel Casual
│   ├── Driver/Pages/          # Halaman panel Driver
│   ├── Forms/Components/      # Custom form component (TemplatePermissionsMatrix)
│   ├── Helpdesk/
│   │   ├── Pages/             # Halaman panel Helpdesk
│   │   ├── Resources/         # Resource Filament per entitas
│   │   └── Widgets/           # Widget dashboard
│   ├── Resources/             # Resource global (User, Role, Department)
│   └── Technician/            # Halaman & resource panel Technician
├── Http/Controllers/
│   ├── Auth/                  # Controller auth Laravel Breeze
│   ├── Helpdesk/              # Controller helpdesk (export, dll)
│   └── TripReportController   # PDF generation
├── Models/                    # Eloquent models
├── Observers/                 # Model observers (activity tracking)
├── Providers/Filament/        # 5 Filament PanelProvider
└── View/Components/           # Blade view components

resources/
├── css/filament/              # Theme CSS per panel (helpdesk, driver, dll)
├── views/
│   ├── filament/
│   │   └── casual/            # Blade views mobile-first panel Casual
│   ├── pdf/                   # Template PDF (laporan trip)
│   └── vendor/filament-panels/ # Override layout Filament (sidebar helpdesk)

database/
├── migrations/                # ~45 migration files
├── factories/                 # Model factories untuk testing
└── seeders/                   # Data seed awal

routes/
├── web.php                    # Root redirect, UI mockup routes, export routes helpdesk
├── auth.php                   # Redirect login/register ke panel helpdesk
├── driver.php                 # Route PDF trip report
```

## Alur Request

```
Browser Request
      │
      ▼
   routes/web.php
      │
      ├── / → redirect /helpdesk
      │
      └── Filament Panel Router
               │
               ├── /helpdesk/*  → HelpdeskPanelProvider
               ├── /casual/*    → CasualPanelProvider
               ├── /driver/*    → DriverPanelProvider
               └── /technician/* → TechnicianPanelProvider
                        │
                        ▼
               Filament Resource/Page
                        │
                        ▼
               Livewire Component
                        │
                        ▼
               Eloquent Model → MySQL
```

## Pola Desain Utama

### 1. Multi-Panel Filament
Setiap panel punya `PanelProvider` sendiri yang mendefinisikan path, tema, role akses, resource, dan middleware. Panel Helpdesk menggunakan layout custom (`resources/views/vendor/filament-panels/components/layout/index.blade.php`) yang me-override sidebar bawaan Filament dengan implementasi Alpine.js kustom.

### 2. RBAC (Role-Based Access Control)
Menggunakan **Spatie Laravel Permission**. Setiap panel memfilter akses berdasarkan role. Resource menggunakan `Policy` atau guard Filament untuk membatasi operasi per-role.

### 3. Domain-per-Panel
Panel Casual, Driver, dan Technician mendukung custom domain via environment variable (`CASUAL_DOMAIN`, dll). Jika tidak ada, panel fallback ke path di domain utama.

### 4. Singleton Settings
Beberapa konfigurasi operasional disimpan sebagai model singleton:
- `DriverTripSettings` — Pengaturan trip global
- `TechnicianSettings` — Batas pekerjaan per hari

### 5. Form Dinamis (Helpdesk)
`HelpdeskFormTemplate` + `HelpdeskFormField` memungkinkan staff membuat form tiket baru tanpa coding. Field, tipe, validasi, dan role yang bisa melihat form semuanya tersimpan di database.

### 6. Mobile-First Panel
Panel Casual dirancang untuk digunakan dari smartphone. Layout menggunakan komponen Blade kustom tanpa sidebar/topbar Filament standar. Semua interaksi (clock-in selfie, GPS, notifikasi) dioptimalkan untuk mobile.

## Enums

| Enum | Nilai |
|------|-------|
| `TripStatus` | `pending`, `in_progress`, `completed` |
| `ServiceRequestStatus` | `submitted`, `in_progress`, `warranty`, `completed` |
| `RequestStatus` | `draft`, `submitted`, `in_review`, `approved`, `in_progress`, `completed`, `rejected` |
| `FormFieldType` | Tipe field form dinamis (text, select, dll) |
| `DesignCategory` | Kategori permintaan desain |
| `ErpModule` | Modul ERP yang memerlukan perbaikan |
| `ShiftType` | Tipe shift kerja |
| `DeliveryStatus` | Status pengiriman |
