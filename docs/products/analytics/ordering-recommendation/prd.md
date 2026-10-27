# PRD — Analytics: Ordering Recommendation per Branch

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Dokumen | Analytics: Ordering Recommendation per Branch |
| Baseline audit | 21 Oktober 2026 |
| Status | Rancangan untuk review; belum mengotorisasi implementasi, migration production, commit, push, atau deployment |
| Modul | `Analytics → Ordering Recommendation` |
| Sifat fitur MVP | View-only decision-support dashboard |
| Pengguna utama | Operational, Kitchen, Store Management, dan user lain sesuai permission/branch scope |
| Stack existing | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Alpine.js 3, Tailwind CSS 4, Pest 4 |
| Acuan arsitektur | [`docs/standards/code-remediation.md`](../../../standards/code-remediation.md) |
| Acuan UI | [`docs/standards/ui-consistency.md`](../../../standards/ui-consistency.md) dan UI aktual `Sales Information` |
| Strategi | Snapshot data lokal, kalkulasi transparan, ranking per branch, dan rollout bertahap dengan backtest |

Dokumen ini mendefinisikan fitur analitik untuk merekomendasikan kebutuhan ordering produk Store ke Kitchen berdasarkan histori penjualan/pemakaian, tren, waste, dan stok. Versi pertama hanya menyajikan informasi dan export; fitur tidak membuat, menyetujui, atau mengirim order.

Dokumen ini tidak memberi izin menghapus data, mengubah kontrak ESB, menjalankan backfill/migration production, atau melakukan deployment. Repository dan kontrak API wajib diaudit kembali sebelum implementasi karena baseline dapat berubah.

## 2. Ringkasan produk

Ordering Recommendation menjawab pertanyaan berikut untuk setiap branch:

1. Produk apa yang paling banyak terpakai/terjual?
2. Produk apa yang trennya naik atau turun?
3. Produk apa yang waste-nya tinggi?
4. Produk apa yang berisiko kehabisan stok?
5. Berapa estimasi kebutuhan 7 atau 14 hari berikutnya?
6. Setelah mempertimbangkan stok, berapa quantity yang disarankan untuk diorder?
7. Dari data apa rekomendasi tersebut dihitung?
8. Seberapa lengkap dan dapat dipercaya rekomendasinya?

Satu recommendation selalu terikat pada satu branch, satu produk, satu UOM, dan satu periode forecast. Pemilihan beberapa branch hanya menampilkan beberapa hasil branch secara bersamaan; quantity antarbranch tidak dilebur.

```text
Sales Information + Daily Sales Material Usage
                    ↓
          Demand historis per branch
                    +
      Stock Movement: stok + waste
                    ↓
        Forecast kebutuhan per tanggal
                    ↓
 Forecast + expected waste + safety buffer
                    −
      available stock − confirmed inbound
                    ↓
     Rekomendasi order per branch-product-UOM
```

## 3. Keputusan produk MVP

| Area | Keputusan |
| --- | --- |
| Lokasi menu | `Analytics → Ordering Recommendation` |
| Model halaman | Serupa Sales Information: filter, KPI, grafik, ranking, tabel, detail, export |
| Scope hasil | Per branch dan per produk |
| Aksi pengguna | View, filter, membuka rincian, dan export bila berizin |
| Order creation | Tidak termasuk MVP |
| Approval | Tidak ada |
| Refresh normal user | Tidak ada pada MVP; data disinkronkan scheduler |
| Sumber demand utama | Daily Sales Material Usage bila kontraknya lengkap |
| Sumber tren penjualan | Sales Information per hari/menu |
| Sumber stok/waste | Stock Movement |
| Horizon | 7 atau 14 hari; default 7 hari |
| Histori kalkulasi | Default 8 minggu; minimum final dikalibrasi melalui backtest |
| Forecast awal | Statistik deterministik dan transparan, bukan black-box ML |
| Data tidak lengkap | Ditandai Partial/Blocked; tidak dianggap nol |
| Multi-company | Tidak digabung sebelum identity dan UOM tervalidasi |
| Export | XLSX mengikuti filter dan branch scope |
| Bahasa UI | Navigation/title bahasa Inggris; penjelasan operasional dapat berbahasa Indonesia |

## 4. Kondisi sistem saat baseline

Audit repository menemukan fondasi berikut:

### 4.1 Sales Information

- Halaman `Sales Information` sudah mendukung pemilihan Brand/Branch, rentang tanggal, periode pembanding, KPI, grafik tren, ranking menu, dan export.
- Data transaksi memuat `salesMenus`, quantity, category, subcategory, revenue, Sales Number, waktu transaksi, dan payment information.
- Agregasi menu pada halaman saat ini terutama memakai nama menu; ini belum cukup aman sebagai identity forecasting lintas company.
- Halaman mengambil data ESB per branch/page lalu menyimpan accumulator sementara untuk presentasi.

Ordering Recommendation mengadopsi pola visual dan hierarki informasi Sales Information, bukan menyalin seluruh implementasi Page/Alpine/export-nya.

### 4.2 Daily Sales Material Usage

- Service existing mempunyai endpoint penggunaan material harian berdasarkan branch, tanggal, dan flag UOM.
- Data konseptual memuat Product Code, Product Name, quantity, conversion quantity, dan unit.
- Endpoint ini merupakan kandidat demand utama karena sudah menerjemahkan penjualan menjadi pemakaian produk/material.
- Kontrak identity, kategori produk, dan konsistensi UOM wajib diverifikasi pada Phase 0.

### 4.3 Stock Movement

- `EsbStockMovementService` sudah memakai `EsbCoreClient` dan mendukung pagination, mapping company/branch, balance, transaction type, dan unit.
- Response dapat memuat Product Code, Product Name, UOM, transaction type, `qtyIn`, `qtyOut`, `qtyBalance`, location, dan waktu dokumen.
- Tipe transaksi observed dapat mencakup `POS Sales`, `Goods Delivery`, `Goods Receipt`, `Waste`, dan tipe lain.
- Service sudah mempunyai guard branch mismatch serta guard UOM yang berbeda.
- Latest balance harus diambil berdasarkan record terbaru per identity/location; historical balance tidak boleh dijumlahkan.

### 4.4 Branch dan company

- Satu branch lokal dapat mempunyai beberapa mapping Company Code/ESB Branch Code.
- Stock Card mempunyai sumber mapping yang dipilih secara eksplisit.
- User mempunyai branch scope lokal dan opsi `access_all_branches`.

### 4.5 Master produk/WIP

- Master Product dan snapshot WIP lokal tersedia pada domain existing.
- Product Detail ID berlaku dalam context company dan UOM; Product Code/nama saja tidak otomatis menjadi identity lintas company.

### 4.6 Batas modul Store Sales Order existing

- `Store Sales Order` existing mencatat Sales Order customer dan kebutuhan operasional.
- Modul tersebut secara eksplisit tidak mencakup forecasting atau ordering Store ke Kitchen.
- Ordering Recommendation tidak boleh memakai tabel/status Store Sales Order existing sebagai order Store–Kitchen.

## 5. Masalah yang diselesaikan

