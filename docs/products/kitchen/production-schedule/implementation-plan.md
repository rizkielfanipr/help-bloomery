---
document_id: KITCHEN-PRODUCTION-SCHEDULE-PLAN
title: Implementation Plan — Jadwal Produksi dan Production Order ESB
status: in-development
version: 1.3
owner: Kitchen
technical_owner: Information Technology
created_at: 2026-10-27
updated_at: 2026-10-28
target_release: TBD
related_documents:
  - docs/products/kitchen/production-schedule/prd.md
  - docs/architecture/integrations/esb.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# Implementation Plan — Jadwal Produksi dan Production Order ESB

Acuan requirement saat ini: `docs/products/kitchen/production-schedule/prd.md`. Dokumen ini hanya mengatur urutan teknis; requirement tidak ditulis ulang di sini.

> **Gate requirement:** PRD canonical saat ini menyatakan bahwa Jadwal Produksi belum terintegrasi dengan ESB. Phase 0 wajib memperbarui dan memperoleh persetujuan atas PRD sebelum phase implementasi dimulai. Plan ini tidak mengubah requirement secara sepihak.

> **Amendment v3.0 (28 Oktober 2026, PRD §38):** rencana produksi lokal dan tabelnya dihapus; halaman menjadi kalender Production Order ESB read-only tanpa toggle layer. Bagian plan yang menyebut rencana lokal, dua sumber, filter Sumber, atau `KITCHEN_PRODUCTION_ORDERS_ENABLED` hanya berlaku sebagai riwayat; langkah remediasi tercatat di [Implementation Report §9](reports/implementation-report.md).

## 1. Scope implementasi

- **Termasuk:** mempertahankan kalender rencana produksi lokal; membaca Production Order dari ESB; menyimpan snapshot header, detail, dan material secara lokal; menampilkan rencana lokal dan Production Order ESB sebagai dua sumber berbeda pada tanggal produksinya; detail read-only; filter Branch, tanggal, status, dan sumber; sinkronisasi queue; retry; authorization Branch; observability; rollout bertahap.
- **Tidak termasuk:** membuat, mengubah, menghapus, authorize, reject, menyelesaikan, atau mengembalikan Production Order di ESB; mengubah status jadwal lokal berdasarkan status ESB; mencocokkan otomatis jadwal lokal dengan Production Order; menghitung actual production, yield, waste, costing, atau kebutuhan material; mengganti katalog BOM R&D; menggabungkan data lintas company berdasarkan product code.

## 2. Baseline repository

- Baseline dokumen dan kode: `main` pada `ca3fe753` ketika plan dibuat.
- Jadwal produksi lokal sudah berjalan melalui `KitchenProductionSchedulePage`, model header/item, Action save/cancel, Policy, dan query kalender.
- Kalender saat ini hanya membaca database lokal dan tidak memanggil ESB.
- Mapping Branch ke company/branch ESB sudah tersedia melalui `BranchEsbCode` dan harus menjadi sumber scope integrasi.
- Mapping bisnis yang disetujui: Kitchen Jateng=`BLSS/KTJ`, Kitchen Surabaya=`BLO13/HOF`, dan Kitchen Jakarta=`BLHJ/TCHL`; nilai `esb_branch_id` numerik berasal dari sinkronisasi API.
- `BLHJ` belum terdapat pada `config('esb.core.companies')` pada saat keputusan dibuat dan harus ditambahkan secara additive beserta environment credential-nya.
- Autentikasi serta retry token ESB sudah tersedia melalui `EsbCoreClient`; implementasi tidak boleh membuat client autentikasi kedua.
- Belum ada service, model snapshot, job, command, atau test Production Order ESB pada repository.
- Endpoint yang tersedia bersifat read-only untuk scope ini: daftar Production Order, detail order, daftar detail belum selesai, status selesai, material terkelompok, dan print group.
- Endpoint utama kalender adalah daftar Production Order dengan filter `branchID`, rentang tanggal, status, BOM type, sort, dan pagination. Endpoint detail digunakan untuk mengambil isi order berdasarkan `productionOrderNum`.

