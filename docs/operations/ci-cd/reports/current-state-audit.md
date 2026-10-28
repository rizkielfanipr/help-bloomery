# Audit Baseline CI/CD Help Bloomery

## 1. Identitas audit

| Field | Nilai |
| --- | --- |
| Tanggal audit | 26 Oktober 2026 |
| Repository | `rizkielfanipr/help-bloomery` |
| Baseline commit | `aba7a2c049ff113ebe63cc77aa724dfd418ef662` |
| Branch lokal | `main` |
| Status | Phase 1 dan Phase 2 selesai; atomic staging aktif, production masih memakai deployment lama |
| Dokumen target | `docs/operations/ci-cd/release-pipeline.md` |

Audit ini adalah implementasi tahap pertama dari pembangunan ulang CI/CD. Tujuannya adalah mencatat kondisi nyata sebelum workflow, server, atau aturan GitHub diubah.

Audit ini tidak mengotorisasi deployment, perubahan konfigurasi VPS, perubahan GitHub Ruleset, penghapusan branch, atau pengubahan secret.

---

## 2. Ringkasan hasil

| Area | Status | Hasil utama |
| --- | --- | --- |
| Repository | Selesai | `ci.yml` aktif untuk PR dan `deploy.yml` lama tetap aktif setelah merge |
| Laravel runtime | Selesai | PHP 8.4, Laravel 13.8, Filament 5.6, Livewire 4.3 |
| Test pipeline | Selesai untuk PR | Build/Unit dan empat Feature shard lulus; workflow push lama masih serial |
| Deployment | Selesai untuk staging | Atomic staging aktif; production masih memakai forced-command deployment lama |
| GitHub repository | Selesai | PR #1 sudah squash-merge ke `main` sebagai commit `d66410c9` |
| GitHub branches | Selesai | `main`, `staging`, `feature/ci-cd-delivery-flow`, dan satu fix branch |
| GitHub Ruleset | Selesai | `Protect main` (`25804747`) aktif dan mewajibkan `CI Required` |
| VPS | Selesai | Runtime, deploy script, Nginx, worker, scheduler, backup, permission, dan kapasitas disk sudah diverifikasi |
| Monitoring | Selesai dari aplikasi | Production `/up` terverifikasi sehat setelah deployment run #63 |
| Queue dan scheduler | Selesai dari kode | Queue memakai database secara default; scheduler memiliki tugas operasional penting |

Kesimpulan terkini:

1. Workflow `CI Required` sudah menjadi gate teknis yang dapat diwajibkan sebelum merge.
2. Script artifact, atomic deployment, health check, dan rollback sudah version-controlled di `ops/deploy`.
3. Branch eksperimen CI/CD lama tidak boleh di-merge apa adanya karena berbasis commit yang tertinggal jauh dari `main`.
4. Atomic staging sudah tervalidasi; perubahan paling aman berikutnya adalah Phase 3 dengan production tetap tertahan approval.

---

## 3. Kondisi working tree lokal

Pada saat audit, working tree lokal berisi banyak perubahan aplikasi yang belum menjadi bagian dari pekerjaan CI/CD. Perubahan tersebut harus diperlakukan sebagai milik pengguna dan tidak boleh ikut dimodifikasi, dipindahkan, dihapus, atau di-commit bersama perubahan pipeline.

Konsekuensinya:

- pekerjaan CI/CD harus menggunakan file baru atau file yang benar-benar berada dalam scope;
- tidak boleh menjalankan `git reset --hard`, `git checkout -- .`, atau pembersihan massal;
- sebelum membuat commit CI/CD, daftar file harus diperiksa secara eksplisit;
- idealnya pekerjaan lanjutan dilakukan pada branch baru dari `main` yang bersih atau Git worktree terpisah.

---

## 4. Baseline repository

### 4.1 Stack terdeteksi

| Komponen | Versi/kondisi |
| --- | --- |
| PHP | 8.4 |
| Laravel | 13.8.0 |
| Filament | 5.6.2 |
| Livewire | 4.3.0 |
| Pest | 4.7.0 |
| PHPUnit | 12.5.24 |
| Node.js CI | 22 |
| Vite | 8 |
| Tailwind CSS | 4.3 |
| Database aplikasi | MySQL |
| Database CI | SQLite in-memory |
| Queue default `.env.example` | `database` |
| Cache default `.env.example` | `database` |