1. Store/Operational belum mempunyai rekomendasi quantity yang menggabungkan demand, tren, waste, dan stok.
2. Penilaian kebutuhan sering bergantung pada pemeriksaan manual beberapa report.
3. Data penjualan, material usage, dan stock movement tersedia tetapi belum menjadi dataset historis terintegrasi per branch-product-date.
4. Tidak ada ukuran konsisten untuk stock coverage, stockout risk, waste rate, dan forecast accuracy.
5. Rekomendasi berisiko salah jika API gagal dan missing data dianggap nol.
6. Multi-company serta UOM berbeda dapat menghasilkan penjumlahan yang salah tanpa canonical mapping.
7. Forecast yang tidak menyimpan versi dan input snapshot tidak dapat diaudit atau dibandingkan dengan actual.

## 6. Tujuan dan ukuran keberhasilan

### 6.1 Tujuan

- Menyediakan rekomendasi order terpisah per branch.
- Menampilkan ranking dan tren produk di dalam setiap branch.
- Menjelaskan formula dan sumber setiap recommendation.
- Menggunakan snapshot lokal agar dashboard cepat dan tidak bergantung pada live API saat render.
- Membedakan nilai nol, data kosong, API gagal, stale, dan mapping invalid.
- Mengukur forecast terhadap actual melalui backtest.
- Menyiapkan fondasi yang dapat dikembangkan menjadi Draft Order tanpa menulis ulang kalkulator.

### 6.2 Metrik keberhasilan

| Area | Metrik |
| --- | --- |
| Forecast | WAPE, MAE, dan forecast bias per branch/product |
| Operasional | Stockout risk, emergency order, waste rate, days of stock |
| Data | Persentase hari/source lengkap, sync failure, stale source |
| Penggunaan | Branch dilihat, export, detail recommendation dibuka |
| Performa | Waktu load dashboard, query count, payload Livewire, durasi sync |
| Akurasi | Selisih forecast dengan actual setelah horizon selesai |

MVP tidak diberi target persentase akurasi arbitrer sebelum pilot menghasilkan baseline nyata.

## 7. Scope

### 7.1 In scope

- Navigation dan Page `Analytics → Ordering Recommendation`.
- Snapshot histori Sales, Daily Sales Material Usage, Stock Movement, dan data quality.
- Demand, trend, waste, available stock, stock coverage, stockout risk, dan recommendation.
- Pemilihan satu atau beberapa branch dengan hasil tetap terpisah.
- Ranking per branch.
- Confidence dan explanation per recommendation.
- Backtest forecast terhadap actual.
- Filter, KPI, grafik, tabel, modal detail, pagination, dan XLSX export.
- Scheduler, queued jobs, backfill command, idempotency, dan observability.
- Permission dan branch scope.

### 7.2 Out of scope MVP

- Membuat atau mengirim Store–Kitchen Order.
- Approval, status workflow, dan revisi order.
- Auto-submit ke Kitchen/ESB.
- Supplier selection.
- Notifikasi eksternal.
- Input/override recommendation oleh user.
- Settings page per branch/product.
- MOQ/pack-size rounding bila master resmi belum tersedia.
- Lead-time optimization bila kontrak lead time belum tersedia.
- Promo/event/payday/holiday adjustment otomatis.
- Python service, microservice, atau ML platform eksternal.
- Perubahan perilaku halaman Sales Information existing.

## 8. Aktor dan akses

| Aktor | Kebutuhan | Scope |
| --- | --- | --- |
| Operational | Melihat kebutuhan seluruh branch yang diizinkan | Sesuai permission dan branch access |
| Kitchen Management | Melihat demand branch yang dipasok | Scope ditentukan permission/branch mapping yang tersedia |
| Store Management | Melihat recommendation branch sendiri | Accessible branches |
| Analyst/Management | Membandingkan beberapa branch dan export | Accessible branches atau all branches |
| Administrator | Memantau sync/backfill dan data quality | Permission terpisah |

Permission konseptual:

```text
view ordering recommendations
export ordering recommendations
refresh ordering recommendation data
```

Permission refresh hanya ditambahkan jika aksi manual benar-benar dibuat. Normal user MVP cukup mempunyai view/export.

## 9. Sumber data dan kontrak penggunaan

### 9.1 Sales Information

Digunakan untuk:

- quantity menu terjual per tanggal;
- tren transaksi dan menu;
- validasi perubahan demand;
- peringkat menu bila stable identity tersedia;
- konteks branch dan company.

Tidak digunakan sendirian untuk menghitung kebutuhan produk apabila mapping menu → orderable product tidak dapat dibuktikan.

### 9.2 Daily Sales Material Usage

Digunakan sebagai demand utama produk:

```text
sales_driven_usage
= total quantity produk yang digunakan karena penjualan
```

Kontrak minimum yang harus dipastikan:

- branch/company source;
- sales date;
- Product Code atau Product Detail ID;
- Product Name;
- quantity;
- unit;
- conversion quantity/unit bila tersedia;
- response completeness/pagination.

### 9.3 Stock Movement

Digunakan untuk:

- available stock terbaru;
- historical waste;
- pergerakan barang masuk/keluar;
- validasi pemakaian;
- waktu terakhir stock tersedia.

Aturan:

- `POS Sales` tidak ditambahkan kembali jika demand sudah memakai Daily Sales Material Usage.
- `Waste` disimpan terpisah dari sales-driven usage.
- `Goods Delivery` historis tidak otomatis dianggap confirmed inbound.
- `qtyBalance` memakai row terbaru per product-location-UOM.
- Saldo beberapa location hanya digabung jika scope stock location sudah disepakati.

### 9.4 Confirmed inbound

Confirmed inbound hanya boleh digunakan jika terdapat sumber yang dapat membuktikan:

- order belum diterima;
- branch tujuan;
- product identity dan UOM;
- outstanding quantity;
- status yang sah;
- expected delivery.

Jika sumber belum tersedia, field inbound menjadi `null/not_available`, bukan 0. UI menampilkan **Inbound belum diperhitungkan** dan confidence diturunkan.

### 9.5 Master Product/WIP

Digunakan untuk:

- resolve product identity;
- kategori;
- active status;
- Product Detail ID;
- UOM dan conversion;
- menentukan kandidat produk orderable.

## 10. Identity, mapping, dan UOM

### 10.1 Identity mentah

Identity sumber minimum:

```text
local_branch_id
+ company_code
+ esb_branch_code
+ product_detail_id atau product_code
+ UOM
```

### 10.2 Canonical product

Jika produk yang sama muncul pada beberapa company, sistem membutuhkan canonical mapping eksplisit. Mapping harus menyimpan:

- canonical product key;
- company code;
- Product ID/Product Detail ID/Product Code;
- UOM sumber;
- UOM canonical;
- conversion factor bila ada;
- active/effective state;
- waktu verifikasi.

Nama produk tidak cukup untuk membuat mapping otomatis.

### 10.3 Aturan UOM

