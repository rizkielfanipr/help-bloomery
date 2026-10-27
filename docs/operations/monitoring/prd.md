# PRD — System Monitoring Help Bloomery

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 20 Oktober 2026 |
| Status | Rancangan untuk review; belum mengotorisasi implementasi, migration production, atau deployment |
| Modul | System Monitoring |
| Prioritas | P1 |
| Target pengguna | Tim IT, SUPERADMIN, dan Management dengan akses terbatas |
| Stack existing | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Alpine.js 3, Tailwind CSS 4, Pest 4 |
| Acuan kode | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |
| Strategi | Modular monolith, server-driven UI, implementasi bertahap, tanpa frontend framework baru |

Dokumen ini mendefinisikan sistem monitoring internal untuk aplikasi Help Bloomery. Fokus awal adalah observability transaksi ESB dan kesehatan proses aplikasi, bukan sekadar pemeriksaan apakah halaman website dapat dibuka.

Dokumen ini tidak memberi izin untuk mengirim transaksi ESB production, membuka secret, memasang dependency, mengubah konfigurasi VPS, menjalankan migration production, atau melakukan deployment. Kondisi repository dan infrastruktur wajib diperiksa kembali pada awal implementasi.

## 2. Latar belakang

Help Bloomery menjalankan banyak proses penting di dalam satu aplikasi, antara lain:

- Master Product dan Product Detail ESB;
- BOM ESB;
- Bulk Product dan Promotion;
- Item Journal dan attachment;
- Goods Receipt;
- queue, scheduler, cache, storage, database, dan deployment;
- proses bisnis lokal yang bergantung pada data ESB.

Saat terjadi masalah, tim perlu menjawab pertanyaan berikut dengan cepat:

1. Apakah aplikasi, database, queue, scheduler, storage, dan ESB sedang sehat?
2. Transaksi apa yang gagal, tidak pasti, atau hanya berhasil sebagian?
3. Siapa yang menjalankan transaksi, dari modul mana, untuk Branch dan company code apa?
4. Apakah request aman untuk dicoba ulang atau harus direkonsiliasi terlebih dahulu?
5. Apakah error mulai terjadi setelah deployment tertentu?
6. Berapa lama gangguan berlangsung dan siapa yang menanganinya?

Saat ini bukti tersebut tersebar pada tabel domain, log, GitHub Actions, dan pemeriksaan server manual. Sistem monitoring diperlukan untuk menyatukan informasi operasional tanpa menjadikan monitoring sebagai sumber kebenaran baru bagi data bisnis.

## 3. Tujuan

1. Menyediakan satu halaman untuk melihat kesehatan sistem secara cepat.
2. Menyediakan jejak terpusat untuk seluruh mutation ESB yang berasal dari UI aplikasi.
3. Membedakan `failed` dari hasil eksternal yang `unknown`.
4. Mencegah retry buta yang dapat menggandakan transaksi.
5. Menampilkan queue, scheduler, deployment, error, dan kapasitas secara terukur.
6. Mengirim notifikasi hanya untuk kondisi yang memerlukan tindakan.
7. Menghubungkan insiden teknis dengan Permintaan IT tanpa menduplikasi workflow tiket.
8. Menjaga dampak monitoring terhadap performa aplikasi tetap kecil dan terukur.

## 4. Ukuran keberhasilan

- Seluruh mutation ESB dari UI terdaftar dalam satu transaction monitor.
- Setiap transaksi mempunyai correlation ID, modul sumber, actor, target, status, waktu, dan ringkasan hasil.
- Status `unknown` tidak dapat di-retry otomatis.
- Health check kritis berjalan terjadwal dan mempunyai heartbeat.
- Gangguan baru menghasilkan satu alert, bukan notifikasi berulang setiap interval.
- Recovery menghasilkan notifikasi penutupan yang dapat dikaitkan dengan alert awal.
- Dashboard utama terbuka tanpa menjalankan HTTP ESB secara langsung.
- Payload monitoring tidak menyimpan credential, token, password, atau file binary.
- Data lama dan workflow modul existing tetap valid selama rollout bertahap.
- Test memastikan authorization, branch/company isolation, deduplication, retention, retry safety, dan sanitasi.

## 5. Prinsip produk dan arsitektur

