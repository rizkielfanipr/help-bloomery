# PRD — Kalender Project dan Template Checkpoint R&D

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 6 Oktober 2026 |
| Modul | Research & Development |
| Fitur | Kalender per Project, Template Checkpoint, Tambah Task, dan Copy Task |
| Status | Rancangan untuk review; belum mengotorisasi implementasi, migration production, deployment, atau permission sync |
| Prioritas | P1 |
| Stack existing | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Alpine.js 3, Tailwind CSS 4, Pest 4 |
| Referensi arsitektur | `docs/code-remediation-prd.md` |
| Referensi UI | `docs/ui-consistency-prd.md` |
| Kontrak fitur existing | `docs/rnd-project-task-calendar-prd.md` |

Dokumen ini memperluas Kalender Tugas Project R&D yang sudah ada. Kalender per Project menjadi workspace operasional utama, sedangkan kalender global pada daftar Project tetap dipertahankan sebagai monitor lintas Project.

Dokumen ini tidak memberi izin untuk menghapus data, menjalankan migration production, menambah dependency, mengubah permission production, mengirim notification production, commit, push, atau deployment. Repository wajib diaudit kembali pada awal setiap phase karena implementasi aktual dapat berubah setelah baseline dokumen ini.

## 2. Ringkasan keputusan

1. Setiap halaman detail Project mempunyai section **Kalender & Task**.
2. Kalender per Project menjadi lokasi utama untuk membuat, mengubah, menyalin, dan memantau Task Project tersebut.
3. Kalender global tetap tersedia untuk monitoring lintas Project dan mempertahankan route serta perilaku existing.
4. **Template Checkpoint** adalah blueprint reusable berisi beberapa checkpoint berurutan.
5. Setiap checkpoint yang diterapkan ke Project menghasilkan satu `RndProjectTask` nyata dan independen.
6. Task hasil template adalah snapshot; perubahan template berikutnya tidak mengubah Task yang sudah dibuat.
7. Branch dan PIC dipilih saat template diterapkan, bukan disimpan permanen pada template.
8. Versi awal menggunakan satu konfigurasi Branch/PIC untuk seluruh checkpoint dalam satu penerapan template.
9. Copy Task hanya menyalin data deskriptif dan durasi, bukan workflow, progress, review, reminder, atau histori.
10. Seluruh mutation bisnis berada pada Action, diotorisasi oleh Policy, dijalankan dengan transaction boundary yang eksplisit, dan diuji dengan Pest.
11. Aksi **Tambah Task** existing pada kalender global tetap tersedia untuk kompatibilitas; **Gunakan Template** difokuskan pada detail Project agar konteks tidak ambigu.

## 3. Latar belakang

Kalender Task saat ini berada pada halaman daftar Project melalui `HasProjectTaskCalendar`. Kalender tersebut sudah:

- menggabungkan tanggal rilis Project dan deadline Task;
- membatasi query pada rentang bulan yang terlihat;
- menyediakan filter Project, Branch, kategori, status, PIC, dan Tugas Saya;
- membuka form Task saat tanggal diklik;
- mendukung assignment Branch/PIC, progress, submission, review, revision, reminder, notification, dan attachment.

Namun, penggunaan sehari-hari masih mempunyai hambatan:

1. Pembuatan Task dari kalender global tetap meminta pengguna memilih Project.
2. Halaman detail Project belum mempunyai kalender Task tersendiri.
3. Urutan checkpoint yang berulang harus dibuat satu per satu pada setiap Project.
4. Task serupa tidak dapat disalin secara aman.
5. Tidak ada preview timeline sebelum sekumpulan checkpoint dibuat.
6. Trait kalender saat ini menangani banyak state UI, query, upload, dan mutation; memperbanyak trait tersebut ke halaman detail Project akan memperbesar coupling dan bertentangan dengan prinsip page tipis.

## 4. Masalah yang diselesaikan

- Mengurangi risiko Task masuk ke Project yang salah.
- Menghilangkan pemilihan Project berulang dalam konteks detail Project.
- Mempercepat pembuatan rangkaian pekerjaan yang memiliki pola sama.
- Menstandarkan checkpoint Project tanpa mengubah workflow Task existing.
- Memberi preview tanggal sebelum batch Task dibuat.
- Memungkinkan Task existing dijadikan dasar Task baru tanpa menyalin histori.
- Menjaga kalender global tetap ringan sebagai monitor lintas Project.
- Menghindari duplikasi aturan bisnis antara kalender global dan kalender per Project.

## 5. Tujuan dan ukuran keberhasilan

### 5.1 Tujuan produk

1. Pengguna R&D dapat mengelola Task langsung dari halaman detail Project.
2. Pengguna dapat membuat beberapa Task sekaligus dari Template Checkpoint.
3. Pengguna dapat melihat tanggal hasil template sebelum menyimpan.
4. Pengguna dapat menyalin Task dengan alur singkat dan aman.
5. PIC tetap menerima workflow, notification, reminder, dan review yang sama seperti Task existing.
6. Kalender global tetap dapat memonitor seluruh Project tanpa kehilangan fungsi existing.

### 5.2 Ukuran keberhasilan

- Pembuatan Task dari detail Project tidak menampilkan field pilihan Project.
- Satu template dapat menghasilkan seluruh checkpoint dalam satu proses atomik.
- Kegagalan satu checkpoint tidak meninggalkan Task parsial.
- Task hasil template tetap sama ketika template kemudian diedit atau dinonaktifkan.
- Copy Task tidak membawa status, assignment history, follow-up, review, reminder, completion, atau result attachment.
- Permission dan branch scope diverifikasi server-side.
- Query kalender per Project dibatasi pada Project dan rentang tanggal aktif.
- UI berfungsi pada viewport 360, 390, 768, 1024, dan 1440 px serta zoom 200%.
- Test terdampak dan quality gate proyek lulus.

## 6. Scope

### 6.1 In scope

