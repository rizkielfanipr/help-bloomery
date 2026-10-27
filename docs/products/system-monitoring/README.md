# System Monitoring

Pemantauan kesehatan aplikasi dari dalam aplikasi: health check layanan, transaksi ESB, queue dan scheduler, error, deployment, alert, dan incident. Kondisi per 27 Oktober 2026.

## Lokasi

| Menu (Back Office → System Monitoring) | Permission |
|---|---|
| Overview | `view system monitoring`; acknowledge alert: `acknowledge monitoring alerts` |
| Service Health | `view system monitoring` |
| ESB Transactions | `view monitoring technical details`; ringkasan payload: `view monitoring sanitized payloads`; rekonsiliasi: `reconcile esb transactions` |
| Queue & Scheduler | `view monitoring technical details` |
| Errors | `view monitoring technical details` |
| Deployments | `view monitoring technical details` |
| Incidents | `view system monitoring`; mengubah: `manage monitoring incidents` |
| Settings | `manage monitoring settings` |

## Fitur

| Fitur | Perilaku |
|---|---|
| **Overview** | Membaca snapshot lokal yang di-cache (tidak memanggil ESB saat render): status layanan, alert aktif, tren 7 hari, dan notifikasi. |
| **Service Health** | Hasil health check yang tersimpan: aplikasi (`/up`), database, cache, disk, object storage (R2), heartbeat queue dan scheduler, probe baca ESB, dan sertifikat SSL (nonaktif secara default). Uptime 24 jam dan 7 hari. |
| **ESB Transactions** | Setiap mutasi ke ESB dicatat sebagai transaksi beserta attempt-nya. Status: `pending`, `processing`, `succeeded`, `failed`, `unknown`, `partial`, `reconciled`, `cancelled`. Hasil tidak pasti (`unknown`) direkonsiliasi manual dan tidak pernah di-retry otomatis. Payload hanya disimpan sebagai ringkasan yang sudah disanitasi. |
| **Queue & Scheduler** | Heartbeat worker dan scheduler, metadata queue, riwayat scheduled task, failed job. Read-only: tidak ada restart atau retry dari UI. |
| **Errors** | Error backend di-fingerprint dan digabung per jenis. Triage: `new`, `acknowledged`, `investigating`, `resolved`, `ignored` (wajib alasan). Stack frame hanya untuk akses teknis. |
| **Deployments** | Metadata deployment dari callback pipeline bertanda tangan atau `monitoring:record-deployment`. Read-only. |
| **Incidents** | Incident dengan severity `sev1`–`sev4` dan status `open → acknowledged → investigating → monitoring → resolved` (dapat dibuka ulang). Timeline append-only, tautan ke alert/error/transaksi/deployment, analisis root cause, dan tautan ke Permintaan ERP. |
| **Settings** | Kanal alert (in-app, email), pengingat alert, incident otomatis SEV-1/SEV-2, ambang keterlambatan scheduler dan queue, maintenance window, serta interval, timeout, dan ambang gagal/pulih per layanan. Secret tidak pernah ditampilkan. |

Alert dideduplikasi per sumber. Notifikasi hanya dikirim saat status berubah, dan maintenance window menahan alert baru tanpa menghentikan pencatatan.

## Command

| Command | Jadwal / fungsi |
|---|---|
| `monitoring:run-checks` | Tiap menit — heartbeat dan health check yang jatuh tempo |
| `monitoring:aggregate` | 00:20 — agregat harian untuk tren Overview |
| `monitoring:prune` | 03:15 — retensi; incident tidak pernah dihapus |
| `monitoring:record-deployment` | Mencatat metadata deployment |

## Data

Tabel `monitoring_*` (service, check result, heartbeat, transaction, error, alert, incident beserta event dan link, deployment, scheduled task run, daily aggregate, setting). Lihat [Data Model](../../architecture/data-model.md#system-monitoring).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [PRD System Monitoring](prd.md) | Implemented |

## Role bawaan

`IT_STAFF` memiliki seluruh akses monitoring kecuali `manage monitoring settings`.
