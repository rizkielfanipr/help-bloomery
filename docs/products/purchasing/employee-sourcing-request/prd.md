---
document_id: PURCHASING-EMPLOYEE-SOURCING-REQUEST-PRD
title: PRD — Request Sourcing melalui Employee App
status: implemented
version: 1.2
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/purchasing/README.md
  - docs/products/rnd/README.md
  - docs/products/rnd/material-sourcing-lifecycle/prd.md
  - docs/products/inventory/non-po-receiving/prd.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
  - docs/architecture/panels.md
  - docs/architecture/rbac.md
supersedes: null
---

# PRD — Request Sourcing melalui Employee App

> Dokumen ini adalah kontrak planning. Dokumen belum mengotorisasi migration production, perubahan role production, mutation ESB, atau deployment.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Purchasing |
| Fitur | Request Sourcing melalui Employee App |
| Status | implemented |
| Prioritas | TBD — belum ditetapkan owner bisnis |
| Target pengguna | User Employee App dengan permission; Purchasing sebagai processor |
| Modul terdampak | Employee App, Purchasing, Research & Development |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

User operasional membutuhkan kanal resmi untuk meminta Purchasing mencari supplier bagi produk yang sudah ada di Master Product ESB. Fitur menyediakan tile **Request Sourcing** di Employee App, form mobile dengan styling Request ERP, dan product picker reusable dari Receiving Tanpa PO. Submission membuat request lokal dan antrean Purchasing, bukan langsung membuat sourcing atau mengirim review ke R&D. Setelah Purchasing memverifikasi kebutuhan serta melengkapi minimal satu supplier, request dikonversi secara idempotent menjadi sourcing mandiri existing dan selanjutnya mengikuti workflow R&D serta Finance yang sudah berlaku.

## 3. Latar belakang

Sourcing mandiri existing hanya dapat dibuat Purchasing dari Back Office dan pada saat dibuat sudah membutuhkan supplier serta harga. User di luar Purchasing belum mempunyai tile untuk meminta sourcing secara terstruktur. Permintaan berisiko masuk melalui percakapan informal tanpa identitas produk yang valid, branch requester, status, PIC, alasan, maupun jejak keputusan.

Request awal harus dipisahkan dari record sourcing karena requester belum tentu mengetahui supplier, harga, MOQ, lead time, atau status sample. Pemisahan ini juga mencegah request yang belum diverifikasi langsung masuk ke Review Sourcing R&D.

## 4. Baseline sistem

Audit repository pada commit `a4a101a9` menemukan:

1. Launcher Employee App dibangun oleh `app/Filament/Casual/Pages/LauncherPage.php` dan tile tampil berdasarkan permission.
2. Request ERP memakai page/layout bare, header biru, content card mobile, attachment private, success state, serta bottom navigation form/riwayat.
3. Receiving Tanpa PO dan Item Journal memakai `ManagesEsbStockProductPicker` serta partial `esb-product-picker-sheet` untuk pencarian nama/kode dan pagination Product Detail ESB.
4. Product picker tersebut sudah mendukung mode tanpa stok melalui `pickerShowsStock()`, tetapi kontrak host perlu diaudit agar tidak memaksa branch/location yang tidak diperlukan.
5. Back Office Purchasing memiliki menu **Sourcing Bahan** dengan permission `view material sourcings`, `submit material sourcing`, dan `hand over material samples`.
6. `CreateStandaloneMaterialSourcingAction` membuat satu `RndProductEsbMaterial` origin `standalone`, memverifikasi ulang Product Detail aktif dari ESB, mewajibkan minimal satu supplier/harga, lalu mengatur status `pending_rnd_review`.
7. Sourcing mandiri dapat dibuat berkali-kali untuk Product Detail ESB yang sama dan memakai kode lokal unik `SRC-*`.
8. Belum ada model, permission, menu, status, atau relasi khusus permintaan sourcing dari Employee App.
9. `sourcing_requested_at` pada sourcing mandiri existing dimulai ketika record sourcing dibuat; waktu tunggu request user belum mempunyai metrik tersendiri.
10. Sourcing downstream, evaluasi sample, dan Finance tetap menjadi kontrak `docs/products/rnd/material-sourcing-lifecycle/prd.md` dan tidak boleh diduplikasi.

