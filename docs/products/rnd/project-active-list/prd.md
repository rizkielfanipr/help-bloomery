---
document_id: RND-PROJECT-ACTIVE-LIST-PRD
title: PRD — Daftar Project Aktif
status: implemented
version: 1.0
owner: Research & Development
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/rnd/README.md
  - docs/products/rnd/project-types/prd.md
  - docs/products/rnd/project-task-calendar/prd.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# PRD — Daftar Project Aktif

> Dokumen ini menjadi sumber requirement canonical untuk scope daftar utama Project R&D. Requirement daftar/filter waktu pada `docs/products/rnd/project-types/prd.md` tetap historis untuk implementasi tipe Project, tetapi digantikan oleh dokumen ini sejauh mengatur index Project.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Research & Development |
| Fitur | Daftar Project Aktif |
| Status | implemented |
| Prioritas | TBD — belum ditetapkan owner bisnis |
| Target pengguna | User Back Office dengan akses Project R&D |
| Modul terdampak | Research & Development → Project |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

Index Project R&D saat ini menggunakan grid kartu dan secara default memuat seluruh Project non-arsip, termasuk Upcoming, Active, dan Completed. Kebutuhan baru adalah daftar operasional yang lebih ringkas, mudah dipindai, dan hanya menampilkan Project yang sedang aktif. Implementasi mengganti kartu menjadi daftar baris responsif, menjadikan scope aktif sebagai aturan query wajib pada daftar utama, serta mempertahankan pencarian, filter tipe, aksi existing, dan kalender Task sebagai workspace terpisah.

## 3. Latar belakang

Tim R&D menggunakan index Project sebagai titik masuk pekerjaan yang sedang berjalan. Kartu berukuran besar mengurangi jumlah Project yang dapat dipindai dalam satu layar, sedangkan pencampuran Project Upcoming, Completed, dan Archived menambah noise operasional. Sistem sudah memiliki definisi status waktu berdasarkan `start_date`, `end_date`, dan soft delete; perubahan ini menggunakan definisi existing tanpa menambah status baru.

## 4. Baseline sistem

- Menu berada di Back Office → Research & Development → Project dengan permission `view rnd projects` dan permission mutation existing.
- `app/Filament/Helpdesk/Resources/Projects/Pages/ListProjects.php` memakai custom Blade view dan method `projects()` sebagai sumber daftar serta release calendar.
- State default `projectStatus = ''` membuat query mengembalikan semua Project non-arsip.
- Filter status existing: Semua Status, Upcoming, Active, Completed, dan Diarsipkan.
- Status waktu dihitung sebagai Upcoming bila hari ini sebelum `start_date`, Completed bila hari ini setelah `end_date`, dan Active pada rentang inklusif di antaranya.
- `resources/views/filament/helpdesk/rnd-projects/index.blade.php` merender state `list` sebagai grid kartu dua/tiga kolom.
- Search nama/deskripsi dan filter `RndProjectType` sudah tersedia.
- Kalender gabungan Task/release memakai `HasProjectTaskCalendar`; deep-link dashboard memakai query parameter `openTask`.
- Arsip menggunakan soft delete dan action restore existing hanya terlihat melalui filter Archived pada index.
- Aksi edit/arsip memakai permission `edit rnd projects` dan `delete rnd projects`; restore memakai `edit rnd projects` melalui `HasPermissions`.
- `ProjectResource::table()` bukan renderer utama index karena `ListProjects` memakai custom view.

## 5. Problem statement

Daftar Project tidak efisien untuk pemindaian operasional karena setiap Project memakai kartu besar dan hasil default mencampur Project dari lifecycle waktu yang berbeda. User R&D membutuhkan satu daftar fokus untuk pekerjaan aktif, tanpa kehilangan akses ke detail, edit, arsip, pembuatan Project, filter tipe, pencarian, dan kalender Task.

## 6. Goals

1. Mengubah daftar utama Project dari grid kartu menjadi daftar baris yang ringkas dan responsif.
2. Menampilkan hanya Project aktif pada daftar utama.
3. Mempertahankan pencarian, filter tipe, jumlah konten per tipe, progress waktu, dan aksi existing.
4. Mempertahankan kalender Task dan deep-link task existing.
5. Menjaga permission, route, model data, dan workflow mutation existing.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Project nonaktif pada daftar utama | Dapat muncul secara default | 0 | Feature test dengan tanggal terkontrol |
| Bentuk item daftar | Grid kartu | Satu daftar baris responsif | Review Blade dan UAT viewport |
| Regresi aksi Project | Belum diukur untuk perubahan ini | 0 regresi pada test terdampak | Pest/Livewire test |
| Regresi kalender Task/deep-link | Belum diukur untuk perubahan ini | 0 regresi | Test kalender dan `openTask` |

