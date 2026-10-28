---
document_id: KITCHEN-PRODUCTION-SCHEDULE-ROLLOUT
title: Rollout Runbook — Production Order ESB pada Jadwal Produksi
status: draft
version: 1.1
owner: Kitchen
technical_owner: Information Technology
created_at: 2026-10-27
updated_at: 2026-10-28
target_release: TBD
related_documents:
  - docs/products/kitchen/production-schedule/prd.md
  - docs/products/kitchen/production-schedule/implementation-plan.md
  - docs/operations/ci-cd/release-pipeline.md
  - docs/architecture/integrations/esb.md
supersedes: null
---

# Rollout Runbook — Production Order ESB pada Jadwal Produksi

> Jangan menulis credential, token, atau isi `.env`. Sebutkan nama variabel environment saja.

Acuan requirement: [PRD §25–§37](prd.md#amendment-v20--production-order-esb-sebagai-overlay-read-only). Seluruh langkah di bawah adalah rencana; setiap langkah di shared environment (staging/production) dijalankan oleh IT dengan persetujuan terpisah.

## 1. Prerequisite

- [ ] PR fitur sudah di-merge ke `main` dan CI hijau.
- [x] Kontrak response endpoint Production Order terverifikasi terhadap BLSS pada 27 Oktober 2026 (PRD §27.2).
- [ ] Credential global ESB Core (`ESB_CORE_USERNAME`/`ESB_CORE_PASSWORD`) tersedia di environment tujuan. `BLSS` dibaca dengan akun global karena akun per-company BLSS mendapat `403` (PRD §27.1). `BLO13` dan `BLHJ` menggunakan credential company kecuali verifikasi terpisah membuktikan perlu global credential.
- [ ] Credential environment company `BLO13` dan `BLHJ` tersedia. Pastikan implementasi telah menambahkan `BLHJ` ke `config('esb.core.companies')` tanpa menyimpan credential di repository.
- [ ] Jalankan **Master → Branch → Sync Branch ESB** lebih dulu untuk memperoleh ID numerik terbaru. Mapping Branch Kitchen saat ini (sumber: Master Branch, K20): Kitchen Jateng=`BLSS/KTJ`, Kitchen Surabaya=`BLO13/HOF`, Kitchen Jakarta=`BLHJ/TCHL`. Mapping lokal terbukti dapat menyimpan Branch ID ESB usang (`KTJ` pernah tersimpan 2, ESB 4), yang membuat sync mengembalikan 0 order.
- [ ] Ketiga Branch lokal aktif dan terhubung ke Brand `Kitchen`; tidak ada mapping non-Kitchen yang ikut preview command.
- [ ] Queue worker dan scheduler staging/production sehat di System Monitoring → Queue & Scheduler.

## 2. Environment dan configuration

| Variabel | Wajib | Default | Keterangan |
|---|---|---|---|
| `KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED` | Tidak | `false` | Mengizinkan scheduler menjalankan sync incremental per jam. Command manual tidak terpengaruh. |
| `KITCHEN_PRODUCTION_ORDER_GLOBAL_CREDENTIAL_COMPANIES` | Tidak | `BLSS` | Company yang dibaca dengan credential global `ESB_CORE_USERNAME`. Tambahkan company hanya setelah daftar `GET /branch` akun global terbukti sama dengan company tersebut. |
| `ESB_CORE_BLHJ_USERNAME`, `ESB_CORE_BLHJ_PASSWORD` | Ya untuk Kitchen Jakarta | kosong | Credential company `BLHJ`, dibaca dari environment oleh `config('esb.core.companies.BLHJ')`. |
| `KITCHEN_PRODUCTION_ORDER_QUEUE` | Tidak | kosong (queue default) | Queue khusus bila ingin memisahkan beban. Worker harus mendengarkan queue tersebut. |

`KITCHEN_PRODUCTION_ORDERS_ENABLED` (v3.0, K18), `KITCHEN_PRODUCTION_ORDER_COMPANIES`, dan `KITCHEN_PRODUCTION_ORDER_MAPPINGS` (v3.1, K20) tidak dipakai lagi: kalender selalu tampil dan mapping diambil dari Master Branch; hapus variabel tersebut dari `.env` bila sempat diisi. Batas lain (window, halaman, ambang stale, retensi run) ada di `config/kitchen.php` dan tidak memerlukan variabel environment. Setelah mengubah `.env`, jalankan `php artisan config:cache` dan `php artisan queue:restart` melalui prosedur release existing.

## 3. Migration

Tiga migration additive (tanpa pemanggilan ESB atau backfill) dan satu migration **destruktif**:

- `2026_10_27_204800_create_esb_production_orders_table`
- `2026_10_27_204802_create_esb_production_order_details_table`
- `2026_10_27_204803_create_esb_production_order_sync_runs_table`
- `2026_10_28_085835_drop_kitchen_production_schedule_tables` — men-drop `kitchen_production_schedule_items`, `kitchen_production_schedule_transitions` (bila ada), dan `kitchen_production_schedules` (PRD §38, K17). **Backup wajib dahulu (§6).** `down()` hanya membuat ulang struktur kosong.

Tabel baru kosong saat deploy, sehingga tidak ada lock pada tabel besar. Migration berjalan lewat `php artisan migrate --force` pada pipeline release existing; `permissions:sync` menghapus permission usang `create/update/cancel kitchen production schedules`.

## 4. Queue dan scheduler

| Komponen | Keterangan |
|---|---|
| `kitchen:sync-production-orders` | Incremental hari ini −7 s.d. +14 hari; `--dry-run`, `--branch=`, `--mapping=` |
| Tombol **Sync Data** | Mengantrekan `SyncEsbProductionOrdersJob` incremental untuk Branch Kitchen dalam scope user; cooldown 5 menit per mapping; permission `sync kitchen production orders` |
| `kitchen:backfill-production-orders` | Backfill eksplisit `--from`/`--to`; dry-run default, `--execute` untuk dispatch, `--force` tanpa konfirmasi, `--allow-long-range` untuk > 120 hari |
| `SyncEsbProductionOrdersJob` | Satu run (mapping + window); timeout 80 detik, 3 percobaan, backoff 60/300/900 detik |
| `SyncEsbProductionOrderDetailJob` | Satu order; timeout 80 detik, 3 percobaan, backoff 60/300/900 detik |
| Scheduler | `kitchen:sync-production-orders` setiap jam, hanya bila `KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED=true`; `model:prune` riwayat run pukul 03:40 |

## 5. External integration

- Endpoint: `GET /production/production-order` dan `GET /production/production-order/{productionOrderNum}` relatif terhadap `ESB_CORE_BASE_URL`.
- Read-only; tidak ada request mutation.
- Staging memakai Company Code dan Branch yang disepakati untuk UAT. Mulai dari satu mapping dan rentang pendek agar beban ESB kecil.

## 6. Backup

**Wajib** sebelum release v3.0 di setiap environment bersama: backup tabel rencana lokal yang akan di-drop, lalu simpan file backup di lokasi backup database existing.

```bash
mysqldump --single-transaction --no-create-db {database} \
  kitchen_production_schedules kitchen_production_schedule_items kitchen_production_schedule_transitions \
  > kitchen-production-schedules-before-v3.sql
```

Hapus `kitchen_production_schedule_transitions` dari perintah bila tabel itu tidak ada. Catat jumlah baris setiap tabel di report sebelum deploy. Gunakan kredensial database dari prosedur server existing; jangan menulisnya di dokumen.

## 7. Deployment steps

1. Jalankan backup §6 dan catat jumlah baris.
2. Deploy release melalui pipeline atomic existing dengan `KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED=false`.
3. Setelah `permissions:sync`, berikan `sync kitchen production orders` ke role yang boleh menyinkronkan manual melalui **Management Access → Role & Permission** (default hanya `SUPERADMIN`).
4. Pastikan migration berjalan: `php artisan migrate:status --no-interaction` menampilkan empat migration di atas.
3. Staging — pratinjau: `php artisan kitchen:sync-production-orders --mapping={ID} --dry-run`. Periksa mapping yang dilewati beserta alasannya.
4. Staging — ulangi dry-run untuk seluruh mapping Branch Kitchen dan pastikan tidak ada Branch non-Kitchen. Jalankan sync manual satu mapping terlebih dahulu: `php artisan kitchen:sync-production-orders --mapping={ID}`.
5. Periksa run di database (`esb_production_order_sync_runs`): status `succeeded`/`partial`, counter, dan `error_summary`.
6. Rekonsiliasi (§9). Bila kontrak tidak cocok, hentikan dan perbaiki normalizer.
7. Staging — backfill K1: `php artisan kitchen:backfill-production-orders --from={hari ini −90} --to={hari ini +14} --mapping={ID}` (dry-run), lalu ulangi dengan `--execute`.
8. Staging — UAT desktop dan mobile (§8). Kalender selalu tampil; sebelum sync pertama kalender kosong dengan banner "belum pernah disinkronkan".
9. Production — ulangi langkah 3–6 untuk satu Branch dan rentang pendek.
10. Production — setelah satu mapping sehat, verifikasi manual `BLSS/KTJ`, `BLO13/HOF`, dan `BLHJ/TCHL`; kemudian set `KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED=true` dan pantau satu siklus penuh.

## 8. Smoke test

- [ ] `/up` hijau dan halaman **Kitchen → Jadwal Produksi** terbuka.
- [ ] Halaman tidak menampilkan tombol buat jadwal, editor, atau aksi apa pun (v3.0).
- [ ] Chip `ESB` tampil pada tanggal produksi; klik membuka detail read-only dengan detail dan material.
- [ ] Filter Branch Kitchen dan Status bekerja.
- [ ] Tombol **Sync Data** hanya tampil bagi role yang diberi `sync kitchen production orders`; klik menampilkan "Sinkronisasi … cabang diantrekan" lalu banner "sedang berjalan" hingga selesai.
- [ ] Brand hanya `Kitchen`; user all-branch hanya melihat Kitchen Jakarta, Kitchen Jateng, dan Kitchen Surabaya, bukan Branch Brand lain.
- [ ] Banner status sinkronisasi sesuai keadaan (belum pernah, berjalan, sukses, stale, partial, gagal).
- [ ] Mobile 360/390 px: agenda tanpa scroll horizontal; modal dapat di-scroll dan footer tidak menutup baris terakhir.
- [ ] User Branch A tidak melihat order Branch B.

## 9. Data verification

Bandingkan setiap mapping Branch Kitchen (saat ini `BLSS/KTJ`, `BLO13/HOF`, `BLHJ/TCHL`) dan beberapa tanggal/status dengan layar Production Order ESB:

- jumlah header per tanggal;
- nomor order, tanggal produksi, status;
- jumlah detail, product/BOM, quantity, dan UOM;
- material per detail.

Catat hasil sebagai angka dan nomor order fiktif/ringkas di report; jangan menyalin data pribadi atau payload ke dokumen.

## 10. Monitoring

- **System Monitoring → Queue & Scheduler**: riwayat `kitchen:sync-production-orders`, failed job `SyncEsbProductionOrders*`.
- **Log**: pesan `Kitchen Production Order ...` dengan `sync_run_id`, `mapping_id`, `company_code`, `error_class`.
- **Run**: tidak ada run `failed` berulang untuk mapping yang sama; `list_completed_at` terbaru < 3 jam untuk setiap mapping aktif.
- Memory worker dan disk tetap normal setelah backfill.

## 11. Rollback trigger

- Error authentication/validation berulang untuk semua mapping setelah perbaikan konfigurasi.
- Response ESB terbukti tidak sesuai kontrak sehingga data salah tampil.
- Beban ESB atau queue mengganggu proses lain.
- Health check release gagal.

## 12. Rollback steps

1. Menyembunyikan halaman tanpa rollback: cabut `view kitchen production schedules` dari role terkait melalui menu Role & Permission.
2. Sync: set `KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED=false` — scheduler berhenti mendispatch; queue global tidak dihentikan. Job yang sedang berjalan aman diselesaikan karena idempotent.
3. Rollback ke release sebelum v3.0 (atomic release rollback existing) **wajib** diikuti `php artisan migrate:rollback --step=1` untuk membuat ulang struktur tabel rencana lokal, lalu pulihkan backup §6 (`mysql {database} < kitchen-production-schedules-before-v3.sql`, setelah menghapus tabel kosong hasil rollback). Tanpa langkah ini halaman lama error. Permission lama dibuat kembali oleh `permissions:sync` release lama, tetapi assignment role perlu diberikan ulang.
4. Jangan drop tabel snapshot atau menghapus snapshot Production Order.
5. Verifikasi: `/up` hijau, worker mengarah ke release aktif, halaman Jadwal Produksi terbuka, dan scheduler tidak mendispatch job Production Order bila dinonaktifkan.

## 13. Post-rollout observation

Pantau minimal 24 jam (satu siklus penuh scheduler) setelah scheduler aktif: status run per mapping, failed job, latency ESB, memory worker, dan umpan balik tim Kitchen.

## 14. Sign-off

| Peran | Nama | Tanggal | Hasil |
|---|---|---|---|
| Owner bisnis | | | |
| Technical owner | | | |
