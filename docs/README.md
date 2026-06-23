# Help Bloomery — Documentation

Sistem internal Bloomery Patisserie untuk manajemen SDM, operasional, dan helpdesk.

## Daftar Isi

| Dokumen | Deskripsi |
|---------|-----------|
| [Setup & Instalasi](setup.md) | Persyaratan, instalasi, variabel environment |
| [Arsitektur Sistem](architecture.md) | Tech stack, struktur folder, alur request |
| [Panel Filament](panels.md) | Lima panel admin, role akses, routing |
| [Model & Database](models.md) | Semua model, relasi, struktur tabel |
| [RBAC — Role & Permission](rbac.md) | Daftar role, permission, dan aturan akses |
| [Modul: Casual Staff](modules/casual.md) | Registrasi, absensi, shift, posisi casual |
| [Modul: Driver & Trip](modules/driver.md) | Manajemen perjalanan, waypoint, BBM |
| [Modul: Helpdesk](modules/helpdesk.md) | Tiket, form template dinamis, request |
| [Modul: Technician](modules/technician.md) | Permintaan servis, siklus repair, garansi |

## Ringkasan Aplikasi

**Help Bloomery** adalah platform intranet multi-panel yang mengelola:

- **Casual Staff** — Registrasi, clock-in/out dengan GPS & selfie, export absensi ke Excel
- **Driver** — Manajemen perjalanan, check-in waypoint, laporan BBM
- **Helpdesk** — Tiket request berbasis form template dinamis
- **Technician** — Service request perangkat dengan alur multi-siklus
- **Management** — User, role, departemen, dan pengaturan sistem

## Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Framework | Laravel 13, PHP 8.4 |
| Admin Panel | Filament 5 |
| Real-time UI | Livewire 4 |
| Frontend | Tailwind CSS 4, Alpine.js 3 |
| Database | MySQL |
| RBAC | Spatie Laravel Permission 7 |
| Activity Log | Spatie Activity Log 5 |
| PDF | DomPDF 3 |
| Testing | Pest 4 |