## 8. Non-goals

- Tidak mengubah detail Project, isi workspace per tipe, Product Release, BOM, Partnership, atau Premix.
- Tidak menambah status Project, field `is_active`, tabel, migration, atau backfill.
- Tidak membuat Project Upcoming, Completed, atau Archived dapat dipilih dari daftar utama.
- Tidak menghapus data, soft-delete, method restore, atau kemampuan backend existing.
- Tidak membuat halaman pengelolaan arsip baru pada scope ini.
- Tidak menghapus kalender Task atau workflow checkpoint.
- Tidak mengubah permission, role, route, integrasi ESB, queue, scheduler, atau dependency.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Viewer Project R&D | Melihat, mencari, memfilter, dan membuka Project aktif | `view rnd projects` |
| Creator Project R&D | Membuat Project baru | `create rnd projects` |
| Editor Project R&D | Mengubah identitas/periode Project | `edit rnd projects` |
| Archiver Project R&D | Mengarsipkan Project aktif | `delete rnd projects` |
| Viewer Task R&D | Menggunakan kalender Task dan membuka detail task | `view rnd project tasks` |

## 10. User journey

1. User membuka Research & Development → Project.
2. Sistem menampilkan daftar Project yang aktif hari ini, diurutkan mengikuti kontrak existing terbaru-diubah.
3. User dapat mencari nama/deskripsi atau memilih tipe Project.
4. User membaca identitas, tipe, periode, jumlah isi, progress waktu, dan aksi pada satu baris.
5. User membuka detail, mengedit, atau mengarsipkan Project sesuai permission.
6. Project yang diarsipkan atau diedit keluar dari periode aktif langsung hilang dari daftar.
7. User dengan permission Task dapat berpindah ke kalender Task; deep-link `openTask` tetap membuka konteks task yang dituju.

## 11. Business rules

1. Project aktif adalah record yang tidak terhapus, dengan `start_date <= today` dan `end_date >= today`.
2. Batas tanggal bersifat inklusif: Project yang mulai atau berakhir hari ini tetap aktif.
3. Upcoming, Completed, dan Archived tidak boleh muncul pada daftar utama, termasuk ketika search atau filter tipe digunakan.
4. Search hanya mencocokkan `name` atau `description` di dalam scope Project aktif.
5. Filter tipe hanya mempersempit scope Project aktif.
6. Definisi aktif dihitung server-side; UI tidak boleh menjadi satu-satunya filter.
7. Urutan default tetap `updated_at` terbaru agar perubahan ini tidak memperkenalkan prioritas bisnis baru.
8. Jumlah isi mengikuti tipe: Product Release menghitung `products`, Premix menghitung `premixAssemblies`, Partnership menghitung `partnershipMenus`.
9. Aksi tetap tunduk pada Policy/permission existing.
10. Kalender Task tetap merupakan fasilitas umum Project; filtering daftar aktif tidak boleh menghilangkan task historis atau task masa depan dari query kalender.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Daftar utama hanya mengambil Project aktif dengan aturan §11 | Must |
| FR-2 | Grid kartu diganti daftar baris responsif | Must |
| FR-3 | Setiap baris menampilkan nama, deskripsi opsional, tipe, periode/rilis, jumlah isi, progress, dan aksi | Must |
| FR-4 | Search nama/deskripsi tetap tersedia dan terscope ke Project aktif | Must |
| FR-5 | Filter tipe tetap tersedia dan terscope ke Project aktif | Must |
| FR-6 | Filter status waktu/arsip dihapus dari daftar utama | Must |
| FR-7 | Create, open, edit, dan archive mempertahankan permission serta alur existing | Must |
| FR-8 | Setelah archive atau edit periode membuat Project nonaktif, record hilang dari daftar | Must |
| FR-9 | Empty state membedakan tidak ada Project aktif dan tidak ada hasil filter/search | Should |
| FR-10 | Kalender Task, release marker yang relevan, dan deep-link `openTask` tidak mengalami regresi | Must |
| FR-11 | Mobile menampilkan item sebagai list vertikal yang dapat dibaca tanpa horizontal-scroll halaman | Must |

## 13. Status dan lifecycle

Tidak ada status tersimpan baru. Label berikut merupakan derivasi waktu existing:

| Status derivatif | Makna | Muncul di daftar utama | Aktor |
|---|---|---:|---|
| Upcoming | `today < start_date` | Tidak | Sistem |
| Active | `start_date <= today <= end_date` dan tidak soft-deleted | Ya | Sistem |
| Completed | `today > end_date` | Tidak | Sistem |
| Archived | Record soft-deleted | Tidak | User berizin melalui action existing |

