# PRD — Penyederhanaan Receiving dan QC Inbound

## 1. Status dokumen

- **Status:** Planning
- **Modul:** Employee App → Receiving
- **Halaman utama:** `/goods-receipt-page`
- **Prioritas:** Tinggi
- **Tujuan utama:** Mempercepat proses penerimaan barang, khususnya untuk Purchase Order dengan puluhan hingga ratusan produk.

Dokumen ini menjadi sumber konteks implementasi. Perubahan harus mempertahankan kompatibilitas laporan Goods Receipt existing dan kontrak payload ESB yang masih digunakan.

## 2. Latar belakang

Form Receiving saat ini mengulang seluruh Quantity Check, Quality Check, Cold Chain, Shelf Life, Sampling, Rejection, dan attachment untuk setiap produk. Pada PO dengan 100 produk, pola ini menghasilkan form sangat panjang, interaksi lambat, payload Livewire besar, dan risiko input terlewat.

Masukan pengguna yang harus diselesaikan:

1. Nomor Surat Jalan dan Invoice menjadi satu kolom yang dapat diisi salah satu.
2. Tanggal Surat Jalan dan Invoice menjadi satu kolom yang dapat diisi salah satu.
3. Foto dokumen/invoice dan foto barang dipisahkan.
4. Status file selesai diunggah harus terlihat dan validasi tidak boleh membaca file berhasil sebagai belum diunggah.
5. Quantity Check dan Quality Check harus lebih sederhana.
6. Tanggal ED menampilkan sisa hari.
7. Purchase Order dari Company Code SPN harus tersedia.
8. User hanya dapat melihat Company Code dan branch yang diizinkan.
9. Informasi dan checkbox Tutup PO dihilangkan; PO selesai otomatis hilang dari daftar.

## 3. Kondisi implementasi saat ini

### 3.1 Integrasi ESB

`EsbGoodsReceiptService` masih menggunakan Company Code tetap `BLSS`. Method daftar PO, detail PO, location, dan create Goods Receipt belum menerima Company Code sebagai context request.

Akibatnya:

- PO dari SPN tidak dapat diambil;
- seluruh proses Receiving diasumsikan berasal dari BLSS;
- context Company Code asal PO belum dibawa sampai proses submit.

### 3.2 Pembatasan branch

Validasi akses branch baru dilakukan mendekati proses persist. Daftar Purchase Order belum dibangun sejak awal berdasarkan pasangan Company Code dan branch yang dapat diakses user.

### 3.3 Form produk

Setiap produk menampilkan seluruh field QC meskipun barang diterima normal. Kalkulasi preview QC juga dilakukan untuk setiap baris saat halaman dirender.

### 3.4 Attachment

Foto bukti dokumen dan foto masalah barang belum memiliki pemisahan konteks yang jelas. Status upload bergantung pada temporary upload state dan belum memberikan tanda berhasil yang konsisten.

## 4. Prinsip produk

1. **Jalur normal harus singkat.** Produk yang diterima sesuai PO tidak memerlukan form detail.
2. **Exception membuka detail.** Field Hold, Rejected, karantina, foto bukti, suhu, batch, dan sampling hanya muncul saat relevan.
3. **Akses dibatasi di server.** Menyembunyikan PO di UI tidak cukup.
4. **Satu PO membawa satu context sumber.** Company Code dan branch asal PO tidak boleh berubah selama proses.
5. **Draft tidak boleh hilang.** Pagination, pencarian, atau refresh browser tidak boleh membuang input.
6. **Data existing tetap dapat dibaca.** Field lama tidak langsung dihapus.

## 5. Flow pengguna yang dituju

### 5.1 Pilih Purchase Order

Daftar PO menampilkan:

- nomor PO;
- supplier;
- branch penerima;
- Company Code;
- tanggal PO;
- tanggal dibutuhkan;
- jumlah produk jika tersedia;
- status Authorized atau Receiving.