Audit Phase 0 harus memverifikasi kembali baseline terhadap commit terbaru sebelum kode dibuat.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Requirement | `docs/products/kitchen/production-schedule/prd.md` | ubah pada Phase 0 |
| Dokumentasi fitur | `docs/products/kitchen/production-schedule/{README.md,implementation-plan.md,rollout.md}` | ubah/baru |
| Dokumentasi modul | `docs/products/kitchen/README.md` | ubah setelah perilaku tersedia |
| Integrasi ESB | `app/Services/EsbProductionOrderService.php` | baru |
| Mapping Branch | `app/Models/BranchEsbCode.php`, resolver existing | reuse/ubah bila terbukti perlu |
| Snapshot header | `app/Models/EsbProductionOrder.php` | baru |
| Snapshot detail | `app/Models/EsbProductionOrderDetail.php` | baru |
| Sync run | `app/Models/EsbProductionOrderSyncRun.php` | baru bila audit tidak menemukan model generik yang sesuai |
| Migration | `database/migrations/*_create_esb_production_order_*.php` | baru, additive |
| Factory | `database/factories/EsbProductionOrder*.php` | baru |
| Sinkronisasi | `app/Jobs/Kitchen/SyncEsbProductionOrdersJob.php` | baru |
| Orkestrasi | `app/Services/Kitchen/ProductionOrderSyncPlanner.php` atau Action setara | baru bila diperlukan lebih dari satu pemanggil |
| Command | `app/Console/Commands/SyncEsbProductionOrdersCommand.php` | baru |
| Scheduler | `routes/console.php` | ubah |
| Query kalender | `app/Services/Kitchen/ProductionScheduleCalendarQuery.php` | ubah atau komposisi service baru |
| Detail builder | `app/Services/Kitchen/ProductionScheduleDetailBuilder.php` | ubah atau dipisah menurut sumber |
| Back Office | `app/Filament/Helpdesk/Pages/KitchenProductionSchedulePage.php` | ubah |
| View | `resources/views/filament/helpdesk/pages/kitchen-production-schedule-page.blade.php` | ubah |
| Authorization | `app/Policies/KitchenProductionSchedulePolicy.php` | audit/ubah minimal |
| Permission seeder | file seeder permission existing hasil audit | ubah hanya bila permission baru disetujui |
| Service tests | `tests/Feature/EsbProductionOrderServiceTest.php` | baru |
| Pipeline tests | `tests/Feature/KitchenProductionOrderSyncTest.php` | baru |
| UI/regression | `tests/Feature/KitchenProductionSchedulePageTest.php` dan test Kitchen existing | ubah |
| Arsitektur | `docs/architecture/{data-model.md,integrations/esb.md}` | regenerasi/ubah setelah implementasi |

Nama class dan path baru bersifat perkiraan. Implementasi wajib mengikuti konvensi sibling dan hasil audit Phase 0, bukan membuat abstraksi hanya untuk mencocokkan tabel ini.

## 4. Dependency dan integration boundary

### 4.1 Batas sumber data

| Sumber | Kepemilikan | Mutability dari Bloomery Office | Penggunaan |
|---|---|---|---|
| Jadwal produksi lokal | Bloomery Office | create, update, cancel sesuai Policy | rencana tim Kitchen |
| Production Order | ESB Core | read-only | order/realisasi operasional yang tercatat di ESB |
| BOM Assembly | Katalog lokal R&D | read-only dari modul Kitchen | pemilihan item pada rencana lokal |
| Branch mapping | Bloomery Office | dikelola melalui master mapping existing | menentukan company dan Branch ESB yang boleh dibaca |

Rencana lokal dan Production Order ESB tidak mempunyai foreign key atau status sinkronisasi satu sama lain pada versi pertama. Kesamaan tanggal, BOM, product, atau quantity hanya untuk tampilan; bukan bukti bahwa dua record adalah record yang sama.

### 4.2 Batas pemanggilan API

