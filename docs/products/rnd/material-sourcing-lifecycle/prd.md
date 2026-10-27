# PRD — Workflow Master Bahan, Sourcing Sampel, dan Pricelist

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Modul utama | R&D, Purchasing, IT, dan Finance |
| Fitur | Workflow Master Bahan sampai Pricelist dan Completed |
| Baseline | 21 Oktober 2026 |
| Status | Implemented (diverifikasi terhadap kode 27 Oktober 2026). Status awal: Draft untuk review; belum mengotorisasi implementasi, mutation ESB production, migration production, atau deployment |
| Target stack | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Tailwind CSS 4, Pest 4 |
| Referensi arsitektur | `docs/standards/code-remediation.md` |
| Referensi UI | `docs/standards/ui-consistency.md` |

Dokumen ini mendefinisikan alur final pembuatan Master Bahan oleh R&D, review wajib oleh IT sebelum data dikirim ke ESB, sourcing dan serah-terima sampel oleh Purchasing, approval sampel oleh R&D, serta input Pricelist oleh Finance. WIP mempunyai jalur khusus tanpa sourcing dan sampel.

PRD ini tidak mengizinkan penghapusan data lama, perubahan data production, pengiriman produk atau Pricelist ke ESB production, penambahan dependency, atau deployment. Kondisi repository, kontrak API ESB, permission, queue, storage, dan data existing wajib diaudit kembali sebelum implementasi setiap phase.

## 2. Ringkasan keputusan produk

1. R&D membuat Master Bahan atau Master WIP di aplikasi internal terlebih dahulu.
2. Produk tidak boleh dipush atau ditautkan ke ESB sebelum melewati review IT.
3. Saat R&D mengirim pengajuan untuk review, sistem mengirim notifikasi Telegram kepada tim IT.
4. Selama review, IT dapat memperbaiki field teknis dengan audit trail atau mengembalikan pengajuan kepada R&D.
5. Hanya IT atau pengguna dengan permission khusus yang dapat menyetujui dan memulai push/tautkan produk ke ESB.
6. Setelah sinkronisasi terverifikasi, Product ID dan Product Detail ID setiap UOM disimpan secara eksplisit.
7. Setelah Product Detail ID tersedia, R&D dapat memakai bahan tersebut pada resep/BOM tanpa menunggu sourcing selesai.
8. Bahan biasa melanjutkan proses Purchasing, serah-terima sampel, konfirmasi penerimaan, dan approval R&D.
9. Hanya bahan biasa yang telah disetujui R&D yang masuk antrean `Finance → Pricelist Bahan`.
10. WIP tidak melewati sourcing atau sampel. WIP langsung masuk antrean Finance setelah sinkronisasi ESB terverifikasi.
11. Konfirmasi Pricelist oleh Finance sekaligus menjadi approval Finance; tidak diperlukan aksi approval Finance kedua yang identik.
12. Record hanya menjadi `Completed` setelah sinkronisasi ESB dan Pricelist terverifikasi, serta approval R&D tersedia untuk bahan yang membutuhkan sourcing.
13. Status ESB, status sourcing/sampel, dan status Pricelist disimpan terpisah agar kegagalan satu proses tidak merusak proses lainnya.
14. Seluruh mutation eksternal dan notifikasi harus retry-safe, tercatat, dan tidak menghasilkan data atau pesan ganda.

## 3. Latar belakang

R&D membutuhkan Product Detail ID ESB lebih awal agar bahan baru dapat digunakan saat menyusun resep atau BOM. Namun, input awal R&D kadang mengandung kesalahan pada kode, kategori, UOM, conversion factor, SKU, flag unit, atau identitas produk. Jika data langsung dikirim ke ESB, koreksi menjadi lebih sulit dan dapat menghasilkan Product Detail duplikat atau unit yang salah.

Karena itu, dibutuhkan tahap review IT sebelum push ESB. Pada tahap ini data masih berada di aplikasi internal sehingga dapat diperbaiki dengan aman. Setelah data dinyatakan benar, IT dapat menyetujui dan mengirim atau menautkan produk ke ESB.

Setelah identitas ESB tersedia, alur bercabang:

- bahan biasa menjalani sourcing, penyerahan sampel, penerimaan dan evaluasi R&D, lalu Pricelist Finance;
- WIP tidak menjalani sourcing dan langsung masuk antrean Pricelist Finance.

Notifikasi Telegram diperlukan agar permintaan review baru segera diketahui IT, tetapi aplikasi tetap menjadi sumber kebenaran status, keputusan, dan audit trail.

## 4. Temuan baseline repository

Temuan berikut merupakan kondisi kode saat baseline dan wajib diverifikasi ulang saat implementasi:

1. `RndProductEsbMaterial` sudah menyimpan identitas material, unit, status sinkronisasi, Product ID, Product Detail ID, payload, response, error, serta metadata sourcing.
2. Status sinkronisasi material existing meliputi `draft`, `syncing`, `synced`, `failed`, dan `unknown`/rekonsiliasi.
3. `MaterialSourcingStatus` existing hanya mencakup `NotStarted`, `PendingRndReview`, `PendingFinanceReview`, `Approved`, dan `Rejected`.
4. Sourcing supplier existing sudah menyimpan nama supplier, merek, harga penawaran, MOQ, lead time, kontak, catatan, lampiran, dan penginput.
5. Approval R&D dan Finance existing dilakukan langsung dari `MaterialSourcingResource`.
6. Push material ESB existing masih dijalankan dari halaman produk Project R&D dan menangani pencegahan klik ganda serta status hasil tidak pasti.
7. Sourcing mandiri existing dimulai dengan memilih produk yang sudah aktif di Master Product ESB. Alur ini merupakan sourcing ulang produk existing, bukan pembuatan Master Bahan baru.
8. `ProductPriceIndex` dan sinkronisasi harga Purchase Order existing membaca informasi harga, tetapi belum membuktikan tersedianya kontrak endpoint tulis Master Pricelist ESB.
9. Belum ditemukan implementasi Telegram pada baseline audit singkat ini. Integrasi baru harus dibuat melalui konfigurasi aman dan queue.
10. Halaman besar R&D sudah membawa banyak tanggung jawab. Workflow baru tidak boleh menambah transaksi bisnis kompleks atau HTTP call langsung ke Page/Resource.

