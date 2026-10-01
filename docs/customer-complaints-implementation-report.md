# Laporan Implementasi — Customer Complaints

PRD: `docs/customer-complaints-prd.md` (baseline 1 Oktober 2026). Implementasi dikerjakan 1 Oktober 2026, enam phase berurutan, satu commit per phase di branch `main`.

## 1. Status per phase

| Phase | Status | Commit |
| --- | --- | --- |
| 0 — Audit dan contract freeze | Selesai (audit dilakukan via subagent eksplorasi + verifikasi manual sebelum kode ditulis; temuan dipakai langsung sebagai dasar desain, tidak ada laporan audit terpisah) | — |
| 1 — Fondasi domain | Selesai, test lulus | `5cee03a` |
| 2 — Form aplikasi user | Selesai, test lulus | `338ca20` |
| 3 — Back office | Selesai, test lulus | `b0b4bac` |
| 4 — Notifikasi dan attachment hardening | Selesai, test lulus | `4554406` |
| 5 — UI review dan release hardening | Selesai sebagian (lihat §9 Risiko) | `1c73436` |

Tidak ada phase yang ditandai selesai sebelum test terfokusnya lulus.

## 2. Ringkasan hasil akhir

- Tile **Form Komplain** tersedia di launcher aplikasi Casual, tampil hanya untuk user dengan permission `create customer complaints`, bersifat opsional (tidak ada indikator "belum diisi", tidak memengaruhi scoring kepatuhan).
- Form mengumpulkan Branch (default branch utama user, divalidasi ulang di server), Tanggal Kejadian, Sumber dan Kategori Komplain, field customer opsional, Detail Komplain, dan hingga 5 attachment (jpg/jpeg/png/webp/pdf, maks. 5 MB/file) ke disk privat `b2`.
- Nomor komplain `CMP-YYYYMMDD-NNNN` dibuat atomik di bawah row lock, dengan retry pada collision unique index sebagai pengaman kedua.
- Back office `Operational → Customer Complaints` menyediakan index (search, filter Branch/Category/Source/Status/rentang tanggal, pagination 10/20) dan halaman detail dengan aksi **Tindak Lanjut** (Status, PIC, Internal Notes, Resolution) serta Activity Timeline.
- Transisi status mengikuti `New → In Review → Resolved → Closed`; `Resolved`/`Closed` wajib mempunyai resolution.
- Notifikasi database: Operational reviewer yang berwenang pada branch terkait diberi tahu saat komplain baru masuk; pelapor diberi tahu saat status menjadi `Resolved`/`Closed`.
- Attachment hanya dapat dibuka melalui route terautorisasi (`CustomerComplaintAttachmentController`), bukan temporary URL mentah; file tidak dihapus saat soft delete; file yang sudah terupload dibersihkan jika Action menolak submission.
- 36 test baru khusus fitur ini + seluruh regresi existing (1099 Feature + 109 Unit) lulus.

## 3. Migration yang dibuat

Seluruhnya baru dan aditif; tidak ada migration production existing yang diubah.

1. `2026_10_01_133421_create_customer_complaints_table.php`
2. `2026_10_01_133422_create_customer_complaint_activities_table.php`
3. `2026_10_01_133423_grant_customer_complaints_permissions.php` — memberi permission ke role existing pada database production yang sudah berjalan (pola yang sama dengan `2026_09_21_082905_grant_technician_monthly_maintenance_permissions.php`).
4. `2026_10_01_134708_add_internal_notes_to_customer_complaints_table.php` — kolom `internal_notes` ditambahkan terpisah karena §14.1 PRD secara eksplisit hanya mendaftarkan kolom "minimum" dan tidak menyebutkan kolom ini, padahal §10.3 mensyaratkannya sebagai field Follow-up.

## 4. Perubahan schema

### `customer_complaints`
`id`, `complaint_number` (unique), `branch_id` (FK, restrict delete), `occurred_at`, `source`, `category`, `order_reference` (nullable, indexed), `customer_name`, `customer_contact`, `description`, `attachment_paths` (JSON nullable), `status` (indexed, default `new`), `assigned_to` (FK users, nullable), `internal_notes` (nullable, migration terpisah), `resolution` (nullable), `resolved_at`/`resolved_by`, `closed_at`/`closed_by`, `submitted_by` (FK users, restrict delete), timestamps, soft deletes. Index tambahan: `occurred_at`, `status`, `assigned_to`, `submitted_by`, komposit `(branch_id, status)`.

