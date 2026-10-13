# Prompt Implementasi End-to-End — Bundle Approval Daily Briefing

Salin seluruh prompt di bawah ini ke coding agent yang akan mengerjakan implementasi.

---

Anda bekerja pada repository Laravel Help Bloomery. Implementasikan perubahan Daily Briefing dari awal sampai akhir berdasarkan dokumen berikut sebagai sumber requirement:

1. `docs/daily-briefing-bundle-approval-prd.md` — sumber utama workflow bundle approval dan metode nilai.
2. `docs/daily-briefing-remediation-prd.md` — aturan partisipasi, assignment, role, branch isolation, lifecycle, cleanup, dan histori.
3. `docs/daily-briefing-scoring-ui-prd.md` — metode scoring, settings, UI Nilai Briefing, compare, dan recompute.
4. `docs/code-remediation-prd.md` — batas arsitektur dan kualitas kode.
5. `docs/ui-consistency-prd.md` — standar UI, modal, responsive, state, dan aksesibilitas.
6. `AGENTS.md` — instruksi repository yang wajib dipatuhi.

## Objektif akhir

Ubah Daily Briefing menjadi workflow berikut:

```text
Branch peserta mempunyai satu bundle canonical per periode kewajiban
→ STORE_STAFF mengisi poin-poin yang diwajibkan
→ beberapa STORE_STAFF pada branch yang sama boleh berkontribusi
→ semua poin lengkap
→ bundle masuk supervisor_review
→ supervisor melihat seluruh poin dalam satu modal
→ supervisor approve atau reject satu kali untuk seluruh bundle
→ jika reject, supervisor dapat menandai poin yang harus diperbaiki
→ jika diperlukan, supervisor melakukan reopen secara teraudit
→ score branch diperbarui dari approved bundle ÷ kewajiban aktif yang sudah jatuh tempo
```

Monitoring, kalender, score, PDF, Excel, scheduler, dan cleanup harus memakai definisi domain yang sama. Jangan membuat submission/rejection palsu untuk kewajiban yang tidak pernah diisi.

## Aturan kerja utama

- Mulai dengan audit repository dan database aktual. Jangan menganggap nama class, kolom, route, permission, atau implementasi dalam PRD sudah tersedia.
- Gunakan Laravel Boost `search-docs` sebelum setiap kelompok perubahan kode Laravel/Filament/Livewire. Gunakan `database-schema`, `database-query`, `route:list`, dan tool read-only lain yang relevan.
- Ikuti konvensi file sibling dan reuse komponen/service existing sebelum membuat yang baru.
- Jangan mengubah dependency atau membuat base folder baru tanpa persetujuan.
- Jangan mengubah migration lama yang pernah berjalan; buat migration additive baru.
- Jangan menghapus test existing.
- Jangan menimpa atau membuang perubahan user di working tree.
- Jangan menjalankan mutation data production, cleanup production, grouping histori production, recompute histori production, migration production, atau deployment tanpa instruksi eksplisit terpisah.
- Implementasi command mutation boleh dibuat dan diuji pada test database, tetapi eksekusi production harus berhenti pada dry-run/report sampai disetujui.
- Jangan commit atau push kecuali user meminta. Jika diminta commit, pisahkan commit/PR sesuai phase dan jangan memasukkan perubahan user yang tidak terkait.
- Setiap perubahan perilaku wajib mempunyai test programatik.
- Untuk perubahan PHP, jalankan `vendor/bin/pint --dirty --format agent` sebelum finalisasi.
- Gunakan `php artisan test --compact` dengan file/filter paling relevan, kemudian perluas cakupan saat integrasi.
- Gunakan `apply_patch` untuk edit manual file.
- Jangan menaruh query database di Blade atau menghitung aturan bisnis di Page/Resource.
- Jangan membagikan URL aplikasi sebelum memeriksanya dengan Laravel Boost `get-absolute-url`.

## Keputusan produk default

