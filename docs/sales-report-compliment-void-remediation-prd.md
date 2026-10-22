# PRD — Revisi Workflow, Compliment, dan Void Sales Report

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Dokumen | Revisi Workflow, Compliment, dan Void Sales Report |
| Baseline audit | 21 Oktober 2026 |
| Status | Rancangan untuk review; belum mengotorisasi implementasi, migrasi production, commit, push, atau deployment |
| Modul | `Sales Report` pada aplikasi store dan panel Helpdesk/Back Office |
| Stack existing | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Alpine.js 3, Tailwind CSS 4, Pest 4 |
| Acuan arsitektur | [`code-remediation-prd.md`](code-remediation-prd.md) |
| Acuan UI | [`ui-consistency-prd.md`](ui-consistency-prd.md) |
| Strategi | Perubahan bertahap pada modular monolith, characterization test lebih dahulu, migrasi data non-destruktif, dan rollout terukur |

Dokumen ini menerjemahkan kebutuhan bisnis berikut:

1. Approval Finance dihapus. Setelah Supervisor menyetujui, laporan langsung berstatus `Completed`.
2. Finance tidak lagi mempunyai aksi approve dan hanya dapat menolak laporan `Completed` dengan alasan wajib. Penolakan langsung membuat nilai store pada tanggal tersebut menjadi 0.
3. Compliment wajib mempunyai Sales Number, keterangan transaksi, dan foto.
4. Data Compliment dapat diekspor ke Excel untuk dibandingkan dengan laporan ESB.
5. Void wajib mempunyai Sales Number, payment method, alasan void, foto nota awal, dan foto nota setelah void.
6. Data Void dapat diekspor ke Excel untuk dibandingkan dengan laporan ESB.

Dokumen ini tidak memberi izin menghapus data atau kolom lama. Seluruh keputusan implementasi tetap harus diverifikasi terhadap repository dan data environment tujuan sebelum dijalankan.

## 2. Ringkasan keputusan produk

| Area | Keputusan final PRD |
| --- | --- |
| Workflow utama | `Draft → Supervisor Review → Completed` |
| Approval Finance | Dihapus dari workflow baru |
| Aksi Finance | Hanya `Reject Report` terhadap laporan `Completed` yang dapat diakses |
| Dampak reject Finance | Status menjadi `Rejected`; nilai store untuk tanggal tersebut menjadi 0 melalui kalkulator existing |
| Alasan reject | Wajib, 5–2.000 karakter, disimpan pada audit trail |
| Rejected | Final untuk scope ini; tidak ada reopen atau resubmit baru |
| Compliment | Bagian opsional, tetapi setiap baris yang ditambahkan wajib lengkap |
| Void | Bagian opsional, tetapi setiap baris yang ditambahkan wajib lengkap |
| Sales Number Void | Wajib agar data Void benar-benar dapat dibandingkan dengan transaksi ESB |
| Bukti Compliment baru | Gambar saja; minimal satu foto |
| Bukti Void baru | Tepat satu foto nota awal dan satu foto nota setelah void |
| File lama | Tetap dapat dibaca; file PDF Compliment lama tidak dihapus |
| Export | Dua export terpisah: Compliment dan Void; mengikuti filter dan branch scope pengguna |
| Data Finance lama | Reviewer, waktu, catatan, approval history, dan status lama dipertahankan |
| Settlement/MDR lama | Tidak dihapus oleh revisi ini; perubahan proses settlement berada di luar scope sampai ada keputusan bisnis terpisah |

## 3. Kondisi sistem saat baseline

Audit repository menemukan kondisi berikut:

### 3.1 Workflow dan penilaian

- Enum `SalesReportStatus` masih mempunyai `Draft`, `PendingSupervisor`, `PendingFinance`, `Completed`, `Rejected`, dan `RejectedBySystem`.
- Ketika seluruh shift selesai, laporan menjadi `PendingSupervisor`.
- Aksi Supervisor saat ini memindahkan laporan ke `PendingFinance`.
- Finance saat ini dapat approve `PendingFinance` menjadi `Completed` atau reject menjadi `Rejected`.
- Finance sudah dapat reject laporan `Completed`, dengan alasan dan histori approval.
- Kalkulator nilai existing hanya memberikan nilai 100 kepada status `Completed`; `Rejected` dan `RejectedBySystem` mendapat nilai 0.
- Auto-reject existing hanya memproses laporan yang masih menunggu Supervisor dan tidak perlu diperluas ke tahap Finance.

### 3.2 Compliment

- Compliment sudah tersimpan per Sales Report dan per shift.
- Field existing mencakup jenis Compliment, attachment, notes, dan submitter.
- Sales Number belum disimpan pada Compliment.
- Upload existing menerima gambar dan PDF serta dapat menyimpan lebih dari satu attachment.
- Data Compliment sudah terlihat pada detail Sales Report, tetapi belum memiliki laporan index dan export khusus.

### 3.3 Void

- Belum ada model, tabel, form input, detail, laporan index, atau export khusus untuk Void pada Sales Report.

### 3.4 Export

- Export Sales Report umum sudah menggunakan XLSX streaming/chunk dan sanitasi nilai yang berpotensi menjadi formula Excel.
- Export existing masih memuat kolom Finance approval.
- Belum ada export Compliment dan Void terpisah.

### 3.5 Risiko kode existing

- Page detail Sales Report menangani authorization, validasi, transaksi, perubahan status, dan audit secara langsung.
- Page input shift menangani state UI, validasi, upload, transaksi database, snapshot ESB, dan kalkulasi sekaligus.
- Menambah seluruh fitur baru langsung ke dua Page tersebut akan memperbesar coupling dan menyulitkan test.

## 4. Tujuan