### `customer_complaint_activities`
`id`, `customer_complaint_id` (FK, cascade delete, indexed), `activity_type`, `previous_status`/`new_status` (nullable), `notes` (nullable), `metadata` (JSON nullable), `created_by` (FK users, nullable), timestamps. Append-only melalui use case normal (dibuat bersamaan dengan perubahan record utama, dalam transaction yang sama).

## 5. Permission dan role

| Permission | Group config | Role awal |
| --- | --- | --- |
| `create customer complaints` | `Akses Employee App` (bukan `Operational`) — satu-satunya permission yang menggerbangi tile, submit form, **dan** akses panel Casual sekaligus, sesuai permintaan eksplisit PRD §12 | `STORE_STAFF`, `SUPERVISOR_STORE` |
| `view any customer complaints` | `Operational` | `SUPERVISOR_STORE` |
| `view customer complaints` | `Operational` | `SUPERVISOR_STORE` |
| `update customer complaints` | `Operational` | `SUPERVISOR_STORE` |
| `delete customer complaints` | `Operational` | hanya `SUPERADMIN` (otomatis, tidak ada role lain) |

**Keputusan/asumsi yang belum dapat divalidasi oleh pengguna** (dicatat sesuai permintaan Phase 0): aplikasi ini belum mempunyai role "Operational Reviewer"/"Operational Manager" sebagaimana disebut di §6 PRD. `SUPERVISOR_STORE` dipakai sebagai padanan terdekat untuk penerima permission review back office dan notifikasi komplain baru. Jika ada role lain yang dimaksud, pemetaan ini perlu disesuaikan melalui halaman Role & Permission admin (permission sudah terdaftar di sana, tidak perlu migration baru untuk mengubah pemetaan role).

## 6. Attachment lifecycle

1. **Upload**: Livewire `WithFileUploads` di `CustomerComplaintPage`, validasi `mimes:jpg,jpeg,png,webp,pdf`, maks. 5 file, 5 MB/file, disimpan ke disk privat `b2` di bawah `customer-complaints/{branchId}/`.
2. **Registrasi**: path disimpan sebagai array JSON pada `customer_complaints.attachment_paths`, bukan tabel attachment terpisah (mengikuti pola `ErpRepairRequest`).
3. **Orphan cleanup**: jika `CreateCustomerComplaintAction` menolak submission setelah file terupload (mis. validasi branch gagal), file yang baru diupload langsung dihapus dari `b2`.
4. **Preview/download**: hanya melalui `GET /customer-complaint-attachments/{path}` (`CustomerComplaintAttachmentController`) — path yang tidak terdaftar pada komplain manapun langsung 404 sebelum pengecekan otorisasi berjalan; pengecekan otorisasi memakai `CustomerComplaintPolicy::view()` yang sama dengan halaman detail.
5. **Soft delete**: tidak memicu penghapusan file apa pun; file tetap berada di `b2` untuk kemungkinan rollback. Pembersihan permanen sengaja belum diimplementasikan (§15 — menunggu kebijakan retensi terpisah).

## 7. Status notifikasi

- Dikirim lewat `Notification::send()` **setelah** closure `DB::transaction()` selesai (bukan di dalam event `afterCommit()` — tidak ada koneksi queue di aplikasi ini yang mengaktifkan `after_commit`, jadi "setelah commit" di sini bersifat struktural, mengikuti pola `CreateProjectTaskAction` yang sudah ada).
- `CustomerComplaintSubmittedNotification` → setiap user aktif yang memegang `view customer complaints` **dan** dapat mengakses branch komplain tersebut (`User::canAccessBranch()`).
- `CustomerComplaintResolvedNotification` → pelapor, hanya pada transisi masuk ke `Resolved`/`Closed` (bukan pada edit berikutnya saat status sudah di salah satu status tersebut).
- Keduanya database-only (`ShouldQueue`, channel `database`), sesuai scope versi pertama (§16) — tidak ada WhatsApp/email otomatis.

## 8. Hasil test dan assertion

| Suite | Test | Assertion | Hasil |
| --- | --- | --- | --- |
| `CustomerComplaintPermissionTest.php` | 5 | — | lulus |
| `CustomerComplaintDomainTest.php` | 4 | — | lulus |
| `CustomerComplaintSubmissionTest.php` | 12 | — | lulus |
| `CustomerComplaintBackOfficeTest.php` | 10 | — | lulus |
| `CustomerComplaintAttachmentTest.php` | 5 | — | lulus |
| **Subtotal fitur ini** | **36** | **169** | **lulus** |
| Full suite (`tests/Feature`) | 1099 | 5695 | lulus |
| Full suite (`tests/Unit`) | 109 | 323 | lulus |