- Kalender Task pada detail Project.
- Kalender global tetap tersedia sebagai monitoring lintas Project.
- Tambah Task dari tanggal kalender per Project.
- Pengelolaan Template Checkpoint.
- Pengurutan checkpoint template.
- Preview penerapan template.
- Penerapan template menjadi Task nyata.
- Copy Task di Project yang sama atau Project tujuan lain.
- Provenance/audit sumber template atau Task salinan.
- Permission, Policy, branch scope, validation, transaction, notification, dan test.
- Empty, loading, validation, success, error, read-only, dan permission state.

### 6.2 Out of scope versi awal

- Recurring Task otomatis.
- Dependency antartask, critical path, atau Gantt chart.
- Sinkronisasi Google Calendar/Outlook.
- Template attachment.
- Menyalin attachment Task.
- Menyimpan PIC permanen pada template.
- Branch/PIC berbeda untuk setiap checkpoint dalam satu penerapan.
- Mengubah workflow status Task/Assignment existing.
- Mengubah aturan reminder existing.
- Drag-and-drop Task untuk mengubah deadline.
- Perubahan framework frontend atau dependency baru.
- Penghapusan kalender global.

## 7. Prinsip produk dan arsitektur

### 7.1 Kalender per Project sebagai workspace utama

Kalender pada detail Project digunakan untuk pekerjaan operasional:

- tambah Task;
- gunakan Template Checkpoint;
- lihat dan edit Task;
- copy Task;
- pantau deadline, status, Branch, dan PIC.

Project berasal dari record halaman dan tidak boleh dipercaya dari public state browser. Mutation selalu melakukan query melalui relasi Project yang sedang dibuka atau melakukan authorization ulang terhadap Project tujuan.

### 7.2 Kalender global sebagai monitor

Kalender global tetap digunakan untuk:

- melihat deadline lintas Project;
- melihat tanggal rilis;
- filter lintas Project/Branch/PIC;
- membuka detail Task;
- deep-link dari dashboard atau notification.

Route, filter, branch scope, agenda mobile, dan kontrak kalender global existing dipertahankan. Aksi **Tambah Task** existing tetap tersedia dan tetap meminta Project. Apply Template hanya tersedia pada detail Project pada MVP. Copy Task tersedia dari detail Task; bila detail dibuka dari kalender global, Project sumber menjadi default dan Project tujuan tetap harus diotorisasi.

### 7.3 Template sebagai blueprint, Task sebagai snapshot

Template tidak menjadi workflow runtime. Saat diterapkan:

1. data checkpoint dibaca;
2. tanggal dihitung;
3. pengguna memeriksa preview;
4. Task nyata dibuat;
5. Task berjalan memakai workflow existing.

Perubahan atau penonaktifan template tidak mengubah Task existing.

### 7.4 Page tipis dan proses reusable

Mengikuti `code-remediation-prd.md`:

```text
Filament Page / Livewire UI
    → authorization + presentation validation
    → Action bisnis
        → date calculator / assignee resolver / status service
        → model + database transaction
        → notification setelah commit
```

- Page mengelola modal, filter, state form, loading, dan feedback.
- Query kalender tidak diletakkan di Blade.
- Apply template dan copy Task tidak bergantung pada instance Livewire.
- Blade tidak menjalankan query atau mutation.
- Abstraction baru hanya dibuat karena terdapat reuse konkret antara kalender global, kalender Project, apply template, dan copy Task.

## 8. Persona dan hak akses

| Persona | Kebutuhan |
| --- | --- |
| R&D Manager/Staff | Mengelola template, membuat Task, menerapkan checkpoint, copy/edit/cancel, assign PIC, dan review |
| PIC Branch | Melihat assignment miliknya, menyimpan progress, submit hasil, dan menanggapi revisi |
| Viewer Branch | Melihat Task untuk Branch yang dapat diakses sesuai permission |
| Cross-branch Reviewer | Melihat dan mereview lintas Branch sesuai permission |
| Administrator | Mengelola akses tanpa otomatis menjadi PIC |

### 8.1 Permission existing yang dipertahankan

- `view rnd project tasks`
- `create rnd project tasks`
- `update rnd project tasks`
- `assign rnd project tasks`
- `respond rnd project tasks`
- `review rnd project task follow ups`
- `cancel rnd project tasks`
- `view all branch rnd project tasks`

### 8.2 Permission baru yang direkomendasikan

- `view rnd project task templates`
- `manage rnd project task templates`
- `apply rnd project task templates`
- `copy rnd project tasks`

`create rnd project tasks` tetap wajib untuk hasil apply/copy. Dengan demikian:

- apply memerlukan `apply rnd project task templates` **dan** `create rnd project tasks`;
- copy memerlukan `copy rnd project tasks` **dan** `create rnd project tasks`;
- manage template tidak otomatis memberi hak membuat Task.

Permission khusus memberi kontrol eksplisit dan tidak menggantikan authorization record/branch.

## 9. Istilah domain

| Istilah | Definisi |
| --- | --- |
| Template Checkpoint | Blueprint reusable untuk satu rangkaian Task Project |
| Checkpoint | Satu item template yang akan menjadi satu Task nyata |
| Tanggal Acuan | Tanggal dasar perhitungan offset checkpoint |
| Penerapan Template | Satu batch pembuatan Task dari template ke Project |
| Preview Timeline | Hasil kalkulasi Task dan tanggal sebelum mutation |
| Task Snapshot | Task nyata yang tidak berubah ketika template asal berubah |
| Copy Task | Membuat Task baru dari data deskriptif Task existing |
| Provenance | Referensi asal template/checkpoint/Task untuk audit |

## 10. User journey

### 10.1 Tambah Task dari Project

1. Pengguna membuka detail Project.
2. Pengguna menuju section **Kalender & Task**.
3. Pengguna menekan **Tambah Task** atau memilih tanggal.
4. Project otomatis terkunci.
5. Tanggal yang dipilih menjadi default Tanggal Assign.
6. Pengguna mengisi nama, kategori, prioritas, deadline, catatan, attachment, Branch, dan PIC.
7. Sistem memvalidasi data dan permission.
8. Task dibuat memakai workflow existing dan muncul pada kalender.

### 10.2 Gunakan Template Checkpoint