1. **Monitoring bukan sumber data bisnis.** Data domain tetap berada pada tabel domain seperti Item Journal, Goods Receipt, BOM, atau Master Product.
2. **External failure is normal.** Timeout dan connection reset tidak otomatis berarti transaksi gagal.
3. **Mutation harus dapat ditelusuri.** Setiap pengiriman mempunyai correlation ID yang konsisten dari UI sampai log.
4. **Tidak ada HTTP eksternal saat render dashboard.** Dashboard membaca snapshot lokal terakhir.
5. **Retry hanya jika terbukti aman.** Status `unknown` wajib melalui rekonsiliasi.
6. **Sanitize by default.** Monitoring menyimpan metadata minimum dan payload tersanitasi.
7. **Alert berdasarkan perubahan status.** Pemeriksaan berulang tidak menghasilkan spam.
8. **Server-driven UI.** Gunakan Filament, Livewire, Blade, dan Tailwind existing.
9. **No speculative infrastructure.** MVP tidak membutuhkan microservice, Vue, React, atau platform observability baru.
10. **Progressive rollout.** Satu integrasi dipindahkan pada satu waktu dengan characterization test.

## 6. Scope

### 6.1 In scope

- Navigation group `System Monitoring` pada back office.
- Overview kesehatan sistem.
- ESB Transaction Monitor.
- Service Health dan heartbeat.
- Queue dan Scheduler Monitor.
- Error Monitor backend dan browser secara bertahap.
- Deployment Monitor.
- Server/Capacity snapshot.
- Incident Center.
- Notifikasi in-app, WhatsApp, dan email sesuai konfigurasi existing.
- Permission, Policy, retention, sanitasi, audit trail, export terbatas, dan automated tests.

### 6.2 Out of scope MVP

- Mengganti Laravel dengan frontend framework baru.
- Microservice monitoring terpisah.
- APM berbayar atau dependency monitoring baru tanpa persetujuan.
- Remote shell, terminal, restart service, atau eksekusi command arbitrer dari UI.
- Menampilkan `.env`, credential, token, password, cookie, authorization header, atau raw personal data.
- Menjadikan dashboard monitoring sebagai mekanisme retry massal.
- Menghapus log atau data domain production dari UI.
- Menjalankan database repair otomatis.
- Monitoring perangkat jaringan fisik, CCTV, atau mesin produksi.
- Public status page untuk pelanggan.

## 7. Pengguna dan akses

| Pengguna | Akses |
| --- | --- |
| SUPERADMIN | Seluruh monitoring, konfigurasi, detail sanitasi, dan incident management |
| IT | Overview, service health, transaksi, error, deployment, incident, dan aksi rekonsiliasi yang diizinkan |
| Management | Overview read-only, uptime, tren insiden, tanpa payload teknis |
| User operasional | Tidak mendapat menu monitoring |

Permission minimum:

- `view system monitoring`;
- `view monitoring technical details`;
- `view monitoring sanitized payloads`;
- `manage monitoring incidents`;
- `acknowledge monitoring alerts`;
- `reconcile esb transactions`;
- `retry safe esb transactions`;
- `export monitoring reports`;
- `manage monitoring settings`.

Permission tidak boleh hanya mengatur visibilitas tombol. Seluruh Page, query, detail record, export, dan action wajib memeriksa authorization di server.

## 8. Struktur navigasi

```text
System Monitoring
├── Overview
├── Service Health
├── ESB Transactions
├── Queue & Scheduler
├── Errors
├── Deployments
├── Incidents
└── Settings
```

Menu dapat dirilis bertahap. Item navigasi yang belum mempunyai implementasi lengkap tidak ditampilkan.

## 9. Overview

Overview menjawab kondisi sistem tanpa membuka detail teknis.

### 9.1 Kartu status

- Application;
- Database;
- Cache;
- Queue worker;
- Scheduler;
- Object storage;
- ESB authentication;
- Master Product ESB;
- BOM ESB;
- Item Journal ESB;
- Goods Receipt ESB;
- Promotion ESB;
- deployment production terakhir;
- backup terakhir, jika sumber datanya sudah tersedia dan tervalidasi.

Status standar:

| Status | Makna |
| --- | --- |
| `healthy` | Pemeriksaan berhasil dalam ambang normal |
| `degraded` | Masih tersedia tetapi lambat atau sebagian bermasalah |
| `down` | Tidak tersedia atau pemeriksaan kritis gagal |
| `unknown` | Belum pernah diperiksa atau bukti tidak cukup |
| `maintenance` | Sengaja dalam masa pemeliharaan |

