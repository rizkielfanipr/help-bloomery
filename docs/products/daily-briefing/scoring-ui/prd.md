# PRD — Metode Penilaian dan UI Nilai Daily Briefing

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 12 Oktober 2026 |
| Status | Implemented (diverifikasi terhadap kode 27 Oktober 2026). Status awal: Rancangan siap review; belum mengotorisasi implementasi, recompute production, migration production, atau deployment |
| Modul | Daily Briefing — Scoring, Settings, dan Report |
| Halaman utama | Human Resources → Nilai Briefing |
| Halaman pengaturan | Human Resources → Pengaturan Briefing |
| Referensi UI nilai | Finance → Basket Size |
| Referensi UI settings | Finance → Sales Report Settings |
| Acuan remediasi | `docs/products/daily-briefing/remediation/prd.md` |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

Dokumen ini mengatur perubahan metode perhitungan nilai Daily Briefing dan penyajian UI-nya. Requirement partisipasi branch, role `STORE_STAFF`, assignment task, status `missing`, branch scope, scheduler, dan cleanup tetap mengikuti `docs/products/daily-briefing/remediation/prd.md`.

Dokumen ini tidak memberi izin untuk menimpa nilai historis, menjalankan recompute production, menghapus halaman lama, mengubah permission production secara massal, atau melakukan deployment.

## 2. Latar belakang

Nilai Briefing saat ini disajikan sebagai Filament Resource berbentuk tabel dengan detail breakdown dalam modal. Pengaturan tanggal awal scoring, auto-reject, dan bobot periode berada pada halaman yang terpisah. Perhitungan sudah membedakan Harian, Mingguan, dan Bulanan, tetapi kontrak formula, denominator, effective date, histori metode, serta kesesuaian angka antara UI dan export belum cukup eksplisit.

User memilih dua pola existing sebagai arah:

1. Halaman Nilai Briefing mengikuti pengalaman workspace Basket Size: header informatif, filter periode/branch, ringkasan, ranking, detail, dan proses hitung ulang yang terlihat.
2. Halaman Pengaturan Briefing mengikuti Sales Report Settings: form bersection, helper text lokal, informasi proses otomatis, serta footer simpan yang jelas.

Perubahan visual tidak boleh membuat kalkulasi baru di Blade atau memperlebar akses branch.

## 3. Masalah yang diselesaikan

1. Rumus nilai belum menjadi kontrak produk yang mudah dijelaskan dan diuji.
2. Nilai per task dan per periode belum disajikan dengan approved/expected yang mudah diaudit.
3. Perhitungan bulan berjalan berisiko memasukkan tanggal masa depan jika tidak dibatasi eksplisit.
4. Branch yang belum/tidak mengikuti Daily Briefing tidak boleh mendapat denominator atau score.
5. Bobot default dan override branch terpisah dari pengaturan scoring utama.
6. Halaman nilai belum memberikan pengalaman ranking dan drill-down seperti Basket Size.
7. Hitung ulang belum mempunyai progress, batas scope, dan ringkasan hasil seperti Basket Size.
8. UI, breakdown modal, PDF, dan Excel harus menggunakan dataset serta calculator yang sama.
9. Perubahan metode tidak boleh diam-diam mengubah nilai historis.
10. Supervisor hanya boleh melihat branch sesuai `accessibleBranchIds()`.

## 4. Tujuan

1. Menetapkan formula nilai Daily Briefing per branch yang transparan.
2. Memusatkan kalkulasi dalam service yang dapat digunakan command, UI, PDF, dan Excel.
3. Menampilkan ranking branch dengan pola interaksi Basket Size.
4. Menyatukan pengaturan scoring, bobot, dan auto-reject dalam pengalaman yang konsisten dengan Sales Report Settings.
5. Mempertahankan effective date, branch participation, task assignment, dan branch scope.
6. Menyediakan recompute yang aman, terlihat progresnya, dan idempotent.
7. Menjaga reproducibility nilai historis melalui breakdown snapshot dan versi metode.
8. Menyediakan rollout bertahap serta compare/dry-run sebelum nilai lama dihitung ulang.

## 5. Non-tujuan