- Seluruh request Production Order melewati `EsbCoreClient` dan service khusus Production Order.
- Service bertanggung jawab atas query parameter, pagination, normalisasi envelope, validasi bentuk response, timeout, dan klasifikasi error.
- Filament/Livewire dan Blade tidak memanggil ESB langsung.
- Kalender dan detail membaca snapshot lokal agar halaman tetap dapat digunakan ketika ESB lambat atau tidak tersedia.
- Job berjalan per `BranchEsbCode`, rentang tanggal, dan cursor/page; payload job tidak membawa token.
- `productionOrderNum` diperlakukan sebagai identifier eksternal, bukan identifier global tanpa company context.
- Tidak ada endpoint `POST`, `PUT`, `PATCH`, atau `DELETE` dalam scope.

### 4.3 Endpoint minimum

| Endpoint | Tujuan | Waktu pemanggilan |
|---|---|---|
| `GET /core/production/production-order` | Header kalender dan status | sync terjadwal/manual |
| `GET /core/production/production-order/{productionOrderNum}` | Header, detail, dan material lengkap | setelah header baru/berubah atau refresh detail |

Endpoint browse, unfinished, return, grouped material, print group, dan `is-finished` tidak digunakan pada MVP kecuali characterization test membuktikan endpoint utama tidak memenuhi kontrak tampilan yang disetujui.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | Requirement alignment, API characterization, audit mapping/permission/UI, keputusan data retention | PR 1 — docs dan contract tests | Plan direview Kitchen dan IT |
| 1 | ESB read client, DTO/normalizer seperlunya, fake HTTP tests, error contract | PR 2 — integration foundation | PRD berstatus `approved`; response nyata terverifikasi tanpa menyimpan data sensitif |
| 2 | Schema snapshot additive, model, factory, upsert Action/service, sync-run tracking | PR 2 — integration foundation | Phase 1 lulus |
| 3 | Job per mapping, pagination, detail hydration, command, scheduler, retry/idempotency | PR 3 — sync pipeline | Phase 2 lulus; queue staging aktif |
| 4 | Query gabungan rencana lokal + snapshot ESB, server-side filter dan authorization | PR 4 — calendar integration | Snapshot staging representatif tersedia |
| 5 | UI kalender, legend sumber, drawer/detail read-only, state loading/empty/stale/error | PR 4 — calendar integration | Phase 4 lulus |
| 6 | UAT staging, backfill terbatas, observability, rollout production, dokumentasi akhir | PR 5 bila ada perbaikan rollout | Semua test dan quality gate lulus |

### Phase 0 — Requirement dan contract gate

1. Amend PRD untuk memasukkan Production Order ESB sebagai overlay read-only dan mempertahankan jadwal lokal.
2. Tetapkan rentang histori awal, frekuensi refresh, retention snapshot, dan status ESB yang ditampilkan.
3. Verifikasi response nyata untuk company/Branch uji: pagination, format tanggal, tipe ID, nilai nullable, status, duplicate number, dan perubahan status.
4. Pastikan mapping menggunakan `esb_branch_id` untuk parameter `branchID`; jangan memakai primary key lokal atau menebak dari kode Branch.
5. Putuskan apakah permission view existing cukup. Rekomendasi awal: reuse permission view dan branch scope existing karena data berada pada halaman yang sama; buat permission terpisah hanya jika owner bisnis memerlukan pemisahan akses.
6. Catat keputusan jangka panjang sebagai ADR bila pilihan overlay versus penggantian kalender masih diperdebatkan.
7. Verifikasi tiga pasangan canonical: `BLSS/KTJ`, `BLO13/HOF`, dan `BLHJ/TCHL`; pastikan masing-masing terhubung ke Branch lokal ber-Brand `Kitchen` dan memperoleh `esb_branch_id` dari Sync Branch ESB.
8. Audit seluruh query/filter/validator/policy/planner agar `access_all_branches` tidak pernah memperluas scope ke Brand selain `Kitchen`.

**Exit criteria:** PRD approved, contoh kontrak API sudah menjadi test fixture anonim, dan tidak ada open decision yang mengubah schema atau authorization.

