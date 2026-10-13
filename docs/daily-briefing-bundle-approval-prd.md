# PRD — Bundle Approval dan Penilaian Kepatuhan Daily Briefing

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 13 Oktober 2026 |
| Status | Rancangan siap review; belum mengotorisasi implementasi, migration production, cleanup, recompute histori, atau deployment |
| Modul | Daily Briefing Employee App dan Human Resources |
| Perubahan utama | Approval dari per poin menjadi satu keputusan per bundle; penilaian berdasarkan kewajiban aktif |
| Stack | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Tailwind CSS 4, Pest 4 |
| Acuan domain | `docs/daily-briefing-remediation-prd.md` |
| Acuan scoring/UI | `docs/daily-briefing-scoring-ui-prd.md` |
| Acuan engineering | `docs/code-remediation-prd.md`, `docs/ui-consistency-prd.md` |

Dokumen ini memperinci perubahan workflow approval dan penilaian Daily Briefing. Aturan partisipasi cabang, role `STORE_STAFF`, assignment task, branch isolation, cleanup synthetic rejection, dan histori tetap mengikuti PRD remediasi. Jika terdapat konflik, requirement yang lebih aman terhadap histori, otorisasi, dan data valid diprioritaskan serta dicatat sebagai keputusan implementasi.

## 2. Ringkasan keputusan produk

1. Input tetap dilakukan per poin/tugas oleh `STORE_STAFF`.
2. Poin pada kewajiban yang sama dikelompokkan menjadi satu bundle canonical.
3. Supervisor melakukan satu kali `approve` atau `reject` terhadap seluruh bundle.
4. Aksi approval/rejection hanya tersedia ketika seluruh poin wajib dalam bundle telah disubmit lengkap.
5. Rejection dapat menandai poin bermasalah agar perbaikannya terarah, tetapi keputusan utama tetap berada pada bundle.
6. Record yang tidak pernah disubmit tidak dibuat sebagai submission atau rejection palsu; status `missing` dihitung dari assignment.
7. Nilai kepatuhan dihitung dari jumlah bundle approved dibanding jumlah bundle yang diwajibkan pada hari/minggu/bulan aktif.
8. Data lama dikelompokkan otomatis bila deterministik; data ambigu masuk `manual_review` dan tidak ditebak.
9. Bukti, catatan, pengirim, waktu submit, reviewer lama, dan nilai lama harus dipertahankan atau disnapshot.

## 3. Masalah yang diselesaikan

- Supervisor harus memproses terlalu banyak baris karena approval berada pada setiap poin.
- Satu kewajiban briefing dapat mempunyai status campuran yang sulit dipahami.
- Monitoring Poin tidak menyajikan satu unit kerja yang jelas bagi supervisor.
- Record kosong dapat terlihat sebagai rejected meskipun tidak pernah disubmit.
- Rumus nilai sulit dijelaskan karena unit submission, approval, dan score berbeda.
- Beberapa `STORE_STAFF` pada cabang yang sama berpotensi menghasilkan submission ganda.
- Data lama memerlukan transisi tanpa kehilangan histori atau menghapus submission valid.

## 4. Tujuan

1. Membuat proses review menjadi satu keputusan per kewajiban briefing.
2. Menampilkan seluruh poin dan bukti dalam satu modal review.
3. Menjamin tombol approval hanya tersedia setelah bundle lengkap.
4. Menetapkan satu unit data yang sama untuk monitoring, approval, scoring, kalender, dan export.
5. Membuat rumus nilai mudah dihitung, dijelaskan, dan direproduksi.
6. Mendukung kontribusi beberapa `STORE_STAFF` tanpa menciptakan bundle ganda.
7. Mengelompokkan histori secara aman, idempotent, dan dapat di-rollback.

## 5. Non-tujuan

- Menghapus bukti atau histori submission valid.
- Mengaktifkan seluruh branch secara otomatis.
- Memberi supervisor akses lintas branch di luar `accessibleBranchIds()`.
- Menjadikan supervisor sebagai peserta hanya karena mempunyai akses review.
- Mengubah bobot tanpa konfigurasi dan keputusan bisnis yang disetujui.
- Menjalankan cleanup, migration data, atau recompute production hanya karena PRD ini dibuat.
- Menggabungkan seluruh bulan menjadi satu approval bulanan untuk tugas harian.

