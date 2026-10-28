# Item Journal Inventory

| Atribut | Nilai |
|---|---|
| Domain | Inventory |
| Status | draft |
| Owner | Inventory |
| Technical owner | Information Technology |
| Modul terdampak | Employee App, Inventory, Quality Control, ESB Core |
| Target release | TBD |

## Ringkasan

Item Journal akan menjadi fitur Inventory yang berdiri sendiri. Employee App menampilkan tile **Item Journal** terpisah agar staff store dapat membuat penyesuaian stok tanpa masuk ke tile Quality Control, sedangkan rekapan Back Office dipindahkan dari grup **Quality Control** ke **Inventory**. Implementasi mempertahankan tabel, model, route, nomor jurnal, dan riwayat yang sudah ada agar data lama tetap aman.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | draft | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | draft | Urutan teknis dan quality gate |
| [Implementation prompt](implementation-prompt.md) | draft | Instruksi implementasi end-to-end untuk agent/developer |

## Keputusan

| Keputusan | Status |
|---|---|
| Model dan tabel `quality_control_item_journal*` tidak diganti pada rollout ini | Disepakati dalam PRD untuk menjaga kompatibilitas data |
| Lampiran baru berupa gambar dari alur kamera; PDF dihapus dari input baru | Disepakati, dengan batasan browser dijelaskan dalam PRD |

## Laporan

| Laporan | Tanggal |
|---|---|
| — | — |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-10-28 | draft | PRD dan implementation plan dibuat berdasarkan audit kode saat ini |
