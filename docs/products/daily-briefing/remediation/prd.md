# PRD — Remediasi Menyeluruh Daily Briefing

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline audit | 12 Oktober 2026 |
| Status | Rancangan siap review; belum mengotorisasi implementasi, cleanup data, migration production, atau deployment |
| Modul | Daily Briefing |
| Aplikasi user | Employee App — Daily Briefing, Kalender, Riwayat, dan Nilai Briefing |
| Back office | Human Resources — Kelola Poin, Monitoring Poin, Kalender, Pengaturan, Bobot, dan Nilai Briefing |
| Stack | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Tailwind CSS 4, Pest 4 |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

Dokumen ini menjadi sumber requirement remediasi Daily Briefing. Implementasi wajib mengaudit kondisi repository dan data aktual pada awal setiap phase karena role, permission, branch, task, route, serta record production dapat berbeda dari baseline lokal.

Dokumen ini tidak memberi izin untuk menghapus data, menjalankan migration production, mengubah role production secara massal, melakukan deployment, atau mengubah formula nilai di luar requirement yang disepakati.

## 2. Ringkasan masalah

Audit menemukan aturan peserta, task, branch scope, deadline, approval, kalender, scoring, dan report tersebar pada Page, Resource, Command, Model, serta Controller dengan kontrak yang berbeda.

Dampak utamanya:

1. User aktif yang bukan peserta dapat dibuatkan record kosong berstatus `rejected`.
2. Cabang yang tidak mengikuti Daily Briefing dapat dianggap ikut karena task global menjadi fallback otomatis.
3. Supervisor dapat melihat dan berpotensi memproses data lintas cabang karena filter Monitoring Poin bersifat fail-open dan memakai permission modul lain.
4. `rejected` mencampur submission yang ditolak dengan kondisi tidak pernah mengisi.
5. Halaman submit belum memastikan user dan task benar-benar ter-assign.
6. Kalender, scoring, dan report memakai definisi peserta serta penyelesaian yang berbeda.
7. Export dapat memakai task yang salah, menghilangkan user yang tidak pernah submit, atau tidak menghormati branch access.
8. Task duplikat dapat menghasilkan kewajiban dan rejection ganda.
9. Test existing lulus tetapi belum melindungi kontrak role, partisipasi cabang, branch isolation, dan report.

## 3. Tujuan

1. Menetapkan satu sumber aturan peserta dan assignment Daily Briefing.
2. Membatasi pengisian kepada `STORE_STAFF` aktif pada cabang peserta.
3. Menjaga seluruh akses review, monitoring, scoring, dan report sesuai cakupan cabang user.
4. Memisahkan `missing`, `submitted`, `supervisor_review`, `approved`, dan `rejected` secara semantik.
5. Menghapus pembuatan submission palsu oleh scheduler.
6. Menyamakan hasil halaman user, kalender admin, Monitoring Poin, scoring, PDF, dan Excel.
7. Menjaga histori ketika user berpindah cabang atau konfigurasi task berubah.
8. Menyediakan cleanup data lama yang aman, terukur, dapat diaudit, dan mempunyai dry-run.
9. Menambah safety net test untuk seluruh workflow kritis.
10. Menyelaraskan UI dengan state bisnis tanpa mengubah pola visual aplikasi secara massal.

## 4. Non-tujuan

- Mengganti Laravel, Filament, Livewire, atau Tailwind.
- Memindahkan modul ke microservice.
- Mendesain ulang seluruh Employee App atau Helpdesk.
- Mengubah seluruh role aplikasi.
- Membuat approval bertingkat baru selain requirement Daily Briefing.
- Mengubah bobot atau rumus nilai tanpa keputusan bisnis terpisah.
- Mengirim WhatsApp, email, atau integrasi eksternal baru.
- Menghapus histori submission valid.
- Menjalankan cleanup production tanpa preview dan persetujuan eksplisit.

## 5. Prinsip produk dan teknis

1. **Explicit participation:** cabang tidak dianggap ikut hanya karena task global tersedia.
2. **Explicit assignment:** kewajiban user harus dapat dijawab dari satu service dengan input user, task, period, dan tanggal.
3. **Server-side authorization:** tombol tersembunyi bukan pengganti Policy atau query scope.
4. **Fail closed:** permission atau branch assignment yang tidak jelas menghasilkan penolakan akses, bukan akses global.
5. **No synthetic submission:** record yang tidak pernah diisi tidak boleh disimpan sebagai submission `rejected`.
6. **Historical snapshot:** histori memakai branch dan assignment pada saat record dibuat, bukan profil user saat ini.
7. **One business definition:** kalender, monitoring, score, dan export memakai resolver yang sama.
8. **Thin UI layer:** Page/Resource mengelola state dan presentasi; assignment, status, dan kalkulasi berada pada service/action.
9. **Characterization first:** perilaku existing yang dipertahankan diberi test sebelum refactor.
10. **Cleanup with evidence:** data lama hanya diubah setelah classification, dry-run, sampling, dan rollback plan.

