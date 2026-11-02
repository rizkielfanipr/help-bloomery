---
document_id: INVENTORY-STOCK-CARD-DRAFT-WORKFLOW-PRD
title: PRD — Stock Card Draft Workflow
status: draft
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/inventory/README.md
  - docs/products/inventory/stock-card-draft-workflow/implementation-plan.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
  - docs/architecture/panels.md
  - docs/architecture/rbac.md
supersedes: null
---

# PRD — Stock Card Draft Workflow

> Dokumen ini menjadi kontrak untuk penyempurnaan draft Stock Card. Implementasi belum dimulai selama status dokumen masih `draft`.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Inventory |
| Fitur | Stock Card Draft Workflow |
| Status | draft |
| Prioritas | P1 |
| Target pengguna | `STORE_STAFF` dan role lain yang memperoleh akses Employee App Stock Card |
| Modul terdampak | Employee App → Stock Card; Back Office review tidak berubah |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

Penghitungan Stock Card pada saat closing membutuhkan waktu karena staff harus mengisi banyak item. Sistem sebenarnya sudah menyimpan input sebagai draft, tetapi perilaku tersebut tidak terlihat jelas: draft masih ditampilkan sebagai **Belum diisi**, tidak ada tombol **Simpan Draft**, dan tombol **Simpan Stock Card** sebenarnya langsung mengunci laporan untuk review. Fitur ini memperjelas workflow draft, memulihkan nilai terakhir saat dilanjutkan, membedakan qty belum dihitung dari stok nol, serta mengamankan transisi pengiriman melalui validasi server-side dan transaksi atomik.

## 3. Latar belakang

Staff perlu mencicil penghitungan sebelum closing agar pekerjaan tidak menumpuk di akhir operasional. Nilai yang sudah dihitung harus tetap tersedia setelah reload, logout, atau berpindah halaman. Pada saat yang sama, sistem tidak boleh menganggap item yang belum dihitung sebagai stok nol karena keduanya mempunyai arti operasional yang berbeda.

## 4. Baseline sistem

Audit repository pada 2 November 2026 menemukan:

- `StockCardStatus` sudah memiliki `draft`, `pending_supervisor`, `pending_finance`, dan `completed`.
- `StockCardEntryPage::updated()` memanggil `persistDraft()` setelah qty, catatan, atau staff berubah.
- Draft disimpan pada `stock_cards`, `stock_card_entries`, dan `stock_card_employees`, lalu dipulihkan oleh `loadData()`.
- Constraint unik `stock_cards(branch_id, report_date)` menjaga satu Stock Card per branch/tanggal; entry unik per `stock_card_id + product_code`.
- Test existing membuktikan draft bertahan setelah reload.
- Landing Stock Card memperlakukan `draft` sama seperti belum ada record dan menulis **Belum diisi**.
- Tombol **Simpan Stock Card** mengubah status menjadi `pending_supervisor`, membuat approval, dan mengunci input staff.
- Qty kosong saat draft sudah disimpan sebagai `null`; field hanya memakai placeholder `0`.
- Submit belum menjalankan seluruh validasi final di method mutasi dan belum membungkus seluruh perubahan dalam satu transaction dengan row lock.
- Autosave saat ini dapat menyinkronkan seluruh entry dan employee pada setiap perubahan field.

## 5. Problem statement

1. User tidak mengetahui bahwa input parsial sudah tersimpan dan dapat dilanjutkan.
2. Draft yang sudah berisi data masih terlihat sebagai laporan yang belum dimulai.
3. Aksi simpan draft dan kirim untuk review tidak dibedakan secara eksplisit.
4. Nilai kosong dan nilai nol harus dipertahankan sebagai dua kondisi berbeda.
5. Submit harus aman terhadap pemanggilan langsung, double-click, kegagalan parsial, dan perubahan status bersamaan.
6. Autosave tidak boleh menambah beban write yang tidak proporsional terhadap jumlah item.

## 6. Goals