- Quantity berbeda UOM tidak boleh dijumlahkan tanpa conversion valid.
- Conversion harus mempunyai arah dan factor yang eksplisit.
- Hasil perhitungan menyimpan source quantity, source UOM, normalized quantity, dan normalized UOM bila conversion dilakukan.
- Rekomendasi ditampilkan dalam UOM yang dipahami user dan dapat dibuktikan.
- KPI tidak boleh menjumlahkan `PCS + KG + GR` sebagai satu total quantity.

### 10.4 Produk eligible

MVP hanya menghitung produk yang:

- aktif;
- dapat diidentifikasi;
- memiliki UOM valid;
- termasuk kategori yang dinyatakan orderable Store dari Kitchen;
- mempunyai branch/company source yang sah.

Kategori final ditetapkan pada Phase 0. Allowlist sementara harus terpusat di config/domain service, bukan hard-code dalam Page atau Blade.

## 11. Model data konseptual

Nama tabel final mengikuti konvensi repository dan hasil schema audit.

### 11.1 Ordering data sync runs

Menyimpan satu upaya sinkronisasi:

| Field | Fungsi |
| --- | --- |
| `source` | `sales`, `daily_usage`, `stock_movement`, `catalog`, atau `inbound` |
| `branch_id` | Branch lokal |
| `company_code` | Context company |
| `esb_branch_code` | Context branch ESB |
| `period_from/to` | Periode pengambilan |
| `status` | Pending, Running, Completed, Partial, Failed |
| `rows_received/stored` | Audit jumlah data |
| `started_at/finished_at` | Durasi |
| `error_summary` | Pesan aman |
| `correlation_id` | Observability |
| `metadata` | Pagination/version tanpa credential |

### 11.2 Ordering daily product metrics

Satu row mewakili satu branch-company-product-UOM-date:

| Field | Fungsi |
| --- | --- |
| `branch_id` | Branch lokal |
| `company_code` | Company ESB |
| `esb_branch_code` | Branch source |
| `metric_date` | Tanggal |
| product identity | Product Detail/Product ID/Product Code snapshot |
| product label | Nama dan kategori snapshot |
| `source_uom` | UOM response |
| normalized fields | Quantity dan UOM setelah conversion jika valid |
| `sales_driven_usage` | Demand utama |
| `pos_sales_usage` | Validasi saja, tidak ditambahkan ganda |
| `waste_quantity` | Waste terpisah |
| `goods_in_quantity` | Historical inbound/receipt bila relevan |
| `ending_stock` | Balance terbaru pada tanggal tersebut |
| `stock_observed_at` | Waktu balance |
| `sales_complete` | Kelengkapan source |
| `usage_complete` | Kelengkapan source |
| `stock_complete` | Kelengkapan source |
| sync reference | Relasi/log sync |

Unique identity minimal mencakup branch, company, ESB branch, date, source product identity, dan UOM.

### 11.3 Forecast runs

| Field | Fungsi |
| --- | --- |
| `branch_id` | Forecast selalu per branch |
| `history_from/to` | Training window |
| `forecast_from/to` | Horizon |
| `algorithm_version` | Reproducibility |
| `configuration_snapshot` | Bobot/cap/buffer yang dipakai |
| `data_cutoff_at` | Batas data input |
| `status` | Pending, Running, Completed, Partial, Failed |
| completeness fields | Kualitas source |
| `generated_at` | Waktu hasil |

### 11.4 Forecast items

| Field | Fungsi |
| --- | --- |
| forecast run | Parent |
| canonical/source identity | Produk yang dihitung |
| UOM | UOM hasil |
| historical demand | Demand input |
| weekday baseline | Baseline |
| trend factor | Adjustment tren |
| forecast demand | Kebutuhan penjualan |
| historical/expected waste | Waste |
| safety buffer | Buffer |
| available stock | Stok terakhir |
| confirmed inbound | Nullable |
| gross/raw/final recommendation | Hasil formula |
| coverage days | Days of stock |
| predicted stockout date | Nullable |
| confidence | High, Medium, Low, Blocked |
| explanation | Snapshot alasan |
| backtest metrics | MAE/WAPE/Bias jika tersedia |

Forecast lama tidak ditimpa. Hasil historis diperlukan untuk audit dan backtest.

### 11.5 Daily forecast breakdown

Jika diperlukan untuk grafik dan stockout date, simpan atau bangun secara deterministik:

- forecast item;
- date;
- weekday baseline;
- trend factor;
- forecast demand;
- cumulative demand;
- projected stock remaining.

Keputusan menyimpan tabel terpisah atau JSON snapshot ditentukan berdasarkan query/export/performa aktual. Jangan membuat tabel tanpa consumer.

## 12. Sinkronisasi dan snapshot

### 12.1 Prinsip

- Dashboard membaca database lokal, bukan menarik seluruh histori ESB saat render.
- API fetch dilakukan oleh Job/Command/Service.
- Job idempoten berdasarkan source, branch, company, periode, dan version.
- Partial failure dicatat per source/company/page.
- Data lama tidak dihapus hanya karena source gagal pada run terbaru.
- Credential/token tidak disimpan pada payload/log.

### 12.2 Jadwal harian

Urutan konseptual setelah periode operasional selesai:

1. Sinkronkan Sales Information.
2. Sinkronkan Daily Sales Material Usage.
3. Sinkronkan Stock Movement.
4. Resolve catalog/identity/UOM.
5. Bangun/update daily metrics.
6. Tandai kelengkapan.
7. Generate forecast branch yang datanya berubah.

Waktu scheduler final ditentukan bersama Operational berdasarkan jam tutup branch dan keterlambatan data ESB.

### 12.3 Refresh stok intraday

Tahap lanjutan dapat memperbarui current stock beberapa kali sehari tanpa mengambil ulang seluruh histori. Refresh hanya mengubah snapshot current balance dan menghasilkan forecast run baru bila memang dibutuhkan.

### 12.4 Backfill

Command backfill wajib mendukung:

- dry-run;
- branch filter;
- date from/to;
- source filter;
- chunk per branch/date;
- progress;
- resume/checkpoint;
- retry aman;
- exception report;
- batas concurrency/rate limit;
- tidak menghapus snapshot valid ketika retry gagal.

Backfill production memerlukan instruksi dan approval terpisah.

## 13. Forecast engine MVP

### 13.1 Prinsip

- Deterministik: input dan algorithm version yang sama menghasilkan output sama.
- Transparan: seluruh komponen formula tersedia di detail.
- Per branch-product-UOM.
- Missing data tidak diubah menjadi nol.
- Outlier dan cap terdokumentasi.
- Model dipilih melalui backtest, bukan karena kompleksitas.

### 13.2 Training window

- Default: 8 minggu sebelum forecast cutoff.
- Weekday yang sama dibandingkan dengan weekday historis yang sama.
- Minimum jumlah observasi ditetapkan setelah Phase 0/backtest.
- History window dan minimum observations disimpan pada configuration snapshot.

### 13.3 Weekday weighted baseline

Untuk target date `D`:

```text
Weekday Baseline(D)
= Σ (historical usage pada weekday yang sama × weight recency)
  ÷ Σ weight dari observasi valid
```

Observasi gagal/tidak lengkap tidak dimasukkan dan tidak dianggap nol.