### 4.2 File operasional yang tersedia

| File | Fungsi | Status |
| --- | --- | --- |
| `.github/workflows/deploy.yml` | Test, build, dan deployment push `main`/`staging` | Aktif |
| `.env.example` | Kontrak konfigurasi aplikasi | Aktif |
| `bootstrap/app.php` | Routing dan health endpoint `/up` | Aktif |
| `routes/console.php` | Daftar scheduler Laravel | Aktif |
| `docs/operations/production/redis.md` | Pedoman Redis production | Aktif |
| `docs/operations/ci-cd/release-pipeline.md` | Desain target pipeline baru | Draft implementasi |

Belum ditemukan pada baseline `main`:

- script deployment VPS di dalam repository;
- konfigurasi Nginx;
- konfigurasi Supervisor;
- konfigurasi cron server;
- workflow Pull Request;
- Pull Request template;
- Dependabot configuration;
- script pembagian test paralel;
- artifact release yang dipromosikan dari staging ke production.

---

## 5. Workflow GitHub Actions saat ini

Workflow aktif adalah `.github/workflows/deploy.yml` dengan alur berikut:

```text
push main atau staging
  -> checkout
  -> setup PHP 8.4
  -> setup Node.js 22
  -> composer install
  -> npm ci
  -> npm run build
  -> unit tests
  -> feature tests serial, atau dua targeted tests jika commit berisi [skip full tests]
  -> SSH ke VPS
  -> deploy production atau deploy staging
```

### 5.1 Kondisi yang sudah baik

- token GitHub hanya memiliki `contents: read`;
- SSH menggunakan `BatchMode`, `IdentitiesOnly`, dan strict host-key checking;
- host key disediakan melalui secret `VPS_KNOWN_HOSTS`;
- deployment memiliki concurrency lock bersama;
- timeout job tersedia;
- dependency PHP dan frontend menggunakan lock file;
- CI menggunakan SQLite in-memory sehingga tidak menyentuh database production.

### 5.2 Masalah yang harus diperbaiki

| Temuan | Risiko | Prioritas |
| --- | --- | --- |
| Workflow hanya dipicu setelah push | Kode sudah masuk branch deployment sebelum test selesai | Kritis |
| Tidak ada required PR check | Direct push dapat melewati review | Kritis |
| `[skip full tests]` dibaca dari commit message | Test regresi dapat dilewati oleh author commit | Kritis |
| Feature tests berjalan serial | Feedback sekitar 20 menit | Tinggi |
| Production otomatis setelah push `main` | Tidak ada checkpoint UAT/approval | Tinggi |
| Build GitHub tidak menjadi artifact deployment | Kode yang diuji tidak terbukti identik dengan kode server | Tinggi |
| Command deployment tidak ada di repository | Sulit diaudit, diuji, dan direview | Tinggi |
| Belum terlihat post-deploy health gate | Workflow bisa sukses walau aplikasi tidak sehat | Tinggi |
| Actions memakai tag versi | Supply-chain lebih lemah daripada full commit SHA | Sedang |

---

## 6. Kondisi GitHub repository

Data yang berhasil dibaca melalui GitHub connector:

| Properti | Nilai |
| --- | --- |
| Repository | `rizkielfanipr/help-bloomery` |
| Visibility | Public |
| Default branch | `main` |
| Hak akun terhubung | Admin, maintain, push, pull, triage |
| Riwayat Pull Request akun | Kosong |
| Branch terdeteksi | `main`, `staging`, `feature/ci-cd-delivery-flow`, `fix/technician-logbook-access-and-username` |

Nama secret yang dipakai oleh workflow saat ini:

- `VPS_HOST`;
- `VPS_USER`;
- `VPS_SSH_KEY`;
- `VPS_KNOWN_HOSTS`.

Nilai secret tidak dibaca dan tidak boleh dimasukkan ke dokumentasi atau log.

### 6.1 Konfigurasi GitHub yang masih harus diverifikasi di UI