1. Memperpendek approval Sales Report tanpa menghilangkan kontrol Finance.
2. Membuat penilaian store langsung aktif setelah approval Supervisor.
3. Menjadikan reject Finance sebagai koreksi pasca-approval yang tercatat dan langsung memengaruhi nilai.
4. Menyimpan Compliment dan Void dalam struktur yang dapat direkonsiliasi dengan transaksi ESB.
5. Menyediakan export Excel yang mudah dibandingkan dengan laporan ESB.
6. Menjaga data, histori approval, attachment, branch scope, dan record lama.
7. Menempatkan proses bisnis pada Action/Service agar dapat dipakai oleh UI atau API di masa depan tanpa menyalin logika.
8. Membuat UI input mobile-friendly dan UI back office konsisten dengan standar Help Bloomery.

## 5. Non-goals

- Tidak mengganti Filament, Livewire, Blade, atau Tailwind dengan framework frontend baru.
- Tidak membuat microservice atau aplikasi terpisah.
- Tidak mengubah rumus nilai bulanan selain dampak status existing: `Completed = 100`, `Rejected = 0`.
- Tidak mengubah aturan jumlah shift aktif, jadwal submit, Basket Size, atau sumber transaksi ESB.
- Tidak mengubah kontrak endpoint ESB.
- Tidak menghapus data Finance approval, settlement, MDR, reconciliation, atau attachment lama.
- Tidak membuat proses approve Compliment atau approve Void yang terpisah.
- Tidak melakukan auto-match ke laporan ESB di luar snapshot transaksi yang sudah dimiliki sistem.
- Tidak menyamakan persis kolom Excel ESB sebelum contoh file ESB resmi diterima.

## 6. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Batasan |
| --- | --- | --- |
| Store Staff | Mengisi Sales Report shift, Compliment, dan Void | Hanya branch sendiri dan shift yang sah |
| Supervisor Store | Memeriksa dan menyetujui Sales Report | Tidak boleh menyetujui laporan yang dia submit sendiri kecuali pengecualian administrator existing |
| Finance | Melihat laporan, Compliment, Void, mengunduh export, dan reject laporan Completed | Tidak mempunyai tombol approve; branch scope tetap diberlakukan server-side |
| Administrator/Superadmin | Akses sesuai permission dan pengecualian existing | Seluruh aksi tetap tercatat |
| Scheduler | Auto-reject laporan yang tidak ditinjau Supervisor sesuai settings existing | Tidak memproses laporan Completed hanya karena belum dilihat Finance |

## 7. Workflow target

### 7.1 State machine

```text
Draft
  └─ seluruh shift berhasil disubmit
      → PendingSupervisor / "Supervisor Review"
          ├─ Supervisor approve
          │   → Completed
          │       └─ Finance reject + alasan
          │           → Rejected
          └─ melewati batas auto-reject
              → RejectedBySystem
```

`PendingFinance` tidak lagi dihasilkan oleh proses baru.

### 7.2 Tabel transisi

| Status awal | Aksi | Aktor | Status akhir | Audit |
| --- | --- | --- | --- | --- |
| `Draft` | Seluruh shift lengkap | Store Staff/sistem | `PendingSupervisor` | Approval history `submitter/submitted` existing |
| `PendingSupervisor` | Approve | Supervisor | `Completed` | `stage=supervisor`, `action=approved` |
| `PendingSupervisor` | Auto-reject | Sistem | `RejectedBySystem` | Audit existing dengan metadata scheduler |
| `Completed` | Reject Report | Finance | `Rejected` | `stage=finance`, `action=rejected`, metadata `previous_status=completed` |

Tidak ada transisi baru dari `Rejected` atau `RejectedBySystem`. Implementasi tidak boleh menghidupkan kembali alur resubmit lama secara tidak sengaja.

### 7.3 Approval Supervisor

- Tombol berubah dari **Set as Finance Review** menjadi **Approve Report** atau label ringkas yang disepakati UI.
- Action wajib memakai database transaction dan `lockForUpdate()`.
- Action hanya menerima record berstatus `PendingSupervisor`.
- Data koreksi Supervisor dan catatan tetap divalidasi seperti existing.
- Setelah berhasil:
  - `status = completed`;
  - `supervisor_reviewed_by` dan `supervisor_reviewed_at` terisi;
  - audit approval Supervisor dibuat tepat satu kali;
  - nilai tanggal tersebut menjadi 100 melalui kalkulator existing.
- Double click atau concurrent request tidak boleh membuat dua audit atau transisi ganda.

### 7.4 Reject Finance

- Finance tidak melihat tombol approve.
- Tombol **Reject Report** hanya muncul untuk `Completed`.
- Alasan reject wajib 5–2.000 karakter.
- Dialog konfirmasi harus menjelaskan bahwa status menjadi Rejected dan nilai tanggal tersebut menjadi 0.
- Server melakukan permission, record scope, status, dan self-review guard; menyembunyikan tombol saja tidak cukup.
- Setelah berhasil:
  - `status = rejected`;
  - reviewer dan waktu Finance tersimpan;
  - alasan tersimpan pada histori approval dan field kompatibilitas existing yang digunakan UI;
  - metadata menyimpan status sebelumnya;
  - score berikutnya langsung membaca status baru tanpa perlu edit manual.
- Rejected bersifat final pada scope ini.

### 7.5 Auto-reject

- Auto-reject tetap hanya berlaku untuk `PendingSupervisor` sesuai pengaturan existing.
- Tidak ada auto-reject untuk laporan `Completed` yang belum diperiksa Finance.
- Penghapusan tahap Finance tidak boleh mengubah effective date, jumlah hari, atau alasan auto-reject existing.

## 8. Dampak penilaian store

Rumus existing dipertahankan:

| Status pada tanggal wajib | Nilai harian |
| --- | --- |
| `Completed` | 100 |
| `Rejected` | 0 |
| `RejectedBySystem` | 0 |
| `Draft` / `PendingSupervisor` | 0 pada perhitungan tanggal wajib, dikategorikan pending sesuai aturan existing |
| Tidak ada laporan | 0 pada tanggal wajib |

