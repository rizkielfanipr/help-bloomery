# PRD — Perbaikan Menyeluruh Kode Help Bloomery

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 22 September 2026 |
| Status | Rancangan untuk review; belum mengotorisasi implementasi atau deployment |
| Sasaran | Kode lebih stabil, cepat, modular, mudah diuji, aman, dan siap mendukung API tanpa penulisan ulang |
| Stack | Laravel 13, PHP 8.4, Filament 5, Livewire 4, Alpine.js 3, Tailwind CSS 4, Pest 4 |
| Strategi | Refactor bertahap dengan perilaku existing sebagai kontrak |
| Dokumen terkait | `codebase-refactoring-prd.md`, `application-stability-scalability-prd.md`, `ui-consistency-prd.md` |

Dokumen ini khusus mengatur perbaikan kode. Infrastruktur, DNS, SSL, hosting, backup, dan migrasi server mengikuti `docs/application-stability-scalability-prd.md`. Struktur target mengikuti `docs/codebase-refactoring-prd.md`; standar tampilan mengikuti `docs/ui-consistency-prd.md`.

Dokumen tidak memberi izin untuk menghapus data, menjalankan migration production, mengirim transaksi ESB, menambah dependency, atau melakukan deployment. Kondisi repository wajib diaudit pada awal setiap phase karena baseline dapat berubah.

## 2. Masalah yang diselesaikan

1. Page/Resource Livewire berpotensi menangani UI, query, perhitungan, dan integrasi sekaligus.
2. Pemanggilan ESB dan penanganan error belum seluruhnya melalui batas yang seragam.
3. Proses berat berpotensi berjalan dalam request pengguna.
4. State Livewire dan DOM pada halaman besar dapat membawa seluruh dataset.
5. Query, eager loading, pagination, indeks, dan cache belum mempunyai standar pengukuran lintas modul.
6. Workflow, permission, branch scope, status, attachment, dan side effect tersebar di banyak lapisan.
7. Komponen UI dan pola modal/form belum seluruhnya reusable.
8. Test belum menjadi safety net yang merata untuk seluruh workflow kritis.
9. Kode lama, route lama, dan file tidak terpakai perlu dihapus melalui bukti penggunaan, bukan perkiraan.
10. Fondasi API belum boleh menduplikasi proses bisnis yang sudah ada.

## 3. Tujuan dan ukuran keberhasilan

- Page/Resource fokus pada state UI, validasi presentasi, dan pemanggilan operasi bisnis.
- Proses bisnis reusable berada pada Action/Service dengan input dan output jelas.
- Seluruh integrasi ESB memakai client, timeout, logging, error mapping, dan aturan retry yang seragam.
- Proses background aman di-retry dan tidak menghasilkan transaksi ganda.
- Halaman besar memakai server-side pagination/lazy loading dan draft yang persisten.
- Query kritis mempunyai baseline dan bukti perbaikan.
- Policy serta scope cabang diuji di server-side.
- Test terdampak lulus pada setiap perubahan kecil.
- Kode mati dihapus hanya setelah route, reference, discovery, dan test membuktikan aman.
- Perilaku pengguna, data existing, URL penting, permission, status, dan export tetap terjaga kecuali ada requirement terpisah.

## 4. Batasan

### Termasuk

- Audit kode dan dependency internal.
- Refactor modular monolith.
- Ekstraksi Action, Service, Job, Policy, Query/Concern yang benar-benar reusable.
- Optimasi Livewire, query, cache, integrasi, export, dan attachment.
- Test unit, feature, Livewire, contract, dan smoke sesuai risiko.
- Penghapusan kode tidak terpakai secara bertahap.
- Persiapan proses bisnis agar dapat dipanggil API di masa depan.

### Tidak termasuk