## 5. Problem statement

1. User belum mempunyai kanal Employee App untuk meminta sourcing produk existing.
2. Permintaan informal tidak menjamin Product Detail ESB valid atau menyimpan snapshot identitas produk.
3. Purchasing belum mempunyai antrean, PIC, status, dan riwayat keputusan untuk request user.
4. Request awal tidak boleh langsung masuk Review R&D sebelum supplier/sample tersedia.
5. Conversion berisiko menghasilkan sourcing ganda jika terjadi double click atau concurrent processing.
6. Requester memerlukan riwayat dan alasan saat request dikembalikan atau ditolak.

## 6. Goals

1. Menyediakan tile Request Sourcing yang dapat dikontrol melalui permission.
2. Memilih tepat satu Product Detail aktif dari Master Product ESB melalui picker reusable.
3. Menyimpan request lokal dengan requester, branch, kebutuhan, snapshot produk, attachment, status, dan audit.
4. Menyediakan antrean Purchasing untuk memproses, meminta revisi, menolak, atau mengonversi request.
5. Mengonversi request satu kali menjadi sourcing mandiri existing setelah data supplier lengkap.
6. Menjaga workflow R&D/Finance, durasi sourcing, dan sourcing manual existing tanpa regresi.
7. Memberi requester halaman riwayat dan notifikasi perubahan status.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Request user dengan Product Detail valid | Tidak tersedia | 100% | Constraint dan feature test |
| Request dikonversi lebih dari sekali | Tidak tersedia | 0 | Unique FK, lock, concurrency test |
| Request masuk Review R&D sebelum supplier lengkap | Tidak tersedia | 0 | Workflow test |
| Mutation ESB saat membuat request | Tidak tersedia | 0 | HTTP fake dan integration test |
| Transition tanpa actor/waktu/alasan yang diwajibkan | Tidak tersedia | 0 | Status history test |
| Query tambahan per row pada index | Tidak tersedia | Tidak ada N+1 | Query-count test |

## 8. Non-goals

- Meminta pembuatan produk baru yang belum ada di Master Product ESB.
- Mengizinkan product bebas teks atau beberapa produk dalam satu request.
- Meminta requester mengisi supplier, harga, MOQ, lead time, atau sample.
- Mengubah lifecycle review R&D, sample, Finance, Pricelist, atau ESB sync existing.
- Menghapus tombol **Buat Sourcing** manual Purchasing.
- Membuat sourcing otomatis saat request disubmit atau disetujui tanpa supplier.
- Menampilkan data harga/supplier kepada requester tanpa permission Purchasing.
- Membuat SLA, escalation scheduler, WhatsApp CTA, atau integrasi eksternal baru pada versi pertama.
- Menambah package frontend/backend atau API publik.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Requester Employee App | Membuat, membaca milik sendiri, memperbaiki saat diminta, dan membatalkan sebelum diproses | `access employee app sourcing request` |
| Purchasing viewer | Melihat antrean dan detail request | `view material sourcing requests` |
| Purchasing processor | Claim/process, meminta revisi, menolak, dan conversion | `process material sourcing requests` |
| Purchasing sourcing staff | Melengkapi supplier dan membuat sourcing existing | `submit material sourcing` |
| R&D/Finance | Memproses sourcing hasil conversion sesuai workflow existing | Permission existing |
| Administrator | Memberikan permission melalui Role & Permission | Permission management existing |

Conversion memerlukan kedua permission Purchasing: `process material sourcing requests` dan `submit material sourcing`.

## 10. User journey

### 10.1 Requester

