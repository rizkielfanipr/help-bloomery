---
document_id: KITCHEN-PRODUCTION-STAFF-ASSIGNMENT-PRD
title: PRD — Assignment Staff Produksi per Detail Production Order
status: in-development
version: 1.0
owner: Kitchen
technical_owner: Information Technology
created_at: 2026-11-04
updated_at: 2026-11-04
target_release: TBD
related_documents:
  - docs/products/kitchen/README.md
  - docs/products/kitchen/production-schedule/prd.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# PRD — Assignment Staff Produksi per Detail Production Order

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Kitchen |
| Fitur | Assignment Staff Produksi per Detail Production Order |
| Status | `in-development` — OD-1–OD-3 ditutup pada 4 November 2026 |
| Prioritas | P1 |
| Target pengguna | Tim Kitchen dan pengelola jadwal produksi |
| Modul terdampak | Back Office → Kitchen → Jadwal Produksi; Master Employee sebagai sumber data read-only |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

Dokumen ini adalah sumber requirement canonical untuk assignment Staff Produksi. [PRD Jadwal Produksi](../production-schedule/prd.md) tetap menjadi sumber kebenaran kalender, snapshot, dan sinkronisasi Production Order ESB.

## 2. Executive summary

Setiap Production Order ESB dapat mempunyai beberapa detail BOM/product, tetapi aplikasi belum mencatat staff yang mengerjakan masing-masing detail. Fitur ini menambahkan assignment lokal satu atau beberapa **Staff Produksi** pada setiap detail Production Order. Production Order, BOM, quantity, status, dan material tetap read-only dari ESB; hanya assignment staff yang dikelola di Bloomery Office. Assignment dibatasi oleh Branch Kitchen, permission, status aktif employee, dan seluruh perubahannya memiliki audit trail.

## 3. Latar belakang

Halaman `Kitchen → Jadwal Produksi` sudah menampilkan kalender Production Order ESB dan detail BOM/product. Satu Production Order dapat berisi beberapa detail dengan pembagian staff yang berbeda. Tanpa assignment di aplikasi, pembagian kerja harus dicatat melalui media lain dan sulit dilihat bersama order yang dikerjakan.

Tim Kitchen membutuhkan jawaban langsung pada detail Production Order:

> Siapa Staff Produksi yang ditugaskan untuk setiap detail BOM/product?

Assignment harus melengkapi snapshot ESB tanpa menjadikan Bloomery Office sebagai editor Production Order.

## 4. Baseline sistem

1. `EsbProductionOrder` adalah snapshot header ESB dan mempunyai banyak `EsbProductionOrderDetail`.
2. Satu `EsbProductionOrderDetail` mewakili satu baris BOM/product dan menyimpan `external_detail_id`, BOM, product, quantity, UOM, status selesai, serta material.
3. `StoreProductionOrderDetailAction` meng-upsert detail berdasarkan `external_detail_id`. Sinkronisasi berulang mempertahankan ID lokal detail yang sama.
4. Detail yang tidak muncul pada response berikutnya tidak dihapus sampai kontrak ESB membuktikan response selalu lengkap.
5. Kalender dan detail hanya membaca snapshot lokal; membuka halaman tidak memanggil ESB.
6. `Employee` sudah menyediakan Branch, employee code, nama, posisi, dan status aktif.
7. Permission existing memisahkan akses lihat jadwal, akses semua Branch Kitchen, dan sinkronisasi manual.
8. Belum ada tabel, Action, Policy method, permission, audit, atau UI assignment Staff Produksi.

Path baseline utama:

- `app/Models/EsbProductionOrder.php`;
- `app/Models/EsbProductionOrderDetail.php`;
- `app/Models/Employee.php`;
- `app/Actions/Kitchen/StoreProductionOrderDetailAction.php`;
- `app/Filament/Helpdesk/Pages/KitchenProductionSchedulePage.php`;
- `resources/views/filament/helpdesk/pages/kitchen-production-schedule-page.blade.php`;
- `app/Policies/EsbProductionOrderPolicy.php`;
- `config/permissions.php`.

## 5. Problem statement