1. Pengguna menekan **Gunakan Template**.
2. Pengguna memilih template aktif.
3. Pengguna memilih Tanggal Acuan.
4. Sistem menampilkan Preview Timeline.
5. Pengguna dapat menonaktifkan checkpoint yang tidak diperlukan.
6. Pengguna dapat mengoreksi nama, prioritas, tanggal assign, dan deadline pada preview.
7. Pengguna memilih Branch dan PIC yang berlaku untuk seluruh checkpoint terpilih.
8. Sistem melakukan validasi final seluruh batch.
9. Pengguna mengonfirmasi jumlah Task dan penerima notification.
10. Sistem membuat seluruh Task dalam satu transaction.
11. Kalender dimuat ulang dan menampilkan Task hasil template.

### 10.3 Copy Task

1. Pengguna membuka detail Task.
2. Pengguna memilih **Copy Task**.
3. Project saat ini menjadi default Project tujuan.
4. Pengguna dapat memilih Project tujuan lain jika memiliki akses.
5. Pengguna menentukan Tanggal Assign baru.
6. Deadline dihitung dengan mempertahankan durasi Task sumber.
7. Pengguna memilih Branch dan PIC baru.
8. Pengguna memeriksa data dan menyimpan.
9. Task baru dibuat dengan status awal existing tanpa membawa histori sumber.

## 11. Aturan bisnis kalender per Project

1. Kalender hanya menampilkan Task milik Project yang sedang dibuka.
2. Query dibatasi pada rentang tanggal kalender yang terlihat.
3. Project archived/read-only tidak menerima mutation baru kecuali requirement lanjutan menyatakan lain.
4. Klik tanggal hanya aktif jika pengguna dapat membuat Task pada Project tersebut.
5. Project tidak dikirim sebagai input bebas ketika form dibuka dari detail Project.
6. Detail Task tetap melakukan authorization record-level.
7. Kalender desktop memakai month grid; mobile memakai agenda list.
8. Tanggal rilis Project ditampilkan sebagai marker kontekstual, bukan Task.
9. Filter status, kategori, Branch, PIC, dan Tugas Saya tidak mengubah authorization query.
10. Filter kosong tidak dianggap loading atau error state.

## 12. Aturan bisnis Template Checkpoint

1. Template wajib mempunyai nama unik yang aktif secara case-insensitive atau aturan uniqueness yang disepakati saat audit database.
2. Template aktif wajib mempunyai minimal satu checkpoint sebelum dapat diterapkan.
3. Maksimal 30 checkpoint dapat diterapkan dalam satu batch pada versi awal.
4. Checkpoint mempunyai `sort_order` yang stabil.
5. Nama Task, kategori, prioritas, offset assign, dan offset deadline wajib valid.
6. `due_offset_days` tidak boleh menghasilkan deadline sebelum assigned date.
7. Offset boleh negatif bila Tanggal Acuan adalah tanggal rilis dan pekerjaan harus dilakukan sebelum rilis.
8. Template tidak menyimpan status runtime, PIC, follow-up, review, reminder, atau hasil.
9. Template yang pernah digunakan dinonaktifkan, bukan dihapus dari UI normal.
10. Edit template hanya memengaruhi penerapan berikutnya.
11. Penerapan template yang sama ke Project yang sama diizinkan setelah peringatan dan konfirmasi eksplisit.
12. Preview tidak membuat record Task atau mengirim notification.
13. Checkpoint yang dinonaktifkan pada preview tidak dibuat.
14. Seluruh checkpoint terpilih menggunakan Branch/PIC yang sama pada MVP.

## 13. Tanggal acuan dan kalkulasi

### 13.1 Pilihan Tanggal Acuan

- **Tanggal Mulai Project**: `rnd_projects.start_date`.
- **Tanggal Rilis Project**: `rnd_projects.end_date` sesuai penggunaan kalender existing.
- **Tanggal Khusus**: dipilih pengguna dan divalidasi sebagai tanggal.

### 13.2 Rumus

```text
assigned_date = anchor_date + assigned_offset_days
due_date      = anchor_date + due_offset_days
```

### 13.3 Contoh

Jika tanggal rilis 30 Oktober 2026:

| Checkpoint | Assign Offset | Due Offset | Hasil |
| --- | ---: | ---: | --- |
| Trial Resep | -21 | -18 | 9–12 Oktober 2026 |
| Tasting Internal | -14 | -12 | 16–18 Oktober 2026 |
| Quality Control | -7 | -5 | 23–25 Oktober 2026 |
| Product Photography | -4 | -2 | 26–28 Oktober 2026 |
| Launching | 0 | 0 | 30 Oktober 2026 |

Kalkulasi tanggal berada pada class domain yang reusable dan mempunyai unit test. UI hanya mengirim anchor dan override preview yang tervalidasi.

## 14. Aturan bisnis Copy Task

### 14.1 Data yang disalin

- title;
- task type/category;
- description;
- priority;
- durasi dari assigned date ke due date.

### 14.2 Data yang tidak disalin

- status;
- Branch/PIC tanpa konfirmasi ulang;
- assignment status dan timestamp;
- follow-up dan result attachment;
- review note dan reviewer;
- reminder;
- completed by/at;
- activity log;
- instruction attachment pada MVP.

### 14.3 Aturan tambahan

1. Task Completed atau Cancelled dapat menjadi sumber karena yang disalin hanya blueprint deskriptif.
2. Pengguna harus dapat melihat Task sumber dan membuat Task pada Project tujuan.
3. Branch/PIC divalidasi ulang menggunakan akses terkini.
4. Tanggal Assign baru wajib diisi.
5. Deadline default mempertahankan jumlah hari Task sumber, tetapi boleh dikoreksi sebelum simpan.
6. Copy selalu menghasilkan record, assignment, reminder, dan notification baru sesuai workflow create existing.
7. Source Task tidak dimutasi.
8. Submit ganda tidak boleh menghasilkan dua Task tanpa feedback yang jelas.

## 15. Status dan workflow

Fitur ini tidak mengubah enum atau transition Task/Assignment existing.

### 15.1 Status Task existing

- Draft
- Assigned
- In Progress
- Submitted
- Revision Required
- Completed
- Cancelled

