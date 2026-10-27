# Model & Database

## Diagram Relasi (Ringkasan)

```
User ──────────── Department
 │
 ├── CasualPosition (current position)
 ├── CasualPositionRegistration ── CasualPositionOpening ── CasualPosition
 │                                                        └── Branch
 ├── CasualClockRecord ── Branch
 │
 ├── Trip (sebagai driver) ── Vehicle
 │                         └── TripRoute ── TripRouteWaypoint
 │                              └── TripWaypointCheckin
 │                              └── TripFuelFillup
 │
 ├── ServiceRequest (sebagai technician/scheduled_by)
 │    └── ServiceRequestRepair
 │
 └── HelpdeskRequest (sebagai requester/assignee)
      └── HelpdeskFormTemplate ── HelpdeskFormField
```

---

## Model Detail

### User

**Tabel:** `users`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | Primary key |
| `name` | string | Nama lengkap |
| `username` | string, nullable | Username unik |
| `email` | string | Email unik |
| `password` | string | Bcrypt |
| `employee_id` | string, nullable | ID karyawan |
| `department_id` | bigint, nullable | FK → departments |
| `phone` | string, nullable | Nomor HP *(digunakan sebagai login identifier casual staff)* |
| `bank_name` | string, nullable | Nama bank |
| `bank_account_number` | string, nullable | Nomor rekening |
| `avatar` | string, nullable | Path foto profil |
| `is_active` | boolean | Default `true` |
| `casual_position_id` | bigint, nullable | FK → casual_positions (posisi aktif) |

**Relasi:**
- `BelongsTo`: Department, CasualPosition
- `HasOne`: CasualPositionRegistration
- `HasMany`: CasualClockRecord

**Traits:** HasRoles (Spatie), LogsActivity

---

### Department

**Tabel:** `departments`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `name` | string | Nama departemen |
| `code` | string | Kode singkat |

**Relasi:** `HasMany` User

---

### CasualPosition

**Tabel:** `casual_positions`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `name` | string | Nama posisi |
| `fee_per_day` | decimal | Honor per hari |
| `description` | text, nullable | |
| `is_active` | boolean | |

**Relasi:** `HasMany` User, `HasMany` CasualPositionOpening

---

### Branch

**Tabel:** `branches`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `name` | string | Nama branch/lokasi |
| `address` | string, nullable | Alamat lengkap |
| `lat` | decimal(10,7), nullable | Latitude koordinat |
| `lng` | decimal(10,7), nullable | Longitude koordinat |
| `radius_meters` | integer | Radius toleransi GPS (default: 100) |
| `location_required` | boolean | Wajib verifikasi GPS saat clock-in/out |
| `is_active` | boolean | |

**Relasi:** `HasMany` CasualClockRecord, `HasMany` CasualPositionOpening

---

### CasualPositionOpening

**Tabel:** `casual_position_openings`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `casual_position_id` | bigint | FK → casual_positions |
| `branch_id` | bigint, nullable | FK → branches (nullOnDelete) |
| `work_date` | date | Tanggal kerja |
| `total_slots` | integer | Jumlah slot tersedia |
| `description` | text, nullable | |
| `is_active` | boolean | |
| `posted_by` | bigint | FK → users |

**Relasi:**
- `BelongsTo`: CasualPosition, Branch, User (posted_by)
- `HasMany`: CasualPositionRegistration

**Scopes:** `available()` — hanya opening aktif dengan work_date ≥ hari ini

---

### CasualPositionRegistration

**Tabel:** `casual_position_registrations`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `casual_position_opening_id` | bigint | FK → casual_position_openings |
| `user_id` | bigint | FK → users |

**Constraint:** Unique per `(casual_position_opening_id, user_id)`

**Relasi:** `BelongsTo` CasualPositionOpening, User

---

### CasualClockRecord