1. Membuka tile **Request Sourcing**.
2. Melihat branch/divisi dari akun dan informasi pengisian.
3. Menekan **Tambah Produk**.
4. Mencari produk berdasarkan nama atau kode pada bottom sheet Product Detail ESB.
5. Memilih satu produk dan melihat ringkasan kode, nama, kategori, subkategori, serta UOM.
6. Mengisi alasan/tujuan wajib, estimasi kebutuhan dan target tanggal opsional, catatan, serta attachment opsional.
7. Menekan **Kirim Request Sourcing**.
8. Sistem memverifikasi ulang produk, membuat nomor `MSR-*`, menyimpan request/status history, dan memberi notifikasi Purchasing.
9. User melihat request pada tab **Riwayat**.
10. Bila `revision_requested`, user memperbaiki field yang diizinkan dan mengirim ulang.

### 10.2 Purchasing

1. Membuka **Purchasing → Permintaan Sourcing**.
2. Melihat antrean default status `submitted`, `in_review`, dan `revision_requested`.
3. Membuka detail dan menekan **Mulai Proses** agar PIC tercatat.
4. Purchasing dapat meminta revisi atau menolak dengan alasan wajib.
5. Untuk melanjutkan, Purchasing menekan **Buat Sourcing**.
6. Produk request terisi dan dikunci; form meminta minimal satu supplier/harga serta field sourcing existing.
7. Server mengambil ulang Product Detail dari ESB dan mengunci request.
8. `CreateStandaloneMaterialSourcingAction` membuat sourcing origin `standalone`; request ditautkan dan status menjadi `converted` dalam satu use case idempotent.
9. Record sourcing menampilkan sumber **Request User** dan mengikuti review R&D/Finance existing.

## 11. Business rules

1. Satu request mempunyai tepat satu Product Detail ESB.
2. Company produk versi pertama terkunci `BLSS` pada server.
3. Requester dan branch berasal dari user login; browser tidak boleh menetapkannya.
4. User tanpa branch tidak dapat submit dan mendapat pesan untuk menghubungi admin.
5. Browser hanya mengirim product code/detail ID; seluruh snapshot produk dibaca ulang dari ESB saat submit.
6. Produk hilang, tidak aktif, atau detail ID tidak cocok dengan code ditolak dan wajib dipilih ulang.
7. Alasan/tujuan wajib; estimasi kebutuhan, target tanggal, catatan, dan attachment opsional.
8. Satu request satu produk; kebutuhan multi-produk dibuat sebagai request terpisah.
9. Product Detail yang sama boleh diminta kembali pada request berbeda.
10. Double submit browser dilindungi submission key/idempotency yang sesuai pola project.
11. Request `submitted` dapat dibatalkan requester selama belum di-claim Purchasing.
12. Request `revision_requested` hanya dapat diperbaiki requester pemilik dan dikirim kembali ke `submitted`.
13. Request yang sudah `in_review`, `converted`, `rejected`, atau `cancelled` tidak dapat diedit requester.
14. Mulai proses mencatat PIC; processor lain tidak boleh menimpa tanpa aksi takeover eksplisit yang tidak termasuk versi pertama.
15. Revision/rejection/cancel wajib menyimpan actor, waktu, dan alasan.
16. Conversion hanya diizinkan dari `in_review`, memerlukan minimal satu supplier lengkap, dan maksimal satu kali.
17. Request tidak langsung mengubah `sourcing_requested_at`; waktu tersebut dimulai ketika sourcing hasil conversion dibuat.
18. Waktu respons request dihitung terpisah dari `submitted_at` ke claim/conversion/rejection/cancel.
19. Hasil conversion tetap `sourcing_origin = standalone`; relasi request membedakan badge **Request User** dari sourcing mandiri biasa.
20. Requester hanya melihat status, kebutuhan, alasan keputusan, dan referensi sourcing; supplier/harga mengikuti permission Back Office existing.
21. Data request dan history tidak dihapus melalui UI versi pertama.
22. Attachment selalu privat dan hanya tersedia melalui endpoint terotorisasi.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Menyediakan tile Employee App Request Sourcing | Must |
| FR-2 | Menyediakan form dan history dengan pola UI Request ERP | Must |
| FR-3 | Memilih satu produk melalui picker reusable Receiving Tanpa PO tanpa stok | Must |
| FR-4 | Verifikasi ulang Product Detail aktif dari ESB saat submit | Must |
| FR-5 | Menyimpan snapshot requester, branch, produk, kebutuhan, attachment, dan nomor request | Must |
| FR-6 | Menyediakan submenu Purchasing → Permintaan Sourcing | Must |
| FR-7 | Mendukung claim/process, revision, reject, cancel, dan conversion | Must |
| FR-8 | Conversion memakai form supplier dan workflow sourcing mandiri existing | Must |
| FR-9 | Menjamin satu request maksimal satu sourcing | Must |
| FR-10 | Menampilkan sumber Request User dan link dua arah request/sourcing | Must |
| FR-11 | Mengirim notifikasi internal pada submit dan perubahan status | Should |
| FR-12 | Menampilkan metrik umur request tanpa mencampur durasi sourcing | Should |
| FR-13 | Menyediakan search/filter/pagination server-side | Must |
| FR-14 | Menjaga sourcing manual dan workflow R&D/Finance existing | Must |

