# Redis untuk Production

Redis digunakan untuk mengurangi koneksi MySQL dari cache, session, dan queue.
Jangan mengubah driver sebelum koneksi Redis server berhasil diuji.

## 1. Periksa Redis

Pastikan ekstensi dan layanan Redis tersedia:

```bash
php -m | grep -i redis
redis-cli ping
php artisan app:check-redis
```

Semua pemeriksaan harus berhasil dan mengembalikan `PONG`/status terhubung.

## 2. Aktifkan cache dan session

Ubah `.env` production:

```env
APP_ENV=production
APP_DEBUG=false

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=default

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

Kemudian:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan app:check-redis
```

Login ulang diperlukan karena session lama berada di database.

## 3. Aktifkan queue

Aktifkan hanya setelah worker Redis dikonfigurasi:

```env
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90
```

Restart worker setelah deployment:

```bash
php artisan queue:restart
```

Gunakan satu proses worker yang dikelola Supervisor/systemd. Pastikan tidak ada
cron atau worker duplikat yang menjalankan queue yang sama.

## 4. Deployment permission

Permission tidak lagi disinkronkan pada setiap request. Jalankan saat deployment:

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan permission:cache-reset
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```

Gunakan binary PHP 8.4 yang tersedia di server untuk seluruh perintah tersebut.
