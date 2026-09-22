# Audit Phase 1 — Baseline dan Peta Kode

## 1. Status audit

| Field | Nilai |
| --- | --- |
| Tanggal | 22 September 2026 |
| Acuan | `docs/code-remediation-prd.md` Phase 1 |
| Status | Selesai sebagai baseline awal; perlu diperbarui bila repository berubah |
| Jenis pekerjaan | Read-only audit dan dokumentasi; tidak memindahkan atau menghapus kode |
| Branch | `main` |

Saat audit dimulai, working tree sudah berisi perubahan Stock Card serta dua dokumen PRD yang belum di-commit. Audit ini tidak mengubah file aplikasi tersebut. Perubahan existing harus diselesaikan/di-commit terpisah sebelum Phase 2 agar baseline test tidak tercampur.

## 2. Ringkasan eksekutif

Repository mempunyai safety net yang cukup besar, tetapi belum hijau secara penuh dan fondasi background processing belum aktif. Risiko tertinggi bukan jumlah file, melainkan konsentrasi tanggung jawab pada beberapa Page/Service besar, request langsung ke ESB, queue production lokal yang masih `sync`, tidak adanya Job dan Policy, state Livewire besar, serta query database pada Blade.

Prioritas kode yang direkomendasikan:

1. Stabilkan test suite dan pisahkan test legacy/scaffold yang tidak sesuai route aktual.
2. Bentuk authorization baseline sebelum memindahkan kode karena folder Policy belum ada.
3. Standardisasi ESB client dan cegah network request nyata dari test.
4. Aktifkan pola Job/queue, dimulai dari operasi read-only Stock Movement.
5. Refactor Stock Card agar kategori aktif saja berada pada state Livewire.
6. Pecah hotspot R&D dan analytics setelah fondasi bersama stabil.

## 3. Inventaris repository

| Area | Jumlah/temuan |
| --- | ---: |
| File PHP di `app` | 553 |
| Blade view | 201 |
| Test file PHP | 101 |
| Pest test terdeteksi secara statis | 724 |
| Test dieksekusi penuh | 744 |
| Route non-vendor | 261 |
| Model | 106 |
| Migration | 236 |
| Operasi `Schema::create/table` | 424 |
| Service | 28 |
| Action | 11 |
| Job | 0 |
| Policy | 0 |
| Enum | 19 |
| HTTP Controller | 28 |
| Livewire class di `app/Livewire` | 1 |
| Filament class dengan public array | 35 |
| Filament class dengan `#[Computed]` | 35 |
| File aplikasi memakai `Http::` | 6 |
| Blade dengan query database langsung | 4 |

## 4. Panel dan discovery

Lima provider panel masih aktif:

| Provider | ID/path | Discovery utama |
| --- | --- | --- |
| `AdminPanelProvider` | `admin` / `/admin` | `App\Filament\Resources`, `Pages`, `Widgets` |
| `HelpdeskPanelProvider` | `helpdesk` / `/` | Helpdesk resources/pages/widgets dan shared Resources |
| `CasualPanelProvider` | `casual` / domain atau `/casual` | Casual resources/pages/widgets |
| `DriverPanelProvider` | `driver` | Driver resources/pages/widgets |
| `TechnicianPanelProvider` | `technician` | Technician resources/pages/widgets |

Implikasi:

- Target dua panel pada PRD refactoring belum menjadi kondisi aktual.
- Shared `App\Filament\Resources` ditemukan oleh Admin dan Helpdesk, sehingga pemindahan berisiko duplikasi/hilang discovery.
- Driver/Technician mempunyai file paralel dengan Casual pada beberapa flow; konsolidasi perlu characterization test route dan permission terlebih dahulu.
- Phase modularisasi tidak boleh dimulai dengan menghapus provider.

## 5. Hotspot ukuran kode

### PHP terbesar