Gunakan keputusan berikut kecuali repository atau requirement utama membuktikan harus berbeda. Jika berbeda, dokumentasikan alasan dan dampaknya sebelum melanjutkan:

1. Aggregate root approval adalah parent bundle (`BriefingRecord` bila kompatibel); `BriefingItem` tetap bukti per poin.
2. Business key bundle adalah `branch_id + period + period_start`.
3. `period_start` dinormalisasi oleh satu domain service:
   - daily: tanggal kewajiban;
   - weekly: awal minggu bisnis yang dipakai aplikasi;
   - monthly: tanggal pertama bulan.
4. Bundle dimiliki branch, bukan user individual.
5. Contributor aktual disimpan per item melalui `submitted_by` dan `submitted_at`.
6. `user_id` parent lama tidak langsung dihapus; pertahankan sebagai legacy owner/created-by atau nullable selama compatibility phase.
7. Requirement task disnapshot terpisah dari submission item. Jangan membuat item kosong untuk mewakili kewajiban.
8. Bundle hanya masuk `supervisor_review` jika seluruh required item lengkap.
9. Semua submission type, termasuk checkbox, wajib mengisi `is_completed`, `completed_at`, `submitted_by`, dan `submitted_at` ketika valid.
10. Approval/rejection dilakukan satu kali di bundle. Item hanya menyimpan bukti dan penanda koreksi.
11. Approved menjadi read-only. Perubahan memerlukan reopen dengan actor, waktu, dan alasan.
12. `missing`, `not_due`, dan `not_applicable` adalah derived state, bukan submission palsu.
13. Bulan berjalan hanya menghitung kewajiban yang sudah jatuh tempo. Hari/periode masa depan tidak masuk denominator.
14. Zero denominator menghasilkan `not_applicable`, bukan `0`.
15. Data migrasi ambigu tidak ditebak; klasifikasikan sebagai `manual_review`.
16. Existing `canonicalForBranch()` yang memilih record tertua bukan solusi konflik final. Rekonsiliasi duplicate wajib dilakukan sebelum unique constraint branch diterapkan.

## Target status

Status operasional bundle:

- `draft`: ada sebagian submission tetapi belum lengkap;
- `supervisor_review`: semua required item lengkap;
- `approved`: disetujui supervisor;
- `rejected`: bundle lengkap ditolak supervisor;
- `reopened`: dibuka kembali secara teraudit.

State derived:

- `not_due`;
- `missing`;
- `not_applicable`.

State migrasi saja:

- `manual_review`.

Gunakan enum dan transition rule terpusat. Tolak transisi ilegal server-side walaupun tombol UI tersembunyi.

## Target formula penilaian

```text
Nilai Harian =
approved daily bundles
÷ expected daily occurrences yang aktif dan sudah jatuh tempo
× 100

Nilai Mingguan =
approved weekly bundles
÷ expected weekly occurrences yang aktif dan sudah jatuh tempo
× 100

Nilai Bulanan =
approved monthly bundles
÷ expected monthly occurrences yang aktif dan sudah jatuh tempo
× 100

Nilai Akhir = Σ(nilai periode × bobot efektif periode)
```

Denominator harus berasal dari irisan:

- partisipasi branch;
- kalender operasional;
- assignment task;
- effective period;
- deadline/due occurrence.

Record database yang berhasil dibuat tidak boleh menjadi denominator. Bundle `draft`, `supervisor_review`, `rejected`, dan derived `missing` tidak masuk numerator approved. Pertahankan versioning/snapshot formula agar histori dapat direproduksi.

## Cara mengeksekusi pekerjaan

Kerjakan secara berurutan. Jangan melompati safety gate. Setelah setiap phase:

1. jelaskan perubahan dan keputusan;
2. tampilkan file utama yang berubah;
3. jalankan test minimum yang relevan;
4. jalankan formatter bila ada PHP berubah;
5. periksa `git diff --check` dan `git status --short`;
6. laporkan risiko atau gap tersisa;
7. lanjutkan ke phase berikutnya jika tidak membutuhkan otorisasi baru.

