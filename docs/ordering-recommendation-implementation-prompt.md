# Prompt Implementasi — Analytics: Ordering Recommendation per Branch

Salin seluruh prompt berikut ke coding agent dari root repository Help Bloomery.

```text
Implementasikan fitur `Analytics → Ordering Recommendation` secara end-to-end berdasarkan dokumen berikut:

- `docs/ordering-recommendation-prd.md` — sumber requirement produk utama dan terbaru untuk scope ini.
- `docs/code-remediation-prd.md` — guardrail arsitektur, keamanan data, integrasi, performa, testing, dan rollout.
- `docs/ui-consistency-prd.md` — guardrail tampilan, responsive behavior, form, modal, tabel, state, dan accessibility.
- UI dan perilaku aktual `Sales Information` — referensi hierarki visual, filter, KPI, grafik, ranking, dan export; bukan sumber untuk menyalin kelemahan implementasinya.

Kerjakan sampai implementasi aman, teruji, siap UAT staging, dan dapat di-review. Jangan berhenti pada planning, skeleton, contoh kode, atau TODO untuk bagian yang kontraknya sudah dapat dibuktikan dari repository/API sample. Kondisi repository aktual adalah fakta utama. PRD Ordering Recommendation mengalahkan asumsi atau dokumen lama untuk scope ini.

## 1. Outcome produk yang wajib dicapai

1. Tersedia halaman baru `Analytics → Ordering Recommendation`.
2. Halaman bersifat view-only decision support. Fitur tidak membuat, menyetujui, atau mengirim Store–Kitchen Order.
3. Recommendation dihitung terpisah untuk setiap branch, produk, dan UOM.
4. Sumber demand utama adalah Daily Sales Material Usage apabila kontraknya terbukti lengkap.
5. Sales Information digunakan sebagai konteks tren/validasi, bukan ditambahkan lagi sehingga demand terhitung ganda.
6. Stock Movement digunakan untuk current available stock, waste, dan konteks movement yang relevan.
7. Dashboard membaca snapshot database lokal. Halaman tidak mengambil histori ESB secara live ketika render atau filter berubah.
8. Forecast default memakai histori delapan minggu dan horizon tujuh hari; horizon empat belas hari juga tersedia.
9. Kalkulasi awal deterministik, versioned, dapat dijelaskan, dan dibandingkan dengan actual melalui backtest.
10. Output utama meliputi demand, tren, waste, safety buffer, available stock, confirmed inbound bila tersedia, gross requirement, recommended quantity, stock coverage, predicted stockout, confidence, dan explanation.
11. Missing, zero, partial, stale, failed, invalid mapping, dan blocked harus dibedakan.
12. Multi-company dan UOM tidak boleh digabung tanpa identity/conversion mapping yang sah.
13. Setiap branch memiliki overview dan ranking sendiri: Prioritas Order, Top Demand, Trend Naik, Trend Turun, Waste, dan Stockout Risk.
14. UI serupa Sales Information dalam hierarki, tetapi server-driven, ringan, responsif, dan mengikuti `ui-consistency-prd.md`.
15. Export XLSX dibuat server-side dan mengikuti filter, permission, serta branch scope yang sama dengan UI.
16. Seluruh sync, snapshot, forecast, dan backfill bersifat idempotent, observable, dan aman di-retry.
17. Tidak ada mutation ESB pada fitur MVP.

## 2. Aturan kerja wajib

1. Baca `AGENTS.md` dan seluruh dokumen sumber sampai selesai sebelum mengubah kode.
2. Aktifkan skill proyek yang relevan di `**/skills/**`, termasuk Laravel, Pest, dan Tailwind bila tersedia.
3. Audit `git status --short --branch`, `git diff --stat`, dan diff file terkait. Pertahankan seluruh perubahan user; jangan menimpa atau memasukkan perubahan unrelated.
4. Gunakan Laravel Boost sebagai pilihan utama untuk application info, schema, log, URL, dan dokumentasi versi paket.
5. Sebelum code change, gunakan Laravel Boost `search-docs` dengan beberapa query broad dan package scope relevan. Verifikasi syntax Laravel 13, Filament 5, Livewire 4, Pest 4, queue, scheduler, authorization, pagination, export, dan testing.
6. Gunakan `database-schema` sebelum merancang migration, model, constraint, index, relation, atau query. Gunakan `database-query` hanya untuk inspeksi read-only.
7. Gunakan `rg`/`rg --files` untuk pencarian kode. Baca sibling files sebelum membuat Page, Model, Action, Service, Job, Command, Query, Export, view, factory, seeder, atau test.
8. Gunakan `php artisan make:* --no-interaction` untuk file Laravel baru bila generator tersedia.
9. Jangan mengubah migration lama yang mungkin sudah dijalankan. Buat migration additive dan reversible. Jangan mencampur DDL dan backfill data dalam satu migration.
10. Jangan menambah dependency, framework frontend, chart library, atau package Excel baru tanpa persetujuan user. Reuse dependency existing.
11. Jangan mengubah behavior, route, atau output Sales Information existing kecuali ekstraksi internal benar-benar diperlukan dan sudah dilindungi characterization test.
12. Jangan melakukan HTTP ESB dari Blade, render Livewire, accessor/model event, atau database transaction.
13. Jangan query database atau menjalankan kalkulasi domain di Blade.
14. Jangan menyimpan seluruh histori atau tabel recommendation ke public Livewire/Alpine state. Gunakan server-side query, pagination, aggregation, dan lazy detail.
15. Jangan menggunakan product name sebagai identity. Jangan menggabungkan produk lintas company/UOM hanya karena namanya sama.
16. Jangan menganggap API gagal sebagai quantity nol.
17. Jangan menjumlahkan historical `qtyBalance` untuk memperoleh current stock.
18. Jangan menambahkan POS Sales ke Daily Usage jika Daily Usage sudah menjadi demand utama.
19. Jangan menganggap seluruh Goods Delivery sebagai confirmed inbound tanpa bukti status, arah, branch tujuan, identity, dan outstanding quantity.
20. Jangan hard-code credential, token, URL production, branch ID, user ID, role ID, company code, ESB branch code, location, category, atau UOM di Page/Blade.
21. Seluruh akses navigation, Page, query, detail, export, command administratif, dan manual refresh wajib authorize server-side.
22. Seluruh branch input dari browser wajib di-intersect dengan accessible branches user. `access_all_branches` tetap mengikuti mekanisme existing.
23. Seluruh job external-read mempunyai timeout, retry policy yang aman, backoff, lock, error mapping, correlation ID, dan `failed()` mengikuti convention repository.
24. Setiap phase wajib mempunyai focused tests. Jangan menghapus atau melemahkan test existing.
25. Fake seluruh HTTP ESB pada test dan cegah stray request. Test tidak boleh memanggil production.
26. Jika PHP berubah, jalankan `vendor/bin/pint --dirty --format agent` sebelum finalisasi.
27. Jika source frontend berubah, jalankan build/check yang dipakai repository.
28. Jalankan `git diff --check` dan focused regression tests sebelum handoff.
29. Jangan commit, push, membuka PR, deploy, menjalankan migration/backfill production, memberi permission production, atau memutasi data production tanpa instruksi eksplisit terpisah.
30. Jangan membuat dokumentasi baru kecuali diminta; update checklist/status yang memang sudah disediakan bila diperlukan.

## 3. Stop gate kontrak bisnis dan data

Sebelum membuat formula, schema final, atau menjalankan backfill, jawab dengan bukti repository, response API development/staging yang aman, atau keputusan user untuk hal berikut:

1. kategori/allowlist produk yang benar-benar dapat dipesan Store dari Kitchen;
2. stock location yang mewakili available stock Store;
3. apakah Daily Sales Material Usage menyediakan Product Detail ID atau hanya Product Code;
4. stable Menu ID/Code pada Sales Information;
5. hubungan menu terjual dengan WIP/orderable product;
6. arti arah Goods Delivery/Goods Receipt pada branch Store;
7. sumber confirmed inbound yang belum diterima;
8. UOM recommendation yang diharapkan Store;
9. jam data hari sebelumnya dianggap final;
10. branch pilot.

Aturan stop gate:

- Jangan menebak semantic transaksi, identity, conversion, atau location.
- Jika jawabannya tersedia dalam kode/config/sample response yang sah, dokumentasikan bukti pada implementation report dan lanjutkan.
- Jika satu keputusan belum tersedia, hentikan hanya komponen yang bergantung padanya; implementasikan bagian aman, test, dan fondasi lain.
- Nilai yang belum tersedia harus menjadi `null/not_available` atau status `Blocked`, bukan nol.
- Jangan membuat mapping otomatis berdasarkan kemiripan nama.
- Jangan menjalankan backfill faktual dengan contract yang belum dipastikan.

## 4. Phase 0 — Audit dan baseline

### 4.1 Worktree dan peta ownership

1. Jalankan status dan diff worktree.
2. Petakan implementasi aktual untuk:
   - `SalesInformationPage` dan Blade;
   - service Sales/Daily Sales Material Usage;
   - `EsbCoreClient`, `EsbService`, dan `EsbStockMovementService`;
   - branch access, `access_all_branches`, company/ESB branch mapping;
   - Product, Product Detail, WIP, UOM, conversion, category, dan location;
   - scheduler, queue, command, failed job, logging, correlation ID;
   - permission config/seeder dan launcher/sidebar navigation;
   - XLSX/export pattern existing;
   - chart pattern/library existing;
   - related tests/factories.
3. Identifikasi perubahan user yang overlap. Jangan menyentuh domain lain.
4. Catat route/navigation contract yang harus tetap stabil.

### 4.2 Dokumentasi versi paket

Gunakan `search-docs` minimal untuk:

- Laravel queued jobs, unique jobs, `WithoutOverlapping`, retry/backoff, failed jobs, batches, scheduler `withoutOverlapping()`/`onOneServer()`;
- Laravel database transaction, lock, upsert, chunking, cursor, indexes, cache locks, streamed download, validation, policies;
- Filament 5 custom Pages, navigation authorization, tables/custom data security, filters/actions, testing;
- Livewire 4 pagination, computed/lazy state, locked identifiers, loading state, events, modal behavior, testing;
- Pest 4 feature/unit/browser tests, queue fakes, HTTP fakes, datasets;
- Tailwind CSS 4 responsive grid, overflow, focus, dark variants, and accessibility patterns if UI changes.

### 4.3 Schema dan data sample

1. Gunakan `database-schema` summary, lalu detail untuk tabel branch, company mapping, product/WIP/UOM, permission, dan tabel sumber lokal yang relevan.
2. Gunakan read-only query untuk memeriksa sample/volume lokal bila membantu.
3. Jangan mengasumsikan schema dari nama model.
4. Verifikasi source response dengan fixture/sample yang sudah tersedia. Jika harus melakukan request development, jangan mengubah data eksternal dan jangan membuka credential di output.
5. Petakan pagination, empty response, partial response, error envelope, Product Detail ID, Product Code, UOM, transaction type, timestamps, dan company/branch context.

### 4.4 Baseline test dan performa

1. Jalankan test focused existing untuk Sales Information, Stock Movement, branch access, ESB clients, export, dan navigation.
2. Catat failure pre-existing secara terpisah; jangan memperbaiki unrelated failure tanpa scope.
3. Ukur baseline yang tersedia:
   - query count dan response time Sales Information;
   - ukuran state/payload Livewire;
   - jumlah data per branch/date/source;
   - durasi/pagination API pada fixture;
   - export pattern dan memory behavior.

### 4.5 Characterization tests wajib

Sebelum ekstraksi/refactor, pastikan test membuktikan:

1. Sales Information mempertahankan filter, scope branch, KPI, grafik, ranking, dan export existing;
2. service Daily Usage mengirim context/date/flag yang benar dan menangani pagination/error sesuai implementasi;
3. Stock Movement mempertahankan company/branch guard, pagination, latest balance, transaction types, dan UOM behavior;
4. user branch terbatas tidak dapat meminta branch lain dengan manipulasi state;
5. user `access_all_branches` dapat melihat opsi yang semestinya;
6. API timeout/429/5xx/invalid JSON/empty success dibedakan;
7. credential/token tidak muncul pada exception atau log aman.

## 5. Phase 1 — Integration boundary dan kontrak sumber

### 5.1 Prinsip

Pisahkan integrasi reusable dari Page. Jangan membuat Ordering Recommendation bergantung pada public properties, Alpine accumulator, atau cache internal Sales Information.

Alur target:

```text
ESB Client
    → domain read integration service
        → normalized source result
            → snapshot job / daily metric builder
```

### 5.2 Result source

Gunakan pola existing; jika belum ada pola yang cocok, bentuk result terstruktur secukupnya yang dapat membedakan:

- Completed;
- Partial;
- Failed;
- Empty Success;
- Validation/Mapping Error.

Result minimal membawa rows normalized, pagination/completeness metadata, safe error summary, source context, dan observed/fetched timestamp. Jangan menyimpan token atau raw sensitive headers.

### 5.3 Sales/Daily Usage

1. Reuse auth/client/pagination existing.
2. Ekstrak consumer reusable dari Page hanya bila diperlukan.
3. Pertahankan behavior Sales Information melalui tests.
4. Normalisasi setiap row dengan branch, company, ESB branch, date, source identity, product snapshot, quantity, UOM, dan completeness.
5. Daily Usage menjadi demand utama hanya setelah contract cukup.
6. Sales/menu data menjadi konteks tren dan validasi; jangan menjumlahkannya ke Daily Usage.

### 5.4 Stock Movement

1. Reuse `EsbStockMovementService`; jangan copy atau subclass tanpa alasan.
2. Pisahkan Waste, POS Sales validation, Goods In, dan Balance.
3. Available stock memakai row balance terbaru per product-location-UOM pada location scope sah.
4. Jangan menjumlahkan balance historis.
5. Perbedaan UOM/location/company dipertahankan sampai mapping tervalidasi.
6. Branch mismatch atau unknown transaction semantics menghasilkan error/Blocked, bukan asumsi.

### 5.5 Gate phase

- Seluruh source dapat dipanggil dari service/job tanpa instance Filament Page.
- Semua call dapat di-fake.
- Error/partial/empty dapat dibedakan.
- Sales Information existing tetap lulus.

## 6. Phase 2 — Schema snapshot yang additive

Gunakan nama final yang sesuai konvensi repository. Jangan membuat folder base baru tanpa persetujuan.

### 6.1 Sync runs

Buat entity untuk satu upaya sinkronisasi dengan field konseptual:

- source;
- local branch;
- company code;
- ESB branch code;
- period from/to;
- status Pending/Running/Completed/Partial/Failed;
- rows received/stored;
- started/finished timestamps;
- safe error summary;
- correlation ID;
- non-sensitive metadata.

Tambahkan index berdasarkan query nyata: source, branch, period, status, dan correlation/idempotency identity.

### 6.2 Daily product metrics

Satu row mewakili:

```text
local branch
+ company
+ ESB branch
+ metric date
+ source product identity
+ source UOM
```

Simpan minimal:

- Product Detail/Product ID/Product Code snapshot yang tersedia;
- product name/category snapshot;
- source and normalized quantities/UOM;
- sales-driven usage;
- POS Sales validation usage;
- waste quantity;
- goods-in quantity bila valid;
- ending stock dan observed timestamp;
- source completeness flags;
- sync references/input version.

Unique constraint harus merepresentasikan natural identity dan mendukung idempotent upsert. Jangan menggunakan product name sebagai bagian identity utama.

### 6.3 Forecast runs dan items

Forecast run menyimpan:

- branch;
- history from/to;
- forecast from/to;
- algorithm version;
- configuration snapshot/hash;
- data cutoff;
- status;
- completeness summary;
- generated timestamp;
- idempotency key.

Forecast item menyimpan:

- run;
- canonical/source product identity dan snapshots;
- UOM;
- historical demand;
- weekday baseline;
- trend raw/final;
- forecast demand;
- waste raw/capped/expected;
- buffer percent/quantity/source;
- stock/stock observed at;
- inbound nullable;
- gross/raw/final recommendation;
- coverage/predicted stockout;
- confidence;
- explanation/reason codes;
- selected model/version dan backtest metrics.

Forecast historis immutable: run baru tidak menimpa hasil lama.

### 6.4 Daily forecast breakdown

Simpan tabel terpisah hanya bila consumer grafik, stockout, dan export membuktikan kebutuhan. Jika JSON snapshot lebih sesuai, pastikan terindeks melalui parent, bounded, deterministic, dan mudah dites. Jangan membuat tabel tanpa consumer.

### 6.5 Model/factory

1. Buat model dengan explicit casts, fillable/guarded, typed relations, useful scopes, dan factory.
2. Enum keys memakai TitleCase.
3. Jangan membuat seeder production-like tanpa diminta. Factory cukup untuk tests.
4. Mirror default migration pada model bila convention aplikasi memerlukannya.

### 6.6 Gate phase

- Migration additive/reversible.
- Schema dan index didukung query pattern/EXPLAIN.
- Factory dapat membentuk complete, partial, stale, blocked, multi-company, dan UOM mismatch states.
- Migration dan model tests focused lulus.

## 7. Phase 3 — Identity, mapping, UOM, dan eligibility

### 7.1 Raw identity

Gunakan identity minimum:

```text
local_branch_id
+ company_code
+ esb_branch_code
+ product_detail_id atau product_code
+ UOM
```

### 7.2 Canonical mapping

1. Reuse master/mapping existing bila sudah memadai.
2. Jangan membuat canonical merge otomatis berdasarkan nama.
3. Jika mapping eksplisit belum tersedia, hasil lintas company tetap terpisah atau Blocked.
4. Mapping harus menyimpan source identity, canonical identity, source/canonical UOM, conversion factor/direction, active/effective state, dan verified timestamp bila domain existing mendukungnya.
5. Jangan membuat UI/settings mapping baru pada MVP kecuali PRD atau user menyetujuinya.

### 7.3 UOM

1. Quantity berbeda UOM tidak boleh dijumlahkan tanpa conversion valid.
2. Simpan source quantity/UOM dan normalized quantity/UOM.
3. Conversion mempunyai arah dan factor eksplisit.
4. Rekomendasi ditampilkan dalam UOM order yang sudah dikonfirmasi.
5. KPI tidak boleh menjumlahkan PCS, KG, GR, atau UOM lain menjadi satu total quantity.

### 7.4 Eligible products

Filter hanya produk aktif, identity valid, UOM valid, kategori orderable, dan branch/company context sah. Eligibility rule terpusat di config atau domain service; jangan hard-code di Page/Blade.

### 7.5 Gate phase

- Same name/different company tidak merge tanpa mapping.
- Same identity/different UOM tidak merge tanpa conversion.
- Invalid mapping menghasilkan Blocked dengan explanation.
- Semua edge case mempunyai unit tests.

## 8. Phase 4 — Sync jobs, metric builder, dan backfill

### 8.1 Jobs

Implementasikan job per source/branch/company/date atau unit kerja kecil yang sesuai volume aktual.

Wajib:

- explicit `tries`, `timeout`, dan `backoff`;
- unique/concurrency lock per source-branch-company-date;
- API pagination selesai sebelum status Completed;
- validation/mapping error tidak diretry tanpa perubahan;
- retryable timeout/429/5xx mengikuti policy client;
- `failed()` mencatat status/error aman;
- token/credential tidak masuk serialized payload/log;
- partial page/source tidak menimpa good metric dengan nol.

### 8.2 Daily metric builder

1. Input hanya normalized snapshot/result.
2. Upsert berdasarkan natural identity dan input version.
3. Waste dipisahkan dari sales usage.
4. POS Sales hanya validasi bila Daily Usage menjadi demand.
5. Available stock menggunakan latest valid observation.
6. Missing source mempertahankan completeness false/null.
7. Rerun input sama menghasilkan row sama tanpa duplicate.

### 8.3 Orchestration

Urutan konseptual:

1. Sales snapshot;
2. Daily Usage snapshot;
3. Stock Movement snapshot;
4. resolve identity/UOM/catalog;
5. build daily metrics;
6. mark completeness;
7. dispatch forecast hanya jika prerequisites terpenuhi.

Gunakan batch/chaining hanya jika sesuai kebutuhan dan dokumentasi versi. Jangan membuat job monolitik yang mengambil seluruh branch/tanggal.

### 8.4 Backfill command

Buat command dengan:

- `--dry-run` default aman atau confirmation eksplisit untuk execution;
- branch filter;
- date from/to;
- source filter;
- chunk;
- progress;
- resume/checkpoint;
- safe retry;
- rate/concurrency control;
- anomaly/failure summary;
- tidak menghapus snapshot valid.

Dry-run tidak boleh membuat atau mengubah row bisnis/snapshot. Command harus menolak invalid range dan branch yang tidak sah.

### 8.5 Scheduler

1. Daftarkan setelah jam final data yang sudah dikonfirmasi.
2. Gunakan `withoutOverlapping()` dan `onOneServer()` jika infrastructure/cache mendukung.
3. Scheduler hanya dispatch unit kerja; jangan melakukan fetch besar inline.
4. Pisahkan refresh stock intraday sebagai phase/command tersendiri bila dibutuhkan.

### 8.6 Gate phase

- Sync dan metric build idempotent.
- Partial/failure tidak menjadi nol.
- Command dry-run, resume, filter, lock, dan failure diuji.
- Queue test membuktikan dispatch order/prerequisite.

## 9. Phase 5 — Forecast calculator v1

Calculator harus berupa PHP service murni/reusable tanpa ketergantungan pada Filament, Livewire, HTTP, atau view.

### 9.1 Input dan determinisme

Input eksplisit mencakup branch-product-UOM, observasi historis valid, stock snapshot, optional inbound, horizon, cutoff, dan configuration snapshot. Input + algorithm version sama harus menghasilkan output sama.

### 9.2 Weekday weighted baseline

Untuk setiap tanggal target:

```text
Weekday Baseline(D)
= sum(usage weekday sama × recency weight)
  / sum(weight observasi valid)
```

Observasi missing/failed tidak dianggap nol. Minimum observation disimpan di config/version dan dibuktikan melalui backtest/pilot.

### 9.3 Trend

```text
Raw Trend Factor = Recent Average / Comparison Average
```

- Terapkan lower/upper cap terpusat.
- Simpan raw dan final factor.
- comparison 0 + recent 0 = no demand.
- comparison 0 + recent > 0 = New/Recently Sold, bukan growth tak terbatas.

### 9.4 Forecast demand

```text
Forecast Demand(D) = Weekday Baseline(D) × Trend Factor
Period Forecast = sum daily forecast
```

### 9.5 Waste

```text
Historical Waste Rate
= Total Waste / (Sales-driven Usage + Total Waste)

Expected Waste
= Forecast Demand × Capped Historical Waste Rate
```

Denominator nol menghasilkan not available. Simpan raw/capped rate.

### 9.6 Safety buffer

```text
Safety Buffer = Forecast Demand × Global Default Buffer Percentage
```

Tidak ada Settings Page MVP. Nilai ada di config, tampil di detail/export, dan tersimpan pada run snapshot.

### 9.7 Recommendation

```text
Gross Requirement
= Forecast Demand + Expected Waste + Safety Buffer

Raw Recommendation
= Gross Requirement - Available Stock - Confirmed Inbound

Final Recommendation
= max(0, Raw Recommendation)
```

- Inbound unavailable tetap `null`; formula/explanation menyatakan belum diperhitungkan dan confidence turun.
- Jangan menampilkan inbound 0 bila source belum tersedia.
- Negative stock ditampilkan sebagai anomaly.
- Jangan melakukan rounding MOQ/pack size pada MVP.

### 9.8 Coverage dan stockout

```text
Average Daily Demand = valid historical demand / valid days
Stock Coverage Days = Available Stock / Average Daily Demand
```

Average demand nol menghasilkan label tidak ada demand, bukan infinity. Predicted stockout memakai cumulative daily forecast bila tersedia.

### 9.9 Confidence dan explanation

Implementasikan High/Medium/Low/Blocked berdasarkan versioned config dan reason codes. Explanation dibangun dari komponen tersimpan, bukan string acak di Blade.

Blocked minimal untuk:

- demand utama tidak tersedia;
- stock tidak tersedia/terlalu stale sesuai policy;
- branch mismatch;
- identity invalid;
- UOM tidak dapat dinormalisasi.

### 9.10 Persistence

1. Forecast run memakai unique/idempotency key dari branch, cutoff, horizon, algorithm version, dan configuration hash.
2. Simpan run dan items secara transaction-safe setelah kalkulasi siap.
3. Jangan membungkus HTTP call dalam transaction.
4. Completed run hanya menjadi active/latest setelah seluruh persistence berhasil.
5. Run lama immutable.

### 9.11 Unit test wajib

Uji minimal:

- weekday baseline dan recency weights;
- trend naik/turun/cap;
- previous period nol;
- missing days;
- new product;
- waste denominator nol/cap;
- buffer;
- stock deduction;
- inbound null vs zero;
- raw recommendation negatif;
- stock coverage dan stockout date;
- stale/negative stock;
- seluruh confidence states;
- deterministic/versioned result.

## 10. Phase 6 — Backtest dan pemilihan model

### 10.1 Candidate models

Implementasikan kandidat sederhana yang kontraknya jelas:

1. same-weekday seasonal naive;
2. weekday weighted average;
3. rolling average dengan weekday factor;
4. median weekday untuk outlier tinggi.

Jangan menambah layanan ML, Python, microservice, atau dependency eksternal.

### 10.2 Metrics

```text
MAE = average(abs(Forecast - Actual))

WAPE = sum(abs(Forecast - Actual)) / sum(Actual)

Bias = sum(Forecast - Actual) / sum(Actual)
```

Total actual nol membuat WAPE/Bias unavailable sesuai definisi; jangan membagi nol.

### 10.3 Selection

1. Pilih metode per branch-product-UOM hanya bila sample minimum terpenuhi.
2. Jika sample kurang, gunakan default model dengan confidence rendah.
3. Simpan selected method, algorithm version, sample count, MAE/WAPE/Bias.
4. Algorithm version baru tidak mengubah forecast historis.

### 10.4 Tests dan gate

Uji formula metrics, zero actual, candidate selection, insufficient sample, reproducibility, dan immutability. Gate selesai bila error dapat dilihat per branch-product-UOM.

## 11. Phase 7 — Query layer, permission, dan navigation

### 11.1 Permission

Tambahkan permission mengikuti convention repository, konseptual:

```text
view ordering recommendations
export ordering recommendations
refresh ordering recommendation data
```

Permission refresh hanya dibuat jika ada admin action manual yang benar-benar diimplementasikan. Normal user tidak memerlukan refresh.

### 11.2 Navigation

Target:

```text
Analytics
├── Sales Information
├── Ordering Recommendation
├── Promotion Information
└── Stock Information
```

Gunakan label/title `Ordering Recommendation`, group `Analytics`, slug mengikuti convention, dan Heroicon existing. Pastikan group terbuka saat route aktif dan launcher/sidebar memakai permission yang sama.

### 11.3 Query service

Buat query layer yang menangani:

- accessible branch scope;
- latest eligible completed forecast run;
- filters;
- sorting;
- KPI aggregate;
- branch overview;
- rankings;
- chart aggregates;
- server-side pagination;
- lazy detail.

Page tidak boleh menyimpan seluruh result. Custom data Filament wajib di-scope sebelum sorting/pagination; browser filters tidak menentukan authorization.

### 11.4 Ranking

Semua ranking mempunyai key branch + period + type. Implementasikan:

- Prioritas Order;
- Top Demand;
- Trend Naik;
- Trend Turun;
- Waste;
- Stockout Risk;
- optional Overstock bila data mendukung.

New/Recently Sold tidak mendapat persentase infinity. Missing period dikeluarkan atau diberi label incomplete. Mixed UOM tidak dijumlahkan menjadi raw total.

### 11.5 Gate phase

- Navigation/Page/filter/detail/export menolak out-of-scope branch.
- `access_all_branches` bekerja sesuai existing rules.
- Query count/pagination terukur.
- Manipulasi Livewire state tidak melewati policy/scope.

## 12. Phase 8 — UI serupa Sales Information

Gunakan UI aktual Sales Information sebagai referensi hierarchy, bukan copy-paste satu Blade besar.

### 12.1 Urutan halaman

1. Header ringkas.
2. Filter analisis.
3. Freshness/completeness strip.
4. KPI.
5. Branch overview.
6. Grafik demand/forecast.
7. Ranking per branch.
8. Tabel recommendation.
9. Pagination.
10. Detail recommendation.

### 12.2 Filter

Minimum:

- Brand;
- Branch multi-select;
- periode 7 hari, 30 hari, atau custom;
- forecast horizon 7/14 hari;
- category;
- confidence;
- stock risk;
- product search.

Aturan:

- options branch sudah scoped;
- perubahan brand memperbarui branch server-side;
- multi-branch tidak menggabungkan kalkulasi;
- submit menggunakan tombol `Tampilkan Analisis` atau pola submit eksplisit, bukan request setiap ketikan;
- filter reset pagination;
- filter tetap tersedia pada empty/error state;
- default branch mengikuti accessible/primary branch jika sesuai convention.

### 12.3 Freshness strip

Tampilkan last updated, forecast generated, history window, completeness, warning source, dan algorithm version. Tone green/amber/red/gray, tetapi selalu sertakan teks status.

### 12.4 KPI

Tampilkan jumlah branch, produk dianalisis, produk perlu order, stockout risk, trend naik, waste tinggi, dan data tidak lengkap. Jangan menampilkan total quantity lintas UOM.

### 12.5 Branch overview

Untuk multi-branch, satu card/row per branch berisi jumlah SKU perlu order, stockout risk, top rising trend, top waste, confidence, source state, dan aksi lihat detail branch.

### 12.6 Charts

Minimum:

1. Actual Demand vs Forecast per date;
2. Historical Demand Trend;
3. Stock Coverage distribution;
4. Top Trend Naik;
5. Top Waste.

Gunakan chart stack existing. Dataset dibatasi/top-N dan sudah diagregasi server-side. Sertakan axis/unit dan fallback table/label. Destroy/reinitialize chart dengan aman saat filter berubah. Jangan memuat histori tak terbatas ke browser.

### 12.7 Ranking

Gunakan selector/tab untuk satu branch aktif. Saat banyak branch dipilih, user memilih branch atau masuk dari card branch. Jangan membuat satu daftar lintas branch yang membingungkan.

### 12.8 Recommendation table

Server-side table/pagination minimal menampilkan:

- Branch;
- Product/code;
- Category;
- UOM;
- Trend;
- Waste;
- Available Stock;
- Coverage;
- Forecast Demand;
- Recommended Quantity;
- Confidence;
- Stockout risk/status;
- action Detail.

Gunakan column selection yang diperlukan, sorting/filter server-side, dan no N+1. Tabel boleh scroll dalam container; tidak boleh menimbulkan horizontal scroll seluruh halaman.

### 12.9 Detail

Detail modal/page menjelaskan formula secara berurutan:

- source identity;
- history and forecast range;
- baseline per weekday/daily breakdown;
- trend raw/capped;
- waste raw/capped;
- buffer;
- stock and observed time;
- inbound availability;
- gross/raw/final recommendation;
- selected model/backtest;
- confidence/reasons;
- source completeness/warnings.

Jika modal digunakan, terapkan role/name dialog, Escape, backdrop, focus trap/return focus, body scroll, max height, and duplicate-open guard sesuai komponen existing.

### 12.10 Visual consistency

- Reuse components existing terlebih dahulu.
- White/dark surface mengikuti panel existing.
- Border gray, `rounded-2xl` untuk container utama, `rounded-xl` section kecil, spacing `gap-6`/`space-y-6`.
- Jangan memakai shadow pada card/section biasa.
- Primary blue, success green, attention amber, error red, neutral gray.
- Gunakan gap antar sibling, bukan margin acak.
- Gunakan Tailwind 4 utilities; jangan memakai utility deprecated.
- Jangan menambahkan CSS/JS besar inline ke Blade jika dapat ditempatkan sesuai convention existing.

### 12.11 Responsive dan accessibility

Verifikasi viewport 360, 390, 768, 1024, 1440 px serta zoom 200%:

- filter stack mobile;
- KPI/branch cards responsive;
- no global horizontal scroll;
- nama/kode/warning wrap;
- icon-only actions memiliki accessible label;
- keyboard dapat membuka/menutup detail;
- warna bukan satu-satunya status;
- loading, empty, zero, stale, partial, failed, blocked berbeda;
- focus indicator terlihat.

### 12.12 Gate phase

- UI membaca local snapshot saja.
- Tidak ada ESB HTTP pada render/filter/detail.
- Payload/DOM/query dibandingkan baseline.
- UI selaras Sales Information dan `ui-consistency-prd.md`, tetapi tidak menyalin accumulator/browser export-nya.

## 13. Phase 9 — XLSX export

Gunakan export library/pattern existing. Jangan menambah dependency tanpa persetujuan.

### 13.1 Contract

Export menggunakan query contract, filter, selected forecast run, accessible branch scope, dan semantic formatting yang sama dengan UI.

### 13.2 Sheets

`Summary`:

- generated at;
- history/forecast range;
- algorithm version;
- branch;
- completeness;
- KPI.

`Recommendations`:

- Branch, Company Code, ESB Branch Code;
- Product Detail ID, Product Code, Product Name, Category, UOM;
- Historical Demand, Average Daily Demand;
- Trend percentage/label;
- Waste quantity/rate;
- Available Stock, observed time, coverage;
- Forecast Demand, Expected Waste, Safety Buffer;
- Confirmed Inbound;
- Gross Requirement, Recommended Quantity;
- Predicted Stockout;
- Confidence, Explanation, Source State.

`Daily History`:

- identity/date;
- sales-driven usage;
- POS validation usage;
- waste;
- goods in;
- ending stock;
- completeness flags.

`Data Quality`:

- branch/company/source/period;
- status;
- missing dates/pages;
- last sync;
- safe error summary.

### 13.3 Guardrails

1. Product codes/IDs yang perlu mempertahankan leading zero diekspor sebagai string.
2. Sanitasi formula injection untuk nilai awal `=`, `+`, `-`, `@`.
3. Jangan mengekspor credential, token, raw payload, stack trace, atau internal secret.
4. Empty result tetap menghasilkan workbook/header valid.
5. Gunakan chunk/streaming; queued export untuk dataset sangat besar bila pattern existing mendukungnya.
6. Filename memuat range relevan.
7. Authorization diperiksa saat request dan saat queued job dijalankan bila queue dipakai.

### 13.4 Tests

Uji filter, branch scope, sheets/header, leading zero, formula injection, empty result, multi-chunk, mixed UOM, dan data quality.

## 14. Phase 10 — Observability, performance, dan failure handling

### 14.1 Observability

Log/metric yang aman:

- sync duration per source/branch;
- rows fetched/stored dan page count;
- API timeout/rate limit/failure;
- partial/stale branch;
- forecast duration;
- confidence distribution;
- backtest WAPE/Bias distribution;
- dashboard response/query count;
- export duration/rows/failure;
- queue backlog/failed jobs.

Correlation ID menghubungkan sync run, jobs, metrics, dan external request. Jangan log credential atau sensitive raw payload.

### 14.2 Performance

1. Gunakan database pagination.
2. KPI dengan aggregate query, bukan `get()->count()` seluruh row.
3. Detail harian lazy saat dibuka.
4. Chart memakai bounded aggregates.
5. Gunakan eager loading/column selection dan audit N+1.
6. Tambahkan index hanya berdasarkan query/EXPLAIN.
7. Jika cache digunakan, key wajib memuat user/permission scope, branch/filter, dan forecast run; invalidasi saat run baru completed.
8. Jangan cache response personal lintas authorization context.

### 14.3 Failure behavior

- API failure mempertahankan good snapshot lama dan menandai stale/failed.
- Partial source tidak mengganti nilai valid dengan nol.
- Forecast gagal tidak menggantikan latest completed run.
- UI tetap dapat membaca latest valid run dengan warning.
- Job retry tidak membuat duplicate run/item.

## 15. Phase 11 — Test matrix lengkap

Gunakan Pest dan convention tests existing. Buat tests dengan `php artisan make:test --pest ... --no-interaction`; jangan memasukkan prefix suite yang menggandakan directory.

### 15.1 Integration contract

- pagination seluruh source;
- auth/token/company context;
- branch mismatch;
- invalid response;
- timeout/429/5xx;
- partial company/page;
- empty success vs failure;
- no credential in error/log.

### 15.2 Daily metrics

- multi-company satu branch;
- same name/different identity;
- canonical mapping valid;
- UOM conversion valid/invalid;
- latest stock per location;
- no historical balance sum;
- separate waste;
- no POS double count;
- idempotent rerun.

### 15.3 Forecast

Semua edge case pada Phase 5 dan reproduction/version tests.

### 15.4 Backtest

MAE, WAPE, Bias, zero actual, candidate selection, sample minimum, historical immutability.

### 15.5 Authorization

- navigation permission;
- limited branch;
- all branches;
- malicious branch filter;
- detail/export outside scope;
- refresh permission bila ada.

### 15.6 Page/UI

- default filter;
- multi-branch grouped result;
- filter resets pagination;
- all data states;
- no mixed-UOM KPI;
- ranking per branch;
- explanation detail;
- bounded charts;
- keyboard/modal behavior.

Tambahkan Pest browser/visual checks hanya jika infrastructure project sudah mendukungnya; jangan memasang dependency baru diam-diam.

### 15.7 Export

Semua guardrail pada Phase 9.

### 15.8 Job/command

- dry-run no mutation;
- filters/range validation;
- retry/idempotency;
- resume/checkpoint;
- concurrency lock;
- partial failure keeps good data;
- forecast dispatch prerequisites;
- failed job marks safe status.

### 15.9 Regression

Jalankan focused tests untuk Sales Information, Stock Movement, branch scope, permission, navigation, ESB clients, queue/scheduler, dan export existing yang terdampak.

## 16. Urutan eksekusi dan commit boundary

Kerjakan sebagai perubahan kecil dan dapat direview. Rekomendasi boundary:

1. characterization tests dan integration extraction;
2. additive schema/models/factories;
3. identity/UOM/eligibility;
4. sync jobs/daily metric builder/backfill;
5. forecast calculator/persistence;
6. backtest;
7. query/permission/navigation;
8. dashboard UI;
9. export;
10. observability/performance hardening.

Jangan membuat commit kecuali diminta user. Boundary di atas adalah logical review boundary, bukan izin commit/push.

## 17. Verifikasi akhir lokal

Sebelum handoff:

1. Jalankan focused tests per phase.
2. Jalankan seluruh test domain Ordering Recommendation dan regression terdampak dengan `php artisan test --compact` pada file/filter spesifik.
3. Jalankan `vendor/bin/pint --dirty --format agent` jika PHP berubah.
4. Jalankan build/check frontend bila source asset berubah.
5. Jalankan `git diff --check`.
6. Periksa route/page discovery dan navigation permission.
7. Periksa schema/index/migration status tanpa menjalankan production.
8. Verifikasi tidak ada HTTP stray pada tests.
9. Verifikasi tidak ada credential, `.env`, dump, snapshot production, atau generated build unrelated yang masuk diff.
10. Review diff untuk perubahan unrelated.

Jangan mengklaim full test suite lulus bila hanya focused tests yang dijalankan. Laporkan command dan hasil sebenarnya.

## 18. UAT staging

Siapkan langkah UAT, tetapi jangan menjalankan deployment/backfill staging tanpa otorisasi yang sesuai.

Urutan UAT:

1. deploy additive schema/code;
2. sync/backfill dry-run;
3. pilih 2–3 branch pilot;
4. backfill periode sample;
5. verifikasi identity/UOM/source completeness;
6. generate forecast 7/14 hari;
7. bandingkan manual dengan Sales/Daily Usage/Stock report;
8. periksa branch ranking;
9. periksa export;
10. uji limited branch dan all branches;
11. review mobile/desktop/accessibility;
12. backtest terhadap actual historis;
13. dapatkan approval Operational/Kitchen sebelum production.

Skenario minimum:

- complete single-company branch;
- multi-company branch;
- several branches selected;
- trend up/down;
- new/no-demand product;
- high waste;
- zero/negative/stale stock;
- UOM mismatch;
- Daily Usage failure with Stock success;
- one company failure;
- inbound unavailable;
- 7/14-day horizon;
- limited/all-branch user;
- filtered export;
- explanation matches table/formula.

## 19. Rollout dan rollback guardrails

Rollout production bukan bagian implementasi otomatis ini. Rencana aman:

1. deploy additive schema/code dalam keadaan hidden/permission-gated;
2. staging sync/backfill/UAT;
3. production backfill terbatas dengan approval;
4. grant pilot permission;
5. buka view-only;
6. monitor minimal empat minggu;
7. bandingkan forecast vs actual sebelum membahas auto-order.

Rollback utama:

- hide navigation/revoke permission;
- stop Ordering Recommendation scheduler/jobs;
- tetap simpan snapshot/forecast untuk audit;
- jangan memakai migration down production sebagai rollback utama;
- jangan menghapus data tanpa instruksi terpisah.

## 20. Batas scope yang harus dijaga

Jangan implementasikan pada MVP:

- create/submit Store–Kitchen Order;
- approval/rejection workflow;
- auto-submit ke Kitchen/ESB;
- supplier selection;
- notification eksternal;
- manual recommendation override;
- branch/product settings page;
- MOQ/pack-size rounding tanpa master;
- lead-time optimization tanpa contract;
- promo/event/payday/holiday automation;
- Python/ML service/microservice;
- rewrite Vue/React/Astro/Inertia;
- perubahan behavior Sales Information;
- production deployment/backfill.

## 21. Definition of Done

Implementasi dianggap siap UAT hanya bila:

- halaman tersedia di Analytics dan visualnya selaras Sales Information;
- seluruh output dihitung per branch-product-UOM;
- source history tersimpan lokal dan idempotent;
- demand, trend, waste, stock, recommendation, confidence, dan data quality dapat dijelaskan;
- ranking benar-benar per branch;
- missing/stale/partial/failed tidak dianggap nol;
- multi-company/UOM aman;
- forecast versioned, immutable, reproducible, dan dapat di-backtest;
- export server-side aman serta scoped;
- navigation/Page/filter/detail/export authorization teruji;
- UI responsif dan accessible;
- tests, Pint, diff check, discovery, dan build relevan lulus;
- tidak ada unrelated changes;
- tidak ada ESB mutation;
- staging backfill/deployment belum dilakukan tanpa izin terpisah.

## 22. Format laporan akhir agent

Laporkan secara ringkas tetapi berbukti:

1. outcome yang selesai;
2. keputusan Phase 0 beserta bukti dan keputusan yang masih pending;
3. file utama yang dibuat/diubah;
4. migration/schema/index yang ditambahkan;
5. arsitektur final dan batas tanggung jawab;
6. formula, algorithm/config version, identity/UOM behavior;
7. permission dan branch-scope behavior;
8. data state/failure behavior;
9. UI/export yang selesai;
10. commands/tests yang dijalankan dan hasilnya;
11. baseline/performance comparison yang tersedia;
12. blocker/risiko/assumption tersisa;
13. langkah UAT staging;
14. konfirmasi bahwa tidak ada commit, push, deploy, migration/backfill production, atau ESB mutation kecuali memang diinstruksikan terpisah.

Jika kontrak data kritis belum dapat dibuktikan, jangan menyatakan fitur selesai. Jelaskan bagian yang aman sudah selesai, bagian yang sengaja diblokir, bukti yang kurang, dan keputusan spesifik yang dibutuhkan.
```