Implementasi aktual `CreateProjectTaskAction` langsung membuat Task `Assigned` karena Branch dan PIC wajib tersedia. Apply template dan Copy Task mengikuti kontrak aktual tersebut pada MVP. Pengaktifan kembali alur Draft merupakan scope terpisah dan tidak boleh dimasukkan diam-diam dalam feature ini.

### 15.2 Status Assignment existing

- Assigned
- In Progress
- Submitted
- Revision Required
- Approved
- Cancelled

Task hasil template/copy menggunakan agregasi status, follow-up, review, reminder, dan cancellation existing tanpa jalur khusus.

## 16. Model data target

### 16.1 `rnd_project_task_templates`

| Kolom | Tipe konseptual | Aturan |
| --- | --- | --- |
| `id` | bigint | Primary key |
| `name` | string | Wajib |
| `description` | text nullable | Tujuan/penggunaan template |
| `is_active` | boolean | Default true, indexed |
| `created_by` | FK users nullable | `nullOnDelete` |
| `created_at`, `updated_at` | timestamps | Wajib |

### 16.2 `rnd_project_task_template_checkpoints`

| Kolom | Tipe konseptual | Aturan |
| --- | --- | --- |
| `id` | bigint | Primary key |
| `rnd_project_task_template_id` | FK | Cascade delete hanya sebelum template digunakan; UI normal memakai deactivate |
| `title` | string | Wajib, maks. 255 |
| `task_type` | string | Nilai `RndProjectTaskCategory` |
| `description` | text nullable | Instruksi default |
| `priority` | string | Nilai `RndProjectTaskPriority`, default medium |
| `assigned_offset_days` | signed integer | Wajib |
| `due_offset_days` | signed integer | Wajib |
| `sort_order` | unsigned integer | Wajib |
| timestamps | timestamps | Wajib |

Index yang direncanakan: `(rnd_project_task_template_id, sort_order)`.

### 16.3 `rnd_project_task_template_applications`

Tabel ini memberi audit boundary untuk satu batch apply dan mendukung peringatan duplicate application.

| Kolom | Tipe konseptual | Aturan |
| --- | --- | --- |
| `id` | bigint | Primary key |
| `rnd_project_id` | FK | Project tujuan |
| `rnd_project_task_template_id` | FK nullable | `nullOnDelete`, histori tetap ada |
| `template_name` | string | Snapshot nama template |
| `anchor_type` | string | project_start/project_release/custom |
| `anchor_date` | date | Tanggal acuan final |
| `idempotency_key` | UUID/string | Unique; mencegah submit/retry membuat batch ganda |
| `applied_by` | FK users nullable | `nullOnDelete` |
| `applied_at` | timestamp | Waktu batch berhasil dibuat |
| `created_at`, `updated_at` | timestamps | Wajib |

Constraint/index yang direncanakan: unique `idempotency_key`, index `(rnd_project_id, rnd_project_task_template_id)`, dan `created_at` bila pola query membuktikan perlu.

### 16.4 Perubahan `rnd_project_tasks`

Kolom nullable:

- `rnd_project_task_template_application_id`;
- `rnd_project_task_template_checkpoint_id`;
- `copied_from_task_id` self-reference.

Foreign key menggunakan `nullOnDelete` agar Task snapshot dan histori operasional tidak hilang jika sumber tidak tersedia. Index ditambahkan pada foreign key yang dipakai query provenance.

### 16.5 Relasi konseptual

```text
RndProjectTaskTemplate
    └── hasMany TemplateCheckpoint

RndProject
    ├── hasMany TemplateApplication
    └── hasMany RndProjectTask

TemplateApplication
    ├── belongsTo Project
    ├── belongsTo Template (nullable)
    └── hasMany generated Tasks

RndProjectTask
    ├── belongsTo TemplateApplication (nullable)
    ├── belongsTo TemplateCheckpoint (nullable)
    └── belongsTo copiedFromTask (nullable)
```

Migration dibuat sebagai file baru melalui Artisan. Migration existing yang sudah berjalan tidak dimodifikasi. `down()` harus reversible selama aman, tetapi rollback production tidak boleh menghapus data tanpa prosedur yang disetujui.

## 17. Arsitektur target

### 17.1 Action

```text
app/Actions/Rnd/ProjectTask/
├── CreateProjectTaskAction.php              existing, dipertahankan
├── UpdateProjectTaskAction.php              existing, dipertahankan
├── ApplyProjectTaskTemplateAction.php       baru
└── CopyProjectTaskAction.php                baru
```

`ApplyProjectTaskTemplateAction` bertanggung jawab atas satu use case batch:

- authorize dilakukan caller/Policy sesuai pola existing;
- memvalidasi payload domain;
- menghitung/mengecek seluruh tanggal;
- memvalidasi seluruh Branch/PIC sebelum insert;
- membuat application record;
- membuat seluruh Task dan assignment dalam satu transaction;
- mengembalikan collection Task yang dibuat;
- menjadwalkan notification setelah commit.

`CopyProjectTaskAction` bertanggung jawab atas:

- menerima Task sumber, Project tujuan, tanggal baru, dan assignment;
- membuat payload create yang bersih;
- menyimpan provenance;
- tidak menyalin state runtime/histori.

### 17.2 Shared creation core

Aturan deadline, validasi assignment, pembuatan pivot, pembuatan assignment, dan notification saat ini berada pada `CreateProjectTaskAction`. Sebelum apply template memanggil pembuatan berulang, lakukan characterization test lalu ekstrak hanya bagian yang benar-benar perlu digunakan bersama.

Targetnya bukan membuat repository/DTO/interface generik. Targetnya mencegah dua implementasi aturan Branch/PIC dan memastikan notification tidak terkirim sebelum outer transaction commit.

### 17.3 Query kalender

Karena kalender global dan kalender Project memakai mekanisme rentang tanggal yang sama, query/read model boleh diekstrak menjadi class kohesif, misalnya query object/service yang menerima:

- start/end date;
- Project scope opsional;
- Branch/user permission context;
- filter kategori/status/PIC/Tugas Saya.

Nama dan lokasi final mengikuti audit pola repository saat phase implementasi. Query object tidak menyimpan state UI dan tidak menghasilkan markup.