Perubahan waktu pemberian nilai:

- Sebelum revisi: nilai 100 baru diperoleh setelah Finance approve.
- Sesudah revisi: nilai 100 diperoleh ketika Supervisor approve.
- Jika kemudian Finance reject: nilai tanggal tersebut berubah menjadi 0.

Tidak dibutuhkan tabel score baru apabila kalkulator tetap menghitung dari status aktual. Cache score, jika ada, wajib di-invalidasi setelah transisi status.

## 9. Kebutuhan Compliment

### 9.1 Aturan pengisian

Bagian Compliment tetap opsional. Namun, setelah pengguna menekan **Tambah Compliment**, setiap baris wajib mempunyai:

1. Jenis Compliment.
2. Sales Number.
3. Keterangan Transaksi.
4. Minimal satu foto bukti.

Pengguna dapat menambah lebih dari satu Compliment dalam satu shift.

### 9.2 Sales Number

- Label UI: **Sales Number**.
- Sumber pilihan utama adalah snapshot transaksi ESB pada Sales Report, branch, tanggal, dan shift yang sama.
- Picker harus searchable dan menampilkan Sales Number serta informasi ringkas yang membantu identifikasi transaksi.
- Nilai yang disimpan tidak hanya foreign key lokal. Simpan snapshot Sales Number dan konteks ESB agar export lama tetap stabil meski snapshot transaksi berubah.
- Bila snapshot ESB belum tersedia, pengguna dapat mengetik Sales Number secara manual setelah memilih opsi manual yang eksplisit. Record diberi penanda `manual`/`unverified`, bukan dianggap cocok dengan ESB.
- Normalisasi hanya boleh memangkas spasi; jangan mengubah karakter atau leading zero Sales Number.
- Duplikat persis dalam shift yang sama harus diberi validasi yang jelas. Constraint database final ditentukan setelah audit apakah satu transaksi sah memiliki lebih dari satu jenis Compliment.

### 9.3 Keterangan Transaksi

- Field `notes` existing dapat dipertahankan dan label UI diubah menjadi **Keterangan Transaksi**.
- Wajib, string, maksimal 2.000 karakter.
- Data existing tetap ditampilkan sebagai Keterangan Transaksi tanpa backfill isi buatan.

### 9.4 Foto Compliment

- Submission baru menerima `jpg`, `jpeg`, `png`, atau `webp`.
- Minimal satu file; batas maksimum ukuran per file mengikuti baseline existing, yaitu 5 MB, kecuali kebijakan upload global lebih ketat.
- Preview, progress, error, dan hapus file harus terlihat.
- File disimpan pada private disk existing dan hanya dibuka melalui URL sementara/endpoint terotorisasi.
- PDF Compliment lama tetap dapat dilihat; larangan PDF hanya berlaku pada submission baru.
- Jika transaksi database gagal setelah file tersimpan, file baru harus dibersihkan secara best-effort.

### 9.5 Snapshot yang disimpan

Field konseptual minimum pada Compliment:

| Field | Tipe konseptual | Aturan |
| --- | --- | --- |
| `sales_report_id` | FK | Wajib |
| `shift_number` | tiny integer | Wajib |
| `sales_number` | string | Wajib untuk record baru |
| `sales_number_source` | enum/string | `esb_snapshot`, `manual`, atau `legacy` |
| `source_comcode` | string nullable | Snapshot konteks transaksi |
| `source_branch_code` | string nullable | Snapshot konteks transaksi |
| `sales_transaction_at` | datetime nullable | Snapshot waktu transaksi bila tersedia |
| `compliment_type_id` | FK nullable | Relasi master existing |
| `compliment_type_name` | string | Snapshot nama existing |
| `notes` | text | Keterangan transaksi |
| `attachment_paths` | JSON | Bukti foto; struktur existing dipertahankan |
| `submitted_by` | FK nullable | Audit submitter |

Kolom baru harus nullable pada migration awal agar record lama tetap valid. Kewajiban field diberlakukan pada validasi submission baru, bukan dengan memalsukan nilai historis.

## 10. Kebutuhan Void

### 10.1 Aturan pengisian

Bagian Void bersifat opsional. Jika pengguna menekan **Tambah Void**, setiap baris wajib mempunyai:

1. Sales Number.
2. Payment Method.
3. Alasan Void.
4. Foto Nota Awal.
5. Foto Nota Setelah Void.

Sales Number ditambahkan sebagai kewajiban meskipun tidak disebut pada daftar field awal karena tanpa identitas transaksi, export Void tidak dapat dibandingkan secara andal dengan laporan ESB.

### 10.2 Sales Number Void

- Mengikuti picker dan aturan sumber Compliment.
- Dipilih dari transaksi branch, tanggal, dan shift yang sama bila snapshot tersedia.
- Mode manual diperbolehkan secara eksplisit dan diberi status `unverified`.
- Snapshot company, branch, waktu transaksi, dan nominal diambil ketika tersedia.

### 10.3 Payment Method

- Input menggunakan searchable select, bukan free text sebagai default.
- Sumbernya adalah payment method yang tersedia pada Sales Report/ESB untuk konteks branch yang sama.
- Simpan ID/kode bila tersedia dan selalu simpan nama sebagai snapshot.
- Jika master tidak tersedia, mode manual harus eksplisit dan tercatat.
- Payment method yang sudah tidak aktif tetap tampil pada record historis melalui snapshot nama.

### 10.4 Alasan Void

- Wajib, string, maksimal 2.000 karakter.
- Label UI: **Alasan Void**.
- Tidak boleh digantikan hanya dengan pilihan kategori tanpa keterangan.

### 10.5 Bukti nota

