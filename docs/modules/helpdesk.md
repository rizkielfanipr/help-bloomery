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
