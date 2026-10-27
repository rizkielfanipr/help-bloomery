# Architecture

Gambaran teknis aplikasi. Prosedur server dan pipeline berada di [operations](../operations/README.md).

| Dokumen | Isi | Indeks |
|---|---|---|
| [Overview](overview.md) | Arsitektur multi-panel, struktur folder, alur request, pola desain, enum | needs-review — belum diverifikasi terhadap kode terbaru |
| [Data Model](data-model.md) | Model, relasi, dan struktur tabel | needs-review — belum diverifikasi terhadap migration terbaru |
| [Panels](panels.md) | Panel Filament, role akses, routing | needs-review |
| [RBAC](rbac.md) | Role, permission, aturan akses | needs-review — sumber permission aktual ada di `config/permissions.php` |
| [Integrations](integrations/README.md) | Integrasi sistem eksternal | — |

Dokumen modul lama di `docs/modules/` (Casual, Driver, Helpdesk, Technician) belum dipindahkan; lihat [README utama](../README.md#dokumen-yang-belum-diklasifikasikan).

Path lama: `docs/architecture.md` → `overview.md`, `docs/models.md` → `data-model.md`, `docs/panels.md`, `docs/rbac.md`.
