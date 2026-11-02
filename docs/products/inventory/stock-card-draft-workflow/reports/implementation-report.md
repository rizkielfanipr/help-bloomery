---
document_id: INVENTORY-STOCK-CARD-DRAFT-WORKFLOW-REPORT
title: Laporan Implementasi — Stock Card Draft Workflow
status: in-development
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/inventory/stock-card-draft-workflow/prd.md
supersedes: null
---

# Laporan Implementasi — Stock Card Draft Workflow

Acuan: `docs/products/inventory/stock-card-draft-workflow/prd.md`

## 1. Status per phase

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 — Characterization | Selesai | Branch `feature/stock-card-draft-workflow` | Baseline 97 test hijau |
| 1 — Mutation boundary | Selesai | Branch yang sama | Tiga Action dan guard policy |
| 2 — UX draft | Selesai pada level test | Branch yang sama | Pemeriksaan perangkat belum dilakukan, lihat §5 |
| 3 — Regression dan delivery | Selesai | Branch yang sama | Merge, deployment, dan UAT tetap keputusan user |

## 2. Ringkasan perubahan

- `PrepareStockCardDraftAction` membuat draft dan daftar produk tanpa qty; perangkat kedua tidak menimpa entry yang sudah ada.
- `SaveStockCardDraftAction` hanya menulis baris dan employee yang berubah, di bawah row lock, dan menolak status selain `draft`.
- `SubmitStockCardAction` memvalidasi data tersimpan lalu menyimpan `reported_qty`, pengirim, status, dan satu approval dalam satu transaction dengan `lockForUpdate()`.
- `StockCardPolicy` menambah `createDraft`, `editDraft`, dan `submit`.
- `StockCardEntryPage` hanya mengatur state dan melacak baris yang belum tersimpan. Landing memakai `withCount`, dan kalender menandai draft.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| Tiga Action, bukan dua seperti rencana awal | Membuat draft memakai permission create dan mengedit memakai edit; menyimpan tidak boleh menambah produk dari browser | PRD §16 |
| Browser tidak boleh mengubah identitas produk atau daftar `rows` (403) | Identifier item tidak boleh dipercaya hanya karena ada di public property | PRD §16 butir 5 |
| Tanggal mutation memakai `draftDate` yang locked | `reportDate` terikat URL dan dapat diubah dari browser | PRD §16 butir 5 |
| Melanjutkan draft tidak mengambil katalog ESB | Draft sudah memiliki daftar produk; ESB yang gagal sebelumnya mengunci tombol kirim | PRD §11 butir 11–12, §15 |
| Qty tidak valid tidak menahan baris lain | Satu salah ketik tidak boleh menghentikan penyimpanan seluruh draft | PRD §18 |
| Default PRD §26 diterapkan | Draft bersama, tanpa auto-expire, History hanya laporan terkirim, tanpa kolom versi | PRD §26 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §19 | Log kegagalan memuat branch ID dan tanggal laporan, bukan stock card ID | Kombinasinya unik, dan kegagalan bisa terjadi sebelum record terbaca | Tidak ada |
| Implementation plan §3 | Tambah `PrepareStockCardDraftAction` dan `tests/Feature/StockCardDraftWorkflowTest.php` | Lihat §3 | Tidak ada |
| — | Perbaikan di luar PRD: hitungan `pastDaysCount` kalender bernilai negatif pada bulan berjalan sehingga kartu ringkasan tersembunyi | Kartu Draft baru tidak akan tampil tanpa perbaikan ini | Tidak ada |
| — | Setup test lama yang mengisi `rows` dari client diganti draft dari database, dan `call('save')` menjadi `call('submit')` | Perilaku lama kini ditolak sesuai PRD §16; assertion lama dipertahankan | Tidak ada |

## 5. Test dan verifikasi

Hasil aktual dicatat pada Pull Request. `tests/Feature/StockCardDraftWorkflowTest.php` mencakup null/zero, restore, autosave per baris, status gagal, guard identitas, submit atomik, double-submit, rollback, permission, branch, status, snapshot, baris legacy, landing, kalender, dan History.

**Belum diverifikasi:**

- Concurrency nyata dua koneksi: double-submit diuji berurutan, sedangkan row lock diperiksa lewat level transaction dan kode. Database test tidak mensimulasikan dua transaksi paralel.
- Perilaku browser dan perangkat: indikator **Menyimpan draft…**, pesan gagal saat koneksi putus (`$wire.$interceptRequest`), **Coba lagi**, double-tap, tampilan mobile, dan dark mode.

## 6. Migration dan konfigurasi

Tidak ada migration, permission baru, env, dependency, queue, scheduler, atau integrasi baru.

## 7. Risiko dan pekerjaan lanjutan

- **Perubahan akses:** `HRD_STAFF` memiliki `access employee app stock card` tanpa `create stock cards` dan `edit stock cards`. Sebelumnya role ini bisa mengisi dan mengirim karena Page tidak memeriksa permission; sekarang ditolak sesuai PRD §16. Role lain yang diberi akses tile lewat UI perlu diperiksa dengan cara yang sama.
- Dua perangkat yang mengedit baris sama mengikuti last-write-wins; kolom versi tetap ditunda sesuai PRD §26.
- Baris dengan qty tidak valid tetap ditandai gagal sampai diperbaiki, dan nilainya hanya ada di halaman sampai saat itu.
