# PRD — Tipe Project R&D: Release Product, Premix, dan Partnership

## 1. Identitas dokumen

| Item | Nilai |
| --- | --- |
| Modul | R&D → Project |
| Jenis perubahan | Perluasan tipe Project dan remediasi arsitektur terkait |
| Status | Draft untuk persetujuan |
| Target stack | Laravel 13, Filament 5, Livewire 4, Tailwind CSS 4, Pest 4 |
| Referensi wajib | `docs/code-remediation-prd.md`, `docs/ui-consistency-prd.md` |
| Dampak data existing | Non-destructive; seluruh Project existing tetap menjadi Release Product |

Dokumen ini menjadi sumber kebutuhan produk untuk penambahan tipe Project R&D. Dokumen ini tidak mengizinkan deploy production, perubahan permission massal, penghapusan data, atau migrasi data destruktif tanpa persetujuan terpisah.

## 2. Ringkasan eksekutif

Menu R&D → Project saat ini berorientasi pada proses peluncuran produk. Kebutuhan bisnis berkembang menjadi tiga tipe Project:

1. **Release Product** — seluruh perilaku Project existing dipertahankan.
2. **Premix** — Project sederhana yang berisi daftar BOM bertipe Assembly saja.
3. **Partnership** — Project kerja sama/event yang menyimpan nama event dan daftar Menu yang digunakan.

Implementasi harus menggunakan satu entitas induk `RndProject`, lalu memisahkan data dan tampilan khusus tipe. Partnership sengaja dibuat ringan: pemilihan Menu membaca katalog lokal yang sudah tersedia, menyimpan snapshot minimum, dan tidak menjalankan resolver BOM, material, forecast, shelf life, finalisasi memo, atau panggilan ESB pada saat halaman dirender.

Release Product menjadi nilai default agar data, route, bookmark, workflow, dan test existing tetap kompatibel.

## 3. Latar belakang dan masalah

### 3.1 Kondisi saat ini

- `RndProject` belum memiliki atribut tipe.
- halaman daftar dan detail Project menganggap seluruh Project adalah Release Product;
- `ViewProject` dan view detail sudah memuat banyak tanggung jawab workflow;
- `rnd_project_boms` sudah dapat berelasi langsung ke Project, sehingga dapat digunakan oleh Premix tanpa membuat Product dummy;
- `rnd_project_boms.esb_bom_id` saat ini unik secara global, sehingga BOM yang sama berpotensi tidak dapat digunakan pada dua Project berbeda;
- Add Menu Internal Memo mempunyai katalog dan pola picker yang dapat dijadikan referensi, tetapi model/action Memo tidak boleh dipakai sebagai tempat penyimpanan Partnership;
- workflow Internal Memo memuat proses berat yang tidak dibutuhkan Partnership.

### 3.2 Masalah bisnis

- semua pekerjaan R&D dipaksa masuk ke struktur Release Product walaupun kebutuhan datanya berbeda;
- Premix hanya membutuhkan daftar Assembly, tetapi berpotensi terbebani form dan section Release Product;
- Partnership hanya membutuhkan identitas event dan daftar Menu, tetapi berpotensi ikut menarik workflow BOM/material yang berat;
- penambahan kondisi langsung ke Page/view yang sudah besar akan meningkatkan risiko regresi dan sulit diuji.

## 4. Tujuan

1. Menyediakan tiga tipe Project dalam satu menu R&D → Project.
2. Mempertahankan seluruh data dan perilaku Release Product existing.
3. Membuat Premix hanya menerima BOM Assembly.
4. Membuat Partnership dapat mencatat nama event dan banyak Menu dengan UX Add Menu seperti Internal Memo.
5. Menjaga halaman cepat dengan local-first catalog, query terukur, pagination, dan tanpa HTTP eksternal saat render.
6. Memisahkan mutasi bisnis ke Action dan query katalog ke Query object agar Page/Livewire tetap tipis.
7. Menyamakan visual dengan pola Project Workspace dan guardrail `ui-consistency-prd.md`.
8. Menjamin authorization, company/brand context, data lama, serta route existing tidak berubah secara tidak sengaja.

## 5. Non-goals

Scope ini tidak mencakup:

- mengubah workflow atau perhitungan Release Product;
- mengubah Internal Memo menjadi Partnership;
- forecast quantity, material breakdown, shelf life, CCP, marketing material, SOP toko, atau release ke cabang untuk Premix/Partnership;
- sinkronisasi Menu/BOM secara langsung dari browser;
- status approval baru untuk Project;
- konversi tipe Project setelah Project dibuat;
- membuat role atau permission baru tanpa kebutuhan yang disetujui;
- menghapus tabel atau data R&D existing;
- redesign seluruh modul R&D di luar halaman daftar, create/edit, dan detail Project yang terdampak.

## 6. Terminologi