1. Memungkinkan Stock Card diisi bertahap tanpa kehilangan input terakhir.
2. Menampilkan status draft dan progress secara jelas.
3. Memisahkan aksi **Simpan Draft** dari **Kirim untuk Review**.
4. Menjaga `null` sebagai belum dihitung dan `0` sebagai stok fisik nol.
5. Menjalankan validasi final dan perubahan status secara atomik serta terotorisasi.
6. Mempertahankan workflow review Supervisor dan Finance yang sudah berjalan.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Draft bertahan setelah reload | Sudah ada, tidak terlihat jelas | 100% pada test draft parsial | Feature test Livewire |
| Draft dikenali di landing | Ditampilkan `Belum diisi` | Status dan progress draft tampil | Feature/UI test |
| Item belum dihitung | Disimpan `null`, tetapi belum menjadi kontrak | Tetap `null` sampai user mengisi | Assertion database |
| Submit parsial | Dicegah di modal, belum lengkap di mutation boundary | Selalu ditolak server-side | Feature test direct action |
| Approval ganda | Belum mempunyai kontrak double-submit eksplisit | Maksimal satu approval submitter | Test concurrency/idempotency |

## 8. Non-goals

- Mengubah alur review Supervisor atau Finance.
- Mengubah sumber katalog Stock Movement ESB.
- Menambah produk manual ke Stock Card.
- Mengubah aturan pemilihan kategori atau snapshot harian.
- Menganggap draft sebagai laporan selesai di History atau kalender.
- Menambah permission, dependency, queue, scheduler, API, atau integrasi eksternal baru.
- Membuka kembali laporan yang sudah dikirim kepada staff.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Store Staff | Mengisi, menyimpan draft, melanjutkan draft, dan mengirim laporan | `access employee app stock card`, `create stock cards`, `edit stock cards` |
| Supervisor Store | Review setelah laporan dikirim | `review stock cards as supervisor` |
| Finance Staff | Review akhir setelah Supervisor | `review stock cards as finance` |
| Administrator | Melihat dan mengelola sesuai permission/branch scope | Permission Stock Card existing |

## 10. User journey

1. User memilih tanggal pada tile Stock Card.
2. Sistem menyiapkan daftar produk dan membuat draft tanpa mengisi qty palsu.
3. User mengisi sebagian qty dan catatan; autosave menyimpan perubahan yang valid.
4. User dapat menekan **Simpan Draft** dan meninggalkan halaman.
5. Landing menampilkan `Draft · {terisi}/{total} item` dan aksi **Lanjutkan Draft**.
6. Saat dibuka kembali, item terisi menampilkan nilai terakhir dan item belum dihitung tetap kosong.
7. Setelah semua item dan Staff In Charge lengkap, user menekan **Kirim untuk Review**.
8. Sistem menampilkan konfirmasi bahwa data akan dikunci.
9. Server mengulang validasi, mengunci record, membuat snapshot `reported_qty`, mengubah status ke `pending_supervisor`, dan membuat audit approval.
10. Halaman berubah read-only dan menampilkan status menunggu Supervisor.

## 11. Business rules