Gunakan checklist dalam PRD sebagai kontrak. Jika menemukan konflik requirement, prioritaskan keamanan data, authorization fail-closed, histori, dan hasil yang dapat direproduksi.

# Phase 0 — Audit dan baseline

Lakukan audit read-only terhadap:

- model, migration, enum, cast, relationship, factory, dan seeder Daily Briefing;
- Employee App Daily Briefing, kalender, riwayat, dan nilai;
- Monitoring Poin, settings, bobot, score workspace, modal, export PDF/Excel;
- Action/Service/Policy/query scope;
- scheduler dan command Daily Briefing;
- test existing;
- permission serta branch access;
- schema dan indeks aktual;
- status serta volume data lokal/test yang aman diperiksa;
- seluruh reference ke `briefing_records`, `briefing_items`, dan `briefing_scores`.

Rekam baseline:

- business key existing;
- penggunaan `user_id`, `branch_id`, `record_date`, dan `period`;
- cara kelengkapan dihitung;
- lokasi approval per item;
- formula score aktual;
- scheduler yang membuat/mengubah record;
- route dan navigation;
- unique constraint/index existing;
- query rawan N+1;
- test yang belum melindungi requirement bundle.

Jalankan test characterization existing yang relevan sebelum perubahan. Jangan mengubah kode pada langkah audit kecuali membuat dokumentasi/audit memang diminta.

Output phase:

- gap matrix antara repository dan PRD;
- dependency map singkat;
- keputusan schema final;
- daftar migration/action/service/page/test yang akan berubah;
- risiko data dan urutan PR/commit.

# Phase 1 — Safety net dan characterization tests

Tambahkan atau perbarui Pest tests untuk mengunci perilaku aman sebelum refactor:

- hanya `STORE_STAFF` aktif pada branch peserta dapat submit;
- role supervisor bukan peserta kecuali juga memenuhi aturan peserta;
- branch nonpeserta tidak memperoleh task atau score;
- supervisor hanya melihat branch dalam `accessibleBranchIds()`;
- super-admin bypass hanya melalui mekanisme standar;
- histori memakai `briefing_records.branch_id`, bukan branch user terkini;
- submission valid lama tetap terbaca;
- synthetic rejection dapat dibedakan dari submission nyata;
- route dan sidebar penting tetap dapat diakses sesuai permission.

Gunakan factory/state existing. Jika factory belum memadai, tambah state yang reusable dan deskriptif.

# Phase 2 — Schema additive dan model domain

Buat migration baru melalui Artisan dengan `--no-interaction`. Jangan gabungkan DDL dan backfill data besar dalam migration.

Target parent bundle minimal:

- `branch_id`;
- `period`;
- `period_start`;
- opsional `period_end`;
- status/review status bundle;
- `submitted_at` atau waktu bundle lengkap;
- `reviewed_by`;
- `reviewed_at`;
- `review_notes`/`rejection_reason`;
- metadata reopen atau relasi audit transition;
- assignment/scoring version metadata.

Target item minimal:

- contributor (`submitted_by`);
- `submitted_at`;
- completion fields yang konsisten;
- correction marker/reason bila diperlukan;
- source/migration metadata bila diperlukan.

Sediakan requirement snapshot terpisah bila schema existing tidak dapat menyimpan daftar kewajiban tanpa membuat submission kosong. Snapshot minimal menyimpan task key, label, submission type, required/optional, include-in-score, serta identitas bundle.

Sediakan audit transition bila mekanisme audit existing belum mencukupi.

Indeks target setelah duplicate terselesaikan:

- unique `branch_id + period + period_start`;
- index monitoring `branch_id + status + period_start`;
- index antrean `status + submitted_at`;
- unique item `briefing_record_id + task_key` jika data telah aman;
- index/FK contributor dan reviewer.

