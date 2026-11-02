---
document_id: INVENTORY-NON-PO-RECEIVING-REPORT
title: Laporan Implementasi — Penerimaan Tanpa PO dan PO Susulan
status: draft
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/inventory/non-po-receiving/prd.md
  - docs/products/inventory/non-po-receiving/rollout.md
supersedes: null
---

# Laporan Implementasi — Penerimaan Tanpa PO dan PO Susulan

Acuan: `docs/products/inventory/non-po-receiving/prd.md`

## 1. Status per phase

| Phase | Status | Commit/PR | Catatan |
|---|---|---|---|
| 0 | Selesai | Branch `feature/non-po-receiving` | Baseline Receiving 51 test lulus; kontrak dibaca dari kode dan test, belum dari ESB live |
| 1 | Selesai | Branch `feature/non-po-receiving` | Aggregate lokal, draft, submit, QC, foto, policy, notifikasi |
| 2 | Selesai | Branch `feature/non-po-receiving` | Antrean PO Susulan, transisi, perbandingan PO |
| 3 | Selesai | Branch `feature/non-po-receiving` | `CreateGoodsReceiptAction` dipakai bersama, GR idempotent, failed/unknown, rekonsiliasi |
| 4 | Berjalan | Branch `feature/non-po-receiving` | Test dan dokumen selesai; review, CI, dan UAT staging belum |

## 2. Ringkasan perubahan

- `GoodsReceiptPage` kini mendelegasikan mutasi ke `CreateGoodsReceiptAction`. Aturan QC per item pindah ke `InboundGoodsReceiptQcService::itemViolations()`, perilaku item ke trait `ManagesReceivingItems`, dan modal QC ke partial yang dipakai bersama. Test Receiving existing tidak diubah.
- `NonPoReceiptPage` adalah mode Tanpa PO pada tile Receiving, dengan Action draft dan submit di `app/Actions/Inventory/Receiving/`.
- `NonPoReceiptResource` adalah submenu **PO Susulan**, dengan Action di `app/Actions/Purchasing/NonPoReceipt/`.
- Back Office Receiving menampilkan sumber dan tautan; System Monitoring membedakan lewat aksi `submit_non_po`, idempotency key `non-po-goods-receipt:*`, dan label record lokal.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| Halaman Employee App terpisah, dihubungkan dengan pilihan mode | Receiving Dengan PO tetap kecil dan berisiko regresi rendah; entry tetap tile Receiving | PRD §17 |
| QC detail disimpan sebagai snapshot `qc_data` | Hanya dibaca kembali; kolom final ada di `goods_receipt_items` saat GR dibuat | PRD §14 |
| Perubahan status ke `gr_processing` di bawah row lock menjadi gerbang panggilan ESB | Klik ganda dan request bersamaan hanya menghasilkan satu POST | PRD §18 |
| Tidak menambah kolom pada `goods_receipts` | Sumber dapat diturunkan dari `non_po_receipts.goods_receipt_id` | PRD §20, §29 |
| Foto dokumen dan foto barang wajib saat kirim | Tanpa PO, dokumen dan foto menjadi satu-satunya bukti awal | PRD §10, FR-3 |

## 4. Deviasi dari PRD

Seluruh amendemen kontrak ada di PRD §29. Selain itu:

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| Plan §4 "submit tidak memanggil ESB" | Submit membaca cabang, lokasi, dan Master Product dari ESB; tidak ada mutasi | PRD §16 mewajibkan lookup ulang server-side | Tidak ada |
| §13 aktor `gr_failed`/`gr_unknown` | Rekonsiliasi memakai `process non po purchase order requests`, batal memakai `cancel non po purchase order requests`; tidak ada permission baru | PRD tidak menetapkan permission khusus | Tinjau saat UAT |
| Plan §6 regenerasi data model | Baris ditambahkan manual dalam format yang sama | Repository tidak mempunyai generator `data-model.md` | Tidak ada |

## 5. Test dan verifikasi

Hasil aktual ada pada deskripsi Pull Request. Yang sudah diverifikasi:

- Test Receiving existing lulus tanpa perubahan setelah ekstraksi Action; test ini menangkap satu regresi tipe payload (float menjadi integer akibat JSON cast) yang sudah diperbaiki.
- `tests/Feature/NonPoReceipt{Inventory,Purchasing}Test.php` memakai ESB tiruan. Belum ada transaksi ke ESB sungguhan.
- Empat migration fitur lulus up, down, dan re-up pada MySQL sementara. Satu nama index yang melebihi 64 karakter ditemukan dan diperbaiki; SQLite pada CI tidak menangkap kasus ini.

Belum diverifikasi: tampilan di browser, kontrak GET PO/POST GR dengan ESB live, dan seluruh skenario UAT `rollout.md` §6.

## 6. Migration dan konfigurasi

- Empat tabel baru: `non_po_receipts`, `non_po_receipt_items`, `non_po_receipt_batches`, `non_po_receipt_status_histories`. Additive; tidak ada backfill dan tidak ada perubahan pada `goods_receipts`.
- Lima permission baru: `create non po receipts`, `submit non po receipts`, `view non po purchase order requests`, `process non po purchase order requests`, `cancel non po purchase order requests`. Deploy menjalankan `permissions:sync`; role production diberikan lewat Role & Permission.
- Tidak ada env, queue, atau scheduler baru.

## 7. Risiko dan pekerjaan lanjutan

- PO yang dibuat untuk PO Susulan juga tampil di Receiving Dengan PO. Sebelum GR PO Susulan selesai, staff dapat menerima PO itu dari jalur normal. Verifikasi menghitung GR normal tersebut sehingga over-receipt diblokir, tetapi tidak ada larangan lintas jalur.
- Proses PHP yang mati saat POST GR meninggalkan record di `gr_processing` tanpa aksi rekonsiliasi di UI. Perilaku GR `processing` di Receiving normal sama. Perlu penanganan manual dan dipertimbangkan sebagai pekerjaan lanjutan.
- Sisa Hold pada penerimaan campuran tetap lokal setelah `completed`, sama seperti Receiving normal; belum ada alur pelepasan Hold menjadi GR kedua.
- Di luar fitur ini: migration `2026_08_21_084500_create_marketing_material_fulfillments_table` gagal pada MySQL kosong karena nama index melebihi 64 karakter, jadi `migrate` dari nol di MySQL tidak dapat selesai. Environment yang sudah berjalan tidak terdampak.
