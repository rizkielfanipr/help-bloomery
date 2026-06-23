# Modul Casual Staff

Modul untuk manajemen pegawai casual (harian/kontrak pendek): registrasi, penugasan posisi, absensi berbasis GPS dan selfie.

> **Catatan:** Sistem shift (`CasualShift`) telah dihapus. Jam kerja casual bersifat fleksibel. Konfigurasi lokasi absensi (GPS) kini dikelola melalui model **Branch**.

## Alur Sistem

```
HR membuat:
  CasualPosition (posisi + fee per hari)
  Branch (lokasi absensi + GPS config)
  CasualPositionOpening (lowongan: posisi + branch + tanggal + slot)
          │
          ▼
Casual staff buka /casual/register
  → Input nama, nomor HP, nama bank, nomor rekening, password
  → Akun dibuat otomatis (role: casual_staff)
  → Email internal di-generate: {nomor_hp}@casual.app
          │
          ▼
SelectPosition page
  → Lihat daftar lowongan tersedia
  → Pilih & daftar (CasualPositionRegistration)
  → User.casual_position_id diupdate
          │
          ▼
ClockPage (pada hari kerja)
  → Clock-in: verifikasi GPS (jika wajib) + selfie
  → Clock-out: verifikasi GPS (jika wajib) + selfie
  → CasualClockRecord dibuat/diupdate
```

## Komponen Utama

### Registrasi

Pendaftaran dilakukan langsung di `/casual/register` tanpa token registrasi. Staff mengisi:
- Nama lengkap
- Nomor HP *(digunakan sebagai username login)*
- Nama Bank & Nomor Rekening
- Password

Email internal di-generate otomatis (`{phone}@casual.app`) untuk keperluan autentikasi, tidak ditampilkan ke user.

### Login

Staff login menggunakan **Nomor HP** sebagai identifier (bukan email). Sistem melakukan lookup `email` dari kolom `phone` di tabel `users` sebelum proses autentikasi.

### Pemilihan Posisi

Halaman `SelectPosition` menampilkan semua `CasualPositionOpening` yang:
- `is_active = true`
- `work_date >= today()`
- Jumlah registrasi < `total_slots`

Registrasi dilakukan dalam database transaction dengan `lockForUpdate()` untuk mencegah race condition slot penuh. Staff bisa membatalkan registrasi selama `work_date` lebih dari 24 jam dari sekarang.

### Absensi (Clock Page)

Halaman `ClockPage` menangani dua langkah:

**Step 1 — Verifikasi Lokasi** *(jika `branch.location_required = true`)*
- Peta interaktif menampilkan posisi user dan radius lokasi kerja
- GPS diambil dari browser, dibandingkan dengan koordinat branch
- User harus berada dalam radius untuk bisa lanjut

**Step 2 — Deteksi Wajah / Selfie**
- Kamera aktif untuk ambil foto selfie
- Foto disimpan ke storage (`clock_in_photo` / `clock_out_photo`)

Data disimpan ke `CasualClockRecord`. Tidak ada perhitungan keterlambatan atau pulang cepat.

### Halaman Lainnya

| Halaman | Fungsi |
|---------|--------|
| `AttendancePage` | Riwayat absensi bulanan |
| `ReportPage` | Total hari kerja & estimasi pendapatan |
| `NotificationPage` | Notifikasi dari sistem |
| `ProfilePage` | Edit nama, nomor HP, ganti password |

## Manajemen di Panel Helpdesk

HR/admin mengelola semua entitas casual via panel Helpdesk:

| Grup | Menu | Resource | Fungsi |
|------|------|---------|--------|
| Human Resources | Casual Staff | `CasualStaffResource` | Index & edit data staff casual |
| Human Resources | Posisi Casual | `CasualPositionResource` | CRUD posisi & honor per hari |
| Human Resources | Lowongan Posisi | `CasualPositionOpeningResource` | Buka lowongan per tanggal & branch |
| Human Resources | Absensi Casual | `CasualClockRecordResource` | Monitor & export absensi semua staff |
| Master | Branch | `BranchResource` | CRUD lokasi absensi + GPS config |

### Export Excel Absensi

Di halaman **Absensi Casual**, tombol export (ikon download hijau) menghasilkan file `.xlsx` berisi:

| Kolom | Sumber |
|-------|--------|
| Nama | `user.name` |
| Branch | `branch.name` |
| Posisi | `user.casualPosition.name` |
| Tanggal | `date` |
| Clock In | `clock_in_at` |
| Clock Out | `clock_out_at` |
| Fee (Rp) | `user.casualPosition.fee_per_day` |
| Fee Lembur (Rp) | *(belum diimplementasikan)* |
| Nomor Rekening | `user.bank_account_number` |
| Nama Bank | `user.bank_name` |
| Nomor HP | `user.phone` |

Filter Branch dan Staff yang aktif di tabel akan ikut terbawa ke hasil export.

**Route:** `GET /helpdesk/exports/casual-clock-records?branch_id=&user_id=`
**Controller:** `App\Http\Controllers\Helpdesk\CasualClockRecordExportController`

## Konfigurasi GPS Lokasi (Branch)

Di **Master → Branch**, aktifkan `location_required` dan isi:
- `lat` & `lng` — koordinat pusat lokasi kerja
- `radius_meters` — radius toleransi (default: 100 meter)

Saat clock-in/out, koordinat GPS user dibandingkan dengan koordinat branch. Jika di luar radius, staff tidak dapat melanjutkan clock-in.

## Tabel Database Terlibat

```
casual_positions
branches
casual_position_openings
casual_position_registrations
casual_clock_records
users (casual_position_id, phone, bank_name, bank_account_number)
```