Urutan daftar:

1. tanggal dibutuhkan paling awal;
2. nomor PO untuk tanggal yang sama;
3. PO tanpa tanggal dibutuhkan ditempatkan paling akhir.

Sumber PO dibatasi menggunakan mapping branch user.

Contoh akses user Erwin:

| Company Code | Branch yang diizinkan |
|---|---|
| BLSS/BSS | HEAD OFFICE JATENG |
| SPN | Sarana |

Kode final `BLSS` atau `BSS` harus diverifikasi terhadap Master Branch dan kredensial ESB yang tersedia.

### 5.2 Informasi penerimaan

Form utama berisi:

- Tanggal Penerimaan;
- Lokasi;
- Jenis Dokumen: Surat Jalan atau Invoice;
- Nomor Dokumen;
- Tanggal Dokumen;
- Catatan opsional.

Field terpisah berikut dihapus dari UI baru:

- Nomor Surat Jalan;
- Nomor Invoice;
- Tanggal Surat Jalan;
- Tanggal Invoice;
- Status Invoice;
- Tutup PO Otomatis.

Invoice tidak wajib jika user memilih Surat Jalan sebagai dokumen penerimaan.

### 5.3 Attachment

Attachment dibagi menjadi:

#### Foto Dokumen

Foto Surat Jalan atau Invoice sesuai jenis dokumen yang dipilih.

#### Foto Barang

Foto kondisi barang pada saat diterima. Foto ini berbeda dari foto bukti masalah per produk.

Setiap file menampilkan:

- nama file;
- ukuran file;
- status Mengunggah, Berhasil, atau Gagal;
- tombol hapus;
- jumlah file berhasil.

Tombol submit dinonaktifkan selama file masih diunggah.

### 5.4 Pemeriksaan produk

Produk ditampilkan sebagai tabel atau daftar baris ringkas:

| Produk | Sisa PO | Qty Diterima | Kondisi | ED | Catatan |
|---|---:|---:|---|---|---|
| Butter | 20 PCS | 20 | Sesuai | 90 hari lagi | — |
| Cream | 10 PCS | 8 | Bermasalah | 12 hari lagi | Kemasan rusak |

Default produk:

- dipilih untuk diterima;
- Qty Diterima mengikuti outstanding PO;
- kondisi `Sesuai`;
- Accepted sama dengan Qty Diterima;
- Hold dan Rejected bernilai 0;
- pemeriksaan visual dianggap lulus.

Aksi massal:

- Pilih Semua Produk;
- Tandai Semua Sesuai;
- Isi Qty Sesuai Outstanding;
- Kosongkan Semua Qty;
- pencarian produk;
- filter Semua, Sesuai, Bermasalah, dan Belum Diperiksa.

## 6. Penyederhanaan Quantity Check

Pada jalur normal user hanya mengisi **Qty Diterima**.

Sistem menghitung:

```text
Accepted = Qty Diterima
Hold = 0
Rejected = 0
```

Sistem tetap menghitung variance terhadap outstanding PO dan menampilkan badge:

- Sesuai PO;
- Kurang N unit;
- Lebih N unit.

Tolerance dan pembagian Accepted/Hold/Rejected masuk ke Detail Pemeriksaan dan hanya tampil jika kondisi produk `Bermasalah` atau terdapat variance yang perlu ditindaklanjuti.

## 7. Penyederhanaan Quality Check

Pilihan utama setiap produk:

- `Sesuai`;
- `Bermasalah`.

Jika `Sesuai`, pemeriksaan warna, tekstur, kemasan, dan kontaminasi dianggap lulus.

Jika `Bermasalah`, tampilkan Detail Pemeriksaan:

- jenis masalah visual;
- Qty Hold;
- Qty Rejected;
- kategori masalah;
- alasan;
- lokasi karantina;
- foto bukti;
- catatan.