## 6. Istilah domain

| Istilah | Definisi |
| --- | --- |
| Cabang peserta | Cabang yang secara eksplisit mengaktifkan Daily Briefing pada rentang tanggal tertentu |
| Peserta | User aktif dengan role `STORE_STAFF`, memiliki branch, dan branch tersebut aktif sebagai peserta pada tanggal terkait |
| Task global | Template/default task yang dapat dipakai cabang peserta tanpa konfigurasi khusus |
| Task cabang | Task yang hanya berlaku untuk satu branch |
| Assignment | Keputusan bahwa satu task wajib/tersedia untuk satu user pada period dan tanggal tertentu |
| Submission | `briefing_item` yang benar-benar dibuat melalui aksi user yang sah |
| Missing | Assignment telah melewati deadline tetapi tidak mempunyai submission lengkap |
| Rejected | Submission nyata ditolak reviewer atau auto-reject review karena melewati SLA approval |
| Branch snapshot | `briefing_records.branch_id` yang menyimpan branch user saat submission dibuat |
| Review SLA | Batas waktu submission berstatus menunggu review sebelum ditolak otomatis |

## 7. Aktor dan cakupan akses

| Aktor | Submit | Lihat data sendiri | Review | Monitoring/report | Cakupan branch |
| --- | --- | --- | --- | --- | --- |
| `STORE_STAFF` | Ya, jika peserta | Ya | Tidak | Nilai branch sendiri bila diizinkan | Branch assignment aktif |
| Supervisor Kitchen/Store | Tidak secara default | Tidak sebagai peserta, kecuali juga `STORE_STAFF` | Ya, jika diberi permission review | Branch yang dapat diakses | `accessibleBranchIds()` |
| HRD | Tidak secara default | Tidak | Sesuai permission | Sesuai permission | Branch access atau seluruh branch sesuai otorisasi |
| Super Admin | Tidak dianggap peserta hanya karena admin | Tidak | Ya | Seluruh branch | `canAccessAllBranches()` |

Role dan permission merupakan dua lapisan berbeda:

- role `STORE_STAFF` menentukan kategori peserta;
- permission `submit daily briefing` mengizinkan penggunaan fitur submit;
- permission `review briefing items` mengizinkan Approve/Reject;
- permission view menentukan akses halaman, bukan memperluas branch scope;
- `canAccessAllBranches()` merupakan satu-satunya bypass branch scope standar.

## 8. Sumber kebenaran partisipasi cabang

### 8.1 Data yang dibutuhkan

Buat konfigurasi partisipasi eksplisit, misalnya tabel `briefing_branch_settings`:

| Kolom | Aturan |
| --- | --- |
| `id` | Primary key |
| `branch_id` | Wajib, foreign key, unik untuk konfigurasi aktif sesuai desain final |
| `is_enabled` | Boolean |
| `started_at` | Tanggal pertama cabang mulai diwajibkan |
| `ended_at` | Nullable; tanggal terakhir partisipasi |
| `created_by` | Nullable sesuai pola audit existing |
| `updated_by` | Nullable sesuai pola audit existing |
| timestamps | Wajib |

Jika histori aktivasi/nonaktif berulang diperlukan, gunakan rentang partisipasi terpisah dan unique constraint yang mencegah overlap. Jangan memilih desain satu-row apabila bisnis membutuhkan beberapa periode aktif.

### 8.2 Aturan partisipasi

Cabang aktif pada tanggal `D` jika:

```text
is_enabled = true
AND started_at <= D
AND (ended_at IS NULL OR ended_at >= D)
```

Cabang tanpa konfigurasi bukan peserta. Task global tidak mengaktifkan partisipasi secara implisit.

### 8.3 Aktivasi awal

Migration tidak boleh otomatis mengaktifkan seluruh branch. Daftar branch peserta dan `started_at` harus berasal dari keputusan bisnis atau data production yang diverifikasi.

## 9. Assignment service

Buat service domain bersama, misalnya `BriefingAssignmentService`, tanpa ketergantungan pada Filament atau Livewire.

Kontrak minimum:

```php
public function isParticipant(User $user, CarbonInterface $date): bool;

/** @return Collection<int, BriefingTask> */
public function tasksFor(
    User $user,
    BriefingPeriod $period,
    CarbonInterface $recordDate,
): Collection;

public function taskIsAssignedTo(
    User $user,
    BriefingTask $task,
    CarbonInterface $recordDate,
): bool;

/** @return Collection<int, User> */
public function participantsForBranch(
    Branch $branch,
    CarbonInterface $date,
): Collection;
```

