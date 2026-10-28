# Kitchen

Kalender Production Order ESB read-only untuk Branch Kitchen. Kondisi per 28 Oktober 2026 (PRD v3.0).

## Lokasi

| Tempat | Menu | Permission |
|---|---|---|
| Back Office → Kitchen | Jadwal Produksi | `view kitchen production schedules`; seluruh Branch Kitchen: `view all branch kitchen production schedules`; tombol **Sync Data**: `sync kitchen production orders`. Tidak ada aksi create/update/cancel. |

## Jadwal Produksi

Halaman menampilkan **Kalender Production Order**: Production Order dari ESB per tanggal produksinya, dibaca dari snapshot lokal. Rencana produksi lokal (buat/edit/batal jadwal, picker BOM Assembly) sudah dihapus pada v3.0 beserta tabelnya ([PRD §38](production-schedule/prd.md)).

- Grid bulanan Senin–Minggu di desktop dan agenda per tanggal di mobile. Rentang tanggal, Branch, dan status diterapkan di query.
- Chip berlabel **ESB** menampilkan nomor order, Branch, status, dan jumlah item (quantity lintas satuan tidak dijumlahkan). Klik chip membuka detail read-only: status, tipe BOM, Branch ESB, dibuat/diubah/diotorisasi di ESB, informasi tambahan, terakhir disinkronkan, dan detail BOM/product dengan quantity, UOM, status selesai, serta material yang dapat dibuka. Halaman tidak mempunyai tombol atau form yang mengubah data.
- Filter: **Branch Kitchen** (`Semua Kitchen` lalu Branch sesuai akses) dan **Status** (label status ESB dari snapshot). Tersimpan di URL (`bulan`, `branch`, `status`).
- **Sync Data** (permission `sync kitchen production orders`): mengantrekan sync incremental (−7 s.d. +14 hari) untuk Branch yang dipilih di filter, atau semua Branch Kitchen yang dapat diakses. Request browser tidak memanggil ESB; mapping dengan run aktif atau run < 5 menit terakhir dilewati (cooldown). Selama sync berjalan, banner menampilkan "Sinkronisasi sedang berjalan" dan halaman memperbarui diri setiap 5 detik. Butuh worker queue yang berjalan.
- Hanya Branch ber-Brand **Kitchen** yang tampil, untuk setiap user termasuk Superadmin. Akses semua branch / `view all branch kitchen production schedules` hanya membuka seluruh Branch Kitchen, tidak pernah Brand lain. Query dan detail builder menegakkan scope ini di server.
- Kalender dan detail tidak memanggil ESB. Snapshot terakhir tetap tampil saat sinkronisasi gagal.
- Banner status per Branch: belum terhubung ke ESB, mapping belum lengkap (termasuk company belum terdaftar di konfigurasi ESB), belum pernah disinkronkan, sedang berjalan, data mungkin belum terbaru (> 3 jam), sebagian detail gagal, atau sinkronisasi terakhir gagal; ditambah waktu terakhir disinkronkan.
- Quantity detail adalah `productionOrderQty` dalam satuan resep ESB (misalnya `4 Resep@210GR`); quantity material adalah total kebutuhan (`totalQty`).
- Sebelum sync pertama di sebuah environment, jalankan **Master → Branch → Sync Branch ESB** agar Branch ID ESB di mapping terisi dan tidak usang.

### Sinkronisasi

| Command / job | Perilaku |
|---|---|
| `kitchen:sync-production-orders` | Incremental hari ini −7 s.d. +14 hari untuk **setiap mapping ESB aktif dan lengkap pada Branch Kitchen aktif** di Master Branch (Company Code terdaftar di `config/esb.php`, Branch ID ESB terisi) — tanpa allowlist di konfigurasi (K20). Saat ini: Kitchen Jateng `BLSS/KTJ`, Kitchen Surabaya `BLO13/HOF`, Kitchen Jakarta `BLHJ/TCHL`. Branch Kitchen baru cukup ditambahkan di Master Branch beserta mapping-nya. BLSS dibaca dengan credential global ESB Core karena credential per-company BLSS tidak punya akses (`KITCHEN_PRODUCTION_ORDER_GLOBAL_CREDENTIAL_COMPANIES`); company lain memakai credential company (`ESB_CORE_{COMPANY}_*`). `--dry-run`, `--branch=`, `--mapping=`. Scheduler: setiap jam bila `KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED=true` |
| `kitchen:backfill-production-orders` | Backfill `--from`/`--to`; dry-run default, `--execute` untuk dispatch, maksimal 120 hari kecuali `--allow-long-range` |
| `SyncEsbProductionOrdersJob` | Satu run = satu mapping + window ≤ 31 hari; membaca halaman (maksimal 50), menyimpan per halaman, retry hanya untuk timeout/429/5xx |
| `SyncEsbProductionOrderDetailJob` | Membaca detail order baru, berubah, atau belum lengkap; kegagalan satu order membuat run `partial` |
| `model:prune` (03:40) | Menghapus riwayat run > 90 hari; snapshot tidak pernah dihapus |

Status run: `queued`, `running`, `succeeded`, `partial`, `failed`. Satu window hanya boleh mempunyai satu run aktif; run yang tidak bergerak > 2 jam ditutup `failed` lalu dijadwalkan ulang. Order yang sama dari mapping Branch lain tidak dipindahkan (dihitung konflik).

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Pages/KitchenProductionSchedulePage.php`, `resources/views/filament/helpdesk/pages/kitchen-production-schedule-page.blade.php` |
| Logika | `app/Actions/Kitchen/`, `app/Services/Kitchen/` |
| Integrasi ESB | `app/Services/EsbProductionOrderService.php`, `app/Exceptions/Kitchen/` |
| Job / command | `app/Jobs/Kitchen/`, `app/Console/Commands/Kitchen*ProductionOrdersCommand.php`, `routes/console.php` |
| Konfigurasi | `config/kitchen.php` |
| Policy | `app/Policies/EsbProductionOrderPolicy.php` |
| Enum | `app/Enums/EsbProductionOrder*.php` |
| Model | `app/Models/EsbProductionOrder*.php` |
| Test | `tests/Feature/KitchenProductionOrder*Test.php`, `tests/Feature/EsbProductionOrderServiceTest.php`; fixture ESB di `tests/Pest.php` |

## Data

Snapshot ESB: `EsbProductionOrder`, `EsbProductionOrderDetail`, `EsbProductionOrderSyncRun`. Tabel rencana lokal `kitchen_production_schedules` dan `kitchen_production_schedule_items` di-drop oleh migration `2026_10_28_085835_drop_kitchen_production_schedule_tables`. Lihat [Data Model](../../architecture/data-model.md#kitchen).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [Jadwal Produksi](production-schedule/README.md) dan [PRD](production-schedule/prd.md) §25–§38 — kalender Production Order ESB | In development (kode selesai, belum rollout) |
| [PRD](production-schedule/prd.md) §1–§24 — rencana produksi lokal | Deprecated (dihapus v3.0) |
