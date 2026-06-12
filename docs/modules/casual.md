# Modul Casual Staff

Modul untuk manajemen pegawai casual (harian/kontrak pendek): registrasi, penugasan posisi & shift, absensi berbasis GPS dan selfie.

## Alur Sistem

```
HR membuat:
  CasualPosition (posisi)
  CasualShift (shift + GPS config)
  CasualPositionOpening (lowongan: posisi + shift + tanggal + slot)
  CasualRegistrationToken (token untuk daftar)
          │
          ▼
Casual staff buka /casual/register
  → Input token
  → Buat akun (role: casual_staff)
          │
          ▼
SelectPosition page
  → Lihat daftar lowongan tersedia
  → Pilih & daftar (CasualPositionRegistration)
  → User.casual_position_id & casual_shift_id diupdate
          │
          ▼
ShiftDetailPage — tampilkan info shift hari ini
          │
          ▼ (saat jam kerja mulai)
ClockPage
  → Clock-in: selfie + GPS validation
  → Clock-out: selfie + GPS validation
  → CasualClockRecord dibuat/diupdate
```

## Komponen Utama

### Registrasi

**Token Registrasi** (`CasualRegistrationToken`) adalah kode sekali pakai yang dibuat HR untuk mengontrol siapa yang bisa mendaftar. Token dapat memiliki tanggal kadaluarsa.

- HR membuat token di panel Helpdesk → **Human Resources → Token Registrasi**
- Token diberikan ke calon casual staff (WhatsApp, email, dll)
- Staff buka `/casual/register`, input token → buat akun

### Pemilihan Posisi

Halaman `SelectPosition` menampilkan semua `CasualPositionOpening` yang:
- `is_active = true`
- `work_date >= today()`
- Jumlah registrasi < `total_slots`

Registrasi dilakukan dalam database transaction dengan `lockForUpdate()` untuk mencegah race condition slot penuh.

### Absensi (Clock Page)

Halaman `ClockPage` menangani:

1. **Clock-in:**
   - Ambil selfie via kamera (`selfie-camera.blade.php` component)
   - Validasi GPS (jika `location_required = true` di shift)
   - Hitung keterlambatan vs `start_time` + `tolerance_late_minutes`

2. **Clock-out:**
   - Selfie lagi
   - Validasi GPS
   - Hitung pulang cepat vs `end_time` - `tolerance_early_out_minutes`

Data disimpan ke `CasualClockRecord`.

### Halaman Lainnya

| Halaman | Fungsi |
|---------|--------|
| `AttendancePage` | Riwayat absensi bulanan |
| `ReportPage` | Total hari kerja & estimasi pendapatan |
| `NotificationPage` | Notifikasi dari sistem |
| `ProfilePage` | Edit nama, telepon, ganti password |

## Manajemen di Panel Helpdesk

HR/admin mengelola semua entitas casual via panel Helpdesk (**Human Resources** menu):

| Menu | Resource | Fungsi |
|------|---------|--------|
| Posisi Casual | `CasualPositionResource` | CRUD posisi & honor |
| Lowongan Posisi | `CasualPositionOpeningResource` | Buka lowongan per tanggal |
| Token Registrasi | `CasualRegistrationTokenResource` | Generate & monitor token |
| Jadwal Shift | `CasualShiftResource` | Konfigurasi shift & GPS |
| Absensi Casual | `CasualClockRecordResource` | Lihat & monitor absensi semua staff |

### CasualPositionOpening — Relation Manager

Halaman detail `CasualPositionOpening` memiliki tab **Registrasi** yang menampilkan semua user yang sudah mendaftar ke lowongan tersebut.

## Konfigurasi GPS Shift

Di `CasualShift`, aktifkan `location_required` dan isi:
- `location_lat` & `location_lng` — koordinat pusat (kantor/lokasi kerja)
- `location_radius_meters` — radius toleransi (misal: 100 meter)

Saat clock-in/out, koordinat GPS user dibandingkan dengan titik pusat. Jika di luar radius, clock-in ditolak atau diberi warning.

## Tabel Database Terlibat

```
casual_positions
casual_shifts
casual_position_openings
casual_position_registrations
casual_clock_records
casual_registration_tokens
users (casual_position_id, casual_shift_id)
```