## 6. Istilah domain

| Istilah | Definisi |
| --- | --- |
| Bundle | Unit kewajiban dan approval untuk satu branch, tanggal kewajiban, dan periode |
| Bundle item | Poin/tugas beserta bukti yang menjadi detail bundle |
| Required item | Poin yang dihasilkan assignment resolver untuk bundle tersebut |
| Complete bundle | Semua required item mempunyai submission valid sesuai submission type |
| Canonical bundle | Satu-satunya bundle aktif untuk kombinasi business key yang sama |
| Contributor | `STORE_STAFF` yang mengisi satu atau lebih item dalam bundle branch-nya |
| Missing | Kewajiban telah melewati deadline tetapi tidak menghasilkan bundle lengkap |
| Manual review | Data migrasi yang tidak dapat dikonversi otomatis tanpa risiko salah |

## 7. Unit bundle

### 7.1 Business key

Bundle canonical ditentukan oleh:

```text
branch_id + period + period_start
```

`period` menggunakan enum existing, misalnya `daily`, `weekly`, dan `monthly`. `period_start` harus dinormalisasi oleh domain service dan tidak boleh memakai tanggal submit mentah:

- harian: tanggal kewajiban tersebut;
- mingguan: awal minggu bisnis yang disepakati;
- bulanan: tanggal pertama bulan terkait.

Database wajib mempunyai unique constraint yang mencegah dua bundle canonical untuk business key yang sama. Constraint final harus mempertimbangkan mekanisme archive/legacy bila bundle lama tidak langsung dipindahkan.

Implementasi existing masih mempunyai unique key per `user_id + record_date + period`, sementara helper `canonicalForBranch()` memilih record tertua apabila terdapat beberapa record branch yang sama. Pemilihan record tertua bukan penyelesaian konflik yang cukup untuk target bundle. Evolusi schema wajib menetapkan bahwa:

- bundle dimiliki branch, bukan user individual;
- `user_id` parent lama dipertahankan sebagai legacy owner/`created_by` atau dibuat nullable melalui migration additive;
- contributor aktual dicatat pada item;
- duplicate/collision diselesaikan sebelum unique constraint branch diterapkan;
- unique constraint target adalah `branch_id + period + period_start`.

### 7.2 Snapshot histori

Bundle menyimpan snapshot minimal:

- `branch_id`;
- `obligation_date`;
- `period`;
- daftar task/key wajib atau versi assignment yang digunakan;
- deadline yang berlaku;
- versi metode scoring;
- waktu bundle menjadi lengkap.

Perubahan task, branch user, kalender, atau pengaturan setelah submission tidak boleh mengubah isi historis secara diam-diam.

Daftar kewajiban direkomendasikan disimpan sebagai requirement snapshot terpisah dari item submission. Snapshot memuat minimal task key, label, tipe submission, sifat wajib/opsional, dan keikutsertaan dalam score. Requirement snapshot tidak boleh dibuat sebagai submission kosong; pemisahan ini mencegah kembalinya synthetic submission.

### 7.3 Multi-contributor

- Beberapa `STORE_STAFF` aktif pada branch yang sama boleh mengisi item berbeda dalam bundle yang sama.
- Setiap item menyimpan `submitted_by` dan `submitted_at` sendiri.
- User hanya boleh mengubah item sesuai aturan edit/reopen dan branch scope.
- Simpan dilakukan dengan transaction serta lock/unique constraint untuk mencegah bundle ganda akibat submit bersamaan.
- Requirement ini tidak menentukan PIC tunggal; jika bisnis menginginkan PIC, dibuat keputusan terpisah.

## 8. Status dan lifecycle

### 8.1 Status bundle

| Status | Makna | Persisted atau derived |
| --- | --- | --- |
| `draft` | Bundle sudah mempunyai sebagian submission tetapi belum lengkap | Persisted |
| `supervisor_review` | Seluruh required item lengkap dan siap direview | Persisted |
| `approved` | Bundle disetujui supervisor | Persisted |
| `rejected` | Bundle lengkap ditolak supervisor | Persisted |
| `reopened` | Bundle ditolak/approved dibuka kembali dengan audit | Persisted |
| `not_due` | Assignment belum mencapai deadline | Derived |
| `missing` | Deadline lewat dan tidak ada bundle lengkap | Derived |
| `not_applicable` | Branch/user/task tidak ter-assign | Derived |
| `manual_review` | Hanya untuk proses migrasi/rekonsiliasi data lama | Migration state, bukan status operasional normal |

