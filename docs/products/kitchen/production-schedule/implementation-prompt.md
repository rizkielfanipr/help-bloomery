---
document_id: KITCHEN-PRODUCTION-SCHEDULE-PROMPT
title: Prompt Implementasi — Jadwal Produksi dan Production Order ESB
status: in-development
version: 1.1
owner: Kitchen
technical_owner: Information Technology
created_at: 2026-10-27
updated_at: 2026-10-28
target_release: TBD
related_documents:
  - docs/products/kitchen/production-schedule/prd.md
  - docs/products/kitchen/production-schedule/implementation-plan.md
  - docs/architecture/integrations/esb.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# Prompt Implementasi — Jadwal Produksi dan Production Order ESB

> **Digantikan sebagian oleh PRD v3.0 (§38, 28 Oktober 2026):** rencana produksi lokal, filter Sumber, dan toggle `KITCHEN_PRODUCTION_ORDERS_ENABLED` sudah dihapus. Instruksi di bawah yang meminta mempertahankan rencana lokal tidak berlaku; gunakan PRD §25–§38 sebagai acuan.

Salin blok berikut ke agent implementasi dari root repository.

```text
Implementasikan perluasan `Kitchen → Jadwal Produksi` agar kalender existing tetap menampilkan rencana produksi lokal dan juga menampilkan Production Order ESB sebagai data read-only berdasarkan tanggal produksinya.

Keputusan bisnis terbaru yang wajib mengalahkan implementasi/dokumen lama yang bertentangan:

- Kitchen Jateng menggunakan company/mapping `BLSS / KTJ`.
- Kitchen Surabaya menggunakan company/mapping `BLO13 / HOF`.
- Kitchen Jakarta menggunakan company/mapping `BLHJ / TCHL`.
- Seluruh filter, editor jadwal, kalender, detail, status sinkronisasi, dan sync planner hanya boleh memuat Branch ber-Brand `Kitchen`, termasuk bagi Superadmin atau user dengan `access_all_branches`.
- `esb_branch_id` numerik wajib berasal dari Sync Branch ESB/API, bukan ditebak dari Branch Code.

Kerjakan pada branch baru dari `main` terbaru:

`feature/kitchen-production-order-esb`

Jangan menggunakan branch pekerjaan lama sebagai basis kecuali seluruh commit-nya sudah masuk `main`. Jangan membawa perubahan yang tidak terkait.

## 1. Dokumen sumber yang wajib dibaca penuh

Sebelum mengubah file apa pun, baca seluruh dokumen berikut:

1. `AGENTS.md` — aturan repository dan Laravel Boost.
2. `docs/products/kitchen/production-schedule/prd.md` — requirement canonical yang berlaku saat ini.
3. `docs/products/kitchen/production-schedule/implementation-plan.md` — urutan phase dan batas arsitektur.
4. `docs/products/kitchen/README.md` — perilaku Kitchen yang benar-benar berjalan saat ini.
5. `docs/architecture/integrations/esb.md` — boundary integrasi ESB existing.
6. `docs/architecture/rbac.md` dan `docs/architecture/panels.md` — permission dan navigation existing.
7. `docs/standards/code-remediation.md` — arsitektur, data safety, maintainability, dan quality gate.
8. `docs/standards/ui-consistency.md` — standar UI desktop/mobile, modal, tabel, loading, empty, dan error state.
9. Skill repository yang relevan di `**/skills/**`, termasuk Laravel best practices, Pest testing, Tailwind, dan feature delivery bila tersedia.

Gunakan Laravel Boost `search-docs` sebelum membuat perubahan kode. Gunakan query yang luas dan relevan untuk Laravel queue, scheduler, HTTP client retry/testing, Eloquent upsert, database transaction, Filament/Livewire testing, pagination, authorization, dan job uniqueness. Gunakan dokumentasi yang sesuai versi package repository.

## 2. Status requirement dan gate yang tidak boleh dilewati

Amendment Production Order ESB dan keputusan K1–K15 pada PRD sudah disetujui user pada 27–28 Oktober 2026. Karena itu:

1. Lakukan Phase 0 sebagai audit terhadap implementasi yang sudah ada, bukan untuk meminta persetujuan requirement yang sama lagi.
2. Gunakan PRD v2.1 sebagai requirement canonical. Keputusan terbaru tentang tiga mapping dan scope Brand `Kitchen` mengalahkan kode atau dokumen lama yang bertentangan.
3. Jangan menulis ulang fitur dari nol bila komponennya sudah tersedia. Identifikasi gap, pertahankan data dan perilaku yang benar, lalu lakukan remediation sekecil dan seaman mungkin.
4. Berhenti dan minta keputusan user hanya jika audit menemukan pilihan bisnis baru yang belum dijawab PRD atau perubahan destruktif terhadap data.
5. Migration, konfigurasi, queue, dan rollout harus tetap mengikuti quality gate, feature flag, dry-run, serta prosedur rollback dalam dokumen canonical.

## 3. Hasil akhir yang diharapkan

Setelah seluruh phase disetujui dan selesai:

1. Menu tetap berada di `Kitchen → Jadwal Produksi`.
2. Kalender existing tetap menampilkan rencana produksi lokal yang dapat dibuat, diedit, dan dibatalkan sesuai Policy existing.
3. Production Order ESB ditampilkan pada tanggal `productionOrderDate` sebagai event dengan source badge `ESB`.
4. Rencana lokal dan Production Order ESB terlihat berbeda serta tidak digabung otomatis.
5. Klik rencana lokal tetap membuka detail/editor existing.
6. Klik Production Order ESB membuka detail read-only berisi header, status, daftar BOM/product, quantity, UOM, dan material bila tersedia.
7. Filter menyediakan source `Semua`, `Rencana`, dan `Production Order ESB`, serta tetap menghormati Branch dan rentang kalender.
8. Data ESB dibaca melalui queue dan disimpan sebagai snapshot lokal. Render kalender dan modal tidak bergantung pada response ESB real-time.
9. Sinkronisasi berjalan per company/Branch mapping secara idempotent, retryable, terukur, dan tidak membebani memory.
10. Pengguna tidak dapat melihat Production Order dari Branch yang tidak dapat diakses.
11. ESB outage tidak menghilangkan snapshot terakhir yang valid; UI menampilkan waktu sinkronisasi dan warning yang jelas.
12. Tidak ada mutation terhadap Production Order ESB dalam fitur ini.
13. Seluruh perubahan mempunyai Pest tests, dokumentasi, rollout, rollback, dan observability.
14. Pilihan Brand pada halaman selalu `Kitchen`; pilihan Branch hanya `Semua Kitchen`, Kitchen Jakarta, Kitchen Jateng, dan Kitchen Surabaya sesuai akses user.
15. Ketiga mapping canonical diproses terpisah dengan company context masing-masing; `BLHJ` dikenali oleh konfigurasi ESB.

## 4. Scope

Termasuk:

- audit perilaku Jadwal Produksi existing;
- amendment PRD dan alignment dokumen;
- client read-only Production Order ESB;
- endpoint daftar Production Order dan detail Production Order;
- pagination dan normalisasi response;
- snapshot header, detail, dan material minimum;
- sync-run tracking;
- queue job per mapping/range;
- command untuk incremental sync dan backfill;
- scheduler incremental;
- query kalender gabungan dua source;
- detail read-only;
- filter source, Branch, status, dan visible date range;
- authorization Branch;
- loading, empty, no-result, stale, partial, dan error state;
- observability dan monitoring minimum;
- automated tests;
- dokumentasi dan rollout bertahap.

## 5. Non-scope

Jangan mengerjakan:

- create Production Order ke ESB;
- edit, delete, authorize, reject, finish, result sign-off, return, atau perubahan status di ESB;
- push jadwal lokal menjadi Production Order;
- auto-link jadwal lokal dengan Production Order;
- auto-update status jadwal lokal dari status ESB;
- merge record berdasarkan product code, BOM name, tanggal, atau quantity;
- actual production input;
- production result;
- variance, yield, waste, costing, material requirement planning, atau inventory reservation;
- perubahan master BOM;
- perubahan flow katalog BOM R&D;
- export Excel/PDF;
- notifikasi baru;
- dashboard monitoring besar baru;
- dependency/package baru tanpa persetujuan;
- rewrite halaman existing atau migrasi ke framework frontend baru;
- destructive migration atau penghapusan data lama.

## 6. Prinsip domain yang wajib dipertahankan

### 6.1 Dua sumber, dua makna

- `KitchenProductionSchedule` adalah rencana lokal Bloomery Office.
- Production Order adalah record eksternal dari ESB.
- Kesamaan tanggal, Branch, BOM, product, atau quantity tidak membuktikan dua record tersebut sama.
- Jangan membuat foreign key atau status sinkronisasi antar-keduanya pada versi pertama.
- Jangan menjadikan Production Order pengganti record rencana lokal.

### 6.2 Source identity

Setiap calendar event internal harus memiliki source eksplisit, minimal:

- `local_schedule`;
- `esb_production_order`.

Identity event harus mencakup source sehingga ID lokal dan ID eksternal tidak bertabrakan.

### 6.3 Multi-company dan multi-branch

- Production Order number tidak boleh dianggap global.
- Identity minimum header adalah `company_code + production_order_num`.
- Parameter ESB `branchID` berasal dari `BranchEsbCode.esb_branch_id`, bukan primary key Branch lokal dan bukan `esb_branch_code`.
- Company credential dipilih melalui mekanisme existing berdasarkan `esb_comcode`.
- Satu Branch lokal dapat memiliki lebih dari satu mapping; audit dan ikuti canonical mapping behavior repository. Jangan memilih mapping dengan tebakan.
- Jangan menggabungkan data antar-company berdasarkan product code.
- Mapping canonical yang diizinkan:
  - Kitchen Jateng: `BLSS / KTJ`;
  - Kitchen Surabaya: `BLO13 / HOF`;
  - Kitchen Jakarta: `BLHJ / TCHL`.
- Ketiga Branch lokal wajib terhubung ke Brand `Kitchen`.
- `BLSS`, `BLO13`, dan `BLHJ` wajib tersedia pada allowlist Production Order; `BLHJ` juga wajib ditambahkan ke `config('esb.core.companies')` dengan credential environment.
- Jika satu Branch mempunyai beberapa mapping, jangan memilih berdasarkan record pertama, nama, atau label. Gunakan pasangan canonical di atas; jika diperlukan agar tidak hardcode, tambahkan selector/flag additive pada Master Branch setelah audit.

### 6.4 Scope Brand Kitchen

- Semua query pilihan/filter Branch selalu memakai constraint Brand `Kitchen`.
- `access_all_branches` hanya melewati pembatasan assignment cabang, bukan pembatasan Brand.
- `view all branch kitchen production schedules` hanya memberi seluruh Branch Kitchen.
- Branch non-Kitchen harus ditolak pada filter, create/update, kalender lokal, kalender ESB, detail, status sync, command, dan planner.
- Jangan menentukan Kitchen dari nama Branch; gunakan relasi `branches.brand_id` ke Brand bernama `Kitchen`/scope canonical existing.

## 7. Endpoint ESB minimum

Gunakan hanya endpoint yang diperlukan untuk MVP:

### Daftar header

`GET /core/production/production-order`

Parameter yang relevan:

- `branchID`;
- `dateFrom`;
- `dateTo`;
- `productionOrderDate` bila diperlukan untuk characterization;
- `productionOrderNum`;
- `bomTypeID`;
- `statusID`;
- `page`;
- `limit`;
- `sort`.

Gunakan untuk header kalender dan status.

### Detail order

`GET /core/production/production-order/{productionOrderNum}`

Gunakan untuk header lengkap, detail, dan material.

Endpoint berikut tidak digunakan pada MVP kecuali audit response nyata membuktikan endpoint minimum tidak cukup dan user menyetujui perluasan:

- `/browse-detail`;
- `/browse-multi-production-result`;
- `/browse-return`;
- `/browse-return-standard`;
- `/browse-unfinished`;
- `/details`;
- `/group-production-order-material`;
- `/print-group`;
- `/{productionOrderNum}/is-finished`;
- `/{productionOrderNum}/unfinished-detail`.

Jangan menambah pemanggilan endpoint hanya karena tersedia di dokumentasi.

## 8. Aturan repository

- Mulai dari `main` terbaru dan pastikan working tree bersih.
- Audit sibling files sebelum menentukan nama class, namespace, pattern service, job, command, model, factory, test, dan Filament page.
- Gunakan komponen dan helper existing sebelum membuat abstraction baru.
- Gunakan `php artisan make:* --no-interaction` untuk file Laravel baru.
- Jangan mengubah dependency tanpa approval.
- Jangan mengedit migration lama yang sudah mungkin dijalankan; buat migration additive baru.
- Jangan membuat script verifikasi/tinker bila test dapat membuktikan behavior.
- Gunakan `database-schema` sebelum merancang migration dan relasi.
- Gunakan `database-query` hanya untuk audit read-only bila perlu.
- Jangan membaca atau menyalin secret ke output, test fixture, log, atau dokumentasi.
- Jalankan `vendor/bin/pint --dirty --format agent` setelah mengubah PHP.
- Gunakan `apply_patch` untuk perubahan file manual.
- Pertahankan perubahan user yang tidak terkait.

## 9. Aturan arsitektur

- Filament/Livewire page hanya mengelola presentation state, validasi input UI, dan delegasi use case.
- Blade tidak menjalankan query dan tidak memanggil ESB.
- Request eksternal berada di service integrasi khusus.
- Mapping payload ke snapshot berada di Action/service yang dapat diuji terpisah.
- Job membawa scalar identifier/range, bukan model besar, token, atau payload penuh.
- Calendar query membaca database lokal dan mengembalikan view model yang jelas.
- Detail resolver memilih builder berdasarkan source.
- Perubahan snapshot berjalan transactionally per order atau unit kerja kecil; jangan mengunci seluruh sync run.
- Gunakan decimal string/cast yang sesuai untuk quantity; jangan menghitung quantity menggunakan float tanpa aturan.
- Hindari array mentah menyebar dari service ESB ke UI. Gunakan DTO/value object hanya bila memberi boundary nyata dan mengikuti konvensi repository.
- Jangan membuat repository pattern, event, interface, atau layer baru yang hanya mempunyai satu penggunaan tanpa kebutuhan pengujian/pertukaran yang nyata.

## 10. Aturan client ESB

- Reuse `EsbCoreClient` untuk authentication, cache token, refresh, timeout, dan base URL.
- Jangan membuat login client kedua.
- Service Production Order menerima company code dan parameter query yang sudah tervalidasi.
- Normalisasi envelope `status`, `result`, `errors`, pagination, dan nullable values.
- Perlakukan response 2xx dengan bentuk payload salah sebagai failure terklasifikasi.
- Bedakan:
  - validation/permanent request error;
  - authentication error;
  - retryable timeout/network/5xx;
  - missing/invalid Branch mapping;
  - malformed response.
- Pagination wajib mempunyai termination guard berdasarkan `page`, `next`, count, dan maximum page yang wajar.
- Jangan log bearer token, authorization header, credential, atau full payload production.
- Semua test HTTP menggunakan `Http::fake()`; test tidak boleh memanggil ESB nyata.

## 11. Rancangan data minimum

Audit schema existing terlebih dahulu. Implementasi final boleh menyesuaikan nama dengan konvensi repository, tetapi behavior berikut wajib tersedia.

### 11.1 Snapshot header

Tabel/model rekomendasi: `esb_production_orders` / `EsbProductionOrder`.

Data minimum:

- mapping reference nullable bila sesuai data safety;
- local `branch_id`;
- `company_code`;
- `esb_branch_id`;
- snapshot branch name;
- `production_order_num`;
- `production_order_date`;
- `bom_type_id` dan nama;
- `status_id` dan nama;
- `additional_info`;
- created/edited/authorized metadata yang tersedia dan memang diperlukan untuk detail;
- `source_fetched_at`;
- `source_last_seen_at`;
- `payload_hash` atau version fingerprint normalized;
- timestamps.

Constraint minimum:

- unique `(company_code, production_order_num)`;
- index `(branch_id, production_order_date)`;
- index `(branch_id, status_id, production_order_date)`.

### 11.2 Snapshot detail

Tabel/model rekomendasi: `esb_production_order_details` / `EsbProductionOrderDetail`.

Data minimum:

- FK header;
- external detail ID;
- BOM ID/name/type;
- product detail ID/code/name;
- production quantity;
- UOM;
- notes;
- finished flag bila tersedia;
- material snapshot minimum;
- timestamps.

Constraint minimum:

- unique `(esb_production_order_id, external_detail_id)`;
- index hanya untuk query yang benar-benar dipakai.

Untuk material, audit volume dan query requirement:

- gunakan JSON snapshot bila material hanya dibaca di detail;
- buat child table hanya jika dibutuhkan filter, agregasi, reconciliation, atau volume payload membuat JSON tidak layak;
- dokumentasikan keputusan di amendment PRD atau ADR jika konsekuensinya jangka panjang.

### 11.3 Sync run

Tabel/model rekomendasi: `esb_production_order_sync_runs` / `EsbProductionOrderSyncRun`.

Data minimum:

- mapping/local Branch/company;
- mode `incremental` atau `backfill`;
- `date_from`, `date_to`;
- status `queued`, `running`, `succeeded`, `partial`, `failed`;
- page/cursor terakhir bila dibutuhkan recovery;
- counters;
- sanitized error class/code/message;
- started/finished timestamps;
- idempotency/uniqueness key.

Jangan melakukan HTTP request atau backfill di migration.

## 12. Data compatibility dan lifecycle snapshot

- Tabel jadwal lokal tidak diubah kecuali audit membuktikan perubahan additive kecil diperlukan.
- Data jadwal lokal existing harus tetap dapat dibuka dan diedit/cancel sesuai status.
- Snapshot ESB tidak mengubah jadwal lokal.
- Upsert payload yang sama harus idempotent.
- Perubahan status/detail ESB memperbarui snapshot tanpa membuat duplicate.
- Hilangnya record dari satu response parsial tidak berarti record dihapus di ESB.
- Jangan hard-delete snapshot dari hasil satu sync.
- Mapping yang dinonaktifkan menghentikan sync baru tetapi tidak menghapus histori snapshot.
- Retention dan archival harus diputuskan pada Phase 0; default aman adalah mempertahankan snapshot.
- Simpan snapshot label yang diperlukan agar histori tetap dapat dibaca ketika master ESB berubah.

## 13. Aturan authorization

- Menu tetap memerlukan `view kitchen production schedules`.
- Semua query lokal dan ESB snapshot dibatasi Branch accessible di SQL.
- `view all branch kitchen production schedules` hanya memperluas scope Branch dan tidak menggantikan permission fitur.
- Jangan hardcode role seperti `SUPERADMIN`, `KITCHEN`, atau nama jabatan.
- Re-query dan authorize source event, Branch, mapping, serta record ketika detail dibuka.
- Source, Branch ID, company code, ESB branch ID, order number, dan local/external record ID dari browser tidak dipercaya.
- Create/update/cancel hanya berlaku untuk source lokal.
- Event ESB tidak pernah menampilkan tombol mutation.
- Reuse permission existing bila owner bisnis tidak meminta pemisahan akses.
- Jika permission baru disetujui, buat additive, default-deny, seed secara eksplisit, dan test user tanpa permission, permission terbatas, all-branch, serta forged direct action.

## 14. Pipeline sinkronisasi

### 14.1 Planner

- Pilih hanya Branch aktif ber-Brand `Kitchen`.
- Pilih hanya mapping canonical `BLSS/KTJ`, `BLO13/HOF`, dan `BLHJ/TCHL` yang aktif serta mempunyai `esb_branch_id` valid.
- Pastikan `BLHJ` ditambahkan ke konfigurasi company ESB dan credential dibaca hanya dari environment.
- Jangan dispatch mapping yang tidak lengkap; catat sebagai blocked/failed yang dapat dipahami.
- Jangan dispatch mapping Branch non-Kitchen atau pasangan company/Branch Code selain daftar canonical.
- Job uniqueness berbasis mapping + mode + date window.

### 14.2 List sync

- Satu job menangani satu mapping dan date window terbatas.
- Iterasi pagination secara bounded.
- Upsert header per page/chunk.
- Jadwalkan detail hydration hanya untuk header baru, berubah, stale, atau belum lengkap.
- Kegagalan satu mapping tidak menggagalkan mapping lain.
- Kegagalan satu order detail menghasilkan partial run, bukan rollback seluruh header yang sudah valid.

### 14.3 Detail hydration

- Ambil detail menggunakan company context dan `productionOrderNum`.
- Verifikasi order number dan Branch/company sebelum menyimpan.
- Upsert detail secara transaction per order.
- Hapus/mark stale detail lama hanya jika response detail dianggap authoritative dan contract-nya sudah diuji.
- Jangan memanggil detail endpoint setiap render halaman.

### 14.4 Incremental dan backfill

- Scheduler hanya menjalankan incremental window yang kecil dan disetujui.
- Backfill memakai command eksplisit dengan `--date-from`, `--date-to`, dan scope Branch/mapping.
- Command menyediakan dry-run/preview bila akan mendispatch banyak job.
- Command menolak range tidak wajar kecuali ada flag konfirmasi eksplisit.
- Jangan membuat scheduler melakukan backfill penuh berulang.

### 14.5 Queue

- Gunakan queue existing dan konfigurasi supervisor existing.
- Tentukan timeout, tries, backoff, unique-for, dan tags dengan alasan yang dapat diuji.
- Jangan menaikkan memory limit global sebagai pengganti chunking/pagination.
- Payload queue tidak menyimpan token atau full API response.

## 15. Query kalender

- Pertahankan `ProductionScheduleCalendarQuery` atau pola sibling existing untuk jadwal lokal.
- Tambahkan source query ESB tanpa mengubah semantics query lokal.
- Keduanya harus memakai visible date range kalender yang sama.
- Filter Branch dan source dilakukan di database.
- Status local dan ESB tidak dipaksa memakai enum yang sama.
- Bentuk event internal minimum:
  - source;
  - source-specific identity;
  - date;
  - local Branch ID/name;
  - title/number;
  - status ID/label;
  - item count;
  - read-only/edit capability;
  - last synced timestamp untuk ESB.
- Jangan menjumlahkan quantity lintas UOM.
- Jangan load material/detail besar pada query kalender.
- Detail dimuat saat user membuka event.
- Pastikan tidak terjadi N+1.

## 16. Aturan UI

Ikuti `docs/standards/ui-consistency.md` dan pola kalender existing.

### Kalender

- Pertahankan kalender desktop tujuh kolom dan agenda/list mobile existing.
- Jangan membuat horizontal scroll pada mobile.
- Tambahkan legend/source filter:
  - `Semua`;
  - `Rencana`;
  - `Production Order ESB`.
- Chip lokal dan ESB harus berbeda secara visual, tetapi tetap menggunakan token design system.
- Chip ESB minimal mempunyai badge `ESB`.
- Jangan memakai warna sebagai satu-satunya pembeda; gunakan label/icon/text.
- Branch, date, and status filters tetap rapat, responsif, dan server-side.
- Context Brand selalu `Kitchen`. Jika selector Brand dipertahankan untuk konsistensi dengan Sales Information, hanya `Kitchen` yang boleh muncul dan nilainya menjadi default; selector boleh dihilangkan jika redundant.
- Selector Branch hanya menampilkan `Semua Kitchen` dan Branch Kitchen yang dapat diakses. User all-branch melihat seluruh Kitchen, bukan seluruh Brand.

### Detail Production Order

- Gunakan modal/drawer existing yang konsisten dengan aplikasi.
- Read-only; jangan tampilkan form control atau tombol aksi yang seolah dapat mengubah ESB.
- Header ringkas:
  - Production Order number;
  - date;
  - Branch;
  - BOM type;
  - status;
  - additional information;
  - creator/editor/authorizer bila tersedia;
  - terakhir disinkronkan.
- Detail ditampilkan sebagai tabel responsif dengan product/BOM, code, quantity, UOM, finished state, dan notes.
- Material ditampilkan dalam tabel/expandable section per detail bila tersedia.
- Modal tidak full screen kecuali standar mobile mengharuskan.
- Konten modal mempunyai max-height terhadap viewport dan satu area vertical scroll yang benar.
- Footer tidak menutupi baris terakhir.

### State

Sediakan state berbeda untuk:

- belum ada Production Order pada filter;
- sync belum pernah berjalan;
- Branch belum memiliki mapping ESB;
- mapping tidak lengkap;
- sync sedang berjalan;
- sync terakhir berhasil;
- snapshot stale;
- sync partial;
- sync gagal tetapi snapshot lama masih tersedia;
- detail belum selesai di-hydrate.

Gunakan kalimat yang mudah dipahami pengguna operasional. Hindari istilah seperti payload, hydration, cursor, atau malformed pada UI.

## 17. Observability

- Simpan status dan counters sync run.
- Structured log minimum:
  - sync run ID;
  - mapping ID;
  - local Branch ID;
  - company code;
  - date range;
  - page;
  - duration;
  - normalized error class/code.
- Counter minimum:
  - pages fetched;
  - headers received;
  - headers created;
  - headers updated;
  - headers unchanged;
  - details succeeded;
  - details failed.
- Jangan log token, password, authorization header, atau full production payload.
- Jika System Monitoring existing mempunyai extension point yang sesuai, tampilkan last-success/failure setelah pipeline stabil. Jangan memperluas scope menjadi dashboard baru.

## 18. Tahapan implementasi wajib

### Phase 0 — Audit, requirement amendment, dan API contract

1. Pastikan branch berasal dari `main` terbaru dan worktree bersih.
2. Audit file Kitchen, mapping Branch ESB, client ESB, scheduler, queue, permissions, sidebar, tests, dan docs.
3. Verifikasi endpoint documentation terhadap bentuk response yang dapat diuji. Jangan memakai credential atau data production di fixture.
4. Buat fixture anonim untuk list dan detail, termasuk pagination, nullable values, status, material, empty, dan error.
5. Amend PRD dengan requirement overlay read-only, source separation, sync, authorization, data lifecycle, UI, observability, rollout, dan acceptance criteria.
6. Putuskan dan dokumentasikan:
   - histori backfill awal;
   - incremental window;
   - refresh frequency;
   - retention snapshot;
   - status ESB yang ditampilkan;
   - material JSON versus child table;
   - permission reuse versus permission baru;
   - behavior multiple active mappings.
7. Update implementation plan bila audit menghasilkan deviasi.
8. Jalankan link/code-map validation.
9. Laporkan hasil Phase 0 dan minta approval user.
10. Audit mapping Master Branch dan kunci pasangan `BLSS/KTJ`, `BLO13/HOF`, `BLHJ/TCHL`; ambil `esb_branch_id` melalui Sync Branch ESB.
11. Audit perilaku all-branch pada `filterBranches`, `schedulableBranches`, validator, policy/scope, Production Order query, sync summary, dan planner; catat seluruh titik yang masih memuat Brand non-Kitchen.

Berhenti di sini sampai approval eksplisit diberikan.

### Phase 1 — Client read-only ESB

1. Gunakan generator Artisan untuk class baru bila sesuai.
2. Implement service Production Order di atas `EsbCoreClient`.
3. Implement list dan detail only.
4. Tambahkan normalization dan error classification.
5. Implement pagination guard.
6. Tambahkan focused tests dengan `Http::fake()`.
7. Jalankan formatter dan focused tests.

### Phase 2 — Schema dan snapshot domain

1. Audit database schema dengan Laravel Boost.
2. Buat migration additive.
3. Buat models, casts, relations, factories, dan factory states yang berguna.
4. Buat Action/service idempotent untuk upsert header/detail.
5. Implement sync-run lifecycle.
6. Tambahkan schema/domain tests.
7. Pastikan migration tidak memanggil ESB dan tidak backfill.

### Phase 3 — Queue pipeline, command, dan scheduler

1. Implement planner mapping.
2. Implement bounded list-sync job.
3. Implement detail hydration job bila dipisah.
4. Implement idempotency/uniqueness, retry, backoff, and partial failure.
5. Implement command incremental/backfill dengan guard range.
6. Tambahkan scheduler dengan interval hasil Phase 0.
7. Tambahkan tests multi-company, multi-branch, missing mapping, pagination, retry, partial failure, idempotency, dan memory-safe behavior.
8. Tambahkan dukungan company `BLHJ` pada config ESB dan test bahwa credential tidak pernah di-hardcode.
9. Pastikan planner hanya mendispatch tiga mapping canonical milik Brand Kitchen dan melaporkan mapping lain sebagai dilewati.

### Phase 4 — Query dan authorization kalender

1. Pertahankan query source lokal.
2. Tambahkan query source ESB dari snapshot.
3. Bentuk view model event dengan source identity.
4. Tambahkan source/status/Branch/date filtering server-side.
5. Tambahkan detail resolver per source.
6. Audit Policy dan permission.
7. Tambahkan direct-action and forged-ID tests.
8. Tambahkan query-count/N+1 protection bila pola test repository tersedia.
9. Terapkan Brand Kitchen secara unconditional pada filter, create/update validation, query kalender lokal/ESB, detail, status sync, dan planner. Hapus exception lama untuk all-branch.

### Phase 5 — UI

1. Update Filament/Livewire page dan Blade existing secara incremental.
2. Tambahkan legend/source filter.
3. Tambahkan ESB event chip read-only.
4. Tambahkan detail modal/drawer yang dapat di-scroll.
5. Tambahkan all required states.
6. Pertahankan create/edit/cancel local schedule behavior.
7. Jalankan Livewire/Filament tests, frontend build, dan browser verification desktop/mobile.

### Phase 6 — Dokumentasi, UAT, rollout, dan report

1. Buat/update dokumen `rollout.md` pada folder fitur ini dari template rollout resmi.
2. Update README fitur dan `docs/products/kitchen/README.md` berdasarkan behavior aktual.
3. Update `docs/architecture/integrations/esb.md`.
4. Update RBAC/panel docs bila permission/navigation berubah.
5. Regenerasi/update data model melalui mekanisme canonical repository bila model baru ditambahkan.
6. Jalankan staging backfill terbatas.
7. Rekonsiliasi minimal dua company/Branch dan beberapa status/date.
8. Uji ESB unavailable, token expired, mapping invalid, partial detail failure, and stale snapshot UI.
9. Rollout production bertahap: deploy schema/client, manual sync terbatas, verifikasi, baru scheduler.
10. Buat `reports/implementation-report.md` berisi commit/PR, status phase, test, keputusan, deviasi, UAT, rollout, risiko, dan follow-up.

## 19. Test minimum

### Client ESB

- path dan query list benar;
- company credential context benar;
- `branchID` berasal dari `esb_branch_id`;
- pagination satu dan banyak page;
- empty result;
- nullable fields;
- detail nested material;
- 400 validation error;
- 401 refresh token;
- repeated 401 gagal aman;
- 500 dan timeout retryable;
- malformed envelope gagal terklasifikasi;
- maximum page guard.

### Schema dan snapshot

- unique company + Production Order number;
- number sama pada company berbeda diizinkan;
- header upsert idempotent;
- status update tidak duplicate;
- detail upsert idempotent;
- decimal quantity tersimpan akurat;
- snapshot label tetap terbaca ketika mapping/master berubah;
- partial sync tidak menghapus snapshot;
- missing mapping tidak membuat record salah Branch;
- sync run counters/status konsisten.

### Queue dan command

- job per mapping/range;
- multi-company dan multi-branch terisolasi;
- incomplete/inactive mapping tidak diproses;
- uniqueness mencegah concurrent duplicate;
- retry/backoff sesuai klasifikasi;
- partial detail failure tidak menghilangkan header valid;
- backfill range guard;
- scheduler hanya incremental;
- job dapat di-retry tanpa duplicate;
- bounded pagination/chunking.

### Authorization

- user dengan permission dan Branch access dapat melihat;
- user tanpa permission ditolak;
- user Branch A tidak dapat melihat detail Branch B;
- all-branch tetap memerlukan permission fitur;
- forged source/ID/Branch/company/order number ditolak;
- local mutation tidak dapat diarahkan ke ESB event;
- ESB event tidak mempunyai mutation action.
- all-branch hanya melihat seluruh Branch Kitchen dan tidak pernah melihat/memilih Branch Brand lain;
- Branch non-Kitchen ditolak pada filter, create/update, kalender, detail, status sync, command, dan planner;
- tiga mapping canonical dapat diakses hanya sesuai permission fitur dan Branch scope user;

### Kalender dan UI

- local schedule regression suite tetap lulus;
- visible date range benar;
- ESB order muncul pada production date;
- source filter bekerja;
- Branch/status filter bekerja per source;
- source identity tidak bertabrakan;
- item count benar;
- detail tidak dimuat pada calendar query;
- no N+1;
- local event tetap editable sesuai permission;
- ESB detail read-only;
- empty, no mapping, unsynced, running, stale, partial, dan failed states;
- stale snapshot tetap tampil;
- modal scroll tidak memotong konten;
- mobile agenda tidak horizontal scroll;
- sidebar Kitchen tetap terbuka saat halaman aktif.

### Regression

- Kitchen production workflow tests;
- R&D BOM catalog tests;
- Branch ESB mapping tests;
- ESB core client tests;
- permission/sidebar tests;
- scheduler/queue smoke tests yang relevan.

## 20. Perintah verifikasi minimum

Sesuaikan filename/filter dengan hasil implementasi. Jangan menjalankan full suite lebih awal jika focused tests cukup untuk iterasi.

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/EsbProductionOrderServiceTest.php
php artisan test --compact tests/Feature/KitchenProductionOrderSyncTest.php
php artisan test --compact tests/Feature/KitchenProductionSchedulePageTest.php
php artisan test --compact tests/Feature/KitchenProductionScheduleWorkflowTest.php
php artisan test --compact tests/Feature/BranchEsbCodeTest.php
npm run build
bash ops/ci/check-doc-links.sh
bash ops/ci/check-code-map.sh
git diff --check
```

Tambahkan focused test lain berdasarkan file yang benar-benar berubah. Jangan menghapus test existing tanpa approval.

## 21. Rollout wajib

- Jangan mengaktifkan scheduler sebelum migration, queue worker, client, snapshot, dan observability terverifikasi.
- Deploy schema/client terlebih dahulu tanpa backfill otomatis.
- Jalankan manual sync satu mapping dan range pendek di staging.
- Rekonsiliasi jumlah header, status, date, detail, quantity, UOM, and materials dengan ESB.
- Backfill staging sesuai window approved.
- UAT UI desktop/mobile.
- Deploy production melalui atomic release existing dan approval environment.
- Jalankan manual production sync pada satu Branch/range kecil.
- Verifikasi health endpoint, queue worker, scheduler, logs, sync run, memory, disk, dan calendar.
- Aktifkan scheduler bertahap.
- Pantau minimal satu siklus penuh.
- Jangan menaruh credential baru di repository. Bila konfigurasi environment diperlukan, dokumentasikan hanya nama variabel dan kegunaannya di rollout.

## 22. Rollback wajib

- Sediakan cara menonaktifkan layer ESB/calendar source tanpa mematikan rencana lokal.
- Sediakan cara menghentikan scheduler/job Production Order tanpa menghentikan queue global.
- Rollback aplikasi harus tetap kompatibel dengan schema additive.
- Jangan drop tabel snapshot atau menghapus snapshot pada emergency rollback.
- Gunakan atomic release rollback existing bila health check gagal.
- Setelah rollback, pastikan:
  - health endpoint hijau;
  - worker mengarah ke release aktif;
  - jadwal lokal tetap dapat dibuka;
  - scheduler Kitchen ESB tidak mendispatch job baru bila dinonaktifkan.

## 23. Quality gates

Pekerjaan belum selesai sebelum seluruh gate relevan terpenuhi:

- [ ] PRD amendment disetujui sebelum coding.
- [ ] Baseline repository dan API contract terdokumentasi.
- [ ] Tidak ada mutation Production Order ESB.
- [ ] Tidak ada direct ESB call dari UI.
- [ ] Migration additive dan backfill terpisah.
- [ ] Multi-company/Branch identity benar.
- [ ] Authorization server-side lulus.
- [ ] Idempotency dan partial failure lulus.
- [ ] Formatter lulus.
- [ ] Focused tests lulus.
- [ ] Regression tests terdampak lulus.
- [ ] Frontend build lulus.
- [ ] Documentation link dan code map checks lulus.
- [ ] `git diff --check` lulus.
- [ ] CI Pull Request hijau.
- [ ] UAT staging dan reconciliation disetujui.
- [ ] Rollout dan rollback diverifikasi.
- [ ] Implementation report selesai.

## 24. Definition of Done

- Kalender menampilkan rencana lokal dan Production Order ESB dengan source yang jelas.
- Rencana lokal existing tidak mengalami regresi.
- Production Order ESB hanya read-only.
- Snapshot lokal tetap tersedia saat ESB gagal.
- Pipeline multi-company/multi-branch aman, idempotent, bounded, dan observable.
- Branch authorization mencegah data leak.
- UI mudah dipahami dan responsif.
- Test, CI, documentation, staging UAT, production rollout, dan monitoring selesai.
- README modul menjelaskan behavior aktual, bukan hanya rencana.

## 25. Format update selama pengerjaan

Pada akhir setiap phase, laporkan:

1. phase dan status;
2. requirement/keputusan yang dikunci;
3. file dibuat/diubah;
4. migration/schema impact;
5. permission impact;
6. queue/scheduler impact;
7. test yang dijalankan beserta jumlah hasil;
8. deviasi dari PRD/plan dan alasannya;
9. risiko/open question;
10. apakah aman melanjutkan ke phase berikutnya.

Jangan lanjut melewati approval gate yang diwajibkan.

## 26. Format laporan akhir

1. Branch aktif dan commit basis.
2. Ringkasan perubahan end-to-end.
3. Status setiap phase.
4. File utama yang dibuat/diubah.
5. Schema dan data compatibility.
6. Authorization dan security.
7. Queue, scheduler, dan integrasi ESB.
8. UI desktop/mobile.
9. Test/formatter/build/doc checks dan hasilnya.
10. UAT, rollout, dan health verification.
11. Deviasi dari PRD dan keputusan final.
12. Risiko tersisa dan pekerjaan lanjutan.

## 27. Larangan perubahan eksternal tanpa instruksi terpisah

Jangan melakukan tindakan berikut hanya karena implementasi kode selesai:

- commit;
- push;
- membuka Pull Request;
- merge;
- deploy staging atau production;
- menjalankan migration pada shared environment;
- menjalankan backfill pada staging/production;
- mengubah `.env` atau GitHub secrets/variables;
- mengaktifkan scheduler;
- menjalankan request nyata ke ESB;
- mengubah data production.

Semua tindakan tersebut memerlukan instruksi atau approval terpisah sesuai risiko dan workflow repository.
```

