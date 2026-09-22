# Laporan Phase 3 — Fondasi Shared Process Stock Card

## 1. Status

| Field | Nilai |
| --- | --- |
| Acuan | `docs/code-remediation-prd.md` Phase 3 |
| Tanggal | 22 September 2026 |
| Scope | Authorization dan branch scope Stock Card |
| Status | Selesai dan tervalidasi |
| Full suite | 750 test lulus, 0 gagal, 4.350 assertions |

Scope ini sengaja dibatasi pada Stock Card agar perubahan authorization dapat direview sebagai satu concern. Query database yang masih berada di Blade Purchasing, ERP, Content, dan Design sudah diaudit, tetapi belum dipindahkan dalam scope ini.

## 2. Masalah sebelum perubahan

Aturan akses Stock Card tersebar pada beberapa lokasi:

- `StockCardResource` memeriksa permission dan membatasi query berdasarkan cabang.
- `ViewStockCard` menentukan izin review Supervisor dan Finance.
- `StockCardEsbSynchronizer` mengulang aturan permission, cabang, status, dan larangan memproses laporan sendiri.
- Belum ada Policy untuk menjadi aturan server-side yang dapat digunakan bersama oleh Filament, service, dan API pada masa depan.

Risikonya adalah perubahan pada satu entry point tidak otomatis berlaku pada entry point lain.

## 3. Perubahan kode

### 3.1 StockCardPolicy

File baru: `app/Policies/StockCardPolicy.php`.

Policy menjadi sumber aturan untuk kemampuan berikut:

| Ability | Aturan |
| --- | --- |
| `viewAny` | Memerlukan permission `view stock cards` |
| `view` | Memerlukan permission view dan akses ke cabang record |
| `create` | Memerlukan permission `create stock cards` |
| `update` | Memerlukan permission edit dan akses cabang |
| `delete` | Memerlukan permission delete dan akses cabang |
| `deleteAny` | Memerlukan permission delete; record tetap dibatasi query cabang |
| `restore` | Mengikuti aturan update |
| `forceDelete` | Mengikuti aturan delete |
| `reviewAsSupervisor` | Record harus Supervisor Review, user memiliki permission review Supervisor, dapat mengakses cabang, dan bukan submitter kecuali mempunyai akses seluruh cabang |
| `reviewAsFinance` | Record harus Finance Review, user memiliki permission review Finance, dapat mengakses cabang, dan bukan submitter kecuali mempunyai akses seluruh cabang |
| `refreshEsb` | User dapat melihat record, bukan submitter kecuali mempunyai akses seluruh cabang, dan mempunyai permission reviewer sesuai status record |

Policy ditemukan otomatis oleh Laravel melalui konvensi `StockCard` → `StockCardPolicy`; tidak diperlukan registrasi manual.

### 3.2 Query scope cabang

`StockCard` memperoleh scope:

```php
StockCard::query()->accessibleTo($user)
```

Perilakunya:

- user `access_all_branches` atau `SUPERADMIN` menerima seluruh record;
- user lain hanya menerima record dari primary branch dan additional accessible branches;
- aturan memakai helper akses cabang existing pada `User`, sehingga pengecualian administrator tidak berubah.

### 3.3 StockCardResource

Resource sekarang:

- memakai `accessibleTo()` untuk query index;
- memakai Policy untuk `canViewAny()` dan `canView()`;
- memakai Policy untuk delete record dan bulk delete;
- mengembalikan query kosong bila tidak ada user terautentikasi.

Filter, URL, tabel, permission name, serta perilaku tampilan tidak diubah.

### 3.4 Review Supervisor dan Finance

`ViewStockCard` tidak lagi menyalin aturan review. Method UI berikut sekarang mendelegasikan keputusan ke Policy:

- `canReviewAsSupervisor()`;
- `canReviewAsFinance()`.

Method mutation masih melakukan pemeriksaan authorization sebelum transaction, sehingga menyembunyikan tombol bukan satu-satunya pengamanan.

### 3.5 Refresh Stock Movement ESB

