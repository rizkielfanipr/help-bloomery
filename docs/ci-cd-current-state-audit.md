# Audit Baseline CI/CD Help Bloomery

## 1. Identitas audit

| Field | Nilai |
| --- | --- |
| Tanggal audit | 25 Oktober 2026 |
| Repository | `rizkielfanipr/help-bloomery` |
| Baseline commit | `aba7a2c049ff113ebe63cc77aa724dfd418ef662` |
| Branch lokal | `main` |
| Status | Audit repository dan GitHub selesai; verifikasi VPS masih menunggu akses read-only |
| Dokumen target | `docs/ci-cd-release-pipeline.md` |

Audit ini adalah implementasi tahap pertama dari pembangunan ulang CI/CD. Tujuannya adalah mencatat kondisi nyata sebelum workflow, server, atau aturan GitHub diubah.

Audit ini tidak mengotorisasi deployment, perubahan konfigurasi VPS, perubahan GitHub Ruleset, penghapusan branch, atau pengubahan secret.

---

## 2. Ringkasan hasil

| Area | Status | Hasil utama |
| --- | --- | --- |
| Repository | Selesai | Hanya ada satu workflow aktif: `.github/workflows/deploy.yml` |
| Laravel runtime | Selesai | PHP 8.4, Laravel 13.8, Filament 5.6, Livewire 4.3 |
| Test pipeline | Selesai | Unit test dan seluruh Feature test berjalan serial setelah push |
| Deployment | Selesai sebagian | GitHub mengirim `deploy production` atau `deploy staging`; implementasi command berada di VPS |
| GitHub repository | Selesai | Default branch `main`, repository public, belum ada riwayat Pull Request |
| GitHub branches | Selesai | `main`, `staging`, `feature/ci-cd-delivery-flow`, dan satu fix branch |
| GitHub Ruleset | Berdasarkan audit sebelumnya | Belum tersedia; harus diverifikasi lagi sebelum aktivasi |
| VPS | Tertunda | SSH read-only gagal karena autentikasi key non-interaktif ditolak |
| Monitoring | Selesai | Health `/up`, Redis check, monitoring checks, dan deployment recorder tersedia |
| Queue dan scheduler | Selesai dari kode | Queue memakai database secara default; scheduler memiliki tugas operasional penting |

Kesimpulan tahap pertama:

1. Workflow sekarang belum menjadi gate sebelum kode masuk `main`.
2. Implementasi deployment yang sebenarnya masih berada di VPS dan belum version-controlled.
3. Branch eksperimen CI/CD lama tidak boleh di-merge apa adanya karena berbasis commit yang tertinggal jauh dari `main`.
4. Perubahan paling aman berikutnya adalah membuat workflow CI khusus Pull Request dari `main` terbaru, tanpa mengubah deployment production terlebih dahulu.

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
| `docs/production-redis.md` | Pedoman Redis production | Aktif |
| `docs/ci-cd-release-pipeline.md` | Desain target pipeline baru | Draft implementasi |

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

## 9. Baseline VPS yang sudah diketahui

Informasi berikut berasal dari histori operasional dan file referensi, tetapi tetap harus diverifikasi langsung:

| Item | Kandidat nilai | Tingkat kepastian |
| --- | --- | --- |
| User deployment | `deploy` | Tinggi |
| Host production | `202.155.136.47` | Tinggi |
| Production path | `/var/www/bloomery-office-production` | Tinggi |
| Staging path | `/var/www/bloomery-office-staging` | Sedang; muncul pada branch eksperimen |
| Production URL | `https://office.bloomery.co.id` | Tinggi |
| SSH port | `22` | Tinggi |
| Firewall | UFW aktif, port 22/80/443 terbuka | Berdasarkan pemeriksaan terdahulu |
| Fail2ban | Jail `sshd` aktif | Berdasarkan pemeriksaan terdahulu |

Percobaan audit SSH otomatis pada 25 Oktober 2026 menghasilkan `Permission denied (publickey)`. Tidak ada perubahan server yang dilakukan.

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
| G1. GitHub baseline | Selesai sebagian | Environment, Ruleset, dan scope secret diverifikasi di UI |
| G2. VPS baseline | Tertunda | Checklist bagian 10 dikumpulkan |
| G3. Branch eksperimen diamankan | Selesai secara keputusan | Branch tidak di-merge apa adanya |
| G4. Working tree pekerjaan dipisahkan | Tertunda | Branch/worktree CI bersih dari perubahan aplikasi |

Pipeline deployment baru tidak boleh menyentuh production sebelum G1–G4 selesai. Workflow CI Pull Request yang tidak melakukan deployment boleh dibuat lebih dahulu dari `main` terbaru.

---

## 12. Langkah implementasi berikutnya

Urutan yang direkomendasikan:

1. Kumpulkan hasil checklist VPS read-only.
2. Buat branch baru dari `main` terbaru, misalnya `feature/ci-cd-pull-request-gate-v2`.
3. Buat `.github/workflows/ci.yml` khusus `pull_request` dan `merge_group`.
4. Hapus mekanisme `[skip full tests]` dari CI baru.
5. Pecah Feature test menjadi empat shard paralel secara deterministik dan jalankan setiap shard dalam batch terbatas.
6. Tambahkan job agregator stabil bernama `CI Required`.
7. Uji CI melalui Pull Request tanpa mengubah deployment lama.
8. Setelah stabil, aktifkan Ruleset `main` dengan required check `CI Required`.
9. Baru lanjutkan artifact release, staging, production approval, atomic deployment, dan rollback.

Perubahan pada langkah 2–8 harus menjadi commit CI-only. Jangan sertakan source aplikasi yang saat ini masih berubah di working tree.

---

## 13. Hasil implementasi CI-only

File berikut sudah dibuat pada working tree lokal dan belum di-commit atau dipush:

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

Sharding bawaan Pest 4 sempat diuji, tetapi inventaris seluruh `tests/Feature` berhenti dengan exit code 255 pada suite proyek ini. Implementasi menggunakan pembagian file deterministik dan batch maksimal 20 file, mengikuti pola workflow lama yang sudah berjalan. Full Feature suite belum dijalankan lokal; validasi tersebut dilakukan saat workflow pertama dijalankan di GitHub.

Workflow deployment lama `.github/workflows/deploy.yml` tidak diubah. Oleh karena itu implementasi CI baru belum dapat memicu staging atau production.

---

## 14. Acceptance criteria tahap audit

- [x] Workflow aktif terdokumentasi.
- [x] Trigger, test, build, dan deployment command terdokumentasi.
- [x] Stack dan runtime Laravel terdokumentasi.
- [x] Health, queue, scheduler, Redis, dan monitoring capability terdokumentasi.
- [x] Kondisi branch dan Pull Request GitHub terdokumentasi.
- [x] Branch eksperimen lama diaudit dan diberi keputusan aman.
- [x] Tidak ada secret yang dibaca atau ditulis.
- [x] Tidak ada perubahan pada production.
- [ ] Script deploy VPS berhasil dibaca.
- [ ] Konfigurasi Nginx, Supervisor, cron, dan PHP-FPM production diverifikasi.
- [ ] GitHub Environment dan Ruleset diverifikasi dari UI.

Tahap audit dinyatakan **selesai sebagian dan aman untuk melanjutkan CI-only**. Tahap deployment tetap tertahan sampai item VPS dan GitHub Environment yang belum terverifikasi diselesaikan.
