---
document_id: INVENTORY-STOCK-CARD-DRAFT-WORKFLOW-PLAN
title: Implementation Plan — Stock Card Draft Workflow
status: in-development
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/inventory/stock-card-draft-workflow/prd.md
  - docs/products/inventory/README.md
supersedes: null
---

# Implementation Plan — Stock Card Draft Workflow

Acuan requirement: `docs/products/inventory/stock-card-draft-workflow/prd.md`. Dokumen ini mengatur urutan teknis; requirement canonical tetap berada di PRD.

## 1. Scope implementasi

- **Termasuk:** explicit save draft, restore nilai terakhir, empty-versus-zero, status/progress draft, autosave feedback, submit atomik, policy/status guard, kalender draft, dan regression test.
- **Tidak termasuk:** perubahan review Supervisor/Finance, schema baru, optimistic version column, integrasi ESB baru, permission baru, produk manual, queue, scheduler, dan reopen setelah submit.

## 2. Baseline repository

- Baseline branch: `main` setelah PR #31, commit `977a638c`.
- `StockCardEntryPage` sudah membuat dan memulihkan draft melalui `persistDraft()`.
- `StockCardStatus::Draft` dan constraint unik card/entry sudah tersedia.
- Landing belum membedakan draft dari belum dimulai.
- Final mutation masih berada di Page dan belum atomik.
- Working tree bersih saat planning dibuat.

Sebelum implementasi, ulangi `git switch main`, `git pull --ff-only origin main`, lalu buat branch `feature/stock-card-draft-workflow`; jangan menumpuk implementasi di branch dokumentasi ini.

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| Action | `app/Actions/Inventory/StockCard/SaveStockCardDraftAction.php` | Baru; save draft terotorisasi |
| Action | `app/Actions/Inventory/StockCard/SubmitStockCardAction.php` | Baru; submit atomik |
| Employee App Page | `app/Filament/Casual/Pages/StockCardEntryPage.php` | Ubah; orchestration dan UI state |
| Landing Page | `app/Filament/Casual/Pages/StockCardPage.php` | Ubah; status/progress query |
| Calendar Page | `app/Filament/Casual/Pages/StockCardCalendarPage.php` | Ubah; marker draft |
| Entry View | `resources/views/filament/casual/pages/stock-card-entry-page.blade.php` | Ubah; tombol dan save indicator |
| Landing View | `resources/views/filament/casual/pages/stock-card-page.blade.php` | Ubah; label dan progress |
| Calendar View | `resources/views/filament/casual/pages/stock-card-calendar-page.blade.php` | Ubah; presentasi draft |
| Policy | `app/Policies/StockCardPolicy.php` | Ubah; create/update/submit draft guard |
| Model/Enum | `app/Models/StockCard.php`, `app/Enums/StockCardStatus.php` | Minimal; helper lifecycle bila diperlukan |
| Test | `tests/Feature/StockCardTest.php` | Ubah; draft, restore, zero/null, UI |
| Test | `tests/Feature/StockCardPolicyTest.php` | Ubah; permission/branch/status |
| Test | `tests/Feature/StockCardApprovalWorkflowTest.php` | Ubah; submit/approval regression |
| Dokumentasi | `docs/products/inventory/{README.md,stock-card-draft-workflow/**}` | Ubah; kontrak dan report |

## 4. Dependency dan integration boundary

- Tidak menambah package Composer/NPM.
- Save draft dan submit hanya menyentuh database lokal.
- `EsbStockMovementService`, pemuatan katalog, dan `StockCardEsbSynchronizer` tetap pada tanggung jawab existing.
- Tidak ada HTTP call di dalam transaction.
- Livewire Page mengatur state/presentasi; Action menangani authorization, validation boundary, persistence, dan state transition.

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | Characterization test baseline | Satu PR fitur | PRD disetujui |
| 1 | Extract Action, policy guard, transaction/lock | PR yang sama | Phase 0 hijau |
| 2 | UI draft, progress, autosave feedback, kalender | PR yang sama | Action stabil |
| 3 | Regression, docs, report, delivery | PR yang sama | Phase 1–2 selesai |

## 6. Migration strategy

Tidak ada migration. Gunakan kolom/status/constraint existing. Jika implementasi menemukan kebutuhan schema baru, hentikan implementasi, amend PRD dan plan, lalu minta review sebelum membuat migration additive.

## 7. Data compatibility

- Draft existing tetap dapat dibuka.
- `actual_qty = null` tetap berarti belum dihitung.
- `actual_qty = 0` tetap merupakan nilai sah dan tidak boleh diubah menjadi null oleh normalisasi kosong.
- `reported_qty` hanya diisi saat submit.
- Category/selection snapshot existing tidak dihitung ulang saat melanjutkan draft.
- Route, permission, enum value, dan approval history existing tidak berubah.