| Istilah | Definisi |
| --- | --- |
| Project | Record induk pekerjaan R&D pada `rnd_projects` |
| Release Product | Tipe existing untuk proses pengembangan/peluncuran produk |
| Premix | Tipe Project yang mengelola pilihan BOM Assembly |
| Partnership | Tipe Project untuk event/kerja sama dan daftar Menu yang digunakan |
| Menu catalog | Snapshot lokal Menu yang telah disinkronkan dari sumber upstream |
| BOM catalog | Snapshot lokal BOM yang dipakai sebagai sumber picker |
| Snapshot | Salinan identitas/nama saat item ditambahkan agar histori tetap terbaca |

## 7. Keputusan produk utama

### 7.1 Nilai tipe

Gunakan enum string `RndProjectType`:

| Enum | Nilai tersimpan | Label UI |
| --- | --- | --- |
| `ReleaseProduct` | `release_product` | Release Product |
| `Premix` | `premix` | Premix |
| `Partnership` | `partnership` | Partnership |

`release_product` wajib menjadi default database dan default model.

### 7.2 Tipe bersifat immutable

Tipe hanya dipilih saat membuat Project dan tidak dapat diubah setelah record tersimpan. Edit Project tidak menampilkan input tipe.

Alasan:

- mencegah data Release Product tersisa ketika Project diubah menjadi Premix/Partnership;
- menghindari konversi data implisit dan penghapusan relasi;
- membuat authorization, validasi, dan tampilan lebih deterministik.

Jika salah memilih tipe, user membuat Project baru dan mengarsipkan Project yang salah sesuai mekanisme existing.

### 7.3 Kemampuan per tipe

| Kemampuan | Release Product | Premix | Partnership |
| --- | ---: | ---: | ---: |
| Identitas Project, tanggal, deskripsi | Ya | Ya | Ya |
| Task Calendar/Checkpoint existing | Ya | Ya | Ya |
| Product release existing | Ya | Tidak | Tidak |
| BOM existing terkait release | Ya | Tidak | Tidak |
| BOM Assembly Project | Tidak sebagai flow baru | Ya | Tidak |
| Nama event/partnership | Tidak | Tidak | Ya |
| Daftar Menu Partnership | Tidak | Tidak | Ya |
| Forecast/material/shelf life/CCP/marketing/SOP | Existing | Tidak | Tidak |

Task Calendar dan arsip tetap merupakan fasilitas umum Project, bukan konten domain tipe. Section Release Product lain wajib disembunyikan pada Premix dan Partnership.

## 8. User stories

### 8.1 Semua Project

- Sebagai user R&D yang berwenang, saya dapat membuat Project dan memilih tipe yang sesuai.
- Sebagai user, saya dapat melihat tipe langsung dari daftar dan header detail.
- Sebagai user, saya dapat memfilter Project berdasarkan tipe.
- Sebagai user, saya tetap dapat menggunakan pencarian, filter waktu/status, task, dan arsip sesuai hak existing.

### 8.2 Premix

- Sebagai user R&D, saya dapat menambahkan satu atau beberapa BOM Assembly ke Project Premix.
- Saya tidak dapat memilih BOM selain Assembly.
- Saya dapat menghapus Assembly dari Project tanpa menghapus master BOM atau histori Project lain.
- Saya dapat memakai BOM Assembly yang sama pada Project Premix berbeda.

### 8.3 Partnership

- Sebagai user R&D, saya dapat mengisi nama event/partnership.
- Saya dapat membuka modal Add Menu dengan pola yang familiar dari Internal Memo.
- Saya dapat mencari, memfilter, memilih banyak Menu, lalu menambahkannya sekali submit.
- Saya dapat menghapus Menu dari Partnership tanpa menghapus Menu dari katalog.
- Saya tetap dapat membaca nama Menu lama dari snapshot walaupun katalog berubah atau item menjadi tidak aktif.

## 9. Alur pengguna

### 9.1 Membuat Project

1. User membuka R&D → Project.
2. User menekan **Buat Project**.
3. Form meminta tipe, nama Project, tanggal, dan deskripsi sesuai field existing.
4. Jika tipe Partnership dipilih, field **Nama Event/Partnership** muncul dan wajib diisi.
5. User menyimpan.
6. Sistem melakukan authorization dan validasi server-side.
7. Sistem membuat satu `RndProject` dalam transaction.
8. User diarahkan ke detail dengan workspace sesuai tipe.

### 9.2 Premix — tambah Assembly

1. User membuka Project Premix.
2. User menekan **Tambah Assembly**.
3. Modal membaca `rnd_bom_catalogs` secara server-side dan hanya menampilkan record eligible dengan `bom_type_id = 1`.
4. User mencari dan memilih satu atau beberapa Assembly.
5. Submit memanggil Action khusus, melakukan authorization ulang, mengambil snapshot katalog dari database, dan menyimpan secara idempotent.
6. Daftar diperbarui tanpa reload penuh.