1. Satu Stock Card berlaku untuk satu branch dan satu tanggal laporan.
2. Draft bersifat bersama untuk user yang berhak pada branch yang sama, bukan milik pribadi pembuat pertama.
3. Draft dapat dilanjutkan untuk tanggal laporan yang sama tanpa auto-expire.
4. Qty belum dihitung wajib disimpan sebagai `null` dan ditampilkan kosong.
5. Qty `0` hanya disimpan setelah user secara eksplisit memasukkan nol.
6. Draft boleh mempunyai qty kosong dan Staff In Charge kosong.
7. Qty draft yang terisi harus numeric, minimal `0`, dan maksimal empat angka desimal.
8. Submit wajib mempunyai qty valid untuk seluruh item dan minimal satu Staff In Charge aktif pada branch tersebut.
9. Hanya status `draft` yang dapat diubah atau dikirim oleh staff.
10. Setelah submit, koreksi staff ditutup; workflow koreksi existing Supervisor/Finance tetap berlaku.
11. Snapshot kategori dan pemilihan produk existing tetap menjadi sumber daftar item draft.
12. Item draft yang sudah terisi tidak boleh hilang hanya karena cache/katalog kemudian berubah.
13. Draft tidak masuk History sebagai laporan selesai dan tidak dihitung submitted pada kalender.
14. Autosave gagal harus terlihat sebagai gagal; UI tidak boleh menampilkan klaim sukses palsu.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Sistem menyimpan qty, catatan, dan Staff In Charge sebagai draft persisten | Must |
| FR-2 | Item kosong disimpan `null`, bukan `0` | Must |
| FR-3 | Draft memulihkan nilai terakhir setelah reload atau login ulang | Must |
| FR-4 | Landing membedakan belum dimulai, draft, pending supervisor, pending finance, dan completed | Must |
| FR-5 | Landing dan entry menampilkan progress item terisi | Must |
| FR-6 | Entry menampilkan status autosave: menyimpan, tersimpan, atau gagal | Must |
| FR-7 | Tombol **Simpan Draft** tidak menjalankan validasi kelengkapan final | Must |
| FR-8 | Tombol final bernama **Kirim untuk Review** dan memakai confirmation dialog | Must |
| FR-9 | Submit mengulang seluruh validasi final server-side | Must |
| FR-10 | Submit mengunci record dan menyimpan snapshot/status/audit dalam satu transaction | Must |
| FR-11 | Double-submit tidak membuat transisi atau approval ganda | Must |
| FR-12 | Kalender memberi pembeda draft tanpa menghitungnya sebagai submitted | Should |
| FR-13 | Autosave hanya menulis perubahan yang diperlukan, bukan seluruh dataset setiap ketikan | Should |
| FR-14 | History tetap mengecualikan draft | Must |

## 13. Status dan lifecycle

| Status | Makna | Transisi yang diizinkan | Aktor |
|---|---|---|---|
| `draft` | Penghitungan belum dikirim dan masih dapat dicicil | `pending_supervisor` | Store Staff |
| `pending_supervisor` | Data staff terkunci dan menunggu review | `pending_finance` | Supervisor |
| `pending_finance` | Sudah disetujui Supervisor | `completed` atau kembali ke Supervisor sesuai workflow existing | Finance |
| `completed` | Review selesai | Tidak ada pada scope ini | Finance |

Tidak ada status baru. Istilah **final** pada sisi staff berarti finalisasi input staff untuk dikirim, bukan status bisnis `completed`.

## 14. Model data konseptual

- `StockCard` tetap menjadi aggregate root dengan unique constraint `branch_id + report_date`.
- `StockCardEntry.actual_qty` tetap nullable; `null` berarti belum dihitung dan `0` berarti stok fisik nol.
- `StockCardEntry.reported_qty` baru diisi saat submit untuk menjadi baseline review.
- `StockCardEmployee` tetap menyimpan snapshot Staff In Charge.
- `StockCardApproval` hanya mencatat submit/review, bukan setiap autosave.
- `updated_at` dapat dipakai sebagai informasi waktu draft terakhir tersimpan.
- Tidak ada migration pada scope awal. Optimistic concurrency dengan field versi menjadi pekerjaan lanjutan bila edit simultan terbukti sering terjadi.

## 15. Integration contract

Tidak ada kontrak eksternal baru. Save draft dan submit staff tidak memanggil ESB. Pengambilan katalog dan refresh saldo pada tahap review tetap mengikuti `EsbStockMovementService` dan `StockCardEsbSynchronizer` existing.

## 16. Authorization dan security