Konsekuensi baseline:

- workflow existing harus diberi characterization test sebelum direstrukturisasi;
- data approval lama tidak boleh otomatis ditafsirkan sebagai bukti serah-terima sampel;
- sourcing mandiri untuk produk ESB existing harus tetap didukung, tetapi klasifikasinya dibedakan dari pengajuan Master Bahan baru;
- integrasi Pricelist melalui API baru boleh diaktifkan setelah kontrak endpoint resmi terbukti.

## 5. Masalah yang diselesaikan

1. Kesalahan input R&D berpotensi masuk ke ESB sebelum diperiksa.
2. Koreksi setelah produk dibuat di ESB dapat menghasilkan unit dan Product Detail duplikat.
3. Belum ada inbox review IT yang jelas sebelum push ESB.
4. IT belum mendapat notifikasi segera saat pengajuan baru masuk.
5. Status approval supplier, sinkronisasi ESB, sampel, dan harga masih berpotensi tercampur.
6. Bukti penyerahan dan penerimaan sampel belum menjadi lifecycle tersendiri.
7. Sampel ulang berisiko menimpa histori sampel lama.
8. Finance belum mempunyai satu antrean khusus untuk bahan siap diberi Pricelist.
9. WIP berpotensi dipaksa melewati sourcing meskipun tidak memerlukannya.
10. Belum ada aturan tunggal dan teruji untuk menentukan kapan record dapat menjadi `Completed`.
11. Mutation ESB dan notifikasi eksternal berisiko ganda apabila retry atau pengguna menekan tombol berulang.
12. Data lama berisiko salah diklasifikasikan apabila status baru dibackfill tanpa bukti.

## 6. Tujuan

- Memberi R&D ruang menyimpan dan merevisi Master Bahan sebelum dikirim ke ESB.
- Menjadikan review IT sebagai gerbang wajib dan dapat diaudit.
- Memberi IT alat untuk mendeteksi duplikasi, memperbaiki data teknis, dan menautkan produk existing.
- Menyediakan Product Detail ID lebih awal agar R&D dapat membuat resep/BOM.
- Menyediakan workflow sourcing dan sampel yang jelas untuk bahan biasa.
- Menyediakan jalur WIP yang lebih singkat tanpa sourcing.
- Menyediakan submenu Finance khusus untuk Pricelist Bahan.
- Membuat notifikasi Telegram andal tanpa menjadikannya sumber kebenaran.
- Memisahkan lifecycle internal, ESB, sourcing, dan pricing.
- Menjamin authorization server-side, audit trail, idempotency, dan reconciliation.
- Menjaga data existing dan route penting tetap kompatibel selama rollout bertahap.

## 7. Non-tujuan

Scope ini tidak mencakup:

- mengganti Filament/Livewire atau melakukan rewrite frontend;
- membuat microservice baru;
- menghapus Product Detail atau produk lama di ESB;
- mengubah conversion factor Product Detail existing secara langsung jika ESB tidak mendukungnya;
- menganggap harga penawaran supplier sebagai Pricelist resmi secara otomatis;
- membuat algoritma rekomendasi supplier atau harga;
- mengganti workflow pembuatan resep/BOM selain integrasi eligibility material;
- menghapus data approval/sourcing lama pada deployment pertama;
- mengaktifkan mutation Pricelist API sebelum endpoint dan kontraknya diverifikasi;
- menjadikan Telegram sebagai tempat approval;
- mengirim data supplier sensitif atau attachment melalui Telegram;
- mengaktifkan seluruh fitur langsung di production tanpa staging dan rollout bertahap.

## 8. Istilah domain

| Istilah | Definisi |
| --- | --- |
| Master Bahan | Data calon produk/material yang dibuat R&D sebelum atau setelah mempunyai identitas ESB |
| Bahan biasa | Raw Material, Packaging, Marketing, atau klasifikasi lain yang membutuhkan sourcing dan sampel |
| WIP | Bahan antara/hasil Assembly yang tidak menjalani sourcing supplier pada workflow ini |
| Review IT | Pemeriksaan wajib terhadap identitas dan struktur teknis produk sebelum push ESB |
| Link Existing | Menautkan record lokal ke produk ESB yang sudah ada dan terbukti cocok |
| ESB Sync Verified | Produk dapat dibaca kembali dari ESB dan identitas Product ID/Product Detail ID telah tersimpan |
| Sourcing Request | Permintaan kepada Purchasing untuk mencari atau mencatat supplier bahan biasa |
| Sample Cycle | Satu siklus penyerahan, penerimaan, dan evaluasi sampel; sampel ulang membuat siklus baru |
| Harga Penawaran | Harga kandidat dari supplier; bukan otomatis Pricelist resmi |
| Pricing Request | Antrean item yang telah memenuhi syarat untuk ditangani Finance |
| Pricelist Verified | Harga berhasil dibaca kembali dari sumber resmi atau diverifikasi sesuai mekanisme yang disetujui |
| Reconciliation | Proses IT menyelesaikan hasil mutation eksternal yang tidak pasti atau mapping yang tidak cocok |

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab |
| --- | --- |
| R&D | Membuat Master Bahan/WIP, memperbaiki revisi bisnis, menggunakan material pada resep, menerima dan mengevaluasi sampel |
| IT | Memeriksa master data teknis, memperbaiki field teknis, mendeteksi duplikasi, menyetujui push, menautkan produk existing, dan menangani rekonsiliasi |
| Purchasing | Mengelola kandidat supplier, data sourcing, dan bukti penyerahan sampel |
| Finance | Mengelola antrean Pricelist, menentukan harga resmi, mengirim atau memverifikasi Pricelist |
| Administrator | Mengelola permission, konfigurasi Telegram, monitoring queue, dan audit lintas proses |
| Sistem | Menjalankan validasi otomatis, transisi status, notifikasi, queue, integrasi ESB, histori, dan aturan Completed |

## 10. Alur end-to-end

### 10.1 Bahan biasa

