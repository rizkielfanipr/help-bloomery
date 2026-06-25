# Modul Helpdesk

Sistem tiket berbasis **form template dinamis**. Admin dapat membuat jenis-jenis form request tanpa coding — field, validasi, status, dan akses role semuanya dikonfigurasi via UI.

## Konsep Utama

```
HelpdeskFormTemplate
  ├── name, icon, color — tampilan di daftar template
  ├── statuses (json) — daftar status custom (misal: "Diproses", "Menunggu Spare Part")
  ├── roles (json) — role yang bisa buat request via template ini
  └── HasMany: HelpdeskFormField
        ├── label, type (FormFieldType)
        ├── is_required, hint, placeholder
        └── options (json) — untuk tipe select/radio

HelpdeskRequest
  ├── Dibuat user berdasarkan HelpdeskFormTemplate
  ├── data (json) — isian semua field template
  ├── status — salah satu nilai dari template.statuses
  └── assignee_id — staff yang menangani
```

## Alur Request

```
Staff/User membuat request:
  → Pilih template form (sesuai role akses)
  → Isi form field yang sudah didefinisikan
  → Submit → HelpdeskRequest dibuat (status = status pertama dari template)
          │
          ▼
Helpdesk staff (panel Helpdesk → Semua Permintaan):
  → Lihat semua request masuk
  → Assign ke staff/diri sendiri
  → Update status
  → Tambah catatan
  → Resolve (resolved_at = now)
```

## Form Field Types (`FormFieldType` Enum)

Field yang bisa ditambahkan ke template form. Tipe menentukan input UI yang ditampilkan saat user mengisi request.

## Template Permission Matrix

Komponen kustom `TemplatePermissionsMatrix` (`app/Filament/Forms/Components/TemplatePermissionsMatrix.php`) adalah UI matrix untuk mengatur role mana yang bisa melihat/menggunakan template. Tampil sebagai tabel checkboxes di halaman edit template.

## Manajemen di Panel Helpdesk

### Template Form

**Helpdesk → Template Form** (`FormTemplateResource`)

- Buat template baru: nama, ikon, warna
- Tambah field: label, tipe, wajib/tidak, hint
- Atur status custom
- Atur role akses via Permission Matrix

### Semua Permintaan

**Helpdesk → Semua Permintaan** (`HelpdeskRequestResource`)

Tampilan list semua request dengan filter status, departemen, template, dan assignee.

Halaman view request menampilkan isian form lengkap, timeline aktivitas (via Spatie Activity Log), dan form untuk update status/catatan.

## Activity Log

`HelpdeskRequest` menggunakan trait `LogsActivity` dari Spatie. Setiap perubahan status, assignee, atau notes tercatat otomatis di tabel `activity_log`.

## Observer

`HelpdeskFormTemplateObserver` memantau perubahan pada template. Diregistrasi di service provider.

## Tabel Database Terlibat

```
helpdesk_form_templates
helpdesk_form_fields
helpdesk_requests
activity_log (activity terkait HelpdeskRequest)
```

---

## Cara Menambah Menu Baru ke Sidebar Helpdesk

> **PENTING:** Sidebar helpdesk adalah **custom hardcoded view**, bukan auto-discovery Filament. Setiap menu baru **wajib didaftarkan manual** di:
>
> `resources/views/vendor/filament-panels/components/layout/index.blade.php`

### Struktur `$navGroups`

File ini mendefinisikan array `$navGroups` di bagian `@php`. Setiap group punya:

```php
[
    'id'    => 'finance',          // ID unik untuk Alpine.js toggle
    'label' => 'Finance',          // Label group di sidebar
    'icon'  => 'banknote',         // Lucide icon name (bukan Heroicon)
    'items' => [
        [
            'label'  => 'Sales Report',
            'icon'   => 'bar-chart-2',                                        // Lucide icon
            'href'   => $r('filament.helpdesk.resources.sales-reports.index'), // route name
            'active' => request()->is('helpdesk/sales-reports*'),              // active state
        ],
    ],
],
```

### Langkah Menambah Sub-menu Baru

1. **Cari group yang sesuai** di `$navGroups` (atau buat group baru)
2. **Tambah item** ke array `'items'` dengan format di atas
3. **Tambah auto-open rule** di bagian `$initialOpen`:

```php
if (str_contains($path, 'sales-report') || str_contains($path, 'payment-method')) {
    $initialOpen[] = 'finance';
}
```

4. **Gunakan Lucide icon name** — bukan Heroicon. Cek di https://lucide.dev/icons/
5. **Route name** bisa dicek dengan `php artisan route:list --name=helpdesk`

### Icon Penting (Lucide)

| Keperluan      | Lucide Name        |
|----------------|--------------------|
| Kalender       | `calendar-days`    |
| Grafik/Chart   | `bar-chart-2`      |
| Dokumen        | `file-text`        |
| Keuangan       | `banknote`         |
| Kartu bayar    | `credit-card`      |
| Pengguna       | `user` / `users`   |
| Pengaturan     | `settings`         |

### Mengapa Tidak Auto-Register?

Sidebar ini adalah view yang di-publish (`vendor/filament-panels`) dan sudah di-kustomisasi total dengan desain kustom (logo, Lucide icons, Alpine.js state). Filament auto-discovery tetap berjalan (routes, Livewire components), tapi **rendering sidebar sepenuhnya dikontrol file ini**.

Jika ingin kembali ke auto-discovery Filament: hapus file ini dan Filament akan render sidebar default-nya.