### 9.2 Ringkasan operasional

- error 1 jam dan 24 jam terakhir;
- transaksi ESB `failed`, `unknown`, dan `partial`;
- queue pending dan failed;
- scheduler heartbeat terakhir;
- insiden terbuka;
- tren kesehatan 24 jam dan 7 hari.

Dashboard membaca aggregate/snapshot lokal. Tidak boleh melakukan query log besar atau panggilan ESB langsung pada render.

## 10. Service Health

### 10.1 Jenis pemeriksaan

| Pemeriksaan | Metode | Contoh interval |
| --- | --- | --- |
| Application | Endpoint health internal tanpa autentikasi sensitif | 1 menit |
| Database | Query ringan seperti `SELECT 1` | 1 menit |
| Cache | Write/read key sementara dengan namespace monitoring | 1–5 menit |
| Queue | Heartbeat job | 1 menit |
| Scheduler | Heartbeat command | 1 menit |
| Storage | Pemeriksaan konfigurasi atau file probe terkontrol | 5–15 menit |
| ESB auth | Authentication/read endpoint aman | 5 menit |
| ESB domain service | Read endpoint ringan per layanan | 5 menit |
| SSL | Tanggal kedaluwarsa sertifikat | 12 jam |
| Disk/server | Snapshot lokal dari sumber yang disetujui | 5 menit |

Interval merupakan default rancangan, bukan hardcoded contract. Nilainya ditempatkan pada config, bukan akses `env()` langsung dari class.

### 10.2 Data hasil check

- check key dan display name;
- environment;
- status;
- checked at;
- response time milliseconds;
- HTTP status bila relevan;
- sanitized message;
- consecutive failures/successes;
- incident ID aktif bila ada;
- metadata aman.

### 10.3 Aturan status

- Satu kegagalan sementara dapat menjadi `degraded` sesuai konfigurasi check.
- `down` dapat membutuhkan beberapa kegagalan berturut-turut untuk mencegah false positive.
- Recovery dapat membutuhkan beberapa keberhasilan berturut-turut.
- Maintenance window menahan alert tetapi tetap menyimpan hasil check.
- Check timeout harus lebih pendek dari interval scheduler.

## 11. ESB Transaction Monitor

### 11.1 Cakupan mutation UI existing

| Modul | Operasi |
| --- | --- |
| R&D Product Release | Create/update Master Product dan replacement Product Detail |
| R&D BOM | Create/update BOM dan inline BOM update |
| BOM Adjustment | Update dan reconciliation BOM |
| Bulk Data Product | Create/update Master Product multi-comcode |
| Bulk Data Promotion | Create Promotion per comcode |
| Item Journal | Create jurnal, upload, dan delete attachment |
| Goods Receipt | Create Goods Receipt |

Operasi read-only seperti `Sync Branch ESB`, refresh katalog, Product Price Index, dan mengambil Purchase Order tidak dicatat sebagai mutation. Read operation dapat memiliki metric agregat, tetapi tidak memenuhi tabel transaksi utama.

### 11.2 Status transaksi

| Status | Makna | Retry |
| --- | --- | --- |
| `pending` | Sudah dicatat tetapi belum diproses | Tidak dari UI umum |
| `processing` | Sedang dikirim | Tidak |
| `succeeded` | Keberhasilan terverifikasi | Tidak |
| `failed` | ESB memberikan kegagalan eksplisit dan aman untuk dievaluasi | Hanya jika use case menyatakan aman |
| `unknown` | Hasil mutation tidak dapat dipastikan | Tidak; rekonsiliasi wajib |
| `partial` | Sebagian target multi-company berhasil | Retry hanya target gagal yang aman |
| `reconciled` | Status akhir dipastikan melalui pemeriksaan | Tidak |
| `cancelled` | Dibatalkan sebelum side effect eksternal | Tidak |

### 11.3 Metadata transaksi

- UUID/correlation ID;
- environment;
- source module dan source action;
- operation type;
- HTTP method dan endpoint template, bukan URL berisi secret;
- company code dan Branch bila relevan;
- local model type dan ID;
- external reference/result ID;
- actor user ID, role snapshot, dan Branch context;
- status, attempt count, started/completed/last verified at;
- duration milliseconds;
- request fingerprint/idempotency key bila tersedia;
- sanitized request summary;
- sanitized response summary;
- error class, safe error code, dan safe message;
- parent transaction ID untuk multi-company/dua tahap;
- deploy commit aktif jika tersedia.