1. Detail Production Order tidak menunjukkan staff yang bertanggung jawab mengerjakannya.
2. Assignment pada level header tidak cukup karena satu order dapat berisi beberapa BOM dengan pembagian tim berbeda.
3. Catatan di luar aplikasi tidak mempunyai validasi Branch, permission, atau audit yang konsisten.
4. Assignment tidak boleh dimasukkan ke field snapshot ESB karena field tersebut dapat berubah saat sinkronisasi.
5. Assignment harus tetap dapat dibaca saat employee nonaktif, berpindah Branch, atau data master berubah.

## 6. Goals

1. Memungkinkan nol, satu, atau beberapa Staff Produksi ditugaskan pada setiap detail BOM/product.
2. Menampilkan assignment langsung di detail Production Order.
3. Menggunakan master Employee existing tanpa membuat master staff baru.
4. Menjaga assignment tetap ada setelah sinkronisasi ulang snapshot ESB.
5. Menegakkan permission dan Branch scope di server.
6. Menyediakan audit penambahan dan pelepasan assignment.
7. Menjaga seluruh data Production Order ESB tetap read-only.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Detail tersinkron yang dapat menyimpan Staff Produksi | 0% | 100% | Feature test dan UAT |
| Mutation assignment lintas Branch yang berhasil | Belum ada fitur | 0 | Authorization test dan log error |
| Request atau mutation ke ESB dari assignment | 0 | 0 | HTTP fake/spy dan code review |
| Assignment hilang setelah sync detail yang sama | Belum ada fitur | 0 | Regression test sync |
| Perubahan assignment tanpa audit event | Belum ada fitur | 0 | Feature test audit |
| Query N+1 pada kalender/detail akibat assignment | Belum ada fitur | 0 | Query-count assertion |

## 8. Non-goals

- Employee App atau halaman **Tugas Produksi Saya**.
- Notifikasi assignment kepada employee.
- PIC utama, pembagian quantity, persentase kontribusi, shift, atau jam kerja.
- Status mulai/selesai lokal, check-in, actual output, yield, waste, atau produktivitas.
- Perubahan `is_finished`, status order, quantity, BOM, atau material di ESB.
- Rekomendasi staff otomatis berdasarkan posisi atau beban kerja.
- Payroll, attendance, insentif, costing, dan integrasi HRIS.
- Assignment per material di dalam detail BOM.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Viewer Jadwal Produksi | Melihat Production Order, detail, dan Staff Produksi dalam Branch scope | `view kitchen production schedules` |
| Pengelola assignment | Menambah, mengganti, dan melepas Staff Produksi | `assign kitchen production order staff` dan permission view |
| Viewer seluruh Kitchen | Melihat seluruh Branch ber-Brand Kitchen, tetapi tidak otomatis dapat mengubah assignment | `view all branch kitchen production schedules` dan permission view |
| Administrator | Memberikan permission assignment melalui Role & Permission | Permission pengelolaan role existing |

Permission baru bersifat additive. Role operasional tidak mendapatkannya secara otomatis dari seeder. Superadmin mengikuti mekanisme `permissions:sync` existing, tetapi Policy tetap membatasi record pada Branch ber-Brand Kitchen.

## 10. User journey

1. User membuka `Kitchen → Jadwal Produksi`.
2. User memilih bulan, Branch Kitchen, dan status bila diperlukan.
3. User membuka sebuah Production Order dari kalender.
4. Sistem menampilkan detail BOM/product beserta section **Staff Produksi**.
5. User berizin menekan **Atur Staff Produksi** pada satu detail.
6. Modal menampilkan employee aktif dari Branch Kitchen yang sama.
7. User mencari berdasarkan nama, employee code, atau posisi.
8. User memilih satu atau beberapa employee lalu menyimpan.
9. Server membaca ulang detail, order, Branch, actor, dan seluruh employee terpilih.
10. Sistem menyimpan selisih assignment dalam satu transaksi dan menulis audit event.
11. Card detail dan ringkasan coverage diperbarui tanpa reload halaman penuh.
12. Sinkronisasi ESB berikutnya memperbarui snapshot tanpa menghapus assignment.