### 13.4 Trend factor

```text
Recent Average
= rata-rata usage pada periode terbaru yang valid

Comparison Average
= rata-rata usage pada periode pembanding yang valid

Raw Trend Factor
= Recent Average ÷ Comparison Average
```

Trend factor final diberi lower/upper cap terpusat agar lonjakan sesaat tidak mendominasi. Nilai cap ditentukan melalui kalibrasi dan disimpan dalam run snapshot.

Jika comparison average nol:

- recent juga nol → tidak ada demand;
- recent lebih dari nol → klasifikasi `New/Recently Sold`, bukan growth tak terbatas;
- confidence diturunkan sampai histori mencukupi.

### 13.5 Forecast demand

```text
Forecast Demand(D)
= Weekday Baseline(D) × Trend Factor

Forecast Demand Periode
= Σ Forecast Demand(D)
```

### 13.6 Waste rate

```text
Historical Waste Rate
= Total Waste
  ÷ (Sales-driven Usage + Total Waste)
```

Jika denominator nol, waste rate `not_available`.

```text
Expected Waste
= Forecast Demand × Capped Historical Waste Rate
```

Waste cap terpusat dan dikalibrasi; raw serta capped rate sama-sama disimpan agar hasil dapat dijelaskan.

### 13.7 Safety buffer

MVP tanpa Settings Page menggunakan default config terpusat:

```text
Safety Buffer
= Forecast Demand × Default Buffer Percentage
```

Nilai default tidak boleh tersembunyi. UI detail dan export menampilkan persentase/quantity buffer serta sumber `global default`.

Settings per branch/product merupakan phase lanjutan setelah kebutuhan terbukti.

### 13.8 Available stock

- Gunakan latest validated `qtyBalance` untuk location scope yang disepakati.
- Simpan observed timestamp.
- Jika stock stale melampaui threshold, confidence turun atau recommendation diblokir sesuai konfigurasi.
- Negative stock ditampilkan sebagai anomaly dan tidak disembunyikan.

### 13.9 Recommendation

```text
Gross Requirement
= Forecast Demand + Expected Waste + Safety Buffer
```

```text
Raw Recommendation
= Gross Requirement
  − Available Stock
  − Confirmed Inbound
```

```text
Final Recommendation
= max(0, Raw Recommendation)
```

Jika confirmed inbound tidak tersedia:

- formula memakai komponen yang tersedia;
- inbound disimpan `null`;
- explanation menyatakan belum diperhitungkan;
- confidence diturunkan;
- jangan menampilkan `0 inbound` seolah sumber telah diperiksa.

MVP tidak membulatkan ke MOQ/pack size sampai master resmi tersedia.

### 13.10 Stock coverage

```text
Average Daily Demand
= Total valid historical demand ÷ jumlah hari valid
```

```text
Stock Coverage Days
= Available Stock ÷ Average Daily Demand
```

Jika average demand nol, coverage tidak ditampilkan sebagai infinity; gunakan label `Tidak ada demand pada periode`.

Predicted stockout date menggunakan cumulative daily forecast jika tersedia.

## 14. Model comparison dan “belajar dari histori”

MVP dapat membandingkan kandidat sederhana:

1. Same weekday seasonal naive.
2. Weekday weighted average.
3. Rolling average dengan weekday factor.
4. Median weekday untuk data dengan outlier tinggi.

Backtest menjalankan tiap kandidat pada periode historis yang sudah diketahui actual-nya. Sistem memilih metode dengan error terendah hanya jika sample memenuhi minimum.

Ini merupakan adaptive forecasting yang dapat dijelaskan tanpa layanan AI eksternal.

### 14.1 Metrik backtest

```text
MAE = rata-rata |Forecast − Actual|
```

```text
WAPE
= Σ |Forecast − Actual|
  ÷ Σ Actual
```

```text
Bias
= Σ (Forecast − Actual)
  ÷ Σ Actual
```

- Bias positif: cenderung over-forecast.
- Bias negatif: cenderung under-forecast.
- WAPE dengan total actual nol menjadi unavailable.

### 14.2 Pemilihan metode

- Metode terbaik dipilih per branch-product-UOM bila sample cukup.
- Jika sample kurang, gunakan default model dan confidence rendah.
- Model/version yang dipilih disimpan pada forecast item.
- Perubahan algorithm version tidak mengubah forecast historis.

## 15. Trend dan ranking per branch

### 15.1 Prinsip

Ranking key:

```text
branch_id + periode + ranking_type
```

Tidak ada ranking produk gabungan yang mengubah recommendation per branch.

### 15.2 Jenis ranking

| Ranking | Dasar |
| --- | --- |
| Top Demand | Historical sales-driven usage |
| Trend Naik | Growth periode sekarang vs pembanding |
| Trend Turun | Penurunan terbesar |
| Waste Tertinggi | Waste rate dan quantity dengan denominator valid |
| Risiko Stockout | Predicted stockout date/coverage |
| Prioritas Order | Urgency, need, trend, coverage, dan confidence |
| Potensi Overstock | Coverage jauh di atas forecast horizon |

### 15.3 Kondisi khusus

- Previous period nol dan current lebih dari nol → `New/Recently Sold`.
- Current dan previous nol → tidak masuk trend ranking.
- Missing/partial period → ranking ditandai incomplete atau dikeluarkan.
- Quantity berbeda UOM tidak dijumlahkan sebagai satu total.
- Ranking quantity lintas UOM dipisahkan atau memakai normalized percentile, bukan raw total campuran.

### 15.4 Priority explanation

Priority score harus mempunyai explanation, misalnya:

> Prioritas Tinggi — stok diperkirakan habis dalam 2 hari, demand meningkat 18%, dan stok saat ini hanya mencukupi sebagian kebutuhan minggu depan.

Bobot score terpusat dan tersimpan pada configuration snapshot. UI tidak menampilkan angka score tanpa alasan.

## 16. Confidence dan data quality

### 16.1 Confidence

| Level | Kriteria konseptual |
| --- | --- |
| High | Histori cukup, identity/UOM valid, seluruh source lengkap/fresh, backtest memadai |
| Medium | Histori cukup tetapi ada source opsional tidak tersedia atau sebagian hari terlewat |
| Low | Produk baru, histori pendek, source partial, inbound tidak tersedia, atau error backtest tinggi |
| Blocked | Stock/demand utama tidak tersedia, identity invalid, branch mismatch, atau UOM tidak dapat dinormalisasi |

Threshold final berasal dari config/version dan dikalibrasi pada pilot.

### 16.2 Source state

Setiap branch menampilkan:

- Sales last synced at;
- Daily Usage last synced at;
- Stock Movement last synced at;
- periode data lengkap;
- jumlah source/company gagal;
- status `Fresh`, `Stale`, `Partial`, atau `Failed`.

### 16.3 Zero vs missing

| Kondisi | Tampilan |
| --- | --- |
| Source sukses, quantity 0 | `0` |
| Source tidak mengembalikan product pada kontrak yang membuktikan nol | `0`, jika dapat dibuktikan |
| Request gagal | `Tidak tersedia` |
| Mapping invalid | `Mapping belum lengkap` |
| Stock terlalu lama | `Stok stale` |
| Forecast diblokir | `Tidak dapat dihitung` + alasan |