Jangan memaksakan unique constraint yang akan gagal pada data legacy. Bila perlu, buat schema dan index non-unique dahulu lalu tambahkan constraint pada phase rekonsiliasi setelah bukti duplicate selesai.

Perbarui model dengan fillable/guarded, cast, relationship bertipe eksplisit, enum, scope, dan factory sesuai konvensi repository. Jangan memasukkan workflow kompleks ke model.

Test:

- migration dapat berjalan dan rollback pada database test;
- cast dan relationship benar;
- business key/period normalization benar;
- task snapshot tidak berubah ketika task master diedit.

# Phase 3 — Assignment, period, dan bundle resolver

Implementasikan atau rapikan service domain:

- `BriefingAssignmentService` sebagai sumber peserta dan required task;
- normalizer `period_start`/period window;
- `BriefingBundleResolver` untuk mencari atau membuat bundle canonical;
- `BriefingBundleCompletenessService` untuk mengevaluasi required snapshot terhadap submission nyata.

Resolver wajib:

- menggunakan branch snapshot;
- fail-closed untuk branch/user/task yang tidak valid;
- aman pada concurrent request;
- memakai transaction dan unique constraint/lock sesuai pola repository;
- tidak memilih arbitrary oldest record sebagai penyelesaian duplicate baru;
- tidak membuat item submission kosong.

Test:

- daily/weekly/monthly normalization;
- branch nonpeserta;
- task global hanya untuk branch peserta;
- task branch override/fallback sesuai PRD;
- task inactive tidak menjadi assignment baru;
- dua `STORE_STAFF` branch sama memperoleh bundle canonical yang sama;
- request concurrent tidak menghasilkan bundle ganda;
- optional item tidak menghalangi completion jika desain mengizinkannya.

# Phase 4 — Submission workflow Employee App

Refactor submission melalui Action tunggal, misalnya `SubmitBriefingItemAction` atau nama existing yang paling konsisten.

Action wajib:

- mengotorisasi role, permission, branch, participant, dan assignment;
- memperlakukan task key dari Livewire sebagai input tidak tepercaya;
- resolve canonical bundle;
- menyimpan contributor dan timestamp;
- memvalidasi bukti berdasarkan submission type;
- memastikan checkbox juga menjadi completed submission;
- memperbarui status bundle di dalam transaction;
- mengubah bundle ke `supervisor_review` hanya setelah seluruh required item lengkap;
- idempotent terhadap double-submit;
- tidak mengubah bundle approved tanpa reopen.

Perbarui Employee App:

- satu workspace bundle untuk tanggal/periode;
- progres `n/total`;
- status item dan bundle;
- loading serta double-submit protection;
- approved read-only;
- rejected menampilkan alasan dan poin koreksi;
- no-access/not-participant state yang jelas.

Test seluruh submission type, validasi, assignment ilegal, resubmit, approved lock, dan kontribusi multi-user.

# Phase 5 — Bundle review dan Monitoring Poin

Implementasikan Action terpisah:

- `ReviewBriefingBundleAction` untuk approve/reject;
- `ReopenBriefingBundleAction` untuk membuka kembali;
- audit transition untuk seluruh perubahan status.

Review action wajib:

- Policy/permission dan branch scope fail-closed;
- transaction serta `lockForUpdate()` atau atomic strategy yang setara;
- memeriksa ulang status dan completeness di server;
- menolak draft/incomplete;
- idempotent terhadap request berulang;
- mengisi reviewer, waktu, dan alasan;
- mendukung correction marker pada item saat reject;
- dispatch recompute setelah commit, bukan sebelum commit.

Ubah Monitoring Poin menjadi satu baris/kartu per bundle. Tampilkan:

- tanggal/periode;
- branch;
- progres poin;
- contributor;
- waktu lengkap;
- status;
- reviewer;
- aksi detail/review.