#### Hasil audit Phase 0 (27 Oktober 2026, basis `main` `ca3fe753`)

Amendment ditulis di PRD §25–§37 (status `in-review`). Temuan yang menyesuaikan plan ini:

1. **Kontrak API belum dapat diverifikasi.** Repository tidak mempunyai dokumentasi atau contoh response Production Order, dan request nyata ke ESB tidak dijalankan tanpa instruksi terpisah. Fixture anonim belum dibuat agar tidak mengarang kontrak; hipotesis bentuk response dicatat di PRD §27.2 dan menjadi open question PRD §37.4.
2. **`EsbCoreClient` belum mempunyai error taxonomy** (Phase E `esb.md` belum dikerjakan): connection failure dibungkus `RuntimeException` dengan exception asli di `previous`, dan GET tidak di-retry. Klasifikasi error (PRD §27.3) dibuat di service Production Order dengan membaca status response dan `previous`; retry dilakukan oleh job, bukan client.
3. **Multiple mapping.** `branch_esb_codes` unique pada `branch_id + esb_branch_code + esb_comcode`, jadi satu Branch dapat mempunyai beberapa mapping. Keputusan terbaru hanya mengizinkan mapping canonical tiga Kitchen pada PRD §28.3/K14. Jangan menyinkronkan mapping lain berdasarkan urutan atau label; bila perlu, tambahkan penanda/selector additive pada Master Branch setelah audit.
4. **Pola sibling:** `SyncOrderingSourceJob` + `OrderingSyncRun` (idempotency key, `ShouldBeUnique`, `WithoutOverlapping`, tries/backoff, `failed()`), `OrderingSourceResult` (hasil baca ternormalisasi dengan flag `retryable`), `MonitoringSanitizer` (pesan error). Nama class pada §3 disesuaikan dengan pola ini saat implementasi.
5. **Queue:** worker production `queue:work redis --queue=default --timeout=90`, `retry_after` 90 detik. Unique job Laravel tidak berlaku di dalam batch; job detail memakai `WithoutOverlapping` (PRD §30.5). Tabel `job_batches` sudah ada, tetapi batching belum dipakai di aplikasi.
6. **Toggle rollback** memerlukan konfigurasi baru (layer kalender dan scheduler). `config/kitchen.php` tidak ada; nama variabel final ditetapkan di `rollout.md`.
7. **Branch scope** snapshot memakai aturan yang sama dengan `KitchenProductionSchedule::scopeVisibleTo` dan `KitchenProductionSchedulePolicy::view`, tetapi selalu ditambah constraint Brand `Kitchen`, termasuk untuk all-branch (K7, K9, K15).
8. **Path endpoint:** `ESB_CORE_BASE_URL` sudah berakhiran `/core`, sehingga path di service adalah `/production/production-order` (sama dengan pola BOM `/product/...`).

Amendment disetujui user pada 27 Oktober 2026. Open question PRD §37.4 nomor 1–3 belum terjawab; implementasi memakai asumsi yang tercatat di PRD §37.4.

#### Status phase (27 Oktober 2026)

Phase 0–5 selesai di branch `feature/kitchen-production-order-esb`; Phase 6 baru dokumentasinya. Rincian, keputusan desain final, dan deviasi ada di [Implementation Report](reports/implementation-report.md); langkah staging/production ada di [Rollout](rollout.md).

### Phase 1 — Client Production Order ESB

1. Tambahkan method daftar dan detail melalui `EsbCoreClient`.
2. Normalisasi response menjadi bentuk internal yang eksplisit; jangan menyebarkan associative array mentah ke UI.
3. Implementasikan pagination hingga halaman terakhir dengan batas pengaman jumlah page.
4. Tolak response sukses yang kehilangan envelope/field identity wajib.
5. Bedakan authentication failure, validation error, retryable transport/server error, dan permanent mapping error.

**Exit criteria:** seluruh kontrak request/response, pagination, refresh token, timeout, malformed payload, dan error classification lulus dengan `Http::fake()`.

### Phase 2 — Snapshot lokal