### 9.3 Partnership — tambah Menu

1. User membuka Project Partnership.
2. User menekan **Tambah Menu**.
3. Modal mengikuti interaksi Add Menu Internal Memo: pencarian, filter context yang berlaku, multi-select, jumlah pilihan, cancel, dan submit.
4. Data dibaca dari katalog lokal; tidak ada request ESB dari render atau mutation ini.
5. Action memvalidasi seluruh ID terpilih dalam satu query terukur, mengecek scope, lalu menyimpan snapshot minimum dalam satu transaction.
6. Duplikasi tidak membuat row kedua dan user mendapat hasil yang jelas.

## 10. Kebutuhan fungsional

### 10.1 Daftar Project

- Pertahankan route, navigation group, search, pagination, dan filter existing.
- Tambahkan badge tipe dengan label teks; warna bukan satu-satunya pembeda.
- Tambahkan filter tipe: Semua, Release Product, Premix, Partnership.
- Kolom ringkasan harus relevan terhadap tipe:
  - Release Product: pertahankan count existing;
  - Premix: jumlah Assembly;
  - Partnership: jumlah Menu.
- Count harus dihitung dengan aggregate/`withCount`, bukan load seluruh relasi.
- Empty state mengikuti filter aktif dan menyediakan aksi yang memang diizinkan.

### 10.2 Form create/edit

- Create menampilkan pilihan tipe sebagai select atau pilihan kartu ringkas yang aksesibel.
- Default pilihan adalah Release Product untuk kompatibilitas, tetapi user tetap harus melihat tipe yang dipilih sebelum submit.
- Nama event hanya muncul untuk Partnership dan wajib setelah whitespace dinormalisasi.
- Edit mempertahankan tipe sebagai informasi read-only, bukan input yang dapat diubah.
- Field yang gagal validasi tetap berisi nilai input user.
- Tombol save memiliki loading/disabled state dan mencegah submit ganda.

### 10.3 Detail Release Product

- Seluruh section, route turunan, calculation, export, attachment, status, dan workflow existing tetap bekerja.
- Penambahan tipe tidak boleh mengubah query bisnis atau hasil Release Product.
- Header menampilkan badge **Release Product**.
- Refactor hanya diperbolehkan bila diperlukan untuk pemisahan view, dengan characterization test sebelum perubahan.

### 10.4 Detail Premix

- Menampilkan identitas Project, badge Premix, task/checkpoint umum, dan section **BOM Assembly**.
- Section menampilkan nama BOM, kode, UOM/product ringkas bila tersedia, status katalog/snapshot yang relevan, dan aksi hapus sesuai permission.
- Hanya katalog dengan `bom_type_id = 1` yang dapat dipilih.
- Validasi tipe dilakukan kembali di Action; filter UI bukan kontrol keamanan.
- Project Premix tidak membuat `RndProjectProduct` dummy dan tidak menghubungkan pivot product-BOM.
- Tidak menjalankan forecast, material expansion, shelf life, CCP, marketing, Store SOP, atau release flow.
- Jika item katalog yang sudah tersimpan kemudian nonaktif/hilang, row Project tetap tampil dari snapshot dan diberi keterangan yang tidak menyesatkan.

### 10.5 Detail Partnership

- Menampilkan identitas Project, badge Partnership, nama event, task/checkpoint umum, dan section **Menu Partnership**.
- Daftar Menu menampilkan nama, kode, company/brand context yang relevan, category bila tersedia, serta aksi hapus.
- Add Menu mendukung multi-select.
- Menu yang sama tidak boleh tersimpan dua kali dalam Project dan context yang sama.
- Tidak ada quantity, material, forecast, shelf life, resolver BOM, approval, finalization, atau PDF Memo pada MVP.
- Snapshot tersimpan tidak diubah otomatis ketika katalog berubah.
- Jika katalog belum tersedia, halaman detail tetap dapat dibuka; hanya Add Menu yang menampilkan empty/error state yang dapat ditindaklanjuti.

## 11. Arsitektur aplikasi

### 11.1 Prinsip

Mengikuti `code-remediation-prd.md`:

- Filament Page/Livewire menangani state UI dan orkestrasi, bukan seluruh logika bisnis;
- Blade hanya merender data yang sudah disiapkan dan tidak melakukan query;
- mutasi ditempatkan pada Action satu tujuan;
- query picker ditempatkan pada Query object;
- authorization dilakukan di UI untuk visibilitas dan di Action untuk enforcement;
- semua mutasi multi-row memakai transaction dan constraint database;
- tidak ada HTTP mentah pada Page, Livewire, atau Blade;
- perubahan dilakukan bertahap, bukan rewrite total `ViewProject`.

### 11.2 Komponen domain yang disarankan

Nama final mengikuti konvensi folder existing setelah Phase 0 audit.