## 8. Authorization

- Reuse `access employee app stock card`, `create stock cards`, dan `edit stock cards`.
- Policy/action memeriksa user aktif, branch, record branch, dan status `draft`.
- Submit tidak mengandalkan `showConfirm`, `isSubmitted`, atau public property sebagai security guard.
- Query mutasi mengambil branch dari authenticated user.
- Test mencakup tanpa permission, beda branch, bukan draft, dan direct method invocation.

## 9. UI

1. Landing membedakan no record/draft/pending/completed dan menghitung progress melalui aggregate query, bukan load seluruh entry.
2. Entry memakai empty value untuk `null` dengan placeholder `0`.
3. Autosave dipicu saat perubahan stabil (debounce/blur) dan hanya menyimpan item/employee yang diperlukan.
4. State UI: idle, dirty, saving, saved timestamp, failed.
5. **Simpan Draft** menyimpan state terakhir tanpa final validation atau approval.
6. **Kirim untuk Review** membuka confirmation dan memanggil Action submit setelah validasi server-side.
7. Calendar menampilkan marker draft terpisah; History tidak berubah.

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | Existing draft restore, snapshot, approval workflow, policy |
| 1 | Partial draft, null/zero, permission/branch/status, transaction rollback, double-submit |
| 2 | Landing progress/status, save indicator states, button labels, calendar draft marker |
| 3 | Seluruh `StockCard*Test.php`, doc checks, Pint, diff check |

Test penting:

- Draft parsial menyimpan nilai terakhir setelah reload/logout.
- Qty kosong tetap null dan qty eksplisit nol tetap nol.
- Save draft tanpa employee dan tanpa seluruh qty berhasil.
- Submit dengan qty kosong/negatif/non-numeric/lebih dari empat desimal ditolak.
- Employee nonaktif atau branch lain ditolak.
- Submit langsung tanpa confirmation state tetap divalidasi.
- Submit ganda menghasilkan satu approval.
- Gagal membuat approval me-rollback status/snapshot.
- Draft tidak dapat diedit setelah `pending_supervisor`.
- Draft row yang sudah terisi tidak hilang ketika katalog berubah.

## 11. Observability

- Gunakan notifikasi UI untuk save success/failure dan waktu penyimpanan terakhir.
- Approval audit tetap dibuat hanya pada submit.
- Report exception dengan identifier aman; jangan log payload lengkap.
- Implementation report mencatat jumlah test, hasil formatter/check, dan deviasi.

## 12. Rollout

Application-only deployment. Tidak ada migration, permission sync, environment, worker, scheduler, atau external setup. UAT staging pada mobile mencakup draft parsial, reload, logout/login, koneksi gagal, nilai nol, dan double-tap submit.

## 13. Rollback

Rollback application release. Tidak ada schema/data conversion sehingga draft baru tetap kompatibel dengan kode sebelumnya. Pastikan status yang sudah dikirim tetap mengikuti workflow review existing.

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| Null ternormalisasi menjadi nol | Test eksplisit empty/null/zero pada Action dan Livewire |
| Autosave membuat terlalu banyak query | Persist dirty row, debounce/blur, ukur query pada test/profiling |
| Race submit | Transaction, `lockForUpdate()`, recheck status |
| Draft lintas branch | Policy dan branch query dari authenticated user |
| Snapshot katalog berubah | Pertahankan snapshot dan characterization test |
| UI mengklaim saved saat request gagal | State success hanya di-set dari response berhasil |

## 15. Quality gates

- [x] `php artisan test --compact tests/Feature/StockCardTest.php`
- [x] `php artisan test --compact tests/Feature/StockCardPolicyTest.php`
- [x] `php artisan test --compact tests/Feature/StockCardApprovalWorkflowTest.php`
- [x] Test Stock Card lain yang terdeteksi `rg --files tests | rg 'StockCard'` lulus
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `bash ops/ci/check-doc-links.sh`
- [x] `bash ops/ci/check-code-map.sh`
- [x] `git diff --check`
- [ ] CI Pull Request hijau
- [x] Review authorization, transaction, null/zero, dan branch scope

## 16. Definition of Done

- [ ] Semua phase dan acceptance criteria PRD selesai.
- [ ] PRD berstatus `implemented` setelah production sesuai proses proyek.
- [x] README Inventory menjelaskan perilaku final.
- [x] `reports/implementation-report.md` memuat hasil, deviasi, test, dan risiko.
- [x] Implementation prompt dihapus setelah implementasi selesai.
- [ ] Branch fitur di-push dan PR Bahasa Indonesia dibuka ke `main`.