- Mengubah requirement peserta Daily Briefing.
- Menjadikan seluruh `STORE_STAFF` wajib mengirim task yang sama jika unit kewajiban telah ditetapkan per branch.
- Mengubah status `missing`, `approved`, atau `rejected` di luar PRD remediasi.
- Menghapus data score lama melalui migration.
- Mengganti framework UI.
- Membuat dashboard analytics lintas modul.
- Mengubah UI Basket Size atau Sales Report Settings.
- Menambah dependency chart atau frontend baru tanpa persetujuan.
- Menjalankan recompute seluruh histori secara otomatis.

## 6. Prinsip

1. **Per branch:** score mengukur kepatuhan branch, bukan kompetisi individual `STORE_STAFF`.
2. **Approved earns credit:** hanya occurrence task yang Approved mendapat kredit.
3. **Expected is explicit:** denominator berasal dari assignment branch pada tanggal yang memang berlaku.
4. **No future penalty:** tanggal masa depan tidak masuk expected.
5. **Not applicable is excluded:** branch/task/periode yang tidak berlaku tidak bernilai nol.
6. **One calculator:** UI, command, PDF, Excel, dan detail memakai hasil calculator yang sama.
7. **Historical reproducibility:** breakdown menyimpan konteks metode dan periode efektif.
8. **Branch-safe:** filter dan action menghormati `accessibleBranchIds()`; hanya `canAccessAllBranches()` mendapat akses global.
9. **UI explains the score:** pengguna dapat menelusuri angka akhir sampai occurrence/task sumbernya.
10. **No hidden recompute:** perubahan settings tidak otomatis menimpa histori tanpa aksi dan konfirmasi.

## 7. Unit penilaian

Unit kewajiban adalah **per branch**.

Satu occurrence didefinisikan sebagai:

```text
branch + task + period + record date
```

Satu occurrence mendapat kredit apabila terdapat submission canonical yang berstatus `approved`.

Jika beberapa `STORE_STAFF` dapat mengirim task yang sama, sistem tetap hanya menghitung maksimal satu occurrence approved. Aturan pemilihan submission canonical mengikuti implementasi assignment/submission pada PRD remediasi dan harus dilindungi transaction serta database invariant bila memungkinkan.

## 8. Metode perhitungan

### 8.1 Nilai periode

Untuk setiap periode:

```text
Period Score = Approved Occurrences ÷ Expected Occurrences × 100
```

Dengan:

- `Approved Occurrences`: jumlah occurrence yang memiliki submission Approved;
- `Expected Occurrences`: jumlah occurrence yang wajib berdasarkan partisipasi branch, task aktif/scoreable, effective date, dan tanggal yang sudah berlaku.

Jika `Expected Occurrences = 0`, periode menjadi `not_applicable` dan tidak menerima nilai nol.

### 8.2 Nilai akhir

```text
Final Score =
    (Daily Score × Effective Daily Weight)
  + (Weekly Score × Effective Weekly Weight)
  + (Monthly Score × Effective Monthly Weight)
```

Bobot dinyatakan sebagai proporsi 0–1 dalam kalkulasi dan persen pada UI.

Contoh:

| Periode | Approved | Expected | Nilai | Bobot | Kontribusi |
| --- | ---: | ---: | ---: | ---: | ---: |
| Harian | 54 | 60 | 90.00 | 40% | 36.00 |
| Mingguan | 8 | 10 | 80.00 | 30% | 24.00 |
| Bulanan | 3 | 3 | 100.00 | 30% | 30.00 |
| **Total** |  |  |  | **100%** | **90.00** |

### 8.3 Effective weight

Jika satu periode tidak memiliki expected occurrence, configured weight periode tersebut didistribusikan proporsional ke periode yang berlaku.

Contoh configured weight Harian 40%, Mingguan 30%, Bulanan 30%, tetapi Bulanan tidak berlaku:

```text
Effective Daily Weight  = 40 ÷ (40 + 30) × 100 = 57.14%
Effective Weekly Weight = 30 ÷ (40 + 30) × 100 = 42.86%
```

Configured dan effective weight keduanya wajib ditampilkan pada breakdown.

### 8.4 Status nilai

| Kondisi | Status |
| --- | --- |
| Score final ≥ passing score | Achieve |
| Score final < passing score | Tidak Achieve |
| Tidak ada expected occurrence | Belum Berlaku / Tidak Ada Penilaian |
| Periode berjalan belum final | Sementara |
| Kalkulasi gagal atau stale | Perlu Dihitung Ulang |