`StockCardEsbSynchronizer::canRefresh()` sekarang memakai ability `refreshEsb` dari Policy. Pemeriksaan yang sama tetap dijalankan sebelum request ESB dan kembali dijalankan di dalam database transaction setelah record dikunci.

## 4. Perilaku yang dipertahankan

- `SUPERADMIN` tetap dapat melewati pembatasan melalui `Gate::before` existing.
- User dengan `access_all_branches` tetap dapat melihat seluruh cabang.
- User biasa tetap hanya melihat cabang yang dapat diakses.
- Submitter biasa tidak dapat mereview atau refresh laporannya sendiri.
- User akses seluruh cabang tetap memperoleh pengecualian review laporan sendiri sesuai implementasi sebelumnya.
- Supervisor hanya bertindak pada status `pending_supervisor`.
- Finance hanya bertindak pada status `pending_finance`.
- Permission lama tidak diganti atau di-rename.
- Tidak ada migration, perubahan schema, dependency, route, atau format response.

## 5. Test baru

File baru: `tests/Feature/StockCardPolicyTest.php`.

Enam characterization test ditambahkan:

1. Query hanya mengembalikan Stock Card dari cabang user.
2. User akses seluruh cabang memperoleh seluruh record.
3. View memerlukan permission dan akses cabang.
4. Delete memerlukan permission dan akses cabang.
5. Review Supervisor menjaga aturan status, cabang, dan submitter.
6. Refresh ESB menjaga aturan reviewer dan pengecualian akses seluruh cabang.

## 6. Validasi

### Test Stock Card terkait

```bash
php artisan test --compact \
  tests/Feature/StockCardTest.php \
  tests/Feature/StockCardApprovalWorkflowTest.php \
  tests/Feature/StockCardSettingsTest.php
```

Hasil: **66 test lulus, 293 assertions**.

```bash
php artisan test --compact tests/Feature/StockCardPolicyTest.php
```

Hasil: **6 test lulus, 13 assertions**.

### Formatter

```bash
vendor/bin/pint --dirty --format agent
```

Hasil: **lulus**.

### Full suite

```bash
php artisan test --compact
```

| Metrik | Hasil |
| --- | ---: |
| Total test | 750 |
| Lulus | 750 |
| Gagal | 0 |
| Assertions | 4.350 |
| Durasi | 329,262 detik |

## 7. File Phase 3

- `app/Policies/StockCardPolicy.php` — policy baru.
- `app/Models/StockCard.php` — scope `accessibleTo()`.
- `app/Filament/Helpdesk/Resources/StockCards/StockCardResource.php` — delegasi query dan authorization.
- `app/Filament/Helpdesk/Resources/StockCards/Pages/ViewStockCard.php` — delegasi izin review.
- `app/Services/StockCardEsbSynchronizer.php` — delegasi izin refresh ESB.
- `tests/Feature/StockCardPolicyTest.php` — characterization test policy dan branch scope.

`ViewStockCard.php` juga telah mempunyai perubahan kategori produk dari pekerjaan Stock Card sebelumnya. Bagian tersebut bukan perubahan authorization Phase 3 dan tidak diklaim sebagai hasil scope ini.

## 8. Temuan lanjutan

Empat Blade masih menjalankan query database untuk opsi filter:

- Purchase Requests: branch.
- Content Requests: branch.
- ERP Repair Requests: branch, ERP module, request type.
- Design Requests: branch dan design category.

Pemindahan query tersebut sebaiknya dilakukan per modul dengan test filter masing-masing. Dua view, Content dan Design, juga tidak ditemukan pada render-hook aktif saat audit sehingga usage perlu diverifikasi sebelum diubah atau dihapus.

## 9. Risiko tersisa

- Policy baru baru mencakup Stock Card; resource lain masih menggunakan permission check masing-masing.
- Branch filter option pada beberapa tabel lain masih dapat mengambil data langsung dari Blade.
- Perubahan Phase 2, Phase 3, dan perubahan Stock Card UI existing masih berada pada working tree yang sama dan belum di-commit.