Validasi tetap memastikan:

```text
Accepted + Hold + Rejected = Qty Diterima
```

## 8. Cold Chain

Cold Chain tidak ditampilkan untuk semua produk. Field suhu muncul apabila:

- produk memiliki metadata chilled atau frozen; atau
- user mengaktifkan `Perlu Pemeriksaan Suhu`.

Input utama:

- kategori Chilled atau Frozen;
- suhu aktual.

Min dan max sebaiknya berasal dari master pengaturan produk. Jika master belum tersedia, sistem menggunakan default sementara yang dapat dikoreksi oleh user berwenang.

## 9. ED, batch, dan shelf life

Kolom ED tersedia pada baris produk yang relevan.

Setelah tanggal ED dipilih, sistem menampilkan:

- `90 hari lagi`;
- `12 hari lagi`;
- `Hari ini`;
- `Lewat 3 hari`.

Status visual:

- hijau untuk aman;
- amber untuk mendekati expired;
- merah untuk expired atau gagal standar shelf life.

Perhitungan dasar:

```text
Sisa hari = Tanggal ED - Tanggal Penerimaan
```

Jika tanggal produksi tersedia:

```text
Shelf Life Tersisa = (Sisa hari / Total umur produk) × 100%
```

Untuk satu batch, form cukup berisi:

- Nomor Batch, opsional;
- Tanggal ED;
- Qty Batch.

Tanggal produksi dan rincian persentase berada di Detail Batch. Tombol Tambah Batch digunakan jika produk datang dalam lebih dari satu batch.

## 10. Sampling

Sampling tidak ditampilkan secara default. Field muncul jika:

- produk ditandai kritis pada master; atau
- user mengaktifkan `Perlu Sampling`.

Input:

- Pass;
- Fail;
- Pending;
- metode dan catatan opsional.

Accepted tidak diizinkan jika sampling masih Pending atau Fail.

## 11. Penutupan Purchase Order

Bagian Informasi Tutup PO dan checkbox Tutup PO Otomatis dihapus.

Sistem menentukan `autoClosePO` secara otomatis:

```text
autoClosePO = true
jika seluruh outstanding PO telah diterima,
tidak ada Hold,
dan tidak ada quantity yang masih akan diterima.
```

Selain kondisi tersebut, `autoClosePO = false`.

Setelah Goods Receipt berhasil:

1. status PO diambil ulang dari ESB;
2. PO selesai dihapus dari daftar;
3. jika ESB mengalami jeda pembaruan, hasil GR lokal dipakai untuk menyembunyikan PO yang sudah selesai;
4. PO parsial tetap tampil dengan outstanding terbaru.

## 12. Integrasi multi-company

`EsbGoodsReceiptService` harus menerima Company Code pada setiap operasi:

```php
purchaseOrders(string $companyCode, array $filters)
purchaseOrder(string $companyCode, string $purchaseNumber)
locations(string $companyCode, int $branchId)
create(string $companyCode, string $purchaseNumber, array $payload)
```

Setiap PO membawa context berikut:

```text
company_code
esb_branch_id
esb_branch_code
local_branch_id
```

Context tersebut digunakan kembali saat mengambil detail, lokasi, validasi, penyimpanan lokal, dan create Goods Receipt.

Kebutuhan SPN:

- kredensial ESB Core SPN;
- mapping SPN ke numeric ESB Branch ID;
- mapping ESB Branch Code SPN ke local branch Sarana;
- verifikasi bahwa endpoint Purchase Order, Location, dan Goods Receipt tersedia pada Company Code SPN.

## 13. Pembatasan akses

Daftar sumber dibangun dari `accessibleBranchIds()` user dan mapping `branch_esb_codes` yang aktif.

Validasi dilakukan pada:

1. pengambilan daftar PO;
2. pembukaan detail PO;
3. pengambilan lokasi;
4. submit Goods Receipt.