**Tabel:** `casual_clock_records`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `user_id` | bigint | FK → users |
| `branch_id` | bigint, nullable | FK → branches (nullOnDelete) |
| `date` | date | Tanggal absensi |
| `clock_in_at` | datetime, nullable | Waktu masuk |
| `clock_in_photo` | string, nullable | Path foto selfie masuk |
| `clock_in_lat` | decimal, nullable | GPS masuk |
| `clock_in_lng` | decimal, nullable | GPS masuk |
| `clock_out_at` | datetime, nullable | Waktu keluar |
| `clock_out_photo` | string, nullable | Path foto selfie keluar |
| `clock_out_lat` | decimal, nullable | GPS keluar |
| `clock_out_lng` | decimal, nullable | GPS keluar |
| `notes` | text, nullable | |

**Constraint:** Unique per `(user_id, date)` — satu record absensi per staff per hari.

**Relasi:** `BelongsTo` User, Branch

---

---

### Trip

**Tabel:** `trips` (soft deletes)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `driver_id` | bigint | FK → users |
| `vehicle_id` | bigint | FK → vehicles |
| `trip_route_id` | bigint | FK → trip_routes |
| `trip_date` | date | Tanggal perjalanan |
| `status` | enum | `TripStatus`: pending, in_progress, completed |
| `started_at` | datetime, nullable | |
| `completed_at` | datetime, nullable | |
| `has_fuel_fillup` | boolean | |
| `meal_allowance_amount` | decimal | Uang makan |
| `notes` | text, nullable | |

**Relasi:**
- `BelongsTo`: User (driver), Vehicle, TripRoute
- `HasMany`: TripWaypointCheckin
- `HasOne`: TripFuelFillup

---

### TripRoute

**Tabel:** `trip_routes` (soft deletes)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `name` | string | |
| `description` | text, nullable | |
| `meal_allowance_amount` | decimal | Default uang makan |
| `requires_waypoint_attachment` | boolean | Wajib foto di waypoint |
| `is_active` | boolean | |

**Relasi:** `HasMany` TripRouteWaypoint, Trip

---

### TripRouteWaypoint

**Tabel:** `trip_route_waypoints`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `trip_route_id` | bigint | FK → trip_routes |
| `urutan` | integer | Nomor urut |
| `name` | string | Nama waypoint |
| `description` | text, nullable | |

**Relasi:** `BelongsTo` TripRoute; `HasMany` TripWaypointCheckin

---

### TripWaypointCheckin

**Tabel:** `trip_waypoint_checkins`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `trip_id` | bigint | FK → trips |
| `trip_route_waypoint_id` | bigint | FK → trip_route_waypoints |
| `checked_in_at` | datetime | |
| `attachment_path` | string, nullable | Foto bukti |
| `notes` | text, nullable | |

---

### TripFuelFillup

**Tabel:** `trip_fuel_fillups`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `trip_id` | bigint | FK → trips |
| `spbu_address` | string | Alamat SPBU |
| `fuel_type` | string | Jenis BBM |
| `liters` | decimal | Volume (liter) |
| `price_per_liter` | decimal | Harga/liter |
| `total_price` | decimal | Total biaya |
| `attachment_path` | string, nullable | Foto struk |

---

### DriverTripSettings

**Tabel:** `driver_trip_settings` (singleton)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `show_fuel_modal` | boolean | Tampilkan modal BBM setelah trip selesai |
| `require_fuel_attachment` | boolean | Wajibkan foto struk BBM |
| `report_cutoff_day` | integer | Tanggal cut-off laporan bulanan |

---

### Vehicle

**Tabel:** `vehicles` (soft deletes)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `license_plate` | string | Nomor polisi |
| `brand` | string | Merek |
| `model` | string | Model/tipe |
| `year` | integer | Tahun |
| `is_active` | boolean | |

---

### ServiceRequest

**Tabel:** `service_requests`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `department_id` | bigint | FK → departments |
| `technician_id` | bigint, nullable | FK → users |
| `scheduled_by` | bigint | FK → users (yang membuat request) |
| `scheduled_date` | date, nullable | Jadwal servis |
| `requestor_notes` | text, nullable | Catatan pemohon |
| `attachments` | json, nullable | Array path foto/dokumen |
| `status` | enum | `ServiceRequestStatus` |
| `warranty_expires_at` | datetime, nullable | Tanggal berakhir garansi |