Resolver wajib digunakan oleh:

- `DailyBriefingPage`;
- scheduler deadline dan review SLA;
- Kalender Briefing user dan admin;
- Monitoring Poin;
- scoring;
- Excel/PDF report;
- cleanup/audit command yang membutuhkan klasifikasi assignment.

### 9.1 Resolusi task

Aturan yang disepakati:

1. Jika branch bukan peserta, hasil task kosong.
2. Jika terdapat task cabang aktif untuk period tersebut, gunakan task cabang.
3. Jika tidak terdapat task cabang dan branch peserta mengizinkan default, gunakan task global aktif.
4. Task inactive tidak pernah menjadi assignment baru.
5. Task global adalah template/default, bukan indikator partisipasi.
6. Task key historis tetap dapat ditampilkan meskipun task dinonaktifkan.
7. Satu tugas bisnis tidak boleh muncul ganda dalam assignment aktif.

## 10. Akses halaman dan mutation

### 10.1 Employee App

Halaman Daily Briefing, Kalender, Riwayat, dan Nilai wajib memiliki `canAccess()` atau Policy yang eksplisit.

Untuk halaman submit:

```text
authenticated
AND user active
AND role STORE_STAFF
AND permission submit daily briefing
AND memiliki branch
AND branch peserta pada tanggal terkait
```

`openTaskModal()` dan `saveTask()` wajib memvalidasi assignment server-side. Task key dari Livewire dianggap input tidak tepercaya.

### 10.2 Back office

Monitoring Poin dan aksi Approve/Reject wajib memeriksa:

```text
permission review briefing items
AND record branch termasuk accessibleBranchIds()
```

Pengecualian hanya untuk user yang `canAccessAllBranches()`.

Permission Sales Report tidak boleh dipakai untuk menentukan akses Briefing.

### 10.3 Branch snapshot

Query histori dan monitoring memakai `briefing_records.branch_id`. Relasi ke `users.branch_id` hanya fallback legacy yang terdokumentasi dan harus dihapus setelah backfill tervalidasi.

## 11. Status dan lifecycle

### 11.1 Submission status

| Status | Makna | Memerlukan `briefing_item` |
| --- | --- | --- |
| `not_due` | Assignment belum mencapai deadline | Tidak |
| `missing` | Deadline terlewat dan tidak ada submission lengkap | Tidak |
| `supervisor_review` | Submission lengkap menunggu review | Ya |
| `approved` | Submission disetujui | Ya |
| `rejected` | Submission nyata ditolak | Ya |
| `not_applicable` | User/branch/task tidak ter-assign | Tidak |

`missing`, `not_due`, dan `not_applicable` merupakan hasil evaluasi assignment, bukan nilai `review_status` pada item kosong.

### 11.2 Alur submission

```text
Assigned + sebelum deadline
→ user mengisi bukti/catatan sesuai submission type
→ supervisor_review
→ approved
atau
→ rejected
→ user dapat memperbaiki dan submit ulang selama aturan edit mengizinkan
```

Keputusan apakah submission rejected dapat diperbaiki setelah deadline harus ditetapkan saat implementasi. Default aman: tidak dapat diubah setelah deadline kecuali ada aksi reopen berizin dan tercatat.

### 11.3 Auto-reject review

Auto-reject hanya berlaku untuk item nyata yang:

- `is_completed = true`;
- status menunggu review;
- `completed_at` tidak null;
- melewati `auto_reject_after_days`;
- belum approved/rejected;
- masih konsisten dengan aturan review SLA.

Command wajib idempotent dan tidak mengubah item terminal pada eksekusi berikutnya.

### 11.4 Deadline tanpa submission

Scheduler tidak membuat `briefing_records` atau `briefing_items` untuk kondisi missing. Missing dihitung saat query/report atau disimpan dalam tabel kejadian khusus hanya jika audit permanen benar-benar diperlukan.

## 12. Monitoring Poin

### 12.1 Dataset

Monitoring Poin menampilkan submission nyata. Default query tidak menampilkan synthetic missing item.

Filter minimum:

- branch sesuai akses;
- staff;
- period;
- tanggal;
- review status;
- status completion.

### 12.2 Branch scope

```php
if (! $user->canAccessAllBranches()) {
    $query->whereIn(
        'briefing_records.branch_id',
        $user->accessibleBranchIds(),
    );
}
```

Tidak memiliki permission atau branch assignment tidak boleh menghasilkan query tanpa filter.

### 12.3 Approve/Reject

- Aksi hanya terlihat dan dapat dijalankan untuk item menunggu review.
- Authorization diulang pada server ketika action dijalankan.
- Approve mencatat reviewer dan timestamp.
- Reject mewajibkan alasan dan mencatat reviewer serta timestamp.
- Rejection sistem review SLA memiliki `reviewed_by = null` dan metadata/sumber sistem yang dapat dibedakan dari rejection manual.
- Bulk action wajib memakai Policy yang sama untuk setiap record.

