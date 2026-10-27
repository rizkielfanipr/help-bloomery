# Driver

Perjalanan driver dengan check-in per titik, odometer, pengisian BBM, dan uang makan bulanan. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Driver | Perjalanan | `view trips` (+ create/edit/delete) |
| | Rute Perjalanan | `view trip routes` (+ create/edit/delete) |
| | Kendaraan | `view vehicles` (+ create/edit/delete) |
| | Jenis BBM | `edit trips` |
| | Uang Makan Driver | `view driver meal allowance periods`; aksi: `create/edit/finalize/reopen driver meal allowance periods` |
| | Pengaturan | `edit trips` |
| Employee App | Tile **Driver** | `access employee app driver` |
| Panel Driver (`/driver`) | Halaman yang sama dengan tile Driver | `access employee app driver` |

## Alur perjalanan

1. **Mulai perjalanan** — driver memilih rute master (atau mengisi rute custom dengan daftar titik), tanggal, dan kendaraan.
2. **Perjalanan aktif** — driver mencatat odometer awal (foto), lalu check-in di setiap titik. Check-in dapat mewajibkan foto, lokasi GPS dalam radius titik, akurasi GPS maksimal, dan urutan titik, sesuai Pengaturan. Foto bisa diberi stempel waktu, koordinat, nama driver, titik, dan rute.
3. **BBM** — modal pengisian BBM (bila diaktifkan) mencatat jenis BBM, foto nota, dan foto indikator; nota bisa diwajibkan.
4. **Selesai** — driver mencatat odometer akhir dan menyelesaikan perjalanan.
5. Riwayat perjalanan per periode dapat diunduh sebagai PDF laporan trip.

Status perjalanan: `pending`, `in_progress`, `completed`.

Back office **Perjalanan** menampilkan semua perjalanan (driver, rute, tanggal, status, BBM, uang makan), dapat menambah perjalanan, dan export Excel.

## Rute dan kendaraan

- **Rute Perjalanan**: nama, deskripsi, uang makan per rute, status aktif, dan daftar titik berurutan (nama, keterangan, koordinat/radius).
- **Kendaraan**: nomor polisi, merek, model, tahun, status aktif.
- **Jenis BBM**: master jenis bahan bakar.

## Uang makan driver

- Periode dibuat per bulan dengan tanggal cut-off dari Pengaturan (`report_cutoff_day`).
- **Sinkron** menarik perjalanan dalam periode. Nominal diambil dari perjalanan, lalu dari rute, lalu default uang makan rute custom.
- HR dapat mengubah nominal per perjalanan, mengecualikan perjalanan (dengan alasan), dan menambah penyesuaian per driver (penyesuaian selain nol wajib beralasan).
- Periode dapat di-**finalisasi** dan **dibuka kembali** sesuai permission. Perubahan cut-off menghitung ulang semua periode yang masih terbuka.
- Export tersedia.

## Pengaturan

Satu halaman (`DriverTripSettings`) untuk: modal BBM, wajib nota BBM, cut-off laporan, uang makan default, sumber dan kualitas foto check-in, wajib foto/GPS/radius, radius default, stempel foto, akurasi GPS maksimal, izin check-in ulang, dan wajib urut titik.

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{Trips,TripRoutes,Vehicles,FuelTypes}/`, `app/Filament/Helpdesk/Pages/{DriverMealAllowancePage,DriverTripSettingsPage}.php` |
| Employee App | `app/Filament/Casual/Pages/{TripDashboard,StartTrip,ActiveTrip,FuelFillup,TripDetail,TripHistory,DriverProfilePage}.php`, `app/Filament/Driver/Pages/` |
| Logika | `app/Services/DriverMealAllowanceService.php`, `app/Http/Controllers/Driver/TripReportController.php` |
| Enum | `app/Enums/TripStatus.php` |
| Model | `app/Models/{Trip,Vehicle,FuelType,Driver}*.php` |
| Test | `tests/Feature/{TripFlowTest,DriverMealAllowanceWorkflowTest}.php` |

## Data

`Trip`, `TripRoute`, titik dan check-in perjalanan, `Vehicle`, `FuelType`, `DriverTripSettings`, `DriverMealAllowancePeriod`, `DriverMealAllowanceSummary`, `DriverMealAllowanceTripItem`. Lihat [Data Model](../../architecture/data-model.md#driver).

## Role bawaan

`DRIVER` memakai tile Driver serta melihat rute, kendaraan, dan jenis BBM. Pengelolaan back office diberikan sesuai permission.