## 17. Information architecture dan navigation

Navigation target:

```text
Analytics
├── Sales Information
├── Ordering Recommendation
├── Promotion Information
└── Stock Information
```

Ordering Recommendation:

- navigation label: `Ordering Recommendation`;
- page title: `Ordering Recommendation`;
- navigation group: `Analytics`;
- slug: `ordering-recommendation` mengikuti convention Filament;
- icon: gunakan Heroicon yang konsisten untuk chart/order recommendation;
- sidebar Analytics terbuka ketika route aktif;
- launcher/sidebar visibility memakai permission server-side yang sama.

Jangan mengubah route atau behavior Sales Information existing untuk menambahkan halaman ini.

## 18. UI — adopsi pola Sales Information

UI harus terasa sebagai saudara dari Sales Information, tetapi lebih ringkas dan server-driven.

### 18.1 Pola yang diadopsi

- Filter utama pada bagian atas.
- Aksi analisis/export dekat filter.
- KPI cards setelah filter.
- Grafik tren dan perbandingan.
- Ranking produk.
- Tabel detail.
- Drill-down/detail.
- Status loading/fetch progress yang jelas.
- Export lengkap mengikuti filter.

### 18.2 Pola yang tidak disalin mentah

- Jangan menyalin seluruh Alpine state dan export dataset ke browser.
- Jangan menyimpan seluruh histori pada public Livewire properties.
- Jangan melakukan request ESB page-by-page dari interaksi render normal.
- Jangan membuat satu Blade besar dengan seluruh kalkulasi/export.
- Jangan membuat XLSX besar sepenuhnya di browser.
- Jangan menggunakan menu name sebagai satu-satunya identity.

### 18.3 Urutan halaman

1. Header ringkas.
2. Filter analisis.
3. Data freshness/completeness strip.
4. KPI.
5. Overview/summary branch.
6. Grafik demand dan forecast.
7. Ranking per branch.
8. Tabel recommendation.
9. Pagination.
10. Modal/detail drawer recommendation.

### 18.4 Filter

Filter minimum:

- Brand;
- Branch multi-select;
- periode tampilan: 7 hari, 30 hari, custom;
- forecast horizon: 7 atau 14 hari;
- category;
- confidence;
- stock risk;
- search product.

Aturan:

- Branch options sudah scoped kepada user.
- Brand mengubah pilihan branch secara server-side.
- Pilihan beberapa branch tidak menggabungkan result calculation.
- Filter reset pagination.
- Filter tetap tersedia setelah empty/error state.
- Default branch mengikuti accessible/primary branch bila sesuai convention.

### 18.5 Aksi analisis

Karena snapshot dan forecast dibuat oleh scheduler, aksi utama tidak perlu memanggil live ESB. Pilihan UI:

- **Tampilkan Analisis** untuk menerapkan filter; atau
- data otomatis berubah setelah filter dengan submit eksplisit untuk menghindari request per field.

Normal user tidak melihat **Refresh ESB**. Jika refresh manual ditambahkan, gunakan permission dan background job terpisah serta tampilkan status queued/running.

### 18.6 Freshness strip

Section kecil menampilkan:

- last updated;
- forecast generated at;
- history window;
- data completeness;
- source warnings;
- algorithm version.

Tone:

- Green: fresh/complete.
- Amber: partial/stale.
- Red: failed/blocked.
- Gray: belum ada snapshot.

### 18.7 KPI

KPI minimum:

- Branch dianalisis.
- Produk dianalisis.
- Produk perlu order.
- Risiko stockout.
- Tren naik.
- Waste tinggi.
- Data tidak lengkap.

Jangan menampilkan total quantity lintas UOM.

### 18.8 Branch overview

Jika lebih dari satu branch dipilih, tampilkan kartu/row per branch:

- branch;
- jumlah SKU perlu order;
- stockout risk;
- trend naik tertinggi;
- waste tertinggi;
- overall confidence;
- source state;
- aksi **Lihat Detail Branch**.

Gunakan border/radius tanpa shadow biasa sesuai UI consistency.

### 18.9 Grafik

Grafik minimum:

1. Actual Demand vs Forecast per tanggal.
2. Historical Demand Trend.
3. Stock Coverage distribution.
4. Top Trend Naik.
5. Top Waste.

Grafik harus:

- mempunyai unit/axis yang jelas;
- tidak mencampur raw quantity berbeda UOM dalam satu total;
- menyediakan fallback tabel/label;
- destroy/reinitialize instance secara aman ketika filter berubah;
- tidak membawa dataset tak terbatas ke browser;
- membatasi top-N untuk visual dan menyediakan tabel lengkap terpisah.

### 18.10 Ranking per branch

Gunakan tab/selector ranking:

- Prioritas Order;
- Top Demand;
- Trend Naik;
- Trend Turun;
- Waste;
- Stockout Risk.

Ranking selalu menunjukkan branch aktif. Saat banyak branch dipilih, user memilih branch pada selector atau membuka kartu branch; jangan menampilkan daftar panjang bercampur tanpa group.

### 18.11 Tabel recommendation

Kolom baseline:

| Kolom | Isi |
| --- | --- |
| Rank | Peringkat dalam branch/filter |
| Produk | Code, name, category |
| UOM | UOM hasil |
| Demand Historis | Period total/average |
| Tren | Percentage dan label |
| Waste | Quantity/rate |
| Stok | Latest balance dan timestamp |
| Coverage | Days |
| Forecast | Horizon demand |
| Recommendation | Final quantity |
| Confidence | Badge + label |
| Aksi | Lihat Detail |

Tabel memakai database pagination. Kolom sekunder dipindahkan ke detail agar tidak memaksa scroll lebar.

### 18.12 Detail recommendation

Detail menampilkan:

- branch/company/ESB branch;
- Product Detail/Product Code/name/category/UOM;
- history dan forecast range;
- actual usage per hari;
- weekday samples dan weights;
- raw/capped trend factor;
- historical/expected waste;
- buffer;
- available stock dan observed time;
- inbound value atau alasan tidak tersedia;
- gross/raw/final recommendation;
- coverage dan stockout date;
- algorithm version;
- backtest metrics;
- confidence reasons;
- source completeness/warnings.

Detail boleh berupa modal lebar atau halaman detail ringan, mengikuti kemampuan komponen existing. Jika modal dipakai, wajib mengikuti aturan focus, Escape, backdrop, body scroll, dan return focus.

### 18.13 Responsive dan accessibility

- Viewport: 360, 390, 768, 1024, 1440 px dan zoom 200%.
- Filter satu kolom di mobile.
- KPI grid responsif.
- Halaman tidak mengalami horizontal scroll global.
- Tabel boleh scroll dalam container.
- Nama produk/kode/warning panjang wrap.
- Icon-only button mempunyai accessible name.
- Warna bukan satu-satunya penanda trend/confidence.
- Loading, empty, stale, partial, error, blocked, dan zero state berbeda.

## 19. Export XLSX

