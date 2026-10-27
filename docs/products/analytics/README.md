# Analytics

Analitik penjualan, promosi, pemakaian bahan, dan rekomendasi pemesanan per cabang dari data ESB. Kondisi per 27 Oktober 2026.

## Lokasi

| Menu (Back Office → Analytics) | Permission |
|---|---|
| Sales Information | `view sales information` |
| Ordering Recommendation | `view ordering recommendations`, `export ordering recommendations` |
| Ordering Settings | `manage ordering recommendation settings` |
| Promotion Information | `view promotion information` |
| Stock Information | `view stock information` |

## Sales Information

- Memilih brand, cabang, dan rentang tanggal (dengan periode pembanding opsional). Data diambil per pasangan kode ESB cabang dari `GET /corev1/sales/sales-information` (transaksi `Finished`), halaman demi halaman.
- **Total Pendapatan** = jumlah pembayaran per transaksi, agar selalu sama dengan Rekapitulasi Pembayaran. Pembayaran CASH tunggal dibatasi sebesar `grandTotal` agar kembalian tidak terhitung. Bila transaksi tidak memiliki data pembayaran, `grandTotal` yang dipakai. Angka yang sama dipakai untuk tren, pendapatan per cabang, visit purpose, dan promo.
- Pendapatan per kategori/sub-kategori/menu memakai `salesMenus[].total`, sehingga totalnya bisa berbeda dari Total Pendapatan (pajak, service charge, dan diskon tingkat transaksi).
- Ringkasan diskon (menu, voucher, promo), pax, item, rekap pembayaran, daftar transaksi, dan export.

## Promotion Information

Daftar penggunaan promosi per brand dan cabang dari transaksi ESB, digabung dengan katalog promosi dari API promo ESB.

## Stock Information

Pemakaian bahan harian per cabang (Daily Sales Material Usage ESB, satuan stok) untuk cabang terpilih, diambil bertahap per cabang.

## Ordering Recommendation

- Rekomendasi jumlah pesan per cabang, produk, dan UOM dari **snapshot lokal** dan forecast run terakhir yang selesai. Halaman ini tidak memanggil ESB.
- Pipeline harian: `ordering:sync` (jam dari `ordering_recommendation.sync_time`) menjadwalkan job per sumber, pasangan company, dan hari. Sumbernya Daily Sales Material Usage (permintaan utama) dan Stock Movement. Setelah snapshot selesai, `GenerateOrderingForecastJob` menyusun forecast per cabang dan horizon.
- Hari yang gagal dibaca tidak dihitung sebagai nol. Jumlah tidak dijumlahkan lintas UOM atau company.
- Setiap item memiliki tingkat keyakinan (`high`, `medium`, `low`, `blocked`), label tren (`rising`, `falling`, `stable`, `newly_sold`, `no_demand`, `unknown`), dan reason code. Reason yang bersifat blocking menahan jumlah rekomendasi.
- Detail item menjelaskan komponen perhitungan. Export XLSX berisi sheet Summary, Recommendations, Daily History, dan Data Quality.
- **Ordering Settings**: mode lokasi stok per kode ESB cabang (`unconfigured`, `selected`, `all`) beserta discovery lokasi dari ESB.
- `ordering:backfill` menjalankan backfill riwayat (dry-run atau dispatch).

## Data

`OrderingSyncRun`, `OrderingDailyMetric`, `OrderingForecastRun`, `OrderingForecastItem`; lokasi stok di `BranchEsbCode`. Halaman Sales/Promotion/Stock Information tidak menyimpan data. Lihat [Data Model](../../architecture/data-model.md#analytics).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [PRD Ordering Recommendation](ordering-recommendation/prd.md) | Implemented |