## 14. Model data konseptual

Menggunakan `RndProject` existing dengan field `start_date`, `end_date`, `project_type`, timestamps, dan `deleted_at`. Relasi `products`, `premixAssemblies`, dan `partnershipMenus` digunakan untuk count. Tidak ada perubahan schema, cast, relasi, constraint, atau data lama.

## 15. Integration contract

Not applicable — index Project membaca database lokal dan perubahan ini tidak menambah atau mengubah integrasi eksternal.

## 16. Authorization dan security

- Resource tetap memakai prefix permission `rnd projects` melalui `HasPermissions`.
- Visibility tombol mengikuti `ProjectResource::canCreate`, `canEdit`, dan `canDelete`.
- Method mutation tetap melakukan authorization server-side; menyembunyikan tombol bukan kontrol keamanan tunggal.
- Akses kalender Task tetap mengikuti `view rnd project tasks` dan scope kalender existing.
- Tidak ada perubahan company/branch scope, credential, attachment, atau data sensitif.

## 17. UI/UX specification

### 17.1 Struktur halaman

Urutan halaman: header workspace existing → modal create/edit bila aktif → filter → daftar Project aktif atau kalender Task. Header dan modal existing dipertahankan kecuali penyesuaian copy kecil yang diperlukan agar sesuai kontrak aktif.

### 17.2 Filter

- Search dengan placeholder nama/deskripsi tetap tersedia.
- Filter tipe tetap memakai `RndProjectType` sebagai sumber pilihan.
- Filter status dihapus karena scope daftar selalu Active.
- Filter stack pada mobile dan tersusun ringkas pada desktop.
- Reset search/filter harus tersedia pada empty state hasil filter.

### 17.3 Daftar desktop

Daftar memakai satu container berborder tanpa shadow dekoratif. Informasi dapat disusun sebagai kolom konseptual: Project, Tipe, Periode/Rilis, Isi, Progress, Aksi. Implementasi tidak wajib memakai elemen `<table>` bila list semantics lebih responsif, tetapi alignment desktop harus memudahkan perbandingan antarbaris.

### 17.4 Daftar mobile

Setiap item menjadi satu baris/list item bertumpuk: identitas dan tipe di bagian atas, informasi periode/jumlah/progress di bawahnya, lalu aksi yang tetap terlihat. Seluruh viewport tidak boleh mengalami horizontal-scroll. Nama dan deskripsi panjang wrap atau truncate secara terkendali dengan nilai penting tetap dapat dibaca.

### 17.5 Isi baris

- Nama Project sebagai tautan/identitas utama.
- Deskripsi ringkas bila tersedia; tidak perlu placeholder panjang bila kosong.
- Badge tipe dengan teks, tidak hanya warna.
- Periode `start_date`–`end_date` atau penekanan tanggal rilis yang konsisten.
- Label dan count sesuai tipe Project.
- Progress waktu existing dalam angka dan indikator visual; warna bukan satu-satunya informasi.
- Aksi buka, edit, dan arsip sesuai permission, dengan accessible name pada tombol ikon.

### 17.6 Empty, loading, dan feedback

- Tanpa Project aktif: “Tidak ada project aktif saat ini.” CTA Buat Project hanya bila diizinkan.
- Search/filter kosong: “Project aktif tidak ditemukan.” Sediakan reset filter.
- Mutation tetap memiliki loading/disabled state dan notification existing.
- Error tidak menampilkan exception atau detail internal.

### 17.7 Kalender

Kalender Task tetap tersedia bagi user berpermission. User tanpa permission Task mempertahankan release calendar fallback existing sepanjang tidak menyebabkan Upcoming/Completed muncul pada daftar utama. Perubahan query perlu memisahkan sumber daftar aktif dari sumber kalender bila penggunaan method bersama menyebabkan kalender kehilangan data yang seharusnya tetap terlihat.

## 18. Non-functional requirements

- Query daftar melakukan filter aktif di database, bukan mengambil seluruh record lalu memfilter collection.
- Count relasi tetap eager aggregate untuk mencegah N+1.
- Tidak menambah request eksternal saat render.
- Perubahan tidak menambah public Livewire state besar.
- View diperiksa minimal pada viewport 360, 390, 768, 1024, dan 1440 px serta zoom 200%.
- Dark mode mengikuti variant existing.

## 19. Observability dan audit

Not applicable — perubahan read-only pada query/list tidak memerlukan audit log baru. Mutation create, update, archive, dan restore tetap memakai observability existing. Regresi dipantau melalui error aplikasi, hasil UAT, query count, dan render time halaman.

## 20. Data migration dan kompatibilitas

