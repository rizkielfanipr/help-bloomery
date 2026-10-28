---
document_id: INVENTORY-ITEM-JOURNAL-PLAN
title: Implementation Plan — Item Journal Inventory
status: implemented
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-28
updated_at: 2026-10-28
target_release: TBD
related_documents:
  - docs/products/inventory/item-journal/prd.md
supersedes: null
---

# Implementation Plan — Item Journal Inventory

Acuan requirement: `docs/products/inventory/item-journal/prd.md`. Dokumen ini mengatur urutan teknis; requirement canonical tetap berada di PRD.

## 1. Scope implementasi

- **Termasuk:** tile Item Journal mandiri, permission tile, pelepasan dari navigasi QC, pemindahan menu Back Office ke Inventory, input gambar camera-first, validasi image-only, compatibility test, dan pembaruan dokumentasi.
- **Tidak termasuk:** rename tabel/model legacy, perubahan endpoint ESB, approval baru, perubahan stok tersedia, dan penghapusan attachment historis.

## 2. Baseline repository

- Branch implementasi: `feature/item-journal-inventory`, dibuat dari `main` pada 28 Oktober 2026.
- Halaman Employee App: `ItemJournalPage` masih tersembunyi dari launcher dan terhubung melalui komponen bottom nav QC.
- Resource Back Office: `QualityControlItemJournalResource` read-only dengan group Quality Control.
- Permission aksi berada pada domain Quality Control.
- File input menerima gambar/PDF, maksimal lima file dan 5 MB.
- Tidak diperlukan migration database berdasarkan audit schema.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Launcher Employee App | `app/Filament/Casual/Pages/LauncherPage.php` | Ubah — tambah tile |
| Halaman Item Journal | `app/Filament/Casual/Pages/ItemJournalPage.php` | Ubah — permission tile dan validasi image-only |
| View Item Journal | `resources/views/filament/casual/pages/item-journal-page.blade.php` | Ubah — navigasi mandiri dan camera-first input |
| Bottom nav QC | `resources/views/components/quality-control/bottom-nav.blade.php` | Ubah — hapus Item Journal |
| Resource Back Office | `app/Filament/Helpdesk/Resources/QualityControlItemJournals/**` | Ubah — group Inventory; pertahankan route/namespace |
| Sidebar Back Office | `resources/views/vendor/filament-panels/components/layout/index.blade.php` | Ubah — pindah menu QC ke Inventory |
| Permission registry | `config/permissions.php` | Ubah — tile baru dan pindah pengelompokan aksi ke Inventory |
| Role defaults | `database/seeders/RolesAndPermissionsSeeder.php` | Ubah — default test/install baru; tidak dijalankan di production |
| Policy/model scope | `app/Policies/QualityControlItemJournalPolicy.php`, `app/Models/QualityControlItemJournal.php` | Audit; ubah hanya bila branch scope dikontrak dan kompatibel |
| Monitoring links | `app/Services/Monitoring/MonitoringLocalRecordLinker.php` | Regression check; hindari perubahan route |
| Tests | `tests/Feature/{LauncherPageTest,QualityControlItemJournal*Test}.php` | Ubah/tambah |
| Dokumentasi | `docs/products/{inventory,quality-control}/README.md`, `docs/architecture/{panels,rbac}.md` | Ubah saat implementasi selesai |

## 4. Dependency dan integration boundary

- Tidak menambah package.
- Integrasi ESB tetap dimiliki `EsbItemJournalService`.
- Stock availability tetap dimiliki `EsbStockMovementService`.
- Livewire tetap mengelola upload sementara dan submit.
- Browser hanya menangani pengambilan gambar; validasi keamanan tetap server-side.
- Permission registry dan policy tetap menjadi sumber authorization, bukan visibilitas UI saja.

## 5. Urutan phase

| Phase | Scope | Output | Prasyarat |
|---|---|---|---|
| 0 | Characterization | Test flow existing, route, monitoring link, dan data lama | PRD disetujui |
| 1 | Navigation/RBAC | Tile mandiri, permission tile, group Inventory, QC nav dibersihkan | Phase 0 hijau |
| 2 | Camera input | Input image-only, capture kamera belakang, preview/error state | Phase 1 hijau |
| 3 | Compatibility/security | Role tests, policy/scope audit, old PDF/link regression | Phase 2 hijau |
| 4 | Docs/delivery | README dan architecture sync, CI, UAT/PR | Seluruh test targeted hijau |

Implementasi direncanakan dalam satu PR karena tidak ada migration dan setiap phase saling bergantung. Commit boleh dipisah per concern untuk memudahkan review.

## 6. Migration strategy

Not applicable — tidak ada perubahan schema. Jangan membuat migration rename tabel/model. Permission baru dibuat oleh `permissions:sync` pada deploy normal.

## 7. Data compatibility

