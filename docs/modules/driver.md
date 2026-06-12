# Modul Driver & Trip

Modul untuk manajemen perjalanan dinas: pembuatan trip, check-in per waypoint, pencatatan BBM, dan laporan perjalanan dalam bentuk PDF.

## Alur Trip

```
Admin/HR setup:
  Vehicle (kendaraan)
  TripRoute (rute) → TripRouteWaypoint (titik pemberhentian)
  DriverTripSettings (pengaturan global)
          │
          ▼
Driver buka /driver
  → TripDashboard: lihat trip hari ini / status aktif
          │
          ▼
StartTrip:
  → Pilih tanggal, rute, kendaraan
  → Trip dibuat (status: pending)
          │
          ▼
ActiveTrip:
  → Mulai trip (status: in_progress, started_at = now)
  → Check-in waypoint 1, 2, 3... (TripWaypointCheckin + foto opsional)
  → Isi BBM jika perlu (TripFuelFillup)
  → Selesaikan trip (status: completed, completed_at = now)
          │
          ▼
TripHistory:
  → Lihat riwayat trip selesai
  → Download PDF laporan
```

## Komponen Utama

### Kendaraan (Vehicle)

Daftar armada yang bisa digunakan untuk trip. Dikelola di panel Helpdesk → **Driver → Kendaraan**.

Field penting: `license_plate`, `brand`, `model`, `year`, `is_active`.

### Rute Perjalanan (TripRoute)

Template rute dengan daftar waypoint yang harus dikunjungi driver. Satu rute bisa digunakan berkali-kali untuk trip berbeda.

- `requires_waypoint_attachment` — jika `true`, driver wajib upload foto di setiap waypoint
- `meal_allowance_amount` — default uang makan untuk rute ini (bisa di-override per trip)

### Waypoint (TripRouteWaypoint)

Titik pemberhentian dalam urutan (`urutan`). Driver harus check-in secara berurutan.

### Trip

Record perjalanan aktual. Setiap trip terikat ke satu driver, satu kendaraan, dan satu rute.

**Status Trip (`TripStatus`):**
| Status | Keterangan |
|--------|-----------|
| `pending` | Trip dibuat, belum dimulai |
| `in_progress` | Sedang berjalan |
| `completed` | Selesai |

### Waypoint Check-in (TripWaypointCheckin)

Saat driver tiba di waypoint, dia melakukan check-in. Jika rute memerlukan attachment, driver harus upload foto sebagai bukti.

### Pengisian BBM (TripFuelFillup)

Opsional per trip. Mencatat: SPBU, jenis BBM, volume, harga, total, dan foto struk.

### Pengaturan Trip (DriverTripSettings)

Singleton — hanya ada satu baris. Dikonfigurasi di panel Helpdesk → **Driver → Pengaturan Trip**:

| Setting | Keterangan |
|---------|-----------|
| `show_fuel_modal` | Tampilkan dialog BBM otomatis setelah trip selesai |
| `require_fuel_attachment` | Foto struk wajib (jika `show_fuel_modal = true`) |
| `report_cutoff_day` | Tanggal cut-off laporan bulanan (misal: 25 → laporan bulan ini = tgl 25 bln lalu s/d tgl 25 bln ini) |

## Laporan PDF

Route: `GET /trip-report/pdf` (middleware: auth + role)

Controller: `App\Http\Controllers\TripReportController@pdf`

Menghasilkan PDF berisi daftar trip dalam periode tertentu, lengkap dengan detail waypoint dan BBM.

## Manajemen di Panel Helpdesk

Admin/HR mengelola via panel Helpdesk (**Driver** menu):

| Menu | Resource | Fungsi |
|------|---------|--------|
| Perjalanan | `TripResource` | Monitor semua trip (list & detail) |
| Rute Perjalanan | `TripRouteResource` | CRUD rute & waypoint |
| Kendaraan | `VehicleResource` | CRUD armada |
| Pengaturan Trip | `DriverTripSettingsPage` | Konfigurasi global trip |

## Panel Driver (`/driver`)

Driver menggunakan panel ini dari browser/mobile:

| Halaman | Fungsi |
|---------|--------|
| `TripDashboard` | Dashboard: trip hari ini, tombol mulai/lanjut |
| `StartTrip` | Form buat trip baru |
| `ActiveTrip` | Kelola trip aktif: check-in waypoint, isi BBM, selesaikan |
| `TripHistory` | Riwayat trip + download PDF |

## Tabel Database Terlibat

```
trips
trip_routes
trip_route_waypoints
trip_waypoint_checkins
trip_fuel_fillups
driver_trip_settings
vehicles
```
