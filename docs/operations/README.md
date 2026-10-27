# Operations

Prosedur pipeline, server, dan observability. Arsitektur aplikasi berada di [architecture](../architecture/README.md).

## CI/CD

| Dokumen | Status tertulis | Catatan |
|---|---|---|
| [Release Pipeline](ci-cd/release-pipeline.md) | Phase 1 sampai Phase 4 selesai; Phase 5 dalam masa observasi | Dokumen canonical CI/CD dan deployment atomik. Dirujuk oleh `ops/deploy/README.md`. |
| [Current State Audit](ci-cd/reports/current-state-audit.md) | Phase 1 dan Phase 2 selesai; atomic staging aktif, production masih memakai deployment lama | Audit baseline 26 Oktober 2026; catatan historis. |

## Production

| Dokumen | Status tertulis | Catatan |
|---|---|---|
| [Redis untuk Production](production/redis.md) | — | needs-review: tidak memiliki status tertulis. |
| [PRD Stabilitas, Performa, dan Skalabilitas](production/stability-scalability-prd.md) | Rancangan untuk review; belum mengotorisasi implementasi atau deployment | Infrastruktur, DNS, SSL, hosting, backup, dan migrasi server. Dirujuk oleh Code Remediation. |

## Monitoring

| Dokumen | Status tertulis | Catatan |
|---|---|---|
| [PRD System Monitoring](monitoring/prd.md) | Rancangan untuk review; belum mengotorisasi implementasi, migration production, atau deployment | needs-review: menu System Monitoring sudah ada di aplikasi, sehingga status tertulis perlu dikonfirmasi. |
| [Prompt Implementasi System Monitoring](monitoring/implementation-prompt.md) | — | Prompt implementasi. |

## Belum tersedia

- `deployment/` dan `backup-restore/`: belum ada dokumen terpisah; prosedur deployment saat ini berada di Release Pipeline.

Path lama: `docs/ci-cd-release-pipeline.md`, `docs/ci-cd-current-state-audit.md`, `docs/production-redis.md`, `docs/application-stability-scalability-prd.md`, `docs/system-monitoring-prd.md`, `docs/system-monitoring-implementation-prompt.md`.
