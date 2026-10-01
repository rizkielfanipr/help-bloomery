# PRD — Customer Complaints

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 1 Oktober 2026 |
| Status | Rancangan siap implementasi |
| Modul | Operational |
| Aplikasi user | Tile `Form Komplain` |
| Back office | `Operational → Customer Complaints` |
| Sifat form | Opsional dan hanya diisi ketika terdapat komplain |
| Acuan arsitektur | `docs/code-remediation-prd.md` |
| Acuan UI | `docs/ui-consistency-prd.md` |

Dokumen ini menjadi sumber requirement fitur Customer Complaints. Implementasi wajib mengaudit kondisi repository terlebih dahulu karena nama panel, navigation, komponen, dan pola attachment aktual dapat berubah dari baseline dokumen.

## 2. Masalah yang diselesaikan

Komplain customer saat ini belum mempunyai satu tempat pencatatan yang terstruktur. Informasi dapat tersebar di percakapan, catatan manual, atau grup komunikasi sehingga sulit dilacak, dicari, dan dipastikan penyelesaiannya.

Fitur ini menyediakan form singkat bagi karyawan untuk mencatat komplain dan workspace back office bagi tim Operational untuk meninjau serta menyelesaikannya.

## 3. Tujuan

1. Memberikan form komplain yang cepat dan mudah digunakan dari aplikasi user.
2. Menyimpan komplain beserta branch, kronologi, bukti, dan pelapor.
3. Memberikan daftar terpusat pada back office.
4. Menyediakan status penanganan dan histori perubahan yang dapat diaudit.
5. Menjaga akses data sesuai permission dan cakupan branch user.
6. Menyediakan fondasi bagi SLA, scoring branch, dan analitik pada pengembangan berikutnya.

## 4. Prinsip produk

- **Opsional:** tile bukan checklist harian dan tidak menampilkan kewajiban pengisian.
- **Singkat:** pelapor hanya mengisi informasi yang dibutuhkan untuk memahami kasus.
- **Branch-aware:** pilihan dan akses data mengikuti branch access user.
- **Dapat ditindaklanjuti:** setiap komplain mempunyai status dan penyelesaian.
- **Dapat diaudit:** perubahan status dan tindak lanjut mencatat pelaku serta waktu.
- **Privat:** kontak customer dan attachment hanya dapat dilihat user berwenang.
- **Terisolasi:** fitur tidak mengubah data ESB dan tidak bergantung pada network eksternal.

## 5. Scope

### 5.1 Termasuk

- Tile `Form Komplain` pada launcher aplikasi user.
- Form input komplain.
- Branch berdasarkan akses user.
- Nomor komplain otomatis.
- Upload gambar atau PDF.
- Konfirmasi hasil pengiriman.
- Lima komplain terakhir milik pelapor pada halaman yang sama.
- Submenu `Operational → Customer Complaints`.
- Index, search, filter, sort, dan pagination.
- Halaman detail komplain.
- Perubahan status, PIC, catatan internal, dan resolution.
- Activity timeline.
- Notifikasi database sederhana.
- Permission dan Policy.
- Soft delete dan lifecycle attachment.
- Test terisolasi dari network eksternal.

### 5.2 Tidak termasuk versi pertama

- Integrasi Sales Order ESB.
- SLA dan eskalasi otomatis.
- Scoring branch atau karyawan.
- Customer satisfaction survey.
- Pengiriman WhatsApp atau email otomatis.
- Root cause analysis kompleks.
- Dashboard analitik besar.
- Portal eksternal untuk customer.
- Sinkronisasi dengan vendor complaint.

## 6. Aktor

| Aktor | Tanggung jawab |
| --- | --- |
| Pelapor | Mengirim komplain dan melihat komplain yang dibuat sendiri |
| Operational Reviewer | Meninjau dan mengubah status sesuai branch access |
| Operational Manager | Melihat seluruh branch sesuai permission, menentukan PIC, dan menutup komplain |
| Administrator | Mengatur permission dan melakukan tindakan administratif |

## 7. Alur utama

```text
Launcher aplikasi user
→ Form Komplain
→ Isi informasi singkat dan bukti
→ Kirim
→ Nomor komplain dibuat
→ Notifikasi masuk ke back office
→ Operational meninjau
→ In Review
→ Resolution dicatat
→ Resolved
→ Closed
```

Tile tidak mempunyai indikator belum diisi dan tidak masuk dalam penilaian kepatuhan harian.

## 8. Form aplikasi user

### 8.1 Informasi Lokasi

