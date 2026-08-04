# Setup & Instalasi

## Persyaratan

| Kebutuhan | Versi |
|-----------|-------|
| PHP | 8.4 |
| MySQL | 8.0+ |
| Node.js | 20+ |
| Composer | 2.x |

## Instalasi

```bash
# 1. Clone repo dan install dependensi
composer install
npm install

# 2. Salin file environment
cp .env.example .env
php artisan key:generate

# 3. Konfigurasi database di .env lalu jalankan migrasi
php artisan migrate --seed

# 4. Build asset frontend
npm run build

# 5. Buat symbolic link storage
php artisan storage:link
```

## Variabel Environment Penting

### Aplikasi

```env
APP_NAME="Help Bloomery"
APP_ENV=local
APP_URL=http://help-bloomery.test
```

### Database

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=help_bloomery
DB_USERNAME=root
DB_PASSWORD=
```

### Domain Panel (Opsional)

Setiap panel dapat dikonfigurasi dengan domain/subdomain terpisah:

```env
CASUAL_DOMAIN=casual.help-bloomery.test
DRIVER_DOMAIN=driver.help-bloomery.test
TECHNICIAN_DOMAIN=technician.help-bloomery.test
```

Jika tidak diset, semua panel diakses melalui `APP_URL` dengan path masing-masing.

### Storage

```env
FILESYSTEM_DISK=public
```

### Token ESB per Comcode

Token ESB disimpan per comcode melalui environment server. Jangan menyimpan token
asli di repository.

```env
ESB_TOKEN_BLSS=
```

Branch yang memakai token tersebut harus memiliki `ESB Comcode` bernilai `BLSS`
dan `ESB Branch Code` sesuai kode cabang resmi dari ESB. Isi token mentah tanpa
prefix `Bearer`; aplikasi menambahkan prefix tersebut saat mengirim request.

Setelah menambah atau mengganti token di server, muat ulang konfigurasi:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```

## Development

```bash
# Jalankan semua service sekaligus (server + queue + vite)
composer run dev

# Atau manual:
php artisan serve
php artisan queue:listen
npm run dev
```

## Testing

```bash
# Jalankan semua test
php artisan test --compact

# Filter test tertentu
php artisan test --compact --filter=CasualClockTest
```

## Scheduled Commands

Tambahkan cron berikut ke server production:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Daftar Scheduled Task

| Command | Jadwal | Fungsi |
|---------|--------|--------|
| `AutoCompleteWarrantyCommand` | Harian | Auto-complete service request yang masa garansinya telah berakhir |

## Seeder & User Default

Setelah `php artisan migrate --seed`:

| Role | Email | Password |
|------|-------|----------|
| Super Admin | Lihat DatabaseSeeder | (lihat seeder) |

Untuk membuat super admin secara manual:

```bash
php artisan tinker --execute '
$user = App\Models\User::create([
    "name" => "Super Admin",
    "email" => "admin@bloomery.test",
    "password" => bcrypt("password"),
]);
$user->assignRole("super_admin");
'
```
