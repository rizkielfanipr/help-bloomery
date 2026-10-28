# Help Bloomery

Sistem internal Bloomery Patisserie untuk operasional store, kantor pusat, R&D, dan back office — dibangun di atas Laravel 13 + Filament 5.

## Dokumentasi

Semua dokumentasi ada di [docs/](docs/README.md).

| Dokumen | Deskripsi |
|---------|-----------|
| [Portal Dokumentasi](docs/README.md) | Indeks utama, struktur, dan aturan kontribusi |
| [Setup & Instalasi](docs/setup.md) | Persyaratan, instalasi, variabel environment |
| [Arsitektur Sistem](docs/architecture/overview.md) | Tech stack, struktur folder, pola desain, scheduler |
| [Panel dan Navigasi](docs/architecture/panels.md) | Lima panel, menu Back Office, tile Employee App |
| [Data Model](docs/architecture/data-model.md) | Model dan tabel per domain |
| [RBAC](docs/architecture/rbac.md) | Role, permission, dan branch scope |
| [Dokumentasi Modul](docs/products/README.md) | Satu dokumen per modul bisnis |

## Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Framework | Laravel 13, PHP 8.4 |
| Admin Panel | Filament 5 |
| Real-time UI | Livewire 4 |
| Frontend | Tailwind CSS 4, Alpine.js 3 |
| Database | MySQL |
| RBAC | Spatie Laravel Permission |
| Testing | Pest 4 |

## Panel

| Panel | URL | Syarat akses |
|-------|-----|--------------|
| Back Office | `/` | `access backoffice` |
| Employee App | `/casual` (atau `APP_DOMAIN`) | Minimal satu permission *Akses Employee App* |
| Driver | `/driver` (atau `DRIVER_DOMAIN`) | `access employee app driver` |
| Technician | `/technician` (atau `TECHNICIAN_DOMAIN`) | `access employee app technician` |
| Admin | `/admin` | `access backoffice` + permission Management Access |

## Quick Start

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run build
```

Detail lengkap di [Setup & Instalasi](docs/setup.md).