| Field | Aturan |
| --- | --- |
| Branch | Wajib; default branch utama user; hanya branch yang dapat diakses user |
| Tanggal Kejadian | Wajib; default waktu saat ini; tidak boleh melewati waktu saat pengisian secara tidak wajar |
| Sumber Komplain | Wajib; select option dari enum |

Pilihan sumber awal:

- In Store
- WhatsApp
- Phone
- Delivery
- Marketplace
- Social Media
- Other

### 8.2 Informasi Komplain

| Field | Aturan |
| --- | --- |
| Kategori Komplain | Wajib; select option dari enum |
| Nomor Pesanan / Struk | Opsional; maksimal 100 karakter |
| Nama Customer | Opsional; maksimal 150 karakter |
| Kontak Customer | Opsional; maksimal 100 karakter |
| Detail Komplain | Wajib; maksimal 2.000 karakter |
| Attachment | Opsional; maksimal 5 file, 5 MB per file |

Kategori awal:

- Product Quality
- Service
- Order Accuracy
- Delivery
- Cleanliness & Facility
- Payment
- Other

Attachment menerima JPG, JPEG, PNG, WebP, dan PDF. UI harus menampilkan batas file, progress upload, preview nama/gambar, error per file, serta tombol hapus sebelum submit.

### 8.3 Informasi pengisian

Gunakan container **Informasi Pengisian**:

> Catat komplain sesuai informasi yang diterima customer. Pilih Branch tempat kejadian dan jelaskan kronologi secara singkat. Lampirkan foto atau bukti transaksi jika tersedia.

### 8.4 Setelah submit

Sistem menampilkan:

- nomor komplain;
- branch;
- kategori;
- status `New`;
- waktu pengiriman;
- tombol kembali ke launcher atau melihat detail.

Submit berulang harus dicegah selama request sedang berjalan.

## 9. Riwayat aplikasi user

Halaman Form Komplain menampilkan section **Komplain Terakhir** berisi maksimal lima komplain yang dibuat user tersebut.

Informasi per item:

- nomor komplain;
- tanggal kejadian;
- branch;
- kategori;
- status;
- tombol detail.

Tidak dibuat tile atau menu Riwayat terpisah. User biasa hanya dapat melihat komplain yang dibuat sendiri, termasuk ketika user mempunyai akses ke branch yang sama.

## 10. Back office

### 10.1 Navigation

```text
Operational
└── Customer Complaints
```

Custom sidebar dan navigation Filament harus memakai permission yang sama. Menu group Operational tetap terbuka ketika halaman Customer Complaints aktif.

### 10.2 Index

Urutan UI:

1. Header ringkas dengan ikon, judul, dan deskripsi.
2. Filter inline pada header tabel.
3. Search.
4. Tabel data.
5. Pagination 10 atau 20 data.

Kolom:

- Complaint Number;
- Complaint Date;
- Branch;
- Category;
- Source;
- Customer;
- Status;
- Submitted By;
- aksi detail.

Search mencakup nomor komplain, order reference, nama customer, dan detail komplain. Filter mencakup branch, category, source, status, serta rentang tanggal.

Urutan default adalah data terbaru. Sort tanggal kejadian dan created date tetap tersedia.

### 10.3 Detail

Section **Complaint Information** menampilkan seluruh data pelapor dan attachment.

Section **Follow-up** mempunyai field:

| Field | Aturan |
| --- | --- |
| Status | Wajib dan mengikuti transition map |
| Person in Charge | Opsional pada versi pertama; hanya user aktif yang berwenang |
| Internal Notes | Opsional; maksimal 2.000 karakter |
| Resolution | Wajib ketika status `Resolved` atau `Closed` |

Section **Activity Timeline** menampilkan perubahan status, perubahan PIC, catatan tindak lanjut, pelaku, dan waktu.

Internal Notes tidak ditampilkan pada aplikasi user. Pelapor hanya melihat status dan resolution setelah resolution tersedia.

## 11. Status dan transisi

| Status | Arti |
| --- | --- |
| `New` | Baru dikirim dan belum ditinjau |
| `InReview` | Sedang diperiksa atau ditindaklanjuti |
| `Resolved` | Penyelesaian telah diberikan |
| `Closed` | Kasus telah ditutup |

Transisi normal:

```text
New → In Review → Resolved → Closed
```

Aturan:

- `Resolved` dan `Closed` membutuhkan resolution.
- `Closed` mencatat `closed_at` dan `closed_by`.
- Pembukaan kembali kasus tidak termasuk versi pertama.
- Status disimpan sebagai nilai stabil, sementara label UI berasal dari enum.

