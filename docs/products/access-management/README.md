# Access Management

Pengelolaan pengguna, role, permission, dan akses cabang. Konsep RBAC lengkap ada di [RBAC](../../architecture/rbac.md). Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu | Permission |
|---|---|---|
| Back Office → Management Access | Pengguna | `view users` (+ create/edit/delete) |
| | Role & Permission | `view roles` (+ create/edit/delete) |
| Panel Admin (`/admin`) | User dan Role (resource yang sama) | `access backoffice` + permission Management Access |

## Pengguna

- Data: foto, nama, username (dipakai untuk login), email, password, nomor telepon, role, dan status aktif.
- **Akses cabang**: *Akses Semua Cabang*, atau daftar *Akses Cabang* dengan satu *Cabang Utama/Default*. Akses ini membatasi data pada semua modul berbasis branch.
- User nonaktif tidak dapat masuk ke panel mana pun.

## Role & Permission

- Role berisi nama dan **matrix permission** yang dikelompokkan per domain dan fitur (`PermissionsMatrix`, sumber dari `PermissionRegistry`).
- Daftar role menampilkan jumlah permission dan jumlah pengguna.
- Permission baru dibuat otomatis saat deploy (`php artisan permissions:sync`), tetapi tidak otomatis diberikan ke role selain `SUPERADMIN`. Berikan melalui menu ini.

## Data

`User`, tabel Spatie (`roles`, `permissions`, pivot), dan pivot akses cabang. Lihat [Data Model](../../architecture/data-model.md#platform--master-data).