- **Foto Nota Awal** dan **Foto Nota Setelah Void** merupakan dua field terpisah.
- Masing-masing wajib tepat satu gambar untuk submission baru.
- Tipe file: `jpg`, `jpeg`, `png`, atau `webp`; maksimum 5 MB per file kecuali kebijakan global lebih ketat.
- Preview dan nama tahap bukti harus jelas agar pengguna tidak tertukar.
- File menggunakan private disk dan lifecycle yang sama dengan Compliment.

### 10.6 Entitas data Void

Buat entitas domain terpisah, misalnya `SalesReportVoid`; jangan menyimpan Void di tabel Compliment dengan discriminator.

| Field | Tipe konseptual | Aturan |
| --- | --- | --- |
| `id` | bigint | Primary key |
| `sales_report_id` | FK | Wajib, cascade sesuai lifecycle Sales Report existing |
| `shift_number` | tiny integer | Wajib |
| `sales_number` | string | Wajib untuk record baru |
| `sales_number_source` | enum/string | `esb_snapshot`, `manual`, atau `legacy` |
| `source_comcode` | string nullable | Snapshot ESB |
| `source_branch_code` | string nullable | Snapshot ESB |
| `sales_transaction_at` | datetime nullable | Snapshot ESB |
| `transaction_amount` | decimal nullable | Snapshot nominal untuk compare |
| `payment_method_id` | string/integer nullable | ID/kode sumber bila tersedia |
| `payment_method_name` | string | Snapshot nama wajib |
| `reason` | text | Alasan Void wajib |
| `original_receipt_path` | string | Foto nota awal |
| `voided_receipt_path` | string | Foto setelah void |
| `submitted_by` | FK nullable | Audit submitter |
| timestamps | timestamp | Wajib |

Index minimum:

- `sales_report_id, shift_number`;
- `sales_number`;
- index tambahan branch/tanggal memakai relasi Sales Report terlebih dahulu dan hanya ditambah jika hasil `EXPLAIN` membuktikan perlu.

## 11. Laporan dan export Compliment

### 11.1 Halaman laporan

Sediakan area laporan Compliment di bawah navigasi Sales Report. Implementasi dapat memakai sub-navigation/tabs Filament existing selama tidak membuat menu sidebar baru yang berlebihan.

Filter minimum:

- rentang tanggal;
- branch;
- shift;
- jenis Compliment;
- Sales Number;
- sumber Sales Number: terverifikasi ESB/manual/legacy.

Tabel minimum:

- tanggal;
- branch;
- shift;
- Sales Number;
- jenis Compliment;
- keterangan transaksi ringkas;
- submitter;
- jumlah bukti;
- status kecocokan transaksi ESB.

Detail tetap menyediakan akses terotorisasi ke foto dan konteks Sales Report asal.

### 11.2 Export Excel Compliment

Satu baris mewakili satu record Compliment, bukan satu Sales Report.

Urutan kolom baseline:

1. Compliment ID.
2. Sales Report ID.
3. Tanggal Laporan.
4. Branch Code.
5. Branch Name.
6. Company Code.
7. Shift.
8. Sales Number.
9. Sumber Sales Number.
10. Tanggal/Waktu Transaksi ESB.
11. Jenis Compliment.
12. Keterangan Transaksi.
13. Jumlah Foto.
14. Bukti Tersedia.
15. Disubmit Oleh.
16. Waktu Submit.
17. Status Sales Report.

Export harus mengikuti seluruh filter aktif dan branch scope pengguna. Nama kolom final dapat diselaraskan setelah Finance memberikan contoh report ESB yang benar-benar dipakai untuk compare.

## 12. Laporan dan export Void

### 12.1 Halaman laporan

Filter minimum:

- rentang tanggal;
- branch;
- shift;
- Sales Number;
- payment method;
- sumber Sales Number: terverifikasi ESB/manual/legacy.

Tabel minimum:

- tanggal;
- branch;
- shift;
- Sales Number;
- payment method;
- alasan Void ringkas;
- status foto awal;
- status foto setelah void;
- submitter;
- status kecocokan transaksi ESB.

### 12.2 Export Excel Void

Satu baris mewakili satu record Void.

Urutan kolom baseline:

1. Void ID.
2. Sales Report ID.
3. Tanggal Laporan.
4. Branch Code.
5. Branch Name.
6. Company Code.
7. Shift.
8. Sales Number.
9. Sumber Sales Number.
10. Tanggal/Waktu Transaksi ESB.
11. Nilai Transaksi ESB.
12. Payment Method.
13. Alasan Void.
14. Foto Nota Awal Tersedia.
15. Foto Setelah Void Tersedia.
16. Disubmit Oleh.
17. Waktu Submit.
18. Status Sales Report.

Nama dan urutan final dikunci setelah sample export ESB diterima.

## 13. Kontrak teknis export

- Gunakan pola XLSX streaming existing; jangan mengumpulkan seluruh dataset ke memory.
- Query wajib eager-load relasi yang dipakai dan diproses dengan `chunkById()`/cursor yang aman.
- Export harus memakai query yang sama dengan filter halaman, bukan query global baru.
- Semua text cell harus dilindungi dari formula injection untuk awalan `=`, `+`, `-`, dan `@`.
- Sales Number diperlakukan sebagai string agar leading zero tidak hilang.
- Tanggal/waktu memakai zona waktu aplikasi dan format konsisten.
- Kolom bukti cukup menyatakan tersedia/jumlah. Jangan mengekspor temporary URL yang cepat kedaluwarsa sebagai data permanen.
- Untuk export besar, implementasi lanjutan dapat memakai queue dan file expiry; tahap awal boleh synchronous selama benchmark membuktikan aman.
- Nama file memuat jenis laporan dan rentang tanggal.

## 14. UI aplikasi Store

### 14.1 Struktur halaman

Pertahankan halaman Sales Report shift yang sudah dikenal. Urutan section yang disarankan:

1. Informasi tanggal dan shift.
2. Staff in charge.
3. Data Sales per payment method.
4. Compliment.
5. Void.
6. Ringkasan dan konfirmasi submit.

Compliment dan Void menggunakan pola repeatable section yang sama, tetapi tetap dua domain berbeda.

### 14.2 Standar visual

- Container: border tipis, `rounded-2xl`, tanpa shadow kartu biasa.
- Section input: `rounded-xl`, border abu-abu, padding konsisten.
- Aksi utama biru; hapus baris merah; aksi sekunder border/netral.
- Label wajib terlihat dan memiliki penanda `*`.
- Helper text ditempatkan tepat di bawah field terkait.
- Validation error tampil dekat field dan input tidak hilang.
- Select Sales Number dan Payment Method memakai searchable picker yang nyaman di mobile, bukan native multi-select.
- Tombol submit mempunyai loading state dan mencegah double submit.
- File uploader menampilkan preview, progres, tipe file, batas ukuran, serta tombol hapus.
- Gunakan Heroicons/komponen existing; SVG manual baru dihindari.

### 14.3 Responsive

- Layout default satu kolom pada 360/390 px.
- Dua kolom hanya untuk pasangan field yang tetap terbaca pada tablet/desktop.
- Tidak boleh ada horizontal scroll pada seluruh halaman.
- Picker/modal dibatasi sekitar 90vh dan body-nya dapat scroll.
- Nama branch, Sales Number panjang, alasan panjang, dan error message harus wrap.

### 14.4 State UI

| State | Perilaku |
| --- | --- |
| Belum ada Compliment/Void | Empty state ringkas dan tombol tambah |
| ESB belum dimuat | Jelaskan pilihan manual sebagai data belum terverifikasi |
| Loading transaksi | Indikator pada picker/section, bukan mengganti dengan empty state |
| Upload berjalan | Progress dan aksi submit disabled |
| Validasi gagal | Baris tetap terbuka dan input dipertahankan |
| Shift sudah disubmit | Compliment/Void read-only seperti data Sales Report lain |
| File lama PDF | Tetap dapat dibuka dengan label file lama; tidak ditawarkan pada uploader baru |

## 15. UI Back Office

### 15.1 Navigasi

Gunakan sub-navigation atau tab di area Sales Report:

- **Laporan Harian**
- **Compliment**
- **Void**

Jangan membuat tiga resource yang tampil terpisah dan tidak berhubungan di sidebar bila sub-navigation Filament existing dapat digunakan.

### 15.2 Detail Sales Report

- Header menampilkan branch, tanggal, dan status.
- Section Compliment menampilkan Sales Number, jenis, keterangan, submitter, status verifikasi ESB, dan bukti.
- Section Void menampilkan Sales Number, payment method, alasan, dua bukti, submitter, dan status verifikasi ESB.
- Review History tetap menampilkan histori Supervisor, Finance lama, dan Finance reject baru.
- Untuk laporan `Completed`, Finance hanya melihat form alasan dan tombol **Reject Report**.
- Tidak ada tombol **Set as Completed**, **Approve Finance**, atau **Set as Finance Review** pada workflow baru.
- Copy konfirmasi reject harus menyebut dampak nilai 0.

### 15.3 Tabel laporan

- Filter diletakkan dekat tabel dan stack di mobile.
- Tabel berada dalam container berborder tanpa shadow.
- Horizontal scroll hanya di container tabel bila benar-benar diperlukan.
- Kolom yang kurang penting dipindahkan ke detail, bukan memaksa tabel terlalu lebar.
- Empty state membedakan tidak ada data dari gagal memuat data.
- Export berada pada area filter/list dan mengikuti filter aktif.

## 16. Authorization dan branch scope

Permission konseptual minimum:

| Kemampuan | Permission |
| --- | --- |
| Melihat Sales Report | Pertahankan `view sales reports` |
| Review Supervisor | Pertahankan `review sales reports as supervisor` |
| Reject Finance | Pertahankan permission Finance existing atau migrasikan ke permission bernama eksplisit melalui rollout aman |
| Melihat laporan Compliment | Dapat mengikuti `view sales reports` pada tahap awal |
| Export Compliment | Permission eksplisit direkomendasikan bila export perlu dibatasi |
| Melihat laporan Void | Dapat mengikuti `view sales reports` pada tahap awal |
| Export Void | Permission eksplisit direkomendasikan bila export perlu dibatasi |

Aturan wajib:

- Semua list, detail, export, download attachment, dan mutation menggunakan branch scope server-side.
- Pengguna tanpa `access_all_branches` hanya dapat melihat branch dari `accessibleBranchIds()`.
- ID record dari request tidak boleh dipercaya tanpa policy/scope.
- Submitter tidak boleh meninjau laporan sendiri kecuali pengecualian administrator existing.
- URL attachment bersifat sementara dan hanya dibuat setelah authorization record.
- Permission sync harus diuji pada seeder; jangan memberikan akses baru ke semua role secara massal.

## 17. Arsitektur implementasi target

Sesuai `code-remediation-prd.md`, Page/Resource hanya menangani state UI, presentasi, dan feedback.

```text
Store Sales Report Page
  → SaveSalesReportShiftAction
      → ComplimentInput / VoidInput validation mapping
      → attachment service
      → database transaction
      → SalesReportReconciliationService

Helpdesk Sales Report Detail
  → ApproveSalesReportAsSupervisorAction
  → RejectCompletedSalesReportAction

Compliment/Void Report Page
  → scoped query object
  → ExportSalesReportComplimentsXlsxAction
  → ExportSalesReportVoidsXlsxAction
```

### 17.1 Batas tanggung jawab