Request ditolak jika pasangan Company Code dan branch PO tidak termasuk akses user, walaupun nomor PO dimanipulasi dari browser.

Untuk user yang memiliki beberapa branch, daftar PO merupakan hasil merge dari seluruh pasangan mapping yang diizinkan. Duplikat diidentifikasi menggunakan gabungan Company Code dan nomor PO, bukan nomor PO saja.

## 14. Strategi attachment

State attachment dipisahkan:

```text
documentPhotos
goodsPhotos
itemEvidencePhotos
```

Ketentuan teknis:

- gunakan status upload eksplisit per kelompok;
- validasi hanya `TemporaryUploadedFile` yang selesai;
- jangan menganggap event pemilihan file sebagai upload selesai;
- simpan ke direktori R2 terpisah;
- hapus file tersimpan jika transaksi database gagal;
- pertahankan path attachment pada laporan existing;
- tampilkan status berhasil setelah temporary upload tervalidasi;
- cegah submit selama upload berlangsung.

## 15. Strategi data dan kompatibilitas

Field baru yang direkomendasikan pada `goods_receipts`:

```text
document_type
document_number
document_date
document_photos
goods_photos
source_company_code
```

Field lama seperti `delivery_number`, `delivery_date`, `invoice_number`, `invoice_date`, dan `document_evidence_photos` tidak langsung dihapus. Laporan lama tetap membaca field tersebut. Data baru dapat mengisi field kompatibilitas selama masa transisi bila diperlukan.

Item tetap menyimpan hasil QC rinci agar back office, Vendor Compliance, dan audit tidak kehilangan informasi meskipun UI input disederhanakan.

## 16. Kinerja PO besar

Untuk PO hingga 100 produk:

- tampilkan 20–25 produk per halaman;
- simpan perubahan saat pindah halaman;
- gunakan pencarian dan filter status;
- buka detail QC melalui drawer atau modal;
- jangan menghitung preview semua produk pada setiap render;
- hitung ulang hanya baris yang berubah;
- gunakan binding lazy/debounce sesuai jenis input;
- simpan draft lokal agar refresh browser tidak membuang progres.

Rekomendasi jangka panjang adalah membuat Goods Receipt berstatus `draft` sebelum dikirim ke ESB. Baris produk disimpan bertahap ke database, sehingga payload Livewire tidak perlu membawa seluruh detail 100 produk pada setiap request.

## 17. Tahapan implementasi

### Phase 0 — Audit kontrak dan data

- Verifikasi Company Code BLSS/BSS dan SPN.
- Verifikasi kredensial dan endpoint SPN.
- Audit mapping user, local branch, Company Code, Branch Code, dan numeric Branch ID.
- Catat payload Purchase Order dan Goods Receipt dari kedua company.
- Audit penyebab attachment dianggap belum terunggah.

### Phase 1 — Form utama dan attachment

- Gabungkan jenis, nomor, dan tanggal dokumen.
- Pisahkan Foto Dokumen dan Foto Barang.
- Tambahkan status upload eksplisit.
- Perbaiki validasi attachment.
- Hapus UI Informasi Tutup PO.
- Pertahankan payload ESB BLSS agar risiko perubahan kecil.

### Phase 2 — Tabel produk ringkas

- Ubah produk menjadi tabel/list ringkas.
- Terapkan default Qty Diterima dan Kondisi Sesuai.
- Tambahkan bulk action.
- Pindahkan detail exception ke drawer/modal.
- Hilangkan pengulangan seluruh form QC pada setiap produk.

### Phase 3 — ED dan QC dinamis

- Tambahkan penghitung sisa hari ED.
- Sederhanakan input batch.
- Tampilkan Cold Chain hanya saat relevan.
- Tampilkan Sampling hanya saat relevan.
- Pertahankan hasil QC rinci di database.

### Phase 4 — Multi-company dan access lock