## 12. Permission dan branch scope

| Permission | Kegunaan |
| --- | --- |
| `view any customer complaints` | Membuka index back office |
| `view customer complaints` | Melihat detail yang diizinkan |
| `create customer complaints` | Menampilkan tile dan mengirim form |
| `update customer complaints` | Mengubah PIC, status, catatan, dan resolution |
| `delete customer complaints` | Soft delete untuk role administratif |

Aturan scope:

- pelapor melihat record miliknya sendiri;
- reviewer hanya melihat branch yang dapat diakses;
- user dengan `access_all_branches` dapat melihat seluruh branch jika mempunyai permission;
- validasi branch dilakukan kembali di server saat submit;
- query scope dan Policy menjadi pengaman utama, bukan visibility UI.

Permission awal harus ditambahkan melalui konfigurasi permission, seeder untuk instalasi baru, dan migration pemberian permission yang aman untuk database production existing.

## 13. Nomor komplain

Format awal:

```text
CMP-YYYYMMDD-NNNN
```

Contoh:

```text
CMP-20261001-0001
```

Nomor dibuat di server secara atomik dan mempunyai unique index. Implementasi harus aman dari dua submit bersamaan dan tidak mengandalkan perhitungan `count + 1` tanpa lock.

## 14. Model data

### 14.1 `customer_complaints`

Kolom minimum:

- `id`;
- `complaint_number`, unique;
- `branch_id`, indexed;
- `occurred_at`, indexed;
- `source`;
- `category`;
- `order_reference`, nullable dan indexed;
- `customer_name`, nullable;
- `customer_contact`, nullable;
- `description`;
- `attachment_paths`, JSON nullable;
- `status`, indexed;
- `assigned_to`, nullable dan indexed;
- `resolution`, nullable;
- `resolved_at`, nullable;
- `resolved_by`, nullable;
- `closed_at`, nullable;
- `closed_by`, nullable;
- `submitted_by`, indexed;
- timestamps;
- soft deletes.

### 14.2 `customer_complaint_activities`

Kolom minimum:

- `id`;
- `customer_complaint_id`, indexed dan cascade delete;
- `activity_type`;
- `previous_status`, nullable;
- `new_status`, nullable;
- `notes`, nullable;
- `metadata`, JSON nullable;
- `created_by`, nullable;
- timestamps.

Activity bersifat append-only melalui use case normal. Perubahan record utama dan pembuatan activity dijalankan dalam transaction yang sama.

## 15. Attachment

- Gunakan disk privat existing yang dipakai aplikasi untuk attachment operasional.
- Database hanya menyimpan path dan metadata minimum.
- Nama file tidak boleh menjadi sumber otorisasi.
- Download/preview melalui route terautorisasi atau temporary URL sesuai pola existing.
- Hapus file jika upload sudah tersimpan tetapi transaction database gagal.
- Soft delete komplain tidak langsung menghapus attachment agar rollback tetap tersedia.
- Permanent cleanup attachment dilakukan melalui proses terpisah setelah retention policy ditentukan.

## 16. Notifikasi

Versi pertama menggunakan database notification:

1. Komplain baru memberi notifikasi kepada role Operational yang relevan pada branch tersebut.
2. Status `Resolved` atau `Closed` memberi notifikasi kepada pelapor.

Notifikasi tidak boleh dikirim sebelum transaction database berhasil. Gunakan event/listener atau notification setelah commit mengikuti pola repository.

## 17. Struktur kode target

Ikuti struktur existing dan jangan membuat base folder baru:

```text
app/
├── Actions/CustomerComplaint/
│   ├── CreateCustomerComplaintAction.php
│   ├── UpdateCustomerComplaintAction.php
│   └── DeleteCustomerComplaintAction.php
├── Enums/
│   ├── CustomerComplaintStatus.php
│   ├── CustomerComplaintCategory.php
│   └── CustomerComplaintSource.php
├── Models/
│   ├── CustomerComplaint.php
│   └── CustomerComplaintActivity.php
├── Policies/
│   └── CustomerComplaintPolicy.php
├── Filament/Casual/Pages/
│   └── CustomerComplaintPage.php
└── Filament/Helpdesk/Resources/CustomerComplaints/

resources/views/filament/
├── casual/pages/customer-complaint-page.blade.php
└── helpdesk/customer-complaints/

tests/Feature/
├── CustomerComplaintSubmissionTest.php
├── CustomerComplaintBackOfficeTest.php
├── CustomerComplaintPermissionTest.php
└── CustomerComplaintAttachmentTest.php
```

