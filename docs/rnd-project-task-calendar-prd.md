# PRD — Kalender Tugas Project R&D

## 1. Identitas dan status

| Atribut | Nilai |
| --- | --- |
| Modul | Research & Development |
| Fitur | Kalender Tugas Project |
| Status | Implemented / Completed |
| Prioritas | P1 |
| Acuan arsitektur | `docs/code-remediation-prd.md` |
| Acuan UI | `docs/ui-consistency-prd.md` |

Dokumen ini menjadi acuan pengembangan Kalender Tugas Project R&D. Implementasi wajib diawali dengan audit repository aktual karena struktur kode dapat berubah setelah dokumen dibuat.

## 2. Latar belakang

Kalender R&D saat ini menampilkan tanggal rilis Project. Tim R&D masih memerlukan mekanisme untuk membuat tugas, menentukan Branch tujuan dan PIC, meminta tindak lanjut, memantau deadline, serta mengingatkan pengguna yang belum menyelesaikan pekerjaannya.

Fitur baru tidak mengganti kalender tanggal rilis. Kalender diperluas agar mempunyai dua konteks yang jelas:

1. tanggal rilis Project atau Menu;
2. tugas operasional yang perlu ditindaklanjuti oleh pengguna.

Istilah pada UI menggunakan **Tugas**. Nama class dan tabel menggunakan **Task** agar mengikuti konvensi kode berbahasa Inggris.

## 3. Tujuan

1. Tim R&D dapat membuat tugas yang terhubung ke Project.
2. Tugas dapat ditujukan ke satu atau beberapa Branch.
3. PIC dipilih dari pengguna aktif yang mempunyai akses ke Branch terkait.
4. PIC dapat memberikan progress dan mengirim hasil tindak lanjut.
5. Tim R&D dapat menyetujui hasil atau meminta revisi.
6. Pengguna menerima notifikasi dan reminder sampai tugas selesai.
7. Pengguna melihat tugas mendesak pada dashboard dan modal saat kunjungan pertama dalam sesi.
8. Seluruh akses, perubahan status, attachment, reminder, dan side effect aman serta dapat diuji.

## 4. Batasan

### Termasuk

- Kalender Tugas pada index Project R&D.
- Form tambah dan edit Tugas.
- Branch target dan PIC per Branch.
- Catatan dan attachment Tugas.
- Progress, tindak lanjut, attachment hasil, review, dan revisi.
- Dashboard Tugas Saya.
- Database notification dan scheduled reminder.
- Filter Project, Branch, kategori, status, PIC, dan Tugas Saya.
- Riwayat perubahan penting.

### Tidak termasuk pada versi awal

- Integrasi kalender eksternal seperti Google Calendar atau Outlook.
- WhatsApp reminder.
- Recurring task kompleks.
- Task dependency atau Gantt chart.
- Real-time websocket. Database notification yang tersedia saat page load sudah cukup untuk versi awal.
- Pengubahan kalender tanggal rilis yang sudah ada.

## 5. Istilah

| Istilah | Arti |
| --- | --- |
| Tugas | Pekerjaan yang dibuat dalam sebuah Project R&D |
| Branch Target | Lokasi atau unit yang menerima Tugas |
| PIC | Pengguna yang wajib memberikan tindak lanjut |
| Tindak Lanjut | Progress atau hasil yang dikirim PIC |
| Reviewer | Pengguna R&D yang menyetujui hasil atau meminta revisi |
| Reminder | Notifikasi berdasarkan kedekatan deadline |

## 6. Keputusan assignment

Branch menentukan lokasi Tugas, sedangkan PIC menentukan pengguna yang wajib menindaklanjuti.

1. Pembuat memilih satu atau beberapa Branch.
2. Pilihan PIC hanya memuat pengguna aktif yang dapat mengakses Branch tersebut.
3. Setiap Branch wajib mempunyai minimal satu PIC sebelum Tugas dibagikan.
4. Pengguna `SUPERADMIN` atau `access_all_branches` tidak otomatis menjadi PIC.
5. Pengguna lain pada Branch dapat melihat Tugas jika mempunyai permission, tetapi tidak dapat mengirim tindak lanjut atas nama PIC.
6. Assignment disimpan sebagai snapshot. Perubahan akses Branch di masa depan tidak menghapus kewajiban yang sudah diberikan.
7. PIC yang dinonaktifkan setelah assignment tetap tercatat dalam histori. Tugas harus dialihkan oleh pengguna yang mempunyai permission assign.