- Refactor service agar menerima Company Code.
- Tambahkan sumber SPN.
- Ambil PO berdasarkan mapping branch yang dapat diakses user.
- Validasi ulang context sumber saat detail dan submit.
- Simpan Company Code asal pada Goods Receipt.

### Phase 5 — Auto close dan performa

- Hitung `autoClosePO` otomatis.
- Refresh dan keluarkan PO selesai dari daftar.
- Tambahkan draft/autosave per produk.
- Terapkan pagination produk dan optimasi payload Livewire.

## 18. Acceptance criteria

### Flow normal

1. User memilih PO yang diizinkan.
2. User memilih Surat Jalan atau Invoice.
3. User mengisi satu nomor dan satu tanggal dokumen.
4. User mengunggah foto dokumen dan foto barang pada area berbeda.
5. Sistem menunjukkan status upload berhasil.
6. Qty produk default mengikuti outstanding.
7. User dapat menandai semua produk sesuai.
8. User mengisi ED hanya pada produk terkait.
9. Goods Receipt berhasil dikirim tanpa membuka detail QC setiap produk.

### Exception

1. Produk bermasalah membuka detail QC.
2. Accepted, Hold, dan Rejected tervalidasi terhadap Qty Diterima.
3. Hold/Rejected mewajibkan alasan, karantina, dan foto bukti.
4. Vendor Compliance Incident tetap dibuat untuk Rejected.

### Akses

1. User hanya melihat PO dari mapping branch yang diizinkan.
2. User tidak dapat membuka atau submit PO branch lain secara langsung.
3. PO BLSS/BSS dan SPN tidak tercampur context kredensialnya.

### Penyelesaian PO

1. PO parsial tetap tersedia.
2. PO yang seluruh outstanding-nya sudah diterima otomatis ditutup jika memenuhi syarat.
3. PO selesai tidak lagi muncul pada daftar Receiving.

## 19. Test minimum

- nomor dan tanggal salah satu jenis dokumen dapat disimpan;
- Surat Jalan valid tanpa Invoice;
- attachment document dan goods disimpan terpisah;
- submit ditolak saat upload masih berlangsung;
- status berhasil muncul setelah upload selesai;
- default produk normal menghasilkan Accepted sesuai Qty Diterima;
- exception memvalidasi pembagian quantity;
- sisa hari ED benar untuk masa depan, hari ini, dan expired;
- daftar BLSS/BSS dan SPN menggunakan kredensial masing-masing;
- PO difilter berdasarkan accessible branch user;
- manipulasi nomor PO lintas branch ditolak;
- auto close hanya aktif pada penerimaan final;
- PO final hilang dan PO parsial tetap tampil;
- draft 100 produk tetap tersimpan saat berpindah halaman;
- seluruh HTTP ESB pada test diisolasi dengan fake/mock.

## 20. Deployment dan rollback

Deployment dilakukan bertahap sesuai phase. Setiap phase harus dapat dirilis tanpa mewajibkan phase berikutnya.

Urutan deployment untuk phase yang memiliki perubahan database:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan permissions:sync
php artisan optimize
```

Rollback harus mempertahankan kolom legacy dan data Goods Receipt existing. Kolom baru tidak boleh dihapus sebelum seluruh view, export, dan laporan tidak lagi bergantung pada format lama.

## 21. Keputusan yang harus diverifikasi sebelum implementasi multi-company

1. Apakah kode company yang benar `BLSS` atau `BSS`?
2. Apakah kredensial ESB Core SPN sudah tersedia pada environment?
3. Apakah endpoint SPN menggunakan kontrak payload yang sama dengan BLSS?
4. Apa numeric Branch ID SPN untuk Branch Sarana?
5. Apakah metadata produk ESB menyediakan kategori cold chain, kewajiban ED, dan sampling?
6. Jika metadata tidak tersedia, apakah pengaturan tersebut dikelola melalui master lokal?

