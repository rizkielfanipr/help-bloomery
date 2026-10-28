# Operations

Prosedur pipeline dan server. Arsitektur aplikasi ada di [architecture](../architecture/README.md); pemantauan dari dalam aplikasi ada di modul [System Monitoring](../products/system-monitoring/README.md).

## CI/CD

| Dokumen | Status tertulis | Catatan |
|---|---|---|
| [Release Pipeline](ci-cd/release-pipeline.md) | Phase 1 sampai Phase 5 selesai | Pipeline atomik menjadi jalur resmi; Release #20 sehat, legacy workflow/command dinonaktifkan, dan histori `staging` tersimpan pada branch arsip. |
| [Current State Audit](ci-cd/reports/current-state-audit.md) | Audit baseline 26 Oktober 2026 | Catatan historis; dirujuk Release Pipeline sebagai bukti audit. |

## Production

| Dokumen | Status tertulis | Catatan |
|---|---|---|
| [Redis untuk Production](production/redis.md) | — | Konfigurasi Redis dan verifikasi dengan `php artisan app:check-redis`. |
| [PRD Stabilitas, Performa, dan Skalabilitas](production/stability-scalability-prd.md) | Rancangan untuk review | Infrastruktur, DNS, SSL, hosting, backup, dan migrasi server. Dirujuk oleh Code Remediation. |

Prosedur deployment dan rollback saat ini berada di Release Pipeline. Folder `deployment/` dan `backup-restore/` dibuat saat ada dokumen terpisah.