## 13. Status dan lifecycle

| Status | Makna | Transisi yang diizinkan | Aktor |
|---|---|---|---|
| `submitted` | Menunggu Purchasing | `in_review`, `cancelled` | Purchasing / requester |
| `in_review` | Sudah di-claim dan sedang dilengkapi | `revision_requested`, `converted`, `rejected` | Purchasing |
| `revision_requested` | Informasi requester perlu diperbaiki | `submitted`, `cancelled` | Requester |
| `converted` | Sourcing mandiri berhasil dibuat | Terminal | Sistem melalui Purchasing |
| `rejected` | Tidak dilanjutkan, alasan tersimpan | Terminal | Purchasing |
| `cancelled` | Dibatalkan sebelum conversion | Terminal | Requester |

`converted` hanya berarti request telah menjadi record sourcing. Status R&D/Finance selanjutnya dibaca dari record sourcing tertaut dan tidak disalin menjadi status request baru.

## 14. Model data konseptual

### 14.1 `MaterialSourcingRequest`

- `request_number` unik dan tidak dapat diubah;
- requester dan branch ID/snapshot;
- company code `BLSS` server-controlled;
- Product ID, Product Detail ID, product code/name, category, subcategory, dan UOM snapshot;
- reason/purpose wajib;
- optional estimated quantity, target date, notes, dan attachment paths;
- status enum, submission key/idempotency metadata;
- assigned Purchasing user dan claimed timestamp;
- last revision/rejection/cancel reason serta actor/timestamp;
- nullable unique FK ke `RndProductEsbMaterial` hasil conversion;
- submitted/converted/closed timestamps.

Attachment dapat memakai JSON paths pada aggregate request seperti Request ERP bila Phase 0 membuktikan authorization dan lifecycle-nya memadai. Jangan membuat tabel attachment baru tanpa kebutuhan nyata.

### 14.2 `MaterialSourcingRequestStatusHistory`

- request ID;
- from/to status;
- actor;
- reason/notes;
- metadata aman seperti submission version;
- timestamp append-only.

### 14.3 Relasi sourcing existing

Request menyimpan FK unik ke `RndProductEsbMaterial`; model material menyediakan relasi balik opsional untuk tampilan badge/link. Tidak ada perubahan arti `sourcing_origin` existing.

## 15. Integration contract