Default `passing_score` adalah 80 dan dapat diatur sesuai permission.

### 8.5 Perlakuan status submission

| Status | Kredit | Denominator |
| --- | ---: | ---: |
| Approved | 1 | 1 |
| Supervisor Review | 0 | 1 |
| Rejected | 0 | 1 |
| Missing setelah deadline | 0 | 1 |
| Not Due | Tidak dihitung | Belum masuk |
| Not Applicable | Tidak dihitung | Tidak masuk |

Submission yang belum melewati deadline pada hari berjalan tidak boleh menurunkan score sementara.

## 9. Effective date dan kalender

Expected occurrence hanya dibuat jika seluruh kondisi terpenuhi:

1. branch merupakan peserta pada tanggal occurrence;
2. tanggal tidak lebih awal dari `scoring_started_at` global;
3. tanggal berada dalam rentang partisipasi branch;
4. task aktif dan `include_in_score = true` pada tanggal tersebut;
5. occurrence telah jatuh tempo;
6. tanggal tidak berada di masa depan;
7. period sesuai kalender Harian/Mingguan/Bulanan.

Untuk bulan berjalan, `period_end` efektif adalah minimum dari akhir bulan dan waktu/tanggal sekarang sesuai kebutuhan deadline.

## 10. Arsitektur kalkulasi

Buat service domain, misalnya `BriefingScoreCalculator`, tanpa ketergantungan Filament/Livewire.

Kontrak minimum:

```php
public function calculate(
    Branch $branch,
    int $year,
    int $month,
): BriefingScoreResult;
```

Result minimum:

```php
[
    'method_version' => 2,
    'branch_id' => 1,
    'year' => 2026,
    'month' => 10,
    'score' => 90.0,
    'passing_score' => 80.0,
    'status' => 'achieved',
    'is_provisional' => true,
    'period_start' => '2026-10-01',
    'period_end' => '2026-10-12',
    'periods' => [
        'daily' => [
            'approved' => 54,
            'expected' => 60,
            'rate' => 90.0,
            'configured_weight' => 40.0,
            'effective_weight' => 40.0,
            'contribution' => 36.0,
            'tasks' => [],
        ],
    ],
]
```

Gunakan value object/result class hanya jika membantu type safety dan test; jangan membuat abstraction spekulatif.

## 11. Penyimpanan score dan snapshot

`briefing_scores` tetap menjadi hasil agregat per branch/year/month.

Field yang dibutuhkan:

- `branch_id`;
- `year`;
- `month`;
- `score`;
- `breakdown`;
- `computed_at`;
- `method_version`, jika tidak cukup disimpan dalam breakdown;
- opsional `is_provisional`, jika dibutuhkan query/filter langsung.

Breakdown wajib menyimpan:

- method version;
- passing score saat dihitung;
- configured dan effective weights;
- approved/expected per periode;
- kontribusi per periode;
- daftar task dan aggregate occurrence;
- period start/end efektif;
- participation/effective-date context yang cukup;
- waktu kalkulasi.

Perubahan settings setelah score dihitung tidak mengubah snapshot lama sampai recompute dijalankan.

## 12. Settings

### 12.1 Field global

Tambahkan atau pertahankan:

| Field | Aturan |
| --- | --- |
| `scoring_started_at` | Wajib; tanggal awal scoring global |
| `passing_score` | Wajib; numeric 0–100; default 80 |
| `auto_reject_enabled` | Boolean bila auto-reject dapat dinonaktifkan |
| `auto_reject_after_days` | Integer minimal 1 |
| `auto_reject_effective_from` | Tanggal mulai aturan auto-reject bila dibutuhkan |
| `auto_reject_reason` | Maksimal 255 karakter; mendukung `:days` |

Keputusan penambahan toggle/effective date auto-reject mengikuti audit schema aktual dan compatibility data existing.

### 12.2 Bobot default

Default awal:

- Harian 40%;
- Mingguan 30%;
- Bulanan 30%.

Total wajib tepat 100% dengan toleransi pembulatan maksimal 0,01.

### 12.3 Override branch

Branch peserta dapat menggunakan override bobot. Branch tanpa override menggunakan default.

