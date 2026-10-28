# Arsitektur Sistem

Help Bloomery adalah satu aplikasi Laravel 13 (PHP 8.4) dengan beberapa panel Filament 5 yang berbagi satu database MySQL, satu codebase, dan satu sistem RBAC. Dokumen ini menggambarkan kondisi kode per 27 Oktober 2026.

## Gambaran umum

```
┌──────────────────────────────── Laravel Application ────────────────────────────────┐
│                                                                                      │
│  Back Office (helpdesk, root /)     Employee App (casual, /casual atau APP_DOMAIN)    │
│  Driver (/driver)   Technician (/technician)   Admin (/admin)                         │
│                                                                                      │
│  Filament Page/Resource ─► Action (use case, transaction) ─► Model / Service          │
│                                        │                                             │
│                                        ├─► ESB Core / ESB corev1 (integrasi ERP)     │
│                                        ├─► Cloudflare R2 (disk `b2`)                 │
│                                        └─► Queue job ─► Telegram / notifikasi        │
│                                                                                      │
│  Scheduler (routes/console.php) · Queue worker · System Monitoring                   │
└──────────────────────────────────────────────────────────────────────────────────────┘
```

Detail panel dan menu ada di [Panels](panels.md); role dan permission di [RBAC](rbac.md); tabel dan relasi di [Data Model](data-model.md); integrasi ESB di [ESB Integration](integrations/esb.md).

## Tech stack

| Layer | Teknologi |
|---|---|
| Framework | Laravel 13, PHP 8.4 |
| Admin/UI | Filament 5, Livewire 4, Alpine.js 3, Tailwind CSS 4 |
| Database | MySQL |
| Queue, cache, session | Driver `database` secara default (`.env.example`); Redis didukung dan diverifikasi dengan `php artisan app:check-redis` — lihat [Redis untuk Production](../operations/production/redis.md) |
| File | Disk `b2` (Cloudflare R2, kredensial `R2_*`) untuk lampiran dan foto; disk privat untuk bukti sensitif |
| RBAC | Spatie Laravel Permission |
| Audit | Spatie Activity Log dan tabel audit per domain |
| PDF / Excel | DomPDF; export XLSX streaming |
| Test | Pest 4 (Unit + Feature), CI di GitHub Actions |

## Struktur folder

```
app/
├── Actions/          Use case per domain (Briefing, CustomerComplaint, Kitchen, Monitoring,
│                     Ordering, Rnd/{Bom,InternalMemo,Material,ProjectTask,ShelfLife},
│                     SalesReport, StoreSalesOrder, …). Satu Action = satu transaksi bisnis.
├── Console/Commands/ Command terjadwal, audit read-only, dan utilitas ESB one-off
├── Enums/            Status dan kategori (lifecycle tiap modul)
├── Filament/
│   ├── Helpdesk/     Back office: Pages, Resources, Widgets, Concerns, Navigation
│   ├── Casual/       Employee App (tile launcher dan halaman mobile)
│   ├── Driver/       Panel Driver
│   ├── Technician/   Panel Technician
│   ├── Resources/    User & Role (dipakai panel Admin dan Back Office)
│   └── Exports/      Exporter Filament
├── Http/Controllers/ PDF, export, lampiran privat, scan QR asset, ingestion monitoring
├── Jobs/             Sinkronisasi ESB, forecast, reminder, notifikasi
├── Livewire/         Komponen Livewire mandiri (kartu supplier, kalender task)
├── Models/           162 model Eloquent
├── Notifications/    Notifikasi database/in-app
├── Policies/         Otorisasi per model (berbasis permission, bukan nama role)
├── Providers/        AppServiceProvider, panel provider, MonitoringServiceProvider
└── Services/         Klien ESB, kalkulator skor, read model, monitoring, Telegram
config/permissions.php   Sumber daftar permission per domain
routes/console.php       Jadwal scheduler
resources/views/vendor/filament-panels/components/layout/index.blade.php
                         Sidebar Back Office (struktur menu dan permission tiap item)
```

## Pola desain

