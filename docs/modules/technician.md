# Modul Technician

Sistem manajemen permintaan servis perangkat/peralatan dengan alur multi-siklus repair dan tracking garansi.

## Alur Service Request

```
Staff (via panel Helpdesk) membuat ServiceRequest:
  → Pilih departemen, teknisi, tanggal, catatan, foto awal
  → Status: submitted
          │
          ▼
Teknisi (panel Technician) menerima assignment:
  → Mulai pengerjaan
  → Upload foto sebelum + catatan kondisi awal
  → Status: in_progress
          │
          ▼
Teknisi selesai mengerjakan:
  → Upload foto sesudah + catatan hasil
  → Tentukan garansi (warranty_expires_at)
  → Status: completed (atau warranty jika masih dalam garansi)
          │
          ▼ (jika ada masalah lagi saat garansi)
Buat siklus repair baru (cycle + 1):
  → ServiceRequestRepair baru dibuat
  → Status kembali: in_progress
          │
          ▼
Auto-complete garansi:
  → Scheduled command AutoCompleteWarrantyCommand
  → Setiap hari, cek warranty_expires_at yang sudah lewat
  → Status otomatis berubah menjadi completed
```

## Komponen Utama

### Service Request

Satu `ServiceRequest` mewakili satu perangkat/peralatan yang membutuhkan servis. Request bisa memiliki **beberapa siklus repair** — misalnya: perangkat diperbaiki, tapi rusak lagi dalam garansi → repair cycle baru dibuat.

**Status (`ServiceRequestStatus`):**

| Status | Keterangan |
|--------|-----------|
| `submitted` | Request masuk, belum ada teknisi |
| `in_progress` | Sedang dikerjakan teknisi |
| `warranty` | Selesai, masih dalam periode garansi |
| `completed` | Selesai tanpa/sudah lewat garansi |

### Service Request Repair (Siklus)

Setiap kali teknisi mengerjakan request, satu `ServiceRequestRepair` dibuat dengan nomor `cycle` yang bertambah. Ini memungkinkan tracking history lengkap dari setiap pengerjaan.

Field penting per siklus:
- `before_photo` / `after_photo` — dokumentasi kondisi
- `warranty_expires_at` — garansi khusus untuk siklus ini
- `started_at` / `completed_at` — durasi pengerjaan

### Active Repair

Model `ServiceRequest` memiliki relasi `activeRepair` (`HasOne` ke repair yang belum `completed_at`). Ini digunakan untuk menampilkan progress repair yang sedang berjalan.

### Pengaturan Teknisi (TechnicianSettings)

Singleton dikonfigurasi di panel Helpdesk → **Technician → Pengaturan Teknisi**:

| Setting | Keterangan |
|---------|-----------|
| `max_jobs_per_day` | Batas maksimal pekerjaan per teknisi per hari. Mencegah overloading teknisi. |

## Manajemen di Panel Helpdesk

**Technician → Permintaan Servis** (`ServiceRequestResource`)

Admin/HR dapat:
- Membuat service request baru
- Assign ke teknisi
- Monitor status semua request
- Lihat detail foto sebelum/sesudah per siklus

## Panel Technician (`/technician`)

Teknisi menggunakan panel ini untuk:
- Melihat daftar request yang ditugaskan kepadanya
- Update status pengerjaan
- Upload foto dokumentasi

Resource: `app/Filament/Technician/Resources/ServiceRequests/ServiceRequestResource.php`

Berbeda dengan resource di panel Helpdesk — resource di panel Technician hanya menampilkan request yang `technician_id = auth()->id()`.

## Scheduled Command

`AutoCompleteWarrantyCommand` berjalan harian via scheduler:

```php
// app/Console/Commands/AutoCompleteWarrantyCommand.php
// Mengubah status ServiceRequest dari 'warranty' ke 'completed'
// jika warranty_expires_at <= now()
```

Pastikan cron scheduler aktif di server production.

## Tabel Database Terlibat

```
service_requests
service_request_repairs
technician_settings
```