1. Page tetap memerlukan `access employee app stock card`.
2. Create draft memerlukan `create stock cards`; update/submit draft memerlukan `edit stock cards` dan akses branch.
3. Policy/action wajib memeriksa branch dari user terautentikasi; branch tidak diterima sebagai input tepercaya dari browser.
4. Record harus diambil ulang pada mutation boundary dan status `draft` diverifikasi dari database.
5. `reportDate`, identifier item, snapshot kategori, dan snapshot selection tidak boleh dipercaya hanya karena ada di public Livewire property.
6. UI visibility bukan kontrol keamanan; pemanggilan method Livewire langsung harus tetap ditolak bila tidak valid.
7. Employee harus aktif dan berasal dari branch user pada saat submit.
8. Output catatan tetap di-escape oleh Blade.

## 17. UI/UX specification

### 17.1 Landing Stock Card

| Kondisi | Label | Aksi |
|---|---|---|
| Tidak ada record | Belum dimulai | Mulai isi |
| `draft` | Draft · `{filled}/{total}` item terisi | Lanjutkan Draft |
| `pending_supervisor` | Menunggu review Supervisor | Lihat |
| `pending_finance` | Menunggu review Finance | Lihat |
| `completed` | Selesai | Lihat |

### 17.2 Entry Stock Card

- Field qty pertama kali kosong dengan placeholder `0`.
- Nilai `0` yang dimasukkan user tetap tampil sebagai `0` setelah draft dibuka kembali.
- Banner draft menampilkan progress dan status penyimpanan.
- Status autosave: **Menyimpan draft…**, **Draft tersimpan pukul HH:mm**, atau **Gagal menyimpan draft — Coba lagi**.
- Tombol sekunder: **Simpan Draft**.
- Tombol utama: **Kirim untuk Review**.
- Confirmation dialog menjelaskan bahwa data akan dikunci dan koreksi dilakukan melalui Supervisor/Finance.
- Setelah submit, field menjadi read-only dan status review ditampilkan.

### 17.3 Kalender dan History

- Kalender membedakan draft dari submitted dan missed.
- Draft hari/tanggal lampau tetap dapat dibuka.
- History tetap menampilkan laporan yang sudah dikirim saja.

## 18. Non-functional requirements

- Autosave tidak boleh melakukan write seluruh entry untuk setiap karakter yang diketik; gunakan debounce/blur dan penyimpanan item yang berubah.
- Mutation memakai database transaction; submit memakai `lockForUpdate()`.
- Constraint unik existing tetap menjadi guard terhadap duplicate card/entry.
- UI mobile tidak menambah seluruh dataset baru ke state atau DOM.
- Kegagalan save tidak menghapus state lokal yang masih tampil pada halaman.
- Tidak ada request ESB di dalam database transaction.

## 19. Observability dan audit

- `StockCardApproval` tetap mencatat submitter dan transisi review.
- Autosave tidak membuat approval/log bisnis per ketikan agar audit tidak bising.
- UI menampilkan hasil save berdasarkan hasil server, bukan asumsi client.
- Exception save/submit dilaporkan melalui mekanisme Laravel existing dengan konteks aman: stock card ID, branch ID, report date, dan status; tidak mencatat payload qty lengkap.
- Metrik operasional awal diperoleh dari error log dan test/UAT, tanpa sistem monitoring baru.

## 20. Data migration dan kompatibilitas

Tidak diperlukan migration atau backfill. Data draft existing tetap kompatibel dan akan memperoleh label/progress baru. Route, permission, enum, constraint, snapshot, serta data review existing dipertahankan. Nilai `actual_qty = null` existing tetap dianggap belum dihitung.

## 21. Testing strategy

- Characterization test untuk draft existing, snapshot, dan workflow approval sebelum refactor.
- Feature test Livewire untuk save parsial, restore, indikator UI, validation, dan submit.
- Policy test untuk permission, branch scope, status, dan akses lintas branch.
- Action test untuk transaction, lock/status guard, idempotency double-submit, dan rollback saat gagal.
- Regression test untuk draft row yang tidak lagi dikembalikan katalog serta nilai eksplisit `0`.
- Tidak ada HTTP eksternal baru; test katalog existing tetap memakai HTTP fake.

