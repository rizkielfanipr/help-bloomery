---
document_id: KITCHEN-PRODUCTION-SCHEDULE-ESB-REPORT
title: Laporan Implementasi — Production Order ESB pada Jadwal Produksi
status: draft
version: 1.0
owner: Kitchen
technical_owner: Information Technology
created_at: 2026-10-27
updated_at: 2026-10-27
target_release: TBD
related_documents:
  - docs/products/kitchen/production-schedule/prd.md
  - docs/products/kitchen/production-schedule/implementation-plan.md
  - docs/products/kitchen/production-schedule/rollout.md
supersedes: null
---

# Laporan Implementasi — Production Order ESB pada Jadwal Produksi

Acuan: `docs/products/kitchen/production-schedule/prd.md` §25–§37 (amendment v2.0). Branch `feature/kitchen-production-order-esb`, basis `main` `ca3fe753`.

## 1. Status per phase

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 — Audit, amendment PRD, kontrak API | Selesai | Belum di-commit | Amendment disetujui user 27 Oktober 2026 (K1–K13). Kontrak response terverifikasi terhadap BLSS (§27.2) |
| 1 — Client read-only ESB | Selesai | Belum di-commit | `EsbProductionOrderService` + `EsbProductionOrderNormalizer` di atas `EsbCoreClient` |
| 2 — Schema dan snapshot | Selesai | Belum di-commit | Tiga tabel additive, model, factory, Action upsert header/detail |
| 3 — Queue, command, scheduler | Selesai | Belum di-commit | Planner, dua job, dua command, scheduler per jam dengan toggle, pruning run |
| 4 — Query dan authorization | Selesai | Belum di-commit | `ProductionOrderCalendarQuery`, `ProductionOrderDetailBuilder`, `EsbProductionOrderPolicy` |
| 5 — UI | Selesai (kode dan test Livewire) | Belum di-commit | Verifikasi browser desktop/mobile belum dilakukan (§5) |
| 6 — Dokumentasi, UAT, rollout | Sebagian | — | Dokumen selesai; UAT staging, rekonsiliasi, dan rollout production belum dijalankan (butuh persetujuan terpisah) |

## 2. Ringkasan perubahan

- **Integrasi:** `app/Services/EsbProductionOrderService.php` membaca `GET /production/production-order` dan `/{productionOrderNum}` melalui `EsbCoreClient`, mengklasifikasikan error (`EsbProductionOrderErrorKind`: retryable, authentication, validation, configuration, malformed), dan menolak baris dari Branch/window/nomor lain. Normalisasi field ESB dipusatkan di `app/Services/Kitchen/EsbProductionOrderNormalizer.php`.
- **Data:** `esb_production_orders`, `esb_production_order_details` (material JSON), `esb_production_order_sync_runs`. Identity header `company_code + production_order_num`.
- **Pipeline:** `ProductionOrderSyncPlanner` memilih mapping aktif dan lengkap pada Branch Kitchen aktif, membagi window maksimal 31 hari, dan membuat satu run per dispatch dengan `active_key` unik. `SyncEsbProductionOrdersJob` membaca halaman, menyimpan per halaman (`StoreProductionOrderHeadersAction`), dan mendispatch `SyncEsbProductionOrderDetailJob` untuk order baru/berubah/belum lengkap (`StoreProductionOrderDetailAction`). Run ditutup `succeeded`/`partial`/`failed`.
- **Command:** `kitchen:sync-production-orders` (incremental, `--dry-run`) dan `kitchen:backfill-production-orders` (dry-run default, `--execute`, guard 120 hari).
- **Scheduler:** incremental setiap jam bila `kitchen.production_orders.sync_enabled`; `model:prune` riwayat run 90 hari.
- **Kalender:** `KitchenProductionSchedulePage` menambah filter Sumber dan Status ESB, chip `ESB` read-only, banner status sync per Branch, dan modal detail Production Order. Layer hanya tampil bila `kitchen.production_orders.enabled`.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| K1–K11 sesuai usulan Phase 0 | Disetujui user | PRD §37.3 |
| K13 — hanya Company Code `BLSS` yang disinkronkan dulu, lewat allowlist `kitchen.production_orders.company_codes` | Arahan user; company lain diaktifkan dengan konfigurasi setelah kontraknya diverifikasi | PRD §28.5a, §37.3 |
| K12 — riwayat run dipangkas setelah 90 hari | Run dibuat per eksekusi; snapshot tidak terdampak | PRD §29.3, §37.3 |
| Run per eksekusi + `active_key` unik nullable | Menjaga riwayat sekaligus menjamin satu run aktif per window di database | PRD §29.3 |
| Tanpa `ShouldBeUnique`; `WithoutOverlapping` per run/order | Dispatch yang ditolak unique lock membuat run menunggu hasil detail selamanya | PRD §30.5 |
| Time budget 45 detik lalu job lanjutan | Worker production `--timeout=90`; satu window bisa banyak halaman | PRD §30.5 |
| Per-source list `schedules`/`orders` per hari, bukan satu view model gabungan | Menjaga struktur rencana lokal dan test existing tanpa regresi; identity dibedakan lewat action dan `wire:key` | PRD §33.3 |
| Detail lama tidak dihapus bila hilang dari response detail | Kontrak kelengkapan response detail belum terbukti | PRD §30.4 |
| Toggle `config/kitchen.php` | Rollback layer dan scheduler tanpa menyentuh rencana lokal. Berbeda dari `config/kitchen.php` max-age lama yang dilarang §24 | PRD §36, rollout §2 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §29.3, §30.1, §30.5, §33.3 | Desain final berbeda dari draft amendment (lihat §3) | Ditemukan saat implementasi | PRD sudah diperbarui di branch yang sama |
| §27.1 | BLSS dibaca dengan credential global, bukan `EsbCoreClient` per-company | Akun per-company BLSS mendapat `403`; akun global terbukti company BLSS | Konfigurasi `KITCHEN_PRODUCTION_ORDER_GLOBAL_CREDENTIAL_COMPANIES`; verifikasi ulang untuk company lain |
| §27.2 | Field detail berbeda dari hipotesis (`productionOrderDetails`, `ID`, `productionOrderQty`, `flagFinished`, `productionOrderMaterials.totalQty`); hasil kosong `data: null` | Diverifikasi dengan response nyata | Normalizer, fixture, dan test diperbarui |
| §28.4 (K9), 27 Okt | Branch non-Kitchen sempat ikut disinkronkan | Keputusan user 27 Oktober | **Dibatalkan v2.1 (28 Oktober)** — lihat §8 |
| §37.2 UI | Verifikasi browser desktop/mobile belum dijalankan | Membutuhkan data lokal dan perubahan `.env` (toggle) yang tidak dilakukan tanpa persetujuan | Jalankan saat UAT staging (rollout §8) |
| Data model | `data-model.md` tidak dihasilkan ulang dengan generator | Generator tidak tersimpan di repository; tiga baris ditambahkan dari refleksi relasi dengan format sama | Simpan generator di `ops/` bila ingin regenerasi penuh |

