# Prompt Implementasi — System Monitoring Help Bloomery

Salin seluruh prompt berikut ke agent implementasi dari root repository.

```text
Implementasikan modul `System Monitoring` Help Bloomery secara end-to-end berdasarkan dokumen berikut:

- `docs/operations/monitoring/prd.md` — sumber requirement produk utama dan terbaru.
- `docs/standards/code-remediation.md` — guardrail arsitektur, integrasi eksternal, idempotency, performa, dan keamanan data.
- `docs/standards/ui-consistency.md` — standar UI, responsive behavior, modal, state, dark mode, dan accessibility.
- `docs/operations/production/stability-scalability-prd.md` — referensi batas infrastruktur, deployment, queue, scheduler, dan reliability.

Kerjakan sampai tuntas di repository ini. Jangan berhenti pada planning, audit, migration, atau contoh kode. Hasil akhir harus menyediakan fondasi monitoring yang aman dan berguna, dimulai dari ESB Transaction Monitor, kemudian Service Health, Queue & Scheduler, Error Monitor, Deployment Monitor, Incident Center, Settings, retention, alert, dan automated tests sesuai fase di bawah.

Gunakan modular monolith Laravel/Filament existing. Jangan membuat microservice, Vue, React, Inertia, atau aplikasi monitoring terpisah. Jangan menambahkan dependency tanpa persetujuan.

## 1. Aturan kerja wajib

1. Baca `AGENTS.md` dan seluruh dokumen sumber di atas sampai selesai sebelum mengubah kode.
2. Aktifkan skill Laravel, Pest, dan Tailwind project yang relevan. Baca rule architecture, database, migration, security, HTTP client, queue, scheduler, caching, validation, Eloquent, Blade, testing, dan style yang diperlukan.
3. Gunakan Laravel Boost `application-info` pada awal audit.
4. Sebelum perubahan kode, gunakan Laravel Boost `search-docs` dengan beberapa query broad dan package scope yang tepat. Verifikasi API Laravel 13, Filament 5, Livewire 4, Pest 4, scheduler, queue, notification, cache lock, database transaction, exception handling, dan polling yang digunakan.
5. Gunakan Boost `database-schema` sebelum menulis migration. Gunakan `database-query` hanya untuk inspeksi read-only. Jangan memakai Tinker untuk mutasi data bila test/factory cukup.
6. Audit `git status --short --branch`, `git diff --stat`, dan diff seluruh file terkait. Worktree mungkin berisi perubahan user. Pertahankan semuanya dan jangan memakai reset, checkout, clean, atau stash.
7. Periksa sibling files dan reuse pola repository. Jangan memperkenalkan arsitektur kedua bila Action, Service, Policy, Enum, Job, atau Filament Resource existing sudah memadai.
8. Gunakan `php artisan make:* --no-interaction` untuk file Laravel baru.
9. Jangan mengubah migration lama yang mungkin sudah dijalankan. Buat migration additive, reversible, dan forward-compatible.
10. Jangan menghapus test existing. Tambahkan characterization test sebelum memindahkan behavior kritis.
11. Jangan melakukan HTTP request nyata dalam automated tests. Gunakan `Http::fake()`, cegah stray request sesuai pola suite, dan jangan memakai credential production.
12. Jangan commit, push, membuat PR, deploy, menjalankan migration/data mutation staging/production, atau mengubah konfigurasi VPS tanpa instruksi terpisah.
13. Jangan membuat dokumentasi baru selain implementation report bila benar-benar diperlukan pada akhir pekerjaan.
14. Semua route, Page, query, detail, export, dan mutation wajib authorize server-side. Visibility tombol bukan authorization.
15. Jangan query database atau memanggil HTTP di Blade.
16. Jangan memanggil endpoint eksternal pada render dashboard monitoring.
17. Jangan menyimpan token, password, authorization header, cookie, session, signed URL lengkap, raw `.env`, binary/base64 file, atau request header mentah.
18. Gunakan allowlist sanitizer per domain, bukan hanya blacklist global.
19. Monitoring tidak boleh menjadi source of truth data bisnis. Tabel domain existing tetap authoritative.
20. Kegagalan recorder/monitoring tidak boleh otomatis menggagalkan transaksi bisnis yang sebenarnya berhasil.
21. Jangan membuat generic abstraction besar sebelum minimal dua consumer membuktikan kebutuhan. Fondasi boleh reusable, adapter domain tetap spesifik.
22. Jalankan test terfokus setelah setiap fase, bukan hanya di akhir.
23. Jika PHP berubah, jalankan `vendor/bin/pint --dirty --format agent` sebelum finalisasi.
24. Jika CSS/JS source berubah, jalankan build frontend repository dan laporkan hasilnya.
25. Jika requirement lama atau komentar kode bertentangan, `docs/operations/monitoring/prd.md` menjadi requirement untuk scope monitoring; workflow bisnis existing tetap dipertahankan kecuali PRD eksplisit menyatakan lain.
26. Jangan bertanya tentang hal yang dapat dibuktikan dari schema, kode, test, config, atau pola existing. Bila keputusan production-specific tidak dapat dibuktikan, implementasikan contract/config/validation yang aman, tampilkan `unknown/not configured`, dan laporkan satu blocker konfigurasi tanpa mengarang nilai.

## 2. Outcome akhir yang wajib dicapai

### 2.1 Navigation

Tambahkan group back office `System Monitoring` dengan halaman yang hanya tampil bila implementasinya sudah tersedia:

- Overview;
- Service Health;
- ESB Transactions;
- Queue & Scheduler;
- Errors;
- Deployments;
- Incidents;
- Settings.

Jangan menampilkan placeholder route yang rusak. Jika suatu sumber data belum dikonfigurasi, halaman tetap stabil dan menampilkan status `Unknown`/`Not Configured` dengan penjelasan aman.

### 2.2 Overview

Overview membaca snapshot lokal dan menampilkan:

- Application;
- Database;
- Cache;
- Queue heartbeat;
- Scheduler heartbeat;
- Object storage;
- layanan ESB yang dapat dibuktikan;
- transaksi `failed`, `unknown`, dan `partial`;
- error satu jam dan 24 jam;
- insiden terbuka;
- deployment production terakhir bila sumber tersedia;
- tren 24 jam dan 7 hari dari aggregate lokal.

Overview tidak boleh melakukan HTTP ESB, query log besar, shell command, atau request GitHub saat render.

### 2.3 ESB Transaction Monitor

Semua mutation UI berikut akhirnya harus diinstrumentasi:

1. R&D Product Release — create/update Master Product dan replacement Product Detail.
2. R&D BOM — create/update/inline update BOM.
3. BOM Adjustment — update dan reconciliation.
4. Bulk Data Product — create/update per comcode dan retry target gagal.
5. Bulk Data Promotion — create per comcode.
6. Item Journal — create, upload attachment, retry attachment, delete attachment.
7. Goods Receipt — create Goods Receipt.

Setiap tindakan harus memiliki parent transaction dan attempt/child mutation bila diperlukan. Simpan correlation ID, actor, modul, action, operation, company, Branch, local reference, external reference, endpoint template, method, status, timing, fingerprint/idempotency key, sanitized request/response summary, safe error, dan deploy commit bila tersedia.

Status final:

- `pending`;
- `processing`;
- `succeeded`;
- `failed`;
- `unknown`;
- `partial`;
- `reconciled`;
- `cancelled`.

Timeout/connection reset setelah mutation dimulai harus menghasilkan `unknown`, bukan `failed`. `unknown` tidak dapat di-retry otomatis.

### 2.4 Service Health

Implementasikan check lokal/eksternal melalui scheduler/job untuk:

- application;
- database;
- cache;
- queue heartbeat;
- scheduler heartbeat;
- object storage dengan probe aman;
- ESB authentication/read check yang ringan dan aman;
- SSL expiry atau server metrics hanya bila sumber aman tersedia.

Status: `healthy`, `degraded`, `down`, `unknown`, `maintenance`.

Gunakan consecutive failure/recovery threshold dan overlap protection. Hasil disimpan lokal. Alert hanya saat state berubah.

### 2.5 Queue & Scheduler

Tampilkan heartbeat, last run, duration, late/failed status, dan metadata aman. Jangan membuat terminal, remote shell, restart worker, atau arbitrary command execution dari UI. Retry job hanya boleh tersedia bila job terbukti idempotent dan mempunyai authorization khusus; jika belum terbukti, tampilkan read-only.

### 2.6 Errors

Implementasikan backend error fingerprinting yang menyimpan metadata aman, first/last seen, occurrence count, route/action/module, correlation ID, deployment commit, severity, dan triage status.

Jangan mengubah exception rendering/response user secara tidak sengaja. Browser error collection adalah subphase setelah backend stabil; jangan merekam DOM, form input, cookie, local storage, atau screenshot otomatis.

### 2.7 Deployments

Sediakan data model, Resource/Page, dan ingestion contract yang aman untuk deployment metadata:

- environment;
- branch;
- commit SHA/message;
- actor/provider/run ID;
- status/stage;
- started/finished/duration;
- migration status;
- post-deploy health;
- sanitized failure summary.

Gunakan signed endpoint atau release metadata sesuai bukti repository. Jangan mengarang GitHub token, jangan mengakses GitHub dari browser, dan jangan memberi tombol deploy/rollback pada MVP.

### 2.8 Incidents

Implementasikan incident dengan status:

`Open → Acknowledged → Investigating → Monitoring → Resolved`

Sediakan reopen dengan timeline. Simpan severity SEV-1 sampai SEV-4, affected services, environment, owner, timestamps, summary, root cause, resolution, preventive action, dan links ke alert/error/transaction/deployment/Permintaan IT bila relasi yang aman tersedia.

Incident event bersifat append-only. Incident tidak mengubah status transaksi bisnis secara otomatis.

### 2.9 Alerts dan Settings

Implementasikan alert deduplication berdasarkan environment + service/check/fingerprint. Kirim notification saat state berubah dan recovery. Gunakan in-app notification terlebih dahulu.

WhatsApp/email hanya diaktifkan bila provider/config existing terbukti tersedia. Jika belum, buat configuration contract dan UI `Not Configured` tanpa mengarang credential atau nomor tujuan.

Settings mengatur enablement, interval, timeout, thresholds, recipients, maintenance window, dan retention dalam batas tervalidasi. Secret tidak boleh diedit atau ditampilkan dari UI monitoring.

## 3. Phase 0 — Audit dan characterization

Lakukan audit read-only sebelum mengedit.

### 3.1 Worktree, stack, dan schema

1. Audit branch, status, diff, dan commit terakhir.
2. Jalankan Boost `application-info`.
3. Inspeksi schema terkait:
   - Item Journal;
   - Goods Receipt;
   - R&D Master Product material/unit;
   - R&D BOM/change log/catalog;
   - Bulk Product submissions/items;
   - Branch/company mapping;
   - Permission/roles;
   - notifications;
   - service requests/Permintaan IT;
   - jobs/failed jobs bila tersedia.
4. Audit cache, queue, logging, filesystem/object storage, scheduler, notification, GitHub/deployment config, dan timezone.
5. Jangan menampilkan nilai secret pada output audit.

### 3.2 Inventaris integrasi eksternal

Gunakan `rg` untuk menemukan seluruh:

- ESB client/service dan raw `Http` call;
- POST, PUT, PATCH, DELETE;
- retry/backoff;
- timeout/connect timeout;
- catch `ConnectionException`/`Throwable`;
- status `unknown`, `failed`, `partial`, `syncing`, `processing`;
- idempotency key/submission key;
- retry/reconcile button;
- attachment upload/delete;
- scheduled command dan queued job;
- deployment workflow/script.

Buat working inventory berisi module, UI trigger, service/action, endpoint, method, target company/Branch, local record, response ID, timeout, retry behavior, idempotency, status mapping, dan test coverage.

### 3.3 Baseline tests

Jalankan test paling relevan untuk:

- Master Product/Product Detail;
- BOM create/update/adjustment;
- Bulk Product;
- Bulk Promotion;
- Item Journal;
- Goods Receipt;
- branch access dan permissions;
- queue/scheduler bila ada.

Catat pre-existing failure. Jangan menyamarkannya sebagai hasil implementasi.

### 3.4 Characterization wajib

Sebelum instrumentasi, tambahkan/pertahankan test yang membuktikan:

- jumlah HTTP mutation tidak berubah;
- payload domain tidak berubah;
- timeout mapping existing;
- duplicate-submit protection existing;
- branch/company authorization;
- result/status domain existing;
- tidak ada request eksternal saat render halaman.

**Exit Phase 0:** seluruh mutation UI mempunyai owner, boundary, retry risk, status mapping, dan test baseline.

## 4. Phase 1 — Fondasi database dan domain monitoring

### 4.1 Enum/value objects

Buat enum sesuai konvensi project untuk:

- service status;
- transaction status;
- incident status;
- incident severity;
- error triage status;
- deployment status bila diperlukan.

Gunakan TitleCase enum keys dan string-backed values. Label/color berada pada enum/presenter yang sesuai, bukan tersebar di Blade.

### 4.2 Migration additive

Buat migration baru setelah schema audit. Target table minimum:

- `monitoring_services`;
- `monitoring_check_results`;
- `monitoring_transactions`;
- `monitoring_errors`;
- `monitoring_alerts` bila alert tidak cukup direpresentasikan oleh incident;
- `monitoring_incidents`;
- `monitoring_incident_events`;
- `monitoring_deployments`;
- `monitoring_settings` hanya bila config dinamis memang diperlukan.

Ikuti detail field pada PRD. Gunakan:

- UUID/correlation identifier yang tidak mudah ditebak untuk public reference;
- explicit index untuk environment/status/time/module/company/branch/correlation/fingerprint;
- foreign key dengan delete behavior yang mempertahankan audit (nullable/nullOnDelete bila actor/Branch dihapus);
- JSON hanya untuk metadata/payload sanitized, bukan field yang perlu difilter/index;
- timestamps timezone sesuai konvensi repository;
- unique constraint untuk dedup/invariant yang stabil.

Jangan menggunakan polymorphic FK secara otomatis. Jika linkage lintas model cukup dengan type + ID nullable tanpa FK, dokumentasikan melalui nama method/PHPDoc dan test.

### 4.3 Models dan factories

Setiap model harus mempunyai fillable/guarded, casts(), relationship ber-return type, scope query yang reusable, dan factory berguna. Factory tidak boleh membuat data eksternal nyata.

### 4.4 Permission dan Policy

Tambahkan permission sesuai PRD melalui seeder existing. Ikuti pola permission project agar rerun idempotent. Buat Policy/server-side checks untuk view technical detail, payload, incident management, retry, reconciliation, export, dan settings.

## 5. Phase 2 — Sanitizer dan transaction recorder

### 5.1 Sanitizer

Buat service sanitizer kecil dan teruji:

- global deny rules untuk secret umum;
- allowlist summary per domain;
- recursive handling untuk nested array;
- maximum depth/string/array size;
- masking identifier pribadi bila tidak diperlukan;
- binary/base64 detection;
- signed URL query stripping;
- deterministic output untuk testing/fingerprint.

Jangan menyimpan full raw request/response lalu menyensor saat render. Sanitasi sebelum persistence.

### 5.2 Recorder API

Sediakan API yang mudah dipakai Action/Service, misalnya lifecycle konseptual:

```text
start(parent context)
startAttempt(method, endpoint, sanitized summary)
markSucceeded(external reference, sanitized response)
markFailed(explicit safe failure)
markUnknown(ambiguous external outcome)
markPartial(child summaries)
markReconciled(result)
```

Nama final mengikuti konvensi project. Jangan memaksa seluruh domain mewarisi base class.

Recorder requirements:

- correlation ID dapat diteruskan atau dibuat;
- status transition tervalidasi;
- repeated finalize idempotent;
- duration dihitung server-side;
- actor/context nullable untuk scheduler/system;
- recorder exception dilaporkan tetapi tidak men-trigger ulang mutation domain;
- failure sebelum request boleh `failed/cancelled`; failure setelah request terkirim tetapi response tidak diketahui menjadi `unknown`;
- transaction parent dan attempt terpisah;
- database write kecil dan tidak membungkus HTTP call panjang dalam transaction.

### 5.3 Integration pattern

Instrumentasi dilakukan di Action/Service boundary yang mengetahui domain outcome, bukan di Blade. Client HTTP boleh menambahkan correlation header jika API mendukung, tetapi generic client tidak boleh menebak status bisnis.

## 6. Phase 3 — Instrumentasi mutation ESB bertahap

Kerjakan satu domain pada satu waktu. Setelah setiap domain, jalankan test domain dan assert jumlah request tetap sama.

### 6.1 Pilot: R&D Master Product/Product Detail

Instrumentasi:

- initial create/link;
- update biasa;
- two-phase conversion-factor replacement;
- duplicate-submit prevention;
- direct reconciliation berdasarkan Product ID/Product Detail ID;
- status `unknown` bila salah satu phase sudah mungkin bermutasi tetapi hasil akhir tidak pasti.

Parent transaction untuk replacement memiliki child attempt:

1. deactivate old detail;
2. verify deactivation (read; boleh dicatat sebagai timeline, bukan mutation attempt);
3. create replacement detail;
4. verify/reconcile replacement.

Jangan menghapus Product Detail historis. Modal tetap hanya menampilkan detail aktif canonical.

### 6.2 BOM

Instrumentasi create/update pada:

- Create BOM Recipe;
- edit BOM;
- inline Project BOM;
- BOM Adjustment;
- reconciliation.

Reuse change log/snapshot existing. Jangan membuat snapshot kompetitor. Connection failure setelah mutation menjadi `unknown`; explicit ESB validation menjadi `failed`. Create BOM yang ambiguous tidak boleh langsung diulang tanpa lookup/reconciliation yang terbukti.

### 6.3 Bulk Product

- Parent per submission;
- child per comcode;
- partial status dari child;
- retry hanya child `failed`;
- child `unknown` tidak di-retry;
- target succeeded tidak dikirim ulang.

Audit update conversion factor. Samakan replacement semantics dengan Master Product canonical; jangan PUT factor baru menggunakan Product Detail ID lama bila ESB tidak mendukungnya.

### 6.4 Bulk Promotion

- Parent per user submission;
- child per comcode;
- preserve sukses/gagal per target;
- sediakan local durable record bila flow existing belum mempunyai audit persistence yang cukup;
- retry hanya target gagal jika endpoint/identity membuktikan aman;
- bila tidak dapat dibuktikan, tampilkan reconciliation/manual review, bukan retry button.

### 6.5 Item Journal

- Reuse submission UUID dan payload hash existing;
- parent untuk jurnal;
- child untuk create dan attachment operations;
- retry attachment tidak membuat jurnal baru;
- delete attachment dicatat sebagai destructive mutation;
- `verification_required/unknown` domain dipetakan tanpa kehilangan status lokal existing.

### 6.6 Goods Receipt

- Reuse submission key/payload hash/status domain existing;
- simpan purchase number, company, Branch, Goods Receipt number;
- zero accepted quantity tidak membuat ESB mutation;
- connection failure menjadi unknown;
- explicit rejection menjadi failed;
- jangan retry otomatis create Goods Receipt.

**Exit Phase 3:** seluruh mutation UI pada PRD tercatat dan tidak ada tambahan request akibat monitoring.

## 7. Phase 4 — Service Health, queue, dan scheduler

### 7.1 Health check contract

Buat contract/class kecil per check dengan output seragam:

- key/name/group;
- status;
- checked at;
- response time;
- HTTP status nullable;
- safe message;
- sanitized metadata.

Gunakan config untuk interval, timeout, thresholds, dan enablement. `env()` hanya di file config.

### 7.2 Check implementation

Implementasikan secara bertahap:

- application internal check;
- DB lightweight query;
- cache probe dengan key namespace dan cleanup;
- scheduler heartbeat;
- queue heartbeat job;
- object storage probe yang tidak menulis file permanen;
- ESB read/auth checks dengan timeout ketat;
- SSL/server/backup hanya jika sumber aman terbukti.

ESB check tidak boleh membuat Product, BOM, Journal, Receipt, Promotion, atau mutation lain.

### 7.3 Scheduling

- gunakan scheduler existing;
- `withoutOverlapping()`;
- `onOneServer()` hanya jika cache lock infrastructure mendukung dan deployment memang multi-instance;
- check eksternal dapat menjadi queued job dengan queue khusus setelah audit;
- jangan membanjiri ESB saat outage;
- gunakan backoff/circuit behavior sederhana melalui threshold/cache bila diperlukan;
- tulis heartbeat scheduler secara lokal.

### 7.4 State evaluator

Implementasikan failure/recovery threshold, maintenance state, state transition, satu active alert per dedup key, recovery close, dan incident auto-create hanya untuk severity/config yang disepakati.

## 8. Phase 5 — Error monitoring

Integrasikan backend error collector melalui mekanisme Laravel 13 yang sesuai dokumentasi dan bootstrap existing.

Requirements:

- jangan mengubah response/exception rendering;
- fingerprint deterministik dari exception class + normalized message + route/top frame aman;
- throttle high-volume occurrence writes;
- increment count dan last seen tanpa membuat row per kejadian identik;
- correlation ID/link transaction jika tersedia;
- deploy commit dari config/release metadata;
- exclude validation/auth noise sesuai allowlist yang teruji;
- stack trace/detail teknis dibatasi permission dan disanitasi.

Browser error collector hanya dibuat bila phase backend stabil dan endpoint dapat diamankan dengan CSRF/auth/rate limit. Jangan merekam konten form atau DOM.

## 9. Phase 6 — Deployment ingestion dan capacity

### 9.1 Audit workflow deployment

Periksa `.github/workflows`, deploy script, release metadata, branch mapping staging/main, health command, migration, queue restart, dan working-tree checks.

### 9.2 Ingestion

Pilih mekanisme berdasarkan bukti:

- signed authenticated callback dari workflow; atau
- command lokal VPS yang menulis metadata melalui application context; atau
- release metadata file yang dibaca secara aman.

Jika signed HTTP endpoint digunakan:

- HMAC signature + timestamp/nonce;
- replay protection;
- rate limit;
- payload validation;
- secret dari config server, tidak tersedia pada frontend;
- test invalid signature, expired timestamp, replay, dan malformed payload.

Jangan memasang GitHub token ke aplikasi hanya untuk membaca status bila callback cukup.

### 9.3 Capacity

Implementasikan hanya metric yang dapat dibaca aman. Jangan menjalankan arbitrary shell dari request web. Jika metrics memerlukan deploy-side command, buat payload snapshot tervalidasi melalui ingestion contract yang sama.

## 10. Phase 7 — Incident Center dan notification

### 10.1 Incident actions

Buat Action terpisah untuk:

- create;
- acknowledge;
- assign owner;
- start investigation;
- enter monitoring;
- resolve;
- reopen;
- link/unlink related entities;
- add timeline note.

Setiap action authorize, validate transition, dan menulis append-only event. Gunakan DB transaction untuk perubahan incident + event.

### 10.2 Permintaan IT

Audit schema/service request existing. Link incident ke tiket existing bila relasi aman. Jangan menyalin seluruh tiket ke incident dan jangan otomatis menutup tiket saat incident resolved tanpa requirement terpisah.

### 10.3 Notification

- in-app dahulu;
- dedup berdasarkan alert state;
- recovery notification;
- recipient berdasarkan permission/config;
- WhatsApp/email hanya jika provider existing/configured;
- message tidak memuat secret/raw payload/stack trace;
- link memakai named route dan authorization.

## 11. Phase 8 — UI Filament/Livewire

Ikuti `docs/standards/ui-consistency.md`.

### 11.1 Overview

- header ringkas;
- status cards tanpa shadow berlebihan;
- tren yang benar-benar membantu;
- insiden aktif;
- stale/last updated indicator;
- loading, empty, degraded, down, unknown, maintenance state berbeda jelas.

### 11.2 Index pages

Gunakan Filament table/server-side pagination bila cocok. Filter minimum sesuai PRD. Jangan load JSON payload, stack trace, atau timeline penuh pada index.

### 11.3 Detail transaction

Tampilkan:

- identity dan status;
- module/action/actor/company/Branch;
- timeline attempts;
- request/response summary sanitized;
- linked domain record;
- external reference;
- error/reconciliation result;
- aksi yang benar-benar eligible.

Raw JSON bukan tampilan utama. Bila disediakan untuk role teknis, gunakan sanitized pretty view dan copy action.

### 11.4 Responsive/accessibility

- tidak ada page-level horizontal scroll;
- tabel lebar berada dalam container scroll;
- mobile menggunakan card/stack bila tabel tidak efektif;
- icon-only action memiliki accessible name;
- modal focus/escape/scroll mengikuti pola existing;
- status tidak hanya memakai warna;
- Asia/Jakarta digunakan untuk tampilan waktu sesuai config aplikasi.

### 11.5 Copy final

Bedakan:

- `Tarik dari ESB`;
- `Kirim ke ESB`;
- `Simpan Perubahan ke ESB`;
- `Rekonsiliasi dengan ESB`;
- `Coba Ulang` hanya bila aman.

Perbarui label tombol existing yang ambigu hanya setelah behavior direction-nya terbukti, tanpa mengubah method bisnis.

## 12. Phase 9 — Retention, aggregation, dan cleanup

Implementasikan command/job retention dengan dry-run bila ada risiko besar.

- gunakan `chunkById()`/lazy processing;
- hapus detail sesuai retention PRD;
- pertahankan aggregate, incident, deployment, status transaction, dan linkage penting;
- payload expiry tidak menghapus parent transaction;
- command idempotent;
- schedule dengan overlap protection;
- log summary aman;
- test boundary tanggal dan linked records.

Buat daily aggregate hanya untuk metric yang digunakan dashboard. Jangan precompute semua kombinasi filter.

## 13. Query dan performance guardrails

1. Server-side pagination untuk transaction/error/check history.
2. Eager load actor/Branch/service seperlunya.
3. Select kolom yang dipakai; payload JSON dimuat hanya pada detail.
4. Gunakan conditional aggregate untuk overview.
5. Tambah index berdasarkan query/filter nyata dan `EXPLAIN` bila database mendukung.
6. Cache overview singkat dengan invalidation/TTL eksplisit.
7. Hindari N+1 pada status cards, incident links, dan transaction children.
8. Batasi Livewire public state; gunakan ID/filter kecil, bukan seluruh dataset.
9. Ukur query count dan waktu halaman pilot sebelum/sesudah.

## 14. Security requirements

- Policy untuk setiap model monitoring.
- Technical payload membutuhkan permission terpisah.
- Management view tidak pernah menerima payload teknis ke browser, bukan sekadar disembunyikan CSS.
- Deployment ingestion signed dan replay-safe.
- Monitoring setting mempunyai validasi range.
- Tidak ada secret dalam notification, export, exception, database monitoring, atau browser state.
- Export dibatasi waktu/filter/permission dan tidak menyertakan payload kecuali requirement eksplisit.
- Correlation/public UUID tidak menggantikan authorization.
- Rate limit untuk ingestion/browser error endpoint.
- Audit actor untuk acknowledge, retry, reconcile, setting, incident transition, dan payload access bila diterapkan.

## 15. Testing wajib

Gunakan Pest dan factory existing. Buat test melalui Artisan.

### 15.1 Domain/unit

- enum labels/transitions;
- sanitizer recursive/secret/binary/signed URL;
- fingerprint determinism;
- transaction state machine;
- unknown vs failed mapping;
- retry eligibility;
- alert failure/recovery threshold;
- deduplication;
- retention selection;
- incident transition.

### 15.2 Feature/Livewire/Policy

- navigation visibility;
- direct route authorization;
- Management cannot access technical payload;
- filters/pagination/detail;
- incident actions/timeline;
- settings validation;
- maintenance behavior;
- safe retry/reconcile permission;
- responsive view assertions yang realistis.

### 15.3 Integration characterization

Untuk setiap mutation ESB:

- success;
- explicit 4xx/validation failure;
- 401/token refresh sesuai client contract;
- timeout;
- connection reset;
- malformed response;
- partial multi-company;
- duplicate click/concurrent claim;
- reconciliation success/failure;
- exact count/method/url/payload HTTP request;
- monitoring does not add mutation request;
- unknown is not automatically retried.

### 15.4 Health/scheduler/queue

- check success/failure/timeout;
- threshold transition;
- overlap prevention;
- heartbeat late/recovered;
- outage does not spam notification;
- dashboard performs no external HTTP;
- monitoring job failure does not block business queue.

### 15.5 Deployment ingestion

- valid signature;
- invalid signature;
- expired timestamp;
- replay nonce;
- invalid environment/status;
- secret absent from stored metadata.

### 15.6 Regression

Jalankan test domain existing yang disentuh. Jangan menghapus assertion hanya agar suite hijau. Jalankan minimum targeted tests selama iterasi, lalu seluruh file domain terkait pada final verification.

## 16. Acceptance criteria final

Implementasi belum dianggap selesai sampai semua berikut terbukti:

1. Semua route monitoring dilindungi permission server-side.
2. Overview tidak memanggil endpoint eksternal saat render.
3. Seluruh mutation UI dalam scope mempunyai transaction record dan correlation ID.
4. Jumlah HTTP mutation sebelum dan sesudah instrumentasi tetap sama untuk flow normal.
5. Timeout/connection reset mutation menghasilkan `unknown`.
6. `unknown` tidak mempunyai automatic retry.
7. Double-submit hanya menghasilkan satu external mutation.
8. Bulk flow mempertahankan child sukses dan hanya menangani child gagal yang aman.
9. Sanitizer test membuktikan token/password/header/binary tidak tersimpan.
10. Health checks menyimpan snapshot dan memakai threshold.
11. Alert deduplicated dan recovery tercatat.
12. Queue/scheduler heartbeat dapat membedakan healthy, late, dan unknown.
13. Incident timeline append-only dan transition tervalidasi.
14. Deployment ingestion aman atau tampil `Not Configured` tanpa merusak halaman.
15. Retention idempotent dan tidak menghapus incident/deployment/status parent.
16. Index menggunakan pagination dan tidak memuat payload besar.
17. UI mobile/desktop mengikuti consistency PRD.
18. Monitoring failure tidak menggagalkan transaksi bisnis yang berhasil.
19. Pint lulus untuk PHP yang berubah.
20. Targeted/regression tests lulus; pre-existing unrelated failure dilaporkan terpisah.

## 17. Urutan eksekusi yang disarankan

Kerjakan dengan urutan ini dan jangan menggabungkan semua perubahan sekaligus:

1. Phase 0 audit + characterization.
2. Schema, enum, model, factory, permission, Policy.
3. Sanitizer + transaction recorder + tests.
4. Pilot R&D Master Product/Product Detail.
5. UI ESB Transactions index/detail.
6. Instrumentasi BOM.
7. Instrumentasi Bulk Product/Promotion.
8. Instrumentasi Item Journal/Goods Receipt.
9. Service Health + local Overview.
10. Queue/Scheduler Monitor.
11. Backend Error Monitor.
12. Deployment ingestion/capacity contract.
13. Incident Center + notification.
14. Settings, retention, aggregate, performance hardening.
15. Full impacted regression and implementation report.

Setelah setiap nomor:

- inspect diff;
- jalankan Pint bila PHP berubah;
- jalankan test paling kecil yang membuktikan behavior;
- pastikan tidak ada request eksternal nyata;
- pastikan worktree unrelated tetap utuh.

## 18. Handling keputusan yang belum tersedia

PRD memuat pertanyaan production-specific. Gunakan aturan berikut:

- WhatsApp recipients belum diketahui: implementasikan in-app dan config contract; tampilkan Not Configured.
- SLA acknowledgement/recovery belum diputuskan: gunakan config default konservatif yang terdokumentasi, bukan hardcoded tersebar.
- staging/production layout belum diputuskan: schema wajib mempunyai `environment`; UI default ke current environment dan menyediakan filter bila ada data lain.
- retention belum disetujui: implementasikan config default PRD dan jangan menjalankan cleanup production.
- deployment source belum dipilih: implementasikan model/UI + signed ingestion contract hanya bila dapat diintegrasikan tanpa secret baru; jika tidak, tampilkan Not Configured.
- server metrics source belum tersedia: jangan shell dari web; tampilkan Not Configured.
- PDF/Excel management report belum diputuskan: out of scope implementasi awal.

Jangan menghentikan fondasi monitoring karena salah satu integration source belum tersedia.

## 19. Final verification dan laporan

Sebelum selesai:

1. Jalankan `git diff --check`.
2. Jalankan `vendor/bin/pint --dirty --format agent` jika ada PHP berubah.
3. Jalankan test terfokus per domain yang diubah.
4. Jalankan seluruh file regression monitoring + integrasi terdampak.
5. Jalankan build frontend bila source frontend berubah.
6. Periksa route list, permission seeder, Filament discovery/navigation, scheduler list, migration status lokal, dan query/page performance.
7. Pastikan tidak ada credential atau data sensitif pada diff/test fixture/log.
8. Jangan menjalankan migration production atau deployment.

Laporan akhir harus ringkas tetapi berbukti, mencakup:

- outcome yang selesai;
- phase yang selesai dan yang sengaja belum aktif karena external config;
- file/migration utama;
- integrasi ESB yang sudah diinstrumentasi;
- security/sanitization decisions;
- test command dan hasil exact;
- performance/query evidence;
- migration/deployment steps yang belum dijalankan;
- konfigurasi production yang masih dibutuhkan;
- risiko residual, terutama transaksi legacy/unknown;
- konfirmasi bahwa tidak ada commit, push, deploy, atau data mutation production dilakukan.

Jangan menyatakan fitur production-ready bila migration, queue worker, scheduler, signed deployment callback, alert recipients, atau konfigurasi lain belum benar-benar tersedia dan diverifikasi.
```