### 11.4 Parent dan attempt

Satu tindakan pengguna dapat mempunyai beberapa attempt atau child transaction, misalnya:

- Promotion untuk beberapa comcode;
- Bulk Product untuk beberapa target company;
- replacement Product Detail: nonaktifkan lama lalu buat detail baru;
- Item Journal kemudian upload beberapa attachment.

Parent menyimpan status bisnis keseluruhan. Setiap HTTP mutation disimpan sebagai attempt/child agar partial success dapat ditelusuri tanpa menggabungkan response yang berbeda.

### 11.5 Reconciliation

Reconciliation wajib bersifat domain-specific:

- Master Product: cari berdasarkan Product ID atau identitas yang terbukti unik;
- Product Detail: periksa ID, UOM, conversion factor, SKU, dan active flag;
- BOM: ambil detail berdasarkan BOM ID dan bandingkan snapshot/fingerprint;
- Item Journal dan Goods Receipt: periksa nomor referensi atau endpoint lookup yang tersedia;
- Promotion: periksa external ID/identitas target jika API mendukung.

Jika tidak tersedia endpoint pembuktian, UI tidak boleh mengklaim transaksi gagal. Status tetap `unknown` dan membutuhkan pemeriksaan manual.

### 11.6 Retry

- Tidak ada retry otomatis untuk POST/PUT/DELETE yang hasilnya `unknown`.
- Retry `failed` hanya tersedia bila domain action mendeklarasikan retry-safe.
- Retry membuat attempt baru dan mempertahankan attempt lama.
- Tombol mempunyai confirmation yang menjelaskan risiko.
- Double-click dilindungi oleh atomic claim/lock server-side, bukan hanya disabled button.
- Bulk retry hanya mengirim target gagal; target sukses tidak dikirim ulang.

## 12. Queue dan Scheduler Monitor

### 12.1 Queue

Tampilkan:

- heartbeat worker;
- pending jobs bila driver mendukung;
- failed jobs;
- oldest pending age;
- throughput ringkas;
- job type, queue, attempts, exception aman, dan waktu gagal.

MVP tidak menyediakan restart worker. Retry failed job hanya boleh ditambahkan untuk job yang sudah terbukti idempotent dan memiliki authorization eksplisit.

### 12.2 Scheduler

Setiap scheduled task kritis mempunyai:

- task key;
- jadwal expected;
- started/finished at terakhir;
- status dan durasi;
- next expected run;
- late threshold;
- overlap indicator;
- safe output summary.

Heartbeat scheduler dianggap terlambat bila melewati ambang yang ditentukan. Jangan hanya menyimpulkan scheduler sehat karena aplikasi dapat dibuka.

## 13. Error Monitor

### 13.1 Backend

Catat metadata exception yang aman:

- fingerprint;
- exception class;
- sanitized message;
- route/page/action;
- HTTP status;
- first seen, last seen, occurrence count;
- user/Branch/company context bila diizinkan;
- correlation ID;
- deployment commit;
- status triage.

Stack trace lengkap hanya dapat diakses role teknis dan tetap harus melalui sanitasi. Error validasi normal tidak dicatat sebagai insiden kecuali melewati threshold anomali.

### 13.2 Browser

Browser error ditambahkan setelah backend monitoring stabil. Data minimum:

- message dan fingerprint;
- halaman;
- browser/device ringkas;
- Livewire request/correlation ID bila tersedia;
- first/last seen dan count.

Jangan merekam input form, DOM penuh, screenshot otomatis, cookie, local storage, atau data pribadi.

### 13.3 Triage

Status error:

- New;
- Acknowledged;
- Investigating;
- Resolved;
- Ignored.

Ignored membutuhkan alasan, actor, dan tanggal. Error yang muncul lagi setelah resolved dapat membuka occurrence baru atau menandai regression tanpa menghapus histori.

## 14. Deployment Monitor

Data deployment minimum:

- environment;
- branch;
- commit SHA dan message;
- actor bila tersedia;
- workflow/run ID;
- started/finished at dan duration;
- status;
- migration status;
- post-deploy health status;
- sanitized failure stage.