`missing`, `not_due`, dan `not_applicable` tidak boleh menghasilkan item atau bundle palsu hanya untuk menyimpan status.

### 8.2 Transisi normal

```text
Tidak ada submission
→ draft saat item pertama disimpan
→ supervisor_review ketika semua required item lengkap
→ approved
atau
→ rejected
→ reopened bila diberi izin
→ supervisor_review setelah perbaikan lengkap
```

### 8.3 Definisi kelengkapan

Kelengkapan ditentukan server-side berdasarkan tipe submission:

- `camera_only` dan `photo`: mempunyai bukti foto valid;
- `photo_and_text`: mempunyai foto dan catatan valid;
- `text_only`: mempunyai catatan valid;
- `checkbox`: sudah dicentang, mempunyai `submitted_at`, dan dianggap selesai.

Semua submission valid wajib mempunyai `is_completed = true`, `completed_at`, `submitted_by`, dan `submitted_at`. Tipe checkbox tidak boleh menjadi pengecualian tanggal selesai.

## 9. Approval dan rejection

### 9.1 Syarat aksi

Tombol `Setujui Bundle` dan `Tolak Bundle` hanya muncul dan hanya dapat dieksekusi server-side apabila:

```text
status = supervisor_review
AND seluruh required item lengkap
AND user memiliki permission review briefing items
AND branch bundle termasuk accessibleBranchIds()
```

Visibilitas tombol bukan pengganti Policy atau pemeriksaan Action.

### 9.2 Approval

Satu aksi approval mengisi minimal:

- `status = approved`;
- `reviewed_by`;
- `reviewed_at`;
- catatan reviewer opsional;
- audit log transisi status.

Approval harus idempotent. Double-click atau request ulang tidak boleh membuat audit, score, atau notification ganda.

### 9.3 Rejection

Rejection memerlukan alasan bundle. Supervisor dapat memilih satu atau lebih item bermasalah dan memberi catatan per item. Penanda item bermasalah tidak mengubah fakta bahwa keputusan approval berada pada bundle.

Default aman:

- bundle rejected tidak langsung dihitung approved;
- perbaikan setelah deadline memerlukan aksi `reopen` berizin;
- reopen menyimpan actor, waktu, alasan, dan status sebelumnya;
- hanya item yang ditandai perlu diperbaiki, tetapi kelengkapan seluruh bundle divalidasi ulang sebelum review berikutnya.

### 9.4 Perubahan setelah approval

Bundle approved menjadi read-only. Perubahan memerlukan reopen eksplisit. Reopen membatalkan eligibility score approved sampai bundle disetujui kembali dan memicu recompute terarah untuk branch/periode terdampak.

## 10. Model data target

Nama final wajib mengikuti konvensi repository setelah audit schema. Baseline yang direkomendasikan adalah menggunakan `briefing_records` sebagai bundle bila struktur existing kompatibel, bukan membuat tabel paralel tanpa kebutuhan.

### 10.1 Kolom bundle

Kolom minimum yang perlu tersedia pada parent bundle:

| Kolom | Aturan |
| --- | --- |
| `branch_id` | Wajib; snapshot branch |
| `period_start` | Wajib; hasil normalisasi periode |
| `period_end` | Opsional; berguna untuk audit kalender periode |
| `period` | Wajib; enum/cast existing |
| `status` | Status bundle |
| `completed_at` | Saat bundle pertama kali lengkap; nullable sebelum lengkap |
| `reviewed_by` | Nullable FK user |
| `reviewed_at` | Nullable datetime |
| `review_notes` | Nullable text |
| `reopened_by` / `reopened_at` | Nullable; atau audit transition table |
| `assignment_snapshot` | JSON/version reference bila diperlukan untuk reproducibility |
| `scoring_method_version` | Versi metode yang berlaku |
| timestamps | Wajib |

Tambahkan composite unique index untuk business key serta index query monitoring, misalnya status + branch + obligation date. Indeks final harus didasarkan pada query aktual dan diperiksa terhadap indeks existing.

Baseline indeks target setelah backfill dan resolusi duplicate:

- unique bundle: `branch_id, period, period_start`;
- monitoring: `branch_id, status/review_status, period_start`;
- antrean review: `status/review_status, submitted_at`;
- item canonical: `briefing_record_id, task_key`, setelah duplicate item direkonsiliasi;
- foreign key/index contributor dan reviewer.

### 10.2 Kolom item

Item tetap menyimpan:

- `task_key` atau FK/snapshot task;
- `submitted_by`;
- `submitted_at`;
- `is_completed` dan `completed_at`;
- foto dan catatan;
- penanda `requires_correction` serta catatan koreksi bila dipakai;
- metadata migrasi sumber untuk audit.

Kolom review item lama tidak langsung dihapus. Pertahankan selama compatibility phase sampai seluruh consumer, export, dan histori berhasil berpindah.

### 10.3 Audit status

Jika pola audit transition belum tersedia, sediakan tabel/struktur audit yang mencatat:

- bundle;
- status asal dan tujuan;
- actor;
- timestamp;
- alasan;
- metadata sumber (`user_action`, `migration`, atau `system_reconciliation`).

## 11. Metode penilaian

### 11.1 Prinsip

- Unit score adalah bundle yang diwajibkan, bukan jumlah klik atau jumlah item database.
- Denominator berasal dari assignment dan kalender aktif, bukan jumlah record yang berhasil dibuat.
- Untuk bulan berjalan, denominator hanya memasukkan kewajiban yang sudah jatuh tempo; hari masa depan tidak boleh menurunkan nilai.
- Hanya bundle `approved` yang memenuhi numerator penuh.
- Bundle `draft`, `supervisor_review`, `rejected`, dan `missing` belum menghasilkan nilai approved.
- Branch nonpeserta dan hari nonaktif tidak masuk denominator.

### 11.2 Nilai per periode

```text
Nilai Harian =
jumlah bundle harian approved
÷ jumlah hari aktif yang diwajibkan
× 100

Nilai Mingguan =
jumlah bundle mingguan approved
÷ jumlah minggu aktif yang diwajibkan
× 100

Nilai Bulanan =
jumlah bundle bulanan approved
÷ jumlah kewajiban bulanan aktif
× 100
```

Pembagi nol menghasilkan `not_applicable`, bukan nilai `0`.

Kewajiban aktif merupakan irisan partisipasi branch, kalender operasional, assignment task, periode efektif, dan waktu jatuh tempo. Hari libur/tutup, aktivasi branch di tengah bulan, serta perubahan kalender harus mengikuti snapshot/aturan efektif yang dapat direproduksi.

### 11.3 Nilai akhir berbobot

```text
Nilai Akhir = Σ(nilai periode × bobot periode aktif)
```

Bobot hanya dibagi pada periode yang memang aktif/diwajibkan sesuai keputusan di PRD scoring. Total bobot efektif harus 100%. Override per branch mengikuti konfigurasi existing dan tidak mengubah histori sebelum recompute eksplisit.

### 11.4 Ketepatan waktu

Baseline versi pertama menilai kepatuhan approved. Jika bisnis ingin membedakan submit terlambat dan tepat waktu, tambahkan metrik terpisah; jangan mencampurkan penalti baru ke formula tanpa keputusan dan versi metode baru.

### 11.5 Waktu recompute

Setelah approve, reject, reopen, atau approve ulang, score branch/periode terdampak harus diperbarui melalui Action/Job idempotent setelah transaction commit. Scheduler tetap dapat melakukan rekonsiliasi, tetapi bukan satu-satunya cara score berubah.

## 12. UI Employee App

- Tampilkan satu workspace bundle sesuai tanggal dan periode.
- Tampilkan progres `n dari total poin selesai`.
- Setiap item menampilkan status, bukti, catatan, pengirim, dan waktu submit.
- Setelah lengkap, tampilkan status `Menunggu Persetujuan Supervisor`.
- Bundle approved menjadi read-only.
- Bundle rejected menampilkan alasan utama dan item yang perlu diperbaiki.
- User hanya melihat dan mengubah bundle branch yang menjadi assignment aktifnya.
- UI tidak boleh menawarkan submit jika branch bukan peserta atau task tidak assigned.

## 13. UI Monitoring Poin

Monitoring menggunakan satu baris/kartu per bundle, bukan per item.

Kolom minimum:

- tanggal/kewajiban;
- branch;
- periode;
- progres poin;
- contributor;
- waktu lengkap;
- status;
- reviewer dan waktu review;
- aksi detail/review.

Modal bundle memuat seluruh item, bukti, catatan, pengirim, dan waktu submit. Modal mengikuti standar aksesibilitas dan responsive pada `docs/ui-consistency-prd.md`.

Filter minimum:

- branch sesuai scope;
- periode;
- tanggal/bulan;
- status;
- kelengkapan;
- reviewer.

Default antrean supervisor menampilkan `supervisor_review`. Bundle draft dapat dilihat sebagai informasi tetapi tidak mempunyai tombol approve/reject.

## 14. Authorization dan branch isolation

- Hanya `STORE_STAFF` aktif pada branch peserta yang dapat mengisi.
- Contributor tidak memperoleh hak review dari role submit.
- Supervisor memerlukan permission review dan branch berada dalam `accessibleBranchIds()`.
- `canAccessAllBranches()` menjadi satu-satunya bypass scope standar.
- Query list, count, filter options, modal detail, export, dan mutation memakai scope yang sama.
- Policy/Action selalu memeriksa bundle parent dan branch snapshot; jangan mengandalkan `users.branch_id` terkini untuk histori.

## 15. Migrasi data lama

### 15.1 Urutan wajib

1. Hentikan scheduler yang membuat synthetic rejection.
2. Perbaiki branch scope dan authorization.
3. Jalankan audit serta cleanup dry-run sesuai PRD remediasi.
4. Deploy schema baru tanpa mengubah perilaku aktif.
5. Jalankan command grouping dalam mode dry-run.
6. Review jumlah dan sample per branch/periode/status.
7. Simpan rollback artifact dan snapshot score lama.
8. Jalankan grouping mutation pada scope yang disetujui.
9. Jalankan compare nilai lama dan baru.
10. Aktifkan UI bundle serta recompute terbatas setelah validasi.

### 15.2 Grouping key

Data lama dikelompokkan berdasarkan:

```text
briefing_records.branch_id
+ normalized period_start
+ period
```

`briefing_records.branch_id` adalah sumber utama. `users.branch_id` hanya boleh menjadi fallback legacy yang dilaporkan dan tidak digunakan untuk menebak jika user telah berpindah branch.

### 15.3 Klasifikasi otomatis

| Kondisi data lama | Hasil migrasi |
| --- | --- |
| Semua required item lengkap dan approved | Bundle `approved` |
| Semua required item lengkap dan pending | Bundle `supervisor_review` |
| Semua required item lengkap dan terdapat rejection manusia yang konsisten | Bundle `rejected` |
| Sebagian submission nyata sudah diisi | Bundle `draft` |
| Tidak ada submission nyata setelah deadline | Derived `missing`; jangan membuat bundle palsu |
| Semua item synthetic rejection dan memenuhi classifier aman | Kandidat cleanup; bukan bundle |
| Status/reviewer/branch/tanggal/task ambigu | `manual_review`; tidak dimutasi otomatis |

### 15.4 Reviewer historis

- Reviewer bundle dapat diisi otomatis jika seluruh keputusan item konsisten dan dapat dibuktikan berasal dari reviewer yang sama.
- Jika reviewer berbeda, timestamp berjauhan secara tidak wajar, atau status campuran, masukkan ke `manual_review`.
- Jangan mengarang reviewer atau waktu approval.
- Data item lama tetap tersedia selama compatibility phase.

### 15.5 Command migration

Sediakan command terpisah dengan default non-mutating, misalnya:

```bash
php artisan briefing:group-legacy-bundles --dry-run
php artisan briefing:group-legacy-bundles --execute --snapshot=/path/approved-snapshot.json
```

Requirement command:

- `--execute` wajib eksplisit;
- dapat dibatasi branch, tahun, bulan, dan chunk;
- memakai `lazyById()`/`chunkById()` yang tidak melewatkan record saat update;
- transaction per unit aman, bukan satu transaction raksasa;
- idempotent dan aman dijalankan ulang;
- resumable dengan checkpoint untuk volume production;
- tidak membuat bundle ganda;
- berhenti serta melaporkan konflik, bukan menebak;
- menghasilkan ringkasan sebelum/sesudah dan artifact rollback;
- tidak recompute score secara tersembunyi.