| Lapisan | Tanggung jawab |
| --- | --- |
| Livewire Page | State form, membuka/menutup modal, feedback, memanggil Action |
| Form/validator | Validasi shape input dan pesan per field |
| Action | Authorization use case, transaction boundary, lock, state transition, audit |
| Service | Upload/lifecycle attachment, rekonsiliasi atau kalkulasi reusable |
| Model | Relasi, cast, scope kohesif |
| Policy/scope | Record access dan branch scope |
| Export Action | Query streaming, format kolom, sanitasi cell |
| Blade | Presentasi tanpa query atau mutation |

### 17.2 Guardrail implementasi

- Jangan menambah logika status baru langsung ke Blade.
- Jangan melakukan query daftar transaksi di dalam loop view.
- Jangan melakukan upload permanen tanpa cleanup ketika transaksi gagal.
- Jangan membungkus HTTP ESB yang lambat di dalam database transaction.
- Snapshot ESB yang sudah tersedia dipakai untuk picker; refresh ESB tetap melalui service existing.
- Mutation status wajib retry-safe dan menolak expected status yang sudah berubah.

## 18. Strategi database dan migrasi data

### 18.1 Prinsip

- Migration bersifat additive terlebih dahulu.
- Kolom historis tidak dihapus pada rollout pertama.
- Backfill tidak boleh menebak Sales Number, payment method, atau bukti.
- Setiap data update massal wajib mempunyai dry-run/report jumlah record sebelum execute.
- Production migration dan backfill memerlukan instruksi deployment terpisah.

### 18.2 Compliment lama

- Tambahkan kolom snapshot baru sebagai nullable.
- Record existing diberi `sales_number_source = legacy` bila penanda dibutuhkan.
- Sales Number lama tetap `null`; UI menampilkan **Data lama — Sales Number belum tersedia**, bukan nilai palsu.
- Attachment PDF/gambar existing dipertahankan.
- Export memasukkan record lama dengan Sales Number kosong dan status sumber `legacy` agar selisih dapat dijelaskan.

### 18.3 Void

- Buat tabel baru beserta factory.
- Tidak ada backfill Void dari notes atau attachment Compliment karena klasifikasinya tidak dapat dibuktikan.
- Data mulai tercatat sejak fitur diaktifkan.

### 18.4 Status `PendingFinance` lama

Sebelum migrasi, buat laporan preview:

- jumlah record `PendingFinance` per branch dan bulan;
- apakah `supervisor_reviewed_at` dan `supervisor_reviewed_by` tersedia;
- apakah ada histori `supervisor/approved`;
- apakah ada anomali record tanpa Supervisor approval;
- jumlah record yang mempunyai data Finance/settlement parsial.

Aturan konversi:

1. Record `PendingFinance` yang mempunyai bukti Supervisor approval sah dikonversi ke `Completed`.
2. Konversi tidak membuat Finance approval palsu.
3. Buat audit metadata `source=workflow_migration`, `previous_status=pending_finance` pada event migrasi atau log migrasi khusus.
4. Record tanpa bukti Supervisor approval tidak dikonversi otomatis; masuk daftar exception untuk review manual.
5. Field `finance_reviewed_*`, `finance_note`, reconciliation, dan settlement existing tidak dihapus atau ditimpa.

### 18.5 Enum `PendingFinance`

- Pada phase pertama, pertahankan enum untuk membaca histori/record anomali dan kompatibilitas rollback.
- Workflow baru tidak boleh menghasilkan `PendingFinance`.
- Penghapusan enum/compatibility hanya boleh dilakukan pada phase cleanup setelah database audit membuktikan tidak ada record dan tidak ada referensi kode.

## 19. Compatibility dan rollback

### 19.1 Feature flag

Rollout workflow disarankan memakai konfigurasi/feature flag yang aman, misalnya versi workflow, bukan boolean tersebar pada Blade. Default harus mengikuti keputusan deployment environment.

Tujuannya:

- code dapat dirilis sebelum migrasi status selesai;
- rollback aplikasi tidak membuat status baru tidak terbaca;
- staging dapat diuji dengan alur baru tanpa langsung mengubah production.

### 19.2 Rollback aplikasi

- Karena kolom/tabel ditambahkan secara additive, rollback kode tidak perlu menghapus data Compliment/Void baru.
- Jika workflow flag dikembalikan, record `Completed` hasil Supervisor tetap valid untuk kode lama.
- Jangan otomatis mengembalikan `Completed` menjadi `PendingFinance`; operasi tersebut mengubah nilai dan memerlukan keputusan bisnis.
- File yang sudah diupload tidak dihapus saat rollback aplikasi.

### 19.3 Rollback data

- Backfill status wajib membuat snapshot ID, status awal, status akhir, dan timestamp eksekusi.
- Restore hanya mengubah record yang masih mempunyai nilai hasil migrasi dan belum dimutasi setelah migrasi.
- Restore tidak boleh menimpa reject Finance yang terjadi setelah rollout.

## 20. Observability dan audit

- Seluruh transisi status menyimpan actor, waktu, stage, action, notes, revision, dan metadata status sebelumnya.
- Upload failure dicatat tanpa path sensitif atau temporary URL.
- Export dapat mencatat actor, filter, jumlah baris, waktu, dan hasil bila pola audit export tersedia.
- Manual Sales Number/payment method diberi penanda sehingga Finance dapat memfilter data yang belum terverifikasi.
- Error UI tidak menampilkan exception, credential ESB, disk key, atau path internal.
- Metrik rollout minimum:
  - jumlah Supervisor approve;
  - jumlah Finance reject setelah Completed;
  - jumlah Compliment/Void;
  - persentase Sales Number dari ESB vs manual;
  - upload failure;
  - durasi dan jumlah row export;
  - jumlah legacy `PendingFinance` tersisa.

## 21. Pengujian wajib

### 21.1 Characterization sebelum refactor

- Seluruh shift lengkap menghasilkan `PendingSupervisor`.
- Branch scope pada list/detail/export.
- Supervisor correction dan notes existing.
- Finance reject Completed existing mengubah score menjadi 0.
- Auto-reject hanya memproses `PendingSupervisor`.
- File Compliment existing dapat dibaca dan dihapus sesuai lifecycle.
- Export umum tetap aman terhadap formula injection.