Sumber data dapat berasal dari callback deployment yang ditandatangani, file metadata release di VPS, atau mekanisme lain yang disetujui saat implementasi. Jangan memberikan akses GitHub token ke browser.

Peringatan:

- production commit tidak diketahui;
- post-deploy health check gagal;
- migration pending;
- queue worker belum memuat release terbaru jika dapat dibuktikan;
- working tree server kotor jika data tersebut diberikan oleh deploy script;
- deployment berjalan melebihi threshold.

MVP hanya read-only. Rollback atau deploy ulang tidak dilakukan dari UI monitoring.

## 15. Server dan kapasitas

Metrik minimum bila sumber aman tersedia:

- CPU/load average;
- memory dan swap;
- disk usage;
- database size;
- ukuran log;
- object storage/upload growth bila API mendukung;
- SSL expiry;
- waktu backup terakhir dan statusnya.

Threshold awal:

- disk warning pada 75%;
- disk critical pada 90%;
- SSL warning 30 hari dan critical 14 hari sebelum kedaluwarsa;
- backup warning jika melewati SLA backup yang disepakati.

Nilai threshold berada pada konfigurasi tervalidasi. UI tidak boleh menjalankan command shell arbitrer untuk mengambil metrik.

## 16. Incident Center

Incident dapat dibuat otomatis dari alert atau manual oleh IT.

Data:

- incident number;
- title dan summary;
- severity;
- affected services;
- environment;
- detected/acknowledged/resolved at;
- owner/PIC;
- current status;
- linked alerts, errors, transactions, deployment, dan IT request;
- timeline immutable;
- root cause;
- resolution;
- preventive action.

Status:

```text
Open → Acknowledged → Investigating → Monitoring → Resolved
```

Reopen diperbolehkan dan tercatat pada timeline. Incident tidak mengubah status transaksi bisnis secara otomatis.

Severity:

- SEV-1: layanan utama down atau risiko data luas;
- SEV-2: fungsi penting terganggu tanpa workaround memadai;
- SEV-3: degradasi terbatas atau ada workaround;
- SEV-4: informasi/peringatan non-mendesak.

## 17. Alert dan notifikasi

Channel:

- in-app notification;
- WhatsApp untuk critical setelah konfigurasi provider disetujui;
- email sebagai fallback/rekap.

Aturan anti-spam:

1. Alert dikirim saat state berubah, bukan setiap check.
2. Satu fingerprint layanan hanya mempunyai satu alert aktif.
3. Reminder dikirim setelah durasi tertentu jika belum diakui.
4. Recovery notification dikirim ketika layanan stabil kembali.
5. Maintenance window menahan alert tetapi tidak menghentikan pencatatan.
6. Deduplication key mencakup environment, service, check key, dan error class yang sesuai.

Notifikasi tidak menyertakan secret, raw payload, stack trace penuh, atau personal data. Tautan detail tetap melewati authorization aplikasi.

## 18. Struktur data usulan

Nama tabel final harus diverifikasi terhadap schema saat implementasi.

### 18.1 `monitoring_services`

- `id`;
- `key`, unique per environment;
- `name`, `group`;
- `environment`;
- `is_enabled`, `is_critical`;
- `check_interval_seconds`;
- `timeout_seconds`;
- `failure_threshold`, `recovery_threshold`;
- `last_status`, `last_checked_at`;
- `last_response_time_ms`;
- `last_safe_message`;
- timestamps.

### 18.2 `monitoring_check_results`

- `id`;
- `monitoring_service_id`;
- `status`;
- `checked_at`;
- `response_time_ms`;
- `http_status` nullable;
- `safe_message` nullable;
- `metadata` JSON tersanitasi;
- index service + checked_at.

### 18.3 `monitoring_transactions`

- `id`, UUID public identifier;
- `parent_id` nullable;
- `environment`;
- `source_module`, `source_action`, `operation_type`;
- `company_code`, `branch_id` nullable;
- `local_model_type`, `local_model_id` nullable;
- `external_reference` nullable;
- `correlation_id`;
- `idempotency_key`/`request_fingerprint` nullable;
- `method`, `endpoint_template`;
- `status`;
- `attempt_count`;
- `request_summary`, `response_summary` JSON tersanitasi;
- `error_class`, `error_code`, `safe_error_message` nullable;
- `actor_id` nullable;
- `deploy_commit` nullable;
- `started_at`, `completed_at`, `verified_at` nullable;
- `duration_ms` nullable;
- timestamps.