1. **Action sebagai satu-satunya jalur mutasi.** Halaman Filament hanya memegang state UI; perubahan status dan penyimpanan dilakukan Action yang mengotorisasi ulang di server, memakai transaction, dan row lock bila ada konkurensi. Visibilitas tombol bukan kontrol keamanan.
2. **Policy berbasis permission dan branch scope.** Policy memeriksa permission dan akses branch (`canAccessBranch()` / `canAccessAllBranches()`); nama role tidak di-hardcode. `SUPERADMIN` melewati semua gate.
3. **Integrasi ESB defensif.** Mutasi ke ESB dicatat oleh `EsbTransactionRecorder`. Hasil yang tidak pasti (timeout, 5xx setelah request terkirim) menjadi `unknown`/`needs_reconciliation` dan tidak di-retry otomatis; rekonsiliasi dilakukan manual dengan alasan.
4. **Snapshot lokal untuk data ESB.** Katalog BOM, produk WIP, menu Memo Internal, transaksi sales per shift, dan data Ordering disimpan sebagai snapshot lokal. Halaman membaca snapshot, bukan memanggil ESB saat render.
5. **Status turunan.** Beberapa status tidak pernah dipilih manual, tetapi dihitung (misalnya `missing` Daily Briefing, `Completed` Master Bahan).
6. **Lampiran privat.** File sensitif dibuka melalui controller yang mengotorisasi lalu memberi URL sementara.
7. **Notifikasi setelah commit.** Notifikasi dan job eksternal dikirim setelah transaksi commit; rollback tidak mengirim apa pun.

## Panel

| Panel | ID | Path | Fungsi |
|---|---|---|---|
| Back Office | `helpdesk` | `/` | Operasional kantor pusat: semua modul back office dengan sidebar custom |
| Employee App | `casual` | `/casual`, atau root `APP_DOMAIN` bila diisi | Aplikasi mobile staff: tile per fitur sesuai permission |
| Driver | `driver` | `/driver` atau `DRIVER_DOMAIN` | Panel Driver mandiri (fitur yang sama juga tersedia di Employee App) |
| Technician | `technician` | `/technician` atau `TECHNICIAN_DOMAIN` | Panel Technician mandiri (fitur yang sama juga tersedia di Employee App) |
| Admin | `admin` | `/admin` | Panel default Filament untuk User & Role |

## Alur request

```
Browser ─► routes/web.php (export, PDF, lampiran, scan QR asset)
        └► Filament panel router (/, /casual, /driver, /technician, /admin)
              └► Page / Resource (Livewire)
                    └► Policy ─► Action ─► Model / Service ─► MySQL · ESB · R2
```

## Otomatisasi (scheduler)

| Command | Jadwal | Modul |
|---|---|---|
| `service-requests:complete-warranty` | Harian | Technician — tutup garansi 30 hari |
| `erp-requests:complete-warranty` | Per jam | Information Technology — tutup garansi 3 hari kerja |
| `briefing:auto-reject` | Tiap menit | Daily Briefing — reject submission melewati SLA review |
| `briefing:compute-scores` | Tanggal 1, 02:00 | Daily Briefing — nilai bulanan |
| `rnd:sync-esb-materials` | 02:30 | R&D — tarik perubahan Master Product ESB |
| `product-price-index:sync --days=90` | Senin, 01:00 | R&D — snapshot harga PO mingguan |
| `sales-reports:auto-reject` | Tiap menit | Finance — reject Sales Report melewati batas review |
| `basket-size:finalize` | Tiap 10 menit | Finance — hitung basket size per shift |
| `rnd:send-project-task-reminders` | 07:00 | R&D — reminder deadline task |
| `monitoring:run-checks` | Tiap menit | System Monitoring — heartbeat dan health check |
| `monitoring:aggregate` | 00:20 | System Monitoring — agregat harian |
| `monitoring:prune` | 03:15 | System Monitoring — retensi |
| `ordering:sync` | `ordering_recommendation.sync_time` | Analytics — snapshot dan forecast |

Command one-off dan audit read-only dijelaskan di dokumen modul masing-masing.

## Integrasi eksternal

| Sistem | Dipakai untuk | Konfigurasi |
|---|---|---|
| ESB Core & ESB corev1 | Master Product, BOM, PO/Goods Receipt, Item Journal, sales, stock, promosi | `config/esb.php`, token per comcode — lihat [ESB Integration](integrations/esb.md) |
| Cloudflare R2 | Penyimpanan file | `R2_*`, disk `b2` |
| Telegram | Notifikasi review Master Bahan | `config/services.php` `telegram`, nonaktif secara default |
| WhatsApp | Tautan CTA (bukan API) | Menu Master → WhatsApp |
| GitHub Actions | Deployment metadata ke System Monitoring | `monitoring:record-deployment`, callback bertanda tangan |

## Deployment

Release atomik staging/production melalui GitHub Actions. Lihat [Release Pipeline](../operations/ci-cd/release-pipeline.md).