- Record, detail, attachment, payload, status, dan nomor journal lama tidak diubah.
- PDF historis tetap dapat dibaca dan di-retry oleh service existing.
- Validasi image-only berlaku pada upload baru.
- Route resource dipertahankan agar deep link dan System Monitoring tidak rusak.
- Bila branch scope diperketat, buat characterization untuk `branch_id = null` sebelum mengubah query.

## 8. Authorization

1. Tambahkan `access employee app item journal` pada domain Akses Employee App.
2. Gunakan permission tersebut untuk tile dan `ItemJournalPage::canAccess()`.
3. Policy aksi tetap memeriksa permission view/create/submit/delete existing.
4. Pindahkan pengelompokan permission aksi dari Quality Control ke Inventory tanpa mengganti string permission.
5. Tambahkan default STORE_STAFF pada seeder untuk test/fresh install.
6. Tentukan default INVENTORY_STAFF untuk Back Office sesuai hasil UAT; production assignment dilakukan lewat UI.
7. Pastikan user tanpa permission ditolak walau mengetahui URL.

## 9. UI

### Launcher dan halaman

- Tambah tile Item Journal memakai pola tile existing.
- Hapus tautan Item Journal dari bottom nav QC.
- Gunakan header/back action menuju launcher serta tab/section form-riwayat existing.

### Kamera

- Ganti input menjadi image-only dengan `accept="image/*"` dan `capture="environment"`.
- Hapus PDF dari label dan rule upload baru.
- Pertahankan batas lima foto dan 5 MB/foto.
- Tambahkan preview, hapus, ambil ulang, loading upload, permission denied, dan camera unavailable state.
- Jangan mengklaim jaminan camera-only absolut pada browser yang dialog sistemnya tetap menawarkan galeri.

### Back Office

- Pindahkan custom sidebar entry ke Inventory.
- Set navigation group resource menjadi Inventory.
- Pertahankan tabel/detail read-only dan route existing.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Characterization create, submit, retry attachment, delete attachment, route, monitoring link |
| 1 | Launcher visibility, direct URL 403, policy separation, resource group/sidebar Inventory, QC nav absent |
| 2 | Image upload valid, PDF/non-image invalid, max files/size, markup capture environment, error state |
| 3 | STORE_STAFF/INVENTORY_STAFF/QC/SUPERADMIN, own-vs-all records, old PDF regression, ESB payload unchanged |
| 4 | Seluruh file targeted, docs links, code map, CI PR |

Targeted command minimum:

```bash
php artisan test --compact \
  tests/Feature/LauncherPageTest.php \
  tests/Feature/QualityControlItemJournalTest.php \
  tests/Feature/QualityControlItemJournalPolicyTest.php \
  tests/Feature/QualityControlItemJournalBackOfficeTest.php
```

## 11. Observability

- Pertahankan `EsbTransactionRecorder` dan idempotency key existing.
- Pastikan error kamera lokal dapat dipahami user tanpa mengirim data sensitif ke log.
- Monitoring link tetap mengarah ke resource detail yang valid.
- UAT mencatat jumlah submit berhasil, attachment retry, dan error ESB.

## 12. Rollout

1. Deploy melalui atomic release pipeline.
2. `permissions:sync` membuat permission tile.
3. Administrator memberi permission pada role production melalui Role & Permission.
4. UAT staging memakai perangkat Android dan iOS yang tersedia serta satu desktop tanpa kamera.
5. Verifikasi satu transaksi ESB non-production/sandbox dan attachment.
6. Pantau monitoring transaction setelah release.

## 13. Rollback

- Rollback release mengembalikan navigasi dan input lama.
- Tidak ada reverse migration.
- Permission tile yang tertinggal tidak berdampak pada kode lama.
- Journal yang dibuat release baru tetap kompatibel dengan release lama.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Browser menawarkan galeri walau `capture` aktif | Camera-first UI, tanpa file picker umum; jelaskan batasan platform |
| Tile tidak terlihat setelah deploy | Checklist permission assignment dan UAT role |
| Link lama rusak karena pemindahan resource | Jangan rename route/namespace pada phase ini |
| Data lintas branch terlihat terlalu luas | Characterization dan review policy sebelum mengubah scope |
| PDF historis gagal di-retry | Pisahkan validasi upload baru dari pembacaan attachment existing |

## 15. Quality gates

- [x] `vendor/bin/pint --dirty --format agent`
- [x] Test targeted lulus
- [x] `bash ops/ci/check-doc-links.sh`
- [x] `bash ops/ci/check-code-map.sh`
- [x] `git diff --check`
- [ ] CI Pull Request hijau
- [ ] Review authorization dan data safety
- [ ] UAT camera Android/iOS dan Back Office Inventory

## 16. Definition of Done

- [x] Semua phase selesai sesuai PRD.
- [x] Data lama dan link monitoring tervalidasi.
- [x] README Inventory, Quality Control, Panels, dan RBAC cocok dengan kode final.
- [x] PRD berstatus `implemented` dan implementation report dibuat.
- [x] Deployment note mencantumkan permission production yang harus diberikan.