Export mengikuti seluruh filter aktif serta branch scope. XLSX dibuat server-side dengan streaming/chunking untuk dataset besar.

### 19.1 Sheet Summary

- Generated At.
- History Range.
- Forecast Range.
- Algorithm Version.
- Branch.
- Data Completeness.
- KPI counts.

### 19.2 Sheet Recommendations

Kolom baseline:

1. Branch.
2. Company Code.
3. ESB Branch Code.
4. Product Detail ID.
5. Product Code.
6. Product Name.
7. Category.
8. UOM.
9. Historical Demand.
10. Average Daily Demand.
11. Trend Percentage.
12. Trend Label.
13. Waste Quantity.
14. Waste Rate.
15. Available Stock.
16. Stock Observed At.
17. Stock Coverage Days.
18. Forecast Demand.
19. Expected Waste.
20. Safety Buffer.
21. Confirmed Inbound.
22. Gross Requirement.
23. Recommended Quantity.
24. Predicted Stockout Date.
25. Confidence.
26. Explanation.
27. Source State.

### 19.3 Sheet Daily History

- Branch/company/product/UOM.
- Date.
- Sales-driven usage.
- POS validation usage.
- Waste.
- Goods in.
- Ending stock.
- Completeness flags.

### 19.4 Sheet Data Quality

- Branch.
- Company/source.
- Period.
- Status.
- Missing days/pages.
- Last sync.
- Error summary aman.

### 19.5 Export guardrails

- Product/Sales codes diperlakukan sebagai string.
- Leading zero dipertahankan.
- Text cell berawalan `=`, `+`, `-`, `@` disanitasi.
- Data di luar branch scope tidak ikut.
- Empty result tetap menghasilkan workbook/header valid.
- Filename memuat history/forecast range.
- Jangan mengekspor token, raw credential, stack trace, atau payload sensitif.
- Untuk export sangat besar, gunakan queued export dan expiry sesuai pola repository.

## 20. Arsitektur implementasi

Sesuai `docs/standards/code-remediation.md`, Page tidak boleh menjadi tempat integrasi, kalkulasi, export, dan query besar sekaligus.

```text
Scheduled Command / Scheduler
    → Dispatch per Branch/Company/Date Jobs
        → Sales Integration Service
        → Daily Usage Integration Service
        → EsbStockMovementService
        → Daily Metric Builder

Forecast Job / Action
    → OrderingForecastCalculator
    → OrderingBacktestService
    → OrderingConfidenceCalculator
    → Persist Forecast Run + Items

OrderingRecommendationPage
    → OrderingRecommendationQuery
    → Presenter/ViewModel
    → Server-side pagination/filter

Export Action
    → Same scoped query contract
    → Streaming XLSX
```

### 20.1 Kandidat class

Nama final mengikuti convention sibling:

- `OrderingRecommendationPage`;
- `OrderingRecommendationQuery`;
- `OrderingDemandSnapshotService`;
- `OrderingStockSnapshotService`;
- `OrderingDailyMetricBuilder`;
- `OrderingForecastCalculator`;
- `OrderingBacktestService`;
- `OrderingConfidenceCalculator`;
- `SyncOrderingDailyMetricsJob` atau jobs per source;
- `GenerateOrderingForecastJob`;
- `BackfillOrderingMetricsCommand`;
- `ExportOrderingRecommendationsXlsxAction`.

Jangan membuat interface/repository/DTO untuk setiap class tanpa kebutuhan konkret.

### 20.2 Batas tanggung jawab

| Lapisan | Tanggung jawab |
| --- | --- |
| Page | Filter, pagination, selected branch, modal state, feedback |
| Query | Scoped database query, sorting, aggregation read model |
| Integration service | ESB request/response validation dan error mapping |
| Metric builder | Normalisasi identity/UOM dan daily fact |
| Calculator | Formula murni/reusable tanpa Filament |
| Backtest service | Candidate evaluation dan metrics |
| Job | Batch orchestration, retry, timeout, lock |
| Export action | Streaming workbook dari scoped query |
| Blade | Presentasi; tidak query/HTTP/kalkulasi domain |

### 20.3 Reuse

- Reuse `EsbCoreClient` untuk endpoint Core yang sudah didukung.
- Reuse `EsbStockMovementService` tanpa inheritance/copy.
- Ekstrak consumer reusable dari Sales Information bila integration logic masih berada terlalu dekat dengan Page.
- Jangan membuat Ordering Recommendation membaca public properties atau cache accumulator Sales Information.
- Reuse branch scope/helper yang sudah teruji.

## 21. Job, concurrency, dan idempotency

- Satu lock per source-branch-company-date.
- Job retry hanya untuk error yang aman; validation error tidak diretry tanpa perubahan.
- `tries`, `timeout`, dan `backoff` eksplisit.
- API pagination lengkap sebelum source dinyatakan Completed.
- Partial page/source tidak menimpa metric lengkap dengan nol.
- Metric upsert berdasarkan natural identity dan input version.
- Forecast run mempunyai unique/idempotency key untuk branch, cutoff, horizon, algorithm version, dan configuration hash.
- Forecast job membaca snapshot cutoff yang tetap agar hasil reproducible.
- Job failure mencatat error aman dan melepaskan lock.

## 22. Performa dan caching

- Jangan mengambil histori live ketika dashboard dibuka.
- Query menggunakan date/branch/product indexes yang dibuktikan dari pola query/`EXPLAIN`.
- Gunakan database pagination.
- KPI menggunakan aggregate query, bukan memuat seluruh row.
- Detail harian dimuat saat dibuka.
- Grafik mendapat dataset yang sudah diagregasi dan dibatasi.
- Cache opsional menyertakan user scope/branch/filter/forecast run ID.
- Cache invalidation terjadi ketika forecast run baru completed.
- Jangan cache response personal tanpa identity dan permission context.
- Rekam baseline query count, response time, memory, dan payload Livewire.

## 23. Security dan privacy

- Semua Page, query, detail, export, dan refresh authorize server-side.
- Branch ID dari request selalu dibatasi accessible branches.
- Company/ESB branch context tidak diterima mentah tanpa mapping lokal.
- Error UI tidak menampilkan credential/token/raw stack trace.
- Snapshot raw payload diminimalkan; simpan hanya field yang dibutuhkan untuk audit.
- Export mengikuti permission dan tidak mengandung credential.
- Scheduler/command administratif tidak tersedia melalui route publik.

## 24. Observability

Pantau:

- sync duration per source/branch;
- rows fetched/stored;
- pagination count;
- API failure/rate limit/timeout;
- partial/stale branches;
- forecast duration;
- number High/Medium/Low/Blocked;
- backtest WAPE/Bias distribution;
- dashboard latency/query count;
- export duration/rows/failure;
- backlog dan failed jobs.

Correlation ID menghubungkan sync run, job, metric, dan monitoring external call bila infrastructure existing mendukungnya.

## 25. Test strategy

### 25.1 Integration contract tests

- Pagination Sales, Usage, dan Stock Movement.
- Auth/token/company context.
- Branch mismatch ditolak.
- Invalid response format.
- Timeout/429/5xx behavior.
- Partial company/page.
- Empty success dibedakan dari failure.
- Credential tidak muncul pada error/log.