### 21.2 Workflow baru

- Supervisor approve mengubah `PendingSupervisor` langsung ke `Completed`.
- Tidak ada tombol/method Finance approve yang dapat dipanggil.
- Finance tidak dapat reject `Draft`, `PendingSupervisor`, `Rejected`, atau `RejectedBySystem`.
- Finance dapat reject `Completed` dengan alasan valid.
- Alasan kosong/terlalu pendek ditolak dan status tidak berubah.
- Actor tanpa permission atau di luar branch scope mendapat 403.
- Concurrent approve/reject tidak membuat audit ganda.
- Completed menghasilkan score 100 dan reject Finance mengubahnya menjadi 0.
- Auto-reject tidak menyentuh `Completed`.

### 21.3 Compliment

- Baris Compliment tidak wajib jika tidak ditambahkan.
- Jika ditambahkan: jenis, Sales Number, keterangan, dan foto wajib.
- Sales Number dari shift/branch/tanggal lain tidak dapat dipilih sebagai verified.
- Mode manual tersimpan sebagai manual/unverified.
- File non-image ditolak pada submission baru; PDF lama tetap dapat diakses.
- Attachment dibersihkan jika transaksi database gagal.
- Record lama dengan Sales Number null tetap dapat dibuka dan diekspor.

### 21.4 Void

- Baris Void tidak wajib jika tidak ditambahkan.
- Semua lima field wajib ketika ditambahkan.
- Foto nota awal dan setelah void tidak boleh tertukar atau memakai field yang sama.
- Payment method snapshot tetap tampil jika master dinonaktifkan.
- Mode manual ditandai.
- Authorization download bukti diuji.
- Penghapusan Sales Report mengikuti lifecycle file yang disepakati tanpa orphan.

### 21.5 Export

- Export Compliment/Void hanya memuat branch yang dapat diakses.
- Filter tanggal, branch, shift, jenis/payment method, dan Sales Number diterapkan.
- Satu record menghasilkan satu row.
- Sales Number dengan leading zero tetap string.
- Cell berawalan formula disanitasi.
- Empty result menghasilkan file dengan header yang valid.
- Dataset besar diproses chunk tanpa eager-loading seluruh dataset.

### 21.6 UI

- Mobile 360 dan 390 px tidak mempunyai horizontal scroll halaman.
- Desktop 768, 1024, dan 1440 px diperiksa.
- Zoom 200%, keyboard focus, close modal, Escape, dan return focus diperiksa.
- Validation error mempertahankan input dan uploader.
- Loading state mencegah double submit/reject/export.
- Empty, loading, error, legacy, manual, dan read-only state dapat dibedakan.

## 22. Tahapan implementasi

### Phase 0 — Konfirmasi kontrak bisnis dan sample ESB

- Minta sample Excel ESB untuk Compliment dan Void.
- Konfirmasi apakah satu Sales Number dapat mempunyai lebih dari satu Compliment/Void.
- Konfirmasi daftar payment method yang dianggap sumber resmi.
- Konfirmasi apakah export memerlukan nama/path bukti atau hanya indikator tersedia.

**Gate:** requirement yang memengaruhi schema unik dan header export sudah diputuskan. Implementasi workflow dapat dimulai sebelum format Excel final, tetapi export production belum dinyatakan final.

### Phase 1 — Safety net dan audit data

- Tambah/ubah characterization tests.
- Audit jumlah status dan data `PendingFinance`.
- Audit attachment legacy serta ukuran dataset export.
- Rekam baseline query dan waktu halaman/detail/export.

**Gate:** perilaku existing dan anomali data diketahui; belum ada mutation production.

### Phase 2 — Ekstraksi Action workflow

- Ekstrak Supervisor approval dan Finance reject dari Page ke Action.
- Pertahankan authorization, lock, transaction, audit, dan notification contract.
- Pastikan behavior existing tetap lulus sebelum mengubah transisi.

**Gate:** Page lebih tipis dan characterization test hijau.

### Phase 3 — Workflow baru

- Supervisor approve langsung ke `Completed`.
- Hapus aksi approve Finance dari UI dan jalur callable.
- Pertahankan Finance reject Completed.
- Sesuaikan label, filter, badge, export umum, test, dan auto-reject expectation.

**Gate:** tidak ada workflow baru yang menghasilkan `PendingFinance`.

### Phase 4 — Schema Compliment dan Void

- Migration additive Compliment.
- Model, factory, migration, relation, policy/scope Void.
- Attachment lifecycle dan validation contract.
- Tidak menjalankan backfill tebakan.

**Gate:** legacy data masih terbaca dan rollback kode aman.

### Phase 5 — Input aplikasi Store

- Tambah picker Sales Number dan field Compliment.
- Tambah repeatable Void.
- Ekstrak save action/mapping agar Page tidak semakin besar.
- Implementasikan seluruh loading, validation, upload, cleanup, dan read-only state.

**Gate:** workflow shift lama tetap berfungsi dan form lolos mobile review.

### Phase 6 — Back office dan export

- Tambah sub-navigation/tab Compliment dan Void.
- Tambah filter, detail, attachment access, dan export terpisah.
- Selaraskan header Excel dengan sample ESB.

**Gate:** hasil export dapat dibandingkan Finance tanpa manipulasi manual struktural.

### Phase 7 — Staging migration dan UAT

- Deploy kode kompatibel.
- Jalankan preview `PendingFinance`.
- Migrasikan record sah di staging dengan snapshot.
- Uji Supervisor approve, Finance reject, score, Compliment, Void, dan export.
- Uji role serta branch berbeda.

**Gate:** UAT Store, Supervisor, dan Finance disetujui; exception list terselesaikan.

### Phase 8 — Production rollout