- Rewrite ke microservices, Astro, Vue, React, atau Inertia.
- Mengubah seluruh URL/namespace sekaligus.
- Mengubah rumus atau workflow bisnis tanpa requirement khusus.
- Membuat abstraction, DTO, repository, event, atau interface untuk setiap class tanpa kebutuhan nyata.
- Optimasi berdasarkan jumlah baris kode semata.
- Menghapus test yang gagal hanya untuk membuat pipeline hijau.

## 5. Prinsip refactoring

1. **Characterization first:** perilaku penting ditest sebelum dipindahkan.
2. **One concern per change:** hindari PR lintas banyak domain.
3. **Measure performance:** bandingkan query, payload, memory, dan latency.
4. **Preserve contracts:** route, permission, status, dan format data dijaga.
5. **No speculative abstraction:** ekstrak setelah ada tanggung jawab atau reuse yang jelas.
6. **Retry-safe:** job dan integrasi mutation wajib idempotent.
7. **Delete with evidence:** file dihapus setelah usage audit dan regression test.
8. **Keep controllers/pages thin:** proses bisnis tidak bergantung pada instance Livewire.
9. **Database is explicit:** transaction, lock, uniqueness, dan side effect didokumentasikan.
10. **External failure is normal:** timeout, partial response, dan retry diperlakukan sebagai skenario utama.

## 6. Struktur tanggung jawab target

```text
Filament Page / Resource / Controller
    → authorization + presentation validation
    → Action bisnis
        → Domain Service / Calculator
        → Model + transaction database
        → Integration Service
        → Job / Event / Notification bila diperlukan
```

| Lapisan | Boleh | Tidak boleh |
| --- | --- | --- |
| Page/Resource | State, modal, form, feedback, pagination | HTTP ESB mentah, query besar dalam render, transaksi bisnis kompleks |
| Action | Satu use case, transaction boundary, coordination | HTML, modal, toast, state Livewire |
| Service | Kalkulasi reusable atau integrasi | Menentukan tampilan UI |
| Job | Proses background idempotent | Bergantung pada session/modal |
| Model | Relasi, cast, scope kohesif | Menjadi tempat seluruh workflow lintas domain |
| Policy | Izin record/action | Hanya mengatur visibilitas tombol |
| Blade | Presentasi | Query database, HTTP call, mutation |
| API Controller | Validasi HTTP dan Resource response | Menyalin ulang bisnis dari Filament |

## 7. Phase implementasi

### Phase 1 — Baseline dan peta kode

- Inventaris provider/panel, route, Page, Resource, Widget, Blade, Model, Service, Job, Command, Policy, notification, export, test, dan permission.
- Petakan ownership setiap file ke modul.
- Cari class panjang, method panjang, duplikasi, query di loop, HTTP call di UI, state Livewire besar, dan file tanpa reference.
- Rekam test suite, route list, waktu route kritis, jumlah query, payload Livewire, dan ukuran DOM.
- Tandai workflow dengan external side effect dan transaction boundary.
- Buat dependency map per modul; jangan memindahkan file pada phase audit.

**Output:** inventory, hotspot ranking, dependency map, baseline test/performa, dan urutan pilot.

**Selesai jika:** setiap file kritis mempunyai owner modul dan tidak ada klaim dead code tanpa bukti.

### Phase 2 — Safety net workflow kritis

- Tambahkan characterization test untuk login/redirect, launcher visibility, permission, branch scope, dan sidebar discovery.
- Tambahkan workflow test Stock Card, Receiving, Item Journal, Technician, Sales Report, R&D BOM/forecast, dan attachment.
- Gunakan HTTP fake untuk ESB; larang transaksi test ke production.
- Uji status transition, duplicate submission, timeout, retry, dan record lama.
- Gunakan factory/state existing; jangan membuat fixture rapuh dari database production.

**Selesai jika:** perilaku yang akan direfactor mempunyai test gagal bila kontrak dilanggar.

### Phase 3 — Fondasi shared process

