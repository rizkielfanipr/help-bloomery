---
document_id: INVENTORY-ITEM-JOURNAL-REPORT
title: Laporan Implementasi — Item Journal Inventory
status: implemented
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-28
updated_at: 2026-10-28
target_release: TBD
related_documents:
  - docs/products/inventory/item-journal/prd.md
  - docs/products/inventory/item-journal/implementation-plan.md
supersedes: null
---

# Laporan Implementasi — Item Journal Inventory

Acuan: `docs/products/inventory/item-journal/prd.md` v1.1. Branch `feature/item-journal-inventory`, basis `main` `788a0dda`.

## 1. Status per phase

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 — Characterization | Selesai | PR `feature/item-journal-inventory` | Baseline 23 test lulus sebelum perubahan. Ditambah test retry dan hapus PDF historis, penolakan retry/hapus journal milik user lain, dan link System Monitoring ke detail |
| 1 — Navigasi dan RBAC | Selesai | idem | Tile **Item Journal**, permission `access employee app item journal`, permission aksi dipindah ke grup Inventory, bottom navigation QC tanpa Item Journal, Back Office di grup Inventory |
| 2 — Kamera dan validasi | Selesai | idem | **Ambil Foto** (`accept="image/*"`, `capture="environment"`), preview dengan **Ambil Ulang**/**Hapus**, state izin/kamera/upload, validasi image-only di server |
| 3 — Compatibility dan security | Selesai | idem | Test default role, scope `branch_id = null`, PDF historis, payload ESB, idempotency, monitoring |
| 4 — Dokumentasi dan delivery | Selesai | idem | README Inventory/QC, `panels.md`, `rbac.md`, PRD dan plan `implemented`; UAT perangkat masih menunggu staging |

## 2. Ringkasan perubahan

- **Employee App:** `LauncherPage` mendapat tile **Item Journal** (ikon pertukaran). Tile hanya tampil bila `ItemJournalPage::canAccess()` lolos, yaitu bila user memiliki `access employee app item journal` dan `view quality control item journals`. URL langsung tanpa keduanya menghasilkan 403. Header halaman kini berlabel Inventory dengan tombol kembali ke launcher, dan bottom navigation QC tidak lagi dirender.
- **Quality Control:** `components/quality-control/bottom-nav.blade.php` hanya berisi **Audit**.
- **Back Office:** `QualityControlItemJournalResource::$navigationGroup = 'Inventory'`, dan item sidebar dipindah ke grup Inventory (auto-open ikut Inventory). Class, route name, dan namespace tetap, sehingga `MonitoringLocalRecordLinker` dan deep link lama tetap valid. Resource tetap read-only.
- **Lampiran:** properti `photoUpload` menambahkan satu foto per pengambilan. `retakeUploads.{index}` mengganti foto pada posisi yang sama, dan `removeAttachment` menghapusnya. Aturan server: `file`, `mimes:jpg,jpeg,png,webp`, `mimetypes:image/jpeg,image/png,image/webp`, `dimensions:min_width=1,min_height=1`, `max:5120`, maksimal 5 file. Aturan yang sama dipakai ulang saat submit. Pesan error berbahasa Indonesia.
- **Permission:** `config/permissions.php` menambah `access employee app item journal` di Akses Employee App dan memindah lima permission aksi legacy ke Inventory → Item Journal. Grup config Quality Control dihapus; Audit QC dan Checklist QC tetap muncul dari `permissionDefinition()` resource.
- **Seeder (fresh install/test saja):** `STORE_STAFF` mendapat tile dan view/create/submit/delete attachment. `INVENTORY_STAFF` mendapat `view quality control item journals`. `QUALITY_CONTROL` mendapat tile agar akses Item Journal-nya tetap berjalan.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| Halaman mensyaratkan tile permission **dan** `viewAny`; tile hanya tampil bila keduanya ada | Tile tidak boleh mengarah ke halaman 403, dan permission tile tidak menggantikan policy | PRD §16.1–§16.3 |
| Branch scope view-all tidak diubah | `ItemJournalPage::submit()` selalu menyimpan `branch_id = null`, sehingga tidak ada branch lokal untuk dibatasi. Mengubahnya memerlukan perubahan data dan kontrak baru | PRD §16.5, rbac.md |
| `INVENTORY_STAFF` tidak mendapat view-all secara default | View-all membuka journal semua cabang; harus diputuskan per role lewat UI | Prompt §B.7, PRD §9 |
| `QUALITY_CONTROL` mendapat permission tile di seeder | Menjaga akses QC existing secara kompatibel pada instalasi baru/test | Prompt §B.6 |
| Rule `dimensions` ditambahkan pada validasi foto | Deteksi MIME Flysystem jatuh ke ekstensi bila isi file inconclusive (mis. teks bernama `.jpg`); `dimensions` membaca byte gambar sebenarnya di disk mana pun | PRD §16.6 |
| Deteksi kamera/izin memakai `navigator.mediaDevices.enumerateDevices()` dan `navigator.permissions.query({ name: 'camera' })` | Tanpa dependency baru; bila API tidak tersedia, pesan tidak ditampilkan dan input tetap berfungsi | PRD §17, §18 |
| Input tetap aktif pada perangkat tanpa kamera | Deteksi hanya petunjuk; lampiran opsional dan browser dapat menawarkan pemilih file gambar | PRD §17 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §17 "meminta izin" | State izin hanya berupa petunjuk ("Browser akan meminta izin kamera…") saat status izin `prompt`; dialog izin sendiri ditangani browser/OS | Input `capture` tidak memberi event izin ke halaman | Verifikasi copy pada UAT Android/iOS |
| §21.8 | Test role mencakup juga `QUALITY_CONTROL_SUPERVISOR` | Role itu memegang view-all secara default | — |

Tidak ada deviasi lain. Tidak ada migration, tidak ada perubahan endpoint/payload ESB, dan tidak ada perubahan permission string aksi.

## 5. Test dan verifikasi

- Baseline sebelum perubahan: `LauncherPageTest`, `QualityControlItemJournalTest`, `QualityControlItemJournalPolicyTest`, `QualityControlItemJournalBackOfficeTest`: 23 test lulus.
- Setelah perubahan, empat file yang sama: **55 test, 207 assertions, 0 gagal**.
- Regresi terkait (registry permission, matrix Role & Permission, akses panel Employee App, Audit QC, aplikasi QC, System Monitoring, sidebar Stock Card Settings, detail Back Office): **284 test, 1.931 assertions, 0 gagal**.
- `vendor/bin/pint --dirty --format agent`, `bash ops/ci/check-doc-links.sh`, `bash ops/ci/check-code-map.sh`, dan `git diff --check`: lulus.
- Semua request ESB di test memakai `Http::fake()`.
- Belum dilakukan: UAT kamera pada perangkat Android/iOS dan desktop tanpa kamera (staging).

## 6. Migration dan konfigurasi

- Migration: tidak ada.
- Environment: tidak ada variabel baru.
- Permission baru: `access employee app item journal`, dibuat oleh `permissions:sync` saat deploy.
- Production: berikan `access employee app item journal` beserta permission aksi Item Journal ke role yang disetujui (minimal role store dan role QC yang selama ini memakai Item Journal) melalui Role & Permission. **Tanpa langkah ini, user QC yang selama ini membuka Item Journal akan mendapat 403 dan tile tidak tampil.** Seeder tidak dijalankan di production.
- Queue/scheduler: tidak berubah.

## 7. Risiko dan pekerjaan lanjutan

- Sebagian browser/OS tetap menawarkan galeri walaupun `capture="environment"` dipakai; copy halaman sudah menyatakannya.
- View-all tetap lintas cabang. Pembatasan per cabang memerlukan pengisian `branch_id` (mis. dari mapping ESB) dan amendment PRD tersendiri.
- Rename model/tabel/route ke nama Inventory dapat dipertimbangkan pada rollout terpisah bila diperlukan.