1. Master Product ESB hanya dibaca melalui service existing yang dipakai product picker dan `CreateStandaloneMaterialSourcingAction`.
2. Picker memakai company `BLSS`, search nama/kode, pagination, dan active Product Detail filter.
3. Submit dan conversion mengambil ulang detail server-side; data display browser bukan sumber kebenaran.
4. Membuat request tidak memanggil mutation ESB.
5. Timeout/error baca ESB tidak membuat request parsial; input tetap di form dan user diminta mencoba kembali.
6. Conversion memanggil use case sourcing mandiri existing, bukan menyalin logikanya ke Page/Resource baru.
7. Tidak ada blind retry untuk conversion; request yang sudah mempunyai linked material mengembalikan hasil existing.
8. Notifikasi memakai mekanisme internal aplikasi; WhatsApp, Telegram baru, dan email berada di luar scope.

## 16. Authorization dan security

1. Tile/page/form/history memerlukan `access employee app sourcing request`.
2. Requester hanya dapat query request miliknya; branch user lain tidak cukup untuk membuka record.
3. Back Office view memerlukan `view material sourcing requests`.
4. Claim/revision/reject/conversion memerlukan `process material sourcing requests`.
5. Conversion juga memerlukan `submit material sourcing` dan memenuhi authorization Action existing.
6. Policy/Action melakukan authorization ulang; visibility tombol bukan kontrol keamanan.
7. Requester/branch/company/status/linked material tidak boleh mass-assigned dari browser.
8. Attachment divalidasi jumlah, MIME, ukuran, prefix path, dan kepemilikan request; download melalui controller/action terotorisasi atau temporary URL singkat.
9. File path, signed URL, credential, harga supplier, dan payload ESB tidak dicetak ke log/notifikasi requester.
10. SUPERADMIN mengikuti pola Gate existing, tetapi invariant status/conversion tetap dijaga di Action.
11. Permission disinkronkan melalui pipeline; role production diberikan lewat Role & Permission, bukan perubahan seeder runtime.

## 17. UI/UX specification

### 17.1 Employee App

- Tile bernama **Request Sourcing**, icon/warna mengikuti launcher existing, dan tampil hanya untuk permission baru.
- Page memakai layout bare serta bahasa visual `erp-request-page.blade.php`: header biru, tombol kembali, icon/judul, branch dan tanggal, body `rounded-t-3xl`, white card, info callout, field `rounded-xl`, tombol utama biru `rounded-2xl`, dark mode.
- Bottom navigation berisi **Buat Request** dan **Riwayat**.
- Success state menampilkan nomor `MSR-*`, status, tombol ke riwayat, dan tidak menampilkan WhatsApp CTA pada versi pertama.

### 17.2 Form

Urutan:

1. branch/divisi read-only;
2. Informasi Pengisian;
3. selected-product card atau tombol **Tambah Produk**;
4. alasan/tujuan;
5. estimasi kebutuhan dan UOM read-only;
6. target tanggal;
7. catatan tambahan;
8. attachment;
9. submit.

Input dan attachment bertahan saat validation atau ESB read gagal. Submit mempunyai loading/disabled state dan perlindungan klik ganda.

### 17.3 Product picker

- Reuse `ManagesEsbStockProductPicker` dan `esb-product-picker-sheet` dalam mode `pickerShowsStock = false`; jangan menyalin markup picker.
- Bottom sheet di mobile, dialog terpusat di desktop, search nama/kode debounce, pagination server-side, loading/error/empty state, Escape/backdrop/focus sesuai standar.
- Product row menampilkan nama, kode, dan UOM tanpa stok.
- Setelah dipilih, user dapat **Ganti Produk** sebelum submit.

### 17.4 Riwayat requester

- Hanya request milik user, terbaru lebih dulu, pagination server-side.
- Card menampilkan nomor, produk, tanggal, status berteks, alasan keputusan, dan referensi sourcing bila converted.
- `revision_requested` menyediakan aksi edit/kirim ulang; `submitted` menyediakan cancel dengan konfirmasi.

### 17.5 Back Office