1. R&D membuat Master Bahan sebagai `Draft`.
2. Sistem memvalidasi kelengkapan awal tanpa mengirim data ke ESB.
3. R&D menekan **Kirim untuk Review IT**.
4. Sistem mengunci versi yang diajukan, mencatat submitter dan waktu, lalu mengubah status review menjadi `Waiting for IT Review`.
5. Setelah transaksi database berhasil, sistem mengantrekan notifikasi Telegram kepada IT.
6. IT membuka inbox review dan memeriksa data.
7. IT memilih salah satu hasil:
   - memperbaiki field teknis dengan alasan;
   - mengembalikan kepada R&D sebagai `Revision Required`;
   - menautkan ke produk ESB existing;
   - menyetujui dan memulai push produk baru ke ESB.
8. Sistem menjalankan mutation idempoten dan membaca ulang hasil dari ESB.
9. Sistem menyimpan Product ID dan Product Detail ID setiap UOM, lalu menandai sinkronisasi `Synced` hanya jika hasil terverifikasi.
10. R&D dapat menggunakan material yang sudah `Synced` pada resep/BOM.
11. Sistem membuat Sourcing Request satu kali dan memberi tahu Purchasing melalui notifikasi internal. Telegram Purchasing dapat diaktifkan sebagai event terpisah bila disetujui.
12. Purchasing mengisi data supplier seperti workflow existing.
13. Purchasing memilih kandidat dan mencatat penyerahan sampel beserta foto.
14. R&D mencatat penerimaan sampel beserta foto.
15. R&D mengevaluasi sampel dan memilih `Approve`, `Request Resample`, atau `Reject`.
16. `Request Resample` membuat Sample Cycle baru tanpa menghapus siklus sebelumnya.
17. `Approve` membuat Pricing Request satu kali dan memasukkan material ke submenu Finance.
18. Finance mengisi serta mengonfirmasi Pricelist.
19. Sistem memverifikasi hasil pricing.
20. Sistem menandai workflow `Completed` apabila seluruh guard terpenuhi.

### 10.2 WIP

1. R&D membuat Master WIP sebagai `Draft`.
2. R&D mengirim WIP untuk review IT.
3. Sistem mengirim notifikasi Telegram kepada IT setelah transaksi berhasil.
4. IT memeriksa, memperbaiki/mengembalikan, menautkan, atau menyetujui push ESB.
5. Sistem memverifikasi Product ID dan Product Detail ID.
6. Setelah status `Synced`, sistem membuat Pricing Request untuk Finance satu kali.
7. WIP tidak membuat Sourcing Request, supplier candidate, Sample Cycle, atau approval sampel.
8. Finance mengisi serta memverifikasi Pricelist.
9. Sistem menandai WIP `Completed` ketika ESB dan pricing telah terverifikasi.

### 10.3 Sourcing mandiri produk existing

1. Pengguna memilih produk yang sudah aktif di ESB melalui picker existing.
2. Sistem memverifikasi ulang identitas produk dari ESB sebelum menyimpan.
3. Karena produk sudah mempunyai identitas ESB, workflow tidak membuat produk baru.
4. Permintaan melanjutkan sourcing, sampel, dan approval R&D sesuai kebutuhan.
5. Pricing Request hanya dibuat jika perubahan atau Pricelist baru memang diperlukan; aturan ini wajib ditentukan berdasarkan data produk existing dan bukan diasumsikan.

## 11. Status dan state machine

Status tidak boleh digabung menjadi satu enum besar. Minimal terdapat empat dimensi berikut.

### 11.1 Status review IT

| Status | Makna |
| --- | --- |
| `draft` | Masih dikerjakan R&D |
| `waiting_it_review` | Sudah diajukan dan menunggu IT |
| `under_it_review` | Sedang diperiksa IT |
| `revision_required` | Dikembalikan kepada R&D |
| `approved_for_esb` | Disetujui untuk link/push ESB |
| `review_cancelled` | Pengajuan dibatalkan sesuai izin dan belum dimutasi ke ESB |

Transisi utama:

```text
Draft → Waiting IT Review → Under IT Review
Under IT Review → Revision Required → Draft/Waiting IT Review
Under IT Review → Approved for ESB
```

### 11.2 Status sinkronisasi ESB

| Status | Makna |
| --- | --- |
| `not_ready` | Belum disetujui IT |
| `queued` | Mutation siap diproses |
| `syncing` | Sedang dikirim |
| `synced` | Hasil sudah dibaca balik dan terverifikasi |
| `failed` | Gagal deterministik dan aman diperbaiki/retry |
| `needs_reconciliation` | Hasil eksternal belum pasti atau mapping tidak cocok |

### 11.3 Status sourcing dan sampel

| Status | Makna |
| --- | --- |
| `not_required` | Digunakan untuk WIP |
| `waiting_purchasing` | Permintaan sourcing tersedia |
| `sourcing_in_progress` | Supplier sedang dikelola |
| `sample_prepared` | Sampel siap diserahkan |
| `sample_handed_over` | Purchasing telah menyerahkan sampel |
| `sample_received` | R&D telah mengonfirmasi penerimaan |
| `under_rnd_review` | Sampel sedang dievaluasi |
| `resample_requested` | Diperlukan Sample Cycle baru |
| `approved_by_rnd` | Sampel dan supplier terpilih disetujui |
| `rejected_by_rnd` | Sampel ditolak |

### 11.4 Status Pricelist

| Status | Makna |
| --- | --- |
| `not_ready` | Belum memenuhi guard Finance |
| `ready_for_pricing` | Sudah masuk antrean Finance |
| `draft_pricing` | Finance sedang mengisi harga |
| `submitting` | Sedang dikirim ke sumber resmi |
| `verification_required` | Hasil eksternal belum dapat dipastikan |
| `priced` | Harga resmi telah terverifikasi |
| `failed` | Gagal deterministik dan dapat diperbaiki |

### 11.5 Status workflow keseluruhan

Status ringkas untuk tampilan dapat dihitung dari dimensi di atas. `Completed` bukan status yang boleh dipilih manual.

Guard bahan biasa:

```text
esb_sync_status = synced
AND sourcing_status = approved_by_rnd
AND pricing_status = priced
```

Guard WIP:

```text
esb_sync_status = synced
AND sourcing_status = not_required
AND pricing_status = priced
```

