---
document_id: INVENTORY-ITEM-JOURNAL-PROMPT
title: Prompt Implementasi — Item Journal Inventory
status: draft
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-28
updated_at: 2026-10-28
target_release: TBD
related_documents:
  - docs/products/inventory/item-journal/prd.md
  - docs/products/inventory/item-journal/implementation-plan.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# Prompt Implementasi — Item Journal Inventory

Salin seluruh blok berikut ke agent implementasi dari root repository.

```text
Implementasikan pemindahan dan penyempurnaan fitur Item Journal secara end-to-end pada domain Inventory di branch `feature/item-journal-inventory`.

Pekerjaan harus mengikuti workflow Plan → Branch → Implement → Deliver. Branch sudah dibuat dan berisi dokumentasi awal; jangan membuat branch lain kecuali branch tersebut sudah tidak tersedia. Jangan merge PR dan jangan deploy ke staging/production.

## Tujuan

Hasil akhir yang wajib tercapai:

1. Employee App memiliki tile **Item Journal** mandiri.
2. Staff store dapat membuka dan memakai Item Journal tanpa permission akses Quality Control.
3. Item Journal tidak lagi muncul pada bottom navigation Quality Control.
4. Rekapan Back Office berpindah dari **Quality Control → Item Journal** menjadi **Inventory → Item Journal**.
5. Input attachment baru bersifat image-only dan camera-first; PDF tidak dapat diunggah sebagai attachment baru.
6. Record, detail, attachment, PDF lama, nomor jurnal, status, link monitoring, dan transaksi ESB existing tetap aman serta kompatibel.
7. Resource Back Office tetap read-only.
8. Implementasi mempunyai test authorization, navigasi, upload, integrasi, dan regresi yang memadai.

## Dokumen sumber yang wajib dibaca seluruhnya

- `AGENTS.md` — aturan repository dan Laravel Boost.
- `.claude/skills/feature-delivery/SKILL.md` — workflow delivery fitur.
- `.claude/skills/laravel-best-practices/SKILL.md` — aturan Laravel.
- `.claude/skills/pest-testing/SKILL.md` — aturan test Pest.
- `.claude/skills/tailwindcss-development/SKILL.md` — aturan UI Tailwind.
- `docs/products/inventory/item-journal/prd.md` — sumber requirement canonical.
- `docs/products/inventory/item-journal/implementation-plan.md` — urutan implementasi.
- `docs/products/inventory/README.md` — baseline domain Inventory.
- `docs/products/quality-control/README.md` — baseline domain Quality Control.
- `docs/architecture/panels.md` — launcher dan sidebar.
- `docs/architecture/rbac.md` — permission dan role.
- `docs/standards/code-remediation.md` — keamanan data dan kualitas arsitektur.
- `docs/standards/ui-consistency.md` — konsistensi UI.

Gunakan Laravel Boost `search-docs` sebelum mengubah kode. Gunakan `database-schema` bila perlu memverifikasi tabel; jangan membuat query mutasi atau data demo di database production.

## Baseline yang harus dipertahankan

- Halaman Employee App: `App\Filament\Casual\Pages\ItemJournalPage`.
- Resource Back Office: `App\Filament\Helpdesk\Resources\QualityControlItemJournals\QualityControlItemJournalResource`.
- Model/tabel legacy: `QualityControlItemJournal*` dan `quality_control_item_journal*`.
- Integrasi: `EsbItemJournalService`, `EsbStockMovementService`, `EsbTransactionRecorder`.
- Route resource dan deep link monitoring existing.
- Status existing: `submitting`, `succeeded`, `attachment_failed`, `unknown`, `verification_required`.
- Idempotency menggunakan submission key dan payload hash existing.
- Maksimal lima attachment dan 5 MB per attachment.

Jangan mengganti nama tabel, model, foreign key, route resource, atau endpoint ESB pada pekerjaan ini.

## Scope implementasi

### A. Characterization dan audit awal

1. Pastikan worktree bersih dan branch aktif adalah `feature/item-journal-inventory`.
2. Fetch origin dan pastikan branch tidak tertinggal dari basis `main`; jangan rebase/force-push tanpa kebutuhan nyata.
3. Audit hanya file pada Peta Kode Inventory/QC dan file yang tercantum dalam implementation plan.
4. Jalankan test Item Journal existing sebelum perubahan dan catat baseline.
5. Tambahkan characterization test bila perilaku penting belum tercakup:
   - create dan submit ke ESB;
   - retry attachment;
   - delete attachment;
   - uncertain result/idempotency;
   - resource route dan link System Monitoring;
   - jurnal/attachment lama termasuk PDF.

### B. Permission dan launcher Employee App

1. Tambahkan permission baru `access employee app item journal` pada domain **Akses Employee App** di `config/permissions.php`.
2. Pindahkan pengelompokan permission aksi berikut dari domain **Quality Control** ke **Inventory** tanpa mengganti string permission:
   - `view quality control item journals`;
   - `view all quality control item journals`;
   - `create quality control item journals`;
   - `submit quality control item journals`;
   - `delete quality control item journal attachments`.
3. Tambahkan tile **Item Journal** di `LauncherPage` memakai pola tile existing, ikon penyesuaian stok, dan permission tile baru.
4. Ubah `ItemJournalPage::canAccess()` agar permission tile baru diperlukan untuk membuka halaman. Permission tile tidak boleh menggantikan policy aksi create/submit/delete.
5. Tambahkan default permission untuk `STORE_STAFF` pada seeder fresh install/test:
   - permission tile;
   - view/create/submit/delete attachment Item Journal existing.
6. Pertahankan akses QC existing secara kompatibel bila role QC masih mempunyai permission aksi. Jangan otomatis memberi seluruh role akses production melalui seeder; production dikelola lewat UI.
7. Tentukan default `INVENTORY_STAFF` hanya berdasarkan kontrak PRD: akses Back Office/view Item Journal. Jika scope view-all belum aman lintas branch, jangan memberikan view-all secara default sebelum policy diuji.
8. Tambahkan test bahwa:
   - tile muncul dengan permission baru;
   - tile tidak muncul tanpa permission;
   - URL langsung menghasilkan 403 tanpa permission;
   - user yang mempunyai permission QC tetapi tidak mempunyai permission tile tidak otomatis masuk halaman mandiri;
   - permission tile saja tidak cukup untuk submit.

### C. Lepaskan Item Journal dari Quality Control

1. Hapus Item Journal dari `resources/views/components/quality-control/bottom-nav.blade.php`.
2. Ubah halaman Item Journal agar tidak merender `<x-quality-control.bottom-nav>`.
3. Tambahkan pola navigasi mobile yang konsisten dengan tile lain: tombol kembali ke launcher, judul, form, dan riwayat yang mudah dipahami.
4. Jangan mengubah fitur Audit QC atau Checklist QC.
5. Tambahkan test bahwa navigasi QC tidak lagi memuat tautan Item Journal.

### D. Pindahkan Back Office ke Inventory

1. Pindahkan item custom sidebar dari grup Quality Control ke Inventory di `resources/views/vendor/filament-panels/components/layout/index.blade.php`.
2. Ubah `QualityControlItemJournalResource::$navigationGroup` menjadi `Inventory` dan sesuaikan urutan navigasi secara konsisten.
3. Pertahankan resource read-only: tidak ada route create/edit, dan delete record tetap tidak tersedia.
4. Pertahankan namespace, route name, dan resource class existing agar deep link dan `MonitoringLocalRecordLinker` tidak rusak.
5. Tambahkan/ubah test Back Office untuk memastikan:
   - navigation group adalah Inventory;
   - sidebar hanya menampilkan Item Journal pada Inventory;
   - route index/view tetap sama dan berfungsi;
   - create/edit/delete record tetap tidak tersedia;
   - record lama tetap muncul sesuai authorization.

### E. Attachment camera-first dan image-only

1. Ganti label input **Tambah Foto / PDF** menjadi **Ambil Foto**.
2. Input baru hanya menerima gambar dengan `accept="image/*"` dan memprioritaskan kamera belakang menggunakan `capture="environment"`.
3. Hapus PDF dari rule upload baru dan validasi server-side. Izinkan MIME JPEG, PNG, dan WebP saja.
4. Pertahankan maksimal lima gambar dan 5 MB per gambar.
5. Tampilkan preview foto sebelum submit dengan aksi hapus dan ambil ulang.
6. Sediakan state/pesan yang jelas untuk:
   - permission kamera ditolak;
   - kamera tidak tersedia;
   - upload berlangsung;
   - file tidak valid;
   - jumlah/ukuran melebihi batas.
7. Gunakan solusi sederhana tanpa dependency baru. Utamakan kemampuan browser native/HTML. Jangan menambah package kamera tanpa persetujuan.
8. Jangan mengklaim jaminan camera-only absolut. Sebagian browser/OS dapat tetap menawarkan galeri walaupun `capture` dipakai.
9. Desktop/perangkat tanpa kamera harus mendapat pesan yang jelas; attachment tetap opsional seperti baseline.
10. Validasi image-only hanya berlaku pada upload baru. PDF historis harus tetap dapat dibaca, di-retry, atau dihapus sesuai permission.
11. Tambahkan test untuk:
    - markup `accept="image/*"` dan `capture="environment"`;
    - JPEG/PNG/WebP valid;
    - PDF dan file non-image ditolak;
    - batas lima file dan 5 MB;
    - PDF historis tidak hilang/rusak;
    - payload attachment ESB tetap benar.

### F. Authorization dan branch scope

1. Semua aksi harus diperiksa server-side melalui policy/permission; visibilitas tile/tombol bukan kontrol keamanan.
2. Pertahankan perilaku journal milik user untuk akses biasa.
3. Audit `view all quality control item journals`, yang saat ini dapat membuka seluruh record.
4. Jangan memperketat atau memperluas branch scope secara diam-diam. Bila implementasi perlu mengubahnya:
   - dokumentasikan perubahan di PRD terlebih dahulu;
   - definisikan perilaku `branch_id = null`;
   - tambahkan test access-all, accessible branches, branch terlarang, creator, dan SUPERADMIN.
5. Jangan mengubah permission string aksi legacy pada rollout ini.

### G. Integrasi, data safety, dan monitoring

1. Jangan mengubah endpoint ESB, struktur payload create journal, atau mekanisme HPP/stock availability.
2. Pertahankan idempotency, retry, timeout, transaction recording, dan uncertain-result handling.
3. Gunakan `Http::fake()`/fake service dalam test; jangan memanggil ESB sungguhan.
4. Jangan menghapus, mengubah, atau melakukan backfill record lama.
5. Jangan membuat migration database kecuali audit menemukan kebutuhan yang benar-benar wajib. Jika terjadi, hentikan implementasi dan laporkan karena PRD menetapkan tidak ada migration.
6. Jangan log token, credential, file content, atau payload sensitif.
7. Pastikan link dari System Monitoring ke detail Item Journal tetap valid.

## Aturan UI

- Ikuti `docs/standards/ui-consistency.md` dan pola halaman Employee App existing.
- UI harus nyaman pada viewport minimal 360 px.
- Gunakan border tipis, spacing, typography, dark mode, loading, empty, success, dan error states yang konsisten.
- Jangan membuat komponen visual duplikat bila pola existing dapat dipakai ulang.
- Jangan memasukkan query database atau logika bisnis ke Blade.
- Gunakan Bahasa Indonesia yang sederhana untuk label dan pesan pengguna.

## File utama yang diperkirakan berubah

- `config/permissions.php`
- `database/seeders/RolesAndPermissionsSeeder.php`
- `app/Filament/Casual/Pages/LauncherPage.php`
- `app/Filament/Casual/Pages/ItemJournalPage.php`
- `resources/views/filament/casual/pages/item-journal-page.blade.php`
- `resources/views/components/quality-control/bottom-nav.blade.php`
- `app/Filament/Helpdesk/Resources/QualityControlItemJournals/QualityControlItemJournalResource.php`
- `resources/views/vendor/filament-panels/components/layout/index.blade.php`
- `tests/Feature/LauncherPageTest.php`
- `tests/Feature/QualityControlItemJournalTest.php`
- `tests/Feature/QualityControlItemJournalPolicyTest.php`
- `tests/Feature/QualityControlItemJournalBackOfficeTest.php`
- dokumentasi terkait.

Jangan membuat folder dasar baru. Gunakan `php artisan make:* --no-interaction` bila benar-benar membuat class/test baru. Ikuti struktur dan konvensi sibling files.

## Tahapan kerja wajib

### Phase 0 — Characterization

- Jalankan baseline test.
- Lengkapi test perilaku existing yang belum tercakup.
- Jangan refactor sebelum characterization hijau.

### Phase 1 — Navigation dan RBAC

- Tambah tile/permission.
- Lepas Item Journal dari QC.
- Pindah Back Office ke Inventory.
- Jalankan test launcher, policy, dan resource.

### Phase 2 — Kamera dan validasi

- Implementasikan image-only camera-first.
- Tambahkan preview/error states.
- Jalankan test upload dan UI markup.

### Phase 3 — Compatibility dan security

- Uji role, ownership, data lama, PDF lama, route monitoring, idempotency, dan payload ESB.
- Perbaiki temuan tanpa memperluas scope tersembunyi.

### Phase 4 — Dokumentasi dan delivery

1. Perbarui `docs/products/inventory/README.md` menjadi perilaku final.
2. Perbarui `docs/products/quality-control/README.md` dan hapus Item Journal dari ownership QC.
3. Perbarui `docs/architecture/panels.md` dan `docs/architecture/rbac.md`.
4. Perbarui Peta Kode pada README domain.
5. Set status PRD dan plan menjadi `implemented` hanya setelah seluruh acceptance criteria terpenuhi.
6. Buat laporan implementasi akhir pada subfolder `reports/` fitur ini dari template implementation report.
7. Hapus `implementation-prompt.md` setelah implementasi selesai, sesuai workflow repository.

## Test minimum

Jalankan minimal:

```bash
php artisan test --compact \
  tests/Feature/LauncherPageTest.php \
  tests/Feature/QualityControlItemJournalTest.php \
  tests/Feature/QualityControlItemJournalPolicyTest.php \
  tests/Feature/QualityControlItemJournalBackOfficeTest.php