Tanggung jawab:

- Page mengelola state UI dan memanggil Action.
- Action mengelola satu use case, transaction, dan activity.
- Enum menjadi sumber nilai, label, dan warna.
- Policy mengelola permission serta record scope.
- Model menyimpan relasi dan cast tanpa menangani upload atau UI.
- Blade tidak melakukan query database atau business mutation.

## 18. UI dan responsive

Ikuti `docs/ui-consistency-prd.md`:

- container putih dengan border tipis dan `rounded-2xl`;
- tanpa shadow berlebihan;
- aksen biru untuk aksi utama;
- informasi pengisian berada dekat field;
- label memakai kapital setiap kata;
- attachment mengikuti pola ERP Request existing;
- loading, success, validation, error, dan empty state terlihat jelas;
- form satu kolom pada mobile;
- field dapat menjadi dua kolom hanya pada viewport yang cukup;
- tabel back office berada dalam container responsif;
- detail mobile memakai susunan card/list bila tabel tidak sesuai;
- tombol icon-only memiliki `aria-label`;
- modal mengikuti fokus, Escape, backdrop, dan scroll behavior pada UI PRD.

Viewport review minimum: 360, 390, 768, 1024, dan 1440 px, serta zoom browser 200%.

## 19. Reliability dan keamanan

- Seluruh input divalidasi server-side.
- Output teks menggunakan escaping Blade.
- File diverifikasi berdasarkan MIME, extension, size, dan jumlah.
- Customer contact tidak ditampilkan pada index jika tidak diperlukan.
- Log tidak memuat attachment content atau kontak customer lengkap.
- Mutation memakai transaction dan mencegah double submit.
- List memakai eager loading untuk branch, submitter, dan PIC.
- Index database mengikuti filter yang benar-benar digunakan.
- Tidak ada query database dari Blade.
- Jangan menambahkan dependency baru tanpa persetujuan.

## 20. Test strategy

### 20.1 Submission

- tile hanya tampil dengan permission create;
- branch default berasal dari user;
- user tidak dapat mengirim atas branch di luar aksesnya;
- field wajib dan panjang input divalidasi;
- nomor komplain unik saat request bersamaan;
- submit mencatat user dan activity pertama;
- duplicate click tidak membuat dua record;
- lima riwayat terakhir hanya milik pelapor.

### 20.2 Back office

- navigation muncul sesuai permission;
- branch scope diterapkan pada index dan detail;
- search dan filter bekerja;
- transition status valid diterima;
- transition tidak valid ditolak;
- resolution diwajibkan untuk Resolved dan Closed;
- perubahan status membuat activity dalam transaction yang sama;
- pelapor dapat melihat status dan resolution tetapi tidak Internal Notes.

### 20.3 Attachment

- tipe, ukuran, dan jumlah file divalidasi;
- path disimpan pada disk fake saat test;
- file orphan dibersihkan ketika penyimpanan gagal;
- user tanpa akses tidak dapat membuka attachment;
- soft delete mempertahankan file sesuai retention rule.

### 20.4 UI dan regression

- launcher tetap bekerja tanpa permission fitur;
- menu Operational tetap terbuka pada route aktif;
- halaman tidak mempunyai N+1 pada daftar;
- seluruh test tidak mengakses network eksternal.

## 21. Phase implementasi

### Phase 0 — Audit dan contract freeze

- Audit launcher, branch scope, permission, notification, dan attachment existing.
- Audit navigation custom serta resource Operational.
- Tentukan role penerima permission dan notifikasi awal.
- Catat behavior existing yang harus dipertahankan.

### Phase 1 — Fondasi domain

- Buat enum, model, factory, migration, policy, dan permission.
- Implementasikan generator nomor atomik.
- Buat Action submit beserta activity awal.
- Tambahkan test domain dan permission.

### Phase 2 — Form aplikasi user

- Tambahkan tile opsional.
- Buat form, attachment, informasi pengisian, dan konfirmasi.
- Tambahkan lima komplain terakhir.
- Uji validasi, branch scope, upload, dan double submit.

### Phase 3 — Back office

- Tambahkan resource dan submenu Operational.
- Buat index, filter, search, sort, pagination, dan detail.
- Implementasikan PIC, transition status, resolution, dan timeline.
- Uji Policy serta branch scope.

### Phase 4 — Notifikasi dan attachment hardening