UI menyediakan:

- pilihan branch;
- informasi nilai default;
- toggle `Gunakan Bobot Khusus`;
- tiga field bobot;
- total bobot real-time;
- aksi reset ke default.

Nonaktifkan override dengan menghapus row override atau mengikuti pola existing yang aman. Jangan menyimpan tiga nilai null sebagai override ambigu.

## 13. UI Nilai Briefing — pola Basket Size

Halaman Nilai Briefing berubah dari tabel Resource generik menjadi workspace custom atau Page yang dapat memenuhi interaksi berikut. Route penting dipertahankan atau diberikan redirect kompatibel.

### 13.1 Header workspace

Gunakan pola visual Basket Size:

- container `rounded-2xl`, border, tanpa shadow biasa;
- ikon bintang/chart dalam aksen biru;
- eyebrow `Human Resources · Performance`;
- judul `Nilai Daily Briefing`;
- deskripsi ringkas;
- badge periode aktif;
- tombol `Hitung Ulang` jika user berizin.

Informasi bawah header:

> Peringkat mengikuti nilai akhir Daily Briefing berdasarkan occurrence task Approved dan bobot periode pada bulan yang dipilih.

### 13.2 Filter

Filter minimum:

- Tahun;
- Bulan;
- Branch;
- pencarian nama branch bila daftar besar;
- reset filter.

Default:

- tahun berjalan;
- bulan terakhir yang sudah final, atau bulan berjalan jika produk menginginkan monitoring provisional;
- semua branch yang dapat diakses.

Pilihan branch hanya berasal dari scope akses user.

### 13.3 Ringkasan

Tampilkan metrik yang membantu keputusan:

- jumlah branch dinilai;
- rata-rata nilai;
- jumlah Achieve;
- jumlah Tidak Achieve;
- jumlah Belum Dinilai/Perlu Dihitung Ulang.

Jangan menampilkan kartu dekoratif tanpa fungsi.

### 13.4 Ranking branch

Kolom minimum:

- Peringkat;
- Branch;
- Nilai Harian;
- Nilai Mingguan;
- Nilai Bulanan;
- Nilai Akhir;
- Status;
- Dihitung;
- aksi Detail.

Urutan default:

1. score final descending;
2. nama branch ascending sebagai tie-breaker.

Branch tanpa score valid ditampilkan terpisah sebagai `Belum Dinilai`, bukan diberi nilai 0 dan dimasukkan ranking.

### 13.5 Detail breakdown

Gunakan modal lebar atau detail panel dengan:

- identitas branch dan periode;
- score/status;
- provisional/final indicator;
- configured dan effective weight;
- approved/expected/rate/contribution per periode;
- breakdown per task;
- jumlah Approved, Pending, Rejected, dan Missing;
- period start/end efektif;
- method version;
- computed timestamp.

Modal mengikuti accessibility dan responsive requirements `docs/standards/ui-consistency.md`.

### 13.6 Hitung ulang

Ikuti pengalaman Basket Size:

- scope branch/periode dijelaskan sebelum konfirmasi;
- duplicate click dicegah;
- progress terlihat;
- proses dibagi batch jika banyak branch;
- batas maksimal per request;
- proses besar diarahkan ke command/job;
- hasil merangkum berhasil, skipped, dan gagal;
- kegagalan satu branch tidak menghapus score valid branch lain.

UI tidak menyusun command string. Gunakan Action/Service yang sama dengan command.

### 13.7 State

| State | Perilaku |
| --- | --- |
| Loading | Indikator pada ranking/summary terkait |
| Empty | Jelaskan tidak ada branch peserta atau filter tanpa hasil |
| Belum dihitung | Aksi hitung ulang bila berizin |
| Stale | Peringatan bahwa settings/assignment berubah setelah score dihitung |
| Provisional | Badge `Sementara` untuk bulan berjalan |
| Failed | Pesan aman dan opsi retry jika berizin |
| No access | Fail-closed; jangan tampilkan data branch lain |

## 14. UI Pengaturan Briefing — pola Sales Report Settings

Gunakan satu form utama dengan container `rounded-2xl`, border, header section, body, dan footer simpan seperti Sales Report Settings.

### 14.1 Header

- ikon calculator/scale;
- judul `Pengaturan Nilai Daily Briefing`;
- deskripsi singkat tentang scoring per branch.