## 12. Kebutuhan Master Bahan dan review IT

### 12.1 Field minimum Master Bahan

- jenis/section material;
- nama produk;
- kode produk;
- kategori dan subkategori ESB;
- catatan/spesifikasi;
- daftar UOM;
- satu base UOM;
- purchasing UOM bila relevan;
- conversion factor;
- SKU unik per unit;
- flag Stock, Purchase, Transfer, Sales, dan Base;
- status aktif unit;
- pembuat dan waktu pembuatan.

### 12.2 Validasi otomatis sebelum submit review

- field wajib lengkap;
- tepat satu base UOM;
- conversion factor base bernilai `1`;
- UOM dan SKU tidak duplikat dalam satu produk;
- SKU tidak kosong;
- kode mengikuti pola kategori yang berlaku;
- tidak terdapat row unit kosong;
- jenis bahan menentukan apakah sourcing diperlukan;
- pencarian kandidat duplikasi lokal dilakukan sebelum submit.

Validasi ini membantu, tetapi tidak menggantikan review IT.

### 12.3 Checklist IT

IT memeriksa:

- kesesuaian nama dan kode;
- kategori/subkategori;
- base UOM dan purchasing UOM;
- conversion factor;
- SKU;
- flag setiap unit;
- kemungkinan produk/kode/SKU existing;
- kemungkinan produk dengan nama sama tetapi kode berbeda;
- konsistensi payload terhadap aturan ESB;
- apakah produk harus ditautkan atau dibuat baru.

### 12.4 Hak koreksi IT

IT boleh mengubah field teknis seperti kategori, subkategori, UOM, SKU, conversion factor, dan flag dengan syarat:

- alasan perubahan wajib diisi;
- nilai sebelum dan sesudah dicatat;
- actor dan waktu dicatat;
- perubahan terlihat oleh R&D.

Perubahan spesifikasi bisnis, nama bahan yang mengubah makna, atau klasifikasi kebutuhan sourcing sebaiknya dikembalikan kepada R&D melalui `Revision Required`.

### 12.5 Penguncian

- R&D dapat mengedit saat `draft` dan `revision_required`.
- Setelah submit, field utama read-only bagi R&D.
- IT dapat mengambil review secara eksplisit agar dua reviewer tidak mengedit bersamaan.
- Gunakan optimistic locking atau versi record untuk mencegah approval terhadap data yang berubah.
- Setelah `synced`, perubahan identitas utama tidak boleh memakai edit biasa.

## 13. Integrasi ESB

### 13.1 Prinsip

- Seluruh HTTP ESB melalui Integration Service/client terpusat.
- Page, Resource, dan Blade tidak melakukan HTTP call langsung.
- Mutation mempunyai timeout, correlation ID, logging aman, dan idempotency key.
- Retry hanya dilakukan untuk kondisi yang terbukti aman.
- Timeout setelah request terkirim menghasilkan `needs_reconciliation`, bukan push ulang otomatis.
- Response mentah disanitasi sebelum disimpan atau ditampilkan.

### 13.2 Link existing sebelum create

Sebelum create produk:

1. cari kandidat berdasarkan kode exact;
2. cek SKU dan UOM;
3. cek nama exact/normalized sebagai indikator, bukan satu-satunya identitas;
4. tampilkan kandidat kepada IT;
5. link hanya setelah IT mengonfirmasi kecocokan.

Nama produk saja tidak cukup untuk menautkan record.

### 13.3 Verifikasi hasil

Status `synced` hanya diberikan jika:

- Product ID valid;
- produk dapat dibaca kembali;
- setiap unit lokal yang aktif dapat dipetakan ke satu Product Detail ID;
- tidak ada dua unit lokal yang memetakan detail aktif yang sama;
- SKU/UOM yang dibaca kembali sesuai aturan mapping.

### 13.4 Perubahan conversion factor

Jika ESB tidak mengizinkan perubahan conversion factor pada Product Detail existing:

- unit lama dinonaktifkan melalui prosedur resmi;
- unit pengganti dibuat sebagai detail baru;
- mapping lokal diperbarui setelah verifikasi;
- histori detail lama dipertahankan;
- resep/transaksi yang memakai detail lama dianalisis sebelum perubahan.

Fitur tidak boleh sekadar menghapus Product Detail ID lokal lalu membuat ulang tanpa audit.

## 14. Notifikasi Telegram

### 14.1 Trigger wajib

Notifikasi dikirim setelah transaksi **Kirim untuk Review IT** berhasil commit.

Notifikasi tidak dikirim ketika:

- pengguna hanya menyimpan Draft;
- validasi gagal;
- transaction rollback;
- WIP/material yang sama masih mempunyai pengajuan review aktif dengan event yang sama.

### 14.2 Isi pesan

Pesan minimum:

- judul `Review Master Bahan Baru` atau `Review Master WIP Baru`;
- nama dan kode bahan;
- jenis/section;
- ringkasan UOM;
- nama pengaju;
- waktu pengajuan dalam WIB;
- tautan menuju detail review IT.

Nomor telepon supplier, harga rahasia, attachment, credential, dan payload ESB tidak dikirim melalui Telegram.

### 14.3 Keandalan

- event dibuat setelah database commit;
- pengiriman dilakukan queued job;
- setiap event memiliki idempotency key;
- delivery log menyimpan status `pending`, `sent`, atau `failed`;
- retry memakai backoff dan batas percobaan;
- kegagalan Telegram tidak mengubah status review;
- admin dapat melihat error aman dan melakukan retry terkontrol;
- token dan Chat ID berasal dari konfigurasi environment/config, bukan database terbuka atau source code.

### 14.4 Konfigurasi

Konfigurasi konseptual:

- Telegram enabled;
- Bot Token;
- IT Review Chat ID;
- optional Purchasing Chat ID;
- timeout;
- queue connection.

Nama environment final mengikuti convention konfigurasi aplikasi dan tidak dikunci oleh PRD ini.

## 15. Sourcing Purchasing

### 15.1 Data supplier existing yang dipertahankan

- nama supplier;
- merek;
- harga penawaran;
- MOQ;
- lead time;
- nama dan telepon kontak;
- catatan;
- lampiran penawaran;
- penginput dan waktu input.