## 7. Peran dan permission

Permission minimum:

- `view rnd project tasks`
- `create rnd project tasks`
- `update rnd project tasks`
- `assign rnd project tasks`
- `respond rnd project tasks`
- `review rnd project task follow ups`
- `cancel rnd project tasks`
- `view all branch rnd project tasks`

Aturan akses:

- Policy menjadi sumber otorisasi record dan action.
- Pengelola R&D dapat membuat, mengubah, membagikan, meninjau, dan membatalkan sesuai permission.
- PIC dapat melihat dan merespons assignment miliknya.
- Pengguna Branch dapat melihat Tugas Branch jika mempunyai permission view.
- Administrator all-branch dapat melihat lintas Branch sesuai permission, tetapi tidak otomatis menjadi PIC.
- UI menyembunyikan aksi yang tidak diizinkan dan server tetap menolak mutation yang tidak sah.

## 8. Data dan relasi

### 8.1 `rnd_project_tasks`

- `id`
- `rnd_project_id`
- `title`
- `task_type`
- `description`
- `assigned_date`
- `due_date`
- `priority`
- `status`
- `created_by`
- `completed_by`
- `completed_at`
- timestamps
- soft delete bila sesuai pola Project R&D

### 8.2 `rnd_project_task_branches`

- `rnd_project_task_id`
- `branch_id`
- unique: `rnd_project_task_id + branch_id`

### 8.3 `rnd_project_task_assignments`

- `id`
- `rnd_project_task_id`
- `branch_id`
- `user_id`
- `status`
- `assigned_at`
- `started_at`
- `submitted_at`
- `reviewed_at`
- `reviewed_by`
- `review_note`
- timestamps
- unique: `rnd_project_task_id + branch_id + user_id`

### 8.4 `rnd_project_task_follow_ups`

- `id`
- `rnd_project_task_assignment_id`
- `submitted_by`
- `follow_up_type`
- `notes`
- `estimated_completion_date`
- timestamps

Follow-up bersifat append-only untuk menjaga histori. Koreksi menghasilkan entri baru atau revisi terhubung, bukan menimpa histori lama.

### 8.5 Attachment

Attachment instruksi dan attachment hasil harus dapat dibedakan. Implementasi mengikuti kebijakan storage aplikasi yang terbukti aktual saat audit, termasuk private access, validasi MIME, ukuran, jumlah, preview, download authorization, dan lifecycle delete.

### 8.6 `rnd_project_task_reminders`

- `id`
- `rnd_project_task_assignment_id`
- `reminder_type`
- `reminder_date`
- `sent_at`
- `notification_id`
- timestamps
- unique: `assignment_id + reminder_type + reminder_date`

Unique constraint mencegah reminder ganda saat scheduler atau job dijalankan ulang.

## 9. Kategori, prioritas, dan status

### Kategori Tugas

- Trial
- Tasting
- Quality Control
- Approval
- Production
- Product Photography
- Launching
- General

Kategori disimpan melalui enum atau sumber terpusat. Penambahan kategori dinamis tidak termasuk versi awal.

### Prioritas

- Low
- Medium
- High
- Urgent

### Status Tugas

- Draft
- Assigned
- In Progress
- Submitted
- Revision Required
- Completed
- Cancelled

`Overdue` merupakan kondisi turunan dari deadline dan status, bukan status utama yang menimpa status workflow.

### Status Assignment

- Assigned
- In Progress
- Submitted
- Revision Required
- Approved
- Cancelled

## 10. Aturan transisi