### 25.2 Daily metric tests

- Multi-company satu branch.
- Product identity terpisah per company.
- Canonical mapping valid.
- Nama sama tanpa mapping tidak digabung.
- UOM sama digabung sesuai aturan.
- UOM berbeda tanpa conversion diblokir.
- Latest stock per location benar.
- Historical balance tidak dijumlahkan.
- Waste terpisah dari sales usage.
- POS Sales tidak double counted.
- Idempotent upsert/rerun.

### 25.3 Forecast unit tests

- Weekday baseline.
- Recency weighting.
- Trend naik/turun.
- Trend cap.
- Previous period nol.
- Missing observations.
- New product.
- Waste rate denominator nol.
- Waste cap.
- Buffer.
- Available stock deduction.
- Inbound null vs zero.
- Negative raw recommendation menjadi nol.
- Stock coverage.
- Predicted stockout date.
- Confidence High/Medium/Low/Blocked.
- Same input/version menghasilkan output sama.

### 25.4 Backtest tests

- MAE.
- WAPE.
- Bias.
- Actual total nol.
- Pemilihan candidate method.
- Minimum sample.
- Forecast historis tidak berubah saat algorithm version baru ditambahkan.

### 25.5 Authorization tests

- Navigation sesuai permission.
- User branch terbatas.
- User access all branches.
- Manipulasi filter branch ID.
- Modal/detail di luar scope.
- Export di luar scope.
- Manual refresh permission bila dibuat.

### 25.6 Page/UI tests

- Default filter.
- Multi-branch grouped result.
- Filter reset pagination.
- Loading/empty/stale/partial/error/blocked/zero states.
- KPI tidak menjumlahkan mixed UOM.
- Ranking benar-benar per branch.
- Detail explanation.
- Dataset grafik dibatasi.
- Responsive layout dan keyboard modal.

### 25.7 Export tests

- Filter dan branch scope.
- Multi-sheet/header.
- Leading zero.
- Formula injection.
- Empty result.
- More than one chunk.
- Mixed UOM tetap terpisah.
- Data quality sheet.

### 25.8 Command/job tests

- Dry-run tidak mutasi.
- Branch/date/source filter.
- Retry/idempotency.
- Resume/checkpoint.
- Concurrent lock.
- Partial failure tidak menghapus good data.
- Forecast dispatch hanya setelah metric siap.

## 26. Phase implementasi

### Phase 0 — Kontrak bisnis dan data

- Tetapkan kategori produk orderable Store dari Kitchen.
- Verifikasi Daily Sales Material Usage response aktual.
- Verifikasi stable identity pada Sales Information.
- Tetapkan Stock Movement location scope.
- Validasi makna arah `Goods Delivery`, `Goods Receipt`, `POS Sales`, dan `Waste`.
- Tentukan apakah confirmed inbound tersedia.
- Audit UOM dan canonical product mapping.
- Ambil sample beberapa branch minimal beberapa minggu.

**Output:** data contract, eligibility rule, identity strategy, source completeness rule, dan daftar blocker.

**Gate:** tidak ada komponen formula yang masih memakai arti transaksi tebakan.

### Phase 1 — Safety net dan integration boundary

- Tambah characterization tests untuk service existing.
- Pisahkan integration logic reusable dari Sales Information Page bila diperlukan.
- Pertahankan behavior Sales Information.
- Standarkan error/result per source.

**Gate:** source dapat dipanggil tanpa Page dan seluruh request dapat di-fake/test.

### Phase 2 — Schema dan daily snapshot

- Buat migration additive, models, factories, scopes, dan indexes.
- Buat sync run dan daily metrics.
- Implement job per source dan metric builder.
- Implement dry-run/backfill command.
- Tambahkan data completeness.

**Gate:** histori per branch-product-date dapat dibangun idempoten.

### Phase 3 — Forecast engine v1

- Implement baseline weekday.
- Trend factor/cap.
- Waste rate/cap.
- Buffer.
- Stock deduction.
- Coverage/stockout.
- Confidence/explanation.
- Persist forecast runs/items.

**Gate:** calculator unit tests lengkap dan result reproducible.

### Phase 4 — Backtest

- Implement actual matching.
- MAE/WAPE/Bias.
- Candidate comparison.
- Minimum sample/fallback.
- Simpan method dan metrics.

**Gate:** akurasi dapat dilihat per branch-product, bukan hanya asumsi.

### Phase 5 — Dashboard

- Navigation/permission.
- Filter dan scoped query.
- Freshness strip.
- KPI.
- Branch overview.
- Grafik.
- Ranking.
- Tabel/pagination.
- Detail modal/page.
- Responsive/accessibility.

**Gate:** seluruh UI membaca snapshot lokal dan tidak menjalankan HTTP ESB saat render.

### Phase 6 — Export

- Streaming XLSX multi-sheet.
- Filter/scope sama dengan UI.
- Large dataset tests.

**Gate:** export dapat diaudit dan tidak bocor lintas branch.

### Phase 7 — Staging backfill dan UAT

- Jalankan dry-run sample.
- Backfill 2–3 branch pilot.
- Generate forecast.
- Verifikasi source/identity/UOM.
- Bandingkan manual terhadap Sales/Stock report.
- Review mobile/desktop.

**Gate:** data dan formula disetujui Operational/Kitchen sebelum production.

### Phase 8 — Pilot production terbatas

- Release view-only.
- Backfill terbatas dengan approval.
- Pantau minimal 4 minggu.
- Bandingkan forecast vs actual.
- Catat stockout/waste/emergency order di proses operasional.

**Gate:** baseline akurasi dan kegunaan tersedia.

### Phase 9 — Pengembangan berikutnya

Hanya setelah pilot:

- setting per branch/product;
- MOQ/pack size;
- lead time/order calendar;
- confirmed inbound;
- promo/event/payday/holiday;
- manual override dengan alasan;
- Draft Store–Kitchen Order;
- Kitchen fulfillment workflow;
- notifikasi;
- model forecasting lanjutan.

## 27. UAT scenarios

1. Satu branch dengan seluruh data lengkap.
2. Satu branch dengan beberapa company mapping.
3. Beberapa branch dipilih; ranking tetap terpisah.
4. Produk tren naik/turun.
5. Produk baru.
6. Produk demand nol.
7. Waste tinggi.
8. Stock nol, negatif, dan stale.
9. UOM mismatch.
10. Daily Usage gagal tetapi Stock sukses.
11. Salah satu company gagal.
12. Inbound unavailable.
13. Forecast 7 dan 14 hari.
14. User branch terbatas.
15. User all branches.
16. Export dengan filter aktif.
17. Detail menjelaskan formula yang sama dengan tabel.
18. Backtest forecast lama terhadap actual.

## 28. Acceptance criteria

### Navigation dan akses

- [ ] Menu tampil di `Analytics → Ordering Recommendation`.
- [ ] Navigation dan Page memakai permission yang sama.
- [ ] User hanya melihat accessible branches.
- [ ] Filter/detail/export tidak dapat melewati branch scope.