### 15.6 Rekonsiliasi duplicate

Jika ditemukan beberapa parent untuk business key yang sama:

- pilih canonical hanya jika aturannya deterministik;
- pindahkan relasi item tanpa kehilangan ID sumber;
- deduplicate task hanya jika bukti menunjukkan poin bisnis yang sama;
- status atau bukti konflik masuk manual review;
- jangan menghapus parent lama sebelum seluruh relasi dan rollback artifact tervalidasi.
- simpan mapping source ID ke bundle canonical pada artifact/tabel audit.

## 16. Cleanup synthetic rejection

Cleanup tetap mengikuti `docs/daily-briefing-remediation-prd.md` Bagian 17. Migrasi bundle tidak boleh mengubah synthetic rejection menjadi histori rejected yang terlihat sah.

Hanya `safe_cleanup_candidate` yang dapat dieksekusi setelah persetujuan eksplisit. `manual_review` dan submission valid tidak boleh dihapus. Parent lama hanya dapat dihapus jika tidak mempunyai item valid dan tidak dipakai relasi lain.

## 17. Arsitektur target

Komponen domain yang direkomendasikan:

- `BriefingAssignmentService`: menentukan peserta dan required task;
- `BriefingBundleResolver`: menemukan/membuat canonical bundle dengan aman;
- `BriefingBundleCompletenessService`: mengevaluasi kelengkapan;
- `SubmitBriefingItemAction`: mutation item dan sinkronisasi status bundle;
- `ReviewBriefingBundleAction`: approve/reject satu bundle;
- `ReopenBriefingBundleAction`: reopen terotorisasi dan teraudit;
- `BriefingScoreCalculator`: kalkulasi murni dari assignment serta bundle approved;
- command audit/grouping/recompute terpisah.

Page, Resource, dan Blade tidak menghitung kelengkapan atau nilai sendiri. Seluruh consumer memakai service yang sama.

## 18. Concurrency dan integritas data

- Gunakan transaction pada submit dan review.
- Gunakan unique constraint untuk canonical bundle.
- Gunakan row lock atau strategi concurrency existing ketika dua user mengisi bundle bersamaan.
- Listing wajib eager-load branch/reviewer/snapshot yang digunakan, memakai aggregate seperti `withCount()` untuk progres, memilih kolom yang dibutuhkan, dan menggunakan pagination; tidak boleh ada query database dari Blade/modal.
- Periksa kembali kelengkapan di dalam transaction sebelum mengubah status menjadi `supervisor_review`.
- Periksa status terbaru sebelum approval agar bundle yang sedang diperbaiki tidak dapat disetujui dari modal lama.
- Dispatch recompute/event setelah commit.
- Job recompute harus unique/idempotent untuk branch, bulan, dan versi metode.

## 19. Report dan export

- Export Monitoring menampilkan satu row per bundle dan dapat menyediakan sheet/detail item.
- Report kepatuhan tetap menampilkan branch/hari yang missing meskipun tidak ada row submission.
- PDF Employee App menampilkan nilai branch yang sama dengan halaman back office.
- Semua angka, filter, scope branch, dan status berasal dari query/calculator bersama.
- Histori sebelum tanggal aktivasi metode baru tetap dapat dijelaskan melalui `scoring_method_version`.

## 20. Observability

Catat metrik/log minimal:

- bundle dibuat dan menjadi lengkap;
- approve/reject/reopen beserta actor dan branch;
- percobaan akses lintas branch;
- conflict canonical bundle;
- migration bucket count dan sample ID;
- jumlah manual review;
- perbedaan score lama/baru;
- kegagalan recompute dan waktu proses;
- jumlah derived missing per branch/periode.

Log tidak boleh memuat credential atau URL private attachment permanen.

## 21. Phase implementasi

### Phase 1 — Characterization dan keputusan final

- Petakan schema, enum, page, resource, scheduler, command, score, export, dan test existing.
- Kunci definisi canonical date untuk weekly/monthly.
- Kunci aturan multi-contributor dan reopen.
- Tambahkan characterization test workflow existing yang harus dipertahankan.

### Phase 2 — Schema additive dan domain service