| File | Baris | Risiko utama |
| --- | ---: | --- |
| `ViewProjectProductPage.php` | 2.524 | BOM, instruction, picker, material, ESB, export PIN, dan state UI dalam satu Page |
| `BulkDataPromotionPage.php` | 1.104 | Form, picker, submit, pagination, dan integrasi promotion |
| `EsbService.php` | 938 | Product, sales, payment, shift, promotion, material usage |
| `SalesInformationPage.php` | 911 | Filter, pagination, comparison, dan analytics |
| `ViewProject.php` | 901 | Project, product, document, pricing, projection |
| `EsbPromotionService.php` | 674 | Banyak catalog/pagination serta mutation promotion |
| `SalesReportShiftPage.php` | 573 | Input shift, employee, compliment, report completion |
| `CreateBomRecipePage.php` | 557 | Product search dan recipe creation |
| `StockCardEntryPage.php` | 551 | Catalog fetching, row state, draft/input, submit |
| `GoodsReceiptPage.php` | 451 | PO, QC, attachment, persistence, notification |
| `ItemJournalPage.php` | 381 | Master ESB, picker, local journal, attachment, submit |

Ukuran bukan bukti bug, tetapi file tersebut menjadi kandidat ekstraksi setelah safety net tersedia.

### Blade terbesar

| File | Baris |
| --- | ---: |
| `casual/pages/active-trip.blade.php` | 1.481 |
| `helpdesk/pages/sales-information-page.blade.php` | 1.343 |
| `helpdesk/pages/view-project-product.blade.php` | 1.180 |
| `casual/pages/clock-page.blade.php` | 1.132 |
| `casual/pages/daily-briefing-page.blade.php` | 861 |
| `helpdesk/pages/promotion-information-page.blade.php` | 834 |
| `helpdesk/rnd-projects/view.blade.php` | 785 |

Blade besar perlu dipecah berdasarkan section reusable/presentational. Jangan memindahkan query atau mutation ke komponen Blade.

## 6. Integrasi ESB

Enam file menggunakan Laravel HTTP client. Implementasi tersebar pada:

- `EsbService`
- `EsbCoreService`
- `EsbPromotionService`
- `EsbCompanyProductService`
- `EsbGoodsReceiptService`
- `EsbItemJournalService`

`EsbStockMovementService` membangun proses domain di atas service lain. Timeout bervariasi antara 12, 20, 30, dan 60 detik; connect timeout tidak seragam; kebijakan retry hanya terlihat pada sebagian flow.

Risiko yang terbukti saat full suite:

- Test Product List mencoba mengakses `core-api.esb.co.id` secara nyata dan gagal DNS.
- Artinya isolation test terhadap external network belum lengkap.
- Service besar mencampur beberapa bounded context, terutama `EsbService` (product, sales, payment, promotion, material usage).

Rekomendasi consumer migration:

1. Read-only Stock Movement/product catalog.
2. Master Item Journal (branch/location/purpose/product).
3. Mutation Item Journal dengan idempotency/reconciliation.
4. Goods Receipt mutation.
5. Sales/Promotion analytics.
6. R&D BOM/product mutation.

## 7. Queue, scheduler, dan transaksi

### Queue

- Queue default environment lokal saat audit: `sync`.
- `.env.example` merekomendasikan `database`, tetapi kondisi aktual berbeda.
- Tidak ada file dalam `app/Jobs`.
- Tabel `jobs` dan `failed_jobs` tersedia melalui migration.

Kesimpulan: database mendukung queue, tetapi aplikasi belum membentuk boundary Job. Mengubah connection saja tidak akan memindahkan proses yang saat ini berjalan langsung.

### Scheduler

Scheduler saat ini menjalankan:

- Warranty auto-complete harian.
- Briefing auto-reject setiap menit.
- Briefing score bulanan.
- Sinkronisasi R&D ESB harian.
- Sales Report auto-reject setiap menit.
- Basket Size finalize setiap 10 menit.

