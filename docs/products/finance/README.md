# Finance

Sales Report per shift dan rekonsiliasinya dengan ESB, Compliment dan Void, penilaian kepatuhan laporan, basket size, dan Product Pricelist. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Finance | Sales Report Settings | `edit sales report settings` |
| | Sales Report Scores | `view sales report scores`, `edit sales report assessment settings` |
| | Sales Report (tab Laporan Harian, Compliment, Void) | `view sales reports`; review: `review sales reports as supervisor`, `review sales reports as finance`; settlement: `input sales settlements` |
| | Compliment Types | `view compliment types` (+ create/edit/delete) |
| | Basket Size | `view basket sizes`, `recalculate basket sizes`, `export basket sizes` |
| | Product Pricelist | `view material pricelist`, `manage material pricelist` |
| Employee App | Tile **Sales Report** | `access employee app sales report` |

## Sales Report

1. Staff memilih tanggal dan shift di tile **Sales Report**. Jumlah dan jam shift mengikuti pengaturan shift cabang.
2. **Ambil dari ESB** memuat daftar payment method per label (DINE IN, TAKEAWAY) dan transaksi shift dari ESB corev1. Snapshot transaksi disimpan di server, bukan di state browser.
3. Staff mengisi nominal per payment method (sales store), staff in charge, **Compliment** (sales number terverifikasi, jenis, keterangan, foto bukti), dan **Void** (sales number, payment method, alasan, foto nota awal dan setelah void), lalu submit. `SaveSalesReportShiftAction` menyimpan semuanya dalam satu transaksi. Setelah semua shift masuk, laporan berpindah dari `draft` ke `pending_supervisor`.
4. **Supervisor** me-review dan menyimpan koreksi. Approve langsung menjadikan laporan `completed` (skor 100). Approval Finance sudah dihapus.
5. **Finance** dapat menolak laporan `completed` (skor tanggal itu menjadi 0) dan mengisi settlement/MDR.
6. Laporan yang tidak direview melewati batas hari di Sales Report Settings di-reject otomatis oleh `sales-reports:auto-reject` (tiap menit), menjadi `rejected_by_system`.

Status: `draft`, `pending_supervisor`, `completed`, `rejected`, `rejected_by_system`, dan `pending_finance` (legacy, hanya untuk data lama).

**Rekonsiliasi** (`SalesReportReconciliationService`) membandingkan total store per label dan payment method dengan total sistem ESB. Pembayaran CASH tunggal dibatasi sebesar `grandTotal` agar kembalian tidak terhitung.

Tab **Compliment** dan **Void** menampilkan semua entri dalam branch scope user, dengan filter dan export Excel untuk dicocokkan dengan laporan ESB. Bukti foto disimpan privat dan dibuka melalui URL sementara.

## Sales Report Settings dan Scores

- **Settings**: aktif/nonaktif auto-reject, batas hari, tanggal berlaku, dan alasan auto-reject.
- **Scores**: per cabang per bulan, setiap tanggal wajib bernilai 100 bila laporan lolos dan 0 bila tidak. Skor bulanan = tanggal lolos ÷ tanggal wajib × 100. Tanggal tertentu dapat dikecualikan per cabang.

## Basket Size

- Basket size per shift = pendapatan ÷ total pax dari transaksi ESB dalam jam shift cabang, dihitung oleh `basket-size:finalize` (tiap 10 menit) setelah shift berakhir, tanpa menunggu submit staff.
- Halaman Basket Size menampilkan ranking cabang dan riwayat, menghitung ulang data yang usang secara bertahap, dan export.
- Cabang tanpa pengaturan shift harus dilengkapi lebih dulu di Master Branch.

## Compliment Types

Master jenis compliment yang dipilih saat mengisi Sales Report.

## Product Pricelist

Antrean harga resmi Finance untuk Master Bahan dan WIP yang sudah lolos sourcing: simpan draft harga, konfirmasi dengan referensi ESB (mode manual), kembalikan mapping yang salah ke IT, dan revisi harga. Lihat [Research & Development](../rnd/README.md#master-bahan-dan-sourcing).

## Command

| Command | Fungsi |
|---|---|
| `sales-reports:auto-reject` | Terjadwal tiap menit |
| `basket-size:finalize` | Terjadwal tiap 10 menit |
| `sales-reports:audit-cash-discrepancy` | Read-only secara default; `--apply` menulis ulang `system_amount` yang terdampak bug kembalian CASH |
| `sales-reports:migrate-legacy-finance-status` | Preview, konversi, atau restore laporan `pending_finance` lama |
| `branch-shifts:clear` | One-off: hapus pengaturan shift basket size cabang (dengan backup dan `--restore`) |

## Data

`SalesReport`, `SalesReportShiftSubmission`, `SalesReportEntry`, `SalesReportEmployee`, `SalesReportCompliment`, `SalesReportVoid`, `SalesReportEsbTransaction`, `SalesReportReconciliation`, `SalesReportApproval`, `SalesReportSettings`, `ComplimentType`, `BasketSizeRecord`, `BasketSizeEmployeeRecord`. Lihat [Data Model](../../architecture/data-model.md#finance).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [PRD Sales Report — Compliment & Void Remediation](sales-report-compliment-void-remediation/prd.md) | Implemented |

## Role bawaan

`STORE_STAFF` mengisi Sales Report. `SUPERVISOR_STORE` me-review dan mengelola Basket Size. `FINANCE_STAFF` menolak laporan, mengisi settlement, dan mengelola Compliment Types, Basket Size, serta Product Pricelist.