Hindari heading ganda dari layout dan konten.

### 14.2 Section Metode Penilaian

Field:

- Mulai Penilaian Briefing;
- Nilai Minimum Achieve;
- informasi bahwa hanya Approved mendapat kredit;
- informasi bahwa tanggal masa depan/not-applicable tidak dihitung.

Tampilkan preview formula, bukan kalkulasi data production.

### 14.3 Section Bobot Default

Field:

- Bobot Harian;
- Bobot Mingguan;
- Bobot Bulanan.

Tampilkan total real-time:

```text
Total Bobot: 100% — Valid
```

atau:

```text
Total Bobot: 90% — Harus 100%
```

Error berada dekat field dan form tidak tersimpan jika total tidak valid.

### 14.4 Section Override Branch

- Select branch peserta;
- nilai default read-only;
- toggle bobot khusus;
- field override;
- reset ke default;
- empty state jika tidak ada branch peserta.

Jangan menampilkan seluruh branch sebagai tabel input panjang pada load pertama. Muat konfigurasi branch yang dipilih.

### 14.5 Section Auto-Reject Review

Mengikuti anatomi Sales Report Settings:

- toggle aktivasi jika digunakan;
- jumlah hari;
- berlaku mulai jika digunakan;
- alasan auto-reject;
- helper text `:days`;
- preview jumlah item eligible sebagai informasi read-only jika query cukup ringan.

Copy wajib menyebut bahwa auto-reject berlaku pada submission yang sudah dikirim tetapi belum direview, bukan pada user yang tidak mengisi.

### 14.6 Informasi Proses Otomatis

Container informasi biru menjelaskan:

- scheduler/recompute tidak membuat submission palsu;
- status yang mendapat nilai;
- status yang bernilai nol;
- perubahan settings tidak otomatis menghitung ulang histori;
- bobot branch override mengalahkan default;
- bulan berjalan bersifat sementara.

### 14.7 Footer

- copy `Perubahan berlaku setelah pengaturan disimpan.`;
- tombol `Simpan Pengaturan`;
- loading state `Menyimpan…`;
- disabled selama request;
- feedback sukses/gagal.

## 15. Navigation dan compatibility

- Menu `Nilai Briefing` tetap berada pada group Human Resources.
- Route existing dipertahankan bila memungkinkan.
- Halaman `Bobot Penilaian Briefing` tidak langsung dihapus.
- Setelah settings baru stabil, menu lama dapat disembunyikan dan route diarahkan ke section bobot pada halaman settings.
- Penghapusan Resource/Page lama mengikuti reference audit dan test Filament discovery.
- Export URL existing dipertahankan atau diberikan compatibility redirect.

## 16. Authorization dan branch scope

### Nilai Briefing

- User dengan `canAccessAllBranches()` melihat semua branch peserta.
- User lain hanya melihat `accessibleBranchIds()`.
- Form filter, query ranking, detail modal, recompute, dan export memakai scope yang sama.
- Parameter branch dari URL/Livewire/request tidak dipercaya.

### Pengaturan

- Hanya permission `edit briefing settings` dapat menyimpan pengaturan global.
- Permission bobot branch dapat dipertahankan atau digabung secara eksplisit; jangan memperluas akses tanpa keputusan.
- Jika supervisor boleh mengatur bobot branch, hanya branch accessible yang dapat diubah.
- Authorization diulang pada mutation server-side.

### Employee App

- `STORE_STAFF` hanya melihat score branch sendiri jika diizinkan.
- PDF score memakai permission dan branch scope yang sama dengan halaman.

## 17. Report dan export

PDF dan Excel minimal memuat:

- branch;
- periode;
- score final;
- status;
- provisional/final;
- score Harian/Mingguan/Bulanan;
- configured/effective weight;
- approved/expected;
- contribution;
- method version;
- computed timestamp.

Ketentuan:

- UI, PDF, dan Excel membaca snapshot breakdown yang sama;
- angka dan pembulatan konsisten;
- report bulan berjalan diberi label `Sementara`;
- branch tanpa akses ditolak server-side;
- empty report menjelaskan tidak ada data;
- report histori tetap dapat menampilkan breakdown metode lama.

## 18. Recompute dan migration histori