Modal menampilkan seluruh requirement dan submission, foto/catatan, contributor, waktu, serta correction state. Tombol approve/reject hanya tampil saat `supervisor_review`, tetapi server tetap menolak request ilegal.

Query list/modal wajib:

- memakai branch scope yang sama;
- eager-load relasi;
- memilih kolom yang dibutuhkan;
- memakai `withCount()`/aggregate untuk progres bila sesuai;
- memakai pagination;
- tidak melakukan query di Blade.

Test:

- branch isolation list, count, filter, modal, export, dan mutation;
- incomplete tidak dapat direview;
- approve/reject hanya sekali;
- correction marker;
- reopen dan resubmit;
- stale modal/request ditolak;
- tidak ada N+1 pada dataset representatif.

# Phase 6 — Calculator dan recompute terarah

Implementasikan satu calculator murni/reusable. Jangan menghitung score terpisah di Page, export, atau scheduler.

Calculator wajib:

- membangun expected occurrences dari assignment dan kalender;
- hanya memasukkan occurrence yang sudah jatuh tempo;
- menghormati tanggal efektif partisipasi branch;
- mengecualikan branch nonpeserta dan hari nonoperasional;
- menghitung approved bundle sebagai numerator;
- menghasilkan `not_applicable` saat denominator nol;
- menghitung daily/weekly/monthly dan weighted final score;
- menyimpan snapshot input/output dan method version yang cukup untuk reproduksi;
- mempunyai aturan rounding eksplisit dan konsisten.

Implementasikan `RecomputeBriefingScoreAction` dan Job bila proses perlu retry. Job harus idempotent/unique untuk branch, periode score, dan method version. Approve, reject, reopen, atau approve ulang harus menjadwalkan recompute hanya untuk branch/bulan terdampak setelah commit.

Scheduler dapat menjadi reconciliation fallback, tetapi tidak boleh menjadi satu-satunya cara score berubah dan tidak boleh membuat submission palsu.

Test:

- current month tidak menghitung tanggal masa depan;
- aktivasi branch di tengah bulan;
- hari libur/nonoperasional;
- rejected/draft/pending/missing;
- weekly dan monthly occurrence;
- zero denominator;
- weight override/default;
- rounding;
- approve/reject/reopen memperbarui scope yang benar;
- kegagalan satu branch tidak menghapus score valid branch lain.

# Phase 7 — Score UI, settings, report, dan export

Selaraskan halaman Nilai Briefing dengan pola UI Basket Size sesuai PRD scoring dan UI consistency:

- filter branch/periode/bulan;
- summary card yang membantu keputusan;
- ranking/list branch;
- nilai total dan breakdown harian/mingguan/bulanan;
- approved/expected serta status denominator;
- detail modal yang memakai data calculator/snapshot yang sama;
- responsive dan tanpa scroll horizontal halaman.

Selaraskan halaman settings dengan pola Sales Report Settings:

- kelompok pengaturan yang jelas;
- informasi konsekuensi dekat field;
- validasi total bobot;
- preview formula, bukan mutation production;
- perubahan settings tidak otomatis menimpa histori;
- override branch tidak ambigu.

Perbarui kalender, PDF, Excel, dan export Monitoring agar memakai bundle, assignment, status, scope, dan calculator yang sama.

Test kesamaan angka UI/PDF/Excel, filter/scope, empty state, unauthorized state, dan snapshot lama.

# Phase 8 — Hentikan synthetic rejection baru

Audit seluruh scheduler/command deadline.

Ubah perilaku agar:

- missed assignment dihitung sebagai derived `missing`;
- scheduler tidak membuat `briefing_item` kosong;
- auto-reject review hanya menyentuh submission nyata yang lengkap dan melewati review SLA;
- alasan serta transition audit tercatat;
- command idempotent dan menggunakan overlap/server protection sesuai deployment.

Pertahankan command audit cleanup existing. Jangan menjalankan cleanup mutation production.