### 15.2 Aturan sourcing

- Sourcing Request hanya dibuat setelah ESB sync terverifikasi untuk bahan biasa.
- Satu material hanya memiliki satu Sourcing Request aktif untuk satu workflow version.
- Banyak kandidat supplier boleh ditambahkan.
- Satu kandidat/sample dipilih untuk proses evaluasi, tetapi histori kandidat lain tidak dihapus.
- Harga penawaran tidak otomatis menjadi harga Pricelist.
- WIP selalu mempunyai sourcing status `not_required`.

## 16. Serah-terima dan evaluasi sampel

### 16.1 Sample Cycle

Satu Sample Cycle menyimpan:

- nomor urut siklus;
- supplier/candidate terpilih;
- jumlah dan UOM sampel;
- catatan Purchasing;
- waktu penyerahan;
- pengguna yang menyerahkan;
- foto penyerahan;
- waktu penerimaan;
- pengguna R&D penerima;
- foto penerimaan;
- kondisi saat diterima;
- hasil evaluasi;
- alasan reject atau resample;
- reviewer dan waktu review.

### 16.2 Penyerahan Purchasing

Purchasing hanya dapat menyerahkan sampel apabila kandidat supplier valid telah dipilih. Foto penyerahan, jumlah, UOM, dan waktu wajib tersedia.

### 16.3 Konfirmasi R&D

R&D mengonfirmasi penerimaan dengan foto sendiri. Foto penyerahan Purchasing tidak boleh otomatis dianggap sebagai bukti penerimaan R&D.

### 16.4 Hasil evaluasi

- `Approve`: supplier/sample terpilih disetujui dan Pricing Request dibuat.
- `Request Resample`: alasan wajib; sistem membuat siklus baru dan memberi tahu Purchasing.
- `Reject`: alasan wajib; tidak membuat Pricing Request.

Approval dapat dibuka ulang hanya oleh permission khusus, dengan alasan dan histori. Reopen membatalkan eligibility pricing yang belum diproses; jika pricing sudah berjalan, sistem wajib meminta penyelesaian konflik oleh Finance/administrator.

## 17. Submenu Finance — Pricelist Bahan

### 17.1 Lokasi dan tujuan

Tambahkan submenu:

```text
Finance
└── Pricelist Bahan
```

Halaman ini menjadi antrean kerja Finance, bukan pengganti `Product Price Index`. Product Price Index tetap menjadi analitik harga historis, sedangkan Pricelist Bahan menangani workflow pemberian harga resmi.

### 17.2 Eligibility

Bahan biasa masuk apabila:

- ESB sync `synced`;
- sourcing/sample `approved_by_rnd`;
- Product Detail target tersedia;
- belum mempunyai Pricing Request aktif yang sama.

WIP masuk apabila:

- terklasifikasi sebagai WIP;
- ESB sync `synced`;
- Product Detail target tersedia;
- belum mempunyai Pricing Request aktif yang sama.

### 17.3 Kolom index

- jenis material;
- kode;
- nama bahan/WIP;
- asal `Sourcing` atau `WIP`;
- supplier terpilih bila ada;
- UOM target;
- Product Detail ID;
- harga penawaran sebagai referensi;
- harga Pricelist;
- company/branch scope;
- status pricing;
- tanggal masuk antrean;
- waktu terakhir diperbarui;
- aksi.

### 17.4 Filter

- status pricing;
- jenis/section material;
- asal request;
- kategori;
- supplier;
- company/branch bila berlaku;
- rentang tanggal;
- pencarian kode atau nama.

Default view menampilkan `ready_for_pricing`, `verification_required`, dan `failed` yang masih membutuhkan tindakan.

### 17.5 Form pricing

Field minimum, menunggu kontrak ESB:

- product dan Product Detail read-only;
- UOM read-only;
- harga;
- company;
- branch/cakupan jika Pricelist bersifat per branch;
- tanggal mulai berlaku jika didukung;
- catatan Finance.

Finance tidak boleh mengganti Product Detail ID dari form harga. Jika mapping salah, request dikembalikan ke IT.

## 18. Strategi integrasi Pricelist

### 18.1 Gate sebelum implementasi API

Tim wajib memperoleh dan menguji kontrak resmi untuk:

- endpoint create/update Pricelist;
- endpoint read-back/verifikasi;
- authentication dan company context;
- identitas Product Detail;
- cakupan company/branch;
- tanggal berlaku dan aturan overlap;
- idempotency atau external reference;
- perilaku update versus create;
- error dan rate limit.

Keberadaan menu Master Pricelist di ESB tidak otomatis membuktikan tersedianya endpoint mutation.

### 18.2 Mode manual terkontrol

Jika endpoint tulis belum tersedia:

1. Finance menyimpan draft harga di aplikasi.
2. Finance menginput harga ke ESB melalui prosedur operasional.
3. Aplikasi membaca kembali harga jika endpoint read tersedia, atau meminta konfirmasi manual yang mencatat actor dan bukti.
4. Status `priced` hanya diberikan setelah verifikasi.

### 18.3 Mode API

Jika endpoint terbukti tersedia:

1. simpan draft lokal;
2. validasi ulang eligibility;
3. claim request secara atomik;
4. kirim mutation melalui queued job/service;
5. simpan correlation/external reference;
6. baca kembali hasil;
7. cocokkan Product Detail, harga, scope, dan tanggal;
8. tandai `priced` jika cocok;
9. gunakan `verification_required` saat hasil tidak pasti.

## 19. Model data konseptual

Nama tabel final mengikuti audit schema dan convention existing. Struktur konseptual minimum:

### 19.1 Review IT

- material ID;
- submission version;
- status;
- submitted by/at;
- claimed by/at;
- reviewed by/at;
- decision;
- reason;
- snapshot data saat submit.

### 19.2 Change history

- material ID;
- review ID;
- field/path;
- value before/after yang disanitasi;
- actor;
- reason;
- timestamp.

### 19.3 Sample cycle

- material/sourcing request ID;
- cycle number;
- supplier candidate ID;
- handover fields;
- receipt fields;
- evaluation fields;
- status.