1. Tambahkan schema header, detail, dan sync run secara additive.
2. Upsert header memakai identity company + Production Order number.
3. Upsert detail memakai identity header + external detail ID.
4. Simpan material sebagai child table hanya bila perlu difilter/dianalisis; rekomendasi MVP adalah snapshot JSON per detail karena material hanya ditampilkan read-only. Keputusan final dibuat dari volume dan kebutuhan query Phase 0.
5. Tandai waktu source diambil, waktu source terakhir terlihat, dan hash/version payload yang dinormalisasi.
6. Jangan menghapus snapshot hanya karena tidak muncul pada satu sync parsial.

**Exit criteria:** sync payload yang sama bersifat idempotent, perubahan status/detail memperbarui snapshot, dan kegagalan satu order tidak merusak snapshot order lain.

### Phase 3 — Pipeline sinkronisasi

1. Planner hanya memilih mapping canonical Branch Kitchen: Jateng=`BLSS/KTJ`, Surabaya=`BLO13/HOF`, Jakarta=`BLHJ/TCHL`; mapping harus aktif, memiliki `esb_branch_id`, dan company code valid.
2. Tambahkan `BLHJ` pada konfigurasi `esb.core.companies`; credential hanya berasal dari environment. `BLSS`, `BLO13`, dan `BLHJ` menjadi allowlist Production Order.
3. Scheduler mendispatch job kecil per mapping dan window tanggal; tidak menjalankan seluruh sinkronisasi dalam satu process besar.
4. Default window incremental ditetapkan di PRD/rollout; backfill menggunakan command eksplisit, bukan memperbesar scheduler harian.
5. Job list meng-upsert header lalu menjadwalkan detail untuk record baru/berubah.
6. Gunakan uniqueness key berbasis mapping + rentang + tipe sync untuk mencegah job ganda.
7. Terapkan retry/backoff terukur dan jangan membuat retry loop di dalam Livewire.

**Exit criteria:** pipeline berhasil untuk beberapa company/Branch, tidak melewati branch mapping, tidak kehabisan memory, dan kegagalan satu mapping tidak memblokir mapping lain.

### Phase 4 — Query kalender

1. Pertahankan query jadwal lokal existing sebagai sumber `local_schedule`.
2. Tambahkan query snapshot ESB sebagai sumber `esb_production_order` dengan visible calendar range dan branch scope yang sama.
3. Ubah keduanya menjadi calendar event view model yang mempunyai identity, source, date, branch, label, status, item count, dan action capability.
4. Jangan melakukan union yang menghilangkan identity sumber atau menggabungkan quantity/UOM berbeda.
5. Detail resolver memilih builder berdasarkan source dan selalu mengulang Policy/branch authorization.
6. Terapkan `kitchenBrand()` tanpa pengecualian pada filter Branch, schedulable Branch, kalender lokal/ESB, status sync, dan detail; all-branch hanya melewati pembatasan assignment user, bukan pembatasan Brand.

**Exit criteria:** kalender tidak N+1, source tidak tertukar, filter dilakukan di SQL, dan direct request ke snapshot Branch lain ditolak.

### Phase 5 — UI

1. Pertahankan layout kalender desktop dan agenda mobile existing.
2. Tambahkan legend/filter `Semua`, `Rencana`, dan `Production Order ESB`.
3. Gunakan gaya visual berbeda tetapi konsisten: rencana lokal editable; order ESB memiliki label `ESB` dan read-only.
4. Klik order ESB membuka detail berisi header, status, BOM/product, quantity, UOM, dan tabel material bila tersedia.
5. Action create/edit/cancel hanya muncul untuk rencana lokal.
6. Tampilkan `Terakhir disinkronkan` dan peringatan data lama secara jelas tanpa menghilangkan snapshot terakhir yang valid.

**Exit criteria:** pengguna dapat membedakan rencana dan order aktual tanpa membaca detail, mobile tidak horizontal scroll, modal dapat di-scroll, dan seluruh state UI teruji.

### Phase 6 — UAT dan rollout