### 17.4 Livewire/Page

- `ListProjects` mempertahankan kalender global.
- `ViewProject` mendapat section kalender Project melalui dedicated Livewire child component agar class detail Project yang sudah besar tidak mengambil seluruh state dan mutation kalender.
- Child component menerima konteks Project yang terkunci, melakukan query ulang dan authorization server-side, serta memakai Action/query service bersama. Lokasi namespace final mengikuti audit sibling component saat implementasi.
- Jangan menyalin seluruh `HasProjectTaskCalendar` ke `ViewProject` tanpa pemisahan tanggung jawab.
- Detail/modal berat dimuat saat dibuka.
- Identifier sensitif dikunci atau selalu di-query ulang melalui relasi lalu diotorisasi.
- Public property tidak memuat seluruh Task, seluruh User, atau seluruh template jika data dapat dicari/paginasi server-side.

## 18. Transaction, idempotency, dan side effect

1. Preview bersifat read-only.
2. Apply template memakai satu database transaction untuk application, Task, pivot Branch, dan assignment.
3. Seluruh data divalidasi sebelum record pertama dibuat.
4. Notification dikirim setelah transaction commit.
5. Tombol submit disabled saat request berjalan.
6. Setiap pembukaan flow Apply menghasilkan submission/idempotency key yang dipersistenkan pada application record.
7. `idempotency_key` mempunyai unique constraint; retry key yang sama mengembalikan hasil application existing atau feedback sukses yang aman tanpa membuat Task baru.
8. Kegagalan notification setelah commit tidak me-rollback Task; kegagalan dicatat dan dapat diretry sesuai kebijakan notification existing.
9. Apply tidak membungkus upload atau call eksternal panjang di dalam transaction.
10. Maksimal checkpoint per batch membatasi fan-out notification dan waktu request.

## 19. Branch dan PIC

1. Setiap Branch wajib mempunyai minimal satu PIC.
2. PIC wajib aktif dan dapat mengakses Branch saat mutation.
3. Pengguna all-branch tidak otomatis menjadi PIC.
4. Eligibility memakai `ProjectTaskAssigneeResolver` existing.
5. Pilihan PIC yang stale harus ditolak server meskipun masih terlihat di browser.
6. Duplicate `(branch_id, user_id)` ditolak sebelum insert dan oleh constraint existing.
7. Branch/PIC tidak disimpan pada template MVP.
8. Pada Apply Template MVP, satu mapping Branch/PIC digunakan untuk seluruh Task yang dibuat.
9. Pada Copy Task, mapping sumber boleh ditawarkan sebagai pilihan awal hanya jika seluruh PIC masih eligible; pengguna tetap harus mengonfirmasi.
10. Assignment merupakan snapshot dan tidak dihapus ketika akses Branch pengguna berubah kemudian.

## 20. Attachment dan file lifecycle

1. Attachment instruksi Task existing tetap mengikuti disk, validasi, authorization download, dan lifecycle existing.
2. Template tidak memiliki attachment pada MVP.
3. Copy Task tidak menyalin attachment pada MVP.
4. Result attachment, follow-up attachment, dan review tidak pernah disalin.
5. Jika copy attachment ditambahkan kemudian, file wajib diduplikasi secara fisik atau mempunyai ownership/reference-count contract yang eksplisit; berbagi path tanpa lifecycle jelas dilarang.
6. Tidak ada file disimpan saat preview.
7. Upload Task tunggal tetap membatasi MIME, extension, ukuran, dan jumlah sesuai kontrak existing.

## 21. UI dan UX

Seluruh UI mengikuti `docs/ui-consistency-prd.md` dan pola R&D Project Workspace existing.

### 21.1 Detail Project

Tambahkan section **Kalender & Task** setelah identitas/informasi utama Project pada posisi final yang ditentukan setelah review panjang halaman aktual.

Header section:

- ikon kalender 20–24 px;
- judul dan deskripsi singkat;
- aksi utama **Tambah Task**;
- aksi sekunder **Gunakan Template**.

Section menggunakan:

- `bg-white` dengan dark variant existing;
- `border border-gray-200`, dark `border-gray-700`;
- `rounded-2xl`;
- tanpa shadow biasa;
- padding dan gap sesuai token UI PRD.

### 21.2 Kalender

- Desktop: month grid tujuh kolom.
- Mobile: agenda list, bukan grid yang dipaksa mengecil.
- Kontrol bulan, Today, filter, dan reset dikelompokkan.
- Filter mempunyai label terlihat dan stack pada mobile.
- Warna status mengikuti enum/semantik existing dan selalu disertai teks/tooltip.
- Nama panjang wrap/truncate dengan informasi penuh tersedia pada detail.
- Empty state membedakan “tidak ada Task” dari loading atau error.

### 21.3 Modal Tambah/Edit Task

- Project tampil sebagai konteks read-only, bukan select, saat berasal dari detail Project.
- Form default satu kolom; dua kolom hanya untuk pasangan tanggal/prioritas yang tetap terbaca.
- Error tampil dekat field.
- Validasi gagal mempertahankan input dan modal terbuka.
- Submit mempunyai loading dan disabled state.
- Close/cancel tidak menyimpan.

### 21.4 Modal Gunakan Template

Alur modal/wizard tetap maksimal satu modal utama aktif:

1. Pilih Template dan Tanggal Acuan.
2. Preview Timeline.
3. Branch & PIC.
4. Konfirmasi.

Implementasi boleh memakai step dalam satu modal lebar atau page khusus jika konten terbukti terlalu panjang. Jangan menumpuk modal picker di atas modal utama. Picker menggunakan section/panel dalam modal yang sama atau navigasi step.

Preview menampilkan:

- checkbox aktif per checkpoint;
- nomor urut;
- nama dan kategori;
- tanggal assign dan deadline;
- prioritas;
- error lokal jika tanggal tidak valid.

### 21.5 Modal Copy Task

Form dibuat ringkas:

- Project tujuan;
- Nama Task;
- Tanggal Assign;
- Deadline;
- Branch dan PIC;
- ringkasan data yang tidak ikut disalin.