- Tambahkan notifikasi setelah commit.
- Lindungi preview/download attachment.
- Pastikan lifecycle file dan error handling benar.

### Phase 5 — UI review dan release hardening

- Review seluruh state UI dan viewport.
- Jalankan test terfokus.
- Jalankan Pint untuk perubahan PHP.
- Jalankan full suite dengan memory 512 MB.
- Dokumentasikan deployment dan rollback.

## 22. Quality gates

1. Gunakan Laravel Boost `search-docs` sebelum perubahan kode.
2. Ikuti konvensi sibling file dan komponen existing.
3. Migration production existing tidak boleh diedit; gunakan migration baru.
4. Setiap perubahan mempunyai test yang bermakna.
5. Test terfokus lulus sebelum full suite.
6. Jalankan `vendor/bin/pint --dirty --format agent` untuk perubahan PHP.
7. Jalankan `php -d memory_limit=512M artisan test --compact` sebelum release.
8. Pertahankan perubahan unrelated di working tree.
9. Commit dipisahkan per phase kecil.
10. Jangan push atau deploy kecuali diminta.

## 23. Acceptance criteria

Fitur selesai jika:

- tile Form Komplain hanya muncul kepada user berizin;
- tile bersifat opsional dan bukan kewajiban harian;
- user hanya dapat memilih branch yang dapat diakses;
- komplain dapat dikirim dengan field minimum dan attachment opsional;
- nomor komplain dibuat otomatis dan unik;
- user melihat lima komplain terakhir miliknya;
- submenu Customer Complaints tersedia pada Operational;
- back office dapat search, filter, membuka detail, dan menindaklanjuti komplain;
- status hanya mengikuti transisi yang ditentukan;
- resolution wajib untuk Resolved dan Closed;
- seluruh perubahan penting tercatat dalam timeline;
- permission dan branch scope diterapkan di server;
- attachment privat dan tervalidasi;
- UI mengikuti standar konsistensi serta dapat digunakan di mobile;
- test terfokus, Pint, dan full suite lulus.

## 24. Deployment dan rollback

Deployment:

1. Pull commit yang sudah diuji.
2. Backup database.
3. Jalankan `php artisan migrate --force`.
4. Jalankan `php artisan optimize:clear`.
5. Restart queue worker jika notifikasi queued digunakan.
6. Smoke test permission, submit, attachment, index, detail, dan transition status.

Rollback aplikasi dilakukan per commit. Migration penambahan tabel dibuat backward-compatible. Jika kode harus di-rollback, tabel dapat dipertahankan sementara agar data komplain tidak hilang. Penghapusan tabel hanya melalui release terpisah setelah backup dan persetujuan eksplisit.

## 25. Prompt implementasi

```text
Implementasikan seluruh phase pada docs/customer-complaints-prd.md secara berurutan.

Baca dan ikuti:
- AGENTS.md
- docs/customer-complaints-prd.md
- docs/code-remediation-prd.md
- docs/ui-consistency-prd.md
- docs/panels.md

Audit repository dan working tree terlebih dahulu. Pertahankan seluruh perubahan existing yang tidak berhubungan dan jangan memasukkannya ke commit.

Ketentuan wajib:
- tile Form Komplain bersifat opsional;
- data back office berada di Operational → Customer Complaints;
- branch pilihan dan query mengikuti branch access user;
- pelapor hanya dapat melihat komplain miliknya sendiri;
- status memakai New, In Review, Resolved, dan Closed;
- Resolved dan Closed wajib memiliki resolution;
- attachment harus privat, tervalidasi, dan mengikuti pola existing;
- mutation domain dilakukan melalui Action dan Policy;
- seluruh perubahan status dicatat sebagai activity;
- UI mengikuti docs/ui-consistency-prd.md;
- seluruh test terisolasi dari network eksternal;
- jangan mengubah requirement production demi mempertahankan test stale;
- jangan menambah dependency tanpa persetujuan.

Setelah setiap phase:
1. jalankan test terfokus;
2. jalankan quality gate yang relevan;
3. laporkan file dan behavior yang berubah;
4. laporkan test gagal beserta klasifikasinya;
5. buat commit kecil khusus phase setelah test lulus;
6. jangan push kecuali diminta.

Setelah seluruh phase:
1. jalankan vendor/bin/pint --dirty --format agent;
2. jalankan php -d memory_limit=512M artisan test --compact;
3. buat laporan implementasi Markdown;
4. berikan langkah deployment dan rollback;
5. jangan push atau deploy kecuali diminta.
```