## 13. Kalender

### 13.1 Kalender user

- Hanya menampilkan periode ketika user merupakan peserta.
- Hari ini tidak ditandai missing sebelum deadline task yang relevan terlewati.
- Tanggal sebelum `started_at`, setelah `ended_at`, atau saat user belum menjadi peserta tampil `not_applicable`, bukan missing.
- Status satu hari/period berasal dari seluruh assignment yang berlaku pada tanggal tersebut.
- Loading, empty, error, dan not-applicable state dibedakan.

### 13.2 Kalender admin

- Hanya menghitung branch peserta pada tanggal terkait.
- Branch scope viewer tetap diterapkan.
- Definisi `submitted`, `approved`, `pending`, `rejected`, dan `missing` sama dengan report serta scoring.
- Detail branch tidak menganggap seluruh `STORE_STAFF` production wajib submit apabila unit kewajiban bisnis sebenarnya per branch. Unit kewajiban wajib diputuskan pada Bagian 18.

## 14. Scoring

### 14.1 Cakupan

- Hanya branch peserta pada periode penilaian.
- `scoring_started_at` global tetap dihormati untuk compatibility, tetapi tidak menggantikan `started_at` per branch.
- Task yang dihitung hanya assignment dengan `include_in_score = true`.
- Task inactive tidak membuat expected baru setelah tanggal nonaktif, jika effective dating task diterapkan.
- Bulan berjalan tidak menghitung tanggal masa depan.

### 14.2 Satu definisi unit penilaian

Sebelum implementasi, bisnis wajib memilih salah satu:

1. **Per branch:** satu submission valid per task/period/branch sudah memenuhi kewajiban; atau
2. **Per user:** setiap `STORE_STAFF` peserta wajib menyelesaikan setiap task.

Baseline kode scoring saat ini cenderung per branch karena satu approval dari salah satu user dapat memenuhi satu task harian. Kalender admin saat ini cenderung per user. PRD merekomendasikan **per branch** apabila Daily Briefing merupakan aktivitas toko bersama, tetapi keputusan final harus dikonfirmasi sebelum perubahan scoring.

### 14.3 Reproducibility

Breakdown score menyimpan minimal:

- daftar task/key yang dihitung;
- expected dan approved;
- period start/end efektif;
- branch participation start/end;
- configured dan effective weight;
- versi atau snapshot aturan yang cukup untuk audit.

Recompute periode lama tidak boleh diam-diam memakai task terbaru apabila menghasilkan histori berbeda. Gunakan snapshot atau effective dating sesuai desain implementasi.

## 15. Report dan export

### 15.1 Report kepatuhan briefing

Excel/PDF wajib memakai:

- branch yang diminta dan dapat diakses viewer;
- peserta pada tanggal/periode terkait;
- task hasil assignment branch, bukan selalu task global;
- status hasil evaluasi bersama;
- user/branch yang missing tetap muncul bila report bertujuan mengukur kepatuhan.

Report harus menjelaskan unit kewajiban yang dipakai: per branch atau per user.

### 15.2 Export Monitoring Poin

- Mengekspor submission nyata sesuai filter dan scope halaman.
- Nama task menggunakan label historis/snapshot jika tersedia; fallback ke key bila task lama tidak ditemukan.
- Branch berasal dari `briefing_records.branch_id`.
- Rejection manual dan sistem dapat dibedakan.

### 15.3 Export nilai

- Query branch mengikuti `canAccessAllBranches()` dan `accessibleBranchIds()`.
- Parameter `branch_id` tidak dipercaya tanpa authorization.
- Pilihan branch dalam form hanya menampilkan branch yang dapat diakses.
- PDF dan Excel menggunakan dataset serta label periode yang sama.
- Empty report memberikan pesan yang jelas, bukan file yang tampak berhasil tetapi tanpa konteks.

### 15.4 PDF Employee App

Halaman Nilai dan endpoint PDF memakai permission yang sama. Jika `STORE_STAFF` memang boleh melihat nilai branch sendiri, berikan permission read-only yang sesuai dan batasi query pada branch snapshot/branch aktif user.

## 16. Task management dan data master

### 16.1 Invariant task

- `key` tetap unik.
- Satu branch/period tidak boleh memiliki beberapa task aktif yang mewakili kewajiban bisnis sama tanpa alasan eksplisit.
- Task yang sudah mempunyai histori tidak dihapus; nonaktifkan.
- Perubahan label tidak menghilangkan keterbacaan histori.
- Deadline weekly/monthly tidak diubah massal oleh seeder tanpa requirement.