| Komponen | Tanggung jawab |
| --- | --- |
| `RndProjectType` | Enum tipe dan label |
| `CreateRndProjectAction` | Authorization, validasi contract, transaction create |
| `UpdateRndProjectAction` | Update field umum tanpa mengubah tipe |
| `PremixAssemblyCatalogQuery` | Query lokal Assembly eligible dan pagination |
| `AddPremixAssembliesAction` | Tambah snapshot Assembly secara idempotent |
| `RemovePremixAssemblyAction` | Lepas Assembly dari satu Premix |
| `PartnershipMenuCatalogQuery` | Query lokal Menu sesuai scope/filter |
| `AddPartnershipMenusAction` | Bulk add snapshot Menu dalam transaction |
| `RemovePartnershipMenuAction` | Hapus satu relasi Menu Partnership |

Jangan membuat `ProjectService` besar yang menampung seluruh use case.

### 11.3 Pemisahan UI

`ViewProject` tetap menjadi route/detail entry point, tetapi delegasikan konten tipe ke partial/component yang terpisah:

- workspace Release Product existing;
- workspace Premix;
- workspace Partnership;
- header dan Task Calendar yang benar-benar shared.

Jangan menambah percabangan berulang di satu Blade monolitik. Abstraksi baru hanya dibuat bila ada pengulangan nyata minimal pada lebih dari satu workspace.

### 11.4 Katalog Menu bersama

Partnership tidak boleh bergantung pada model atau lifecycle `RndInternalMemo`.

Strategi:

1. karakterisasi perilaku query katalog Add Menu Internal Memo;
2. bila query tersebut benar-benar generik, ekstrak core query menjadi nama domain-neutral, misalnya `RndMenuCatalogQuery`;
3. pertahankan adapter/wrapper Internal Memo agar kontrak existing tidak berubah;
4. Partnership memanggil query generik tersebut dengan context dan authorization miliknya;
5. jangan memindahkan logic finalisasi/resolver/material Memo ke query generik.

Jika ekstraksi meningkatkan risiko terlalu besar, buat Query Partnership tipis yang membaca tabel katalog lokal yang sama. Duplikasi query kecil dan eksplisit lebih aman daripada coupling ke workflow Memo.

## 12. Model data

### 12.1 Perubahan `rnd_projects`

Tambahkan melalui migration baru:

| Kolom | Tipe | Aturan |
| --- | --- | --- |
| `project_type` | string | non-null, default `release_product`, indexed |
| `partnership_event_name` | string nullable | wajib pada application layer hanya untuk Partnership |

Pertimbangan:

- satu field nullable untuk nama event lebih ringan daripada tabel metadata one-to-one;
- model harus memiliki cast enum untuk `project_type` dan default yang sama dengan database;
- seluruh row existing otomatis menjadi `release_product` tanpa update destruktif terpisah;
- nilai event harus `null` untuk tipe selain Partnership.

### 12.2 Perubahan `rnd_project_boms`

Tambahkan `bom_type_id` nullable sebagai snapshot identitas tipe BOM. Nilai dapat tetap nullable untuk row legacy yang tidak mempunyai bukti aman.

Untuk Premix:

- row baru wajib mempunyai `bom_type_id = 1`;
- `bom_type_name` tetap disimpan sebagai snapshot label;
- Action mengambil nilai dari `rnd_bom_catalogs`, bukan request user.

Constraint `esb_bom_id` yang saat ini unik secara global harus diremediasi agar BOM yang sama dapat dipakai pada Project berbeda:

1. audit row existing dan seluruh consumer;
2. lepas unique global hanya setelah characterization test tersedia;
3. tambahkan unique composite `rnd_project_id + esb_bom_id`;
4. pertahankan foreign key/index yang dibutuhkan query;
5. migration harus reversible dan tidak menghapus row.

Jangan mengubah constraint ini tanpa membuktikan bahwa identitas global tidak dipakai sebagai asumsi pada consumer lain.

### 12.3 Tabel `rnd_project_partnership_menus`

Tabel anak baru menyimpan hubungan dan snapshot minimum:

| Kolom | Keterangan |
| --- | --- |
| `id` | Primary key |
| `rnd_project_id` | FK ke Project, cascade on delete sesuai lifecycle parent |
| `company_code` | Context company dari katalog |
| `brand_id` | Nullable bila katalog/context existing menyediakan Brand |
| `esb_menu_id` | ID Menu upstream |
| `menu_code` | Snapshot kode nullable |
| `menu_name` | Snapshot nama wajib |
| `category_name` | Snapshot kategori nullable |
| `menu_snapshot` | JSON metadata minimum untuk histori/debug, nullable |
| `sort_order` | Urutan stabil, default 0 |
| `created_by` | FK user nullable mengikuti konvensi audit existing |
| timestamps | Waktu dibuat/diubah |

Constraint/index minimum:

- unique `rnd_project_id + company_code + esb_menu_id`;
- index `rnd_project_id + sort_order`;
- index context katalog hanya jika dibuktikan oleh query plan;
- FK mengikuti konvensi repository dan migration reversible.

Tabel ini tidak menyimpan material, forecast, shelf life, quantity, hasil resolver, atau status sync per Menu.

### 12.4 Relasi model

- `RndProject::partnershipMenus(): HasMany`;
- `RndProjectPartnershipMenu::project(): BelongsTo`;
- factory untuk model baru;
- model memakai `$fillable`, casts, dan return type relasi eksplisit.

## 13. Aturan bisnis dan invariant

1. Semua Project existing adalah Release Product.
2. Tipe tidak dapat diubah setelah create.
3. Nama event wajib dan tidak boleh kosong untuk Partnership.
4. Nama event harus `null` untuk Release Product dan Premix.
5. Action Premix menolak Project non-Premix.
6. Action Premix menolak katalog non-Assembly meskipun ID dikirim manual.
7. Satu BOM hanya sekali per Project, tetapi boleh digunakan Project berbeda.
8. Action Partnership menolak Project non-Partnership.
9. Satu Menu/context hanya sekali per Project.
10. Semua ID katalog diverifikasi ulang terhadap query yang sudah scoped.
11. Snapshot existing tidak hilang ketika master katalog berubah/nonaktif.
12. Remove hanya melepas item dari Project target dan tidak menghapus master katalog.
13. Release Product tidak boleh membaca tabel Partnership untuk menjalankan workflow existing.
14. Mutation harus idempotent terhadap double-click/retry.

## 14. Permission dan scope data

- Gunakan permission/policy Project existing sebagai baseline; jangan memperluas akses karena tipe baru.
- Create, update, view, archive/delete, add item, dan remove item diperiksa server-side.
- User yang tidak dapat melihat Project tidak boleh memanggil action turunannya dengan ID langsung.
- Katalog Menu/BOM hanya menampilkan context company/brand/branch yang memang boleh diakses user menurut resolver existing.
- Akses “all branch” harus tetap berarti seluruh context yang sah menurut policy, bukan bypass authorization.
- Jangan hard-code company, branch, Brand, role, atau user ID.
- Test harus mencakup user tanpa akses, akses terbatas, dan akses seluruh branch/context.

## 15. Integrasi dan performa

### 15.1 Local-first

- Picker hanya membaca snapshot katalog lokal.
- Tidak ada panggilan HTTP ESB saat index/detail render.
- Tidak ada panggilan resolver BOM/material ketika Menu Partnership ditambahkan.
- Sinkronisasi katalog tetap menjadi proses upstream terpisah dan tidak dipicu diam-diam oleh buka modal.

### 15.2 Katalog stale/gagal

- Jika snapshot tersedia tetapi lama, item tetap dapat ditampilkan sesuai kontrak katalog existing dan UI memberi informasi freshness bila datanya tersedia.
- Jika tidak ada snapshot usable, modal menampilkan pesan tindakan yang jelas; Project tetap dapat dibuka dan data tersimpan tetap terbaca.
- Kegagalan upstream bukan alasan menghapus snapshot existing.
- Tidak diperlukan env baru khusus usia katalog untuk fitur ini kecuali ada keputusan produk terpisah.

### 15.3 Query dan payload

- picker memakai search/filter/pagination server-side;
- jangan mengirim seluruh katalog ke browser;
- bulk add mengambil seluruh row terpilih dalam satu query, bukan query per ID;
- list/detail memakai eager load/aggregate terukur dan tidak query di Blade;
- daftar Partnership Menu dan Assembly dipaginasi bila jumlahnya dapat berkembang;
- state Livewire menyimpan ID/filter yang diperlukan, bukan snapshot katalog lengkap;
- query count tidak boleh bertambah linear mengikuti jumlah item.

Baseline query count, waktu render, dan ukuran snapshot Livewire dicatat sebelum/sesudah pada Phase performa. Target numerik ditetapkan berdasarkan baseline, bukan angka spekulatif.

## 16. UI/UX

### 16.1 Baseline visual

Mengikuti `ui-consistency-prd.md` dan Project Workspace existing:

- container mengikuti lebar halaman R&D existing;
- section memakai border halus, rounded konsisten, dan tanpa shadow dekoratif;
- spacing menggunakan gap dan hirarki yang konsisten;
- heading hanya satu per konteks utama;
- tombol primary/secondary/danger mengikuti komponen existing;
- tidak membuat kartu statistik jika tidak membantu keputusan user;
- gunakan komponen existing sebelum membuat markup baru.

### 16.2 Header detail

Header menampilkan:

- nama Project;
- badge tipe dengan teks;
- rentang tanggal/status waktu existing;
- nama event hanya untuk Partnership;
- aksi yang diizinkan.

Informasi tidak diulang lagi pada kartu lain tanpa alasan.