- GitHub Environment `staging` dan `production` benar-benar tersedia;
- environment `production` mempunyai required reviewer;
- `main` memiliki Ruleset atau branch protection;
- allowed merge methods yang aktif;
- secret berada pada scope repository atau environment yang tepat;
- Actions permissions tetap minimum;
- retention artifact dan workflow log.

---

## 7. Audit branch `feature/ci-cd-delivery-flow`

Branch ini berisi commit:

```text
244f8b6f ci: add staged delivery workflow
```

Commit tersebut menambahkan:

- `.github/workflows/quality.yml`;
- `.github/workflows/ci-and-staging.yml`;
- `.github/workflows/deploy-production.yml`;
- `.github/scripts/bloomery-github-deploy`;
- perubahan kecil pada satu test.

### 7.1 Keputusan audit

**Jangan merge branch ini apa adanya.**

Branch dibuat dari baseline yang lebih lama daripada `main`. Perbandingan branch terhadap `main` saat audit akan ikut menghapus banyak fitur, migration, test, dan modul yang sekarang sudah berada di production.

### 7.2 Bagian yang dapat dijadikan referensi

- production deployment manual memakai `workflow_dispatch`;
- validasi full commit SHA;
- memastikan commit merupakan ancestor `main`;
- staging marker sebelum production;
- deployment lock memakai `flock`;
- backup database sebelum production;
- penggunaan health endpoint `/up`;
- `queue:restart` setelah deployment.

### 7.3 Bagian yang tidak sesuai arsitektur target

- server masih melakukan checkout ke working tree aktif;
- dependency dan frontend dibangun ulang di server;
- tracked working tree tetap menjadi dependency deployment;
- maintenance mode digunakan untuk seluruh proses build;
- belum memakai release directory dan atomic symlink;
- rollback aplikasi belum otomatis;
- workflow staging dan production menjalankan quality gate dua kali, bukan mempromosikan artifact yang sama;
- GitHub Actions masih memakai tag, belum full commit SHA.

Implementasi berikutnya harus dibuat ulang dari `main` terbaru. File dari commit lama boleh dibaca sebagai referensi, tetapi tidak boleh diambil melalui merge branch penuh.

---

## 8. Baseline Laravel production behavior

### 8.1 Health endpoint

`bootstrap/app.php` mendaftarkan:

```php
health: '/up'
```

Endpoint ini dapat menjadi liveness check setelah release aktif. Untuk deployment gate penuh, endpoint ini harus dilengkapi dengan pemeriksaan terpisah terhadap database, cache, queue, dan halaman aplikasi penting.

### 8.2 Command operasional yang tersedia

| Command | Fungsi |
| --- | --- |
| `php artisan app:check-redis` | Memeriksa koneksi dan konfigurasi Redis |
| `php artisan monitoring:run-checks --force` | Menjalankan seluruh health check System Monitoring |
| `php artisan monitoring:record-deployment ...` | Mencatat status deployment dan commit |
| `php artisan optimize` | Cache configuration, route, event, dan view production |
| `php artisan queue:restart` | Restart queue worker secara graceful melalui cache signal |
| `php artisan migrate --force` | Menjalankan migration non-interaktif di production |

Laravel 13 mengharuskan queue worker yang berjalan lama di-restart setelah perubahan kode. Worker harus dikelola Supervisor atau process manager lain agar hidup kembali setelah `queue:restart`.

### 8.3 Scheduler yang berdampak operasional

`routes/console.php` memuat tugas berikut:

- penyelesaian warranty Service Request setiap hari;
- penyelesaian warranty ERP Request setiap jam;
- auto-reject Daily Briefing setiap menit;
- perhitungan nilai Briefing bulanan;
- sinkronisasi material R&D harian;
- sinkronisasi Product Price Index;
- auto-reject Sales Report setiap menit;
- finalisasi Basket Size setiap 10 menit;
- reminder task R&D harian;
- System Monitoring check setiap menit;
- agregasi dan pruning monitoring;
- sinkronisasi Ordering Recommendation harian.

Cron production harus menjalankan Laravel scheduler tepat satu kali setiap menit. Duplikasi cron dapat menggandakan dispatch job, sedangkan cron yang mati akan menghentikan seluruh proses di atas.