- Pusatkan authorization record pada Policy dan reusable scope.
- Pusatkan branch access dan company/branch ESB mapping tanpa mengubah pengecualian administrator existing.
- Bentuk Result/Error object hanya untuk alur yang memerlukan status terstruktur.
- Pusatkan formatting/status pada Enum atau presenter yang sesuai.
- Ekstrak komponen UI berulang sesuai UI PRD, tanpa memasukkan proses bisnis ke Blade.

**Selesai jika:** Helpdesk dan Employee memanggil aturan akses/proses yang sama, bukan salinan.

### Phase 4 — Standardisasi integrasi ESB

- Audit seluruh endpoint, credential source, company context, timeout, retry, pagination, dan format error.
- Buat client bersama untuk authentication, base URL, headers, correlation ID, logging, dan response parsing.
- Pisahkan service read dan mutation per domain.
- Terapkan timeout eksplisit serta retry hanya ketika aman.
- Tambahkan idempotency/reconciliation untuk create Item Journal, GR, dan mutation lain.
- Cache/snapshot master branch, purpose, location, product, category, dan transaction type.
- Hilangkan pemanggilan HTTP mentah dari Blade/Page setelah consumer bermigrasi dan test lulus.

**Selesai jika:** seluruh call ESB terdaftar dan mengikuti satu kebijakan observability/error.

### Phase 5 — Queue dan proses background

- Pindahkan refresh Stock Movement, sinkronisasi catalog, upload attachment eksternal, export besar, notification massal, dan kalkulasi berat ke Job.
- Tetapkan tries, timeout, backoff, unique lock, serta `failed()` sesuai karakter operasi.
- Simpan status proses dan error aman untuk ditampilkan UI.
- Pisahkan keberhasilan database lokal dari keberhasilan sistem eksternal.
- Pastikan transaction database tidak membungkus HTTP call panjang tanpa alasan.
- Buat reconciliation untuk status eksternal tidak pasti.

**Selesai jika:** request browser tidak menunggu pekerjaan background dan retry tidak menggandakan side effect.

### Phase 6 — Livewire dan frontend server-driven

- Audit seluruh public property besar dan frequency update.
- Stock Card menjadi pilot: hanya kategori aktif berada di state; draft tersimpan sebelum navigasi.
- Picker produk memakai server-side search/pagination dan loading state.
- Detail/modal berat dimuat saat dibuka.
- Hindari `wire:model.live` pada field yang tidak memerlukan request per ketikan.
- Gunakan stable key/record ID dan locked property untuk identifier yang tidak boleh dimanipulasi.
- Pastikan mobile layout tidak memerlukan seluruh dataset di DOM.

**Selesai jika:** payload/DOM turun terukur, draft bertahan setelah reload, dan workflow tetap sama.

### Phase 7 — Query, schema, dan cache

- Gunakan query log/profiling untuk menemukan N+1, duplicate query, full scan, dan aggregate mahal.
- Terapkan eager loading, column selection, database pagination, chunk/cursor, dan aggregate query.
- Tambah indeks berdasarkan `EXPLAIN`/pola query; hindari indeks duplikat.
- Tambah unique constraint untuk invariant bisnis dan idempotency yang sesuai.
- Definisikan cache key, TTL, company/branch isolation, invalidation, dan fallback.
- Jangan cache response personal tanpa identity dan permission context.

**Selesai jika:** query count/latency membaik dan migration aman untuk data existing.

### Phase 8 — Modularisasi per domain

- Ikuti struktur target pada `codebase-refactoring-prd.md`.
- Migrasikan satu modul per perubahan: file PHP, view, route, navigation, policy, permission, test, dan export terkait.
- Pertahankan route name/URL atau sediakan redirect kompatibel.
- Verifikasi discovery Filament setelah setiap pemindahan.
- Hindari folder kosong dan class wrapper yang tidak memberi nilai.

Urutan kandidat:

1. StockCard/Inventory.
2. QualityControl/ItemJournal.
3. Receiving/VendorCompliance.
4. Technician/Assets.
5. SalesReport/Briefing.
6. Rnd/Purchasing.
7. Driver/Casual/BrandMarketing/StoreSop.
8. AccessManagement/MasterData/Analytics.

### Phase 9 — Export, attachment, dan file lifecycle

- Pusatkan storage disk dan path policy per domain.
- Gunakan private object dan temporary URL untuk file sensitif.
- Validasi MIME, ukuran, jumlah, dan authorization download/delete.
- Queue export besar; simpan status dan expiry.
- Pastikan rollback/delete record tidak meninggalkan file tanpa kebijakan.
- Audit seluruh attachment agar penggunaan R2 aktual sesuai asumsi.

### Phase 10 — API readiness

- Pastikan Action/Service tidak bergantung Filament/Livewire.
- Tambahkan API versioning, Form Request, Resource, Policy, rate limit, dan consistent error envelope saat API benar-benar dimulai.
- Mulai dari `me`, launcher, dan notification summary.
- Gunakan satu bootstrap response launcher; hindari endpoint per tile.
- Pertahankan API test terpisah dari UI test.
- Jangan membuat API untuk seluruh model hanya karena model tersedia.

### Phase 11 — Cleanup dan enforcement

- Cari reference via PHP, Blade, route, navigation, config, discovery Filament, reflection, dan string-based call.
- Hapus compatibility layer hanya setelah seluruh consumer bermigrasi.
- Hapus file build/source lama sesuai strategi bundling, bukan berdasarkan nama.
- Jalankan formatter, test terfokus, test modul, route/discovery check, dan build frontend bila relevan.
- Tambahkan architecture test hanya untuk aturan yang stabil dan bernilai.
- Perbarui dokumentasi aktual setelah implementasi, bukan sebelum bukti tersedia.

## 8. Checklist per modul

Setiap modul harus diaudit pada kategori berikut:

- Route dan navigation.
- Page/Resource/Widget dan view.
- Form validation dan authorization.
- Model, relation, cast, scope, dan index.
- Action/Service/Job.
- External integration dan idempotency.
- Attachment/export.
- Notification/event/listener.
- Permission dan branch scope.
- Query count, pagination, payload, dan cache.
- Unit/Feature/Livewire/contract test.
- Dead code dan compatibility layer.

## 9. Kontrak modul berisiko tinggi

### Stock Card

- Company/Branch berasal dari Master Branch.
- Snapshot movement, qty staff, correction, dan approval tetap terpisah.
- Seluruh tipe transaksi tetap tersedia sesuai aturan existing.
- Draft kategori tidak hilang; state tidak membawa seluruh catalog.

### Receiving dan Item Journal

- Mutation ESB tidak boleh terduplikasi.
- Timeout tidak otomatis berarti gagal.
- Attachment lokal/ESB mempunyai status jelas.
- Accepted/Hold/Rejected dan vendor incident mempertahankan invariant existing.

### Technician dan Asset

- User membuat request tanpa menentukan jadwal.
- Assignment, mulai kerja, outsource, report, dan status asset mengikuti transition yang sah.
- Scan QR tidak membuka record tanpa authorization.

### Sales Report dan Briefing

- Scheduler idempotent.
- Scoring hanya memakai status yang disepakati.
- Auto-reject menghormati tindak lanjut supervisor dan effective date.

### R&D

- BOM WIP diuraikan sampai bahan dasar sesuai mapping existing.
- Kitchen dan Store forecast tidak tercampur.
- Export mempertahankan data dan layout yang disepakati.
- Archive/soft-delete tidak menghilangkan BOM/attachment.

## 10. Quality gates

Setiap perubahan kode wajib melewati yang relevan:

1. `vendor/bin/pint --dirty --format agent` untuk PHP.
2. Pest test terfokus pada perubahan.
3. Test modul terkait.
4. `git diff --check`.
5. Route/Filament discovery check untuk pemindahan file.
6. Frontend build bila asset/source frontend berubah.
7. Query/payload comparison untuk pekerjaan performa.
8. Security review untuk permission, upload, API, dan credential.
9. Deployment serta rollback note untuk migration/config/job.

Full suite dijalankan pada milestone atau ketika perubahan menyentuh fondasi bersama.

## 11. Metrik perbaikan kode

| Area | Metrik |
| --- | --- |
| Web | p50/p95 latency, memory, status 5xx |
| Database | query count, duplicate query, slow query, rows scanned |
| Livewire | snapshot bytes, request count, DOM nodes, render time |
| Queue | wait time, processing time, failure/retry count |
| ESB | duration, timeout, error rate, retry, unknown outcome |
| Test | workflow coverage, duration, flaky failures |
| Maintainability | class responsibility, dependency direction, duplicate workflow |

Tidak ada target pengurangan baris kode. Lebih sedikit file juga bukan otomatis lebih baik.

## 12. Strategi pull request

- Satu modul atau satu fondasi per PR.
- Pisahkan movement namespace dari perubahan perilaku bila memungkinkan.
- Jelaskan trigger, perilaku sebelum/sesudah, data migration, test, dan rollback.
- Hindari rename massal bercampur feature baru.
- Commit generated/build asset hanya sesuai konvensi repository.
- Jangan deploy otomatis hanya karena PR selesai.

## 13. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Refactor mengubah workflow | Characterization test dan perubahan kecil |
| Job menggandakan transaksi | Idempotency, lock, reconciliation |
| Cache bocor lintas branch | Key menyertakan context dan authorization server-side |
| Pemindahan file merusak discovery | Route/discovery test setiap modul |
| Optimasi query mengubah hasil | Snapshot expected result dan regression test |
| Draft parsial inkonsisten | Transaction/unique constraint dan explicit state |
| Cleanup menghapus file dinamis | Audit string/discovery/config dan staged deletion |
| Abstraction berlebihan | Require concrete reuse/responsibility evidence |

## 14. Definition of done

Perbaikan menyeluruh dianggap matang ketika:

- Workflow kritis mempunyai test dan observability.
- Tidak ada HTTP ESB mentah di Blade/Page.
- Proses berat berjalan di queue dengan status dan idempotency.
- Halaman besar memakai server-side loading dan draft persisten.
- Query kritis memenuhi baseline performa yang disepakati.
- Permission dan branch scope diuji pada server-side.
- Modul mempunyai ownership folder dan dependency yang jelas.
- API dapat memanggil proses bersama tanpa menyalin logika UI.
- Kode lama yang terbukti tidak digunakan sudah dihapus.
- Deployment tidak membutuhkan langkah rahasia yang hanya diketahui satu orang.

## 15. Prompt penggunaan

### Audit phase

> Audit Phase X dari `docs/code-remediation-prd.md` untuk modul Y. Jangan mengubah kode. Periksa repository aktual, petakan file dan dependency, temukan gap dengan bukti, ukur baseline yang tersedia, serta berikan urutan perubahan, test, risiko, dan acceptance criteria.

### Implementasi phase

> Implementasikan Phase X dari `docs/code-remediation-prd.md` untuk modul Y. Pertahankan behavior, permission, branch scope, data existing, route penting, dan kontrak ESB. Kerjakan dalam scope kecil, gunakan pola repository, tambahkan test, ukur sebelum/sesudah bila terkait performa, jalankan quality gates, dan laporkan deployment serta rollback. Jangan deploy production tanpa instruksi eksplisit.

### Cleanup

> Audit kandidat dead code pada modul Y berdasarkan `docs/code-remediation-prd.md`. Jangan menghapus sebelum seluruh reference, route, Filament discovery, config, Blade, test, dan dynamic call diperiksa. Sajikan kandidat dan bukti terlebih dahulu.