### 16.3 Workspace Premix

- satu section utama **BOM Assembly**;
- CTA **Tambah Assembly** berada di header section;
- desktop dapat memakai tabel ringkas;
- mobile berubah menjadi list/card per item, bukan memaksa seluruh halaman scroll horizontal;
- empty state: belum ada Assembly dan CTA bila user memiliki permission.

### 16.4 Workspace Partnership

- section identitas event dibuat ringkas;
- satu section **Menu Partnership** dengan CTA **Tambah Menu**;
- pola list/tabel selaras dengan Internal Memo tetapi tidak menampilkan field Memo yang tidak relevan;
- nama/kode panjang dapat wrap atau truncate dengan akses ke nilai lengkap;
- empty state membedakan “belum ada Menu” dengan “katalog tidak tersedia”.

### 16.5 Modal picker

Gunakan atau kembangkan komponen `rnd/picker-modal` bila kontraknya sesuai.

Modal wajib:

- memiliki judul dan deskripsi konteks;
- autofocus yang wajar tanpa merusak mobile;
- mendukung keyboard, focus trap, Escape, close, dan cancel;
- backdrop/close tidak menyimpan data;
- mempertahankan search/filter ketika validation failure yang relevan;
- menampilkan loading, empty, error, selected count, dan disabled submit;
- mencegah modal bertumpuk;
- menampilkan item yang sudah dipilih sebagai disabled/selected, bukan membuat duplikasi;
- tidak menyebabkan scroll horizontal seluruh viewport.

### 16.6 Responsive dan aksesibilitas

- viewport minimum mobile, tablet, dan desktop diperiksa;
- zoom 200%, nama panjang, empty state, validation error, dan banyak item diuji;
- label, status, dan selection tidak hanya bergantung pada warna;
- target interaksi cukup besar dan urutan tab logis;
- dark mode hanya wajib bila panel/halaman existing mendukungnya.

## 17. Error handling dan observability

- validation error bersifat actionable dan diletakkan dekat input/aksi;
- duplicate karena race condition ditangani sebagai hasil idempotent atau pesan ramah, bukan 500;
- kegagalan transaction tidak menyisakan sebagian item;
- exception tidak menampilkan credential/payload sensitif;
- log mutation memuat project ID, project type, actor ID, jumlah item, dan outcome yang aman;
- kegagalan katalog/upstream dapat dibedakan dari forbidden, validation, dan database conflict;
- error rate, query count, render time, serta Livewire payload menjadi metrik rollout.

## 18. Kompatibilitas dan migrasi data lama

### 18.1 Backfill

- migration menambahkan `project_type` dengan default database `release_product`;
- seluruh Project lama tetap terbaca sebagai Release Product;
- tidak ada Project existing yang otomatis diklasifikasikan sebagai Premix/Partnership;
- tidak ada BOM/Product/Document/Task existing yang dipindah atau dihapus.

### 18.2 Deployment sequencing

Urutan aman:

1. characterization test Release Product;
2. migration kolom tipe/event dan enum/model support;
3. deploy kode yang dapat membaca default;
4. migration/tabel Partnership dan remediasi unique BOM setelah audit;
5. UI Premix/Partnership diaktifkan;
6. smoke test role/scope dan observability;
7. production hanya setelah staging diterima.

Migration DDL dan backfill besar tidak dicampur bila ukuran tabel menuntut strategi terpisah.

### 18.3 Rollback

- rollback aplikasi harus tetap dapat membaca row Release Product existing;
- sebelum rollback migration tipe/tabel, pastikan tidak ada Project Premix/Partnership yang akan menjadi tidak terbaca;
- data tipe baru tidak boleh dihapus hanya agar rollback teknis berhasil;
- jika sudah ada data baru, gunakan roll-forward fix atau nonaktifkan entry point sementara;
- catat backup dan verifikasi count sebelum perubahan constraint BOM.

## 19. Strategi pengujian

Semua test baru menggunakan Pest dan mengikuti pola test existing.

### 19.1 Characterization Release Product

- create/edit/view Project existing tetap sama ketika tipe tidak dikirim;
- Project lama dibaca sebagai Release Product;
- seluruh section dan aksi Release Product tetap muncul;
- permission, route, export, attachment, task, dan archive tidak berubah;
- query/count existing tidak mengalami regresi material.

### 19.2 Migration/model

- default database dan model konsisten;
- enum cast benar;
- event nullable/required sesuai tipe;
- factory memiliki state per tipe;
- relasi dan unique constraint bekerja;
- migration up/down diuji pada skema yang relevan.

### 19.3 Premix

- authorized user dapat menambah Assembly `bom_type_id = 1`;
- non-Assembly ditolak walaupun request dimanipulasi;
- Project non-Premix ditolak;
- duplicate/double submit tidak membuat row kedua;
- BOM sama dapat dipakai pada dua Project berbeda setelah constraint diremediasi;
- bulk add tidak membuat Product/pivot/forecast/material;
- remove hanya berdampak pada Project target;
- snapshot tetap tampil ketika katalog menjadi nonaktif.