1. Tugas baru dapat disimpan sebagai Draft.
2. Draft hanya dapat dibagikan jika Project, Branch, PIC, tanggal assign, deadline, dan nama Tugas valid.
3. Deadline tidak boleh lebih awal dari tanggal assign.
4. Assigned berubah menjadi In Progress ketika minimal satu PIC mulai mengerjakan.
5. Tugas berubah menjadi Submitted ketika seluruh assignment aktif telah dikirim.
6. Reviewer dapat menyetujui setiap assignment atau meminta revisi.
7. Tugas menjadi Completed ketika seluruh assignment aktif Approved.
8. Revision Required mengaktifkan kembali kewajiban PIC dan reminder.
9. Cancelled menghentikan reminder tanpa menghapus histori.
10. Mutation perubahan status dijalankan melalui Action dengan database transaction dan validasi transisi terpusat.

## 11. Form Tambah Tugas

Form dibuka melalui modal dari kalender atau detail Project.

| Field | Aturan |
| --- | --- |
| Project | Otomatis dari detail Project atau wajib dipilih dari kalender global |
| Nama Tugas | Wajib |
| Kategori Tugas | Wajib |
| Branch | Wajib, dapat lebih dari satu |
| PIC per Branch | Wajib minimal satu pengguna aktif per Branch |
| Tanggal Assign | Wajib |
| Deadline | Wajib dan tidak lebih awal dari tanggal assign |
| Prioritas | Wajib, default Medium |
| Catatan Task | Instruksi pekerjaan |
| Attachment Task | Opsional, mengikuti batas attachment aplikasi |

Pilihan PIC dimuat setelah Branch dipilih dan memakai server-side search bila jumlah pengguna besar. Perubahan Branch menghapus pilihan PIC yang tidak lagi valid setelah konfirmasi yang jelas.

## 12. Form Tindak Lanjut

PIC dapat:

- memulai pekerjaan;
- menyimpan progress;
- menulis catatan;
- mengunggah attachment hasil;
- mencatat kendala;
- memberikan perkiraan tanggal selesai;
- mengirim hasil untuk direview.

Simpan Progress tidak dianggap sebagai submission. Kirim Tindak Lanjut mengubah assignment menjadi Submitted dan mencegah submit ganda.

## 13. Review R&D

Reviewer melihat hasil per Branch dan PIC, kemudian dapat:

- menyetujui;
- meminta revisi disertai catatan wajib;
- melihat attachment dan histori progress;
- melihat waktu assign, mulai, submit, review, dan selesai.

Review tidak menghapus follow-up sebelumnya. Semua keputusan disimpan dalam histori.

## 14. Kalender dan filter

Index Project menyediakan mode yang jelas:

- Daftar Project;
- Kalender Rilis;
- Kalender Tugas.

Kalender Tugas memiliki filter:

- Bulan;
- Project;
- Branch;
- Kategori;
- Status;
- PIC;
- Hanya Tugas Saya.

Klik tanggal membuka Tambah Tugas dengan tanggal awal terisi. Klik blok Tugas membuka detail. Desktop memakai kalender bulanan; mobile dapat memakai agenda list agar konten tidak terpotong.

Warna status mengikuti `docs/ui-consistency-prd.md` dan selalu disertai teks:

- Blue: Assigned;
- Amber: mendekati deadline;
- Red: overdue;
- Purple: Submitted atau Revision Required sesuai label;
- Green: Completed;
- Gray: Cancelled atau Draft.

## 15. Dashboard dan modal pengingat

Dashboard Employee menampilkan **Tugas yang Perlu Ditindaklanjuti** dengan urutan:

1. overdue;
2. deadline hari ini;
3. prioritas Urgent/High;
4. deadline terdekat.

Kartu menampilkan Nama Tugas, Project, Branch, deadline, hitung mundur, status, dan tombol buka.

Modal pengingat:

- tampil pada kunjungan pertama dalam satu sesi bila ada assignment aktif;
- maksimal lima Tugas paling mendesak;
- menutup modal tidak menyelesaikan Tugas;
- tidak muncul pada setiap navigasi halaman;
- tampil lagi pada sesi berikutnya selama Tugas belum selesai;
- kartu dashboard dan database notification tetap tersedia.

## 16. Notifikasi dan reminder

Notifikasi dikirim ketika:

- assignment baru dibuat;
- PIC atau deadline berubah;
- deadline tinggal 3 hari;
- deadline tinggal 1 hari;
- deadline hari ini;
- Tugas overdue;
- revisi diminta;
- PIC mengirim hasil;
- hasil disetujui;
- Tugas dibatalkan.

Versi awal memakai database notification Laravel/Filament. Scheduler mendistribusikan Job reminder. Job wajib idempotent, mempunyai retry/backoff yang sesuai, dan tidak bergantung pada session atau instance Livewire.

Reminder overdue dikirim maksimal satu kali per hari sampai assignment Submitted, Approved, atau Cancelled. Frekuensi ini harus dapat dipindahkan ke setting global bila kebutuhan operasional berubah.

## 17. Arsitektur target

```text
Filament Page / Resource
    → authorization + presentation validation
    → Action/Rnd/ProjectTask
        → ProjectTaskStatusService / AssigneeResolver
        → Model + database transaction
        → Event / Notification / queued Job
```

Kandidat class:

```text
app/
├── Actions/Rnd/ProjectTask/
│   ├── CreateProjectTaskAction.php
│   ├── UpdateProjectTaskAction.php
│   ├── AssignProjectTaskAction.php
│   ├── SubmitProjectTaskFollowUpAction.php
│   ├── ReviewProjectTaskFollowUpAction.php
│   └── CancelProjectTaskAction.php
├── Services/Rnd/ProjectTask/
│   ├── ProjectTaskAssigneeResolver.php
│   ├── ProjectTaskStatusService.php
│   └── ProjectTaskReminderService.php
├── Jobs/Rnd/ProjectTask/
│   └── SendProjectTaskReminderJob.php
├── Notifications/
├── Models/
├── Policies/RndProjectTaskPolicy.php
└── Enums/
```

Jangan membuat folder atau abstraction baru sebelum audit membuktikan sesuai konvensi repository aktual.

## 18. Standar UI

- Ikuti R&D Project Workspace sebagai referensi utama.
- Container putih, border abu-abu, radius konsisten, dan tanpa shadow pada kartu biasa.
- Aksi utama biru; destructive action merah.
- Gunakan Heroicons melalui Blade component.
- Label memakai kapital setiap kata.
- Informasi umum menggunakan heading **Informasi Pengisian**.
- Attachment mengikuti pola ERP Request yang sudah diaudit.
- Modal memenuhi fokus, Escape, backdrop, scroll, loading, validasi, dan perlindungan submit ganda.
- Loading, empty, error, read-only, save success, dan permission state harus eksplisit.
- Periksa viewport 360, 390, 768, 1024, 1440 px dan zoom 200%.

## 19. Performance dan query

- Kalender hanya mengambil rentang tanggal yang sedang terlihat.
- Eager-load Project, Branch, dan assignment yang diperlukan.
- Detail, follow-up, dan attachment berat dimuat ketika modal dibuka.
- Filter dan pagination dilakukan server-side.
- Hindari menyimpan seluruh pengguna dan seluruh Tugas pada public property Livewire.
- Tambahkan index setelah pola query dibuktikan dengan query profiling atau `EXPLAIN`.
- Dashboard hanya mengambil assignment pengguna aktif yang belum selesai.

## 20. Keamanan dan attachment

- Policy memeriksa permission, Branch, dan ownership assignment.
- ID penting tidak dipercaya dari state browser tanpa query ulang dan authorization.
- Attachment divalidasi MIME, extension, ukuran, dan jumlah.
- Download dan preview attachment memerlukan authorization.
- Nama file serta path tidak mengekspos lokasi sensitif.
- Notification tidak memuat catatan atau URL file sensitif secara berlebihan.
- Audit log mencatat pembuat, perubahan assignment/deadline, submission, review, revisi, completion, dan cancellation.

## 21. Phase implementasi

### Phase 0 — Audit dan kontrak

- Audit kalender Project, dashboard Employee, branch scope, notification, attachment, permission, dan scheduler.
- Rekam kontrak tanggal rilis existing dengan characterization test.
- Finalisasi enum, transition map, reminder cadence, dan policy matrix.

### Phase 1 — Fondasi data dan akses

- Migration, model, enum, factory, relation, index, dan constraint.
- Policy, permission registry, seeder yang idempotent, serta branch-scoped query.
- Test akses dan invariant data.