Gunakan morph reference hanya jika benar-benar diperlukan. Jangan membuat foreign key polymorphic yang menyulitkan retention tanpa kebutuhan nyata.

### 18.4 `monitoring_errors`

- fingerprint dan exception class;
- route/action/module;
- safe message;
- occurrence count;
- first/last seen;
- status, severity;
- correlation ID terakhir;
- actor triage dan timestamps;
- metadata tersanitasi.

### 18.5 `monitoring_incidents` dan `monitoring_incident_events`

Incident menyimpan keadaan terakhir. Event menyimpan timeline append-only. Perubahan status, owner, catatan, link, dan resolution ditulis sebagai event.

### 18.6 `monitoring_deployments`

- environment, branch, commit SHA/message;
- actor/provider/run ID;
- status dan stage;
- started/finished at, duration;
- health status;
- metadata aman.

## 19. Retention dan agregasi

Default awal yang harus dikonfirmasi saat implementasi:

| Data | Retention detail | Retention agregat |
| --- | --- | --- |
| Health check | 30 hari | Harian 12 bulan |
| Transaction metadata | 180 hari | Ringkasan bulanan 24 bulan |
| Sanitized payload | 30–90 hari berdasarkan risiko | Tidak wajib |
| Error occurrence | 90 hari | Fingerprint summary 12 bulan |
| Incident | Tidak dihapus otomatis | Tidak berlaku |
| Deployment | 24 bulan | Tidak berlaku |

Retention job menggunakan chunk/lazy processing dan tidak mengunci tabel besar. Penghapusan payload tidak menghapus status transaksi atau incident linkage.

## 20. Sanitasi dan keamanan

Field yang wajib disamarkan atau tidak disimpan:

- `Authorization`, token, password, secret, cookie, session;
- credential ESB dan object storage;
- file binary/base64;
- signed URL lengkap;
- nomor telepon/email jika tidak diperlukan;
- request headers mentah;
- raw SQL dengan binding sensitif;
- seluruh `.env`.

Gunakan allowlist field per domain, bukan hanya blacklist global. Sanitasi dilakukan sebelum persistence dan sebelum logging. UI tetap melakukan escaping output.

Payload detail hanya tersedia bagi permission khusus dan harus mempunyai audit access bila risiko dinilai tinggi.

## 21. UI/UX

UI mengikuti `docs/standards/ui-consistency.md`:

- container `rounded-2xl` dengan border tipis tanpa shadow berlebihan;
- satu aksi utama per area;
- status memakai badge dengan teks dan warna semantik;
- filter berada dekat tabel;
- tabel desktop dan kartu/agenda ringkas pada mobile bila kolom terlalu banyak;
- detail berat dimuat saat drawer/modal dibuka;
- loading, empty, error, dan stale-state berbeda jelas;
- timestamp menampilkan timezone Asia/Jakarta dan informasi relatif bila membantu;
- technical ID dapat disalin tetapi tidak mendominasi UI.

### 21.1 Overview

- grid status layanan;
- ringkasan transaksi dan error;
- grafik tren kecil yang benar-benar membantu;
- daftar insiden terbuka;
- tombol menuju detail, bukan tombol repair langsung.

### 21.2 Transaction table

Kolom minimum:

- waktu;
- modul/aksi;
- operasi;
- company/Branch;
- referensi;
- actor;
- status;
- durasi;
- aksi detail.

Filter:

- waktu;
- environment;
- modul;
- operation type;
- status;
- company code;
- Branch;
- actor;
- correlation ID atau external reference.

Detail menampilkan timeline attempt, sanitized request/response summary, linked record, dan keputusan retry/reconciliation.

### 21.3 Copywriting

Gunakan label yang membedakan arah data:

- `Tarik dari ESB` untuk read/sync inbound;
- `Kirim ke ESB` untuk create;
- `Simpan Perubahan ke ESB` untuk update;
- `Rekonsiliasi dengan ESB` untuk status tidak pasti;
- `Coba Ulang` hanya untuk kegagalan yang dinyatakan aman.

## 22. Arsitektur aplikasi target

```text
Filament Monitoring Pages
    → read model / query service
    → local monitoring tables + cache snapshots

Domain Action
    → MonitoringTransactionRecorder
    → ESB Integration Service
    → finalize succeeded / failed / unknown

Laravel Scheduler
    → Health Check Jobs
    → local snapshots
    → Alert Evaluator
    → Notification Dispatcher
```