1. Jalankan backfill staging pada Branch Kitchen terbatas.
2. Bandingkan jumlah dan status order per hari dengan ESB untuk sampel minimal dua company/Branch.
3. Uji ESB unavailable, token expired, mapping hilang, pagination lebih dari satu page, dan order berubah status.
4. Aktifkan scheduler setelah snapshot dan UI lolos UAT.
5. Rollout production dimulai dengan sync manual terbatas, baru scheduler.

**Exit criteria:** owner Kitchen menyetujui keterbacaan kalender/detail, IT menyetujui observability dan rollback, serta production health tetap hijau.

## 6. Migration strategy

Migration bersifat additive dan tidak mengubah tabel jadwal lokal.

Rancangan minimum:

### `esb_production_orders`

- reference ke `branch_esb_codes` atau snapshot mapping identity;
- local `branch_id` untuk query scope yang efisien;
- `company_code`, `esb_branch_id`, dan snapshot nama Branch;
- `production_order_num`, tanggal order, BOM type, status ID/nama, additional info;
- actor/timestamp ESB yang memang tersedia;
- `source_fetched_at`, `source_last_seen_at`, `payload_hash`;
- unique `(company_code, production_order_num)`;
- index `(branch_id, production_order_date)` dan `(branch_id, status_id, production_order_date)`.

### `esb_production_order_details`

- FK snapshot header;
- external detail ID, BOM ID/name/type;
- product detail ID/code/name, quantity, UOM, notes, finished flag;
- material snapshot atau relasi material sesuai keputusan Phase 0;
- unique `(esb_production_order_id, external_detail_id)`;
- index pada BOM, product detail, dan product code hanya jika query aktual memerlukannya.

### `esb_production_order_sync_runs`

- mapping, range, type, status, counters, cursor/page, started/finished timestamps;
- error class/code/message yang sudah disanitasi;
- unique/idempotency key untuk mencegah run identik aktif bersamaan.

Migration tidak melakukan pemanggilan ESB atau backfill. Backfill dijalankan setelah deploy melalui command terpisah. Foreign key ke master lokal yang dapat berubah dibuat nullable/restrictive agar snapshot histori tidak ikut terhapus.

## 7. Data compatibility

- Seluruh `kitchen_production_schedules` dan item existing tetap utuh serta tetap editable/cancellable sesuai statusnya.
- Event lokal mempertahankan identity dan route/action existing.
- Snapshot ESB tidak mengubah record lokal dan tidak membuat jadwal lokal otomatis.
- Production Order lama dapat ditambahkan bertahap melalui backfill tanpa mengubah tampilan tanggal di luar range yang sudah disinkronkan.
- Mapping yang hilang atau dinonaktifkan menghentikan sync baru, tetapi snapshot lama tetap dapat dibaca oleh user yang masih berhak atas Branch lokalnya.
- Nama/status/product disimpan sebagai snapshot agar histori tetap terbaca ketika data ESB berubah.
- Record yang tidak lagi muncul pada satu response tidak langsung dihapus; deletion/archival hanya boleh dilakukan setelah kontrak ESB dan retention disetujui.
- Existing URL, menu, dan permission dasar dipertahankan.

## 8. Authorization

- Menu tetap memerlukan `view kitchen production schedules`.
- Scope snapshot ESB selalu berasal dari Branch lokal hasil mapping dan mekanisme `canAccessBranch()`/Policy canonical.
- `view all branch kitchen production schedules` hanya memperluas Branch scope; tidak menggantikan permission view fitur.
- Parameter company code, ESB branch ID, Production Order number, source, dan local Branch dari browser tidak dipercaya dan harus di-query ulang.
- Create/update/cancel permission tidak berlaku pada event ESB karena event tersebut read-only.
- Command, scheduler, dan queue tidak menggunakan user browser; scope mapping ditentukan server-side.
- Bila permission khusus Production Order akhirnya disetujui Phase 0, tambahkan secara additive, seed ke role terpilih, dan uji default-deny. Jangan hardcode nama role.

## 9. UI

