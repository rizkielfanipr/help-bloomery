# Setup & Instalasi

Panduan menjalankan Help Bloomery secara lokal. Deployment staging/production dijelaskan di [Release Pipeline](operations/ci-cd/release-pipeline.md).

## Persyaratan

| Kebutuhan | Versi |
|-----------|-------|
| PHP | 8.4 |
| MySQL | 8.0+ |
| Node.js | 22 (dipakai CI) |
| Composer | 2.x |
| Redis | Opsional; default `.env.example` memakai driver `database` |

Di macOS, proyek ini dijalankan dengan Laravel Herd di `https://help-bloomery.test`; tidak perlu `php artisan serve`.

## Instalasi

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Atur koneksi database di .env, lalu:
php artisan migrate --seed

npm run build
```

`php artisan migrate --seed` menjalankan `DatabaseSeeder`: role dan permission, akun contoh (lihat `AllUsersSeeder`), dan data demo untuk beberapa modul. Seeder demo hanya untuk lingkungan lokal; jangan dijalankan di staging atau production.

Login memakai **username**, bukan email.

## Variabel environment

`.env.example` hanya memuat sebagian variabel. Variabel lain dibaca dari `config/*.php` dengan nilai default yang aman. Jangan menyimpan nilai asli credential di repository.

| Kelompok | Variabel | Keterangan |
|---|---|---|
| Aplikasi | `APP_URL`, `APP_DOMAIN` | `APP_DOMAIN` membuat Employee App melayani root domain tersebut |
| Domain panel | `DRIVER_DOMAIN`, `TECHNICIAN_DOMAIN` | Opsional; tanpa nilai, panel memakai path `/driver` dan `/technician` |
| Database | `DB_*` | MySQL |
| Queue, cache, session | `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `REDIS_*` | Lihat [Redis untuk Production](operations/production/redis.md) |
| File | `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_URL` | Disk `b2` (Cloudflare R2) |
| | `RND_PROJECT_TASK_ATTACHMENT_DISK`, `RND_MATERIAL_SAMPLE_DISK`, `SALES_REPORT_EVIDENCE_DISK` | Disk per jenis lampiran (default `b2`) |
| ESB | `ESB_BASE_URL`, `ESB_TOKEN_{COMCODE}` | ESB corev1 (sales, stock); token statis per comcode, tanpa prefix `Bearer` |
| | `ESB_CORE_BASE_URL`, `ESB_CORE_USERNAME`/`PASSWORD`, `ESB_CORE_{COMCODE}_USERNAME`/`PASSWORD`, `ESB_CORE_TIMEOUT`, `ESB_CORE_CONNECT_TIMEOUT`, `ESB_CORE_TOKEN_TTL` | ESB Core (Master Product, BOM, PO/GR, Item Journal) |
| | `ESB_MASTER_PRODUCT_BASE_URL`, `ESB_MASTER_PRODUCT_TOKEN` | Master Product |
| | `ESB_PRICELIST_MODE` | `manual` (satu-satunya mode yang didukung saat ini) |
| Telegram | `TELEGRAM_NOTIFICATIONS_ENABLED`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_IT_REVIEW_CHAT_ID`, `TELEGRAM_PURCHASING_CHAT_ID`, `TELEGRAM_QUEUE` | Nonaktif secara default |
| Monitoring | `MONITORING_*` | Environment, error recording, alert email, SSL check, secret callback deployment — lihat `config/monitoring.php` |
| Ordering | `ORDERING_*` | Jam sinkron, kategori, lokasi stok, tipe transaksi — lihat `config/ordering_recommendation.php` |

Mapping cabang ke ESB (branch code, branch ID, comcode, label) diatur di menu **Master → Branch**, bukan di `.env`.

Setelah mengubah `.env` di server:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```

## Development

```bash
# Server, queue listener, log (Pail), dan Vite sekaligus
composer run dev
```

Di Herd, cukup jalankan queue dan Vite: `php artisan queue:listen` dan `npm run dev`.

## Testing

```bash
php artisan test --compact                       # Unit + Feature
php artisan test --compact --testsuite=Unit
php artisan test --compact --filter=NamaTest
bash ops/ci/feature-shard.sh                     # Feature per batch seperti CI (SHARD_INDEX, SHARD_TOTAL)
bash ops/ci/check-doc-links.sh                   # Validasi link dokumentasi
```

Seluruh suite Feature sebaiknya dijalankan per batch seperti CI; menjalankan semuanya dalam satu proses dapat kehabisan memori.

## Scheduler dan queue

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Jalankan juga queue worker (`php artisan queue:work`). Daftar scheduled task ada di [Arsitektur — Otomatisasi](architecture/overview.md#otomatisasi-scheduler). Status scheduler dan queue dapat dipantau di menu System Monitoring.

## Permission

Setelah menambah permission di `config/permissions.php`, jalankan:

```bash
php artisan permissions:sync
```

Deploy menjalankan perintah ini otomatis. Lihat [RBAC](architecture/rbac.md).