- Tambahkan kolom/index/audit secara additive.
- Buat migration DDL baru; lakukan backfill melalui command terpisah dan terapkan unique constraint branch hanya setelah duplicate terselesaikan.
- Implementasikan resolver, completeness service, dan policy tanpa mengaktifkan UI baru.
- Jangan menghapus kolom review item lama.

### Phase 3 — Submission bundle

- Arahkan Employee App ke canonical bundle.
- Pastikan semua tipe submission, termasuk checkbox, mengisi completion timestamp.
- Terapkan concurrency protection dan test multi-contributor.

### Phase 4 — Monitoring dan bundle review

- Ubah list menjadi satu record per bundle.
- Implementasikan modal detail, approve, reject, correction marker, dan reopen.
- Terapkan branch isolation pada query dan mutation.

### Phase 5 — Calculator dan score UI

- Implementasikan rumus kewajiban aktif.
- Sambungkan recompute setelah commit.
- Pertahankan metode lama melalui compatibility/read path selama compare.

### Phase 6 — Audit dan migrasi histori

- Jalankan grouping dry-run.
- Review sample dan false-positive risk.
- Simpan snapshot serta jalankan mutation hanya atas persetujuan eksplisit.
- Pisahkan manual review dari data otomatis.

### Phase 7 — Cutover dan cleanup compatibility

- Jalankan compare score dan cutover terkontrol.
- Monitor akses, status, missing, dan delta score.
- Hapus compatibility code/kolom lama hanya pada PR terpisah setelah bukti tidak ada consumer.

## 22. Pengujian minimum

### Assignment dan submission

- Hanya `STORE_STAFF` branch peserta dapat submit.
- Branch nonpeserta tidak mendapatkan bundle.
- Task global tidak mengaktifkan branch secara implisit.
- Dua contributor mengisi business key yang sama dan hanya satu bundle tercipta.
- Seluruh submission type menentukan kelengkapan dengan benar.

### Approval

- Draft tidak dapat di-approve atau di-reject.
- Bundle lengkap otomatis masuk `supervisor_review`.
- Approval/rejection hanya terjadi sekali dan idempotent.
- Supervisor hanya melihat serta memproses branch yang dapat diakses.
- Reopen menyimpan audit dan mencegah score approved lama tetap berlaku.

### Scoring

- Numerator hanya bundle approved.
- Denominator memakai kewajiban aktif, termasuk missing tanpa row.
- Hari nonaktif dan branch nonpeserta tidak dihitung.
- Weekly/monthly memakai canonical date yang konsisten.
- Bulan berjalan tidak memasukkan kewajiban masa depan ke denominator.
- Aktivasi branch di tengah bulan dan hari nonoperasional menghasilkan denominator yang benar.
- Pembagi nol menghasilkan `not_applicable`.
- UI, PDF, dan export menghasilkan angka yang sama.

### Migrasi

- Dry-run tidak melakukan mutation.
- Grouping idempotent dan tidak membuat bundle ganda.
- Command dapat dilanjutkan dari checkpoint setelah terhenti.
- Approved konsisten dikelompokkan otomatis.
- Status campuran masuk manual review.
- Synthetic rejection tidak menjadi bundle valid.
- Submission valid, attachment, contributor, dan timestamp tetap terjaga.
- Snapshot dapat dipakai untuk restore pada environment uji.

### UI

- Modal dapat digunakan pada viewport 360, 390, 768, 1024, dan 1440 px.
- Keyboard, fokus, Escape, loading, double-submit protection, dan error state bekerja.
- Tombol review hanya muncul pada status yang benar, tetapi server tetap menolak request ilegal.
- Query Monitoring dan modal tidak mengalami N+1 pada dataset representatif.

## 23. Quality gates

- Cari dokumentasi versi Laravel/Filament/Livewire melalui Laravel Boost sebelum perubahan kode.
- Buat migration baru; jangan mengubah migration yang pernah berjalan di production.
- Jalankan test terdampak dengan `php artisan test --compact`.
- Jalankan `vendor/bin/pint --dirty --format agent` untuk perubahan PHP.
- Audit route, Policy, Filament discovery, scheduler, export, dan navigation.
- Review query count dan index untuk Monitoring serta migration command.
- Jangan menjalankan mutation/recompute/deployment production dari phase implementasi tanpa instruksi eksplisit.

## 24. Rollout

