# Master Data

Data induk yang dipakai lintas modul: brand, branch (termasuk mapping ESB, shift, dan lokasi absen), employee, region penjualan, dan template WhatsApp. Kondisi per 27 Oktober 2026.

## Lokasi

| Menu (Back Office → Master) | Permission |
|---|---|
| Brand | `view brands` (+ create/edit/delete) |
| Branch | `view branches` (+ create/edit/delete); shift: `edit branch shifts` |
| Employee | `view employees` (+ create/edit/delete) |
| WhatsApp | `edit whatsapp settings` |

Resource **Region Penjualan** (`SalesRegionResource`) terdaftar dan memiliki permission sendiri, tetapi tidak tampil di sidebar.

## Brand

Nama brand dan jumlah cabang. Brand dipakai oleh branch, SOP Store, Memo Internal, dan filter analitik.

## Branch

| Bagian | Isi |
|---|---|
| Informasi Branch | Brand, nama, alamat, status aktif |
| Kode ESB | Satu atau beberapa pasangan ESB (branch code, branch ID, comcode) dengan **label** `DINE IN`, `TAKEAWAY`, atau lainnya; waktu sinkron terakhir; dan **Sumber Stock Card & Memo Internal**, yaitu satu mapping eksplisit yang juga dipakai Store Sales Order |
| Shift Basket Size | Daftar shift berurutan (nama, jam mulai, jam selesai) untuk Sales Report dan Basket Size |
| Lokasi Absen | Koordinat, radius, dan kewajiban validasi lokasi untuk absensi |

Label `DINE IN`/`TAKEAWAY` menentukan pemisahan rekonsiliasi Sales Report. Mapping berlabel lain tidak dipakai di Sales Report.

## Employee

ID employee, nama, posisi, branch, dan status aktif. Dipakai sebagai daftar staff in charge pada Sales Report dan Stock Card serta perhitungan basket size per employee.

## Region Penjualan

Nama, kode, urutan, dan status aktif region, dipakai untuk harga regional dan proyeksi penjualan produk R&D.

## WhatsApp

Template pesan per modul (dengan placeholder) dan PIC tujuan. Template dipakai `WhatsappCtaBuilder` untuk membuat tautan WhatsApp setelah pengajuan di Employee App, misalnya Permintaan ERP, Req. Teknisi, Purchasing, dan Konten. Aplikasi tidak mengirim pesan WhatsApp lewat API.

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{Brands,Branches,Employees,SalesRegions}/`, `app/Filament/Helpdesk/Pages/WhatsappSettingsPage.php` |
| Logika | `app/Services/{EsbBranchSyncService,WhatsappCtaBuilder}.php` |
| Model | `app/Models/{Brand,Branch,BranchEsbCode,BranchSalesShift,Employee,SalesRegion,WhatsappNotificationSetting}.php` |
| Test | `tests/Feature/{Branch,EsbBranch}*Test.php` |

## Data

`Brand`, `Branch`, `BranchEsbCode`, `BranchSalesShift`, `Employee`, `SalesRegion`, `WhatsappNotificationSetting`, `AppSetting`. Lihat [Data Model](../../architecture/data-model.md#platform--master-data).

## Role bawaan

`SUPERVISOR_STORE` mengelola Employee, melihat Branch, dan mengubah shift branch.