Attachment dapat disimpan pada tabel attachment reusable apabila pola existing mendukung lifecycle dan authorization yang dibutuhkan.

### 19.4 Pricing request

- material ID;
- Product Detail ID;
- UOM snapshot;
- origin `sourcing` atau `wip`;
- supplier and offer snapshot opsional;
- scope company/branch;
- price;
- effective date;
- status;
- submitted/reviewed metadata;
- payload/response tersanitasi;
- external reference;
- idempotency key;
- timestamps.

### 19.5 Notification delivery

- event type;
- aggregate type/ID;
- destination key, bukan token;
- idempotency key unik;
- status;
- attempts;
- sent at;
- error summary tersanitasi.

## 20. Authorization dan permission

Permission konseptual:

- create/edit Master Bahan;
- submit Master Bahan for IT review;
- review Master Bahan as IT;
- edit technical Master Bahan;
- approve/push Master Bahan to ESB;
- reconcile Master Bahan ESB;
- manage material sourcing;
- hand over material sample;
- receive material sample;
- review material sample as R&D;
- reopen material sample approval;
- view material pricing queue;
- manage material Pricelist;
- retry/verify Pricelist integration;
- view workflow audit log.

Semua mutation memanggil Policy/authorization server-side. Visibility tombol bukan kontrol keamanan. Record scope mengikuti company/branch/module access existing dan harus diuji untuk admin serta role non-admin.

## 21. Arsitektur aplikasi

Mengikuti `docs/standards/code-remediation.md`:

```text
Filament Page / Resource
    → authorization + presentation validation
    → use-case Action
        → workflow/state transition service
        → database transaction
        → domain event after commit
            → Telegram notification job
            → ESB sync/pricing job
        → integration service/client
```

Action kandidat:

- SubmitMaterialForItReviewAction;
- ClaimMaterialReviewAction;
- ReturnMaterialForRevisionAction;
- AmendMaterialTechnicalDataAction;
- ApproveMaterialForEsbAction;
- LinkMaterialToExistingEsbProductAction;
- SyncMaterialToEsbAction;
- ReconcileMaterialEsbSyncAction;
- CreateMaterialSourcingRequestAction;
- HandOverMaterialSampleAction;
- ConfirmMaterialSampleReceiptAction;
- ReviewMaterialSampleAction;
- RequestMaterialResampleAction;
- CreateMaterialPricingRequestAction;
- SubmitMaterialPricelistAction;
- VerifyMaterialPricelistAction;
- CompleteMaterialWorkflowAction.

Nama final boleh menyesuaikan convention sibling class. Tidak perlu membuat interface, repository, DTO, atau event untuk setiap operasi tanpa kebutuhan nyata.

## 22. UI/UX

Implementasi mengikuti `docs/standards/ui-consistency.md` dan pola existing R&D Project Workspace serta halaman Finance.

### 22.1 R&D Master Bahan

- progress ringkas: `Draft → Review IT → ESB → Sourcing/Sample → Pricelist → Completed`;
- field dikelompokkan menurut identitas, klasifikasi, dan unit;
- error berada dekat field;
- status review, sync, sourcing, dan pricing ditampilkan terpisah;
- tombol submit menjelaskan bahwa data akan dikunci selama review;
- tidak memakai shadow pada section biasa.

### 22.2 Inbox review IT

- tabel server-side dengan filter status, jenis, pengaju, dan tanggal;
- default menunjukkan request yang menunggu atau perlu rekonsiliasi;
- detail menampilkan diff, checklist, kandidat duplikasi, unit, dan histori;
- workflow panjang memakai halaman detail, bukan modal bertumpuk;
- aksi destructive/return meminta alasan yang jelas.

### 22.3 Sourcing dan sampel

- data supplier existing dipertahankan;
- serah-terima dan penerimaan menjadi section terpisah;
- thumbnail foto dan metadata terlihat;
- setiap Sample Cycle tampil sebagai timeline/history yang dapat dibuka;
- mobile menyusun section vertikal tanpa horizontal page scroll.

### 22.4 Finance Pricelist Bahan

- index mengikuti pola Finance existing;
- tabel full-width, pagination server-side, dan filter di atas konten;
- harga penawaran diberi label referensi agar tidak dianggap harga resmi;
- Product Detail dan UOM tidak dapat diedit dari form harga;
- status pending, verification, failed, dan priced mempunyai label serta warna konsisten;
- loading dan double-submit protection wajib tersedia.

### 22.5 Status visual

- draft/netral: gray;
- menunggu/revisi/verification: amber;
- syncing/informasi: blue;
- berhasil/approved/priced/completed: green;
- rejected/failed: red.

Warna selalu disertai teks status dari enum/presenter terpusat.

## 23. Attachment dan keamanan file

- Foto sampel dan lampiran supplier disimpan private.
- Akses memakai temporary URL setelah authorization.
- Validasi MIME, ekstensi, ukuran, dan jumlah file dilakukan server-side.
- Simpan uploader, waktu, path, nama asli, MIME, dan ukuran.
- Jangan mempercayai filename untuk menentukan tipe file.
- Penghapusan/replacement mengikuti kebijakan lifecycle; histori approval tidak kehilangan bukti.
- Telegram tidak mengirim attachment sensitif secara langsung.

## 24. Notifikasi internal

Selain Telegram, aplikasi menyediakan notifikasi internal untuk:

- pengajuan review baru kepada IT;
- revision required kepada R&D;
- sinkronisasi berhasil/gagal kepada R&D dan IT;
- Sourcing Request baru kepada Purchasing;
- sampel diserahkan kepada R&D;
- resample kepada Purchasing;
- approval sampel dan Pricing Request baru kepada Finance;
- pricing gagal atau perlu verifikasi kepada Finance/IT;
- workflow completed kepada pemilik proses terkait.

Notifikasi tidak menggantikan daftar kerja dan tidak boleh menjadi satu-satunya cara menemukan request.

## 25. Penanganan kondisi khusus

