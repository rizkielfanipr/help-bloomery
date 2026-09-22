# Laporan Phase 2 — Baseline Test dan Isolasi Network

## Status

| Field | Nilai |
| --- | --- |
| Acuan | `docs/code-remediation-prd.md` Phase 2 |
| Baseline temuan | `docs/code-remediation-phase-1-audit.md` |
| Tanggal | 22 September 2026 |
| Fokus | Membuat baseline test valid, stabil, dan terisolasi dari network eksternal |
| Konfigurasi database test | SQLite in-memory |
| Konfigurasi memory test | 512 MB melalui `phpunit.xml` |

## Perubahan per scope

### 1. Guard network eksternal

`tests/Pest.php` menjalankan `Http::preventStrayRequests()` untuk seluruh test yang memakai application test case. Setiap akses HTTP eksternal kini wajib didaftarkan melalui `Http::fake()`. Test Product List yang sebelumnya bocor ke endpoint ESB diberi fake lokal pada skenario sidebar.

Dampak yang diharapkan: test langsung gagal bila kode baru mencoba mengakses ESB atau layanan eksternal tanpa fake.

### 2. Konfigurasi memory test

`phpunit.xml` menetapkan:

```xml
<ini name="memory_limit" value="512M"/>
```

Konfigurasi ini dipakai oleh perintah standar:

```bash
php artisan test --compact
```

Alasan: suite menjalankan alur PDF, image, dan attachment yang melampaui default PHP 128 MB. Penyimpanan konfigurasi di `phpunit.xml` mencegah perbedaan hasil antara `artisan test` dan pemanggilan Pest secara langsung.

### 3. Scaffold auth dan profile

Test Breeze lama diperbarui mengikuti kontrak aplikasi saat ini:

- Login dan logout diuji melalui panel Helpdesk Filament.
- Registrasi diuji melalui panel Casual beserta field aktif dan role `CASUAL_STAFF`.
- Route verification, password confirmation, password reset, dan standalone password update diuji sebagai endpoint legacy yang tidak tersedia.
- Root Helpdesk diuji mengarahkan guest ke login.
- Profile diuji melalui `ProfilePage` Casual: render data user, upload foto, penggantian file lama, logout, dan proteksi guest.

Requirement production tidak ditambahkan kembali hanya untuk memenuhi scaffold Breeze.

### 4. Kontrak role dan Technician

- Nama role pada test diselaraskan dengan seed production: `CASUAL_STAFF`.
- Test request Technician tidak lagi mengisi `scheduledDate`, karena jadwal ditentukan teknisi setelah request masuk.
- Assertion copy QR mengikuti UI aktif.
- Test yang memicu distribusi request Technician melakukan seed role/permission yang memang tersedia pada production.

### 5. Kontrak BOM dan ESB

Test `EsbCoreService` kini memastikan dua kontrak sekaligus:

1. `bomTypeID` default menjadi Assembly (`1`) bila caller tidak mengirim tipe.
2. Tipe eksplisit, termasuk Menu (`3`), tetap dipertahankan.

Implementasi production `??= 1` dipertahankan karena pemaksaan tipe `1` akan merusak pembuatan BOM Menu. Ini menghindari perubahan requirement production untuk memuaskan expectation test yang stale.

## Klasifikasi temuan baseline

| Temuan | Klasifikasi | Penyelesaian |
| --- | --- | --- |
| Route Breeze auth/profile tidak tersedia | Stale test scaffold | Test diarahkan ke panel aktif atau menegaskan endpoint legacy tidak tersedia |
| Product List mengakses ESB nyata | Test isolation defect | Tambah HTTP fake dan global stray-request guard |
| `bomTypeID` selalu diharapkan `1` | Stale/terlalu sempit; bertentangan dengan BOM Menu aktif | Test menguji default Assembly dan mempertahankan tipe eksplisit |
| Role `casual_staff` tidak ada | Stale fixture | Gunakan role seeded `CASUAL_STAFF` |
| `scheduledDate` diisi requester | Stale workflow expectation | Hapus dari input requester; jadwal tetap tanggung jawab teknisi |
| Label `Scan QR Asset` | Stale UI expectation | Selaraskan dengan copy aktif |
| Root diharapkan HTTP 200 | Stale route expectation | Uji redirect guest ke login Helpdesk |
| Default memory 128 MB | Test runner configuration | Simpan 512 MB di `phpunit.xml` |

## Validasi

Kelompok test dijalankan terpisah sebelum full suite:

```bash
php artisan test --compact tests/Feature/Auth tests/Feature/ExampleTest.php
php artisan test --compact tests/Feature/PositionWarRegistrationTest.php tests/Feature/RequestCodeGenerationTest.php tests/Feature/WhatsappCtaTest.php tests/Feature/ServiceRequestWorkflowTest.php
php artisan test --compact tests/Feature/EsbCoreServiceTest.php tests/Feature/ProductListTest.php
php artisan test --compact tests/Feature/EsbCoreServiceTest.php tests/Feature/BomRecipePageTest.php tests/Feature/ProfileTest.php
php artisan test --compact tests/Feature/Auth tests/Feature/ProfileTest.php
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Hasil full suite final:

| Metrik | Hasil |
| --- | ---: |
| Total test | 744 |
| Lulus | 744 |
| Gagal | 0 |
| Assertions | 4.337 |
| Durasi | 325,601 detik |

Perintah `php artisan test --compact` selesai tanpa request network liar dan tanpa kegagalan memory.

## Test yang masih gagal

Tidak ada. Full suite final lulus 744/744, sehingga tidak ada kegagalan tersisa untuk diklasifikasikan.