## 11. Business rules

1. Assignment berada pada level `EsbProductionOrderDetail`, bukan header atau material.
2. Satu detail dapat mempunyai nol, satu, atau beberapa Staff Produksi.
3. Satu employee dapat ditugaskan pada beberapa detail atau Production Order.
4. Employee baru hanya dapat dipilih bila aktif dan `employee.branch_id` sama dengan Branch order.
5. Branch order harus aktif, ber-Brand `Kitchen`, dan berada dalam scope pengguna.
6. Detail, Branch, company, dan employee dari browser selalu di-query ulang di server.
7. Kombinasi detail + employee aktif tidak boleh duplikat.
8. Submit dengan selection yang sama bersifat no-op dan tidak membuat audit ganda.
9. Selection kosong berarti melepas seluruh assignment dan memerlukan konfirmasi bila sebelumnya terisi.
10. Assignment existing tetap terbaca bila employee kemudian nonaktif, pindah Branch, berubah identitas, atau referensinya terhapus.
11. Employee yang tidak lagi eligible tidak dapat ditambahkan kembali sebelum datanya memenuhi syarat.
12. Assignment tidak tersedia sebelum detail Production Order selesai disinkronkan.
13. Assignment tidak mengubah field snapshot ESB apa pun.
14. Tidak ada request ESB saat membuka picker atau menyimpan assignment.
15. Sync tidak menghapus assignment; detail yang tidak terlihat pada sync terakhir tetap mengikuti retention snapshot existing.
16. Istilah UI canonical adalah **Staff Produksi**; istilah “Pengerja” tidak digunakan.
17. Koreksi assignment setelah detail selesai diperbolehkan dengan konfirmasi tambahan dan tetap menghasilkan audit event (OD-2).

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-01 | Menampilkan Staff Produksi pada setiap detail BOM/product | Must |
| FR-02 | Menampilkan empty state bila detail belum memiliki assignment | Must |
| FR-03 | Menampilkan tombol **Atur Staff Produksi** hanya untuk user berizin | Must |
| FR-04 | Picker hanya memuat employee aktif dari Branch order | Must |
| FR-05 | Picker mendukung multi-select dan pencarian nama, kode, atau posisi | Must |
| FR-06 | Server memvalidasi ulang record dan permission | Must |
| FR-07 | Menyimpan penambahan dan pelepasan berdasarkan diff dalam satu transaksi | Must |
| FR-08 | Setiap perubahan nyata menghasilkan audit event append-only | Must |
| FR-09 | Sinkronisasi ulang mempertahankan assignment | Must |
| FR-10 | Staff historis tetap terbaca melalui snapshot identitas | Must |
| FR-11 | Menampilkan coverage `X dari Y detail sudah memiliki Staff Produksi` | Should |
| FR-12 | Menampilkan konfirmasi sebelum melepas semua staff | Must |
| FR-13 | Menampilkan peringatan untuk staff nonaktif atau yang Branch-nya berubah | Must |
| FR-14 | Mendukung koreksi detail selesai sesuai OD-2 | Should |

## 13. Status dan lifecycle

Assignment tidak menambah status proses produksi. Lifecycle hanya menggambarkan relasi penugasan:

| Status | Makna | Transisi yang diizinkan | Aktor |
|---|---|---|---|
| Tidak ditugaskan | Detail tidak mempunyai Staff Produksi aktif | Tambah satu atau beberapa staff | Pengelola assignment |
| Ditugaskan | Detail mempunyai minimal satu assignment aktif | Tambah, ganti, atau lepas staff | Pengelola assignment |
| Historis | Assignment sudah dilepas atau referensi employee tidak lagi tersedia | Read-only melalui audit | Sistem |

Status Production Order dan `is_finished` tetap berasal dari ESB dan bukan status assignment.

## 14. Model data konseptual

### 14.1 Assignment aktif

Entitas `KitchenProductionOrderStaffAssignment`:

- `esb_production_order_detail_id` — FK detail snapshot;
- `employee_id` — nullable FK agar histori tidak rusak bila referensi employee dihapus;
- snapshot `employee_code`, `employee_name`, dan `employee_position`;
- snapshot `branch_id` dan `branch_name` saat assignment;
- `assigned_by` dan `assigned_at`;
- timestamps.

Constraint unik mencegah employee yang sama aktif dua kali pada detail yang sama. Mutation hanya melalui Action domain; tidak ada CRUD generik.

### 14.2 Audit event

Entitas append-only `KitchenProductionOrderStaffAssignmentEvent`:

- detail dan Production Order yang terdampak;
- event `assigned` atau `unassigned`;
- actor dan waktu;
- snapshot employee, Branch, nomor Production Order, BOM, dan product;
- metadata perubahan yang disanitasi.

Event tidak dapat diubah atau dihapus melalui aplikasi.

### 14.2a Kontrak final hasil audit Phase 0

| Aspek | Keputusan |
|---|---|
| Tabel | `kitchen_production_order_staff_assignments` (aktif) dan `kitchen_production_order_staff_assignment_events` (audit) |
| Strategi baris | Current-row: pelepasan menghapus baris aktif; histori lengkap ada di audit. Unique `(esb_production_order_detail_id, employee_id)` berlaku untuk setiap baris aktif karena baris baru selalu mempunyai `employee_id` |
| FK detail | Assignment `cascadeOnDelete` (detail hanya hilang bila header snapshot dihapus); audit `nullOnDelete` |
| FK employee, Branch, actor | `nullOnDelete` pada kedua tabel, mengikuti pola `sales_report_employees`; snapshot teks menjaga histori tetap terbaca |
| Employee terhapus | Baris aktif tetap tampil dengan label **Data employee dihapus**; karena tidak dapat dipilih lagi, baris tersebut dilepas (dengan event `unassigned`, `metadata.reason = employee_deleted`) pada penyimpanan berikutnya untuk detail itu. Picker menampilkan keterangan **akan dilepas saat disimpan** |
| Concurrent save | Detail di-lock (`lockForUpdate`) selama transaksi. Picker mengirim ID assignment yang dilihat saat dibuka; bila berbeda dari kondisi database, penyimpanan ditolak agar perubahan pengguna lain tidak tertimpa |
| Batas selection | Maksimal 50 Staff Produksi per detail; picker menampilkan 30 kandidat pertama dan meminta pencarian untuk sisanya |
| Policy | Method `assignStaff` pada `EsbProductionOrderPolicy` (tanpa Policy baru) |

### 14.3 Relasi dan penghapusan

- Detail Production Order mempunyai banyak assignment aktif dan audit event.
- Employee mempunyai banyak assignment, tetapi assignment bukan sumber data employee.
- Penghapusan employee tidak boleh menghapus audit atau snapshot identitas.
- Sinkronisasi Production Order tidak membuat, mengubah, atau menghapus assignment.
- Migration bersifat additive dan tidak melakukan backfill.

## 15. Integration contract

1. Tidak ada endpoint ESB baru dan tidak ada perubahan kontrak request/response existing.
2. Assignment hanya membaca snapshot Production Order lokal dan master Employee lokal.
3. Detail tetap di-upsert berdasarkan `external_detail_id` agar primary key lokal stabil.
4. Action assignment tidak boleh memanggil `EsbProductionOrderService` atau men-dispatch sync job.
5. Sync gagal atau partial tidak menghapus assignment; UI tetap memakai state stale/partial existing.
6. Header yang detailnya belum tersedia tidak menampilkan aksi assignment.
7. Idempotency mutation ditentukan dari selection aktif hasil normalisasi, bukan dari data browser mentah.
8. Retry eksternal tidak relevan karena fitur tidak mempunyai request keluar.

## 16. Authorization dan security