- Halaman tetap `Kitchen → Jadwal Produksi`; tidak menambah menu besar baru pada MVP.
- Filter Brand/Branch mengikuti pola select searchable yang sudah dipakai modul Sales Information bila struktur datanya relevan; jangan memakai multi-select native browser.
- Filter source: `Semua`, `Rencana`, `Production Order ESB`.
- Filter status harus menjaga namespace sumber; label status ESB tidak dipetakan secara paksa ke `Scheduled/Cancelled`.
- Calendar chip minimal menampilkan source badge, nomor/nama ringkas, Branch, status, dan jumlah detail.
- Detail Production Order menggunakan modal/drawer read-only dengan header ringkas dan tabel responsif untuk item/material.
- Modal mempunyai tinggi maksimum viewport dan satu area scroll konten; footer/action tidak menutup baris terakhir.
- Empty state membedakan: belum ada order pada filter, mapping belum tersedia, sync belum pernah berjalan, dan sync terakhir gagal.
- Data stale tetap ditampilkan dengan timestamp dan warning; jangan mengganti seluruh halaman dengan error ketika snapshot valid masih ada.
- Gunakan token, table, badge, button, modal, spacing, responsive state, dan empty/error state dari `docs/standards/ui-consistency.md`.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Contract fixture anonim tervalidasi; documentation links lulus |
| 1 | request query/path, auth refresh, pagination, empty result, nullable field, malformed envelope, 400/401/500/timeout |
| 2 | schema/index/unique, idempotent upsert, status/detail update, snapshot retention, decimal quantity |
| 3 | multi-company/multi-branch dispatch, missing mapping, retry/backoff, partial failure, uniqueness, bounded memory, incremental dan backfill window |
| 4 | visible range, source identity, Branch scope, all-branch permission, forged ID denial, SQL filtering, N+1 guard |
| 5 | legend/filter, local action isolation, ESB read-only detail, loading/empty/stale/error, desktop/mobile, modal scroll |
| 6 | focused Kitchen suite, ESB regression suite, scheduler/queue smoke, staging reconciliation, production health |

Perubahan PHP wajib diikuti `vendor/bin/pint --dirty --format agent` dan focused Pest tests. Frontend/view changes wajib menjalankan build yang berlaku di repository dan verifikasi browser pada desktop serta mobile.

## 11. Observability

- Catat satu sync run per mapping dan range dengan status `queued`, `running`, `succeeded`, `partial`, atau `failed`.
- Counter minimum: page dibaca, header diterima, header dibuat/diubah/tidak berubah, detail berhasil/gagal, dan durasi.
- Simpan error code/class dan pesan sanitasi; jangan simpan token, credential, atau payload penuh yang berisi data sensitif.
- Gunakan logging terstruktur dengan company code, mapping ID, range, dan run ID.
- Integrasikan health/last-success ke System Monitoring hanya setelah data dasar stabil; jangan membuat dashboard baru pada PR foundation.
- Alarm operasional minimum: tidak ada sync sukses melebihi ambang yang disetujui, authentication failure, failure berulang untuk mapping yang sama, dan antrean sync tertunda.

## 12. Rollout

1. Merge dan deploy schema/client dalam keadaan belum dijadwalkan.
2. Jalankan sync manual satu Branch Kitchen dan rentang pendek di staging.
3. Rekonsiliasi header/detail dengan ESB.
4. Jalankan backfill staging sesuai window yang disetujui.
5. Aktifkan UI staging dan selesaikan UAT.
6. Deploy production dengan scheduler masih nonaktif atau tanpa dispatch otomatis.
7. Sync manual satu Branch production dan verifikasi snapshot, calendar, log, memory, queue, serta health.
8. Aktifkan scheduler bertahap untuk seluruh mapping Kitchen.
9. Pantau minimal satu siklus penuh sebelum menutup rollout.

Konfigurasi, command final, estimasi volume, UAT checklist, dan prosedur server wajib ditulis di `rollout.md` setelah Phase 0 selesai; jangan mengarang nama environment variable sebelum implementasi menentukan kebutuhan nyata.

## 13. Rollback

