# RBAC — Role & Permission

Aplikasi menggunakan **Spatie Laravel Permission** untuk manajemen role dan permission.

## Daftar Role

| Role | Panel Akses | Fungsi |
|------|------------|--------|
| `super_admin` | Semua panel | Akses penuh tanpa batasan |
| `helpdesk_manager` | Helpdesk, Driver | Manajer helpdesk, approve request |
| `helpdesk_staff` | Helpdesk | Staff helpdesk operasional |
| `hr_staff` | Helpdesk, Casual | Staff HR, kelola casual staff |
| `casual_staff` | Casual | Pegawai casual |
| `driver` | Driver | Pengemudi, kelola trip |
| `technician` | Technician | Teknisi, tangani service request |

## Akses Panel per Role

| Role | Admin | Helpdesk | Casual | Driver | Technician |
|------|-------|---------|--------|--------|-----------|
| `super_admin` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `helpdesk_manager` | — | ✓ | — | ✓ | — |
| `helpdesk_staff` | — | ✓ | — | — | — |
| `hr_staff` | — | ✓ | ✓ | — | — |
| `casual_staff` | — | — | ✓ | — | — |
| `driver` | — | — | — | ✓ | — |
| `technician` | — | — | — | — | ✓ |

## Cara Kerja Guard

Setiap `PanelProvider` menggunakan method `auth()` dengan callback yang mengecek role user:

```php
// Contoh di HelpdeskPanelProvider
->auth(function (Panel $panel): bool {
    return auth()->user()?->hasAnyRole([
        'super_admin',
        'helpdesk_manager',
        'helpdesk_staff',
        'hr_staff',
    ]) ?? false;
})
```

User yang tidak memenuhi syarat akan di-redirect ke halaman login panel tersebut.

## Mengelola Role & Permission

### Via Panel Admin (`/admin`)

1. Login sebagai `super_admin`
2. Navigasi ke menu **Role & Permission**
3. Buat/edit role dengan permission yang diinginkan

### Via Panel Helpdesk (`/helpdesk`)

1. Login sebagai `super_admin` atau `helpdesk_manager`
2. Navigasi ke **Management Access → Role & Permission**

### Via Artisan (CLI)

```bash
# Assign role ke user
php artisan tinker --execute 'App\Models\User::find(1)->assignRole("driver");'

# Revoke role
php artisan tinker --execute 'App\Models\User::find(1)->removeRole("casual_staff");'

# List role user
php artisan tinker --execute 'print_r(App\Models\User::find(1)->getRoleNames()->toArray());'
```

## Form Template & Role

`HelpdeskFormTemplate` memiliki kolom `roles` (JSON array) yang mendefinisikan role mana yang bisa melihat dan membuat request menggunakan template tersebut. Ini terpisah dari RBAC panel — ini adalah filter di level template form.

Contoh: Template "Permintaan Servis AC" mungkin hanya terlihat oleh `hr_staff` dan `helpdesk_manager`.

## Seeder Role Default

Role-role di atas dibuat via seeder. Untuk melihat daftar role yang sudah ada:

```bash
php artisan tinker --execute 'print_r(Spatie\Permission\Models\Role::pluck("name")->toArray());'
```
