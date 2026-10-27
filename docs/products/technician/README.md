# Technician

Permintaan perbaikan dari store, siklus perbaikan oleh teknisi atau vendor luar, garansi, maintenance bulanan, dan asset ber-QR. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Technician | Permintaan Service | `view service requests` (+ create/edit/delete) |
| | Rekap Maintenance | `view technician monthly maintenance` (+ create/edit/delete) |
| | Checklist Poin | `view technician monthly maintenance` |
| | Asset QR | `view assets` (+ create/edit/delete, `generate asset qr codes`, `print asset labels`) |
| | Pengaturan | `edit service requests` |
| Employee App | Tile **Req. Teknisi** (store) | `access employee app technician request` |
| Employee App | Tile **Teknisi** (teknisi) | `access employee app technician` |
| Panel Technician (`/technician`) | Daftar service request dan maintenance | `access employee app technician` |
| Publik | Scan QR asset `/assets/scan/{token}` | Lihat tanpa login; laporan kerusakan wajib login |

## Permintaan service

1. **Pengajuan** — store mengajukan dari tile Req. Teknisi (catatan dan lampiran), dari scan QR asset, atau back office membuatnya langsung. Pada ketiga jalur ini teknisi ditugaskan otomatis berdasarkan jumlah pekerjaan aktif (`AssetServiceRequestAssigner`).
2. **Penjadwalan** — teknisi menjadwalkan (tanggal ≥ hari ini, prioritas `normal/high/urgent`). Kuota per hari dibatasi **Maksimal Pekerjaan per Hari** di Pengaturan.
3. **Pengerjaan internal** — teknisi memulai siklus perbaikan dengan foto dan catatan *before*. Asset dinonaktifkan selama perbaikan. Teknisi lalu menyelesaikan dengan foto/catatan *after* dan kondisi asset.
4. **Outsource** — teknisi dapat mengalihkan ke vendor dengan alasan. Store mengirim laporan vendor, lalu teknisi memverifikasi (setuju → garansi, atau dikembalikan ke outsource).
5. **Garansi 30 hari** — setelah selesai, permintaan berstatus `warranty`. Selama garansi, store dapat mengajukan klaim dengan catatan dan lampiran (status `re_submitted`), yang membuka siklus perbaikan baru. `service-requests:complete-warranty` (harian) menutup permintaan yang garansinya habis.

Status: `submitted`, `scheduled`, `awaiting_parts`, `outsource`, `awaiting_verification`, `in_progress`, `warranty`, `re_submitted`, `completed`.

Setiap siklus perbaikan tersimpan di `service_request_repairs` (nomor siklus, teknisi, foto/catatan before–after, laporan outsource, masa garansi). Back office menyediakan filter tanggal dan export Excel.

## Asset QR

- Master asset: nomor, nama, kategori, merek, model, nomor seri, cabang, status aktif, catatan.
- Generate dan generate ulang QR, unduh QR, cetak label (satuan atau massal), dan lihat riwayat perbaikan per asset.
- QR membuka halaman asset publik; laporan kerusakan dari halaman itu membuat permintaan service setelah login.

## Maintenance bulanan

- **Checklist Poin**: pertanyaan, prosedur pengecekan, urutan, kebutuhan foto, status aktif.
- Teknisi memulai maintenance bulan berjalan per cabang di Employee App, mengisi checklist (draft), lalu submit.
- **Rekap Maintenance** di back office menampilkan status per cabang, tahun, dan bulan.

## Data

`ServiceRequest`, `ServiceRequestRepair`, `Asset`, `TechnicianSettings`, `TechnicianMonthlyMaintenance` dan checklist-nya. Lihat [Data Model](../../architecture/data-model.md#technician).

## Role bawaan

`TECHNICIAN` mengelola service request, maintenance, dan asset. `STORE_STAFF` mengajukan permintaan service.