---

## 9. Baseline VPS terverifikasi

Audit read-only langsung melalui SSH selesai pada 26 Oktober 2026. Tidak ada secret yang disalin ke
dokumentasi.

| Item | Kandidat nilai | Tingkat kepastian |
| --- | --- | --- |
| User deployment | `deploy` | Tinggi |
| Host production | `202.155.136.47` | Tinggi |
| Production path | `/var/www/bloomery-office-production` | Tinggi |
| Staging path | `/var/www/bloomery-office-staging` | Terverifikasi |
| Production URL | `https://office.bloomery.co.id` | Tinggi |
| SSH port | `22` | Tinggi |
| Firewall | UFW aktif, port 22/80/443 terbuka | Berdasarkan pemeriksaan terdahulu |
| Fail2ban | Jail `sshd` aktif | Terverifikasi |

### 9.1 Runtime dan proses

| Komponen | Hasil audit |
| --- | --- |
| Sistem operasi | Ubuntu 24.04.5 LTS |
| PHP | 8.4.26; PHP-FPM memakai `/run/php/php8.4-fpm.sock` |
| Composer | 2.10.3 |
| Node.js / npm | 22.23.3 / 10.9.9 |
| Nginx | Konfigurasi valid; root masih menunjuk ke working tree lama masing-masing environment |
| Production worker | Satu worker Supervisor, queue database `default`, timeout 300 detik, max-time 3600 detik |
| Scheduler | Cron production menjalankan Laravel scheduler setiap menit |
| Redis | Service dan `app:check-redis` sehat; queue dan cache default aplikasi masih database |

### 9.2 Deployment lama

Forced-command gateway berada di `/home/deploy/bin/bloomery-github-deploy`. Alurnya memakai global
`flock`, memeriksa tracked working tree, mengambil commit terbaru, menyalakan maintenance mode,
membuat backup production, menjalankan merge, Composer, npm build, migration, permission sync,
optimization, queue restart, lalu membuka aplikasi.

Risiko yang terverifikasi:

- build berlangsung pada working tree aktif;
- kegagalan setelah maintenance mode berpotensi meninggalkan aplikasi tetap down;
- artifact CI tidak identik secara terjamin dengan hasil build server;
- tidak ada atomic symlink, post-deploy health gate, atau rollback aplikasi otomatis.

### 9.3 Permission, backup, dan kapasitas

| Item | Hasil audit |
| --- | --- |
| Owner aplikasi | `deploy:www-data`; direktori runtime memakai mode setgid 2775 |
| `.env` | File reguler `deploy:www-data` mode 640 pada working tree lama |
| Backup database | Harian pukul 03.30, gzip tervalidasi, retensi file 7 hari |
| Database production | Sekitar 405 MB pada saat pemeriksaan direktori MySQL |
| Database staging | Sekitar 210 MB pada saat pemeriksaan direktori MySQL |
| Replikasi MySQL | Tidak ada replica dan tidak ada proses Binlog Dump |
| Binlog | Aktif; auto-purge aktif; retensi dipersistenkan menjadi 604800 detik atau 7 hari |
| Disk setelah purge aman | `/var/lib/mysql` 46 GB; root 53/79 GB, 22 GB tersedia, penggunaan 72% |

Sebelum pembersihan, binlog mengisi mayoritas `/var/lib/mysql` dan root mencapai 87%. Backup gzip
terbaru divalidasi, status replica diperiksa, lalu binlog lebih tua dari tujuh hari dipurge melalui
MySQL. Tidak dilakukan pemangkasan lanjutan setelah ruang kosong kembali aman. Uji restore backup
ke staging tetap menjadi gate sebelum cutover production.

---

## 10. Checklist verifikasi VPS read-only

Jalankan dari terminal lokal:

```bash
ssh -i ~/.ssh/bloomery_vps deploy@202.155.136.47
```

Setelah masuk, jalankan blok berikut. Perintah ini hanya membaca informasi dan tidak menampilkan isi `.env`:

```bash
hostnamectl
id
php -v
composer --version
node --version
npm --version

find /var/www -maxdepth 2 -mindepth 1 -type d -print | sort

cd /var/www/bloomery-office-production
git remote -v
git branch --show-current
git rev-parse HEAD
git status --short
php artisan about --only=environment
php artisan schedule:list

crontab -l
sudo supervisorctl status
sudo systemctl status nginx --no-pager
sudo systemctl status php8.4-fpm --no-pager
sudo systemctl status redis-server --no-pager
```

Cari implementasi command deployment tanpa membaca secret:

```bash
type -a deploy
command -v deploy
grep -Rsn --exclude='*.env' --exclude='.env' "tracked working tree\|deploy production\|deploy staging" \
    ~/.local/bin /usr/local/bin /usr/local/sbin /home/deploy 2>/dev/null
```

Jika command `deploy` hanya tersedia untuk key GitHub melalui forced command SSH, periksa referensinya:

```bash
grep -n "command=" ~/.ssh/authorized_keys
```

Jangan menyalin public key lengkap ke dokumentasi. Cukup kirim bagian `command="..."` dan samarkan material key setelah tipe key.

Untuk Nginx dan Supervisor, kirim konfigurasi yang relevan setelah memastikan tidak ada password atau token:

```bash
sudo nginx -T
sudo find /etc/supervisor/conf.d -maxdepth 1 -type f -print
sudo grep -Rsn "bloomery\|queue:work" /etc/supervisor/conf.d
```

### 10.1 Informasi yang harus dikumpulkan

- document root Nginx production dan staging;
- socket PHP-FPM yang digunakan;
- lokasi script `deploy`;
- urutan command deploy saat ini;
- lokasi shared `.env` dan `storage`;
- status dan command Supervisor worker;
- jumlah worker serta queue yang dilayani;
- cron scheduler dan kemungkinan duplikat;
- owner/group folder aplikasi;
- proses backup database;
- jumlah dan ukuran release/backup yang disimpan;
- hasil `php artisan app:check-redis`;
- hasil `php artisan monitoring:run-checks --force`.

---

## 11. Gate sebelum implementasi pipeline baru

| Gate | Status | Syarat selesai |
| --- | --- | --- |
| G0. Repository baseline | Selesai | Workflow dan runtime tercatat |
| G1. GitHub baseline | Selesai | Ruleset aktif; Environment `staging` dan `production` tersedia |
| G2. VPS baseline | Selesai | Checklist bagian 10, deploy script, backup, dan kapasitas dikumpulkan |
| G3. Branch eksperimen diamankan | Selesai secara keputusan | Branch tidak di-merge apa adanya |
| G4. Working tree pekerjaan dipisahkan | Selesai | Commit CI dibuat terisolasi tanpa perubahan aplikasi |

Pipeline deployment baru tidak boleh menyentuh production sebelum G1–G4 selesai. Workflow CI Pull Request yang tidak melakukan deployment boleh dibuat lebih dahulu dari `main` terbaru.

---

## 12. Langkah implementasi berikutnya

Subproses yang sudah selesai:

1. [x] Buat branch baru dari `main` terbaru.
2. [x] Buat `.github/workflows/ci.yml` khusus `pull_request` dan `merge_group`.
3. [x] Pastikan CI baru tidak memiliki mekanisme `[skip full tests]`.
4. [x] Pecah Feature test menjadi empat shard paralel dan batch terbatas.
5. [x] Tambahkan job agregator stabil bernama `CI Required`.
6. [x] Uji melalui PR #1 dan squash-merge ke `main`.

Subproses berikutnya:

1. [x] Aktifkan Ruleset `main` dengan required check `CI Required`.
2. [x] Kumpulkan hasil checklist VPS read-only.
3. [x] Verifikasi scope GitHub Environment untuk staging dan production.
4. [x] Implementasikan artifact build, atomic staging deploy, health check, dan rollback di repository.
5. [x] Review dan merge PR Phase 2 tanpa mengaktifkan deploy staging.
6. [x] Bootstrap layout release staging dan forced-command gateway.
7. [x] Uji tiga release staging dan satu rollback.
8. [ ] Review, merge, dan validasikan production approval rehearsal serta promosi artifact yang sama pada Phase 3.

Perubahan pada langkah 2–8 harus menjadi commit CI-only. Jangan sertakan source aplikasi yang saat ini masih berubah di working tree.