```

Kemudian:

```bash
vendor/bin/pint --dirty --format agent
bash ops/ci/check-doc-links.sh
bash ops/ci/check-code-map.sh
git diff --check
```

Jika perubahan memengaruhi permission registry atau launcher secara luas, jalankan test permission/launcher terkait yang ditemukan dalam audit. Jangan menjalankan full Feature suite lokal dalam satu proses bila berisiko kehabisan memory; gunakan CI shard sesuai panduan repository.

## Acceptance checklist

- [ ] Tile Item Journal mandiri tampil berdasarkan permission baru.
- [ ] Akses halaman tidak membutuhkan akses QC.
- [ ] Direct URL tanpa permission menghasilkan 403.
- [ ] Item Journal hilang dari bottom navigation QC.
- [ ] Back Office Item Journal hanya berada di grup Inventory.
- [ ] Resource tetap read-only dan route lama tetap valid.
- [ ] Upload baru hanya menerima JPEG/PNG/WebP, maksimal lima dan 5 MB.
- [ ] Kamera belakang diprioritaskan dan error kamera mudah dipahami.
- [ ] PDF historis dan semua record lama tetap aman.
- [ ] Flow create/retry/delete ESB tetap idempotent dan termonitor.
- [ ] Test targeted dan quality gates lulus.
- [ ] Dokumentasi final sesuai kode.

## Delivery GitHub

Setelah seluruh quality gate lulus:

1. Pastikan tidak ada perubahan unrelated.
2. Commit dengan pesan konvensional, misalnya `feat: move Item Journal to Inventory`.
3. Push branch `feature/item-journal-inventory`.
4. Buat Pull Request ke `main` memakai `.github/pull_request_template.md` dalam Bahasa Indonesia.
5. Isi hasil test aktual, risiko, migration (`tidak ada`), queue/scheduler (`tidak berubah`), integrasi ESB (`kontrak tidak berubah`), permission baru, dan langkah UAT staging.
6. Jangan squash/merge PR dan jangan approve/deploy production.

## Format laporan akhir agent

1. Branch dan commit terakhir.
2. Ringkasan hasil per phase.
3. Daftar file penting yang berubah.
4. Test/quality gate yang dijalankan beserta hasil aktual.
5. Deviasi dari PRD dan alasannya.
6. Catatan rollout permission production.
7. URL Pull Request dan hal yang masih perlu diputuskan pengguna.

## Larangan keras

- Jangan mengganti nama atau menghapus tabel/model Item Journal existing.
- Jangan menghapus atau memodifikasi data lama.
- Jangan mengubah endpoint/payload utama ESB tanpa perubahan PRD dan persetujuan.
- Jangan mengganti permission aksi legacy pada rollout ini.
- Jangan menjadikan visibilitas tile sebagai satu-satunya authorization.
- Jangan menambah dependency tanpa persetujuan.
- Jangan menyimpan credential/token di kode, log, test fixture, atau dokumentasi.
- Jangan force-push, merge PR, menjalankan seeder role di production, atau deploy.
```