Beberapa memakai `withoutOverlapping`, tetapi belum ada heartbeat/monitoring yang ditemukan pada audit ini.

### Transaction boundary

`DB::transaction` ditemukan pada Command, Service, Action, Page, Resource, Controller, Observer, Model, dan Livewire component. Ini menunjukkan transaction ownership belum konsisten. Kandidat prioritas pemindahan dari UI: Goods Receipt, Item Journal, Sales Report Shift, R&D Project/Product, Stock Card review, dan Material Sourcing.

## 8. Livewire dan query presentasi

- 35 class Filament mempunyai public array; tidak semuanya bermasalah, tetapi perlu pengukuran payload.
- Stock Card menyimpan seluruh `rows` catalog dan baru membagi tampilan menggunakan kategori di client. Ini memperbaiki UX, belum mengurangi snapshot/DOM secara fundamental.
- Beberapa page menjalankan load external/data pada `mount`, sehingga route render dapat gagal jika ESB tidak tersedia.
- Empat Blade menjalankan query database langsung:
  - `purchase-requests/table-header-cell.blade.php`
  - `content-requests/table-header-cell.blade.php`
  - `erp-repair-requests/table-header-cell.blade.php`
  - `design-requests/table-header-cell.blade.php`

Query tersebut harus dipindahkan ke Page/Table provider atau view data setelah test filter tersedia.

## 9. Authorization

- Folder `app/Policies` tidak tersedia.
- Authorization banyak bergantung pada permission/resource/page checks dan branch helper.
- Tanpa Policy baseline, pemindahan UI atau pembuatan API berisiko mempertahankan visibilitas tombol tetapi kehilangan record-level authorization.

Phase 3 harus membuat matriks resource/action terlebih dahulu. Policy ditambahkan per domain saat ada characterization test, bukan secara massal tanpa perilaku yang jelas.

## 10. Storage dan attachment

Kode menggunakan disk bernama `b2`, sedangkan `config/filesystems.php` mengonfigurasinya sebagai driver S3 dengan environment `R2_BUCKET` dan `R2_ENDPOINT`. Jadi nama legacy `b2` saat ini menunjuk konfigurasi R2-compatible.

Temuan:

- Pemakaian storage tersebar pada Page, Resource, Controller, Model, Observer, Service, dan Livewire component.
- Beberapa model fallback dari `temporaryUrl()` ke URL biasa.
- Item Journal membaca seluruh attachment contents ke memory sebelum upload ESB.
- PDF R&D membaca beberapa objek storage ke memory.
- Delete file tersebar dan memerlukan audit rollback/orphan lifecycle.

Tidak perlu mengganti nama disk dalam phase awal. Standardisasi file lifecycle dikerjakan setelah kontrak visibility/download diuji.

## 11. Baseline test suite

### Percobaan default

```text
php artisan test --compact
```

Berhenti karena memory limit PHP 128 MB pada `FinfoMimeTypeDetector`.

### Percobaan dengan memory test 512 MB

```text
php -d memory_limit=512M vendor/bin/pest --compact
```

Hasil:

| Metrik | Nilai |
| --- | ---: |
| Total | 744 |
| Passed | 712 |
| Failed assertion | 23 |
| Error | 9 |
| Assertions | 4.289 |
| Durasi | 317,871 detik |

Kelompok kegagalan utama:

1. Test Breeze auth scaffold mengharapkan route registration, verification, password confirmation/reset/update yang tidak tersedia pada aplikasi/panel aktual.
2. Test Product List melakukan network request ESB nyata.
3. `EsbCoreServiceTest` mempunyai expectation request yang tidak cocok dengan implementasi aktual.
4. Position War test mengharapkan role `casual_staff` yang tidak tersedia dengan penamaan sekarang.
5. Technician tests masih mengatur public property `scheduledDate` yang sudah dihapus dari workflow.
6. Technician label assertion masih mengharapkan teks lama `Scan QR Asset`.
7. Example test masih mengharapkan root `200`, sedangkan aplikasi mengarahkan login/panel.