- Tidak ada migration atau backfill.
- Semua Project lama tetap tersimpan dan dapat diakses melalui route detail yang sah.
- Bookmark/detail route tidak berubah.
- Backend restore tidak dihapus, tetapi UI pengelolaan arsip berada di luar scope.
- Kontrak index/filter pada PRD Project Types digantikan hanya untuk scope yang dinyatakan dokumen ini.

## 21. Testing strategy

- Characterization test baseline create, edit, archive, route detail, permission, dan kalender sebelum perubahan.
- Livewire feature test dengan `Carbon::setTestNow()` untuk Active, Upcoming, Completed, batas tanggal, dan soft delete.
- Test kombinasi search + tipe untuk memastikan scope aktif tidak dapat ditembus.
- Test count dan label per `RndProjectType`.
- Test empty state tanpa data aktif dan setelah filter tidak cocok.
- Test permission aksi untuk user berhak dan tidak berhak.
- Test archive/edit menghilangkan record dari daftar.
- Test regresi kalender Task, release marker, dan query parameter `openTask`.
- Tidak ada HTTP eksternal pada test fitur ini.

## 22. Rollout

Satu PR aplikasi setelah PRD disetujui. Tidak memerlukan feature flag, migration, environment variable, permission sync, queue restart, scheduler, atau langkah data manual. UAT staging dilakukan untuk role R&D pada desktop dan mobile sebelum approval production.

## 23. Rollback

Rollback dilakukan dengan revert commit/PR UI dan query. Tidak ada rollback data karena tidak ada schema atau data mutation baru. Project yang dibuat/diedit/diarsipkan melalui workflow existing tetap aman.

## 24. Acceptance criteria

1. Index Project tidak lagi merender Project sebagai grid kartu.
2. Daftar utama hanya menampilkan Project aktif sesuai §11.
3. Project Upcoming, Completed, dan Archived tidak muncul pada daftar utama.
4. Project yang mulai atau berakhir hari ini tetap muncul.
5. Search dan filter tipe tetap bekerja hanya di dalam scope aktif.
6. Setiap item menampilkan informasi dan aksi yang ditetapkan §17.5.
7. Layout dapat digunakan pada mobile tanpa horizontal-scroll halaman.
8. Create, open, edit, dan archive tetap mengikuti permission existing.
9. Calendar Task dan deep-link `openTask` tetap berfungsi.
10. Test terdampak, Pint, doc-link, code-map, dan diff check lulus.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Method `projects()` dipakai daftar dan kalender | Kalender kehilangan Upcoming/Completed atau release marker | Pisahkan query/collection daftar aktif dan kalender; tambah regression test |
| Arsip tidak lagi dapat dipulihkan dari index | User kehilangan entry point restore | Nyatakan sebagai non-goal; jangan hapus backend restore; rencanakan UI arsip terpisah bila dibutuhkan |
| List terlalu lebar di mobile | Aksi/informasi tidak dapat digunakan | Gunakan responsive list, wrap, dan uji viewport minimum |
| Scope aktif hanya diterapkan di UI | Record nonaktif muncul melalui state/query lain | Terapkan constraint pada query server dan test kombinasi filter |
| Perubahan status tanggal saat tengah malam | Record berpindah tanpa refresh | Terima sebagai derivasi tanggal existing; refresh memuat state terbaru |

## 26. Open decisions

Tidak ada keputusan yang memblokir dokumentasi. Keputusan kalender Task dipertahankan dan daftar utama hanya menampilkan Project aktif telah dikonfirmasi pada 2 November 2026. UI pengelolaan arsip dan Project nonaktif dikerjakan pada [PRD Daftar Project Nonaktif](../project-inactive-list/prd.md). Hasil implementasi: [laporan](reports/implementation-report.md).

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Characterization dan pemisahan kontrak query daftar vs kalender | Test baseline dan query boundary yang jelas |
| 1 | Query daftar aktif dan penyederhanaan filter | Server-side active scope, search, dan filter tipe |
| 2 | UI list responsif dan state kosong | Daftar baris desktop/mobile tanpa kartu |
| 3 | Regresi, dokumentasi aktual, dan delivery | Test lulus, README sinkron, implementation report/PR |

## 28. Definition of Done

- [x] Seluruh acceptance criteria terpenuhi.
- [x] Test terdampak ditambahkan dan lulus.
- [x] Authorization diuji untuk role yang berhak dan tidak berhak.
- [x] Tidak ada migration, dependency, permission, atau integrasi baru.
- [x] Kalender Task dan deep-link existing terbukti tidak regresi.
- [x] Dokumentasi fitur dan README domain diperbarui.
- [x] Implementation report mencatat hasil, deviasi, dan test aktual.
- [x] Pull Request ke `main` siap direview dan belum di-merge/deploy tanpa keputusan user.