- UI dapat mematikan layer ESB dan kembali menampilkan jadwal lokal tanpa mengubah data lokal.
- Scheduler Production Order dapat dihentikan tanpa menghentikan queue global.
- Job yang sedang berjalan harus aman di-retry atau dibiarkan selesai karena operasi snapshot idempotent.
- Rollback kode tetap kompatibel dengan tabel additive; tabel snapshot tidak di-drop pada emergency rollback.
- Snapshot hasil sync tidak dihapus otomatis.
- Jika release gagal health check, gunakan atomic rollback release existing.
- Schema removal dan penghapusan snapshot hanya dilakukan melalui perubahan terpisah setelah backup, audit reference, dan persetujuan.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Pengguna mengira rencana lokal sama dengan order ESB | Pisahkan badge, legend, detail, identity, dan action; jangan auto-merge |
| `branchID` salah memakai ID lokal | Resolve hanya dari `BranchEsbCode.esb_branch_id` dan test forged/missing mapping |
| Nomor order sama lintas company | Unique key memakai company code + Production Order number |
| API lambat membuat kalender tidak dapat dibuka | Kalender membaca snapshot lokal; sync melalui queue |
| Pagination tidak selesai atau loop | Validasi `page/next`, max-page guard, dan observability |
| Snapshot dianggap hilang karena sync parsial | Tidak hard-delete ketika record tidak terlihat dalam satu run |
| Job besar kehabisan memory | Job per mapping/range, page streaming/chunking, detail job terpisah |
| Token/credential bocor | Reuse client existing; sanitasi log; tidak menyimpan token pada model/job |
| Scope Branch bocor | Policy + SQL branch scope + re-query server-side + direct-action tests |
| Status ESB bertambah | Simpan ID dan label snapshot; tampilkan fallback aman tanpa merusak enum lokal |
| Detail/material bervolume besar | Hydrate hanya order baru/berubah; simpan bentuk minimum; index berbasis query nyata |
| Scheduler membebani ESB | Interval dan concurrency bertahap, unique job, retry/backoff, manual backfill terpisah |
| PRD lama bertentangan dengan implementasi | Phase 0 gate; kode tidak dimulai sebelum amendment approved |

## 15. Quality gates

- [ ] Amendment PRD berstatus `approved` sebelum kode dimulai.
- [ ] Baseline repository dan API contract diaudit ulang pada commit implementasi.
- [ ] Tidak ada mutation Production Order ESB.
- [ ] Tidak ada direct API call dari Filament/Livewire/Blade.
- [ ] Migration additive dan backfill terpisah dari migration.
- [ ] Authorization Branch diuji server-side.
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Focused test terdampak lulus.
- [ ] Regression test Jadwal Produksi lokal lulus.
- [ ] Frontend production build lulus jika view/aset berubah.
- [ ] `bash ops/ci/check-doc-links.sh`
- [ ] `bash ops/ci/check-code-map.sh`
- [ ] CI Pull Request hijau.
- [ ] UAT staging dan rekonsiliasi ESB disetujui.
- [ ] Rollout dan rollback rehearsal terdokumentasi.

## 16. Definition of Done

- [ ] PRD canonical telah mengatur dengan jelas perbedaan rencana lokal dan Production Order ESB.
- [ ] Semua phase selesai sesuai PRD approved.
- [ ] Kalender menampilkan kedua sumber tanpa menggabungkan atau mengubah record secara otomatis.
- [ ] Detail Production Order tersedia secara read-only dari snapshot lokal.
- [ ] Sinkronisasi multi-company/multi-branch idempotent, terukur, dan aman terhadap kegagalan parsial.
- [ ] Policy dan Branch scope mencegah akses lintas cabang.
- [ ] Scheduler, queue, monitoring, dan recovery sudah diuji di staging.
- [ ] Jadwal lokal existing tidak mengalami regresi.
- [ ] Implementation report ditulis di `reports/implementation-report.md`.
- [ ] README fitur, README Kitchen, data model, RBAC/panel bila berubah, dan dokumentasi integrasi ESB diperbarui.
- [ ] Production rollout sehat dan snapshot pertama telah direkonsiliasi.