### Phase 2 — Pengelolaan Tugas R&D

- Modal create/edit.
- Pemilihan Branch dan PIC.
- Attachment instruksi.
- Kalender Tugas, filter, detail, loading, dan empty state.

### Phase 3 — Tugas Saya dan tindak lanjut

- Dashboard Tugas Saya.
- Detail assignment, progress, submission, dan attachment hasil.
- Agenda mobile.

### Phase 4 — Review R&D

- Review per assignment.
- Approve, revision, histori, dan agregasi status terpusat.

### Phase 5 — Notification dan reminder

- Database notification.
- Modal sekali per sesi.
- Scheduler, queued Job, deduplication, dan reminder log.

### Phase 6 — Hardening dan observability

- Query profiling, index review, attachment security, audit log, accessibility, responsive review, dan full regression suite.

Setiap phase harus menjadi scope kecil yang dapat diuji dan direview. Jangan mencampurkan refactor domain lain.

## 22. Test wajib

- Kalender rilis existing tetap bekerja.
- Branch scope membatasi list dan detail.
- PIC hanya dapat dipilih dari pengguna aktif yang dapat mengakses Branch.
- Pengguna all-branch tidak otomatis menjadi PIC.
- Setiap Branch mempunyai minimal satu PIC saat dibagikan.
- Deadline tidak boleh sebelum tanggal assign.
- Duplicate assignment ditolak database dan Action.
- Pengguna tidak dapat merespons assignment orang lain.
- Progress dan histori tidak hilang setelah revisi.
- Status Tugas mengikuti seluruh assignment aktif.
- Mutation berulang tidak membuat duplicate follow-up atau notification.
- Reminder tidak terkirim dua kali untuk key yang sama.
- Assignment selesai tidak muncul pada modal atau reminder.
- Overdue tetap muncul sampai Submitted, Approved, atau Cancelled sesuai aturan.
- Modal hanya muncul sekali per sesi.
- Attachment upload, preview, download, replace, dan delete mengikuti authorization.
- Seluruh test terisolasi dari network eksternal.

## 23. Deployment dan rollback

Deployment dilakukan per phase setelah migration dan test lulus:

1. backup database;
2. deploy kode;
3. jalankan migration;
4. jalankan permission seeder/sync yang secara eksplisit disediakan fitur;
5. clear/cache aplikasi sesuai prosedur deployment;
6. pastikan cron `schedule:run` aktif sebelum reminder diaktifkan;
7. lakukan smoke test permission, kalender, assignment, notification, dan attachment.

Rollback UI dan scheduler tidak boleh menghapus data Tugas. Migration rollback yang menghapus tabel hanya dilakukan jika belum ada data production atau sudah mempunyai prosedur backup dan recovery yang disetujui.

## 24. Acceptance criteria

- R&D dapat membuat Tugas dari kalender atau detail Project.
- Tugas memiliki Branch, PIC, tanggal assign, deadline, catatan, dan attachment.
- PIC menerima notification dan melihat Tugas pada dashboard.
- Modal reminder tampil sekali per sesi selama masih ada Tugas aktif.
- Countdown dan overdue akurat terhadap timezone aplikasi.
- PIC dapat menyimpan progress dan mengirim tindak lanjut.
- R&D dapat approve atau meminta revisi tanpa menghapus histori.
- Filter Branch hanya menampilkan data yang diizinkan.
- Semua mutation penting mempunyai Action, Policy, transaction boundary, dan test.
- UI mengikuti `docs/ui-consistency-prd.md` pada desktop dan mobile.

## 25. Definition of done

- Seluruh acceptance criteria terpenuhi.
- Test terfokus dan full suite lulus dengan konfigurasi memory proyek.
- Pint dijalankan untuk perubahan PHP.
- Build frontend dijalankan bila asset berubah.
- Route, permission, Filament discovery, scheduler, queue, attachment, dan notification diverifikasi.
- Deployment serta rollback terdokumentasi berdasarkan implementasi aktual.
- Status item Notion diperbarui berdasarkan bukti implementasi, bukan perkiraan.
