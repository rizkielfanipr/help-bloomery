# Stock Card Draft Workflow

| Atribut | Nilai |
|---|---|
| Domain | Inventory |
| Status | in-development |
| Owner | Inventory |
| Technical owner | Information Technology |
| Modul terdampak | Employee App Stock Card |
| Target release | TBD |

## Ringkasan

Penyempurnaan workflow Stock Card agar staff dapat mencicil penghitungan stok melalui draft yang persisten, melihat progress dan status penyimpanan, lalu mengirim laporan yang lengkap untuk review Supervisor. Qty yang belum dihitung tetap kosong (`null`), sedangkan nilai `0` hanya berarti stok fisik benar-benar nol.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | in-development | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | in-development | Urutan teknis, file terdampak, dan quality gate |

## Keputusan

| Keputusan | Status |
|---|---|
| Draft tetap memakai status `draft` existing | Disepakati |
| Qty belum dihitung disimpan sebagai `null`, bukan `0` | Disepakati |
| Tombol final staff bernama **Kirim untuk Review** | Disepakati |
| Draft berlaku bersama untuk satu branch dan tanggal laporan | Diterapkan sesuai default PRD §26 |
| Autosave tetap dipertahankan bersama tombol **Simpan Draft** | Diterapkan |

## Laporan

| Laporan | Tanggal |
|---|---|
| [Laporan implementasi](reports/implementation-report.md) | 2 November 2026 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-02 | draft | Planning dibuat berdasarkan audit workflow Stock Card existing |
| 2026-11-02 | in-development | Kode selesai di Pull Request; menunggu review, CI, dan UAT staging |