### 18.1 Tidak otomatis

Migration schema tidak menjalankan recompute. Penyimpanan settings tidak otomatis menimpa score historis.

### 18.2 Compare command

Sediakan mode audit/dry-run, misalnya:

```bash
php artisan briefing:compare-scores --year=2026 --month=9
```

Output minimum:

- branch;
- score lama;
- score metode baru;
- delta;
- status lama/baru;
- approved/expected;
- alasan perubahan besar;
- jumlah branch dibandingkan/skipped/error.

### 18.3 Recompute

Recompute memerlukan year, month, dan opsional branch. Untuk production:

- jalankan setelah hasil compare direview;
- simpan/export snapshot lama;
- proses per batch;
- log hasil;
- jangan menghapus score lama jika kalkulasi baru gagal;
- sediakan rollback berdasarkan snapshot/artifact.

## 19. Observability

Catat:

- calculation run ID;
- method version;
- branch/year/month;
- approved/expected;
- final score;
- provisional flag;
- skipped reason;
- duration;
- failure class/message aman.

Metric minimum:

- calculation success/failure;
- stale score count;
- average calculation duration;
- branch without expected occurrence;
- score delta saat compare;
- recompute count;
- export count/failure bila tersedia.

## 20. Phase implementasi

### Phase 0 — Baseline dan keputusan

- Pastikan remediasi assignment/branch scope stabil.
- Rekam formula dan score existing.
- Pilih periode/branch sample.
- Konfirmasi default passing score dan bobot.
- Konfirmasi permission settings/bobot.
- Tentukan apakah bulan berjalan default ditampilkan.

**Selesai jika:** keputusan yang memengaruhi schema dan UI dikunci.

### Phase 1 — Characterization test

- Test score existing yang masih valid.
- Test branch scope.
- Test weight default/override.
- Test report existing.
- Test route/navigation existing.

**Selesai jika:** kontrak yang dipertahankan memiliki safety net.

### Phase 2 — Calculator dan snapshot

- Implementasikan `BriefingScoreCalculator`.
- Tambahkan result contract.
- Tambahkan method version dan snapshot breakdown.
- Refactor command menjadi orkestrator.
- Tambahkan unit/feature test formula.

**Selesai jika:** calculator menghasilkan output deterministic tanpa UI.

### Phase 3 — Settings domain

- Tambahkan `passing_score` dan field yang disetujui.
- Konsolidasikan default/override weight.
- Buat validation/action penyimpanan.
- Pertahankan compatibility halaman bobot lama.

**Selesai jika:** settings valid, transactional, dan tidak memicu recompute tersembunyi.

### Phase 4 — UI Settings

- Implementasikan layout ala Sales Report Settings.
- Tambahkan section metode, bobot, override, auto-reject, dan informasi otomatis.
- Tambahkan loading/error/success state.
- Review responsive dan accessibility.

**Selesai jika:** seluruh settings dapat dikelola pada satu pengalaman konsisten.

### Phase 5 — Workspace Nilai

- Buat Page/workspace ala Basket Size.
- Tambahkan filter, summary, ranking, dan detail.
- Terapkan branch scope.
- Pertahankan route/compatibility Resource.

**Selesai jika:** ranking dan breakdown memakai snapshot calculator yang sama.

### Phase 6 — Recompute UX

- Ekstrak Action/Service recompute.
- Tambahkan confirm, batch, progress, limit, dan summary.
- Tambahkan command/job untuk scope besar.
- Pastikan idempotent dan partial failure aman.

**Selesai jika:** user dapat menghitung ulang scope yang diizinkan dengan feedback lengkap.

### Phase 7 — PDF/Excel dan Employee Score

- Perbarui report untuk breakdown baru.
- Samakan permission halaman/PDF.
- Tambahkan provisional/method version.
- Verifikasi angka identik dengan UI.

**Selesai jika:** seluruh output memakai snapshot dan scope yang sama.

### Phase 8 — Compare dan rollout

- Implementasikan compare dry-run.
- Review delta sample.
- Siapkan recompute plan dan rollback artifact.
- Jalankan recompute production hanya atas instruksi eksplisit.

**Selesai jika:** dampak histori diketahui sebelum mutation.

## 21. Pengujian

### Formula