### 16.2 Seeder

Seeder harus idempotent dan tidak menimpa konfigurasi operasional production secara global. Upsert hanya mengubah field yang memang menjadi ownership seeder.

Hapus perilaku yang mengaktifkan seluruh deadline weekly/monthly setelah upsert apabila bertentangan dengan konfigurasi per task.

### 16.3 Duplikasi existing

Sediakan audit command/report yang mengelompokkan kandidat duplikat berdasarkan:

- branch scope;
- period;
- label ter-normalisasi;
- submission type;
- group;
- deadline.

Keputusan canonical key dibuat eksplisit. Key noncanonical dinonaktifkan setelah seluruh histori dan consumer dipetakan.

## 17. Cleanup dan rekonsiliasi data

### 17.1 Command

Sediakan command terpisah, misalnya:

```bash
php artisan briefing:audit-invalid-records
php artisan briefing:cleanup-invalid-records --dry-run
```

Default wajib non-mutating. Mode mutation memerlukan flag eksplisit dan output ringkasan.

### 17.2 Kandidat synthetic rejection

Kandidat kuat memenuhi seluruh kondisi:

- status `rejected`;
- `is_completed = false`;
- `completed_at` null;
- foto dan catatan kosong;
- `reviewed_by` null;
- alasan sama dengan alasan deadline sistem;
- record/item dibuat sekitar waktu scheduler;
- user/task/branch tidak ter-assign atau tidak mempunyai submission nyata.

Kriteria tersebut belum otomatis memberi izin penghapusan. Command harus mengklasifikasikan:

- aman dibersihkan;
- perlu review manual;
- submission valid, jangan disentuh.

### 17.3 Cleanup action

- Simpan export ID dan nilai lama sebelum mutation.
- Hapus item synthetic atau pindahkan ke audit table sesuai keputusan implementasi.
- Hapus parent record hanya jika tidak mempunyai item valid lain dan tidak dibutuhkan relasi lain.
- Jangan menghapus attachment.
- Recompute score hanya untuk branch/periode terdampak setelah cleanup disetujui.
- Catat jumlah sebelum/sesudah per branch, role, task, period, dan alasan.

## 18. Keputusan bisnis yang wajib dikonfirmasi

| Keputusan | Rekomendasi baseline |
| --- | --- |
| Unit kewajiban | Per branch untuk task briefing bersama |
| Siapa yang dapat submit | `STORE_STAFF` aktif pada branch peserta |
| Apakah semua STORE_STAFF boleh submit task yang sama | Ya, tetapi hanya satu submission canonical per task/branch/period atau tetapkan PIC; perlu keputusan model data |
| Rejected boleh diperbaiki setelah deadline | Tidak, kecuali reviewer melakukan reopen |
| Cabang tanpa task khusus | Memakai task global hanya jika cabang aktif sebagai peserta |
| Missing disimpan atau dihitung | Dihitung; tidak membuat submission palsu |
| Supervisor lintas beberapa branch | Mengikuti `user_branches` dan `accessibleBranchIds()` |
| STORE_STAFF dapat melihat score PDF | Ya, hanya branch sendiri, jika bisnis mengizinkan halaman Nilai |

Implementasi scoring dan uniqueness submission tidak boleh dimulai sebelum unit kewajiban dipastikan.

## 19. UI dan konsistensi

Implementasi mengikuti `docs/standards/ui-consistency.md` dan mempertahankan pola panel existing.

### 19.1 Status visual

| State | Warna | Copy minimum |
| --- | --- | --- |
| Approved | Green | Disetujui |
| Menunggu review | Amber | Menunggu Review |
| Rejected | Red | Ditolak + alasan |
| Missing | Red/neutral sesuai konteks | Tidak Mengisi |
| Belum jatuh tempo | Blue/gray | Belum Jatuh Tempo |
| Tidak berlaku | Gray | Tidak Berlaku |

Warna bukan satu-satunya indikator. Semua state mempunyai label teks.

### 19.2 Monitoring dan report

- Filter mobile bertumpuk dan tetap mempertahankan nilai aktif.
- Empty state membedakan tidak ada submission, tidak ada akses branch, dan filter tanpa hasil.
- Detail menggunakan modal Filament existing bila sesuai.
- Approve/Reject mempunyai loading state dan mencegah submit ganda.
- Reject mempertahankan input ketika validasi gagal.
- Tombol icon-only mempunyai tooltip dan accessible name.
- Tidak ada scroll horizontal pada keseluruhan halaman; tabel lebar memakai container scroll.

### 19.3 Settings partisipasi

Gunakan pola Settings:

1. status aktivasi;
2. tanggal mulai/akhir;
3. informasi konsekuensi;
4. aksi simpan.

