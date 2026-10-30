# Item Journal Inventory

| Atribut | Nilai |
|---|---|
| Domain | Inventory |
| Status | implemented |
| Owner | Inventory |
| Technical owner | Information Technology |
| Modul terdampak | Employee App, Inventory, Quality Control, ESB Core |
| Target release | TBD |

## Ringkasan

Item Journal adalah fitur Inventory yang berdiri sendiri. Employee App menampilkan tile **Item Journal** terpisah agar staff store dapat membuat penyesuaian stok tanpa masuk ke tile Quality Control, sedangkan rekapan Back Office berada di grup **Inventory**. Lampiran baru berupa foto camera-first (JPEG/PNG/WebP). Perilaku saat ini dijelaskan di [README Inventory](../README.md#item-journal). Implementasi mempertahankan tabel, model, route, nomor jurnal, dan riwayat yang sudah ada agar data lama tetap aman.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | implemented | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | implemented | Urutan teknis dan quality gate |

## Keputusan

| Keputusan | Status |
|---|---|
| Model dan tabel `quality_control_item_journal*` tidak diganti pada rollout ini | Disepakati dalam PRD untuk menjaga kompatibilitas data |
| Lampiran baru berupa gambar dari alur kamera; PDF dihapus dari input baru | Disepakati, dengan batasan browser dijelaskan dalam PRD |
| Branch scope view-all tidak diubah karena journal tidak menyimpan branch lokal | Diputuskan saat implementasi (PRD §16.5) |

## Laporan

| Laporan | Tanggal |
|---|---|
| [Laporan implementasi](reports/implementation-report.md) | 2026-10-28 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-10-28 | draft | PRD dan implementation plan dibuat berdasarkan audit kode saat ini |
| 2026-10-28 | implemented | Tile mandiri, foto camera-first image-only, dan menu Back Office Inventory; UAT perangkat menunggu staging |