`vendor/bin/pint --dirty --format agent` bersih pada setiap phase (tidak ada pelanggaran style yang tersisa).

## 9. Kegagalan test dan klasifikasi

Tidak ada kegagalan tersisa. Dua isu transien ditemukan dan diperbaiki **selama** pengerjaan (bukan kegagalan yang dilaporkan sebagai akhir):

- **False positive N+1** pada test query-count back office: delta query ternyata berasal dari cache permission Spatie yang belum "panas" pada pengukuran pertama, bukan dari row count. Diperbaiki dengan melakukan satu render pemanasan sebelum kedua pengukuran dibandingkan — klasifikasi: **flaky test, root cause di test itu sendiri, bukan di kode produksi**.
- **TypeError tersembunyi sebagai 404** pada `CustomerComplaintAttachmentController`: type-hint return `Illuminate\Http\Response` terlalu sempit untuk `StreamedResponse` yang dikembalikan `Storage::disk()->response()`, dan `catch (Throwable)` yang terlalu luas menelan `TypeError` tersebut. Diperbaiki dengan mengganti ke `Symfony\Component\HttpFoundation\Response` (tipe yang sama dipakai `RndProjectTaskAttachmentController`) — klasifikasi: **bug produksi riil, ditemukan dan diperbaiki sebelum commit Phase 4**.

## 10. Risiko yang tersisa

- **Pemetaan role "Operational Reviewer/Manager"**: lihat §5 — `SUPERVISOR_STORE` adalah asumsi, bukan konfirmasi eksplisit dari pengguna.
- **Review visual browser belum dilakukan**: viewport 360/390/768/1024/1440 px dan zoom 200% belum diverifikasi di browser sungguhan (tidak ada tool automasi browser pada sesi ini). Tinjauan dilakukan secara struktural (kelas Tailwind, breakpoint, flex-col mobile-first) mengikuti pola `ErpRequestPage` yang sudah terbukti berjalan di viewport yang sama.
- **Ikon pada halaman Form Komplain tetap memakai inline SVG path manual**, bukan komponen Heroicon Blade, karena mengikuti pola existing seluruh mini-app Casual panel (`ErpRequestPage`, `LauncherPage`) yang memang dibangun seperti itu — ini sejalan dengan arahan `docs/ui-consistency-prd.md` sendiri untuk tidak mengganti massal sistem ikon launcher/sidebar existing.
- **Attachment permanen tidak pernah dibersihkan** (soft delete mempertahankan file tanpa batas waktu) — sesuai §15, menunggu kebijakan retensi terpisah di masa depan; bukan bug, tapi perlu disadari sebagai pekerjaan rumah operasional.
- **Database `help_bloomery` lokal belum dimigrasikan** — lihat §11, migration belum dijalankan terhadap database real manapun selama sesi ini.

## 11. Langkah deployment

1. `git pull` commit yang sudah diuji (lima commit Phase 0–5 di atas; belum di-push ke remote mana pun — push hanya dilakukan atas instruksi eksplisit terpisah).
2. Backup database.
3. `php artisan migrate --force` — menjalankan keempat migration baru di §3. Seluruhnya aditif dan aman untuk database yang sudah berjalan.
4. `php artisan optimize:clear` (membersihkan cache permission/config/view).
5. Tidak ada queue worker baru yang perlu di-restart (notifikasi `ShouldQueue` memakai konfigurasi queue existing).
6. Smoke test: tile Form Komplain tampil untuk `STORE_STAFF`/`SUPERVISOR_STORE`, submit komplain menghasilkan nomor `CMP-...`, attachment ter-upload dan dapat dibuka kembali, menu Customer Complaints tampil di sidebar Operational untuk `SUPERVISOR_STORE`/`SUPERADMIN`, transisi status dan notifikasi berjalan.

## 12. Langkah rollback

- Rollback kode dilakukan per commit (lima commit di atas independen secara fungsional per phase, meski saling bergantung urutannya).
- Migration bersifat backward-compatible (hanya menambah tabel/kolom/permission). Jika kode di-rollback, tabel `customer_complaints` dan `customer_complaint_activities` **dibiarkan ada** agar data komplain yang sudah masuk tidak hilang — jangan menjalankan `php artisan migrate:rollback` untuk migration-migration ini kecuali data memang akan dibuang dan sudah ada persetujuan eksplisit serta backup.
- Permission yang sudah diberikan ke `STORE_STAFF`/`SUPERVISOR_STORE` oleh migration §3 juga sengaja **tidak di-revert otomatis** (`down()` kosong, mengikuti pola migration permission existing) — jika perlu dicabut, lakukan manual melalui halaman Role & Permission.