### 19.4 Partnership

- nama event wajib;
- authorized user dapat mencari, memilih banyak, menambah, dan menghapus Menu;
- Project non-Partnership ditolak;
- ID di luar scope ditolak;
- duplicate/double submit idempotent;
- snapshot tersimpan benar dan tetap terbaca ketika katalog berubah;
- Add Menu tidak memanggil HTTP, BOM resolver, material expansion, forecast, atau shelf life;
- empty/stale/error state katalog ditampilkan tanpa merusak detail Project;
- bulk add menggunakan jumlah query yang tetap/terukur.

### 19.5 Permission dan scope

- user tanpa permission mendapat forbidden dan tidak hanya tombol tersembunyi;
- user branch/context terbatas hanya melihat katalog yang sah;
- user dengan akses all branch melihat seluruh context yang sah;
- direct Livewire call/action dengan ID asing tetap ditolak.

### 19.6 UI/browser

- create dengan ketiga tipe;
- conditional field nama event;
- open/close/cancel modal;
- search, filter, pagination, multi-select, save, dan validation failure;
- loading mencegah submit ganda;
- tidak ada JavaScript error/console error pada halaman utama;
- mobile/desktop tidak mengalami whole-page horizontal scroll;
- keyboard navigation dan focus return diverifikasi.

Test tidak boleh hanya mengunci seluruh string class Tailwind karena rapuh; prioritaskan perilaku, visibility, state, dan accessibility penting.

## 20. Tahapan implementasi

### Phase 0 — Audit dan characterization

- petakan Page, view, Action, Query, policy, route, model, migration, dan test Project;
- ukur query count dan Livewire payload list/detail existing;
- audit asumsi global unique `rnd_project_boms.esb_bom_id`;
- karakterisasi Add Menu Internal Memo dan sumber katalog;
- kunci behavior Release Product dalam test.

**Selesai jika:** kontrak existing terdokumentasi dengan bukti dan tidak ada perubahan perilaku.

### Phase 1 — Fondasi tipe

- enum tipe;
- migration kolom tipe/event;
- model casts/default/factory states;
- Create/Update Action;
- form create/edit dan filter/badge daftar;
- regression test Release Product.

**Selesai jika:** Project lama aman, create tiga tipe berhasil, tipe immutable.

### Phase 2 — Pemisahan workspace

- pisahkan shared header/task dari konten tipe;
- pertahankan workspace Release Product;
- sediakan shell Premix dan Partnership;
- jangan refactor workflow release yang tidak diperlukan.

**Selesai jika:** tiap tipe hanya melihat section yang relevan dan Release Product tidak berubah.

### Phase 3 — Premix

- audit/remediasi constraint BOM;
- tambah snapshot `bom_type_id`;
- Query picker Assembly;
- Add/Remove Action;
- UI list dan modal;
- permission, duplicate, performance, dan regression test.

**Selesai jika:** hanya Assembly dapat ditambahkan dan BOM sama dapat digunakan Project berbeda tanpa data dummy.

### Phase 4 — Partnership

- buat tabel/model/factory Partnership Menu;
- ekstrak/reuse query katalog secara aman;
- Add/Remove Action;
- UI event dan Menu picker;
- pastikan tidak ada dependency workflow berat Memo;
- permission, snapshot, catalog failure, dan query test.

**Selesai jika:** multi-menu tersimpan ringan dan histori stabil tanpa HTTP pada render/mutation.

### Phase 5 — Konsistensi UI dan hardening

- review responsive, keyboard, focus, empty/error/loading states;
- ukur query/payload sesudah;
- jalankan test terfokus dan modul R&D terkait;
- jalankan Pint bila PHP berubah, `git diff --check`, dan frontend build bila source frontend berubah;
- dokumentasikan rollout dan rollback.

**Selesai jika:** acceptance criteria dan quality gates terpenuhi di staging.

## 21. Kriteria penerimaan

### Produk

- [ ] User dapat membuat Release Product, Premix, dan Partnership.
- [ ] Seluruh Project existing tetap Release Product dan datanya tidak berubah.
- [ ] Tipe tidak dapat diubah setelah create.
- [ ] Premix hanya menerima BOM Assembly (`bom_type_id = 1`).
- [ ] Partnership menyimpan nama event dan banyak Menu.
- [ ] Add Menu Partnership terasa konsisten dengan Internal Memo tetapi tidak membawa proses beratnya.
- [ ] Section yang tidak relevan tidak tampil pada Premix/Partnership.

### Data dan arsitektur