- Approved/expected per periode.
- Missing, pending, dan rejected bernilai nol setelah due.
- Not-due tidak masuk expected.
- Not-applicable tidak masuk expected.
- Bulan berjalan tidak menghitung masa depan.
- Branch mulai/berakhir di tengah bulan.
- Task mulai/nonaktif di tengah periode bila effective dating tersedia.
- Effective-weight redistribution.
- Pembulatan konsisten.
- Passing threshold boundary.

### Settings

- Default weight total 100%.
- Invalid total ditolak.
- Override branch.
- Reset override.
- Permission mutation.
- Save tidak recompute histori.
- Auto-reject copy/field sesuai domain.

### Workspace

- Branch filter scoped.
- Ranking order dan tie-breaker.
- Summary sesuai ranking.
- Branch tanpa score tidak diberi nilai nol.
- Detail breakdown.
- Provisional/stale state.
- Recompute permission, batching, progress, dan failure.

### Report

- UI/PDF/Excel identik.
- Branch tampering ditolak.
- Method lama tetap terbaca.
- Empty report.
- Employee hanya branch sendiri.

### Compatibility

- Route lama.
- Filament discovery.
- Export link.
- Halaman bobot lama selama transisi.
- Test modul Daily Briefing lainnya tetap lulus.

## 22. Review visual

Periksa:

- viewport 360, 390, 768, 1024, dan 1440 px;
- zoom browser 200%;
- dark mode pada panel yang mendukung;
- ranking dengan nama branch panjang;
- modal breakdown panjang;
- filter aktif;
- loading recompute;
- invalid weight;
- empty/no-access/stale/provisional state;
- tabel tanpa scroll horizontal pada seluruh halaman.

## 23. Quality gates

1. Gunakan Laravel Boost `search-docs` sebelum perubahan kode.
2. Gunakan `database-schema` sebelum migration/model.
3. Buat file Laravel melalui Artisan `make:` dengan `--no-interaction`.
4. Jalankan `vendor/bin/pint --dirty --format agent` untuk perubahan PHP.
5. Jalankan test formula, settings, workspace, report, dan seluruh modul Briefing.
6. Jalankan `git diff --check`.
7. Periksa `php artisan route:list --path=briefing --except-vendor`.
8. Jalankan frontend build bila asset/source frontend berubah.
9. Lakukan review visual dan keyboard.
10. Sertakan migration, rollout, dan rollback note.

## 24. Strategi pull request

### PR 1 — Scoring domain

- Calculator;
- method version/snapshot;
- schema settings yang diperlukan;
- command orchestration;
- formula tests.

### PR 2 — Settings

- settings action/validation;
- UI ala Sales Report Settings;
- default/override weight;
- compatibility halaman bobot;
- settings tests dan visual review.

### PR 3 — Score workspace dan report

- UI ala Basket Size;
- ranking/detail/recompute;
- PDF/Excel;
- Employee Score;
- access/report tests dan visual review.

### PR 4 — Compare/recompute histori

- compare command;
- dry-run artifact;
- approved recompute tooling;
- tidak menjalankan mutation production sebagai bagian PR.

Jangan mencampur cleanup synthetic rejection dengan redesign scoring/UI dalam PR yang sama.

## 25. Rollout

1. Deploy calculator/schema tanpa recompute histori.
2. Verifikasi output metode baru pada test/sample nonproduction.
3. Deploy settings baru dan pertahankan compatibility route lama.
4. Deploy score workspace dengan data snapshot yang tersedia.
5. Jalankan compare dry-run pada periode yang disepakati.
6. Review delta bersama pemilik bisnis.
7. Recompute periode production hanya setelah persetujuan.
8. Monitor score delta, failure, stale count, export, dan laporan user.
9. Sembunyikan halaman bobot lama setelah penggunaan baru stabil.

## 26. Rollback

- UI baru dapat kembali ke route/resource lama tanpa menghapus snapshot.
- Task/assignment dan submission tidak diubah oleh rollback UI.
- Score lama disimpan sebelum recompute.
- Recompute gagal tidak menimpa score valid.
- Settings baru memiliki default compatible.
- Migration tidak menjatuhkan data pada rollback aplikasi setelah production terisi.
- Halaman bobot lama dipertahankan sampai settings baru tervalidasi.

## 27. Acceptance criteria