- Backup database dan verifikasi private storage.
- Deploy migration additive.
- Aktifkan workflow sesuai runbook.
- Jalankan migrasi status yang telah di-preview dan disetujui.
- Pantau log, score, export, upload, queue, dan jumlah `PendingFinance`.

**Gate:** tidak ada data hilang, tidak ada akses lintas branch, dan hasil score sesuai sample.

### Phase 9 — Cleanup tertunda

- Audit seluruh referensi `PendingFinance`, finance approve, dan compatibility code.
- Hapus hanya jika tidak ada record/consumer dan regression test membuktikan aman.
- Kolom histori Finance tidak otomatis dihapus.

**Gate:** cleanup terpisah dari feature rollout dan mempunyai rollback note.

## 23. Acceptance criteria

### Workflow

- [ ] Supervisor approval langsung menghasilkan `Completed`.
- [ ] Finance tidak mempunyai aksi approve.
- [ ] Finance hanya dapat reject record `Completed` yang berada dalam scope aksesnya.
- [ ] Reject wajib alasan dan tercatat pada audit trail.
- [ ] Reject mengubah nilai tanggal dari 100 menjadi 0 tanpa edit manual.
- [ ] Auto-reject tetap hanya memproses Supervisor Review.
- [ ] Workflow baru tidak menghasilkan `PendingFinance`.

### Compliment

- [ ] Section boleh kosong.
- [ ] Setiap baris baru wajib jenis, Sales Number, keterangan transaksi, dan foto.
- [ ] Sales Number verified berasal dari transaksi dengan konteks report yang sama.
- [ ] Mode manual diberi tanda unverified.
- [ ] Data dan file lama tetap dapat dibuka.
- [ ] Laporan dan export Compliment mengikuti filter serta branch scope.

### Void

- [ ] Section boleh kosong.
- [ ] Setiap baris baru wajib Sales Number, payment method, alasan, foto awal, dan foto setelah void.
- [ ] Dua bukti disimpan pada field berbeda dan hanya dapat diakses user berizin.
- [ ] Laporan dan export Void mengikuti filter serta branch scope.

### UI dan teknis

- [ ] UI konsisten dengan `ui-consistency-prd.md`, tanpa shadow kartu biasa.
- [ ] Tidak ada horizontal scroll pada seluruh halaman mobile.
- [ ] Page/Blade tidak memuat business transaction baru secara langsung.
- [ ] Export streaming, chunked, dan aman dari formula injection.
- [ ] Migration additive dan tidak membuat nilai historis palsu.
- [ ] Focused Pest tests, formatter, `git diff --check`, dan review visual lulus.

## 24. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Record `PendingFinance` tertinggal | Preview, konversi berbasis bukti Supervisor, exception list |
| Nilai berubah tidak terduga | Test status-to-score dan sampling sebelum/sesudah migrasi |
| Finance reject record di luar akses | Policy dan scoped query server-side |
| Sales Number salah | Picker scoped, snapshot, mode manual eksplisit, filter unverified |
| File lama tidak terbaca | Migration nullable dan legacy compatibility |
| Upload meninggalkan orphan | Cleanup saat failure dan lifecycle test |
| Export tidak cocok dengan ESB | Kunci header setelah menerima sample ESB |
| Dataset export berat | Streaming, eager loading terbatas, chunk, benchmark, queue bila perlu |
| Page Sales Report makin besar | Ekstraksi Action/Service dan query object |
| Double click menghasilkan transisi ganda | Lock, expected-status guard, dan idempotency test |
| Rollback mengubah score lagi | Rollback aplikasi non-destruktif; data restore terkontrol |

## 25. Dependency dan keputusan yang masih harus dikonfirmasi

Sebelum format export dinyatakan final, dibutuhkan:

1. Contoh file/report ESB yang dipakai Finance untuk compare Compliment.
2. Contoh file/report ESB yang dipakai Finance untuk compare Void.
3. Kepastian apakah satu Sales Number boleh muncul pada beberapa record dengan jenis berbeda.
4. Kepastian sumber master Payment Method ketika transaksi ESB belum berhasil dimuat.

Keempat hal ini tidak menghalangi pembuatan safety net, ekstraksi workflow, dan schema additive. Namun, unique constraint serta header export final tidak boleh ditebak.

## 26. Quality gates

Setiap phase implementasi kode wajib menjalankan yang relevan:

1. Cari dokumentasi Laravel/Filament/Livewire versi terpasang sebelum perubahan kode.
2. Gunakan migration dan class generator Laravel sesuai konvensi project.
3. Jalankan focused test dengan `php artisan test --compact` pada file/filter terdampak.
4. Jalankan `vendor/bin/pint --dirty --format agent` bila PHP berubah.
5. Jalankan `git diff --check`.
6. Jalankan frontend build bila source asset berubah.
7. Periksa route/Filament discovery bila navigation/resource berubah.
8. Review visual pada viewport yang ditentukan.
9. Sertakan deployment, data migration, observability, dan rollback note.
10. Jangan commit, push, atau deploy tanpa instruksi eksplisit.

## 27. Definition of Done

Fitur dianggap selesai hanya ketika:

- workflow baru aktif tanpa menghasilkan `PendingFinance`;
- histori dan data lama tetap tersedia;
- Supervisor approval dan Finance rejection mempunyai audit yang benar;
- score berubah sesuai status;
- input Compliment dan Void tervalidasi serta aman pada mobile;
- laporan dan export dapat dipakai Finance untuk compare ESB;
- branch scope dan attachment authorization teruji;
- migration status selesai tanpa exception yang tidak ditangani;
- focused tests, formatter, build terkait, visual QA, dan UAT lulus;
- runbook deployment dan rollback telah diverifikasi.

Penyelesaian dokumen ini sendiri tidak mencakup implementasi kode, migration, perubahan permission, commit, push, atau deployment.