- Canonical menu: **Purchasing → Permintaan Sourcing**.
- Index memakai tabel karena user membandingkan nomor, requester, branch, produk, target, status, PIC, dan umur request.
- Default hanya status actionable; filter Semua Status, status, branch, requester, produk/kode, tanggal submit/target, dan PIC.
- Badge navigation menghitung `submitted`, `in_review`, dan `revision_requested` tanpa N+1.
- Detail memakai section card: requester, produk, kebutuhan, attachment, timeline, aksi, dan link sourcing.
- Modal conversion menampilkan produk read-only dan form supplier existing; jangan menumpuk modal picker karena produk sudah dipilih requester.

### 17.6 Responsive dan aksesibilitas

- Verifikasi 360, 390, 768, 1024, 1440 px dan zoom 200%.
- Status mempunyai teks, bukan warna saja.
- Nama/kode panjang wrap; tabel scroll hanya pada container.
- Error dekat field, icon-only button mempunyai accessible name, dan modal memenuhi fokus/Escape/scroll internal.

## 18. Non-functional requirements

1. Index/history memakai server pagination, eager loading, dan aggregate badge tanpa N+1.
2. Search produk tidak membawa seluruh katalog ke state Livewire.
3. Conversion memakai transaction dan row lock; unique linked material menjadi pertahanan database.
4. Submit/cancel/revision/reject bersifat idempotent terhadap double click.
5. Attachment baseline mengikuti Request ERP: maksimum 10 MB per file; jumlah/format final diverifikasi Phase 0 terhadap Livewire/PHP/proxy.
6. Tidak ada dependency baru.
7. Enum menjadi sumber label/status.
8. Waktu ESB read mempunyai error state yang dapat dipulihkan tanpa kehilangan input.

## 19. Observability dan audit

Event minimum:

- request submitted/resubmitted/cancelled;
- claimed;
- revision requested;
- rejected;
- conversion started/completed/failed;
- attachment uploaded/downloaded bila audit existing mendukung;
- link ke material sourcing yang dibuat.

Log conversion memuat request ID, actor ID, dan linked material ID tanpa attachment URL, data pribadi berlebih, credential, atau raw ESB payload. Metrik operasional minimum: jumlah antrean actionable, umur request, conversion count, rejection count, dan conversion failure.

## 20. Data migration dan kompatibilitas

1. Migration additive membuat tabel request dan status history serta nullable relation/index yang dibutuhkan.
2. Tidak ada backfill karena fitur belum mempunyai data lama canonical.
3. `rnd_product_esb_materials`, `material_sourcings`, status, dan record existing tidak diubah maknanya.
4. Sourcing mandiri lama tetap tampil sebagai **Mandiri** tanpa Request User.
5. Permission baru tidak otomatis diberikan ke role production.
6. Route/menu existing tidak diganti.
7. Rollback aplikasi mempertahankan tabel/request untuk audit; drop schema hanya melalui release terpisah setelah backup.

## 21. Testing strategy

1. Factory/model test untuk relation, cast, request number, unique link, dan scope requester.
2. Authorization matrix requester/other requester/Purchasing viewer/processor/submitter/unauthorized/SUPERADMIN.
3. Employee App test tile visibility, no-branch guard, form, product picker, attachment, submit, history, revision, dan cancel.
4. HTTP fake untuk search/read ESB, inactive/mismatched detail, timeout, dan forged browser snapshot.
5. Workflow test seluruh transition valid/invalid serta reason requirement.
6. Conversion test supplier validation, re-fetch product, one-request-one-sourcing, concurrent/double conversion, dan rollback failure.
7. Regression `CreateStandaloneMaterialSourcingAction`, Review Sourcing R&D, Finance, duration tracking, dan manual **Buat Sourcing**.
8. File test private path, MIME/size/count, unauthorized download, dan cleanup partial failure.
9. Query count untuk index/history/badge.
10. Visual/manual UAT mobile, dark mode, 200% zoom, long text, loading/error/empty, validation retention, dan permission action visibility.

## 22. Rollout

