# Help Bloomery

Sistem internal Bloomery Patisserie untuk manajemen SDM, operasional, dan helpdesk — dibangun di atas Laravel 13 + Filament 5.

## Dokumentasi

| Dokumen | Deskripsi |
|---------|-----------|
| [Setup & Instalasi](docs/setup.md) | Persyaratan, instalasi, variabel environment |
| [Arsitektur Sistem](docs/architecture.md) | Tech stack, struktur folder, alur request |
| [Panel Filament](docs/panels.md) | Lima panel admin, role akses, routing |
| [Model & Database](docs/models.md) | Semua model, relasi, struktur tabel |
| [RBAC — Role & Permission](docs/rbac.md) | Daftar role, permission, dan aturan akses |
| [Modul: Casual Staff](docs/modules/casual.md) | Registrasi, absensi, shift, posisi casual |
| [Modul: Driver & Trip](docs/modules/driver.md) | Manajemen perjalanan, waypoint, BBM |
| [Modul: Helpdesk](docs/modules/helpdesk.md) | Tiket, form template dinamis, request |
| [Modul: Technician](docs/modules/technician.md) | Permintaan servis, siklus repair, garansi |

## Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Framework | Laravel 13, PHP 8.4 |
| Admin Panel | Filament 5 |
| Real-time UI | Livewire 4 |
| Frontend | Tailwind CSS 4, Alpine.js 3 |
| Database | MySQL |
| RBAC | Spatie Laravel Permission 7 |
| Testing | Pest 4 |

## Panel Akses

| Panel | URL | Role |
|-------|-----|------|
| Helpdesk | `/helpdesk` | super_admin, helpdesk_manager, helpdesk_staff, hr_staff |
| Casual | `/casual` | super_admin, hr_staff, casual_staff |
| Driver | `/driver` | super_admin, driver |
| Technician | `/technician` | super_admin, technician |
| Admin | `/admin` | super_admin |

## Quick Start

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run build
```
