---
document_id: PURCHASING-EMPLOYEE-SOURCING-REQUEST-REPORT
title: Laporan Implementasi — Request Sourcing melalui Employee App
status: draft
version: 1.0
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/employee-sourcing-request/prd.md
  - docs/products/purchasing/employee-sourcing-request/rollout.md
supersedes: null
---

# Laporan Implementasi — Request Sourcing melalui Employee App

Acuan: `docs/products/purchasing/employee-sourcing-request/prd.md`

## 1. Status per phase

| Phase | Status | Catatan |
|---|---|---|
| 0 | Selesai | Keputusan dicatat pada PRD §29. Picker dipakai tanpa refactor; verifikasi produk ESB dipisah ke `ActiveEsbProductDetailResolver` |
| 1 | Selesai | Migration additive, model, factory, enum, Policy, dan tiga permission |
| 2 | Selesai | Submit, kirim ulang, batal, verifikasi produk, lampiran privat, dan notifikasi |
| 3 | Selesai | Mulai Proses, Minta Perbaikan, Tolak, dan Buat Sourcing dengan row lock serta link unik |
| 4 | Selesai | Tile, form, dan Riwayat Employee App; resource Back Office; badge **Request User** di Sourcing Bahan dan Review Sourcing |
| 5 | Selesai lokal | Regression, query count, UAT visual lokal, dan dokumentasi. UAT staging belum dijalankan |

## 2. Ringkasan perubahan