Section **Informasi Proses Otomatis** menjelaskan bahwa aktivasi menentukan kalender, missing, scoring, dan report. Jangan memakai kartu statistik dekoratif.

### 19.4 Pemeriksaan visual

Minimal pada viewport 360, 390, 768, 1024, dan 1440 px serta zoom 200%. Periksa dark mode hanya pada panel yang mendukungnya.

## 20. Arsitektur target

```text
Filament Page / Resource / Export Controller / Command
    → Policy + branch scope
    → BriefingAssignmentService
    → BriefingSubmissionAction / BriefingReviewAction
    → BriefingStatusService
    → BriefingScoreCalculator
    → Model + database transaction
```

| Komponen | Tanggung jawab |
| --- | --- |
| `BriefingAssignmentService` | Peserta, branch participation, task assignment |
| `BriefingSubmissionAction` | Validasi assignment, deadline, payload, create/update submission |
| `BriefingReviewAction` | Authorization, approve/reject/reopen, audit metadata |
| `BriefingStatusService` | Derivasi missing/not-due/submitted/reviewed |
| `BriefingScoreCalculator` | Expected, approved, weight, breakdown reproducible |
| Policy/scope | Permission dan branch isolation |
| Page/Resource | Form state, feedback, filter, pagination, modal |
| Command | Orkestrasi batch idempotent dan observability |

Hindari repository/DTO/interface tambahan tanpa kebutuhan nyata.

## 21. Observability

Command otomatis mencatat log terstruktur:

- command/run ID;
- jumlah branch peserta;
- jumlah participant;
- item pending review yang diperiksa;
- item yang auto-rejected karena review SLA;
- jumlah skipped per alasan;
- duration;
- exception class tanpa data sensitif.

Metric minimum:

- submission per branch/period;
- missing rate;
- approval latency;
- auto-reject review count;
- manual rejection count;
- cleanup candidate count;
- duplicate assignment count;
- command failure/retry count.

Jangan mencatat path attachment privat atau isi catatan sensitif pada log.

## 22. Phase implementasi

### Phase 0 — Konfirmasi bisnis dan baseline

- Konfirmasi daftar branch peserta dan tanggal mulai.
- Konfirmasi unit kewajiban per branch atau per user.
- Inventaris role Supervisor Kitchen/Store production dan branch assignment.
- Export baseline jumlah task, duplikat, record, item, rejection, score, dan branch.
- Rekam route, permission, test, serta query penting.

**Selesai jika:** keputusan Bagian 18 yang memengaruhi schema/scoring telah disetujui.

### Phase 1 — Safety net akses

- Tambahkan test role submit.
- Tambahkan test branch isolation Monitoring Poin dan Approve/Reject.
- Tambahkan test task cabang lain tidak dapat disubmit.
- Tambahkan test fail-closed tanpa permission/branch.
- Tambahkan test report tidak dapat meminta branch di luar akses.

**Selesai jika:** test gagal pada kode lama dan mewakili aturan yang disepakati.

### Phase 2 — Partisipasi dan assignment

- Tambahkan schema partisipasi cabang.
- Implementasikan `BriefingAssignmentService`.
- Migrasikan resolusi task dari UI tanpa mengubah data lama.
- Tambahkan cache hanya jika terukur dan memiliki invalidation jelas.

**Selesai jika:** semua consumer utama mendapatkan assignment identik untuk input yang sama.

### Phase 3 — Authorization dan submission

- Tambahkan permission khusus submit/review.
- Amankan Page dan mutation Livewire.
- Perbaiki query Monitoring Poin dan branch snapshot.
- Pindahkan mutation submission/review ke Action.

**Selesai jika:** akses lintas branch dan task injection ditolak server-side.

### Phase 4 — Scheduler dan status

- Hentikan pembuatan record/item kosong.
- Pisahkan auto-reject review SLA dari evaluasi missing.
- Implementasikan status derivation bersama.
- Tambahkan structured logging dan idempotency test.

**Selesai jika:** scheduler berulang tidak membuat data ganda atau submission palsu.

### Phase 5 — Kalender, monitoring, scoring, dan report

- Migrasikan kalender user/admin ke resolver bersama.
- Samakan definisi submitted/missing/approved.
- Perbaiki scoring dan batas tanggal masa depan.
- Perbaiki Excel/PDF berdasarkan branch assignment.
- Sertakan missing participant pada report kepatuhan.

**Selesai jika:** dataset ekuivalen menghasilkan status yang sama pada seluruh consumer.

### Phase 6 — Data master dan cleanup

- Audit serta nonaktifkan task duplikat.
- Perbaiki seeder agar idempotent dan tidak menimpa konfigurasi operasional.
- Jalankan cleanup dry-run.
- Review sample dan jumlah bersama pemilik bisnis.
- Jalankan mutation hanya setelah persetujuan eksplisit.
- Recompute score terdampak setelah validasi.