1. View memakai permission dan Branch scope Production Order existing.
2. Mutation memerlukan `assign kitchen production order staff` serta permission view.
3. Policy mengecek detail, order, Branch Kitchen, dan akses actor.
4. All-branch hanya memperluas akses ke Branch ber-Brand Kitchen.
5. Employee terpilih divalidasi aktif dan satu Branch pada saat transaksi.
6. Detail ID, employee ID, Branch ID, company, dan nomor order dari browser tidak dipercaya.
7. Action memakai transaksi dan row lock untuk mencegah lost update atau duplikat.
8. Input selection menolak nilai non-integer, duplikat, dan jumlah yang tidak wajar.
9. Tidak ada mass assignment dari UI ke model snapshot ESB.
10. Audit hanya menyimpan identitas operasional yang diperlukan dan tidak menyimpan credential atau payload ESB.

## 17. UI/UX specification

### 17.1 Detail Production Order

- Tambahkan copy: **Data Production Order berasal dari ESB dan hanya dapat dibaca. Assignment Staff Produksi dikelola di Bloomery Office.**
- Setiap card detail menampilkan section **Staff Produksi** setelah quantity/status dan sebelum material.
- Assignment aktif tampil sebagai chip nama; kode atau posisi menjadi secondary text/tooltip.
- Empty state: **Belum ada Staff Produksi yang ditugaskan.**
- Tombol canonical: **Atur Staff Produksi**.
- Viewer tanpa permission mutation tetap melihat assignment tanpa tombol.
- Staff nonaktif diberi label **Nonaktif**; staff yang tidak lagi satu Branch diberi label **Branch berubah**.

### 17.2 Modal assignment

- Judul: **Pilih Staff Produksi**.
- Konteks read-only: nomor order, BOM/product, Branch, quantity, UOM, dan status selesai.
- Search nama, employee code, atau posisi.
- Multi-select dengan assignment existing sudah terpilih.
- Tombol **Batal** dan **Simpan Staff Produksi**.
- Selection kosong setelah sebelumnya terisi memunculkan konfirmasi pelepasan seluruh staff.
- Detail selesai (`is_finished = true`) tetap dapat dikoreksi; modal menampilkan peringatan bahwa detail sudah selesai di ESB dan meminta konfirmasi sebelum menyimpan (OD-2).

### 17.3 Coverage dan responsive state

- Header detail dan chip kalender dapat menampilkan **X dari Y detail sudah memiliki Staff Produksi**.
- Coverage menghitung detail dengan minimal satu assignment aktif, bukan jumlah employee.
- Loading, empty, forbidden, stale, partial-sync, dan details-pending mengikuti pola existing.
- Modal dapat di-scroll pada mobile dan tidak menyebabkan horizontal overflow.
- UAT wajib pada 360, 390, 768, 1024, dan 1440 px, zoom 200%, serta dark mode.
- Nama panjang wrap atau truncate dengan akses ke teks lengkap; warna bukan satu-satunya penanda.

## 18. Non-functional requirements

1. Kalender tidak eager-load seluruh employee; coverage memakai agregat/subquery terbatas.
2. Detail memakai eager loading assignment, employee, dan actor yang terkontrol.
3. Tidak ada N+1 pada kalender, detail, atau picker.
4. Mutation tidak bergantung pada ESB, queue, scheduler, atau jaringan eksternal.
5. Concurrent save tidak boleh membuat assignment duplikat atau kehilangan perubahan.
6. Kegagalan satu employee membuat seluruh transaksi gagal tanpa perubahan parsial.
7. Tidak ada dependency, environment variable, queue, atau scheduler baru.
8. UI tetap usable pada ukuran dan mode di §17.3.

## 19. Observability dan audit

1. Audit event append-only merekam `assigned` dan `unassigned`.
2. Event menyimpan actor, waktu, detail, Production Order, Branch, dan snapshot employee.
3. No-op tidak membuat event.
4. Log error terstruktur memuat ID order/detail, Branch, actor, dan klasifikasi error tanpa payload ESB.
5. Query count kalender dan detail memiliki assertion test untuk mencegah regresi.
6. Tidak ada notification atau dashboard monitoring baru pada MVP.
7. Assignment tidak mengubah badge menu karena bukan antrean baru.

## 20. Data migration dan kompatibilitas