## 5. Test dan verifikasi

| Perintah | Hasil |
|---|---|
| `php artisan test --compact tests/Feature/EsbProductionOrderServiceTest.php` | 29 lulus |
| `php artisan test --compact tests/Feature/KitchenProductionOrderSyncTest.php` | 28 lulus (termasuk allowlist BLSS) |
| `php artisan test --compact tests/Feature/KitchenProductionOrderPageTest.php` | 22 lulus (termasuk state company belum diaktifkan) |
| Kitchen existing (Page, Workflow, BOM eligibility, Schema) | 35 lulus |
| Regression set gabungan: test baru + Kitchen + BranchEsbCode, EsbBranchMapping, EsbBranchSync, EsbCoreClient, RndBomCatalog, SyncBomCatalogAction, RolePermissionsMatrix | 152 lulus, 595 assertions |
| `tests/Feature/Monitoring`, ItRequestWorkflow, WhatsappCta (scheduler/sidebar) | 125 lulus |
| `vendor/bin/pint --dirty --format agent` | Lulus (perbaikan import/format pada dua test) |
| `npm run build` | Lulus |
| `bash ops/ci/check-doc-links.sh`, `bash ops/ci/check-code-map.sh`, `git diff --check` | Lulus (67 dokumen; 175 pola Peta kode) |
| Full Feature suite, `SHARD_INDEX=1..4 SHARD_TOTAL=4 bash ops/ci/feature-shard.sh` (kode final v3.2, 28 Oktober 2026) | Keempat shard lulus: 1.957 test, 9.912 assertions, 0 gagal |

Seluruh request HTTP pada test memakai `Http::fake()`; test halaman memakai `Http::preventStrayRequests()` dan membuktikan render serta modal tidak memanggil ESB.

**Verifikasi lokal dengan ESB nyata (27 Oktober 2026, read-only, atas izin user):**

| Langkah | Hasil |
|---|---|
| Sync per-company BLSS | HTTP `403` — akun per-company tidak punya akses Production Order |
| `GET /branch` akun global vs per-company BLSS | Identik (11 Branch) → akun global = BLSS |
| Mapping lokal `KTJ` | Branch ID ESB usang (2 → 4); diperbaiki dengan **Sync Branch ESB** existing |
| Backfill 29 Jul–10 Nov, Branch 1 (non-Kitchen) | 82 order, 5.612 detail, 0 detail gagal; window kosong awalnya gagal karena `data: null` → diperbaiki |
| Incremental ulang | 21 order, seluruhnya `unchanged` — idempotent, tanpa request detail |