**Selesai jika:** tidak ada assignment ganda dan seluruh mutation mempunyai bukti serta rollback artifact.

### Phase 7 — UI consistency dan hardening

- Terapkan state, copy, loading, empty, dan error yang disepakati.
- Review responsive, keyboard, modal, dan accessibility.
- Ukur query count dan payload Livewire pada halaman utama.
- Jalankan full module regression.

**Selesai jika:** UI konsisten tanpa mengubah kontrak bisnis atau membuka akses baru.

## 23. Strategi migration dan rollout

1. Deploy schema/config partisipasi dalam keadaan tidak mengubah perilaku existing.
2. Isi konfigurasi branch peserta berdasarkan daftar yang disetujui.
3. Deploy assignment resolver dalam mode observasi/compare bila praktis.
4. Perbaiki authorization dan branch scope sebelum cleanup.
5. Hentikan synthetic rejection sebelum membersihkan histori.
6. Migrasikan kalender/report/scoring.
7. Jalankan audit data dan cleanup dry-run.
8. Mutation cleanup dilakukan pada maintenance window bila volume/risk memerlukan.
9. Recompute score terbatas pada periode terdampak.
10. Monitor log, jumlah record, score delta, dan laporan user setelah rollout.

Perubahan authorization yang menutup kebocoran lintas branch dapat dirilis lebih awal sebagai patch terpisah setelah test lulus.

## 24. Rollback

- Migration schema memiliki `down()` yang aman selama belum ada data production yang harus dipertahankan; setelah ada data, rollback aplikasi tidak boleh menjatuhkan tabel tanpa backup.
- Feature flag/config dapat mempertahankan resolver lama selama fase compare, tetapi tidak boleh mempertahankan kebocoran branch.
- Simpan export ID dan nilai sebelum cleanup agar record dapat direkonstruksi.
- Score sebelum recompute disimpan atau dapat dihitung ulang dari snapshot.
- Task duplikat dinonaktifkan, bukan dihapus, sehingga dapat diaktifkan kembali bila mapping canonical salah.
- Rollback tidak boleh menghidupkan kembali scheduler yang membuat submission palsu tanpa persetujuan.

## 25. Pengujian

### 25.1 Authorization dan branch scope

- Guest diarahkan ke login.
- Role selain `STORE_STAFF` tidak dapat submit walau mengetahui URL.
- `STORE_STAFF` cabang nonpeserta tidak dapat submit.
- User tidak dapat submit task branch lain melalui Livewire.
- Supervisor hanya melihat dan mereview `accessibleBranchIds()`.
- Supervisor tanpa permission melihat 403/empty sesuai konteks, bukan seluruh data.
- Super Admin dapat melihat seluruh branch.
- Export menolak `branch_id` di luar akses.

### 25.2 Assignment dan deadline

- Cabang peserta dengan task khusus menggunakan task khusus.
- Cabang peserta tanpa task khusus menggunakan global sesuai konfigurasi.
- Cabang nonpeserta mendapat assignment kosong.
- Tanggal sebelum start/after end menjadi not-applicable.
- Hari ini tidak missing sebelum deadline.
- Scheduler berulang idempotent.
- Missing tidak membuat `briefing_record` atau `briefing_item`.

### 25.3 Submission dan review

- Photo/text validation mengikuti submission type.
- Approved tidak dapat diedit.
- Reject mewajibkan alasan.
- Reviewer lintas branch ditolak.
- Review SLA hanya menolak submission pending yang melewati batas.
- Rejection manual dan sistem dapat dibedakan.

### 25.4 Kalender, scoring, dan report

- Status sama pada kalender user, admin, monitoring, dan report.
- Bulan berjalan tidak menghitung masa depan.
- Branch nonpeserta tidak mendapat score.
- Effective start per branch dihormati.
- Export memakai task branch yang tepat.
- Missing participant tetap muncul pada report kepatuhan.
- PDF dan Excel menggunakan scope serta angka yang sama.
- User pindah branch tidak mengubah histori branch snapshot.

### 25.5 Cleanup

- Dry-run tidak melakukan mutation.
- Submission valid tidak pernah menjadi kandidat aman.
- Parent record dengan item valid tidak dihapus.
- Command dapat dijalankan ulang tanpa mutation tambahan yang tidak diharapkan.
- Ringkasan sebelum/sesudah akurat.

## 26. Quality gates

Setiap perubahan implementasi wajib menjalankan yang relevan:

1. dokumentasi Laravel/Filament/Livewire sesuai versi melalui Laravel Boost;
2. `vendor/bin/pint --dirty --format agent` untuk perubahan PHP;
3. Pest test terfokus;
4. seluruh test modul Briefing;
5. test permission dan branch scope;
6. `git diff --check`;
7. route dan Filament discovery check;
8. query/payload comparison untuk halaman besar;
9. build frontend jika asset/source frontend berubah;
10. review visual desktop/mobile dan keyboard;
11. deployment dan rollback note untuk migration, permission, scheduler, dan cleanup.

## 27. Acceptance criteria

- [ ] Hanya `STORE_STAFF` aktif pada branch peserta yang dapat submit.
- [ ] Cabang nonpeserta tidak mendapat task, missing, rejection, atau score baru.
- [ ] Task key yang tidak ter-assign ditolak server-side.
- [ ] Monitoring dan action review selalu dibatasi `accessibleBranchIds()` kecuali `canAccessAllBranches()`.
- [ ] Permission Briefing tidak bergantung pada permission Sales Report.
- [ ] Histori menggunakan `briefing_records.branch_id`.
- [ ] Scheduler tidak membuat record/item ketika user tidak mengisi.
- [ ] Missing dan rejected memiliki arti berbeda pada domain dan UI.
- [ ] Kalender user tidak menandai missing sebelum deadline.
- [ ] Kalender admin hanya menghitung branch peserta.
- [ ] Scoring tidak menghitung branch nonpeserta atau tanggal masa depan.
- [ ] Report memakai task branch dan menampilkan missing sesuai tujuan report.
- [ ] Export PDF/Excel menghormati branch scope.
- [ ] Score Page dan PDF memiliki permission konsisten.
- [ ] Task duplikat tidak menghasilkan assignment aktif ganda.
- [ ] Seeder tidak menimpa deadline seluruh task secara massal.
- [ ] Cleanup mempunyai dry-run, klasifikasi, artifact rollback, dan persetujuan eksplisit.
- [ ] Semua test modul, permission, branch scope, scoring, dan report lulus.
- [ ] UI mempunyai loading, empty, error, not-applicable, missing, pending, approved, dan rejected state yang jelas.
- [ ] Tidak ada perubahan dependency atau deployment tanpa persetujuan.

## 28. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Daftar branch peserta salah | Konfirmasi bisnis, activation preview, dan effective date |
| Perubahan unit kewajiban mengubah score | Putuskan sebelum implementasi, characterization test, compare report |
| Cleanup menghapus submission valid | Kriteria konservatif, dry-run, sample review, backup ID/value |
| Task canonical salah | Nonaktifkan, jangan hapus; mapping historis dipertahankan |
| Cache assignment stale | Cache key mencakup branch/period dan invalidation saat task/settings berubah |
| User pindah branch mengubah histori | Gunakan branch snapshot pada record |
| Permission sync memperluas akses | Test matrix role; perubahan permission eksplisit dan terbatas |
| Report lama berubah tanpa penjelasan | Before/after sample dan release note definisi status |
| Scheduler overlap | `withoutOverlapping`, idempotency, dan unique invariant |
| Refactor besar sulit direview | Satu concern per PR mengikuti phase |

## 29. Definition of Done

Remediasi dianggap selesai ketika:

- keputusan bisnis pada Bagian 18 sudah terdokumentasi;
- seluruh halaman dan proses memakai assignment/status source yang sama;
- branch isolation terbukti melalui test server-side;
- scheduler tidak menghasilkan submission palsu;
- kalender, monitoring, score, PDF, dan Excel konsisten;
- task duplikat sudah dinonaktifkan dengan histori terjaga;
- cleanup yang disetujui selesai dan dapat diaudit;
- test, formatter, discovery, build, serta review visual lulus;
- deployment dan rollback telah didokumentasikan;
- monitoring pascarilis tidak menunjukkan rejection atau akses lintas branch yang tidak sah.

## 30. Prompt penggunaan

### Audit phase

> Audit Phase X dari `docs/products/daily-briefing/remediation/prd.md`. Jangan mengubah kode atau data. Cocokkan repository dan database aktual, sajikan gap dengan bukti file/query, risiko, test yang kurang, keputusan bisnis yang diperlukan, dan urutan implementasi.

### Implementasi phase

> Implementasikan Phase X dari `docs/products/daily-briefing/remediation/prd.md`. Ikuti `docs/standards/code-remediation.md` dan `docs/standards/ui-consistency.md`. Pertahankan histori, route penting, dan data valid. Terapkan authorization serta branch scope server-side, tambahkan test, jalankan quality gates, dan jangan menjalankan cleanup/deployment production tanpa instruksi eksplisit.

### Cleanup

> Jalankan audit/dry-run cleanup Daily Briefing sesuai Bagian 17. Jangan melakukan mutation. Laporkan jumlah kandidat per branch, role, task, period, alasan klasifikasi, sample ID, risiko false positive, serta langkah rollback sebelum meminta persetujuan eksekusi.
