# Prompt Implementasi — Revisi Workflow, Compliment, dan Void Sales Report

Salin seluruh prompt berikut ke agent implementasi dari root repository.

```text
Implementasikan revisi modul Sales Report secara end-to-end berdasarkan dokumen berikut:

- `docs/products/finance/sales-report-compliment-void-remediation/prd.md` — sumber requirement produk terbaru dan utama untuk scope ini.
- `docs/standards/code-remediation.md` — guardrail arsitektur, keamanan data, performa, test, migrasi, dan remediation.
- `docs/standards/ui-consistency.md` — guardrail UI, responsive behavior, form, modal, table, state, dan accessibility.
- dokumentasi Sales Report lain yang masih aktif — referensi perilaku existing selama tidak bertentangan dengan PRD utama.

Kerjakan implementasi sampai aman, teruji, dan siap di-UAT. Jangan berhenti pada planning, audit, skeleton, contoh kode, atau TODO. Gunakan kondisi repository aktual sebagai fakta. Requirement terbaru dalam PRD utama mengalahkan asumsi lama untuk scope ini.

Outcome bisnis final:

1. Workflow laporan menjadi `Draft → Supervisor Review → Completed`.
2. Supervisor approval langsung membuat laporan `Completed` dan bernilai 100 pada tanggal penilaian yang wajib.
3. Approval Finance dihapus. Workflow baru tidak boleh menghasilkan `PendingFinance`.
4. Finance hanya dapat menolak Sales Report berstatus `Completed` dengan alasan wajib.
5. Finance rejection mengubah status menjadi `Rejected`, tercatat pada audit trail, dan membuat nilai store pada tanggal tersebut menjadi 0.
6. Bagian Compliment tetap opsional. Jika baris ditambahkan, jenis Compliment, Sales Number, Keterangan Transaksi, dan minimal satu foto wajib diisi.
7. Bagian Void tetap opsional. Jika baris ditambahkan, Sales Number, Payment Method, Alasan Void, Foto Nota Awal, dan Foto Nota Setelah Void wajib diisi.
8. Sales Number default dipilih dari snapshot transaksi ESB pada report, branch, tanggal, dan shift yang sama. Mode manual diperbolehkan secara eksplisit dan ditandai unverified.
9. Back office menyediakan laporan terpisah Compliment dan Void dengan filter, detail, branch scope, dan export XLSX.
10. Data, attachment, approval Finance, settlement/MDR, dan histori lama tidak boleh dihapus atau dipalsukan.
11. Record `PendingFinance` lama hanya dikonversi ke `Completed` bila bukti Supervisor approval sah tersedia. Anomali tidak boleh ditebak.
12. UI mengikuti standar Help Bloomery dan tetap nyaman pada mobile.

## 1. Aturan kerja wajib

1. Baca `AGENTS.md` sampai selesai dan patuhi seluruh Laravel Boost Guidelines.
2. Baca seluruh dokumen sumber sampai selesai sebelum mengubah kode.
3. Audit `git status --short --branch`, `git diff --stat`, dan diff seluruh file terkait. Pertahankan perubahan user yang sudah ada; jangan menimpa, merapikan, memasukkan, atau menghapus perubahan unrelated.
4. Gunakan Laravel Boost sebagai pilihan utama untuk inspeksi aplikasi, schema, log, dan dokumentasi versi paket.
5. Sebelum code change, gunakan Laravel Boost `search-docs` dengan beberapa query broad dan package scope yang relevan. Verifikasi pola Laravel 13, Filament 5, Livewire 4, validation, authorization, database transaction, row locking, file upload/private storage, streaming download, dan Pest 4.
6. Gunakan `database-schema` sebelum mendesain migration, constraint, index, atau relation. Gunakan `database-query` hanya untuk inspeksi read-only.
7. Gunakan `rg`/`rg --files` untuk pencarian kode. Baca sibling files sebelum membuat class, Action, model, Resource, Page, view, atau test baru.
8. Gunakan `php artisan make:* --no-interaction` untuk file Laravel baru bila generator tersedia.
9. Jangan mengubah migration lama yang mungkin sudah dijalankan. Gunakan migration baru yang additive, reversible, dan aman bagi data existing.
10. Jangan menambah atau mengganti dependency tanpa persetujuan user.
11. Jangan menjalankan `git reset`, `git checkout --`, `git clean`, stash, hard delete data, atau operasi destruktif lain.
12. Jangan commit, push, membuka PR, deploy, menjalankan migration production, atau memutasi data production tanpa instruksi eksplisit terpisah.
13. Jangan mengubah `.env` user dan jangan hard-code credential, URL production, role ID, user ID, branch ID, company code, atau storage path sensitif.
14. Seluruh mutation wajib authorize server-side. Visibility tombol bukan authorization.
15. Seluruh query list, detail, export, dan attachment wajib menerapkan branch scope server-side.
16. Seluruh transisi status wajib memakai expected-state guard, database transaction, row lock, dan audit trail.
17. Jangan melakukan HTTP ESB dari Blade, render Livewire, Model event, atau di dalam database transaction.
18. Jangan query database dari Blade.
19. Jangan memuat seluruh transaksi, Compliment, Void, branch, atau payment method ke state browser. Gunakan server-side search, pagination, filter, dan lazy detail.
20. Jangan menghapus test existing. Tambah characterization test sebelum refactor behavior.
21. Jangan membuat approval, Sales Number, Payment Method, foto, reviewer, atau histori palsu untuk data lama.
22. Jangan menghapus enum/kolom `PendingFinance` atau field Finance lama pada rollout pertama.
23. Jangan menghapus dukungan membaca PDF Compliment lama. Submission baru saja yang image-only.
24. Jangan membuat export dengan temporary URL attachment sebagai data permanen.
25. Fake ESB/storage eksternal pada test dan larang request eksternal nyata sesuai pola test suite.
26. Jalankan focused test setelah setiap phase, bukan hanya pada akhir.
27. Jika PHP berubah, jalankan `vendor/bin/pint --dirty --format agent` sebelum finalisasi.
28. Jika frontend source/asset berubah, jalankan build sesuai script repository.
29. Jalankan `git diff --check` sebelum menyerahkan hasil.
30. Jangan membuat dokumentasi baru kecuali implementation report memang diminta atau menjadi convention repository.
31. Jika sample Excel ESB belum tersedia, implementasikan baseline export dari PRD, buat mapping header terpusat dan mudah diubah, lalu laporkan bahwa exact parity dengan file ESB belum dapat diverifikasi. Jangan menghentikan bagian aman lain.
32. Jika ditemukan konflik requirement yang dapat mengubah data atau workflow secara material, hentikan hanya bagian yang berisiko, selesaikan bagian aman, dan laporkan bukti serta keputusan yang diperlukan.

## 2. Kontrak domain yang tidak boleh berubah

1. Satu Sales Report mewakili satu branch dan satu tanggal dengan shift sesuai konfigurasi branch existing.
2. Laporan hanya masuk Supervisor Review setelah seluruh shift wajib terkirim.
3. Supervisor tetap memeriksa dan dapat mengoreksi data rekonsiliasi sesuai aturan existing.
4. Supervisor approval adalah satu-satunya approval positif pada workflow baru.
5. Finance tidak mempunyai approval positif; hanya reject terhadap `Completed`.
6. Finance rejection bersifat final pada scope ini. Jangan menambahkan reopen/resubmit baru.
7. `Completed` bernilai 100 dan `Rejected`/`RejectedBySystem` bernilai 0 sesuai kalkulator existing.
8. Auto-reject tetap hanya memproses laporan `PendingSupervisor` yang memenuhi settings existing.
9. Compliment dan Void opsional sebagai section, tetapi setiap row yang dibuat wajib lengkap.
10. Sales Number harus dipertahankan sebagai string; leading zero tidak boleh hilang.
11. Verified Sales Number hanya berasal dari snapshot transaksi dengan Sales Report, branch, tanggal, dan shift yang sesuai.
12. Input manual harus eksplisit dan disimpan sebagai `manual`/`unverified`.
13. Snapshot nama jenis Compliment dan Payment Method harus tetap terbaca walaupun master berubah atau dinonaktifkan.
14. Foto Compliment, foto nota awal, dan foto setelah Void adalah bukti privat yang memerlukan authorization.
15. Data historical tidak memperoleh field wajib baru secara palsu.
16. Settlement/MDR dan field Finance lama tidak dihapus dalam scope ini.

## 3. Phase 0 — Audit repository dan baseline

Lakukan audit read-only sebelum mengedit behavior.

### 3.1 Worktree dan ownership

1. Jalankan `git status --short --branch` dan `git diff --stat`.
2. Identifikasi perubahan user yang overlap dengan Sales Report.
3. Jangan menyentuh perubahan domain lain yang belum di-commit.
4. Petakan file aktual untuk:
   - `SalesReportStatus`;
   - `SalesReport`, `SalesReportApproval`, `SalesReportShiftSubmission`, `SalesReportEntry`, `SalesReportReconciliation`, dan `SalesReportEsbTransaction`;
   - `SalesReportCompliment`, migration, factory, relation, upload, dan UI;
   - Store/Casual Sales Report Page dan Blade;
   - Helpdesk Sales Report Resource, list, detail Page, table, dan Blade;
   - score calculator dan score page;
   - auto-reject command/settings/scheduler;
   - export XLSX existing;
   - permission config, seeder, policies/scopes;
   - storage disk, temporary URL, dan attachment download pattern;
   - seluruh test Sales Report.

### 3.2 Dokumentasi versi paket

Gunakan `search-docs` minimal untuk topik:

- Filament resource sub-navigation, table filter, actions, dan testing;
- Livewire file upload, validation nested arrays, locked identifiers, loading state, dan testing;
- Laravel database transaction, pessimistic locking, after-commit behavior, validation Rule, authorization policy, filesystem temporary URLs, streamed download, dan chunking;
- Pest/Livewire feature testing.

Jangan mengandalkan syntax versi package lain.

### 3.3 Schema aktual

Gunakan `database-schema` untuk memeriksa minimal:

- `sales_reports`;
- `sales_report_approvals`;
- `sales_report_shift_submissions`;
- `sales_report_entries`;
- `sales_report_reconciliations`;
- `sales_report_esb_transactions`;
- `sales_report_compliments`;
- branch, user, permission/role, dan tabel attachment terkait bila ada.

Catat foreign key, nullable, type, cast, index, unique constraint, delete behavior, serta volume data lokal/staging yang dapat diperiksa secara aman.

### 3.4 Baseline test dan performa

Jalankan test terfokus existing untuk:

- workflow approval Sales Report;
- score Sales Report;
- auto-reject;
- Compliment;
- export;
- branch scope dan permission;
- label/grouping dan reconciliation yang berhubungan.

Catat failure existing secara terpisah. Jangan mengubah test hanya agar hijau tanpa memahami contract.

Rekam baseline yang tersedia untuk:

- query detail Sales Report;
- ukuran state/payload halaman input shift;
- waktu export dan jumlah row pada fixture wajar;
- jumlah record per status pada database development yang aman.

### 3.5 Characterization tests wajib

Sebelum memindahkan logika, pastikan test membuktikan:

1. seluruh shift lengkap menghasilkan `PendingSupervisor`;
2. Supervisor hanya dapat meninjau record yang sah dan sesuai scope;
3. correction dan Supervisor notes existing tetap bekerja;
4. Finance reject terhadap `Completed` memerlukan alasan, menulis audit, dan membuat score 0;
5. actor tanpa permission atau di luar branch tidak dapat reject;
6. auto-reject hanya memproses `PendingSupervisor`;
7. attachment Compliment existing tersimpan, terbaca, dan dibersihkan sesuai lifecycle;
8. export existing memakai branch scope dan formula-injection protection;
9. route/navigation Sales Report penting tetap tersedia.

Jalankan test characterization sebelum melanjutkan.

## 4. Phase 1 — Ekstraksi workflow ke Action

Sebelum mengubah transisi, pindahkan tanggung jawab bisnis yang saat ini berada langsung di Page ke Action yang mengikuti convention repository.

Kandidat use case:

- `ApproveSalesReportAsSupervisorAction`;
- `RejectCompletedSalesReportAction`.

Nama final mengikuti sibling Action existing. Jangan membuat abstraction generik yang tidak dibutuhkan.

### 4.1 Kontrak Action Supervisor

Action harus:

1. menerima Sales Report, actor, correction rows, dan note secara eksplisit;
2. authorize server-side melalui policy/permission/scope existing;
3. membuka database transaction;
4. mengambil ulang Sales Report dengan `lockForUpdate()`;
5. memastikan expected status `PendingSupervisor`;
6. memvalidasi correction rows dan note sesuai behavior existing;
7. menyimpan correction/reconciliation secara atomic;
8. mengisi reviewer dan waktu Supervisor;
9. menulis tepat satu audit approval;
10. mengembalikan model fresh/result tanpa toast atau HTML.

Pada akhir Phase 1, pertahankan behavior lama bila perlu agar extraction dapat dibuktikan terpisah. Setelah test hijau, ubah destination status pada Phase 2.

### 4.2 Kontrak Action Finance Reject

Action harus:

1. menerima Sales Report, actor, dan alasan;
2. authorize permission, branch scope, dan self-review guard existing;
3. memastikan status `Completed`;
4. memvalidasi alasan 5–2.000 karakter;
5. lock row dalam transaction;
6. mengubah status ke `Rejected`;
7. mengisi reviewer/waktu Finance dan field compatibility yang benar-benar digunakan UI;
8. membuat audit `stage=finance`, `action=rejected`, dengan metadata `previous_status=completed`;
9. menolak double click/concurrent request tanpa audit ganda;
10. tidak menghapus correction, settlement, MDR, Compliment, Void, atau attachment.

### 4.3 Page setelah ekstraksi

Page hanya:

- mengelola state input;
- memanggil Action;
- refresh record;
- reset field yang sesuai;
- mengirim notification/toast.

Jalankan focused workflow tests setelah extraction.

## 5. Phase 2 — Aktifkan workflow baru

### 5.1 Supervisor langsung Completed

Ubah Action Supervisor sehingga:

- expected status tetap `PendingSupervisor`;
- next status menjadi `Completed`;
- `supervisor_reviewed_by`, `supervisor_reviewed_at`, dan `supervisor_note` tetap tersimpan;
- audit tetap `stage=supervisor`, `action=approved`;
- field Finance tidak dipalsukan;
- notification menyatakan laporan selesai diperiksa/Completed;
- kalkulator score langsung membaca 100 untuk tanggal tersebut.

### 5.2 Hapus approval Finance dari flow aktif

Hapus dari jalur aktif:

- tombol **Set as Finance Review**;
- tombol **Set as Completed** milik Finance;
- method/callable `approveFinance`;
- `canReviewAsFinance` bila hanya dipakai approval lama;
- transition baru ke `PendingFinance`;
- copy/UI yang mengatakan menunggu approval Finance;
- test yang mengharuskan dua tahap approval.

Jangan menghapus enum `PendingFinance`, field reviewer Finance, audit Finance lama, atau kemampuan membaca record legacy pada rollout ini.

### 5.3 Finance reject Completed

Pertahankan hanya aksi **Reject Report** untuk Finance pada `Completed`.

UI wajib:

- menampilkan textarea alasan wajib;
- menampilkan pesan bahwa status berubah menjadi Rejected dan nilai tanggal menjadi 0;
- memakai confirmation sebelum mutation;
- mempunyai loading/disabled state;
- tidak menampilkan tombol setelah reject berhasil.

### 5.4 Refetch ESB

Audit `canRefetchEsb()` dan caller existing:

- Supervisor tetap dapat refresh snapshot saat `PendingSupervisor` sesuai permission;
- jangan mempertahankan ketergantungan pada `PendingFinance` untuk workflow baru;
- jangan memberi Finance mutation/refetch tambahan hanya untuk menggantikan approval yang dihapus;
- compatibility read untuk record legacy boleh dipertahankan jika diperlukan dan diuji.

### 5.5 Auto-reject dan score

- Auto-reject tetap menargetkan `PendingSupervisor` saja.
- Jangan auto-reject `Completed` karena belum dilihat Finance.
- Test score harus membuktikan Supervisor approve = 100 dan Finance reject = 0.
- Invalidasi cache score bila ternyata ada cache pada audit aktual.

Jalankan focused workflow, score, dan auto-reject tests.

## 6. Phase 3 — Schema additive Compliment dan Void

Gunakan migration baru; jangan mengedit migration lama.

### 6.1 Extend Compliment

Tambahkan kolom minimum sesuai tipe aktual repository:

- `sales_number` nullable string;
- `sales_number_source` nullable string;
- `source_comcode` nullable string;
- `source_branch_code` nullable string;
- `sales_transaction_at` nullable datetime.

Pertimbangkan snapshot nominal hanya bila diperlukan export dan dapat dibuktikan dari transaksi. Jangan menyimpan field duplikat tanpa consumer.

Aturan:

- nullable untuk compatibility data lama;
- index Sales Number bila query/filter membutuhkannya dan pola query membuktikan manfaat;
- jangan membuat unique constraint sebelum aturan satu Sales Number vs banyak Compliment terkonfirmasi;
- update `$fillable`, casts, factory, dan factory states;
- relasi existing dan lifecycle attachment tetap bekerja.

Data lama tidak diisi Sales Number palsu. Jika perlu source, gunakan `legacy` hanya melalui migration/backfill terkontrol dan reversible; nilai `null` juga boleh dipresentasikan sebagai legacy bila lebih aman.

### 6.2 Buat entitas Void

Buat model/migration/factory, misalnya `SalesReportVoid`, mengikuti naming repository.

Field minimum:

- `sales_report_id` FK;
- `shift_number`;
- `sales_number` string;
- `sales_number_source` string;
- `source_comcode` nullable;
- `source_branch_code` nullable;
- `sales_transaction_at` nullable datetime;
- `transaction_amount` nullable decimal sesuai presisi uang existing;
- `payment_method_id` nullable dengan tipe yang sesuai sumber aktual;
- `payment_method_name` string snapshot;
- `reason` text;
- `original_receipt_path` string;
- `voided_receipt_path` string;
- `submitted_by` nullable FK;
- timestamps.

Tambahkan:

- index `sales_report_id, shift_number`;
- index `sales_number` bila filter membutuhkannya;
- relasi dari `SalesReport` ke Void;
- relasi submitter;
- casts eksplisit;
- attachment cleanup yang aman dan tidak melakukan delete ganda.

Jangan menyatukan Void ke tabel Compliment dan jangan membuat data Void dari notes lama.

### 6.3 Attachment lifecycle

Gunakan private disk/pattern existing. Pastikan:

- create sukses menyimpan path;
- failure setelah upload membersihkan file baru secara best-effort;
- delete record membersihkan file terkait sesuai lifecycle existing;
- delete Sales Report tidak meninggalkan orphan atau melakukan double delete;
- URL sementara hanya dibuat setelah authorization;
- test memakai fake storage.

Jalankan migration pada database test/lokal yang aman dan jalankan model/migration tests.

## 7. Phase 4 — Refactor penyimpanan shift

`SalesReportShiftPage` sudah besar. Jangan menambahkan seluruh proses Compliment/Void langsung ke method `save()`.

Ekstrak use case penyimpanan shift mengikuti convention repository, misalnya `SaveSalesReportShiftAction`, atau pisahkan mapper/attachment preparation yang kohesif bila refactor penuh terlalu berisiko.

Action harus menangani secara atomic:

- expected shift state dan duplicate submission guard;
- Sales entries;
- shift submission;
- employees;
- Compliment records;
- Void records;
- snapshot transaksi ESB;
- transition `Draft → PendingSupervisor` ketika semua shift lengkap.

Ketentuan:

- external ESB refresh/reconcile tidak dibungkus database transaction panjang;
- uploaded file paths dilacak sebelum transaction;
- jika transaction gagal, file baru dibersihkan;
- double submit tidak membuat row/file ganda;
- notification tetap menjadi tanggung jawab Page;
- existing Basket Size calculation dan reconciliation tidak rusak.

Tambahkan characterization test sebelum dan sesudah refactor.

## 8. Phase 5 — Input Compliment pada aplikasi Store

### 8.1 State row

Setiap row Compliment baru minimal memuat:

- `compliment_type_id`;
- `sales_number` atau transaction snapshot identifier yang aman;
- `sales_number_mode/source`;
- `notes` sebagai Keterangan Transaksi;
- `attachments`.

Jangan mempercayai snapshot company/branch/transaction amount dari public Livewire property. Resolve ulang di server dari scoped transaction ketika mode verified.

### 8.2 Picker Sales Number

- searchable server-side;
- scoped pada Sales Report/branch/date/shift aktif;
- menampilkan Sales Number dan konteks transaksi yang berguna;
- tidak memuat seluruh transaksi ke DOM;
- identifier public yang tidak boleh dimanipulasi gunakan locked/scoped validation sesuai pola Livewire aktual;
- mode manual hanya muncul melalui aksi yang jelas seperti **Input Manual**;
- manual source disimpan `manual` dan ditampilkan `Unverified`.

Jika snapshot ESB kosong atau fetch gagal:

- tampilkan state error/informasi yang jelas;
- jangan menyebut kosong sebagai sudah terverifikasi;
- izinkan manual entry bila actor tetap berwenang;
- jangan membuat transaksi ESB palsu.

### 8.3 Validation Compliment

Section boleh kosong. Jika row ada:

- jenis Compliment wajib dan harus aktif pada saat submit;
- Sales Number wajib, string, dengan panjang aman sesuai data aktual;
- source wajib salah satu nilai yang diizinkan;
- Keterangan Transaksi wajib, maksimal 2.000 karakter;
- attachments wajib array minimal satu;
- file baru hanya `jpg`, `jpeg`, `png`, `webp`;
- maksimum 5 MB per file kecuali global policy lebih ketat;
- exact duplicate dalam payload yang sama ditolak dengan pesan lokal yang jelas;
- server memastikan verified transaction berasal dari context yang sama.

### 8.4 Persistence

Simpan snapshot:

- Sales Number;
- source;
- company/branch code bila ada;
- transaction datetime bila ada;
- jenis Compliment ID dan name snapshot;
- notes;
- attachment paths;
- submitter.

Pertahankan PDF lama agar dapat dibaca. Uploader baru tidak menawarkan PDF.

### 8.5 Read-only state

Setelah shift disubmit:

- row Compliment tampil read-only;
- Sales Number, source badge, jenis, keterangan, submitter, dan bukti terlihat;
- record legacy tanpa Sales Number menampilkan copy netral **Data lama — Sales Number belum tersedia**;
- PDF lama diberi label file lama dan tetap dapat dibuka jika actor berizin.

Jalankan focused Compliment tests.

## 9. Phase 6 — Input Void pada aplikasi Store

### 9.1 State row

Setiap row Void memuat:

- Sales Number/transaction identifier;
- Sales Number source;
- Payment Method identifier/name;
- Payment Method source bila mode manual didukung;
- Alasan Void;
- Foto Nota Awal;
- Foto Nota Setelah Void.

### 9.2 Sales Number

Gunakan picker dan scoped resolution yang sama dengan Compliment tanpa menduplikasi query/business rule. Reuse komponen/presenter/query yang benar-benar kohesif, bukan menyatukan model domain.

### 9.3 Payment Method

- gunakan searchable select dari payment method/transaksi yang tersedia pada context report;
- simpan ID/kode bila tersedia dan name snapshot selalu;
- jangan mengandalkan ID yang berlaku lintas company tanpa bukti;
- bila mode manual dibutuhkan karena master gagal dimuat, buat aksi eksplisit dan tandai unverified;
- inactive master tetap terbaca melalui snapshot name.

### 9.4 Validation Void

Section boleh kosong. Jika row ada:

- Sales Number wajib;
- verified source wajib lolos scoped transaction validation;
- Payment Method wajib;
- Alasan Void wajib, maksimal 2.000 karakter;
- Foto Nota Awal wajib tepat satu image;
- Foto Nota Setelah Void wajib tepat satu image;
- format `jpg`, `jpeg`, `png`, `webp`;
- maksimal 5 MB per file kecuali policy global lebih ketat;
- dua field bukti tidak boleh tertukar dalam persistence;
- exact duplicate dalam payload yang sama ditolak.

### 9.5 Persistence/read-only

Simpan seluruh snapshot dari PRD. Setelah submit tampilkan read-only:

- Sales Number dan verification badge;
- Payment Method;
- Alasan Void;
- dua kartu bukti dengan label berbeda;
- submitter dan waktu submit.

Jalankan focused Void dan shift submission tests.

## 10. Phase 7 — UI Store sesuai standar

Ikuti `docs/standards/ui-consistency.md` dan sibling page aktual.

Struktur halaman:

1. tanggal/shift;
2. staff;
3. Sales per Payment Method;
4. Compliment;
5. Void;
6. konfirmasi submit.

Aturan visual:

- border tipis, `rounded-2xl`/`rounded-xl`, tanpa shadow pada kartu biasa;
- layout satu kolom pada mobile;
- label wajib dan error dekat field;
- helper text lokal, tidak membuat banner besar yang tidak perlu;
- searchable picker, bukan native multi-select;
- preview upload, progress, remove, dan type/size guidance;
- destructive row removal merah;
- primary action biru;
- loading state dan double-submit protection;
- icon memakai Heroicons/komponen existing, bukan SVG manual baru;
- tidak ada horizontal scroll pada halaman 360/390 px;
- modal/picker maksimal sekitar 90vh, focus management, Escape, backdrop, dan return focus sesuai pattern existing.

Periksa viewport 360, 390, 768, 1024, 1440 serta zoom 200%.

## 11. Phase 8 — Back office detail dan navigation

### 11.1 Sub-navigation

Di area Sales Report sediakan pola navigation yang paling sesuai dengan Filament existing:

- `Laporan Harian`;
- `Compliment`;
- `Void`.

Prefer sub-navigation/tabs dalam domain Sales Report. Jangan memenuhi sidebar dengan tiga menu lepas bila komponen existing mendukung grouping yang lebih baik.

Pertahankan route penting atau sediakan compatibility redirect bila route harus berubah.

### 11.2 Detail Sales Report

Detail harus menampilkan:

- identity report, branch, tanggal, shift, status;
- reconciliation dan Supervisor correction existing;
- Compliment grouped per shift dengan Sales Number, type, notes, source status, submitter, dan evidence;
- Void grouped per shift dengan Sales Number, Payment Method, reason, dua evidence, source status, dan submitter;
- Review History lengkap termasuk Supervisor, Finance legacy, dan Finance rejection baru.

Finance UI:

- hanya pada `Completed`;
- alasan wajib;
- tombol **Reject Report**;
- confirmation menyebut score menjadi 0;
- tidak ada approve Finance.

### 11.3 Legacy states

- `PendingFinance` lama tetap dapat dibuka selama compatibility phase;
- jangan tampilkan tombol approval baru yang melanggengkan workflow lama;
- tampilkan badge/copy legacy yang jelas bila dibutuhkan;
- record Compliment lama tanpa Sales Number dan PDF lama tetap dapat dibaca.

Jalankan resource/page/navigation tests.

## 12. Phase 9 — Laporan Compliment

Buat Page/Resource/query yang mengikuti pola repository dan tidak menduplikasi business logic.

### 12.1 Query dan filter

Filter minimum:

- rentang tanggal;
- branch;
- shift;
- jenis Compliment;
- Sales Number;
- source: verified/manual/legacy.

Aturan:

- branch options dan query wajib scoped;
- gunakan database pagination;
- eager load hanya relasi/kolom yang digunakan;
- search Sales Number server-side;
- filter state dipertahankan sesuai pattern Filament existing;
- jangan load attachment binary pada list.

### 12.2 Tabel dan detail

Kolom minimum:

- tanggal;
- branch;
- shift;
- Sales Number;
- jenis Compliment;
- notes ringkas;
- submitter;
- jumlah bukti;
- verification status.

Data panjang wrap/truncate dengan akses detail. Pindahkan field sekunder ke detail agar tabel tidak terlalu lebar.

### 12.3 Export Compliment

Buat Action khusus, misalnya `ExportSalesReportComplimentsXlsxAction`, mengikuti pola XLSX existing.

Satu Compliment = satu row. Header baseline mengikuti urutan PRD:

1. Compliment ID;
2. Sales Report ID;
3. Tanggal Laporan;
4. Branch Code;
5. Branch Name;
6. Company Code;
7. Shift;
8. Sales Number;
9. Sumber Sales Number;
10. Tanggal/Waktu Transaksi ESB;
11. Jenis Compliment;
12. Keterangan Transaksi;
13. Jumlah Foto;
14. Bukti Tersedia;
15. Disubmit Oleh;
16. Waktu Submit;
17. Status Sales Report.

Gunakan mapping header/row terpusat agar mudah diselaraskan setelah sample ESB diterima.

## 13. Phase 10 — Laporan Void

### 13.1 Query dan filter

Filter minimum:

- rentang tanggal;
- branch;
- shift;
- Sales Number;
- Payment Method;
- source: verified/manual/legacy bila relevan.

Aturan query, scope, pagination, dan loading sama ketatnya dengan Compliment.

### 13.2 Tabel dan detail

Kolom minimum:

- tanggal;
- branch;
- shift;
- Sales Number;
- Payment Method;
- Alasan Void ringkas;
- indikator foto awal;
- indikator foto setelah void;
- submitter;
- verification status.

### 13.3 Export Void

Buat Action khusus, misalnya `ExportSalesReportVoidsXlsxAction`.

Satu Void = satu row. Header baseline:

1. Void ID;
2. Sales Report ID;
3. Tanggal Laporan;
4. Branch Code;
5. Branch Name;
6. Company Code;
7. Shift;
8. Sales Number;
9. Sumber Sales Number;
10. Tanggal/Waktu Transaksi ESB;
11. Nilai Transaksi ESB;
12. Payment Method;
13. Alasan Void;
14. Foto Nota Awal Tersedia;
15. Foto Setelah Void Tersedia;
16. Disubmit Oleh;
17. Waktu Submit;
18. Status Sales Report.

## 14. Kontrak bersama export XLSX

Kedua export wajib:

1. menggunakan writer/pola XLSX existing;
2. memproses query dengan chunk/cursor, bukan `get()` seluruh dataset;
3. eager load relasi secara terbatas;
4. mengikuti semua filter aktif;
5. menerapkan branch scope dan permission;
6. menganggap Sales Number sebagai string;
7. menyimpan leading zero;
8. menyelenggarakan timezone/format tanggal konsisten;
9. sanitasi seluruh text cell berawalan `=`, `+`, `-`, `@`;
10. tidak mengekspor temporary URL attachment;
11. menghasilkan header-only file yang valid saat data kosong;
12. memberi filename jenis report dan rentang tanggal;
13. mempunyai test untuk dataset lebih dari satu chunk;
14. dipindah ke queue hanya bila benchmark membuktikan request synchronous tidak aman atau pola existing mengharuskan queue.

## 15. Phase 11 — Permission, policy, dan attachment access

Audit permission config/seeder sebelum menambah permission.

Pertahankan:

- `view sales reports`;
- `review sales reports as supervisor`;
- permission Finance existing sebagai compatibility bila masih tepat.

Jika menambah permission export:

- gunakan nama deskriptif dan convention project;
- tambahkan ke config/seeder secara minimal;
- hanya role bisnis yang disepakati menerima permission;
- test role matrix;
- jangan sync/grant permission pada production dari proses implementasi.

Authorization minimum:

- list Compliment/Void;
- view detail;
- export;
- reject Finance;
- attachment temporary URL/download;
- manual Sales Number/Payment Method bila membutuhkan permission khusus.

Gunakan policy/scope reusable. Jangan menyalin branch logic berbeda pada tiap Page.

## 16. Phase 12 — Migrasi status legacy

Jangan melakukan data mutation production dalam implementasi. Buat perangkat audit/migrasi yang aman bila PRD dan pola project membutuhkannya.

### 16.1 Preview wajib

Sediakan command/action dengan dry-run default yang melaporkan:

- jumlah `PendingFinance` per branch dan bulan;
- jumlah yang memiliki `supervisor_reviewed_by`/`at`;
- jumlah yang memiliki audit `supervisor/approved`;
- jumlah anomali tanpa bukti Supervisor approval;
- jumlah dengan Finance/settlement/MDR parsial;
- candidate IDs atau file report yang aman untuk review.

### 16.2 Execute

Execute hanya dengan flag eksplisit dan confirmation sesuai convention command. Aturan:

- snapshot sebelum perubahan;
- convert hanya candidate dengan bukti Supervisor approval sah;
- `PendingFinance → Completed`;
- jangan membuat approval Finance palsu;
- jangan mengubah reviewer/note/settlement/MDR lama;
- catat `source=workflow_migration` dan `previous_status=pending_finance` pada audit/log yang tepat;
- exception tidak diubah;
- chunk dan transaction per batch/record agar aman;
- idempoten saat dijalankan ulang.

### 16.3 Restore

Sediakan restore yang:

- memakai snapshot eksekusi tertentu;
- hanya memulihkan record yang belum berubah setelah migrasi;
- menolak menimpa record yang sudah Finance reject atau dimutasi user;
- mempunyai dry-run;
- melaporkan restored/skipped/conflict.

### 16.4 Compatibility enum

Pertahankan `PendingFinance` selama rollout dan legacy audit. Jangan melakukan cleanup enum pada PR ini kecuali database, code reference, route, view, test, dan export semuanya membuktikan tidak lagi digunakan dan user menyetujui cleanup terpisah.

Jalankan command tests untuk dry-run, execute, idempotency, anomaly, dan restore conflict.

## 17. Phase 13 — Test matrix lengkap

Gunakan factory dan states; jangan bergantung pada data production.

### 17.1 Workflow

- seluruh shift → `PendingSupervisor`;
- Supervisor approve → `Completed`;
- reviewer/time/note Supervisor tersimpan;
- audit Supervisor tepat satu;
- no Finance approve button/method/callable;
- Finance reject Completed dengan alasan valid → `Rejected`;
- alasan kosong/pendek gagal tanpa mutation;
- Finance tidak dapat reject status selain Completed;
- actor tanpa permission/branch access mendapat 403;
- self-review guard tetap bekerja;
- concurrent approve/reject tidak menghasilkan audit ganda;
- Completed = score 100;
- Finance reject = score 0;
- auto-reject hanya PendingSupervisor.

### 17.2 Compliment

- section kosong valid;
- partial row invalid;
- type inactive invalid;
- Sales Number wajib;
- verified transaction harus satu context;
- manual source tersimpan unverified;
- notes wajib maksimal 2.000;
- image valid tersimpan;
- PDF/non-image baru ditolak;
- lebih dari batas file ditolak;
- DB failure membersihkan file baru;
- submitted shift read-only;
- legacy null Sales Number tetap view/export;
- legacy PDF tetap accessible untuk actor berizin.

### 17.3 Void

- section kosong valid;
- setiap field wajib;
- verified Sales Number scoped;
- Payment Method snapshot tersimpan;
- manual source ditandai;
- original dan voided receipt tersimpan pada field benar;
- format/size invalid ditolak;
- failure membersihkan kedua file;
- read-only state menampilkan dua bukti berbeda;
- unauthorized attachment access ditolak.

### 17.4 Reports dan export

- route/navigation visible sesuai permission;
- list scoped berdasarkan branch;
- filter tanggal/branch/shift/type/payment/source/search bekerja;
- pagination server-side;
- one domain record = one export row;
- leading zero Sales Number bertahan;
- formula injection disanitasi;
- empty result valid;
- multiple chunk tidak hilang/duplikat;
- user tidak dapat mengekspor branch di luar akses;
- legacy row diekspor tanpa nilai palsu.

### 17.5 Migration

- preview tidak mutasi;
- valid candidate converted;
- anomaly skipped;
- field Finance/settlement tidak berubah;
- rerun idempoten;
- snapshot dibuat;
- restore aman;
- conflict tidak ditimpa.

### 17.6 UI/accessibility

- validation error mempertahankan state;
- loading mencegah submit ganda;
- modal/picker keyboard behavior;
- focus return;
- labels dan accessible names;
- mobile layout tidak overflow;
- empty/loading/error/legacy/manual/read-only state tampil berbeda.

## 18. Phase 14 — Quality gates dan review akhir

Jalankan minimal:

1. focused tests per file/filter selama implementasi;
2. seluruh test modul Sales Report;
3. test permission/navigation/attachment terkait;
4. `vendor/bin/pint --dirty --format agent`;
5. ulang focused tests setelah formatter bila file berubah;
6. `git diff --check`;
7. route/Filament discovery check;
8. frontend build bila asset/source frontend berubah;
9. review visual pada viewport yang diwajibkan;
10. query/export benchmark wajar;
11. audit final `rg` untuk:
    - jalur aktif `approveFinance`;
    - transisi baru ke `PendingFinance`;
    - copy `Set as Finance Review`/`Set as Completed`;
    - attachment URL tanpa authorization;
    - query database di Blade;
    - HTTP ESB di Page/Blade;
    - code/test yang masih memaksa dua approval.

Full suite hanya dijalankan bila perubahan fondasi bersama atau milestone repository mengharuskannya; jangan menghapus test yang gagal. Pisahkan failure existing dari regression baru dengan bukti.

## 19. UAT wajib di staging

Siapkan skenario UAT, jangan menjalankan mutation staging tanpa otorisasi user:

1. Store submit shift tanpa Compliment/Void.
2. Store submit Compliment verified.
3. Store submit Compliment manual/unverified.
4. Store submit Void verified dengan dua foto.
5. Validasi partial row dan file invalid.
6. Semua shift selesai → Supervisor Review.
7. Supervisor koreksi lalu approve → Completed.
8. Score tanggal menjadi 100.
9. Finance membuka Completed dan tidak melihat approve.
10. Finance reject dengan alasan → Rejected.
11. Score tanggal menjadi 0.
12. Role/branch lain tidak dapat melihat atau mengekspor data.
13. Export Compliment dan Void dibandingkan dengan sample ESB bila tersedia.
14. Legacy Compliment tanpa Sales Number/PDF masih terbuka.
15. Preview legacy `PendingFinance`, execute pada snapshot staging, dan restore test.

## 20. Deployment dan rollback notes yang harus disiapkan

Jangan deploy. Siapkan runbook yang mencakup:

- urutan backup;
- migration additive;
- deploy code yang backward-compatible;
- cache/config clear bila dibutuhkan;
- storage/queue requirement bila ada;
- feature flag/workflow version bila dipakai;
- preview legacy status;
- snapshot dan execute conversion;
- post-deploy smoke tests;
- monitoring error/upload/export/score;
- rollback aplikasi non-destruktif;
- restore data dengan conflict guard;
- larangan mengubah Completed kembali ke PendingFinance secara otomatis.

Jika menggunakan feature flag, pusatkan di config/service dan test kedua mode. Jangan menyebarkan `env()` atau condition langsung pada Blade.

## 21. Larangan eksplisit

- Jangan menghapus data Compliment, approval, reconciliation, settlement, atau attachment lama.
- Jangan mengubah migration lama.
- Jangan membuat Sales Number hasil tebakan.
- Jangan memasangkan transaksi berdasarkan nominal saja.
- Jangan menganggap Product/transaction ID berlaku lintas company tanpa bukti.
- Jangan membuat Finance approval tersembunyi setelah tombol dihapus.
- Jangan memberi nilai 100 pada status selain Completed.
- Jangan mengizinkan Finance reject tanpa alasan.
- Jangan menambah reopen/resubmit Rejected dalam scope ini.
- Jangan mengubah auto-reject menjadi Finance SLA.
- Jangan menyimpan public URL permanen untuk bukti privat.
- Jangan membuat table list dengan seluruh dataset di DOM.
- Jangan membuat komponen abstrak besar hanya untuk dua section.
- Jangan menyalin seluruh logika branch scope di banyak Page.
- Jangan menyatakan export sudah identik dengan ESB tanpa sample nyata.
- Jangan menjalankan data migration production, commit, push, atau deploy.

## 22. Definition of Done

Implementasi selesai hanya jika:

1. Supervisor approval langsung menghasilkan `Completed`.
2. Tidak ada jalur aktif Finance approve atau workflow baru ke `PendingFinance`.
3. Finance hanya dapat reject `Completed` dengan reason, audit, permission, scope, lock, dan confirmation yang benar.
4. Score berubah 100 → 0 ketika Completed direject Finance.
5. Compliment baru wajib Sales Number, jenis, Keterangan Transaksi, dan foto.
6. Void baru wajib Sales Number, Payment Method, reason, foto awal, dan foto setelah Void.
7. Verified/manual/legacy source terlihat dan tersimpan benar.
8. Data/file lama tetap dapat dibaca tanpa backfill palsu.
9. Laporan Compliment dan Void mempunyai filter, pagination, branch scope, detail, dan export.
10. Export streaming/chunked, menjaga leading zero, dan aman dari formula injection.
11. Attachment privat dan authorization teruji.
12. Legacy migration mempunyai preview, snapshot, idempotent execute, exception list, dan safe restore.
13. UI mobile/desktop, loading, validation, empty, error, legacy, dan read-only state telah diperiksa.
14. Page/Resource tetap tipis; business workflow berada pada Action/Service yang reusable.
15. Focused tests dan seluruh Sales Report module tests lulus.
16. Pint, `git diff --check`, discovery, build terkait, dan visual QA lulus.
17. Tidak ada perubahan unrelated yang ikut tersentuh.
18. Deployment dan rollback notes tersedia, tetapi tidak dijalankan.

## 23. Format laporan akhir agent

Laporkan dengan struktur berikut:

1. **Outcome** — hasil bisnis yang benar-benar selesai.
2. **Perubahan utama** — workflow, Compliment, Void, report/export, permission, dan legacy tooling.
3. **Data compatibility** — bagaimana data lama dipertahankan dan anomali ditangani.
4. **UI** — halaman/section yang berubah dan hasil review responsive/accessibility.
5. **Files** — daftar file utama yang dibuat/diubah.
6. **Tests** — command yang dijalankan beserta hasil dan jumlah test/assertion bila tersedia.
7. **Quality gates** — Pint, diff check, build, route/discovery, benchmark.
8. **External gap** — apakah sample ESB tersedia dan apakah exact export parity sudah diverifikasi.
9. **Deployment/rollback** — langkah yang disiapkan tetapi belum dijalankan.
10. **Remaining risks** — hanya risiko nyata yang belum dapat dibuktikan.

Jangan menyatakan selesai jika ada migration, test, permission, scope, upload lifecycle, atau workflow kritis yang belum diimplementasikan. Jangan menyatakan deploy berhasil bila deployment tidak dilakukan.
```