Test bahwa deadline tanpa submission tidak membuat row, sedangkan review SLA submission nyata tetap bekerja.

# Phase 9 — Command audit dan grouping histori

Implementasikan command terpisah:

```bash
php artisan briefing:group-legacy-bundles --dry-run
php artisan briefing:group-legacy-bundles --execute --snapshot=/path/to/snapshot.json
```

Nama boleh disesuaikan dengan konvensi repository. Requirement wajib:

- default non-mutating;
- mutation hanya dengan `--execute` eksplisit;
- filter branch/year/month dan ukuran chunk;
- `chunkById()`/`lazyById()`;
- resumable/checkpoint;
- transaction per unit aman;
- idempotent;
- tidak membuat bundle/item ganda;
- menyimpan source ID mapping;
- melaporkan breakdown per branch/period/status;
- menghasilkan snapshot rollback;
- tidak menghapus source legacy selama verification phase;
- tidak menjalankan recompute tersembunyi.

Klasifikasi minimum:

- seluruh required item valid dan approved konsisten → `approved`;
- lengkap dan pending konsisten → `supervisor_review`;
- human rejection konsisten → `rejected`;
- sebagian submission nyata → `draft`;
- tidak ada submission nyata → derived `missing`, jangan membuat bundle;
- synthetic rejection aman → kandidat cleanup, jangan dijadikan bundle;
- reviewer/status/branch/date/task/duplicate evidence ambigu → `manual_review`.

Jangan mengarang reviewer/timestamp. Jangan memilih record tertua sebagai canonical bila terdapat bukti/status yang berkonflik.

Tambahkan test command:

- dry-run tidak mengubah data;
- execute test mempertahankan attachment, bukti, contributor, reviewer, timestamp, dan source ID;
- rerun tidak menggandakan data;
- daily/weekly/monthly grouping;
- duplicate collision;
- missing branch/date;
- synthetic rejection exclusion;
- manual review;
- checkpoint/resume;
- restore snapshot pada database test.

# Phase 10 — Compare score dan reconciliation tooling

Implementasikan compare command/report non-mutating untuk score lama vs metode bundle:

- branch;
- bulan;
- score lama dan baru;
- delta;
- approved/expected per periode;
- perubahan status;
- missing count;
- manual review/conflict count;
- outlier dan error;
- method version.

Sediakan recompute histori dengan:

- dry-run/compare default;
- `--execute` eksplisit;
- filter scope;
- snapshot nilai lama;
- idempotency;
- transaction per branch/periode;
- restore path yang diuji.

Jangan menjalankan execute pada production tanpa persetujuan eksplisit.

# Phase 11 — Cleanup compatibility dan hardening

Setelah seluruh consumer berpindah dan test/reference audit membuktikan aman:

- hapus dependency terhadap approval per item dari active path;
- pertahankan kolom legacy selama compatibility window jika masih dibutuhkan histori;
- tambahkan unique constraint branch setelah duplicate resolution berhasil;
- nonaktifkan/hapus code path lama melalui PR terpisah;
- jangan menghapus data sumber atau attachment tanpa approval bisnis;
- audit route, Filament discovery, sidebar, permission, scheduler, export, dan command registration.

Tambahkan test regresi untuk navigation/sidebar agar membuka menu Nilai Briefing tidak meng-collapse group yang aktif.

# Phase 12 — Verifikasi akhir lokal/CI