Kesimpulan: suite besar dan bernilai, tetapi baseline belum hijau. Phase 2 harus mengklasifikasikan test sebagai valid regression, stale expectation, atau konfigurasi test yang salah; jangan mengubah production hanya untuk memuaskan scaffold test yang tidak lagi menjadi requirement.

## 12. Peta risiko prioritas

| Prioritas | Area | Alasan |
| ---: | --- | --- |
| P0 | Test isolation dan stale tests | Refactor tanpa baseline hijau sulit dinilai |
| P0 | External HTTP pada request/test | Dapat membuat halaman/test gagal ketika ESB/DNS bermasalah |
| P0 | Queue masih sync, Job nihil | Proses berat tetap menahan web request |
| P1 | Stock Card state besar | Dataset besar pada Livewire/DOM |
| P1 | Policy nihil | Risiko record authorization saat modularisasi/API |
| P1 | ESB service tersebar/besar | Timeout/error/retry tidak seragam |
| P1 | Transaction dalam Page/Resource | UI memegang workflow dan persistence |
| P2 | R&D Page 2.524 baris | Perubahan berisiko dan sulit diuji terisolasi |
| P2 | Blade besar dan query Blade | Presentasi bercampur data retrieval |
| P2 | Attachment lifecycle tersebar | Risiko memory, orphan, dan visibility |
| P3 | Lima panel/provider | Duplikasi/discovery; migrasi perlu dilakukan belakangan |

## 13. Urutan Phase 2 yang disarankan

Phase 2 dikerjakan dalam beberapa PR kecil:

1. Klasifikasi dan perbaiki test auth/example legacy tanpa mengubah requirement aplikasi.
2. Cegah seluruh HTTP request nyata pada test suite (`Http::preventStrayRequests` pada scope yang tepat dan fake per contract).
3. Perbarui Technician tests mengikuti workflow tanpa `scheduledDate` dan copy QR aktual.
4. Selaraskan role fixture Position War dengan role registry/seeder aktual.
5. Perbaiki expectation `EsbCoreServiceTest` berdasarkan contract API yang berlaku.
6. Tetapkan memory test suite melalui konfigurasi test/CI yang eksplisit setelah penyebab attachment fixture ditinjau.
7. Jalankan full suite sampai hijau atau dokumentasikan test yang memang dihapus hanya dengan persetujuan eksplisit.

Setelah Phase 2 hijau, lanjutkan Phase 3 authorization/shared process, lalu Phase 4 ESB. Stock Card server-side category dikerjakan setelah fondasi test dan integration boundary tersedia.

## 14. Acceptance criteria Phase 1

- [x] Inventaris file/lapisan tersedia.
- [x] Panel dan discovery dipetakan.
- [x] Hotspot PHP/Blade diprioritaskan.
- [x] ESB call sites dan variasi timeout teridentifikasi.
- [x] Queue/scheduler/transaction baseline dicatat.
- [x] Query dalam Blade ditemukan.
- [x] Policy dan branch authorization gap dicatat.
- [x] Storage disk serta lifecycle risk dicatat.
- [x] Full test baseline dicoba dan hasilnya diklasifikasikan awal.
- [x] Urutan Phase 2 ditetapkan.

## 15. Prompt lanjutan

> Implementasikan Phase 2 dari `docs/code-remediation-prd.md` berdasarkan temuan `docs/code-remediation-phase-1-audit.md`. Fokus membuat baseline test suite valid dan terisolasi dari network eksternal. Jangan mengubah requirement production untuk mempertahankan test scaffold yang sudah stale. Kelompokkan perubahan dalam scope kecil, jalankan test file terkait lalu full suite dengan konfigurasi memory test yang terdokumentasi, dan laporkan setiap test yang masih gagal beserta klasifikasinya.