## 22. Rollout

Perubahan dapat dirilis sebagai application-only deployment tanpa migration, permission sync, environment, queue, atau scheduler. UAT staging wajib menguji perangkat mobile, koneksi lambat/putus, reload draft, nilai nol, dan double-tap submit. Tidak diperlukan rollout runbook terpisah.

## 23. Rollback

Rollback dilakukan dengan mengembalikan application release. Data draft tetap kompatibel karena tidak ada schema atau status baru. Draft yang tersimpan selama release baru tetap dapat dibaca oleh workflow lama.

## 24. Acceptance criteria

1. User dapat menyimpan draft walaupun hanya sebagian qty terisi dan Staff In Charge belum lengkap.
2. Qty belum dihitung tersimpan `null` dan tampil kosong saat draft dilanjutkan.
3. Qty eksplisit `0` tersimpan dan kembali tampil sebagai `0`.
4. Nilai terakhir, catatan, employee, dan snapshot draft dipulihkan setelah reload/login ulang.
5. Landing menampilkan status draft dan progress, bukan **Belum diisi**.
6. **Simpan Draft** tidak mengubah status menjadi `pending_supervisor` dan tidak membuat approval.
7. **Kirim untuk Review** menolak item kosong/tidak valid dan employee tidak valid.
8. Submit sukses membuat satu snapshot `reported_qty`, satu approval submitter, dan status `pending_supervisor` secara atomik.
9. Double-submit tidak menggandakan approval atau mengubah status dua kali.
10. Staff tidak dapat mengedit setelah laporan dikirim.
11. User tanpa permission atau dari branch lain tidak dapat menyimpan/mengirim draft.
12. Kalender membedakan draft dan laporan submitted; History tetap mengecualikan draft.
13. Seluruh test Stock Card terdampak, Pint, doc-link check, code-map check, dan CI lulus.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| User mengira qty kosong sama dengan nol | Stok salah saat submit | Empty=`null`, placeholder saja, final validation wajib |
| Dua perangkat mengedit draft bersama | Perubahan terakhir dapat menimpa perubahan lain | Satu branch/tanggal, status save terlihat, transaction; version guard dievaluasi bila terbukti diperlukan |
| Autosave terlalu sering | Latency dan beban database meningkat | Debounce/blur dan persist hanya item yang berubah |
| Method submit dipanggil langsung | Laporan parsial terkirim | Semua validation di Action server-side |
| Double-click submit | Approval ganda | Row lock, cek status `draft`, transaction, unique lifecycle guard |
| Refactor mengubah snapshot katalog | Produk draft berubah/hilang | Characterization test dan pertahankan snapshot existing |

## 26. Open decisions

Tidak ada keputusan bisnis yang memblokir dokumentasi. Default implementasi yang direkomendasikan:

1. Draft bersama per branch/tanggal.
2. Tidak ada auto-expire.
3. History hanya untuk laporan yang sudah dikirim.
4. Concurrency version column ditunda sampai ada bukti kebutuhan edit simultan; transaction dan status lock wajib pada scope ini.

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Characterization dan kontrak | Baseline test draft, submit, snapshot, policy |
| 1 | Mutation boundary | Action save draft dan submit atomik; policy/status validation |
| 2 | UX draft | Landing status/progress, autosave state, tombol draft/review, kalender |
| 3 | Regression dan delivery | Test lengkap, dokumentasi, implementation report, PR |

## 28. Definition of Done

- [ ] Seluruh acceptance criteria terpenuhi.
- [ ] Test draft, submit, authorization, double-submit, dan regresi lulus.
- [ ] Mutation dipindahkan dari Page ke Action yang terotorisasi.
- [ ] Tidak ada migration, permission, dependency, atau integrasi baru tanpa amendment PRD.
- [ ] `docs/products/inventory/README.md` sesuai dengan perilaku implementasi.
- [ ] Implementation report mencatat hasil, deviasi, test, dan risiko lanjutan.
