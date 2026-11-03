# Request Sourcing melalui Employee App

| Atribut | Nilai |
|---|---|
| Domain | Purchasing |
| Status | implemented; rollout dan UAT staging belum dijalankan |
| Owner | Purchasing |
| Technical owner | Information Technology |
| Modul terdampak | Employee App, Purchasing, Research & Development |
| Target release | TBD |

## Ringkasan

Fitur ini menyediakan tile **Request Sourcing** di Employee App agar user dapat meminta pencarian supplier untuk satu produk aktif Master Product ESB. Form mengikuti bahasa visual Request ERP, sedangkan pemilihan produk memakai bottom sheet mobile yang sama dengan Receiving Tanpa PO dalam mode tanpa informasi stok. Request masuk ke antrean Purchasing terlebih dahulu dan baru menjadi sourcing mandiri existing setelah Purchasing melengkapi supplier/sample; request user tidak langsung dikirim ke review R&D.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | implemented | Sumber requirement canonical; keputusan Phase 0 di §29 |
| [Implementation plan](implementation-plan.md) | implemented | Urutan teknis, migration, UI, conversion, dan quality gate |
| [Rollout](rollout.md) | draft | Deployment, permission, UAT, monitoring, dan rollback |

## Keputusan

| Keputusan | Status |
|---|---|
| Satu request hanya untuk satu Product Detail ESB | Dikunci dalam PRD |
| Request user tidak langsung membuat record sourcing atau masuk review R&D | Dikunci dalam PRD |
| Supplier, harga, MOQ, lead time, dan sample dilengkapi Purchasing saat conversion | Dikunci dalam PRD |
| Antrean canonical berada di Purchasing → Permintaan Sourcing | Dikunci dalam PRD |
| Sourcing hasil conversion tetap memakai workflow sourcing mandiri existing | Dikunci dalam PRD |
| Form mengikuti Request ERP; product picker reuse Receiving Tanpa PO tanpa stok | Dikunci dalam PRD |
| Lampiran maksimal 5 file, 10 MB per file, JPG/JPEG/PNG/WEBP/PDF | Dikunci pada Phase 0 (PRD §29) |
| Revisi, tolak, dan Buat Sourcing hanya oleh PIC yang Mulai Proses | Dikunci pada Phase 0 (PRD §29) |
| Permission baru tidak diberikan ke role mana pun oleh seeder | Dikunci pada Phase 0 (PRD §29) |

## Laporan

| Laporan | Tanggal |
|---|---|
| [Laporan implementasi](reports/implementation-report.md) | 2026-11-03 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-03 | implemented | Implementasi selesai pada branch fitur; UAT staging dan rollout belum dijalankan |
| 2026-11-03 | draft | Planning dibuat berdasarkan audit Employee App Request ERP, Receiving Tanpa PO, dan sourcing mandiri existing |