1. Migration additive membuat tabel assignment dan audit event.
2. Tidak ada backfill; detail existing mulai dengan empty state.
3. Tidak ada perubahan kolom atau data pada tabel snapshot ESB.
4. Permission baru ditambahkan ke `config/permissions.php`.
5. Role operasional diberikan melalui Role & Permission setelah deploy dan UAT.
6. Kode sebelum fitur tetap kompatibel dengan schema additive.
7. Data assignment dipertahankan bila aplikasi di-rollback.
8. `docs/architecture/data-model.md`, RBAC/panel bila relevan, dan Peta kode diperbarui pada implementasi.

## 21. Testing strategy

### 21.1 Characterization

- Sync detail yang sama mempertahankan ID lokal.
- Baseline Production Order tetap read-only terhadap ESB.
- Branch scope dan modal detail existing tetap lulus sebelum perubahan.

### 21.2 Domain dan action

- Tambah satu dan multiple staff.
- Ganti selection; lepas sebagian dan seluruh assignment.
- No-op dan submit berulang idempotent.
- Duplicate ID, invalid ID, dan rollback bila satu employee invalid.
- Concurrent save dan unique constraint.

### 21.3 Authorization

- Tanpa permission view atau assignment.
- Branch A mencoba order/employee Branch B.
- Branch non-Kitchen, all-branch, detail palsu, employee palsu, nonaktif, dan beda Branch.
- Request langsung ke Livewire method dan Action.

### 21.4 Audit dan sync regression

- Event assigned/unassigned, actor, snapshot, append-only guard, dan no-op.
- Sync header/detail berubah, sync partial, detail hilang dari response, dan assignment tetap ada.
- Employee nonaktif, pindah Branch, atau referensinya hilang tetap terbaca.

### 21.5 UI dan quality gate

- Tombol berdasarkan permission; selection existing; empty state; warning historis; coverage; nama panjang.
- Query-count assertion pada kalender, detail, dan picker.
- Mobile, desktop, zoom 200%, dark mode, keyboard, dan Escape.
- Regression `KitchenProductionOrder*`, Employee/Master Data terkait, permission/sidebar, sync command/job, dan scheduler smoke.
- Targeted test, Feature shard bila dampak luas, Pint, doc links, code map, `git diff --check`, migration rehearsal MySQL, dan UAT staging.

## 22. Rollout

1. Selesaikan keputusan §26 dan ubah status PRD menjadi `approved`/`in-development` sebelum kode ditulis.
2. Deploy migration additive dan kode tanpa memberikan permission ke role operasional.
3. Jalankan `permissions:sync`.
4. Verifikasi melalui Superadmin: detail, picker, Branch scope, multi-select, audit, sync ulang, mobile, dan dark mode.
5. Berikan permission kepada role/pengguna Kitchen yang disepakati melalui Role & Permission.
6. UAT satu Production Order per Branch Kitchen dengan nol, satu, dan beberapa Staff Produksi.
7. Pantau error log, query count, dan audit event sebelum memperluas akses.

Tidak ada feature flag, env, queue, scheduler, dependency, atau backfill baru.

## 23. Rollback

1. Cabut permission assignment untuk menghentikan mutation tanpa memengaruhi kalender atau sync.
2. Rollback aplikasi aman karena tabel baru additive dan tidak dibaca kode lama.
3. Tabel assignment dan audit tidak di-drop saat emergency rollback agar histori tidak hilang.
4. Penghapusan schema hanya setelah backup dan keputusan terpisah; bukan rollback normal.
5. Permission baru dapat dibiarkan tidak terpakai atau dihapus melalui `permissions:sync` setelah kode lama aktif.

## 24. Acceptance criteria