- [ ] Mutation berada di Action, query katalog berada di Query object, dan Blade tidak query database.
- [ ] Unique constraint mencegah duplikasi dan mendukung BOM sama lintas Project.
- [ ] Snapshot menjaga histori saat katalog berubah.
- [ ] Tidak ada data Release Product yang dipindah/dihapus.
- [ ] Tidak ada HTTP ESB pada render halaman atau Add Menu Partnership.
- [ ] Transaction dan authorization server-side diterapkan.
- [ ] Tidak ada N+1 atau payload katalog penuh ke browser.

### UI

- [ ] Badge/filter tipe terbaca tanpa mengandalkan warna.
- [ ] Layout mengikuti Project Workspace, border/rounded konsisten, tanpa shadow kartu biasa.
- [ ] Modal mendukung keyboard, focus, Escape, cancel, loading, empty, dan error state.
- [ ] Mobile tidak mengalami scroll horizontal seluruh halaman.
- [ ] Empty state membedakan belum ada data dan katalog tidak tersedia.

### Quality

- [ ] Characterization dan regression test Release Product lulus.
- [ ] Test Premix, Partnership, permission/scope, constraint, dan failure path lulus.
- [ ] Query/payload sebelum-sesudah dicatat.
- [ ] Pint, test terfokus, `git diff --check`, dan build relevan lulus.
- [ ] Rollout/rollback note tersedia sebelum production.

## 22. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Project lama salah diklasifikasikan | Default DB/model `release_product` dan regression test |
| Refactor detail merusak Release Product | Characterization test, partial extraction bertahap, tidak rewrite |
| BOM sama gagal dipakai lintas Project | Audit unique global lalu ubah ke unique per Project |
| Non-Assembly masuk Premix | Filter katalog + validasi ulang Action + test request manipulasi |
| Partnership menjadi seberat Memo | Tabel snapshot minimum; larang resolver/material/forecast/finalization |
| Katalog bocor lintas context | Query scoped dan authorization ulang di Action |
| Duplicate akibat double-click/race | Unique constraint, transaction, idempotent handling |
| Katalog unavailable membuat detail gagal | Isolasi error pada picker; data tersimpan tetap dari snapshot |
| Page/view makin monolitik | Workspace per tipe dan Action/Query single-purpose |
| Query meningkat seiring item | Aggregate, eager load, pagination, baseline query test |
| Rollback menghilangkan data tipe baru | Non-destructive rollback plan dan prefer roll-forward |

## 23. Keputusan yang masih perlu dikonfirmasi sebelum implementasi

Keputusan berikut tidak menghalangi PRD, tetapi harus ditutup pada Phase 0:

1. label tombol utama: **Buat Project** atau mempertahankan label existing;
2. metadata Menu mana yang benar-benar tersedia dan stabil pada katalog aktif (brand/category/code);
3. aturan urutan Menu/Assembly: waktu ditambahkan atau manual `sort_order`;
4. apakah archive Project existing cukup untuk koreksi salah pilih tipe;
5. batas jumlah item per bulk add berdasarkan baseline katalog dan payload;
6. apakah project task/checkpoint memang berlaku untuk ketiga tipe—PRD ini menetapkan **ya** sebagai default konservatif.

Tidak boleh menambah field kompleks atau workflow baru hanya untuk menutup keputusan yang belum dibutuhkan MVP.

## 24. Kandidat dampak file implementasi

Daftar ini panduan audit, bukan instruksi rename massal:

- `app/Enums/RndProjectType.php`;
- `app/Models/RndProject.php`;
- `app/Models/RndProjectBom.php`;
- model baru Partnership Menu;
- migration baru untuk tipe/event, BOM type/constraint, dan Partnership Menu;
- factory Project/BOM/Partnership Menu;
- `app/Filament/Helpdesk/Resources/Projects/Pages/ListProjects.php`;
- `app/Filament/Helpdesk/Resources/Projects/Pages/ViewProject.php`;
- view daftar/create/detail Project terkait;
- `resources/views/components/rnd/picker-modal.blade.php` hanya jika extension kompatibel diperlukan;
- Action/Query pada namespace R&D yang mengikuti konvensi existing;
- Pest Feature/Unit/Browser tests terkait.

File final ditentukan setelah Phase 0. Jangan memindahkan namespace besar atau menyentuh modul lain tanpa bukti kebutuhan.

## 25. Definition of Done

Fitur dianggap selesai ketika tiga tipe Project tersedia, Project existing tetap berfungsi sebagai Release Product tanpa perubahan data/perilaku, Premix hanya menyimpan Assembly secara aman, Partnership menyimpan event dan daftar Menu melalui katalog lokal yang ringan, permission dan scope diuji server-side, UI konsisten dan responsif, performa terukur, seluruh quality gate relevan lulus, serta rollout/rollback telah diverifikasi di staging.

Penyelesaian PRD ini sendiri hanya menghasilkan dokumen kebutuhan. PRD tidak berarti implementasi, commit, push, migration, seeding, atau deployment telah dilakukan.
