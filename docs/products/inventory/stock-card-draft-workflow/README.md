# Stock Card Draft Workflow

| Atribut | Nilai |
|---|---|
| Domain | Inventory |
| Status | draft |
| Owner | Inventory |
| Technical owner | Information Technology |
| Modul terdampak | Employee App Stock Card |
| Target release | TBD |

## Ringkasan

Penyempurnaan workflow Stock Card agar staff dapat mencicil penghitungan stok melalui draft yang persisten, melihat progress dan status penyimpanan, lalu mengirim laporan yang lengkap untuk review Supervisor. Qty yang belum dihitung tetap kosong (`null`), sedangkan nilai `0` hanya berarti stok fisik benar-benar nol.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | draft | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | draft | Urutan teknis, file terdampak, dan quality gate |
| [Implementation prompt](implementation-prompt.md) | draft | Instruksi siap pakai untuk agent implementasi |

## Keputusan

| Keputusan | Status |
|---|---|
| Draft tetap memakai status `draft` existing | Disepakati |
| Qty belum dihitung disimpan sebagai `null`, bukan `0` | Disepakati |
| Tombol final staff bernama **Kirim untuk Review** | Disepakati |
| Draft berlaku bersama untuk satu branch dan tanggal laporan | Rekomendasi untuk review PRD |
| Autosave tetap dipertahankan bersama tombol **Simpan Draft** | Rekomendasi untuk review PRD |

## Laporan

| Laporan | Tanggal |
|---|---|
| Belum ada — implementasi belum dimulai | — |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-02 | draft | Planning dibuat berdasarkan audit workflow Stock Card existing |