- **Data:** tabel `material_sourcing_requests` dan `material_sourcing_request_status_histories`, enum `MaterialSourcingRequestStatus`, model dengan guard append-only, dan relasi `RndProductEsbMaterial::sourcingRequest`.
- **Action** (`app/Actions/Purchasing/MaterialSourcingRequest/`): `Submit`, `Resubmit`, `Cancel`, `Claim`, `Decide` (revisi dan tolak), `Convert`, `Transition`, dan `ValidateMaterialSourcingRequestInput`.
- **Service:** `MaterialSourcingRequestAttachmentStore` dan `MaterialSourcingRequestNotifier` di `app/Services/Purchasing/`. `ActiveEsbProductDetailResolver` di `app/Services/Rnd/Material/` dipakai request dan `CreateStandaloneMaterialSourcingAction`.
- **Employee App:** `MaterialSourcingRequestPage` (buat dan perbaiki request) dan `MaterialSourcingRequestHistoryPage`, bottom navigation **Buat Request**/**Riwayat**, serta tile launcher.
- **Back Office:** `MaterialSourcingRequestResource` (tabel antrean dan detail section) dengan aksi Mulai Proses, Buat Sourcing, Minta Perbaikan, dan Tolak; menu dan badge di sidebar Purchasing.
- **Lampiran:** `MaterialSourcingRequestAttachmentController` men-stream file privat setelah otorisasi.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| Picker dipakai tanpa refactor | Mode tanpa stok sudah tidak membaca branch/location | PRD §29 |
| Lampiran sebagai metadata JSON pada request | Pola Request ERP cukup; tidak perlu tabel baru | PRD §14.1, §29 |
| Revisi, tolak, dan conversion hanya oleh PIC | Aturan §11.14 tanpa takeover pada v1 | PRD §29 |
| Visibilitas tombol Back Office memanggil Policy langsung | `Gate::before` membuat SUPERADMIN melihat tombol yang tidak diizinkan status | Policy dan `ViewMaterialSourcingRequest::allows()` |
| Kirim ulang melepas PIC | Request kembali ke `submitted` dan perlu Mulai Proses lagi sesuai lifecycle §13 | PRD §13 |
| Tidak ada grant role di seeder | Larangan prompt implementasi; grant lewat Role & Permission | PRD §26 butir 1 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §17.5 filter Semua Status | Tidak ada opsi khusus "Semua Status"; filter status multi-pilih berisi status actionable secara default dan dikosongkan untuk melihat semua | Pola filter Filament existing | Tidak ada |
| §17.3 Escape pada picker | Sheet picker existing ditutup lewat tombol X atau backdrop; tombol Escape tidak ditangani | Partial dipakai ulang tanpa perubahan agar Receiving dan Item Journal tidak berubah | Perbaikan aksesibilitas picker bersama di PR terpisah |
| §19 audit lampiran | Upload dan unduh lampiran tidak dicatat sebagai event audit | Belum ada mekanisme audit lampiran existing; jumlah lampiran tercatat di metadata history | Tidak ada pada v1 |
| §19 conversion started | Hanya conversion selesai dan gagal yang dicatat di log | Transaksi conversion atomik, sehingga "started" tanpa hasil tidak bermakna | Tidak ada |
| Launcher recent request | Request Sourcing tidak ditambahkan ke daftar request terbaru di launcher | Opsional di implementation plan | Dapat ditambahkan bila diminta |

## 5. Test dan verifikasi

- `tests/Feature/MaterialSourcingRequestWorkflowTest.php` (27 test): submit, snapshot server, produk palsu atau tidak aktif, ESB timeout tanpa record parsial, validasi field dan lampiran, guard cabang, idempotency submission key, alur lengkap claim → revisi → kirim ulang → conversion, conversion ganda, supplier tidak valid, rollback saat produk tidak aktif, PIC, permission conversion, batal, kirim ulang, append-only, matrix otorisasi, dan invariant SUPERADMIN.
- `tests/Feature/MaterialSourcingRequestUiTest.php` (16 test): tile, akses halaman, picker tanpa stok, submit dari form, retensi input saat validasi gagal, guard cabang, riwayat tanpa supplier/harga, batal, perbaikan, akses request orang lain, unduh lampiran, antrean default dan query count, aksi Back Office per peran, SUPERADMIN, alasan revisi, dan badge **Request User**.
- Regression lulus (279 test): `MaterialSourcingTest`, `MaterialSourcingDurationTest`, `tests/Feature/MaterialWorkflow`, `NonPoReceipt*`, `QualityControlItemJournalTest`, `LauncherPageTest`, `RolePermissionsMatrixTest`, `CasualLoginRedirectTest`, dan dua suite baru.
- Pint, `check-doc-links`, `check-code-map`, dan `git diff --check` lulus.
- UAT visual lokal dengan data dummy yang sudah dihapus: halaman form, riwayat, antrean, dan detail tidak overflow pada 360, 390, 768, 1024, dan 1440 px serta lebar 720 px (setara zoom 200%). Dark mode diperiksa dan tidak ada error JavaScript. Picker memuat 20 produk ESB nyata (hanya GET) tanpa teks stok. Modal Buat Sourcing tidak menumpuk picker.
- Belum diuji: upload/unduh lampiran ke object storage sungguhan, conversion bersamaan dari dua proses pada MySQL, dan UAT staging.

## 6. Migration dan konfigurasi

- Migration additive `2026_11_03_154852_create_material_sourcing_requests_tables`; tidak ada backfill dan tidak mengubah tabel existing.
- Permission baru: `access employee app sourcing request`, `view material sourcing requests`, `process material sourcing requests`. Buat Sourcing juga memerlukan `submit material sourcing` existing. Semua diberikan lewat Role & Permission.
- Tidak ada env, queue, scheduler, atau dependency baru. Lampiran memakai disk privat `b2` existing.

## 7. Risiko dan pekerjaan lanjutan

- Conversion memegang row lock request selama pembacaan ulang produk ke ESB (timeout 20 detik). Hanya conversion untuk request yang sama yang menunggu.
- Batas upload PHP dan reverse proxy staging/production perlu diverifikasi minimal 12 MB.
- Siapa yang menerima tile dan permission Back Office di staging/production masih menunggu keputusan Purchasing dan Administrator (PRD §26 butir 1).
