---
document_id: INVENTORY-STOCK-CARD-DRAFT-WORKFLOW-PROMPT
title: Prompt Implementasi — Stock Card Draft Workflow
status: draft
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/inventory/stock-card-draft-workflow/prd.md
  - docs/products/inventory/stock-card-draft-workflow/implementation-plan.md
supersedes: null
---

# Prompt Implementasi — Stock Card Draft Workflow

Salin blok berikut ke agent implementasi dari root repository.

```text
Implementasikan Stock Card Draft Workflow pada domain Inventory sesuai workflow Plan → Branch → Implement → Deliver.

## Dokumen sumber yang wajib dibaca
- AGENTS.md.
- .claude/skills/feature-delivery/SKILL.md.
- .claude/skills/laravel-best-practices/SKILL.md beserta rule yang relevan.
- docs/products/inventory/README.md.
- docs/products/inventory/stock-card-draft-workflow/prd.md — sumber requirement canonical.
- docs/products/inventory/stock-card-draft-workflow/implementation-plan.md.
- docs/standards/code-remediation.md §Phase 6 dan Definition of Done.
- docs/standards/ui-consistency.md.
- docs/architecture/data-model.md §Inventory.
- docs/architecture/panels.md dan docs/architecture/rbac.md.

## Branch
1. Pastikan working tree bersih.
2. git switch main.
3. git pull --ff-only origin main.
4. git switch -c feature/stock-card-draft-workflow.
5. Jangan mengimplementasikan pada branch docs/stock-card-draft-workflow-plan atau main.

## Hasil akhir
- Staff dapat menyimpan Stock Card parsial sebagai draft dan melanjutkannya kemudian.
- Qty belum dihitung tetap null/kosong; qty eksplisit 0 tetap disimpan dan ditampilkan sebagai 0.
- Landing menampilkan draft dan progress, bukan Belum diisi.
- Entry mempunyai autosave feedback, Simpan Draft, dan Kirim untuk Review.
- Submit menjalankan validasi final server-side dan transisi atomik ke pending_supervisor.
- Workflow Supervisor/Finance existing tidak berubah.

## Scope
- Extract mutation draft dan submit ke Action yang single-purpose.
- Perkuat Policy/authorization dan branch/status guard.
- Optimalkan autosave agar tidak menulis seluruh dataset setiap karakter.
- Perbarui landing, entry page, calendar, dan view terkait.
- Tambahkan test null/zero, partial restore, direct submit, rollback, double-submit, dan branch scope.
- Update docs dan buat implementation report saat selesai.

## Non-scope
- Jangan menambah status baru.
- Jangan menambah migration, permission, package, API, queue, scheduler, atau environment.
- Jangan mengubah review Supervisor/Finance, sumber ESB, aturan kategori, atau snapshot selection.
- Jangan menambah produk manual atau reopen setelah submit.
- Jika schema baru benar-benar diperlukan, hentikan pekerjaan dan amend PRD sebelum melanjutkan.

## Aturan arsitektur
- Gunakan Action di app/Actions/Inventory/StockCard untuk mutation.
- Livewire Page hanya mengatur state/presentasi dan memanggil Action.
- Submit memakai DB::transaction(), lockForUpdate(), dan recheck status draft.
- Simpan reported_qty, submitted_by, submitted_at, status, dan StockCardApproval dalam transaction yang sama.
- Double-submit tidak boleh menghasilkan approval ganda.
- Jangan menjalankan HTTP/ESB call di dalam transaction.
- Reuse unique constraint branch_id + report_date dan stock_card_id + product_code.

## Aturan data
- Input kosong dinormalisasi ke null, bukan 0.
- String "0" dan numeric 0 dinormalisasi ke zero yang sah.
- Draft boleh qty parsial dan employee kosong.
- Qty terisi harus numeric, >= 0, maksimal 4 desimal.
- Submit mewajibkan seluruh qty dan minimal satu employee aktif dari branch user.
- Category settings snapshot dan selection snapshot existing harus dipertahankan.
- Filled draft row yang tidak lagi ada di katalog tidak boleh terhapus.

## Authorization
- Reuse access employee app stock card, create stock cards, dan edit stock cards.
- Setiap Action harus authorize server-side melalui Policy.
- Branch berasal dari authenticated user, bukan public property.
- Tolak beda branch, status bukan draft, user tanpa permission, dan employee invalid.
- Jangan mengandalkan visibility tombol atau showConfirm sebagai security guard.

## UI
- Landing: Belum dimulai, Draft · x/y item, Menunggu Supervisor, Menunggu Finance, Selesai.
- Draft membuka aksi Lanjutkan Draft.
- Qty kosong ditampilkan kosong dengan placeholder 0.
- Tampilkan Menyimpan draft…, Draft tersimpan pukul HH:mm, dan Gagal menyimpan draft — Coba lagi.
- Tombol sekunder Simpan Draft; tombol utama Kirim untuk Review.
- Confirmation menjelaskan data akan terkunci dan koreksi melalui Supervisor/Finance.
- Calendar membedakan draft; History tetap mengecualikan draft.
- Ikuti UI mobile dan komponen existing.

## Tahapan
1. Jalankan characterization test Stock Card existing dan catat baseline.
2. Buat Action melalui php artisan make:class --no-interaction, lalu implementasikan mutation dan policy guard.
3. Refactor Livewire Page untuk menggunakan Action tanpa mengubah katalog/snapshot.
4. Implementasikan UI draft/progress/autosave/calendar.
5. Tambahkan test minimum berikut.
6. Perbarui README Inventory, ubah status dokumen sesuai phase, dan buat reports/implementation-report.md.
7. Jalankan formatter dan seluruh quality gate.
8. Commit, push, dan buka PR ke main sesuai template repository dalam Bahasa Indonesia. Jangan merge atau deploy.

## Test minimum
- Partial draft save dan restore setelah reload/login ulang.
- Empty menjadi null; explicit zero tetap zero.
- Save draft tanpa employee atau qty lengkap berhasil tanpa approval.
- Submit menolak empty, negative, non-numeric, lebih dari empat desimal, atau employee invalid.
- Direct Livewire submit tetap divalidasi.
- Branch/permission/status guard.
- Submit sukses atomik dan membuat satu approval.
- Double-submit tidak menggandakan approval.
- Failure di tengah submit me-rollback seluruh perubahan.
- Draft terkunci setelah pending_supervisor.
- Landing progress/status dan calendar draft marker.
- Snapshot dan filled legacy draft rows tetap dipertahankan.

## Quality gate
- php artisan test --compact untuk seluruh tests/Feature/StockCard*Test.php yang terdampak.
- vendor/bin/pint --dirty --format agent.
- bash ops/ci/check-doc-links.sh.
- bash ops/ci/check-code-map.sh.
- git diff --check.
- CI PR hijau.

## Laporan akhir
Laporkan branch, commit, PR, perubahan per phase, file terdampak, hasil test aktual, migration/permission/integration impact, deviasi PRD, risiko, dan langkah UAT. Jangan mengklaim test yang tidak dijalankan.
```
