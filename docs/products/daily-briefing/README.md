# Daily Briefing

Kewajiban harian, mingguan, dan bulanan setiap cabang (foto, teks, atau checklist), direview supervisor, lalu dinilai per cabang per bulan. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu / Tile | Permission |
|---|---|---|
| Back Office → Daily Briefing | Monitoring Poin | `view briefing items`; review: `review briefing items` |
| | Kelola Poin | `view briefing records` (+ create/edit/delete) |
| | Nilai Briefing | `view briefing scores`; hitung ulang: `edit briefing scores` |
| | Bobot Penilaian | `edit briefing period weights` (route lama; menu digantikan bagian *Override Branch* di Pengaturan) |
| | Pengaturan | `edit briefing settings` |
| Employee App | Tile **Briefing** | `access employee app briefing`; mengisi: `submit daily briefing` |

## Konsep

- **Poin (BriefingTask)** — kewajiban per periode (`daily`, `weekly`, `monthly`) dengan jenis input `camera_only`, `photo`, `photo_and_text`, `text_only`, atau `checkbox`, grup, urutan, dan deadline opsional (hari/tanggal dan jam). Poin dapat dibatasi ke branch tertentu.
- **Unit kewajiban per branch.** Satu submission kanonik untuk kombinasi branch + poin + periode + tanggal.
- **Partisipasi branch.** Hanya branch yang terdaftar sebagai peserta (dengan tanggal mulai) yang dinilai. Partisipasi diatur di Pengaturan.
- **Status turunan.** `missing`, `not_due`, dan `not_applicable` dihitung dari assignment, deadline, dan ketiadaan submission. Status ini tidak disimpan (`BriefingStatusService`).

## Alur

1. Staff membuka tile **Briefing**, memilih tanggal, lalu mengisi poin sesuai jenis input (foto kamera, foto galeri, teks, atau checklist). Backdate dibatasi oleh `BriefingBackdateEligibility`.
2. `BriefingSubmissionAction` memvalidasi partisipasi, assignment, deadline, dan jenis input di server, lalu menyimpan submission dengan status review `pending` atau `supervisor_review`.
3. Supervisor me-review di **Monitoring Poin**: Approve, Reject (dengan alasan), Buka Kembali (dengan alasan), atau bulk approve/reject. `BriefingReviewAction` adalah satu-satunya jalur perubahan status dan memeriksa branch scope reviewer.
4. Submission yang tidak direview melewati SLA di Pengaturan otomatis di-reject oleh `briefing:auto-reject` (tiap menit).
5. Nilai dihitung per branch per bulan oleh `briefing:compute-scores` (tanggal 1, 02:00) atau tombol **Hitung Ulang** di Nilai Briefing.

Status review: `pending`, `supervisor_review`, `approved`, `rejected`.

## Penilaian

- `BriefingScoreCalculator` (method version 2): skor periode = jumlah occurrence approved ÷ jumlah occurrence yang diharapkan pada poin yang dinilai (`include_in_score`). Occurrence = branch + poin + periode + tanggal.
- Bobot periode Harian/Mingguan/Bulanan diatur default, dengan override per branch di Pengaturan. Periode yang tidak berlaku tidak dihitung sebagai nol.
- Hasil disimpan di `briefing_scores` beserta breakdown (versi metode, bobot, approved/expected, waktu hitung).
- Nilai Briefing menampilkan ringkasan, ranking, detail per branch, export PDF/Excel. Staff melihat nilai branch-nya di Employee App bila memiliki `view briefing scores`.

## Command

| Command | Fungsi |
|---|---|
| `briefing:auto-reject` | Terjadwal tiap menit; reject submission melewati SLA review |
| `briefing:compute-scores` | Terjadwal bulanan; hitung nilai |
| `briefing:compare-scores` | Read-only; bandingkan skor tersimpan dengan kalkulator |
| `briefing:recompute-history` | Dry-run default; `--confirm` untuk menulis, snapshot dan `--restore` tersedia |
| `briefing:audit-invalid-records`, `briefing:cleanup-invalid-records` | Audit read-only; cleanup hanya dry-run |
| `briefing:approve-existing` | One-off: approve submission branch tertentu |

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/Briefing*/`, `app/Filament/Helpdesk/Pages/Briefing*.php` |
| Employee App | `app/Filament/Casual/Pages/{DailyBriefingPage,BriefingHistoryPage,BriefingScorePage}.php` |
| Logika | `app/Actions/Briefing/`, `app/Services/Briefing*.php` |
| Policy | `app/Policies/BriefingItemPolicy.php` |
| Enum | `app/Enums/Briefing*.php` |
| Model | `app/Models/Briefing*.php` |
| Command | `app/Console/Commands/Briefing*.php` |
| Test | `tests/Feature/{Briefing,DailyBriefing}*Test.php` |

## Data

`BriefingTask`, `BriefingRecord`, `BriefingItem`, `BriefingScore`, `BriefingSettings`, `BriefingBranchSetting`, `BriefingPeriodWeight`. Lihat [Data Model](../../architecture/data-model.md#daily-briefing).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [PRD Remediation](remediation/prd.md) | Implemented — partisipasi, assignment, status, review, scheduler |
| [PRD Scoring & UI](scoring-ui/prd.md) | Implemented — metode nilai v2, Pengaturan, Nilai Briefing |

## Role bawaan

`HRD_STAFF` mengelola poin, review, dan nilai. `SUPERVISOR_STORE` me-review branch yang diaksesnya. `STORE_STAFF` mengisi briefing dan melihat nilai.