Belum dijalankan: verifikasi visual browser oleh user, CI Pull Request, UAT staging, rekonsiliasi angka dengan layar ESB, dan rollout production.

## 6. Migration dan konfigurasi

- Migration additive: `2026_10_27_204800_create_esb_production_orders_table`, `2026_10_27_204802_create_esb_production_order_details_table`, `2026_10_27_204803_create_esb_production_order_sync_runs_table`. Tidak memanggil ESB dan tidak backfill.
- Environment baru (opsional, default nonaktif): `KITCHEN_PRODUCTION_ORDERS_ENABLED`, `KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED`, `KITCHEN_PRODUCTION_ORDER_QUEUE`, serta `KITCHEN_PRODUCTION_ORDER_COMPANIES` (default `BLSS`).
- Permission: tidak ada permission baru (K7).
- Queue/scheduler: lihat [rollout §4](../rollout.md#4-queue-dan-scheduler).

## 7. Risiko dan pekerjaan lanjutan

- Kontrak terverifikasi hanya dari sampel BLSS Branch `KTJ`; status lain selain `Authorized` dan `Result Sign Off` belum terlihat.
- Akun global dipakai untuk BLSS; perubahan hak akun ESB dapat memutus sync (terlihat sebagai run `failed`, kelas authentication).
- Volume material besar (±5.600 detail untuk 82 order): pantau ukuran tabel detail setelah backfill production.
- Label status ESB ditampilkan apa adanya; bila ESB tidak mengirim `statusName`, UI menampilkan `Status {id}`.
- Waktu `esb_*_at` disimpan sesuai string ESB tanpa konversi timezone; periksa saat rekonsiliasi.
- Tampilan last-success/failure di System Monitoring belum ditambahkan (PRD §35, setelah pipeline stabil).
- Setelah rollout production sehat: ubah status amendment PRD menjadi `implemented` dan hapus `implementation-prompt.md`.

## 8. Remediasi PRD v2.1 (28 Oktober 2026)

Audit kode terhadap PRD v2.1 (K9, K13–K15) menemukan dan menutup gap berikut:

| Gap | Perbaikan | Bukti test |
|---|---|---|
| User all-branch/Superadmin masih dapat memilih dan melihat Branch non-Kitchen (rencana lokal dan ESB) | `scopeVisibleTo` kedua model, `filterBranches`, `schedulableBranches`, kedua Policy, `ProductionScheduleValidator`, detail builder, `scheduleOrFail`, dan `syncSummary` selalu memakai scope Brand `Kitchen`. Karena `Gate::before` melewati Policy untuk Superadmin, batasan juga ditegakkan di query dan validator | `KitchenProductionSchedulePageTest` (all-branch, Superadmin), `KitchenProductionScheduleWorkflowTest`, `KitchenProductionOrderPageTest` (dataset all-branch/view-all/superadmin) |
| Planner menerima Branch Brand apa pun dan mapping apa pun | Planner kembali Kitchen-only (juga untuk `--branch`/`--mapping`) dan hanya menerima pasangan canonical `KITCHEN_PRODUCTION_ORDER_MAPPINGS` (default `BLSS:KTJ,BLO13:HOF,BLHJ:TCHL`) | `KitchenProductionOrderSyncTest` (tiga mapping canonical, non-canonical, non-Kitchen) |
| `BLHJ` tidak dikenal konfigurasi ESB | Ditambahkan ke `config('esb.core.companies')` dengan `ESB_CORE_BLHJ_USERNAME/PASSWORD` (`.env.example` tanpa nilai) | `EsbCoreClientTest`, test tiga mapping |
| Label filter | `Semua Kitchen` | `KitchenProductionSchedulePageTest` |

Dua test lama yang mengunci perilaku "all-branch boleh memakai Branch non-Kitchen" diubah ekspektasinya (tidak dihapus) karena bertentangan dengan PRD §28.6. PRD §7.12 dan §10.1 diberi catatan bahwa bagian lama digantikan v2.1.

Dampak data: snapshot dan jadwal lama pada Branch non-Kitchen tidak dihapus, tetapi tidak lagi tampil. Di lokal, 82 snapshot hasil uji Branch 1 (Brand Patisserie) kini tersembunyi; data akan muncul kembali setelah Branch Kitchen lokal dengan mapping canonical tersedia dan sync dijalankan ulang.

## 9. Amendment v3.0 — rencana lokal dihapus (28 Oktober 2026)

Keputusan user (PRD §38, K16–K19): hapus total rencana lokal termasuk tabel, hapus toggle layer, nama menu tetap.

| Area | Perubahan |
|---|---|
| Kode dihapus | `SaveProductionScheduleAction`, `CancelProductionScheduleAction`, `ProductionScheduleItemWriter`, `ProductionScheduleValidator`, `ProductionBomEligibilityService`, `ProductionScheduleCalendarQuery`, `ProductionScheduleDetailBuilder`, `KitchenProductionSchedulePolicy`, `KitchenProductionScheduleStatus`, model dan factory `KitchenProductionSchedule`/`Item`; `visibleRange()` dan `filterBranches()` pindah ke `ProductionOrderCalendarQuery` |
| Halaman | `KitchenProductionSchedulePage` dan Blade ditulis ulang menjadi kalender Production Order read-only: filter Branch Kitchen dan Status, banner sync, grid/agenda, modal detail. Tidak ada filter Sumber, editor, picker, atau aksi |
| Schema | Migration `2026_10_28_085835_drop_kitchen_production_schedule_tables` (destruktif; backup wajib, rollout §6). Migration lama tidak diubah |
| Permission | `create/update/cancel kitchen production schedules` dihapus dari `config/permissions.php` dan masuk `PermissionSynchronizer::DEPRECATED_PERMISSIONS` |
| Konfigurasi | `kitchen.production_orders.enabled` / `KITCHEN_PRODUCTION_ORDERS_ENABLED` dihapus |
| Test | Empat file test rencana lokal (`KitchenProductionBomEligibilityTest`, `KitchenProductionScheduleSchemaTest`, `KitchenProductionScheduleWorkflowTest`, `KitchenProductionSchedulePageTest`) dihapus bersama fiturnya atas persetujuan user; test akses halaman, sidebar, rentang kalender, dan scope Kitchen dipindahkan ke `KitchenProductionOrderPageTest`, ditambah test tanpa aksi mutasi, tabel ter-drop, dan permission usang |

Data lokal: migration drop menghapus 1 jadwal dan 1 item uji di database lokal. Production belum disentuh.

## 10. Amendment v3.1 — mapping dari Master Branch (28 Oktober 2026)

Keputusan user (PRD K20): Branch Kitchen baru cukup ditambahkan di Master Branch tanpa mengubah kode atau konfigurasi.

| Area | Perubahan |
|---|---|
| Konfigurasi | `kitchen.production_orders.company_codes` (`KITCHEN_PRODUCTION_ORDER_COMPANIES`) dan `canonical_mappings` (`KITCHEN_PRODUCTION_ORDER_MAPPINGS`) dihapus |
| Planner | Setiap mapping aktif dan lengkap pada Branch Kitchen aktif disinkronkan (K8); alasan skip tersisa: Branch tidak aktif/bukan Kitchen, mapping tidak aktif, Company Code kosong/belum terdaftar di `config/esb.php`, Branch ID ESB kosong |
| Kalender | State "belum diaktifkan" dihapus; mapping dengan company yang belum terdaftar tampil sebagai "mapping belum lengkap" |
| Test | Test allowlist/canonical diganti test K20 (tiga company sekaligus, Branch Kitchen baru tanpa konfigurasi, company belum terdaftar, Branch keluar dari Brand Kitchen) |

Batasan: company ESB yang benar-benar baru tetap perlu satu entri di `config/esb.php` (`esb.core.companies`) agar credential environment-nya dikenali `EsbCoreClient` — client bersama semua modul ESB, tidak diubah pada fitur ini. Credential per-company untuk modul Production juga perlu diverifikasi per company (BLSS per-company mendapat `403`).

## 11. Amendment v3.2 — tombol Sync Data (28 Oktober 2026)

Keputusan user (PRD §39): tombol sync manual di kalender dengan default usulan — permission baru `sync kitchen production orders` (K21) dan cooldown 5 menit per mapping (K22).

| Area | Perubahan |
|---|---|
| Permission | `sync kitchen production orders` ditambahkan ke `config/permissions.php` (Kitchen → Jadwal Produksi); `EsbProductionOrderPolicy::sync` |
| Action | `RequestProductionOrderSyncAction`: authorize, validasi ulang Branch terhadap Branch Kitchen user, cooldown per mapping, lalu planner yang sama dengan scheduler (incremental) |
| Halaman | Tombol di toolbar, notifikasi hasil, `wire:poll.5s` selama ada run aktif; status "sedang berjalan" kini didahulukan di banner |
| Test | Tombol tersembunyi tanpa permission + direct call 403; antre per mapping Kitchen; filter Branch; Branch di luar scope 403; Superadmin tetap Kitchen-only; cooldown dan run aktif; tanpa mapping lengkap |

Deploy: berikan permission ke role terkait melalui Role & Permission setelah `permissions:sync`.
