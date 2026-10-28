# Workforce — Casual Staff

Pengelolaan pekerja casual: registrasi akun, posisi dan tarif, lowongan per cabang, absensi berbasis GPS dan foto, serta lembur. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Casual Staff | Casual Staff | `view casual staff` (+ `create/edit/delete casual staff`) |
| | Posisi Casual | `view casual positions` (+ create/edit/delete) |
| | Lowongan Posisi | `view casual openings` (+ create/edit/delete) |
| | Absensi Casual | `view clock records` (+ create/edit/delete) |
| | Lembur Casual | `view overtime requests` |
| Employee App | Tile **Absensi** | `access employee app attendance` |
| Employee App | Halaman registrasi akun | Publik (form registrasi Employee App) |

## Fitur

### Registrasi dan data Casual Staff

- Pekerja casual mendaftar sendiri di Employee App: nama lengkap, username, nomor HP, nama bank, nomor rekening, dan kata sandi.
- Back office mengelola data yang sama: posisi, status aktif, dan data bank. Akun Casual Staff adalah `User` dengan `casual_position_id`.
- Relation manager menampilkan riwayat pendaftaran lowongan per staff.
- Export tersedia untuk data staff.

### Posisi Casual

Master posisi: nama, **fee per hari**, **rate lembur per jam**, deskripsi, dan status aktif. Tarif ini dipakai untuk menghitung fee kerja dan lembur.

### Lowongan Posisi

- Back office membuka lowongan per posisi dan cabang/lokasi dengan jadwal berisi tanggal kerja dan jumlah slot, serta mengatur status buka pendaftaran.
- Di Employee App, staff melihat lowongan yang tersedia, mendaftar per tanggal, dan membatalkan pendaftaran selama masih diizinkan.
- Daftar pendaftar per lowongan dapat dilihat di back office (read-only).

### Absensi (clock in/out)

- Tile **Absensi** membuka halaman Clock: clock in dan clock out dengan **foto** dan **koordinat GPS**, terhadap cabang dari pendaftaran aktif staff.
- Halaman juga menampilkan statistik bulanan, jam kerja, pengajuan izin, dan pengajuan lembur.
- Back office memantau seluruh catatan absensi (staff, posisi, cabang, tanggal, jam masuk/keluar, foto, koordinat) dan dapat mengoreksinya sesuai permission. Export absensi tersedia.
- Riwayat absensi staff ada di halaman Attendance; laporan mingguan dapat diunduh sebagai PDF dari halaman Report.

### Lembur

- Staff mengajukan lembur dari halaman Clock (tanggal kerja, durasi, alasan) dan dapat membatalkannya dari riwayat.
- Back office **Lembur Casual** menampilkan rekap lembur dengan fee lembur hasil perhitungan rate posisi. Menu ini hanya untuk melihat data.

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/Casual*/`, `app/Filament/Helpdesk/Widgets/CasualPositionStatsWidget.php` |
| Employee App | `app/Filament/Casual/Pages/{ClockPage,AttendancePage,PositionsPage,ReportPage,ProfilePage}.php`, `app/Filament/Casual/Pages/Auth/Register.php` |
| Controller/Export | `app/Http/Controllers/Helpdesk/Casual*Controller.php`, `app/Filament/Exports/Casual*Exporter.php` |
| Model | `app/Models/Casual*.php` |
| Test | `tests/Feature/{PositionWarRegistrationTest,CasualLoginRedirectTest}.php` |

## Data

`User` (casual), `CasualPosition`, `CasualPositionOpening`, `CasualPositionRegistration`, `CasualClockRecord`, `CasualOvertimeRequest`. Lihat [Data Model](../../architecture/data-model.md#workforce-casual-staff).

## Role bawaan

`HRD_STAFF` mengelola semua menu di atas. `CASUAL_STAFF` memakai tile Absensi serta melihat dan mengajukan lembur miliknya.