### 22.1 Batas tanggung jawab

- Page/Resource mengatur presentation state, filter, dan authorization.
- Action mengoordinasikan use case dan transaction lifecycle.
- Integration Service mengirim request ESB dan memetakan error.
- Recorder tidak mengetahui Blade/Livewire.
- Health check bersifat read-only dan terisolasi per service.
- Notification dispatcher menerima alert yang sudah didedup.

Jangan membungkus HTTP call panjang dalam database transaction tanpa alasan. Record monitoring awal dapat disimpan sebelum request; hasil diselesaikan setelah request.

## 23. Performa dan reliability

- Index mengikuti filter utama dan waktu.
- Daftar menggunakan server-side pagination.
- Grafik membaca aggregate, bukan seluruh check result.
- Payload JSON tidak dimuat pada index.
- Cache overview mempunyai TTL singkat dan invalidation yang jelas.
- Check eksternal berjalan di queue khusus bila queue infrastructure sudah siap.
- Health check tidak boleh menghabiskan seluruh worker bisnis.
- Setiap check memiliki timeout, concurrency limit, dan overlap protection.
- Kegagalan sistem monitoring tidak boleh memblokir transaksi bisnis; recorder mempunyai fallback log aman.
- Monitoring database failure tidak boleh membuat mutation ESB otomatis dikirim ulang.

## 24. Rollout bertahap

### Phase 0 — Baseline dan audit

- Inventaris seluruh mutation ESB, read sync, queue, scheduler, deployment, dan error source.
- Catat endpoint, timeout, retry, status, idempotency, dan tabel domain existing.
- Buat characterization tests sebelum memasang recorder.
- Tetapkan sanitization allowlist per domain.

**Exit criteria:** seluruh mutation UI mempunyai owner, status model, dan risiko retry yang terdokumentasi.

### Phase 1 — ESB Transaction Monitor

- Buat transaction schema, enum, recorder, query, permission, index, dan detail.
- Integrasikan satu pilot: R&D Master Product/Product Detail.
- Lanjutkan BOM, Bulk Product, Promotion, Item Journal, dan Goods Receipt satu per satu.
- Tidak mengubah workflow bisnis pada phase instrumentasi.

**Exit criteria:** seluruh mutation UI tercatat tanpa menggandakan request atau mengubah hasil bisnis.

### Phase 2 — Service Health

- Application, database, cache, queue heartbeat, scheduler heartbeat, storage, dan ESB read checks.
- Overview dan state transition.
- In-app alert dan recovery.

**Exit criteria:** dashboard hanya membaca snapshot lokal dan alert tidak spam.

### Phase 3 — Queue, Scheduler, dan Errors

- Job/schedule run history.
- Backend error fingerprinting.
- Triage dan linkage ke transaction/deployment.
- Browser errors sebagai subphase terpisah.

### Phase 4 — Deployment dan Capacity

- Deploy callback/release metadata.
- Post-deploy health.
- Server capacity dan backup metadata dari sumber aman.

### Phase 5 — Incident Center dan WhatsApp

- Incident workflow dan timeline.
- Link ke Permintaan IT.
- WhatsApp untuk SEV-1/SEV-2 setelah anti-spam dan provider disetujui.
- Laporan uptime dan insiden bulanan.

## 25. Compatibility dan data lama

1. Tabel domain existing tidak dipindahkan ke monitoring.
2. Status lama tidak diubah hanya untuk mengisi histori monitoring.
3. Backfill transaction dilakukan hanya jika sumber data cukup untuk status yang benar.
4. Record lama yang tidak dapat dibuktikan diberi label `legacy/untracked`, bukan diasumsikan berhasil atau gagal.
5. Integrasi dilakukan per action; action yang belum dimigrasikan tetap berfungsi seperti sebelumnya.
6. Monitoring tidak menghapus log existing selama masa validasi.
7. Deployment production menggunakan additive migration terlebih dahulu; destructive cleanup membutuhkan approval terpisah.

## 26. Testing strategy

### 26.1 Unit

- fingerprint dan sanitasi;
- status transition;
- threshold failure/recovery;
- alert deduplication;
- retry eligibility;
- aggregation dan retention selection.

### 26.2 Feature/Livewire

