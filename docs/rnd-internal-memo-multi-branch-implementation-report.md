# Laporan Implementasi Memo Internal R&D Multi-Branch

Tanggal implementasi: 1 Oktober 2026  
Acuan: `docs/rnd-internal-memo-multi-branch-prd.md`

## Status phase

| Phase | Status | Hasil |
| --- | --- | --- |
| 0 — Audit dan kontrak | Selesai | Kontrak Master Menu, mapping branch, credential, akses, TTL, dan strategi data existing dicatat pada laporan Phase 0. |
| 1 — Safety net | Selesai | Characterization test dan penjaga HTTP eksternal tersedia. Baseline diperbarui ketika kontrak multi-branch resmi diterapkan. |
| 2 — Schema dan domain | Selesai | Memo–Branch, Menu–Branch, snapshot mapping, resolver sumber bersama Stock Card, model, factory, dan constraint tersedia. |
| 3 — Form dan Policy | Selesai | Create/edit memakai multi-select Branch Tujuan, validasi akses berjalan server-side, dan Memo dapat dilihat oleh pengguna yang mempunyai akses minimal ke satu branch. |
| 4 — Katalog lokal | Selesai | Katalog disinkronkan oleh job unik per Company/Branch, snapshot lokal dipertahankan saat gagal, dan picker tidak melakukan HTTP ESB. |
| 5 — Merge dan rekonsiliasi | Selesai | Menu digabung berdasarkan Company Code + Menu ID, daftar branch asal tersimpan, dan penghapusan branch ditolak bila membuat Menu yatim. |
| 6 — BOM/Product multi-company | Selesai | BOM, Assembly, output conversion, dan Product detail memakai Company Code Menu. Cache BOM terisolasi per Company Code. |
| 7 — UI dan observability | Selesai | Detail Memo menampilkan Branch Tujuan dan status sinkronisasi, picker memiliki filter Branch/Company/Code/Name, pagination, loading/error state, serta job mencatat durasi dan jumlah Menu tanpa credential. |
| 8 — Existing dan cleanup | Selesai | Memo lama tanpa branch tetap dapat dibuka dengan status “Perlu Menentukan Branch”. Jalur picker BLSS lama, fallback Branch API, dan konfigurasi representative branch dihapus. |

## Perilaku akhir

1. Pengguna memilih satu atau beberapa Branch Tujuan dari Master Branch lokal.
2. Sistem menyimpan snapshot Company Code dan Branch Code setiap branch.
3. Job queue mengambil seluruh halaman Master Menu memakai static token Company Code terkait.
4. Picker membaca snapshot database, sehingga pencarian dan pagination tidak menunggu ESB.
5. Menu yang sama pada beberapa branch dalam Company Code yang sama tampil sekali dan menyimpan seluruh branch asal.
6. BOM Menu, BOM Assembly/WIP, dan Product detail memakai credential ESB Core dari Company Code Menu.
7. Snapshot katalog lama tidak dihapus ketika refresh gagal.
8. Memo existing tidak diberi branch hasil tebakan; pengguna harus menentukan Branch Tujuan saat mengedit.

## Perubahan data dan operasi

- Tabel baru `rnd_internal_memo_menu_catalogs` menyimpan snapshot per Company Code, Branch Code, dan Menu ID.
- Job `SyncInternalMemoMenuCatalogJob` membutuhkan queue worker aktif.
- Sinkronisasi dijadwalkan setelah create Memo, ketika branch baru ditambahkan, atau ketika snapshot berumur lebih dari 15 menit.
- Static token diperlukan untuk setiap Company Code yang boleh dipakai pada Memo.
- Credential ESB Core diperlukan untuk Company Code yang akan dipakai mengambil BOM dan Product.

## Validasi

- Test terfokus seluruh domain Memo Internal: **153 test lulus, 491 assertion**.
- Test terfokus perubahan multi-branch dan ESB Product: **102 test lulus, 349 assertion**.
- Full suite dengan memory limit 512 MB: **1.242 test lulus, 6.115 assertion**.
- Laravel Pint dijalankan pada file PHP yang berubah.
- Seluruh HTTP pada test memakai fake dan penjaga stray request.

## Deployment

1. Backup database.
2. Pull commit release.
3. Jalankan `php artisan migrate --force`.
4. Pastikan static token dan credential ESB Core tersedia untuk Company Code yang digunakan.
5. Pastikan queue worker aktif, lalu restart worker dengan `php artisan queue:restart`.
6. Jalankan `php artisan optimize:clear`.
7. Smoke test Memo satu branch, multi-branch, Menu lintas branch, dan satu branch dengan ESB gagal.

## Rollback

- Rollback aplikasi dapat dilakukan tanpa menghapus snapshot baru; schema bersifat tambahan.
- Hentikan job katalog baru bila ESB bermasalah.
- Jangan menjalankan migration `down` pada tabel katalog sebelum aplikasi lama aktif dan data telah dibackup.
- Relasi branch dan snapshot dapat dipertahankan untuk investigasi meskipun UI multi-branch dinonaktifkan.

## Batasan operasional

- Memo lama tidak otomatis mendapat Branch Tujuan karena branch historis tidak dapat dibuktikan.
- Branch tanpa **Sumber Stock Card & Memo Internal** yang aktif atau tanpa token tetap tidak dapat dipilih. Sistem tidak menebak mapping lain.
- Sinkronisasi katalog bersifat asynchronous; sesaat setelah membuat Memo, picker dapat menampilkan status sedang disinkronkan.
