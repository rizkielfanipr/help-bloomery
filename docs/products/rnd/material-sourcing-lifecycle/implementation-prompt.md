# Prompt Implementasi — Workflow Master Bahan, Review IT, Sourcing Sampel, dan Pricelist

Salin seluruh prompt berikut ke agent implementasi dari root repository.

```text
Implementasikan workflow Master Bahan, review IT sebelum ESB, sourcing dan sampel, serta Pricelist Finance secara end-to-end berdasarkan dokumen berikut:

- `docs/products/rnd/material-sourcing-lifecycle/prd.md` — sumber requirement produk terbaru dan utama.
- `docs/standards/code-remediation.md` — guardrail arsitektur, keamanan data, integrasi, performa, dan remediation.
- `docs/standards/ui-consistency.md` — guardrail UI, responsive behavior, modal, state, status, dan accessibility.
- dokumentasi R&D, BOM, Product Detail replacement, dan ESB integration lain yang masih aktif — referensi perilaku existing yang harus dipertahankan selama tidak bertentangan dengan PRD utama.

Kerjakan sampai implementasi aman dan teruji. Jangan berhenti pada planning, audit, skeleton, contoh kode, atau TODO. Jika bagian API Pricelist belum dapat diimplementasikan karena kontrak endpoint resmi tidak tersedia, selesaikan seluruh bagian aman menggunakan mode pricing manual terkontrol dan buat boundary integrasi yang siap dilengkapi tanpa memalsukan keberhasilan API.

Outcome bisnis final:

1. R&D membuat Master Bahan atau Master WIP sebagai Draft.
2. R&D mengirim data untuk review IT.
3. Sistem mengirim notifikasi Telegram kepada IT setelah transaksi submit berhasil commit.
4. IT memeriksa data, dapat memperbaiki field teknis dengan audit trail, mengembalikan revisi, menautkan produk existing, atau menyetujui push ESB.
5. Produk tidak boleh dipush/ditautkan tanpa approval IT aktif untuk versi data yang sama.
6. Setelah Product ID dan seluruh Product Detail ID terverifikasi, R&D dapat memakai bahan tersebut pada resep/BOM.
7. Bahan biasa masuk sourcing Purchasing, penyerahan sampel, konfirmasi penerimaan, dan evaluasi R&D.
8. Bahan biasa hanya masuk Finance setelah sampel disetujui R&D.
9. WIP tidak menjalani sourcing/sampel dan langsung masuk Finance setelah sinkronisasi ESB terverifikasi.
10. Finance bekerja dari submenu baru `Finance → Pricelist Bahan`.
11. Konfirmasi Pricelist menjadi approval Finance; jangan membuat approval Finance kedua yang identik.
12. `Completed` dihitung oleh sistem setelah seluruh guard terpenuhi dan tidak dapat dipilih manual.

## 1. Aturan kerja wajib

1. Baca `AGENTS.md` sampai selesai dan patuhi seluruh Laravel Boost Guidelines.
2. Baca seluruh PRD sumber sebelum mengubah kode. Requirement terbaru dalam `docs/products/rnd/material-sourcing-lifecycle/prd.md` mengalahkan asumsi lama untuk scope ini.
3. Audit `git status --short --branch`, `git diff --stat`, dan diff seluruh file terkait. Pertahankan perubahan user yang sudah ada; jangan menimpa perubahan unrelated.
4. Gunakan Laravel Boost sebagai pilihan utama untuk memahami aplikasi, schema, log, dan dokumentasi versi paket.
5. Jalankan Laravel Boost `search-docs` sebelum code change dengan query broad dan package scope yang relevan. Verifikasi API Laravel 13, Filament 5, Livewire 4, queue, notifications, filesystem, authorization, validation, database transaction, locking, dan Pest 4.
6. Gunakan `database-schema` sebelum mendesain migration, constraint, index, atau relation. Gunakan `database-query` hanya untuk inspeksi read-only.
7. Gunakan `rg`/`rg --files` untuk pencarian kode dan periksa sibling files sebelum membuat class/komponen baru.
8. Gunakan `php artisan make:* --no-interaction` untuk file Laravel baru bila generator tersedia.
9. Jangan mengubah migration lama yang mungkin sudah dijalankan. Buat migration baru yang additive, reversible, dan aman untuk data existing.
10. Jangan menambah/mengubah dependency tanpa persetujuan user.
11. Jangan melakukan `git reset`, `git checkout --`, `git clean`, stash, hard delete data, atau operasi destruktif lain.
12. Jangan commit, push, membuka PR, deploy, menjalankan migration production, atau melakukan mutation ESB/Telegram/Pricelist production tanpa instruksi terpisah.
13. Jangan mengubah `.env` user. Tambahkan key dokumentasi/config example hanya jika pola repository memerlukannya dan tidak mengandung credential.
14. Jangan hard-code role, user ID, company, branch, category, chat ID, bot token, URL production, atau credential.
15. Seluruh mutation wajib authorize server-side. Visibility tombol bukan authorization.
16. Seluruh perubahan status harus melalui Action/Service yang memvalidasi transisi, actor, versi record, dan guard bisnis.
17. Gunakan database transaction untuk perubahan lokal multi-row. Dispatch event/job eksternal hanya setelah commit.
18. Jangan membungkus HTTP call panjang dalam transaction database.
19. Mutation eksternal harus idempoten/retry-safe. Timeout setelah request terkirim dianggap hasil tidak pasti dan masuk reconciliation/verification, bukan retry create otomatis.
20. Jangan memanggil HTTP eksternal dari Blade, Filament Resource/Page, atau render Livewire.
21. Jangan query database dari Blade.
22. Jangan memuat semua produk, review, supplier, sample, atau pricing ke state browser. Gunakan server-side query, search, filter, pagination, dan lazy detail.
23. Jangan menghapus test existing. Tambah characterization test sebelum refactor perilaku lama.
24. Fake Telegram dan ESB dalam test; larang stray HTTP request bila pola test suite mendukungnya.
25. Jalankan test terfokus setelah setiap phase. Jangan menunggu seluruh fitur selesai.
26. Jika PHP berubah, jalankan `vendor/bin/pint --dirty --format agent` sebelum finalisasi.
27. Jika frontend source berubah, jalankan build sesuai script repository dan tangani error manifest sesuai guideline.
28. Jangan membuat dokumentasi tambahan kecuali implementation report memang dibutuhkan pola repository atau diminta user.
29. Jangan bertanya tentang hal yang dapat dibuktikan dari kode, schema, test, config, atau dokumentasi existing. Ambil keputusan berdasarkan bukti.
30. Jika kontrak eksternal tidak dapat dibuktikan, hentikan hanya mutation eksternal yang berisiko, implementasikan fallback aman, dan laporkan bukti serta blocker secara spesifik.

## 2. Prinsip domain yang tidak boleh berubah

1. Review IT wajib terjadi sebelum link/create produk ESB.
2. Approval IT berlaku untuk satu submission version/snapshot data tertentu.
3. Perubahan data setelah approval membatalkan eligibility approval tersebut dan memerlukan review ulang sesuai aturan versi.
4. IT boleh memperbaiki field teknis dengan alasan dan diff; perubahan makna/spesifikasi bisnis dikembalikan ke R&D.
5. Produk existing harus ditautkan, bukan dibuat ulang.
6. Nama produk saja tidak cukup untuk menentukan produk existing.
7. Status `synced` hanya boleh diberikan setelah read-back dan mapping Product Detail terverifikasi.
8. Product Detail ID setiap unit adalah identity teknis yang harus disimpan dan diuji.
9. Harga penawaran supplier bukan Pricelist resmi.
10. Bukti penyerahan Purchasing dan bukti penerimaan R&D adalah dua bukti berbeda.
11. Sampel ulang membuat Sample Cycle baru; jangan menimpa/menghapus histori lama.
12. WIP tidak membuat Sourcing Request, supplier palsu, Sample Cycle palsu, atau approval sampel palsu.
13. Bahan biasa tidak masuk Finance sebelum `approved_by_rnd`.
14. Finance tidak boleh mengubah Product Detail ID dari form pricing.
15. `Completed` adalah derived/guarded state, bukan input user.
16. Data lama tidak boleh memperoleh review, foto, approval, atau histori palsu melalui backfill.

## 3. Phase 0 — Audit dan characterization

Sebelum mengedit behavior, lakukan audit read-only.

### 3.1 Worktree dan ownership

1. Jalankan `git status --short --branch` dan `git diff --stat`.
2. Petakan perubahan user yang overlap dengan scope.
3. Petakan dengan `rg`:
   - `RndProductEsbMaterial`, unit material, factory, migration, relation, enum, dan test;
   - seluruh tempat create/edit/delete/sync material;
   - `syncEsbMaterial`, `toEsbPayload`, mapping Product Detail, replacement conversion factor, idempotency, transaction recorder, reconciliation, dan monitoring;
   - `MaterialSourcing`, `MaterialSourcingStatus`, `MaterialSourcingApproval`, Resource/Page/view, standalone sourcing, permission, dan test;
   - Product/BOM picker dan eligibility material pada resep;
   - klasifikasi raw, packaging, marketing, dan WIP;
   - Product Price Index, Purchase Order price sync, Finance navigation, permission, dan test;
   - queue configuration, scheduler/worker assumptions, notification patterns, HTTP clients, monitoring, and storage attachment patterns;
   - seluruh call Telegram bila ternyata telah ditambahkan setelah baseline PRD;
   - seluruh route/menu/button push produk ke ESB yang dapat mem-bypass gate baru.

### 3.2 Schema aktual

Inspeksi schema dan index untuk minimal:

- `rnd_product_esb_materials`;
- tabel unit material;
- `material_sourcings`;
- `material_sourcing_approvals`;
- project/product/BOM relation yang memakai material;
- user/permission/role tables;
- notification/job/failed job tables bila ada;
- attachment/storage metadata yang dapat direuse;
- tabel monitoring ESB/idempotency/transaction recorder;
- tabel harga/Purchase Order/Product Price Index.

Catat FK, nullable, enum/string status, soft delete, unique constraint, index, dan volume data development/staging yang tersedia secara aman.

### 3.3 Baseline behavior

Jalankan test paling relevan untuk:

- Material Sourcing;
- standalone sourcing;
- R&D material/unit create-edit-sync;
- Product Detail replacement/update;
- BOM/product recipe eligibility;
- permission dan branch/company access;
- Product Price Index dan price sync;
- ESB monitoring/idempotency.

Catat failure existing secara terpisah. Jangan mengubah test agar pipeline hijau tanpa memahami kontraknya.

### 3.4 Characterization tests wajib

Sebelum refactor tambahkan/pertahankan test yang membuktikan:

- data supplier existing dapat dibuat dan dibaca;
- approval R&D/Finance existing dapat dibuka;
- standalone sourcing memilih produk ESB aktif dan tidak membuat produk baru;
- push material existing mencegah double click/create ganda;
- response tidak pasti existing tidak dipush ulang sembarangan;
- Product Detail mapping existing tetap terbaca;
- bahan synced existing tetap dapat digunakan pada workflow R&D yang sekarang;
- route, navigation, dan permission penting tidak hilang.

### 3.5 Keputusan audit

Sebelum lanjut, dokumentasikan di catatan kerja:

- model/tabel yang dapat direuse dan yang membutuhkan tabel baru;
- daftar entry point push ESB yang harus digate;
- klasifikasi material existing yang menentukan sourcing required;
- strategi kompatibilitas status lama;
- pola attachment private yang direuse;
- pola queue/notification yang direuse;
- fakta kontrak API Pricelist yang benar-benar tersedia;
- risiko data existing dan kebutuhan dry-run.

Jangan membuat model paralel bila tabel existing dapat diperluas dengan aman. Jangan memaksakan satu tabel menyimpan lifecycle yang tidak kohesif.

## 4. Phase 1 — Fondasi enum, status, dan schema additive

Implementasikan pemisahan empat dimensi status:

1. review IT;
2. sinkronisasi ESB;
3. sourcing/sampel;
4. Pricelist.

Ikuti nilai final dalam PRD. Gunakan backed enum TitleCase sesuai convention repository. Jangan menaruh query, HTTP, atau authorization pada enum.

### 4.1 Review IT

Buat struktur untuk menyimpan:

- material ID;
- submission version;
- status;
- snapshot data saat submit;
- submitted by/at;
- claimed by/at;
- reviewed by/at;
- decision dan reason.

Constraint minimum:

- submission version unik per material;
- satu active review per material bila aturan domain menetapkan demikian;
- index status dan submitted_at untuk inbox;
- FK dan delete behavior tidak menghapus histori secara tidak sengaja.

### 4.2 Audit perubahan teknis

Simpan perubahan field/path, before, after, actor, reason, dan timestamp. Sanitasi value sensitif. Jangan membuat audit diff dari seluruh payload eksternal bila hanya field tertentu berubah.

### 4.3 Sample Cycle

Tambahkan entity/struktur untuk cycle number, supplier candidate, handover, receipt, evaluation, reviewer, status, dan attachment relation. Unique cycle number berlaku dalam satu sourcing/material context.

### 4.4 Pricing Request

Tambahkan entity/struktur untuk material, Product Detail ID, UOM snapshot, origin, supplier/offer snapshot opsional, company/branch scope, price, effective date, status, external reference, idempotency key, payload/response tersanitasi, dan timestamps.

Satu eligibility event tidak boleh menghasilkan Pricing Request ganda. Tambahkan constraint yang mencerminkan identity sebenarnya setelah audit.

### 4.5 Notification delivery

Gunakan tabel/pola notification delivery existing bila layak. Jika belum ada, tambah struktur minimum untuk event, aggregate ID, destination key, idempotency key, status, attempts, sent_at, dan error summary.

### 4.6 Model/factory

- gunakan explicit `$fillable` dan casts;
- semua relationship mempunyai return type;
- tambah factory/states yang berguna untuk Draft, waiting IT, revision, synced raw, synced WIP, approved sample, dan ready pricing;
- jangan membuat observer tersembunyi untuk transisi bisnis besar;
- jangan menjalankan HTTP dari model event.

Jalankan migration hanya pada environment lokal/test yang aman sesuai instruksi user dan project. Jalankan test Phase 1.

## 5. Phase 2 — Workflow submission dan review IT

### 5.1 Actions

Buat/refactor use-case Action mengikuti convention repository. Kandidat:

- `SubmitMaterialForItReviewAction`;
- `ClaimMaterialReviewAction`;
- `ReturnMaterialForRevisionAction`;
- `AmendMaterialTechnicalDataAction`;
- `ApproveMaterialForEsbAction`.

Nama final mengikuti sibling files. Setiap Action wajib:

- menerima actor/context eksplisit;
- authorize server-side;
- lock row/version saat transition;
- memvalidasi current state;
- menulis histori dalam transaction;
- mengembalikan model/result yang jelas;
- tidak mempunyai toast, modal, HTML, atau state Livewire.

### 5.2 Submission version

Saat R&D submit:

1. validasi field wajib dan struktur unit;
2. buat snapshot canonical yang stabil;
3. increment submission version secara transaction-safe;
4. ubah status menjadi `waiting_it_review`;
5. kunci editing R&D;
6. record event notification setelah commit.

Double-click/retry dengan data dan intent sama tidak boleh membuat submission version atau event Telegram ganda.

### 5.3 Claim dan concurrency

- IT mengambil review secara eksplisit atau gunakan optimistic concurrency sesuai pola aplikasi;
- dua IT tidak boleh menyimpan keputusan yang saling menimpa;
- approval terhadap stale submission version harus ditolak;
- return/revision melepaskan claim sesuai aturan;
- timeout claim opsional hanya dibuat jika ada requirement dan aturan jelas, jangan ditebak.

### 5.4 Edit teknis

Field teknis yang dibuktikan dari domain dapat diedit IT. Setiap edit:

- alasan wajib;
- before/after dicatat;
- snapshot approval diperbarui secara eksplisit;
- perubahan terlihat oleh R&D;
- tidak melakukan HTTP.

Jika perubahan mengubah makna bisnis atau jenis sourcing, gunakan return-to-R&D.

### 5.5 UI R&D

- tampilkan status review terpisah dari sync/sourcing/pricing;
- tombol `Kirim untuk Review IT` hanya muncul bila eligible;
- jelaskan bahwa data dikunci setelah submit;
- validation errors dekat field;
- loading/double-submit protection;
- Draft dan Revision Required dapat diedit;
- Waiting/Under Review read-only bagi R&D.

### 5.6 Inbox IT

Tambahkan menu/halaman sesuai navigation convention, misalnya `IT → Review Master Bahan` setelah memastikan naming existing.

Index:

- server-side pagination;
- filter status, jenis, pengaju, dan tanggal;
- search kode/nama;
- default menampilkan waiting/under review dan reconciliation relevan;
- tidak menarik payload/detail besar untuk seluruh row.

Detail:

- identitas dan unit;
- checklist teknis;
- diff perubahan;
- kandidat duplikasi;
- histori;
- aksi claim, edit teknis, return, link existing, approve/push.

Gunakan halaman detail untuk workflow panjang. Jangan menumpuk modal.

Test authorization, transition, stale version, double submit, dan UI critical behavior sebelum lanjut.

## 6. Phase 3 — Telegram review IT

### 6.1 Konfigurasi

Tambahkan config mengikuti pola `config/services.php` atau configuration boundary existing. Jangan membaca `env()` di luar config. Jangan commit token/chat ID nyata.

Konsep config:

- enabled;
- bot token;
- IT review chat ID;
- optional Purchasing chat ID;
- timeout.

### 6.2 Client/service

Buat service tipis untuk mengirim pesan melalui HTTP client Laravel:

- explicit timeout/connect timeout;
- throw/error mapping yang aman;
- response validation;
- redaction token dan data sensitif;
- tidak menentukan aturan bisnis kapan pesan dikirim.

Jangan tambah package Telegram jika HTTP client bawaan cukup.

### 6.3 Event dan job

- event/delivery record dibuat setelah submission commit;
- queued job menerima ID, bukan object/session Livewire besar;
- job membaca state yang diperlukan secara aman;
- unique/idempotency key per `material + submission_version + event_type + destination`;
- retry/backoff/tries/timeout eksplisit;
- `failed()` mencatat status aman;
- Telegram gagal tidak mengubah status review.

### 6.4 Pesan

Isi minimum:

- `Review Master Bahan Baru` atau `Review Master WIP Baru`;
- nama/kode;
- jenis;
- ringkasan UOM;
- pengaju;
- waktu WIB;
- absolute URL menuju detail review.

Gunakan named route dan URL aplikasi yang benar. Jangan kirim nomor supplier, harga sensitif, attachment, credential, atau payload ESB.

### 6.5 Tests

- HTTP fake success/failure/timeout;
- rollback tidak dispatch;
- retry tidak membuat delivery ganda;
- disabled config tidak melakukan HTTP dan tetap mencatat perilaku sesuai keputusan;
- URL dan message benar;
- token tidak muncul di log/exception user-facing;
- unauthorized user tidak dapat memicu notification event.

## 7. Phase 4 — Gate IT dan integrasi ESB

### 7.1 Tutup seluruh bypass

Audit ulang seluruh tombol/method/command/API yang dapat create/link/update material ESB. Semua entry point harus memanggil Action/Service yang sama dan memeriksa:

- approved IT review exists;
- approval sesuai submission version/data hash saat ini;
- current state eligible;
- actor authorized;
- operation tidak sedang berjalan;
- reconciliation state tidak di-bypass.

UI lama tidak boleh tetap dapat push tanpa gate walaupun tombol baru sudah tersedia.

### 7.2 Action/Service sync

Ekstrak orchestration dari Page besar ke Action/Service. Page hanya authorize presentation, memanggil Action, dan menampilkan feedback.

Flow:

1. lock/claim local operation;
2. simpan canonical payload dan idempotency key;
3. commit local `queued/syncing` state secara aman sesuai pattern;
4. jalankan integration di luar transaction panjang, sinkron atau queue berdasarkan audit latency/pola existing;
5. cari/link product existing menggunakan identity kuat;
6. create hanya jika tidak ada match yang disetujui;
7. read-back product;
8. map setiap local unit ke satu active Product Detail;
9. simpan mapping dan status;
10. buat Sourcing Request untuk bahan biasa atau Pricing Request untuk WIP melalui operation idempoten.

### 7.3 Matching existing

- exact product code;
- Product ID bila sudah tersimpan;
- SKU;
- UOM ID/name;
- nama normalized hanya indikator tambahan.

Kandidat ambigu masuk `needs_reconciliation` dan ditampilkan kepada IT. Jangan memilih otomatis hanya karena nama sama.

### 7.4 Verification

Jangan tandai synced jika:

- Product ID tidak valid;
- read-back gagal;
- unit aktif lokal tidak mempunyai Product Detail ID;
- satu Product Detail dipakai dua unit lokal secara salah;
- SKU/UOM tidak cocok;
- response parsial/ambigu.

### 7.5 Conversion factor replacement

Pertahankan aturan repository bahwa conversion factor Product Detail lama tidak diubah langsung bila ESB tidak mendukungnya. Reuse service replacement existing:

- nonaktifkan detail lama melalui prosedur resmi;
- buat detail pengganti;
- verifikasi read-back;
- update mapping;
- pertahankan histori;
- test resep/consumer detail lama.

### 7.6 Reconciliation

Sediakan IT view/action untuk:

- melihat request/response tersanitasi;
- mencari hasil create yang mungkin sudah terjadi;
- link hasil existing;
- retry hanya ketika aman;
- resolve dengan actor, waktu, dan alasan.

Jalankan test timeout, duplicate click, partial mapping, existing link, create baru, unauthorized, stale approval, dan reconciliation.

## 8. Phase 5 — Sourcing Request dan Sample Cycle

### 8.1 Compatibility sourcing existing

Pertahankan field supplier existing:

- supplier name;
- brand;
- offer price;
- MOQ;
- lead time;
- contact;
- notes;
- quotation attachment;
- submitter.

Refactor mutation kompleks dari Resource ke Action tanpa mengubah data secara diam-diam.

### 8.2 Create Sourcing Request

Untuk bahan biasa:

- hanya setelah `esb_sync_status = synced`;
- satu active request per workflow version;
- dibuat idempoten;
- notifikasi internal Purchasing dibuat setelah commit;
- Telegram Purchasing hanya jika config/requirement disetujui; jangan anggap otomatis.

Untuk WIP:

- set `sourcing_status = not_required`;
- jangan membuat request/supplier/sample dummy;
- buat Pricing Request sesuai Phase 6.

### 8.3 Handover Purchasing

Buat Action yang mewajibkan:

- supplier candidate valid;
- quantity > 0;
- UOM valid;
- foto handover;
- handover timestamp;
- actor authorized.

Server yang menentukan actor/time canonical. Client time dapat menjadi metadata bila dibutuhkan, bukan sumber audit utama.

### 8.4 Receipt R&D

Receipt adalah aksi terpisah yang mewajibkan:

- cycle berstatus handed over;
- foto receipt dari R&D;
- kondisi/jumlah diterima;
- actor R&D authorized;
- idempotency/double-submit guard.

Jangan reuse foto handover sebagai foto receipt.

### 8.5 Evaluation

Hasil:

- Approve;
- Request Resample;
- Reject.

Aturan:

- reason wajib untuk reject/resample;
- approve mencatat reviewer/time dan membuat Pricing Request satu kali;
- resample menutup cycle sekarang dan membuat cycle berikutnya;
- history immutable/readable;
- reopen memerlukan permission dan reason;
- reopen setelah pricing dimulai menghasilkan conflict state/guard, bukan menghapus Pricing Request atau harga.

### 8.6 Attachment

- private disk/object storage;
- temporary URL setelah authorization;
- MIME/size/count validation;
- upload state dan error jelas;
- metadata uploader/time/path/name/MIME/size;
- rollback/delete lifecycle tidak meninggalkan file tanpa kebijakan;
- test unauthorized download.

### 8.7 UI

- supplier, handover, receipt, dan evaluation menjadi section yang jelas;
- Sample Cycle tampil sebagai timeline/history lazy-loaded;
- status badge memakai enum/presenter;
- action mengikuti role dan current state;
- responsive dan tanpa horizontal page scroll;
- tidak ada modal bertumpuk.

Jalankan seluruh test sourcing/sample sebelum lanjut.

## 9. Phase 6 — Pricing eligibility dan submenu Finance

### 9.1 Eligibility service/action

Pusatkan aturan eligibility, jangan menduplikasi query di Resource dan Page.

Bahan biasa:

```text
esb_sync_status = synced
AND sourcing_status = approved_by_rnd
AND target product_detail_id exists
AND no equivalent active pricing request
```

WIP:

```text
material classification = wip
AND esb_sync_status = synced
AND sourcing_status = not_required
AND target product_detail_id exists
AND no equivalent active pricing request
```

Pricing Request dibuat idempoten ketika material menjadi eligible.

### 9.2 Navigation

Tambahkan submenu `Finance → Pricelist Bahan` dengan permission khusus dan urutan navigation mengikuti convention Finance existing.

Jangan mengganti atau menghapus Product Price Index. Jelaskan perbedaannya melalui copy UI:

- Pricelist Bahan = workflow harga resmi;
- Product Price Index = informasi/analitik harga historis.

### 9.3 Index

Gunakan server-side query/pagination. Kolom minimum:

- type/section;
- code;
- product/material name;
- origin Sourcing/WIP;
- selected supplier bila ada;
- purchasing/target UOM;
- Product Detail ID;
- offer price reference;
- official Pricelist price;
- scope;
- status;
- queued/updated timestamps;
- action.

Filter:

- pricing status;
- material type;
- origin;
- category;
- supplier;
- company/branch jika relevan;
- date range;
- code/name search.

Default menunjukkan ready, verification required, dan failed yang memerlukan tindakan.

### 9.4 Detail/form

- Product, Product Detail ID, dan UOM read-only;
- offer price jelas dilabeli referensi;
- Finance mengisi official price, scope, effective date jika didukung, dan notes;
- invalid mapping dikembalikan ke IT;
- validation errors dekat field;
- loading/double-submit protection;
- history pricing/retry tersedia tanpa memuat seluruh payload pada index.

### 9.5 Approval Finance

Konfirmasi Pricelist yang valid menjadi approval Finance. Jangan mempertahankan aksi approval Finance terpisah yang membuat user menyetujui hal sama dua kali.

Namun, jaga histori approval lama tetap terbaca. Buat compatibility presenter/mapping bila dibutuhkan; jangan hapus row lama.

## 10. Phase 7 — Implementasi pricing manual terkontrol

Implementasikan mode ini bila write API Pricelist belum terbukti.

Flow:

1. Finance menyimpan draft harga lokal.
2. Finance melakukan input ke ESB melalui prosedur operasional di luar aplikasi.
3. Finance membuka request dan mengonfirmasi hasil dengan bukti/reference yang ditentukan.
4. Jika read API tersedia, sistem melakukan read-back dan mencocokkan Product Detail, scope, price, dan date.
5. Jika read API tidak tersedia, confirmation manual mencatat actor, timestamp, note, dan bukti minimum sesuai keputusan bisnis.
6. Status menjadi `priced` hanya setelah verification step, bukan ketika draft disimpan.

Jangan menampilkan toast `Berhasil dikirim ke ESB` bila aplikasi tidak benar-benar melakukan dan memverifikasi mutation.

## 11. Phase 8 — API Pricelist opsional

Jalankan hanya jika kontrak resmi endpoint telah tersedia dan dapat diverifikasi.

### 11.1 Contract audit

Verifikasi:

- create/update endpoint;
- read-back endpoint;
- auth/company headers;
- identity Product Detail;
- company/branch scope;
- effective date/overlap;
- idempotency/external reference;
- update vs create semantics;
- status/error/rate limit;
- WIP support.

Tambahkan contract tests/fakes dari fixture tersanitasi. Jangan menebak field endpoint.

### 11.2 Mutation flow

- Action menyimpan draft dan claim lokal;
- integration job/service melakukan HTTP;
- idempotency key/external reference disimpan;
- timeout menghasilkan `verification_required`;
- read-back menentukan success;
- retry hanya setelah reconciliation memastikan mutation belum terjadi;
- payload/response disanitasi;
- monitoring/correlation ID tercatat.

### 11.3 Feature flag

Jika repository mempunyai pola feature flag, aktifkan API pricing di staging lebih dulu. Default yang aman adalah mode manual sampai contract dan staging terbukti.

## 12. Phase 9 — Completed state dan notifikasi internal

Implementasikan satu service/action untuk mengevaluasi completion guard.

Bahan biasa:

```text
esb_sync_status = synced
AND sourcing_status = approved_by_rnd
AND pricing_status = priced
```

WIP:

```text
esb_sync_status = synced
AND sourcing_status = not_required
AND pricing_status = priced
```

Ketentuan:

- Completed tidak dapat dipilih manual;
- evaluasi idempoten;
- completion actor/system metadata tercatat;
- reopen/correction mempunyai transition eksplisit;
- notifikasi internal dikirim setelah commit;
- jangan menutup workflow saat hanya draft price tersimpan.

Notifikasi internal minimum:

- review baru → IT;
- revision required → R&D;
- sync success/failure → R&D/IT;
- sourcing request → Purchasing;
- sample handover → R&D;
- resample → Purchasing;
- approved sample/WIP synced → Finance;
- pricing failure/verification → Finance/IT;
- completed → owner proses terkait.

## 13. Phase 10 — Data lama dan compatibility

### 13.1 Audit data

Buat query/report read-only untuk mengelompokkan:

- synced dengan mapping detail lengkap;
- synced dengan mapping detail parsial;
- status unknown/failed;
- sourcing approved lama;
- WIP synced;
- approval Finance lama;
- record tanpa klasifikasi/identity yang cukup.

### 13.2 Backfill command bila diperlukan

Gunakan Artisan command sesuai convention:

- default dry-run;
- summary count dan sample ID;
- filter/range/chunk;
- idempoten/resumable bila volume besar;
- explicit execute flag;
- tidak call production ESB secara implisit;
- snapshot/restore hanya jika memang diperlukan dan dirancang aman;
- exception report.

Aturan backfill:

- synced lama hanya diakui setelah bukti mapping/read-back sesuai keputusan rollout;
- review IT lama tidak dibuat palsu; gunakan legacy marker;
- approval sourcing lama tidak membuat foto/cycle palsu;
- WIP synced menjadi kandidat Pricing Request melalui dry-run;
- record ambigu tetap manual review;
- jangan auto-complete data lama hanya dari satu status lama.

### 13.3 Compatibility window

- baca status/kolom lama selama consumer belum seluruhnya dimigrasi;
- tulis melalui workflow baru setelah cutover point yang jelas;
- hapus/deprecate aksi lama hanya setelah usage audit dan regression test;
- route penting dipertahankan atau dialihkan secara kompatibel;
- jangan drop column/table pada deployment pertama.

## 14. Query, performa, dan observability

### 14.1 Query

- eager-load relation yang benar;
- gunakan `withCount` untuk count;
- pilih kolom yang diperlukan;
- filter dan pagination database;
- hindari query di loop dan N+1;
- tambah index berdasarkan pola query/EXPLAIN, bukan tebakan;
- index minimal dipertimbangkan untuk review status/date, sync status, sourcing status, pricing status, material ID, Product Detail ID, dan unique idempotency keys.

### 14.2 Livewire

- jangan menyimpan collection besar pada public property;
- identifier sensitif locked/validated ulang;
- hindari `wire:model.live` yang tidak perlu;
- detail/history lazy-loaded;
- stable keys;
- pagination/filter state tidak menghilangkan context.

### 14.3 Monitoring

Tambahkan metric/log terstruktur sesuai pattern project:

- submit-to-review time;
- review duration;
- revision count dan frequent error fields;
- ESB link/create/failure/reconciliation;
- Telegram delivery/retry;
- sourcing duration dan sample cycles;
- approval-to-pricing duration;
- pricing success/failure/verification;
- completion count;
- correlation ID tanpa secret.

Integrasikan mutation ESB/Telegram/Pricelist ke System Monitoring existing bila boundary monitoring saat ini mendukungnya. Jangan membuat dashboard paralel bila dapat direuse.

## 15. UI consistency checklist

Ikuti `docs/standards/ui-consistency.md`:

- gunakan container berborder dan radius existing, tanpa shadow berlebihan;
- satu aksi utama per area;
- status memiliki label teks dan warna semantik;
- Draft gray, waiting/revision amber, syncing/info blue, success green, failure red;
- index server-side dengan filter yang jelas dan pagination;
- detail dikelompokkan berdasarkan tugas user, bukan nama tabel;
- form panjang memakai halaman/section, bukan modal bertumpuk;
- modal hanya untuk aksi sederhana dan memenuhi focus/Escape/accessibility pattern existing;
- label dan error terlihat;
- attachment menunjukkan preview/progress/validation;
- mobile stack vertikal dan tidak horizontal page scroll;
- icon-only action mempunyai accessible label;
- mutation mempunyai loading dan duplicate-submit protection;
- empty state menjelaskan tindakan berikutnya;
- dark variant mengikuti panel existing bila relevan.

Jangan snapshot-test seluruh class Tailwind. Uji struktur/behavior penting.

## 16. Test matrix wajib

Gunakan Pest dan factory/state existing. Buat feature test sebagai default; unit test untuk service/state transition murni bila memberi nilai.

### 16.1 Review IT

- R&D save Draft;
- submit valid;
- invalid unit/code rejected;
- record locked after submit;
- IT claim;
- IT technical edit + audit diff;
- return reason required;
- R&D revise/resubmit version baru;
- stale approval rejected;
- unauthorized actions 403;
- double-submit idempoten.

### 16.2 Telegram

- after-commit dispatch;
- rollback no dispatch;
- success/failure/timeout;
- unique delivery;
- retry/backoff;
- disabled config;
- message and URL;
- secret redaction.

### 16.3 ESB

- existing exact product link;
- ambiguous duplicate reconciliation;
- create product once;
- read-back success;
- complete Product Detail mapping;
- partial mapping not synced;
- timeout unknown/reconciliation;
- safe retry;
- stale IT approval blocked;
- old bypass endpoint/button blocked;
- conversion factor replacement regression.

### 16.4 Sourcing/sample

- raw synced creates one request;
- WIP creates no request;
- supplier candidate CRUD sesuai izin;
- handover validation/photo;
- receipt validation/photo;
- approve creates one Pricing Request;
- reject creates none;
- resample creates next cycle and preserves history;
- reopen permission/conflict;
- private attachment authorization.

### 16.5 Finance

- raw not approved hidden;
- raw approved visible;
- WIP synced visible;
- WIP unsynced hidden;
- correct access scope;
- Product Detail read-only/server-validated;
- price validation;
- manual verification flow;
- API success/timeout/reconciliation jika phase API aktif;
- no duplicate Pricing Request;
- offer price distinct from official price.

### 16.6 Completion

- raw needs synced + approved sample + priced;
- WIP needs synced + not required + priced;
- Draft pricing not complete;
- failure/verification not complete;
- Completed cannot be set manually;
- completion notification once;
- legacy data not falsely completed.

### 16.7 Regression/performance

- existing sourcing and standalone sourcing;
- R&D product/BOM material use;
- Product Price Index unaffected;
- Finance navigation permission;
- query count/N+1 critical pages;
- pagination/filter retention;
- responsive critical markup/state;
- route discovery and smoke tests.

## 17. Verification commands

Gunakan command minimum yang relevan, contoh:

```bash
php artisan test --compact tests/Feature/<FocusedTest>.php
php artisan test --compact --filter=<focused_name>
vendor/bin/pint --dirty --format agent
```

Kemudian jalankan kelompok test modul yang terdampak. Jangan otomatis menjalankan full suite sangat lama jika pipeline project memang membaginya; laporkan test yang dijalankan dan yang belum dijalankan. Jika perubahan frontend membutuhkan build, gunakan script package yang terbukti dari `package.json`.

Jalankan juga:

- `git diff --check`;
- route/Filament discovery check yang relevan;
- migration test pada database test baru/terkontrol;
- HTTP fake assertion no stray request;
- query/performance check untuk index baru.

## 18. Urutan kerja yang disarankan

Kerjakan dalam perubahan kecil dan dapat direview:

1. Audit + characterization tests.
2. Enum/schema/factory additive.
3. Review IT Action + inbox/detail UI.
4. Telegram delivery.
5. Gate seluruh push ESB + extraction Action/Service.
6. Link existing/read-back/reconciliation.
7. Sourcing Request compatibility.
8. Sample Cycle + private attachment.
9. Pricing eligibility.
10. Finance Pricelist UI + manual controlled mode.
11. API Pricelist hanya bila contract terbukti.
12. Completion guard + notifications.
13. Backfill dry-run + legacy compatibility.
14. Regression, performance, formatting, build, dan implementation report.

Setelah setiap tahap, jalankan test terfokus dan periksa diff agar perubahan tidak melebar.

## 19. Batas penghentian dan keputusan aman

Jangan menghentikan seluruh implementasi hanya karena API Pricelist atau Telegram credential belum tersedia.

- Credential Telegram tidak diperlukan untuk test: gunakan fake/config placeholder dan selesaikan kode aman.
- Chat ID final dapat menjadi konfigurasi deployment; jangan hard-code.
- Jika write API Pricelist belum terbukti, selesaikan mode manual terkontrol.
- Jika schema/data menunjukkan migration berisiko, buat migration additive dan command dry-run; jangan menjalankan mutation data.
- Jika menemukan perubahan user overlap, pahami diff dan integrasikan tanpa menghapusnya; minta arah hanya bila benar-benar tidak dapat dipisahkan secara aman.
- Jika menemukan endpoint push lama yang berbahaya, tutup melalui shared authorization/gate dan regression test, bukan hanya menyembunyikan tombol.

## 20. Handoff akhir

Pada akhir pekerjaan, berikan laporan ringkas tetapi lengkap:

1. outcome yang sudah bekerja;
2. file/domain utama yang berubah;
3. migration/schema yang ditambahkan;
4. status compatibility data lama;
5. status Telegram dan konfigurasi deployment yang diperlukan tanpa membocorkan secret;
6. status integrasi Pricelist: manual controlled atau API verified;
7. test yang dijalankan beserta hasil;
8. formatter/build/check yang dijalankan;
9. blocker atau keputusan bisnis yang masih terbuka;
10. langkah rollout staging yang aman;
11. konfirmasi bahwa tidak ada commit, push, deploy, atau mutation production yang dilakukan tanpa instruksi.

Jangan menyatakan selesai jika salah satu kondisi berikut masih terjadi:

- push ESB masih dapat dilakukan tanpa review IT;
- approval stale masih dapat dipakai;
- Telegram dapat terkirim ganda untuk submission yang sama;
- Product Detail mapping parsial dianggap synced;
- bahan belum approved muncul di Finance;
- WIP dipaksa membuat sourcing/sample;
- offer price dianggap Pricelist resmi;
- Completed dapat dipilih manual;
- timeout eksternal langsung di-retry dan berpotensi menggandakan mutation;
- data lama memperoleh histori palsu;
- authorization hanya mengandalkan tombol tersembunyi;
- test critical path belum tersedia atau gagal.
```