- [ ] Nilai dihitung per branch dari approved/expected occurrence.
- [ ] Hanya occurrence yang sudah due masuk denominator.
- [ ] Branch/task/periode not-applicable tidak diberi nilai nol.
- [ ] Tanggal masa depan tidak menurunkan score.
- [ ] Configured dan effective weight dapat ditelusuri.
- [ ] Total bobot default/override wajib 100%.
- [ ] Passing score dapat diatur dan tersimpan dalam snapshot.
- [ ] Nilai Briefing memakai workspace visual ala Basket Size.
- [ ] Ranking hanya berisi branch yang dapat diakses.
- [ ] Branch belum dinilai tidak dimasukkan sebagai score nol.
- [ ] Detail menjelaskan score sampai tingkat task/occurrence.
- [ ] Hitung ulang mempunyai konfirmasi, progress, limit, dan ringkasan.
- [ ] Pengaturan memakai pola Sales Report Settings.
- [ ] Override branch dapat diaktifkan dan di-reset.
- [ ] Penyimpanan settings tidak otomatis recompute histori.
- [ ] UI, PDF, dan Excel menghasilkan angka identik.
- [ ] Bulan berjalan diberi label Sementara.
- [ ] Score historis menyimpan method version/breakdown yang dapat dijelaskan.
- [ ] Compare dry-run tersedia sebelum recompute histori.
- [ ] Route dan export penting tetap kompatibel.
- [ ] Test, formatter, discovery, build, dan visual review lulus.
- [ ] Tidak ada recompute/deployment production tanpa instruksi eksplisit.

## 28. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Metode baru mengubah nilai lama | Method version, compare dry-run, snapshot, approval sebelum recompute |
| Denominator terlalu besar | Due-date/effective-date test dan breakdown approved/expected |
| Branch tanpa task mendapat nol | `not_applicable` dan effective-weight redistribution |
| Settings salah menurunkan nilai | Validasi total 100%, preview, tidak auto-recompute |
| Supervisor melihat branch lain | Scope yang sama pada filter/query/detail/action/export |
| UI dan export berbeda | Satu calculator dan snapshot breakdown |
| Recompute timeout | Batch, limit, job/command untuk scope besar |
| Partial failure menghapus nilai | Transaction per branch dan preserve last valid score |
| Resource lama dihapus terlalu cepat | Compatibility phase dan reference audit |
| Worktree remediasi belum stabil | Phase 0 wajib selesai sebelum scoring/UI |

## 29. Definition of Done

Perubahan dianggap selesai ketika:

- formula dan effective-date contract telah diterapkan serta diuji;
- assignment/branch participation dari PRD remediasi digunakan;
- calculator menjadi satu sumber angka;
- settings dan workspace baru memenuhi pola UI referensi;
- branch scope diterapkan pada seluruh query/action/export;
- PDF/Excel konsisten dengan UI;
- histori tidak berubah tanpa compare dan persetujuan;
- test modul, formatter, discovery, build, serta review visual lulus;
- rollout dan rollback terdokumentasi;
- tidak ada recompute atau deployment production yang dilakukan tanpa instruksi.

## 30. Prompt penggunaan

### Audit

> Audit implementasi terhadap `docs/products/daily-briefing/scoring-ui/prd.md`. Jangan mengubah kode atau data. Cocokkan formula, settings, workspace Basket Size, report, permission, branch scope, test, dan compatibility dengan repository aktual. Sajikan gap, risiko, keputusan, serta urutan perubahan.

### Implementasi

> Implementasikan phase yang ditentukan dari `docs/products/daily-briefing/scoring-ui/prd.md`. Ikuti `docs/products/daily-briefing/remediation/prd.md`, `docs/standards/code-remediation.md`, dan `docs/standards/ui-consistency.md`. Jangan menimpa perubahan user, jangan recompute atau deploy production, gunakan calculator bersama, pertahankan branch scope dan histori, tambahkan test, jalankan quality gates, serta laporkan rollout dan rollback.

### Compare histori

> Jalankan compare/dry-run score lama dan metode baru untuk periode yang ditentukan. Jangan melakukan mutation. Laporkan score lama/baru, delta, perubahan status, approved/expected, outlier, error, dan rekomendasi scope recompute sebelum meminta persetujuan.