1. Deploy migration additive dan aplikasi.
2. Jalankan permission sync melalui pipeline existing.
3. Berikan permission tile kepada role/user yang disepakati dan permission Back Office kepada Purchasing melalui Role & Permission.
4. UAT staging product picker ESB read-only, attachment private, request transitions, conversion, serta workflow R&D/Finance.
5. Verifikasi sourcing manual existing tetap berfungsi.
6. Observasi error ESB, authorization denial, antrean, conversion failure, dan duplicate prevention.

Detail operasional ada di [Rollout Runbook](rollout.md).

## 23. Rollback

1. Cabut permission tile dan menu untuk menghentikan submission baru.
2. Rollback application artifact ke versi sebelumnya.
3. Pertahankan tabel, attachment, history, dan linked sourcing; jangan memutus relasi atau menghapus data otomatis.
4. Request yang sudah converted tetap diproses melalui workflow sourcing existing.
5. Cleanup schema/storage hanya melalui release terpisah setelah backup/reference audit.

## 24. Acceptance criteria

1. Tile hanya tampil bagi user berpermission dan page menolak user lain.
2. User tanpa branch tidak dapat submit.
3. Satu request hanya dapat memilih satu Product Detail aktif ESB.
4. Snapshot palsu dari browser tidak tersimpan; server memakai hasil ESB terbaru.
5. Form mengikuti pola Request ERP dan picker mengikuti Receiving Tanpa PO tanpa stok.
6. Submit menghasilkan nomor unik, status history, attachment privat, dan notifikasi Purchasing.
7. Requester hanya melihat request miliknya dan dapat memperbaiki hanya saat revision requested.
8. Purchasing dapat claim, request revision, reject, dan conversion sesuai permission.
9. Conversion tidak dapat berjalan tanpa supplier/harga yang valid.
10. Satu request maksimal menghasilkan satu sourcing meskipun action diklik bersamaan.
11. Sourcing hasil conversion tetap mengikuti workflow R&D/Finance existing dan menampilkan sumber Request User.
12. Waktu antre request terpisah dari durasi sourcing.
13. Index/history/filter/badge responsive dan tidak mempunyai N+1.
14. Sourcing manual existing dan Project sourcing tidak mengalami regresi.
15. Tidak ada mutation ESB, WhatsApp, dependency, atau perubahan role production pada tahap submit request.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Request langsung dianggap sourcing aktif | Antrean R&D terisi data tanpa supplier | Entity/status request terpisah dan conversion eksplisit |
| Produk browser dimanipulasi | Sourcing salah produk/UOM | Re-fetch Product Detail ESB pada submit dan conversion |
| Double conversion | Dua sourcing untuk satu request | Transaction, row lock, unique FK, idempotency test |
| User melihat harga supplier | Kebocoran data komersial | Requester view tidak memuat relation supplier; Policy server-side |
| Picker diduplikasi | UI/API drift | Reuse concern/partial Receiving Tanpa PO tanpa stok |
| Waktu antre mencemari durasi sourcing | Laporan durasi salah | Timestamp request dan sourcing dipisahkan |
| Attachment orphan | Storage bertambah tanpa referensi | Compensation pada failure dan audit lifecycle |
| Permission tile diberikan terlalu luas | Request spam/tidak relevan | Grant melalui Role & Permission dan audit usage |

## 26. Open decisions

| No | Keputusan | Rekomendasi/default planning | Pemilik keputusan | Batas waktu |
|---|---|---|---|---|
| 1 | Role/user mana yang menerima tile saat staging/production? | Grant terbatas lewat Role & Permission; jangan ubah role production otomatis | Purchasing + Administrator | Sebelum UAT staging |
| 2 | Format/jumlah attachment final | Ikuti format aman Request ERP, maksimum 10 MB/file; audit limit infra | Purchasing + IT | Phase 0 |
| 3 | Apakah estimated quantity wajib? | Opsional pada v1 karena tujuan awal adalah request supplier, bukan order | Purchasing | Sebelum implementasi form |

Tidak ada open decision yang mengubah arsitektur utama atau memblokir pembuatan dokumentasi. Keputusan final dicatat sebelum field/role terkait diimplementasikan.