Project tujuan dikunci bila aksi berasal dari kalender Project dan pengguna tidak memilih “Copy ke Project lain”.

### 21.6 Pengelolaan Template

Gunakan Resource/Page R&D yang konsisten dengan struktur Filament existing:

- daftar template aktif/nonaktif;
- create/edit template;
- checkpoint dapat ditambah, dihapus, dan diurutkan;
- preview urutan dan offset;
- template yang pernah digunakan mempunyai aksi nonaktifkan, bukan delete normal.

### 21.7 Modal accessibility

- `role="dialog"` dan `aria-modal`;
- accessible name;
- Escape, close button, dan backdrop sesuai pola;
- fokus masuk dan kembali ke trigger;
- focus trap;
- body scroll internal, maksimal sekitar 90vh;
- halaman belakang terkunci;
- tidak ada stacked primary modal.

## 22. UI states wajib

| State | Perilaku |
| --- | --- |
| Loading kalender | Indikator pada area kalender; tidak menampilkan empty state palsu |
| Loading preview | Tombol dan area preview menunjukkan proses |
| Empty kalender | Penjelasan dan aksi Tambah Task/Gunakan Template bila diizinkan |
| Empty template | Penjelasan bahwa belum ada template aktif serta link pengelolaan bila diizinkan |
| Validasi gagal | Error dekat field/checkpoint, state tidak hilang |
| Duplicate warning | Menyebut template pernah diterapkan dan meminta konfirmasi eksplisit |
| Save berhasil | Notification singkat, kalender diperbarui |
| Save gagal | Pesan aman dan dapat ditindaklanjuti; tidak menampilkan exception teknis |
| Tidak punya permission | Aksi tidak tampil, server tetap menolak mutation |
| Project archived/read-only | Kalender dapat dibaca; mutation disabled dengan alasan |
| Partial notification failure | Task tetap tersimpan; kegagalan notification dicatat untuk retry |

## 23. Performance dan query

1. Kalender hanya mengambil Task dalam visible range.
2. Kalender Project selalu menambahkan `rnd_project_id` pada query SQL, bukan memfilter Collection penuh.
3. Eager-load hanya Project/Branch/assignment/user yang dibutuhkan tampilan.
4. Detail follow-up, review, dan attachment dimuat ketika detail dibuka.
5. Template picker memakai server-side search/pagination jika jumlah template bertambah.
6. PIC dimuat berdasarkan Branch, bukan seluruh User ke public property.
7. Preview maksimal 30 checkpoint sehingga payload/DOM terkontrol.
8. Ukur query count, duplicate query, render time, Livewire payload, dan DOM sebelum/sesudah.
9. Index baru hanya ditambahkan berdasarkan pola query dan verifikasi schema/`EXPLAIN`.
10. Kalender global tidak boleh mengalami pertumbuhan query sebanding jumlah Task (N+1 regression).

## 24. Security

1. Policy menjadi sumber authorization untuk view/create/update/copy/apply/manage template.
2. Tombol tersembunyi bukan pengganti authorization server.
3. Project ID, Task ID, template ID, Branch ID, dan user ID selalu di-query ulang dan diotorisasi.
4. Task tujuan harus berada pada Project yang dapat diakses pengguna.
5. Branch scope berlaku pada list dan detail.
6. Apply/Copy menolak PIC inactive atau tidak eligible.
7. Field enum divalidasi terhadap enum existing.
8. Tanggal dan offset mempunyai batas integer yang wajar untuk mencegah nilai ekstrem.
9. Output Blade memakai escaping default.
10. Attachment memakai authorized controller/temporary URL existing dan tidak mengekspos path sensitif.
11. Audit log mencatat create/update/deactivate template, apply template, dan copy Task.

## 25. Notification dan reminder

1. Task hasil template/copy mengikuti notification assignment existing.
2. Notification tidak dikirim saat preview.
3. Notification batch dikirim setelah commit.
4. Penerapan besar tidak boleh menahan transaction sambil melakukan side effect eksternal.
5. Jika fan-out terbukti berat, notification dapat dipindah ke queued listener/job yang idempotent tanpa mengubah hasil mutation lokal.
6. Reminder existing membaca assignment Task hasil template/copy tanpa cabang logika khusus.
7. Notification tidak memuat catatan atau path attachment sensitif berlebihan.

## 26. Compatibility dan migration strategy

1. Record Task existing tetap valid karena seluruh kolom provenance nullable.
2. Route kalender global dan deep-link Task existing dipertahankan.
3. Filter dan permission existing dipertahankan.
4. Tidak ada backfill wajib untuk Task lama.
5. Template/checkpoint dimulai kosong; seeding template bisnis dilakukan hanya jika disetujui terpisah.
6. Permission baru ditambahkan secara idempotent, tetapi sync role production memerlukan langkah deployment eksplisit.
7. Migration dibuat bertahap dan tidak mengubah migration lama.
8. Feature dapat dirilis per phase tanpa memindahkan semua UI sekaligus.
9. Trait existing baru diperkecil setelah consumer baru lulus characterization/regression test; jangan menghapusnya di awal.

## 27. Testing strategy

### 27.1 Characterization existing

- Kalender global tetap menampilkan release dan Task.
- Klik tanggal tetap membuka create Task.
- Filter global tetap bekerja.
- Branch scope dan Tugas Saya tetap aman.
- Workflow create/edit/cancel/progress/review/reminder existing tetap lulus.

### 27.2 Unit/domain test

- Kalkulasi offset positif, nol, dan negatif.
- Deadline tidak sebelum assigned date.
- Durasi copy dipertahankan.
- Payload copy mengecualikan field runtime.
- Template/checkpoint ordering.
- Batas maksimal checkpoint.

### 27.3 Action test

- Apply membuat N Task sesuai checkpoint aktif.
- Apply menyimpan provenance/application.
- Semua Task menjadi snapshot.
- Edit template tidak mengubah Task existing.
- Invalid PIC menyebabkan rollback seluruh batch.
- Invalid date menyebabkan rollback seluruh batch.
- Duplicate Branch/PIC ditolak.
- Copy membuat Task baru dan tidak memutasi sumber.
- Copy tidak membawa assignment/follow-up/review/reminder/completion/attachment.
- Notification dijadwalkan/dikirim tepat satu kali per assignment.