---

## 13. Hasil implementasi CI-only

File berikut sudah berada di `main` melalui squash commit `d66410c9`:

| File | Fungsi |
| --- | --- |
| `.github/workflows/ci.yml` | CI untuk Pull Request, merge queue, dan eksekusi manual |
| `ops/ci/feature-shard.sh` | Pembagian seluruh Feature test ke empat job dan batch terbatas |

Workflow baru memiliki:

- job `Build and unit tests`;
- empat job Feature test paralel;
- artifact frontend yang digunakan bersama oleh seluruh shard;
- satu check agregat stabil bernama `CI Required`;
- action pihak ketiga yang dipin ke full commit SHA;
- token permission `contents: read`;
- concurrency yang membatalkan run PR lama ketika commit baru masuk;
- tidak menggunakan secret production;
- tidak memiliki kemampuan deployment.

Hasil verifikasi lokal:

| Pemeriksaan | Hasil |
| --- | --- |
| Syntax workflow YAML | Lulus |
| Syntax runner Bash | Lulus |
| Composer validation | Lulus |
| Frontend production build | Lulus |
| Unit tests | 154 lulus, 489 assertion |
| Cakupan pembagian Feature test | 222 dari 222 file terpilih |
| Duplikasi antar-shard | 0 |
| Sample runner shard | 19 test lulus, 69 assertion |
| `git diff --check` | Lulus |

Sharding bawaan Pest 4 sempat diuji, tetapi inventaris seluruh `tests/Feature` berhenti dengan exit code 255 pada suite proyek ini. Implementasi menggunakan pembagian kelompok file deterministik dan batch maksimal 20 file, mengikuti pola workflow lama yang sudah berjalan. Full Feature suite sudah tervalidasi melalui empat shard pada CI run #2.

Workflow deployment lama `.github/workflows/deploy.yml` tidak diubah. Oleh karena itu implementasi CI baru belum dapat memicu staging atau production.

### 13.1 Validasi Pull Request #1

Run CI pertama menemukan bahwa pembagian modulo per file mengubah kombinasi test dalam satu proses. `ClearBranchSalesShiftsCommandTest` kemudian mengalami benturan unique constraint dengan test lain pada batch yang sama. Ini merupakan efek urutan batch, bukan kegagalan build atau konfigurasi artifact.

Runner diperbaiki dengan mempertahankan kelompok berurutan maksimal 20 file persis seperti workflow lama, kemudian membagikan kelompok tersebut ke empat shard. Verifikasi lokal setelah koreksi:

- seluruh 222 file tetap terpilih tepat satu kali;
- distribusi shard menjadi 60, 60, 60, dan 42 file;
- kelompok kompatibilitas yang memuat `ClearBranchSalesShiftsCommandTest` lulus 176 test dengan 729 assertion.

CI run #2 selesai sukses dalam 7 menit 12 detik. Seluruh job berikut lulus:

- `Build and unit tests`;
- `Feature shard 1/4`;
- `Feature shard 2/4`;
- `Feature shard 3/4`;
- `Feature shard 4/4`;
- `CI Required`.

PR #1 kemudian di-squash ke `main` sebagai commit `d66410c9`. Workflow lama `Test and deploy` run #63 juga selesai sukses untuk `Test and build` serta `Deploy main`. Pemeriksaan `https://office.bloomery.co.id/up` setelah deployment menampilkan `Application up` dengan response render 21 ms.

Dengan bukti tersebut, subproses implementasi dan validasi CI selesai. Pada 25 Oktober 2026, Ruleset `Protect main` (`25804747`) kemudian diaktifkan untuk default branch. GitHub API mengonfirmasi bahwa branch `main` menerima aturan efektif `deletion`, `non_fast_forward`, `pull_request`, dan `required_status_checks`; check wajibnya adalah `CI Required`, strict up-to-date aktif, dan bypass admin dibatasi pada Pull Request. Phase 1 dinyatakan **selesai**.

### 13.2 Validasi Phase 2 atomic staging

PR #3 di-squash ke `main` sebagai commit `7a10c0ae` dan menambahkan workflow `Release staging`,
immutable artifact, checksum, manifest release, forced-command receiver, atomic symlink, health check,
serta rollback. Server staging kemudian menggunakan layout:

```text
/var/www/bloomery/staging/
├── current -> releases/<run>-<sha8>
├── incoming/
├── releases/
└── shared/
    ├── .env
    ├── health-url
    └── storage/
```

Nginx staging menunjuk ke `/var/www/bloomery/staging/current/public`. Endpoint `/up` dapat diakses
tanpa Basic Auth untuk health check, sedangkan halaman staging lain tetap mengembalikan HTTP 401.
PHP-FPM dapat membaca shared environment dan frontend manifest serta menulis ke shared storage.

Release pertama menemukan ketidaksesuaian kontrak monitoring: script mengirim
`migration_status=succeeded`, sedangkan aplikasi menerima `ran`. Migration, aktivasi release, Redis,
dan health check sudah berhasil; hanya pencatatan monitoring yang gagal. PR #4 (`8f1353f8`)
memperbaiki nilai tersebut, menambahkan regression test, dan menjadikan kegagalan monitoring sebagai
peringatan non-fatal setelah release sehat.

Bukti akhir Phase 2:

- release #2, #3, dan #4 berhasil berurutan;
- `current` mencapai `4-8f1353f8` dan health check lulus;
- rollback #4 ke #3 berhasil dan sehat;
- staging dikembalikan ke #4 dan kembali sehat;
- production belum dipindahkan ke atomic release;
- workflow deployment production lama tetap tersedia sebagai fallback.

Phase 2 dinyatakan **selesai**. Uji restore database staging dan konfigurasi approval environment
production tetap menjadi gate sebelum cutover production.

### 13.3 Implementasi repository Phase 3

Branch Phase 3 mengubah `release.yml` agar push protected `main` membangun satu artifact, mendeploy
atomic staging, lalu menawarkan job `Production approval rehearsal (no deploy)`. Job rehearsal:

- memakai environment `production` agar required reviewer GitHub berlaku;
- mengunduh artifact yang sama dari job build;
- memverifikasi checksum SHA-256 setelah staging sehat;
- tidak mengonfigurasi SSH dan tidak memiliki command deployment production;
- hanya aktif bila repository variable `PRODUCTION_APPROVAL_REHEARSAL_ENABLED=true`.

Workflow lama diubah menjadi fallback `workflow_dispatch` dengan pilihan environment dan alasan
insiden. Trigger push `main`/`staging` serta mekanisme `[skip full tests]` dihapus. Semua third-party
action pada fallback dipin ke full commit SHA. Contract test CI menolak bila release Phase 3 memuat
command `release production` atau fallback lama kembali mempunyai trigger push.

Phase 3 belum dapat dinyatakan selesai sampai required reviewer production dikonfigurasi, satu job
Waiting berhasil di-reject, satu rehearsal berhasil di-approve dan memverifikasi checksum, serta uji
restore database staging selesai.

---

## 14. Acceptance criteria tahap audit

- [x] Workflow aktif terdokumentasi.
- [x] Trigger, test, build, dan deployment command terdokumentasi.
- [x] Stack dan runtime Laravel terdokumentasi.
- [x] Health, queue, scheduler, Redis, dan monitoring capability terdokumentasi.
- [x] Kondisi branch dan Pull Request GitHub terdokumentasi.
- [x] Branch eksperimen lama diaudit dan diberi keputusan aman.
- [x] Tidak ada secret yang dibaca atau ditulis.
- [x] Tidak ada perubahan source aplikasi, schema, atau konfigurasi server; redeployment workflow lama berhasil.
- [x] Script deploy VPS berhasil dibaca.
- [x] Konfigurasi Nginx, Supervisor, cron, dan PHP-FPM production diverifikasi.
- [x] GitHub Ruleset dibuat dan aturan efektif branch `main` diverifikasi melalui GitHub API.
- [x] GitHub Environment diverifikasi.

Phase 1 CI Pull Request, baseline audit, dan Phase 2 atomic staging dinyatakan **selesai**. Langkah
berikutnya adalah Phase 3: mempromosikan artifact staging yang sama ke job production yang tertahan
approval, tanpa melakukan cutover production terlebih dahulu.