- permission setiap halaman dan detail;
- Management tidak melihat payload teknis;
- filtering dan pagination;
- acknowledge/incident transition;
- retry dan reconciliation authorization;
- monitoring recorder tidak mengubah hasil domain action;
- double-submit menghasilkan satu mutation eksternal.

### 26.3 Contract integration

- HTTP fake untuk seluruh endpoint ESB;
- success, explicit failure, timeout, connection reset, malformed response, dan partial success;
- status `unknown` tidak di-retry;
- correlation ID diteruskan bila client mendukung;
- secret tidak tersimpan pada database atau log assertion.

### 26.4 Scheduler/queue

- heartbeat tepat waktu dan terlambat;
- overlap prevention;
- recovery after failure;
- failed monitoring job tidak memblokir queue bisnis;
- retention chunking.

### 26.5 UI

- desktop dan mobile viewport;
- empty/loading/error/stale state;
- tabel tidak menyebabkan page-level horizontal scroll;
- modal detail dapat diakses keyboard;
- status tidak mengandalkan warna saja.

## 27. Acceptance criteria MVP

1. User tanpa permission tidak dapat membuka route monitoring secara langsung.
2. Overview menampilkan snapshot lokal application, database, cache, queue, scheduler, storage, dan ESB.
3. Seluruh mutation R&D Master Product dicatat dengan correlation ID dan status akhir.
4. Timeout mutation menghasilkan `unknown`, bukan `failed` dan bukan automatic retry.
5. Double-click tidak menghasilkan dua request ESB.
6. Payload tersimpan tidak mengandung token, password, authorization header, signed URL, atau file binary.
7. Health check yang gagal berulang membuka satu alert aktif.
8. Recovery menutup alert aktif dan mencatat durasi gangguan.
9. Dashboard tidak memanggil endpoint ESB saat render.
10. Index transaction menggunakan pagination dan filter server-side.
11. Detail transaction memperlihatkan attempt history dan linked record sesuai permission.
12. Monitoring failure tidak membatalkan transaksi bisnis yang sebenarnya berhasil.
13. Retention dapat dijalankan berulang tanpa error atau menghapus incident history.
14. Test terdampak lulus dan Pint dijalankan untuk file PHP yang berubah.

## 28. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Monitoring menambah beban database | Aggregate, pagination, index, retention, payload lazy-load |
| Secret masuk log | Allowlist sanitizer, tests, restricted permission |
| Alert spam | State transition, threshold, dedup key, maintenance window |
| Retry menggandakan transaksi | Unknown state, domain reconciliation, atomic claim |
| Monitoring gagal dan memblokir bisnis | Recorder failure isolation dan fallback log |
| Dashboard memicu ESB outage lebih parah | Tidak ada live external call saat render |
| Status monitoring berbeda dari domain | Domain tetap source of truth; linkage dan reconciliation eksplisit |
| Scope terlalu besar | Rollout per phase dan per integration action |
| UI terlalu teknis | Management view terpisah dan progressive disclosure |

## 29. Pertanyaan keputusan sebelum implementasi

1. Siapa penerima WhatsApp untuk SEV-1 dan SEV-2?
2. Berapa SLA acknowledgement dan recovery yang disepakati?
3. Apakah staging dan production tampil dalam satu dashboard atau dipisah?
4. Berapa retention payload yang disetujui untuk audit?
5. Sumber resmi deployment metadata: GitHub callback atau release metadata VPS?
6. Sumber resmi CPU/disk/backup yang boleh dibaca aplikasi?
7. Apakah Management memerlukan laporan uptime bulanan dalam PDF/Excel?
8. Mutation mana setelah R&D Master Product yang menjadi pilot kedua?

Pertanyaan ini tidak menghalangi Phase 0 audit dan desain fondasi, tetapi harus diputuskan sebelum fitur terkait diaktifkan pada production.

## 30. Definition of done

Satu phase dianggap selesai jika:

- requirement dan batas data phase terpenuhi;
- authorization dan sanitasi diuji;
- mutation tidak bertambah jumlahnya akibat instrumentasi;
- timeout/retry/unknown scenario diuji;
- query dan performa mempunyai bukti yang memadai;
- UI mengikuti acuan konsistensi;
- dokumentasi operasi dan recovery diperbarui;
- rollout dapat dinonaktifkan tanpa merusak workflow bisnis;
- tidak ada migration production atau deployment tanpa approval terpisah.