### 27.4 Policy/permission test

- Viewer template tidak dapat manage.
- User tanpa apply permission ditolak.
- User tanpa copy permission ditolak.
- User tanpa create permission tidak dapat menghasilkan Task melalui apply/copy.
- User tidak dapat copy ke Project di luar akses.
- Branch outsider tidak dapat membuka Task detail.
- Own assignee view contract tetap berlaku.

### 27.5 Livewire/feature test

- Project context terkunci pada detail Project.
- Klik tanggal mengisi assigned date.
- Preview mempertahankan input saat validation error.
- Checkpoint dapat dipilih/dilepas dari preview.
- Duplicate warning muncul untuk template yang pernah diterapkan.
- Double submit tidak membuat batch ganda.
- Kalender diperbarui setelah create/apply/copy.
- Empty/loading/error/read-only state.
- Modal buka/tutup, Escape, cancel, dan focus behavior.

### 27.6 Performance test

- Query kalender tidak tumbuh linear terhadap jumlah Task.
- Kalender Project mengeksekusi scope Project pada SQL.
- Preview tidak melakukan query berulang per checkpoint.
- Detail berat tidak dimuat sebelum dibuka.

### 27.7 Visual review

- Viewport 360, 390, 768, 1024, dan 1440 px.
- Zoom 200%.
- Dark mode pada panel yang mendukung.
- Nama Project/Task/template panjang.
- 30 checkpoint pada preview.
- Banyak Branch/PIC.
- Error per checkpoint.
- Agenda mobile tanpa horizontal page scroll.

Test tidak perlu mengunci seluruh rangkaian class Tailwind. Uji perilaku, state, label penting, authorization, dan hasil mutation.

## 28. Observability dan audit

- Activity log template: create, update, activate, deactivate.
- Activity log Task: source template/checkpoint atau source Task.
- Application record menyimpan actor, Project, template snapshot, anchor, dan waktu.
- Log kegagalan apply mencantumkan correlation/batch context tanpa data sensitif.
- Metrik yang dipantau: jumlah Task per apply, durasi apply, kegagalan validasi, notification failure, query count, dan payload Livewire.
- Jangan menyimpan exception teknis sebagai pesan yang ditampilkan langsung ke pengguna.

## 29. Phase implementasi

### Phase 0 — Audit dan characterization

- Audit repository aktual, route, Page, trait, model, Action, Policy, permission, notification, reminder, attachment, dan test.
- Rekam query count, payload, DOM, dan perilaku kalender global.
- Tambahkan characterization test yang belum tersedia.
- Finalisasi matrix permission dan keputusan Project archived.

**Selesai jika:** kontrak existing terdokumentasi dan test gagal bila perilaku kritis berubah.

### Phase 1 — Refactor fondasi reusable

- Pisahkan query kalender dari state UI jika reuse konkret sudah dibuktikan.
- Pisahkan core create Task yang diperlukan apply/copy tanpa abstraction generik berlebihan.
- Pastikan notification terjadi setelah commit.
- Pertahankan UI dan behavior existing.

**Selesai jika:** kalender global tetap identik secara perilaku dan core create dapat dipakai use case baru.

### Phase 2 — Template dan checkpoint backend

- Migration baru.
- Model, casts, relation, factory, Policy, permission.
- Action manage/apply serta calculator tanggal.
- Test invariant, authorization, dan rollback.

**Selesai jika:** template dapat dikelola dan diterapkan melalui Action tanpa UI kalender baru.

### Phase 3 — Pengelolaan Template UI

- Resource/Page daftar dan form template.
- Reorder checkpoint.
- Active/inactive state.
- Preview offset.
- Responsive/accessibility review.

**Selesai jika:** template dapat dikelola tanpa mengubah workflow Task.

### Phase 4 — Kalender per Project

- Section Kalender & Task pada detail Project.
- Query scoped Project dan visible range.
- Agenda mobile.
- Tambah/edit/detail Task dengan Project terkunci.
- State loading/empty/error/read-only.

**Selesai jika:** Task Project dapat dikelola tanpa memilih Project kembali.

### Phase 5 — Apply Template UI

- Pilih template dan anchor.
- Preview editable.
- Pilih checkpoint.
- Branch/PIC mapping.
- Konfirmasi dan idempotency guard.
- Refresh kalender setelah berhasil.

**Selesai jika:** batch Task dibuat atomik dan sesuai preview.

### Phase 6 — Copy Task

- Aksi dari detail Task.
- Target Project dan tanggal baru.
- Branch/PIC confirmation.
- Provenance dan test exclusion histori.

**Selesai jika:** Task baru independen dan sumber tidak berubah.

### Phase 7 — Hardening dan rollout

- Query profiling dan index review.
- Notification fan-out/retry.
- Audit log dan metrics.
- Accessibility, mobile, keyboard, dark mode.
- Full regression suite dan deployment note.

**Selesai jika:** seluruh acceptance criteria dan quality gate terpenuhi.

Setiap phase menjadi PR kecil dengan satu concern. Jangan mencampur feature ini dengan refactor modul lain atau cleanup tanpa bukti.

## 30. Deployment dan rollback

### 30.1 Deployment

1. Backup database sesuai prosedur environment.
2. Deploy code phase terkait.
3. Jalankan migration baru.
4. Jalankan permission seeder/sync yang secara eksplisit disiapkan.
5. Clear/cache sesuai prosedur deployment.
6. Jalankan smoke test template, kalender Project, create/apply/copy, permission, Branch/PIC, notification, dan reminder.
7. Pantau error log, queue, notification, query, dan latency.

### 30.2 Rollback

- UI/route baru dapat dinonaktifkan tanpa menghapus Task yang sudah dibuat.
- Menonaktifkan Template tidak menghapus Task snapshot.
- Scheduler/reminder existing tetap berjalan untuk Task hasil template/copy.
- Rollback migration yang menghapus tabel/kolom hanya boleh dilakukan jika tidak ada data production atau setelah backup/recovery disetujui.
- Task hasil template/copy tidak dihapus otomatis saat feature di-rollback.