Keputusan butir 2 dan 3 dikunci pada §29. Butir 1 tetap menunggu Purchasing dan Administrator sebelum UAT staging; implementasi tidak memberikan permission baru ke role mana pun melalui seeder.

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Audit schema, picker contract, attachment limits, role, notification, dan characterization sourcing existing | Keputusan teknis dan test baseline |
| 1 | Migration, model/factory/enum, relation, Policy, permission, history | Domain request additive |
| 2 | Submit/revision/cancel, product verification, attachment, notification | Employee request use cases |
| 3 | Claim/reject/conversion dengan lock dan reuse standalone Action | Purchasing workflow idempotent |
| 4 | Tile/form/history, Back Office index/detail, badge/link sourcing | UI end-to-end |
| 5 | Regression, performance, responsive UAT, docs/report/rollout | Delivery evidence |

## 28. Definition of Done

- [ ] Seluruh acceptance criteria terpenuhi.
- [ ] Test terdampak dan regression sourcing existing lulus.
- [ ] Authorization diuji untuk role yang berhak dan tidak berhak.
- [ ] Migration additive dan rollback dipahami.
- [ ] Product picker/attachment/conversion diuji di staging.
- [ ] Dokumentasi Purchasing, R&D, panels, RBAC, dan data model sesuai implementasi.
- [ ] Implementation report mencatat hasil, deviasi, migration, permission, dan UAT.

## 29. Keputusan Phase 0

Hasil audit repository pada commit `de0cbab7` (3 November 2026):

| Area | Keputusan |
|---|---|
| Product picker | `ManagesEsbStockProductPicker` dipakai tanpa refactor. Host mengembalikan `pickerShowsStock() = false`, `pickerCompanyCode() = 'BLSS'`, dan `pickerBranches() = []`; dalam mode tanpa stok trait tidak membaca branch/location. Partial `esb-product-picker-sheet` di-include dengan `pickerShowsStock = false`. |
| Verifikasi produk | Pembacaan ulang Product Detail aktif dipindahkan dari method private `CreateStandaloneMaterialSourcingAction` ke `App\Services\Rnd\Material\ActiveEsbProductDetailResolver`, lalu dipakai submit/resubmit request dan sourcing mandiri. Pesan error sourcing mandiri tidak berubah. |
| Attachment | Disimpan sebagai metadata JSON (`path`, `name`, `mime`, `size`) pada request, mengikuti Request ERP. Maksimum 5 file, 10 MB per file, format JPG, JPEG, PNG, WEBP, dan PDF. Disk privat `b2` dengan prefix `purchasing/material-sourcing-requests/{request_id}/`. File diunduh lewat controller terotorisasi yang men-stream isi file tanpa signed URL. Upload gagal di tengah transaksi menghapus file yang sudah tersimpan; file yang dilepas saat kirim ulang dihapus setelah commit. |
| Estimasi kebutuhan | Opsional (butir 3 §26), angka desimal > 0 dengan UOM dari snapshot produk. |
| Nomor request | `MSR-{YYYYMMDD}-{id 6 digit}`, diisi dalam transaksi pembuatan. |
| Idempotency submit | `submission_key` UUID unik dibuat saat form dibuka; submit ulang dengan key yang sama mengembalikan request yang sudah ada. |
| Notifikasi | Filament database notification setelah commit, mengikuti `NonPoReceiptNotifier`. Submit/kirim ulang memberi tahu user aktif dengan `process material sourcing requests`; revisi, tolak, dan conversion memberi tahu requester. Kegagalan notifikasi tidak menggagalkan transisi. |
| PIC | Revisi, tolak, dan conversion hanya oleh PIC yang melakukan **Mulai Proses**. SUPERADMIN mengikuti `Gate::before`; invariant status dan conversion tetap dijaga di Action. |
| Role | Tidak ada grant baru di `RolesAndPermissionsSeeder`; permission tersedia lewat `permissions:sync` dan diberikan melalui Role & Permission. |