### Data

- [ ] Dashboard membaca snapshot lokal.
- [ ] Sync source idempoten dan partial failure tercatat.
- [ ] Missing data tidak dianggap nol.
- [ ] Multi-company dan UOM tidak digabung tanpa mapping valid.
- [ ] Historical stock balance tidak dijumlahkan sebagai available stock.
- [ ] Demand tidak double counted antara Daily Usage dan POS Sales.

### Forecast

- [ ] Recommendation dihitung per branch-product-UOM.
- [ ] Weekday/trend/waste/buffer/stock terlihat pada detail.
- [ ] Inbound unavailable ditampilkan sebagai unavailable.
- [ ] Final recommendation tidak negatif.
- [ ] Confidence dan explanation tersedia.
- [ ] Algorithm/config version tersimpan.
- [ ] Forecast dapat dibandingkan dengan actual.

### Ranking

- [ ] Ranking dipisahkan per branch.
- [ ] Top Demand, Trend Naik/Turun, Waste, Stockout, dan Priority tersedia.
- [ ] Produk baru tidak mendapat persentase tak terbatas.
- [ ] Mixed UOM tidak dijumlahkan.

### UI

- [ ] Struktur visual serupa Sales Information.
- [ ] UI mengikuti `docs/standards/ui-consistency.md`.
- [ ] Tidak ada shadow pada kartu/section biasa.
- [ ] Mobile tidak mengalami horizontal scroll seluruh halaman.
- [ ] Loading, empty, stale, partial, error, blocked, dan zero berbeda.
- [ ] Grafik tidak memuat dataset tak terbatas.
- [ ] Modal/detail accessible.

### Export

- [ ] Export mengikuti filter dan branch scope.
- [ ] XLSX memakai streaming/chunking.
- [ ] Leading zero dan formula injection ditangani.
- [ ] Empty result valid.
- [ ] Data Quality tersedia.

### Quality

- [ ] Characterization, unit, feature, job, authorization, export, dan UI tests lulus.
- [ ] Pint, `git diff --check`, route/Filament discovery, dan build terkait lulus.
- [ ] Query/payload/performance baseline dibandingkan.
- [ ] Tidak ada perubahan unrelated.

## 29. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Menu tidak memiliki stable identity | Resolve catalog; jangan memakai nama sebagai identity final |
| Daily Usage dan POS Sales double counted | Satu demand source utama; POS hanya validasi |
| Waste abnormal menaikkan forecast | Raw/capped rate dan backtest |
| Stok gagal dianggap nol | Missing/Blocked state |
| Latest balance salah | Latest per location/product/UOM, bukan sum historical |
| Produk lintas company terduplikasi | Canonical mapping eksplisit |
| UOM berbeda dijumlahkan | Conversion validation atau Blocked |
| Goods Delivery dianggap inbound aktif | Gunakan hanya setelah status/arah terbukti |
| Produk baru tidak punya histori | Low confidence/fallback transparan |
| Live API memperlambat dashboard | Snapshot + queue + local query |
| Forecast dianggap keputusan pasti | Confidence dan explanation |
| Export membebani memory | Streaming/chunk/queue bila perlu |
| Page menjadi besar seperti Sales Information | Query/Calculator/Job/Export dipisahkan |
| Cache bocor lintas branch | Cache key dan authorization context |
| Model kompleks tetapi tidak lebih akurat | Candidate backtest dan versioning |

## 30. Keputusan yang harus dikonfirmasi pada Phase 0

1. Kategori/daftar produk apa saja yang benar-benar dipesan Store dari Kitchen?
2. Stock location mana yang mewakili stok tersedia Store?
3. Apakah Daily Sales Material Usage mengembalikan Product Detail ID atau hanya Product Code?
4. Apakah `salesMenus` mempunyai Menu ID/Code stabil pada response production?
5. Bagaimana hubungan menu terjual dengan WIP/orderable product?
6. Apa arti arah `Goods Delivery` pada branch Store?
7. Adakah endpoint/status untuk confirmed inbound yang belum diterima?
8. UOM recommendation yang diharapkan Store: stock, transfer, sales, atau UOM lain?
9. Jam data dianggap final untuk hari sebelumnya?
10. Branch mana yang menjadi pilot?

Jawaban ini memengaruhi kontrak data dan formula. Jangan menebaknya dalam implementasi.

## 31. Quality gates

Setiap phase implementasi kode wajib menjalankan yang relevan:

1. Baca `AGENTS.md` dan dokumen sumber.
2. Audit dirty worktree dan pertahankan perubahan user.
3. Gunakan Laravel Boost `search-docs` sebelum code change.
4. Gunakan `database-schema` sebelum migration/index.
5. Gunakan generator Laravel untuk file baru bila tersedia.
6. Tambah characterization test sebelum refactor.
7. Jalankan focused Pest tests setelah setiap phase.
8. Jalankan `vendor/bin/pint --dirty --format agent` bila PHP berubah.
9. Jalankan `git diff --check`.
10. Jalankan build bila frontend source berubah.
11. Periksa route/Filament discovery.
12. Review visual viewport dan accessibility.
13. Ukur query/payload/latency dan job duration.
14. Fake ESB; jangan melakukan mutation eksternal pada test.
15. Jangan commit, push, migrate production, backfill production, atau deploy tanpa instruksi terpisah.

## 32. Rollout dan rollback

### Rollout

1. Deploy additive schema dan code dalam kondisi feature tersembunyi bila diperlukan.
2. Jalankan sync/backfill staging.
3. Verifikasi sample branch/product/UOM.
4. Generate/backtest staging.
5. UAT UI/export.
6. Beri permission pada role pilot.
7. Jalankan production backfill terbatas dengan approval.
8. Buka view-only untuk pilot.
9. Pantau data quality dan forecast minimal 4 minggu.

### Rollback

- Sembunyikan navigation/permission tanpa menghapus snapshot.
- Hentikan scheduler/job ordering tanpa mengganggu Sales Information/Stock Card.
- Forecast/snapshot baru tetap disimpan untuk audit kecuali ada instruksi penghapusan terpisah.
- Migration down production tidak dijadikan rollback utama.
- Tidak ada order eksternal yang perlu dibatalkan karena MVP view-only.

## 33. Definition of Done

MVP dianggap selesai hanya ketika:

- halaman tersedia di Analytics dan secara visual selaras dengan Sales Information;
- hasil dihitung per branch-product-UOM;
- source history tersimpan lokal secara idempoten;
- demand, trend, waste, stock, recommendation, confidence, dan data quality dapat dijelaskan;
- ranking benar-benar dipisahkan per branch;
- missing/stale/partial tidak dianggap nol;
- multi-company/UOM aman;
- forecast versioned dan dapat dibandingkan dengan actual;
- export aman serta scoped;
- permission dan branch isolation teruji;
- UI responsif dan accessible;
- test serta quality gates lulus;
- staging UAT disetujui;
- deployment/backfill production belum dilakukan tanpa instruksi eksplisit.

Penyelesaian PRD ini sendiri tidak mencakup implementasi kode, migration, backfill, permission sync, commit, push, atau deployment.