## 31. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Trait/Page semakin besar | Ekstrak query dan Action berdasarkan reuse konkret; Page tetap fokus state UI |
| Batch hanya tersimpan sebagian | Satu transaction dan pre-validation seluruh checkpoint |
| Notification terkirim sebelum commit | Dispatch setelah commit; retry-safe bila dipindah ke queue |
| Template edit mengubah histori | Task snapshot dan provenance nullable |
| Template diterapkan dua kali | Duplicate warning, confirmation, dan idempotency submission key |
| PIC stale/tidak eligible | Query ulang serta validasi server tepat sebelum mutation |
| Banyak checkpoint memperbesar payload | Batas 30, lazy/server-side data, preview terkontrol |
| Kalender global mengalami N+1 | Eager loading dan regression query-count test |
| Copy membawa data sensitif/histori | Allowlist field copy; test eksplisit field yang dikecualikan |
| Copy attachment merusak lifecycle | Attachment tidak disalin pada MVP |
| UI terlalu kompleks di mobile | Agenda list dan wizard satu modal, review viewport minimum |
| Perubahan route/permission merusak pengguna existing | Preserve contract dan characterization test |

## 32. Acceptance criteria

### Kalender per Project

- [ ] Detail Project mempunyai section Kalender & Task.
- [ ] Kalender hanya menampilkan Task Project tersebut dalam visible range.
- [ ] Klik tanggal membuka Tambah Task dengan Project terkunci dan tanggal terisi.
- [ ] Desktop month grid dan mobile agenda dapat digunakan tanpa page-level horizontal scroll.
- [ ] Filter tidak melemahkan authorization/branch scope.
- [ ] Kalender global dan deep-link existing tetap berfungsi.

### Template Checkpoint

- [ ] Pengguna berizin dapat membuat, mengubah, mengurutkan, dan menonaktifkan template.
- [ ] Checkpoint menyimpan title, category, description, priority, dan offset tanggal.
- [ ] Preview menunjukkan tanggal final sebelum save.
- [ ] Pengguna dapat mengecualikan checkpoint dari batch.
- [ ] Branch/PIC dipilih saat apply dan divalidasi ulang.
- [ ] Apply menghasilkan Task sesuai preview dalam satu transaction.
- [ ] Kegagalan satu checkpoint me-rollback seluruh batch.
- [ ] Perubahan template tidak mengubah Task existing.
- [ ] Penerapan ulang memunculkan warning dan confirmation.

### Copy Task

- [ ] Copy dapat dilakukan dari detail Task oleh pengguna berizin.
- [ ] Project tujuan dan tanggal baru eksplisit.
- [ ] Deadline default mempertahankan durasi sumber.
- [ ] Branch/PIC dikonfirmasi dan divalidasi ulang.
- [ ] Status, progress, follow-up, review, reminder, completion, activity, dan attachment tidak tersalin.
- [ ] Task sumber tidak berubah.

### Arsitektur, keamanan, dan kualitas

- [ ] Page/Blade tidak menampung transaksi bisnis atau query dalam loop.
- [ ] Apply dan Copy memakai Action reusable.
- [ ] Policy memeriksa permission, record, Project, dan Branch scope.
- [ ] Notification dikirim setelah commit dan tidak ganda.
- [ ] Query count/payload mempunyai baseline dan tidak memburuk tanpa alasan.
- [ ] UI mengikuti token, modal, responsive, accessibility, dan state dari UI PRD.
- [ ] Test terfokus, test modul, Pint, `git diff --check`, dan build bila relevan lulus.
- [ ] Deployment dan rollback note sesuai implementasi aktual tersedia.

## 33. Open decisions sebelum implementasi

1. Apakah Project archived sepenuhnya read-only untuk Task baru, apply, dan copy?
2. Apakah copy lintas Project tersedia bagi seluruh pembuat Task atau hanya role tertentu?
3. Apakah template name harus unik global atau hanya unik di antara template aktif?
4. Apakah penerapan template yang sama boleh diulang tanpa batas setelah confirmation?
5. Apakah mode Branch/PIC per checkpoint perlu masuk phase setelah MVP?
6. Apakah template awal akan dibuat manual oleh R&D atau disediakan melalui seeder terpisah?
7. Apakah section Kalender & Task ditempatkan langsung setelah header Project atau melalui navigasi/tab untuk mengurangi panjang halaman detail?
8. Berapa ambang fan-out notification yang harus dipindahkan ke queue?

Keputusan ini wajib ditutup pada Phase 0 dan dicatat dalam PR/implementation note. Jangan membuat asumsi tersembunyi di kode.

## 34. Quality gates

Setiap phase kode menjalankan yang relevan:

1. `vendor/bin/pint --dirty --format agent` untuk PHP.
2. Pest test terfokus.
3. Test modul R&D Task/Project terkait.
4. `git diff --check`.
5. Route dan Filament discovery check bila Page/Resource berubah.
6. Frontend build bila asset/source frontend berubah.
7. Query/payload comparison bila query atau Livewire state berubah.
8. Security review untuk permission, Branch/PIC, identifier, dan attachment.
9. Visual review desktop/mobile/zoom/keyboard.
10. Deployment dan rollback note untuk migration, permission, notification, dan queue.

Full suite dijalankan pada milestone atau ketika perubahan menyentuh fondasi shared process.

## 35. Definition of done

PRD dianggap selesai ketika:

- dokumen tersedia dan mereferensikan kondisi repository aktual;
- kalender per Project ditetapkan sebagai workspace utama dan kalender global sebagai monitor;
- data model, workflow, Action, Policy, transaction, idempotency, UI state, test, rollout, serta rollback dijelaskan;
- scope dan out of scope tidak ambigu;
- acceptance criteria dan open decisions tersedia;
- referensi `code-remediation-prd.md`, `ui-consistency-prd.md`, dan kontrak kalender existing tidak bertentangan.

Penyelesaian PRD tidak mencakup implementasi kode, migration, perubahan permission, seeding template, commit, push, atau deployment.