Jalankan quality gates secara proporsional:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact [affected test files/filters]
npm run build
git diff --check
git status --short
```

Jika affected suite hijau, jalankan suite modul Daily Briefing yang lebih luas. Jalankan full suite bila waktu/CI memungkinkan atau perubahan menyentuh shared authorization/schema.

Verifikasi manual/visual:

- Employee App submit pada viewport 360, 390, 768, 1024, dan 1440;
- progres dan completion;
- Monitoring list/filter/pagination;
- modal review keyboard/focus/Escape/loading;
- approve/reject/reopen;
- branch isolation;
- score workspace dan settings;
- dark mode bila panel mendukung;
- PDF/Excel sample;
- tidak ada error browser/log terbaru;
- tidak ada N+1 atau query berat yang jelas.

Jika perubahan frontend tidak terlihat, jalankan build sesuai workflow repository; jangan menjalankan development server karena aplikasi disediakan Laravel Herd.

# Phase 13 — Rollout-ready report

Setelah kode selesai, berikan laporan:

1. ringkasan hasil implementasi;
2. keputusan domain/schema final;
3. migration yang dibuat;
4. file dan workflow utama yang berubah;
5. daftar test dan hasilnya;
6. hasil formatter/build;
7. hasil audit authorization dan branch scope;
8. hasil audit query/performa;
9. command dry-run yang tersedia;
10. langkah deployment;
11. langkah grouping/cleanup/recompute production yang masih menunggu izin;
12. rollback plan;
13. manual review/outstanding risk;
14. acceptance checklist PRD.

## Production safety gate

Setelah implementasi dan dry-run tooling siap, berhenti sebelum tindakan berikut:

- menjalankan migration production;
- menjalankan cleanup dengan mutation;
- menjalankan grouping legacy `--execute`;
- menjalankan recompute histori `--execute`;
- menghapus parent/item legacy;
- mengubah permission/role production secara massal;
- push/merge/deploy ke staging atau main jika belum diminta.

Sajikan command exact yang diusulkan, target environment, scope branch/periode, jumlah kandidat dari dry-run, snapshot path, risiko, estimasi dampak, dan rollback. Minta persetujuan eksplisit untuk setiap tindakan production yang mutating.

## Urutan rollout production setelah disetujui

Jalankan hanya jika user memberi otorisasi terpisah:

1. backup database dan verifikasi restore path;
2. deploy schema additive serta compatibility code;
3. jalankan migration schema;
4. verifikasi health, route, queue, scheduler, dan log;
5. hentikan synthetic rejection path;
6. aktifkan bundle write path secara bertahap/feature flag jika tersedia;
7. jalankan cleanup audit/dry-run;
8. jalankan grouping histori dry-run;
9. review sample serta manual-review bucket;
10. buat snapshot mutation;
11. jalankan grouping scope kecil yang disetujui;
12. validasi parity dan UI;
13. lanjutkan scope berikutnya;
14. jalankan compare score;
15. jalankan recompute terbatas jika disetujui;
16. monitor log, access denial, bundle conflict, missing count, queue, dan score delta;
17. lakukan cleanup compatibility hanya setelah masa observasi dan persetujuan.

## Definition of Done

Jangan menyatakan selesai sebelum seluruh kondisi berikut terpenuhi atau secara eksplisit dilaporkan sebagai production gate yang belum diotorisasi:

- satu bundle canonical per branch, period, dan period start;
- multi-contributor tidak menghasilkan bundle ganda;
- semua poin lengkap sebelum review;
- approval/rejection hanya satu kali per bundle;
- rejected dapat menunjukkan poin koreksi;
- missing tidak membuat submission palsu;
- role dan branch scope aman pada UI, query, export, dan mutation;
- score menggunakan approved bundle dibanding expected due occurrences;
- UI, PDF, dan Excel konsisten;
- data lama mempunyai dry-run grouping, manual review, snapshot, dan restore;
- synthetic rejection tidak dianggap submission valid;
- test, formatter, build, dan review visual lulus;
- rollout serta rollback terdokumentasi;
- tidak ada mutation/deployment production tanpa instruksi eksplisit.

Kerjakan dengan perubahan kecil dan dapat diverifikasi. Jika menemukan ketidaksesuaian antara PRD dan repository, jangan menebak atau mengabaikannya: tunjukkan bukti, pilih solusi paling aman yang tetap memenuhi objektif, tambahkan test, dan catat keputusan dalam laporan akhir.