1. Deploy schema additive.
2. Deploy service dan instrumentation dalam compatibility mode.
3. Hentikan synthetic rejection baru.
4. Aktifkan write path bundle untuk data baru.
5. Aktifkan monitoring bundle pada user internal terbatas bila memungkinkan.
6. Jalankan grouping dry-run histori.
7. Jalankan migration scope kecil yang disetujui dan validasi.
8. Jalankan compare score lama/baru.
9. Cutover UI, report, dan score.
10. Monitor sebelum cleanup compatibility.

## 25. Rollback

- Feature flag/config dapat mengembalikan read UI ke mode lama tanpa menghapus data bundle.
- Snapshot menyimpan ID, status, reviewer, timestamp, relasi item, dan score sebelum mutation.
- Migration data menyediakan restore command/prosedur yang diuji.
- Score lama dipertahankan sampai compare dan cutover disetujui.
- Rollback aplikasi tidak boleh menjatuhkan tabel/kolom yang sudah berisi histori production.
- Jangan mengaktifkan kembali scheduler synthetic rejection saat rollback.

## 26. Acceptance criteria

- [ ] Satu branch hanya mempunyai satu bundle canonical per tanggal kewajiban dan periode.
- [ ] Beberapa `STORE_STAFF` dapat berkontribusi tanpa bundle ganda.
- [ ] Semua poin wajib lengkap sebelum bundle masuk antrean supervisor.
- [ ] Supervisor melakukan satu approval/rejection per bundle.
- [ ] Rejection dapat menunjukkan item yang perlu diperbaiki.
- [ ] Draft atau missing tidak disimpan sebagai rejected palsu.
- [ ] Monitoring, modal, export, dan mutation menghormati branch access.
- [ ] Score memakai approved bundle dibagi kewajiban aktif.
- [ ] Nilai harian, mingguan, bulanan, dan nilai akhir dapat direproduksi.
- [ ] Data lama deterministik dapat dikelompokkan otomatis.
- [ ] Data ambigu masuk manual review dan tidak ditebak.
- [ ] Synthetic rejection tidak dimigrasikan sebagai submission valid.
- [ ] Dry-run, snapshot, rollback, dan compare tersedia sebelum mutation production.
- [ ] Test workflow kritis lulus.

## 27. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Bundle ganda akibat submit bersamaan | Composite unique constraint, transaction, lock, retry terkontrol |
| Parent existing masih user-scoped | Migration additive, contributor pada item, duplicate reconciliation sebelum unique branch |
| Approved lama salah dikelompokkan | Classifier konservatif, dry-run, sample, manual review |
| Reviewer histori tidak konsisten | Jangan menebak; bucket manual review |
| Nilai turun karena denominator berubah | Method version, compare report, snapshot, persetujuan sebelum recompute |
| Supervisor melihat branch lain | Policy dan query scope fail-closed pada seluruh consumer |
| Task berubah dan histori ikut berubah | Assignment/task snapshot pada bundle |
| Bundle lengkap diubah setelah approval | Read-only dan reopen beraudit |
| Migration besar mengunci database | Chunk/lazyById, transaction kecil, maintenance window |
| Compatibility field dihapus terlalu cepat | Reference audit dan PR cleanup terpisah |

## 28. Definition of Done

Perubahan dianggap selesai ketika workflow submit sampai score memakai bundle canonical, approval hanya satu kali, branch isolation terbukti melalui test, formula score konsisten di seluruh output, histori valid tetap utuh, data lama telah diklasifikasikan dan dimigrasikan sesuai persetujuan, manual review terselesaikan atau terdokumentasi, serta rollout/rollback production mempunyai bukti yang dapat diaudit.

## 29. Prompt implementasi ringkas

> Implementasikan phase yang ditentukan dari `docs/daily-briefing-bundle-approval-prd.md`. Ikuti `docs/daily-briefing-remediation-prd.md`, `docs/daily-briefing-scoring-ui-prd.md`, `docs/code-remediation-prd.md`, dan `docs/ui-consistency-prd.md`. Gunakan satu bundle canonical per branch, obligation date, dan period; pertahankan item sebagai bukti detail; lakukan approval satu kali hanya setelah lengkap; jaga role serta branch scope server-side; pertahankan histori; tambahkan test; dan jangan menjalankan cleanup, grouping mutation, recompute histori, atau deployment production tanpa instruksi eksplisit.