| Kondisi | Perilaku |
| --- | --- |
| Produk ESB sudah ada | IT menautkan setelah memastikan identitas cocok; tidak membuat produk baru |
| Kandidat duplikasi ambigu | Masuk rekonsiliasi; push diblokir |
| ESB timeout setelah mutation | Status `needs_reconciliation`; jangan retry create otomatis |
| Product Detail sebagian tidak ditemukan | Belum `synced`; resep/pricing hanya memakai detail yang benar-benar terverifikasi sesuai guard |
| Dua IT membuka request sama | Claim/version check mencegah keputusan saling menimpa |
| R&D mengubah data setelah submit | Ditolak oleh version/lock; harus return/revision flow |
| Telegram gagal | Request review tetap aktif; delivery dapat retry |
| Purchasing meminta sampel ulang | Sample Cycle baru; histori lama tetap ada |
| R&D menolak sampel | Tidak masuk Finance |
| WIP | Tidak membuat sourcing/sample; langsung pricing setelah sync |
| Mapping Product Detail salah saat pricing | Finance mengembalikan ke IT; tidak mengganti ID sendiri |
| Pricelist API timeout | `verification_required`; baca balik sebelum retry |
| Harga direvisi | Buat revisi/version baru; histori harga lama dipertahankan |
| Approval dibuka ulang setelah pricing dimulai | Konflik ditandai dan diselesaikan eksplisit; tidak menghapus harga diam-diam |

## 26. Data lama dan migrasi

### 26.1 Prinsip

- migration awal additive dan backward-compatible;
- tidak hard-delete kolom/status lama;
- tidak mengubah approval lama menjadi bukti sampel;
- tidak menganggap record `synced` lama mempunyai review IT historis;
- backfill hanya berdasarkan fakta yang dapat dibuktikan.

### 26.2 Klasifikasi aman

- material lama dengan Product ID dan seluruh Product Detail mapping valid dapat ditandai `esb_sync_status = synced` setelah audit/read-back;
- material lama tanpa bukti review IT diberi marker legacy, bukan review palsu;
- WIP lama yang synced dapat menjadi kandidat Pricing Request melalui dry-run terpisah;
- sourcing lama yang approved tidak otomatis dianggap mempunyai foto handover/receipt;
- record ambigu masuk laporan manual review.

### 26.3 Perangkat migrasi

Jika diperlukan command backfill:

- default dry-run;
- output jumlah per kategori;
- dapat difilter ID/rentang;
- membuat snapshot atau backup terarah sebelum mutation material;
- idempoten dan dapat dilanjutkan;
- tidak melakukan call ESB production tanpa flag dan persetujuan terpisah;
- menyediakan laporan exception.

## 27. Observability dan audit

Simpan atau ukur:

- waktu dari submit R&D sampai review IT;
- waktu review IT;
- jumlah revision required dan field yang sering salah;
- keberhasilan/gagal/link existing/create ESB;
- jumlah reconciliation;
- delivery Telegram dan retry;
- waktu sourcing dan jumlah Sample Cycle;
- waktu dari approval R&D sampai pricing;
- pricing success/failure/verification;
- jumlah workflow completed;
- correlation ID eksternal tanpa credential/payload sensitif.

Audit log harus dapat menjawab siapa, kapan, aksi apa, status sebelum/sesudah, dan alasan perubahan.

## 28. Kebutuhan pengujian

### 28.1 Characterization existing

- data supplier existing tetap dapat dibuat dan dibaca;
- approval lama tetap dapat dibuka;
- sourcing mandiri produk ESB existing tetap bekerja;
- push material existing tidak menghasilkan create ganda;
- route dan permission existing tidak berubah tanpa keputusan eksplisit.

### 28.2 Review IT

- R&D dapat menyimpan Draft dan submit jika valid;
- submit membuat review version dan satu event notifikasi;
- role tidak berizin mendapat 403;
- data terkunci setelah submit;
- IT dapat return dengan alasan;
- R&D dapat memperbaiki lalu resubmit;
- IT edit teknis membuat audit diff;
- stale version tidak dapat disetujui;
- double submit tidak membuat review atau Telegram ganda.

### 28.3 Telegram

- job dibuat setelah commit;
- rollback tidak mengirim pesan;
- retry tidak menggandakan delivery;
- timeout Telegram tidak mengubah status review;
- token dan data sensitif tidak muncul di log;
- URL mengarah ke record yang benar dan tetap membutuhkan login/authorization.

### 28.4 ESB

- produk existing ditautkan tanpa create;
- produk baru dipush satu kali;
- mapping semua UOM tersimpan;
- partial response tidak menandai synced;
- timeout menghasilkan reconciliation;
- retry aman tidak menggandakan produk;
- duplicate click diblokir;
- unauthorized push ditolak;
- unit inactive/active dipetakan sesuai aturan.

### 28.5 Sourcing dan sampel

- WIP tidak membuat Sourcing Request;
- bahan biasa synced membuat satu Sourcing Request;
- Purchasing dapat menambah kandidat;
- handover membutuhkan foto dan kandidat;
- R&D receipt membutuhkan foto;
- approve membuat satu Pricing Request;
- reject tidak membuat Pricing Request;
- resample membuat cycle baru dan menjaga histori;
- reopen memerlukan permission dan alasan.

### 28.6 Finance

- hanya item eligible tampil;
- bahan belum approved tidak tampil;
- WIP synced tampil tanpa sourcing;
- WIP belum synced tidak tampil;
- scope akses diuji server-side;
- Finance tidak dapat mengubah Product Detail dari form;
- pricing success menjadi priced;
- timeout menjadi verification required;
- Completed hanya terjadi ketika guard terpenuhi.

### 28.7 UI dan performa

- filter/pagination berjalan server-side;
- tidak ada query eksternal saat render tabel;
- detail berat dimuat saat dibuka;
- tidak ada N+1 pada unit, review, supplier, dan pricing;
- mobile tidak mengalami horizontal page scroll;
- modal tidak bertumpuk;
- loading dan error state terlihat;
- attachment private diuji authorization-nya.

## 29. Acceptance criteria

Fitur dinyatakan selesai apabila:

1. R&D tidak dapat memulai push ESB tanpa review IT yang disetujui.
2. IT menerima satu notifikasi Telegram untuk setiap submission version baru.
3. IT dapat mengembalikan, memperbaiki data teknis, menautkan existing, atau menyetujui push dengan audit trail.
4. Produk tidak ditandai `synced` sebelum Product ID dan seluruh Product Detail yang diperlukan terverifikasi.
5. R&D dapat memakai material synced pada resep/BOM.
6. Bahan biasa mengikuti sourcing dan Sample Cycle.
7. Bukti handover Purchasing dan receipt R&D tersimpan terpisah.
8. Resample tidak menghapus histori.
9. Bahan biasa hanya masuk Finance setelah approval R&D.
10. WIP masuk Finance langsung setelah sync dan tidak mempunyai data sourcing palsu.
11. Finance mempunyai submenu Pricelist Bahan dengan filter, pagination, status, dan detail yang sesuai.
12. Harga penawaran dan Pricelist resmi dibedakan jelas.
13. Completed dihitung oleh guard dan tidak dapat dipilih manual.
14. Retry Telegram, ESB, dan Pricelist tidak menggandakan side effect.
15. Data lama tetap dapat dibuka dan tidak memperoleh histori palsu.
16. Test workflow, authorization, failure path, dan responsive UI terdampak lulus.

## 30. Phase implementasi

### Phase 0 — Audit dan keputusan kontrak

- audit schema, workflow, permission, route, queue, storage, dan test existing;
- inventaris seluruh entry point push material ESB;
- klasifikasikan raw, packaging, marketing, dan WIP;
- konfirmasi owner Telegram chat;
- konfirmasi kontrak Pricelist ESB;
- rekam baseline data dan test.

Output: dependency map, state mapping, data report, dan keputusan API.

### Phase 1 — Safety net dan enum/status

- tambah characterization test;
- definisikan enum/presenter status terpisah;
- tambah struktur review IT dan audit secara additive;
- pertahankan pembacaan status lama selama compatibility window.

### Phase 2 — Submission dan inbox IT

- implementasikan submit, lock/version, claim, return, technical amendment, dan approval;
- buat inbox/detail IT yang server-driven;
- belum mengubah mutation ESB sampai workflow review stabil.

### Phase 3 — Telegram

- konfigurasi channel;
- event after commit;
- queued job, idempotency, delivery log, retry/backoff;
- monitoring dan test dengan HTTP fake.

### Phase 4 — Gate dan remediasi ESB

- pindahkan orchestration push ke Action/Service;
- wajibkan approval IT;
- implementasikan link existing, verified read-back, idempotency, dan reconciliation;
- cegah Page/Resource melakukan HTTP langsung.

### Phase 5 — Sourcing dan Sample Cycle

- pertahankan supplier fields existing;
- tambah handover, receipt, evaluation, resample, reject, approve, dan attachment private;
- buat history yang dapat diaudit.

### Phase 6 — Pricing eligibility

- implementasikan aturan bahan biasa dan WIP;
- buat Pricing Request idempoten;
- siapkan backfill dry-run untuk record existing.

### Phase 7 — Finance Pricelist Bahan

- implementasikan index, filter, detail, input draft, verification, permission, dan histori;
- gunakan mode manual terkontrol bila API tulis belum terbukti.

### Phase 8 — API Pricelist opsional

- hanya dimulai setelah kontrak API disetujui;
- implementasikan mutation, read-back, idempotency, reconciliation, dan audit;
- feature flag sampai staging terbukti stabil.

### Phase 9 — Migrasi data dan rollout

- jalankan laporan/dry-run staging;
- review exception bersama owner bisnis;
- aktifkan per role/jenis material secara bertahap;
- monitor queue, error ESB, Telegram, dan pricing;
- production mutation memerlukan persetujuan terpisah.

## 31. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| IT menjadi bottleneck | Inbox terurut, Telegram, SLA monitoring, claim, dan filter prioritas |
| Produk duplikat di ESB | Exact identifier search, link existing, idempotency, dan reconciliation |
| IT mengubah spesifikasi bisnis | Batasi field teknis, alasan wajib, diff, dan return-to-R&D |
| Telegram ganda | Unique idempotency key per submission version |
| Telegram down | Workflow tetap berjalan; retry melalui queue |
| Product Detail mapping salah | Read-back dan verifikasi semua unit sebelum synced |
| Sourcing dan WIP tercampur | Classification dan eligibility service terpusat dengan test |
| Harga penawaran dianggap harga resmi | Label UI jelas dan model data terpisah |
| Endpoint Pricelist tidak tersedia | Mode manual terkontrol; API menjadi phase opsional |
| Data lama salah dibackfill | Dry-run, legacy marker, exception report, tanpa histori palsu |
| Page R&D makin besar | Ekstrak Action/Service dan halaman kerja khusus sesuai domain |

## 32. Keputusan yang masih perlu dikunci

Sebelum implementasi phase terkait, owner bisnis perlu mengonfirmasi:

1. Telegram IT menggunakan grup atau chat personal, serta siapa pemilik Bot.
2. Apakah Purchasing juga memerlukan Telegram atau cukup notifikasi internal.
3. Batas field teknis yang boleh diedit IT tanpa dikembalikan ke R&D.
4. Daftar kategori/section yang wajib sourcing dan yang diperlakukan seperti WIP.
5. Pricelist berlaku per company, branch, supplier, periode, atau kombinasi tertentu.
6. Apakah semua UOM atau hanya Purchasing UOM yang wajib mempunyai Pricelist.
7. Mekanisme verifikasi harga ketika endpoint tulis/baca Pricelist belum tersedia.
8. Perlakuan sourcing mandiri terhadap Pricelist produk existing.
9. SLA review IT, sourcing, evaluasi R&D, dan pricing untuk kebutuhan monitoring.

## 33. Definition of done per perubahan

Setiap PR implementasi harus:

- fokus pada satu phase/concern;
- menyertakan migration additive bila diperlukan;
- mempunyai rollback data/schema yang aman sesuai sifat perubahan;
- menambahkan atau memperbarui test terdampak;
- menggunakan HTTP fake untuk ESB/Telegram;
- lulus test minimum relevan dan formatter;
- tidak menulis credential atau payload sensitif ke log;
- mendokumentasikan feature flag dan langkah rollout;
- menyertakan bukti bahwa data lama tetap dapat dibaca;
- tidak melakukan mutation production atau deployment sebagai bagian otomatis dari implementasi lokal.