**Relasi:**
- `BelongsTo`: Department, User (technician), User (scheduled_by)
- `HasMany`: ServiceRequestRepair
- `HasOne`: activeRepair (latest repair in progress)

---

### ServiceRequestRepair

**Tabel:** `service_request_repairs`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `service_request_id` | bigint | FK → service_requests |
| `technician_id` | bigint | FK → users |
| `cycle` | integer | Nomor siklus repair (1, 2, 3...) |
| `before_photo` | string, nullable | Foto kondisi sebelum |
| `before_notes` | text, nullable | |
| `after_photo` | string, nullable | Foto kondisi sesudah |
| `after_notes` | text, nullable | |
| `started_at` | datetime, nullable | |
| `completed_at` | datetime, nullable | |
| `warranty_expires_at` | datetime, nullable | Garansi untuk siklus ini |

---

### TechnicianSettings

**Tabel:** `technician_settings` (singleton)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `max_jobs_per_day` | integer | Maks pekerjaan per hari per teknisi |

---

### HelpdeskFormTemplate

**Tabel:** `helpdesk_form_templates`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `name` | string | Nama template |
| `description` | text, nullable | |
| `icon` | string, nullable | Nama ikon Lucide |
| `color` | string, nullable | Warna hex/tailwind |
| `is_active` | boolean | |
| `statuses` | json | Array status custom untuk template ini |
| `roles` | json | Array role yang bisa mengakses template ini |

**Relasi:** `HasMany` HelpdeskFormField, `HasMany` HelpdeskRequest

---

### HelpdeskFormField

**Tabel:** `helpdesk_form_fields`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `helpdesk_form_template_id` | bigint | FK → helpdesk_form_templates |
| `label` | string | Label field |
| `type` | enum | `FormFieldType` (text, select, dll) |
| `is_required` | boolean | |
| `hint` | string, nullable | Teks bantuan |
| `placeholder` | string, nullable | |
| `options` | json, nullable | Untuk field tipe select |
| `sort_order` | integer | Urutan tampil |

---

### HelpdeskRequest

**Tabel:** `helpdesk_requests` (soft deletes)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `helpdesk_form_template_id` | bigint | FK → helpdesk_form_templates |
| `department_id` | bigint, nullable | FK → departments |
| `requester_id` | bigint | FK → users |
| `assignee_id` | bigint, nullable | FK → users |
| `status` | string | Status dari template (bukan enum tetap) |
| `data` | json | Isian form per field |
| `notes` | text, nullable | Catatan tambahan |
| `resolved_at` | datetime, nullable | |

**Traits:** LogsActivity

---

### PurchasingRequest

**Tabel:** `purchasing_requests` (soft deletes)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `department_id` | bigint | FK → departments |
| `requester_id` | bigint | FK → users |
| `approved_by` | bigint, nullable | FK → users |
| `keperluan` | text | Kebutuhan/tujuan pembelian |
| `status` | enum | `RequestStatus` |
| `approved_at` | datetime, nullable | |
| `notes` | text, nullable | |

**Relasi:** `HasMany` PurchasingRequestItem

---

### DesignRequest

**Tabel:** `design_requests` (soft deletes)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `department_id` | bigint | FK → departments |
| `requester_id` | bigint | FK → users |
| `assignee_id` | bigint, nullable | FK → users |
| `judul_permintaan` | string | Judul project desain |
| `kategori_desain` | enum | `DesignCategory` |
| `ringkasan_brief` | text | Brief/ringkasan permintaan |
| `attachments` | json, nullable | File referensi |
| `status` | enum | `RequestStatus` |
| `resolved_at` | datetime, nullable | |

---

### ErpRepairRequest

**Tabel:** `erp_repair_requests` (soft deletes)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint | |
| `department_id` | bigint | FK → departments |
| `requester_id` | bigint | FK → users |
| `assignee_id` | bigint, nullable | FK → users |
| `jenis_modul_erp` | enum | `ErpModule` |
| `catatan_perbaikan` | text | Deskripsi masalah |
| `attachments` | json, nullable | |
| `status` | enum | `RequestStatus` |
| `priority` | string, nullable | |
| `resolved_at` | datetime, nullable | |
