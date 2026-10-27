# Operations

Prosedur pipeline dan server. Arsitektur aplikasi ada di [architecture](../architecture/README.md); pemantauan dari dalam aplikasi ada di modul [System Monitoring](../products/system-monitoring/README.md).

## CI/CD

| Dokumen | Status tertulis | Catatan |
|---|---|---|
| [Release Pipeline](ci-cd/release-pipeline.md) | Phase 1 sampai Phase 4 selesai; Phase 5 dalam masa observasi | Dokumen canonical CI, release atomik staging/production, rollback. Dirujuk oleh `ops/deploy/README.md`. |
| [Current State Audit](ci-cd/reports/current-state-audit.md) | Audit baseline 26 Oktober 2026 | Catatan historis; dirujuk Release Pipeline sebagai bukti audit. |

## Production

| Dokumen | Status tertulis | Catatan |
|---|---|---|
| [Redis untuk Production](production/redis.md) | — | Konfigurasi Redis dan verifikasi dengan `php artisan app:check-redis`. |
| [PRD Stabilitas, Performa, dan Skalabilitas](production/stability-scalability-prd.md) | Rancangan untuk review | Infrastruktur, DNS, SSL, hosting, backup, dan migrasi server. Dirujuk oleh Code Remediation. |

Prosedur deployment dan rollback saat ini berada di Release Pipeline. Folder `deployment/` dan `backup-restore/` dibuat saat ada dokumen terpisah.