1. Setiap detail tersinkron dapat mempunyai nol, satu, atau beberapa Staff Produksi.
2. Picker hanya menampilkan employee aktif dari Branch Kitchen yang sama.
3. Viewer melihat assignment; mutation hanya tersedia bagi pemegang permission assignment.
4. Request lintas Branch, Branch non-Kitchen, record palsu, employee nonaktif/beda Branch, dan tanpa permission ditolak server.
5. Selection disimpan atomik, idempotent, dan berdasarkan diff.
6. Setiap perubahan nyata menghasilkan audit event; no-op tidak menghasilkan event.
7. Staff nonaktif, pindah Branch, atau referensinya hilang tetap terbaca melalui snapshot.
8. Sinkronisasi ulang tidak menghapus atau menggandakan assignment.
9. Tidak ada request atau mutation ke ESB dari fitur assignment.
10. UI konsisten memakai istilah **Staff Produksi**.
11. Empty state, label historis, konfirmasi pelepasan, dan coverage tampil sesuai spesifikasi.
12. Kalender/detail/picker bebas N+1 dan mempunyai query-count assertion.
13. Migration, permission, Policy, test, dokumentasi, dan UAT staging selesai sebelum akses operasional diberikan.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Assignment hilang ketika sync | Pembagian staff tidak dapat dipercaya | Relasi ke ID detail lokal stabil dan regression test sync wajib |
| Employee pindah Branch atau nonaktif | Eligibility dan tampilan berbeda | Snapshot identitas, warning, dan validasi assignment baru |
| Koreksi setelah detail selesai | Histori operasional dapat berubah | Keputusan OD-2, konfirmasi, permission, dan audit append-only |
| Save bersamaan | Lost update atau duplicate | Transaction, row lock, unique constraint, dan idempotent diff |
| Coverage memperlambat kalender | UX buruk atau N+1 | Agregat server-side dan query-count test |
| User mengira order ESB dapat diedit | Salah ekspektasi | Copy eksplisit bahwa hanya assignment lokal yang dapat diubah |
| Employee terhapus | Nama staff hilang dari histori | FK nullable dan snapshot employee pada assignment/audit |

## 26. Open decisions

| No | Keputusan | Hasil | Diputuskan oleh | Tanggal |
|---|---|---|---|---|
| OD-1 | Eligibility Staff Produksi | Employee aktif (`is_active = true`) dengan `employee.branch_id` sama dengan Branch order; divalidasi ulang di dalam transaksi | User (Kitchen) | 4 November 2026 |
| OD-2 | Koreksi assignment setelah detail `is_finished = true` | Boleh, dengan konfirmasi tambahan di UI dan audit event seperti perubahan lain | User (Kitchen) | 4 November 2026 |
| OD-3 | Scope MVP Employee App | Back Office saja; Employee App/**Tugas Produksi Saya** menjadi PRD terpisah | User (Kitchen) | 4 November 2026 |

Keputusan lain yang sudah ditutup:

- assignment disimpan lokal per detail Production Order, bukan dikirim ke ESB;
- nama UI canonical adalah **Staff Produksi**;
- satu detail mendukung multiple Staff Produksi.

Tidak ada keputusan terbuka.

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Audit schema, identity sync, lifecycle Employee, baseline query, dan tutup OD-1–OD-3 | Kontrak final dan characterization test |
| 1 | Migration additive, model, factory, relasi, permission, Policy, dan audit guard | Fondasi domain |
| 2 | Action assignment transaction, diff, idempotency, concurrency, dan audit | Mutation aman |
| 3 | Detail UI, picker Staff Produksi, coverage, responsive state, dan authorization UI | Pengalaman Back Office lengkap |
| 4 | Regression sync, query count, migration rehearsal, dokumentasi, UAT, dan rollout | Siap Pull Request dan deploy |

Implementation plan dibuat setelah PRD disetujui agar tidak menyalin keputusan yang masih terbuka.

## 28. Definition of Done

- [ ] OD-1–OD-3 ditutup dan PRD berstatus `approved`/`in-development` sebelum kode aplikasi ditulis.
- [ ] Seluruh acceptance criteria terpenuhi.
- [ ] Test action, authorization, audit, sync regression, UI, query count, dan regression Kitchen lulus.
- [ ] Migration additive dan rollback rehearsal berhasil pada MySQL.
- [ ] Tidak ada mutation atau request ESB dari assignment.
- [ ] Pint, doc links, code map, `git diff --check`, dan UAT responsif lulus.
- [ ] README Kitchen, Peta kode, data model, permission docs, rollout, dan implementation report sesuai implementasi.
- [ ] Permission role diberikan melalui UI setelah UAT dan tidak tersembunyi di seeder operasional.
