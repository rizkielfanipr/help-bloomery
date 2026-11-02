---
document_id: BRAND-MARKETING-DESIGN-MASTER-LIBRARY-PRD
title: PRD — Master Design Library Berbasis Brand
status: draft
version: 1.0
owner: Brand Marketing
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/brand-marketing/README.md
  - docs/products/purchasing/README.md
  - docs/products/rnd/README.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# PRD — Master Design Library Berbasis Brand

> Dokumen ini adalah kontrak planning. Dokumen belum mengotorisasi migration production, perubahan role production, pemindahan file existing, atau deployment.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Brand Marketing |
| Fitur | Master Design Library Berbasis Brand |
| Status | draft |
| Prioritas | P1 |
| Target pengguna | Designer Marketing dan Purchasing |
| Modul terdampak | Brand Marketing; Purchasing sebagai consumer |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

File desain marketing saat ini terutama disimpan sebagai material milik produk Project R&D, sehingga belum ada katalog reusable lintas project berdasarkan Brand. Fitur ini menyediakan Master Design Library sebagai sumber kebenaran desain, versi, spesifikasi, dan file privat per Brand. Designer mengelola lifecycle draft hingga published, sedangkan Purchasing melihat desain published dan mengunduh file production yang tepat. Data Project R&D dan fulfillment existing tidak dipindahkan atau diubah pada versi pertama.

## 3. Latar belakang

Satu Brand dapat memiliki bundle desain reguler, seasonal, campaign, product launch, store opening, partnership, dan event. Setiap bundle dapat berisi packaging, sticker, katalog, social media, foto, serta file pendukung. Tanpa master terpusat, file berisiko tersebar per project, tertimpa revisi, sulit dipastikan versi aktifnya, dan tidak mempunyai spesifikasi produksi yang konsisten bagi Purchasing.

Fitur harus menjaga pemisahan tanggung jawab:

- Brand Marketing memiliki aset kreatif dan lifecycle revisinya;
- Purchasing mengonsumsi versi published untuk kebutuhan produksi;
- transaksi order/fulfillment bukan data master;
- histori project/order lama tidak berubah ketika master menerbitkan revisi baru.

## 4. Baseline sistem

1. Master `Brand` existing hanya menyimpan nama dan relasi branch; belum mempunyai relasi desain.
2. Brand Marketing existing menangani Permintaan Design, Kategori Design, dan Permintaan Konten.
3. `RndProjectMarketingMaterial` menyimpan satu file desain per produk Project R&D dengan tipe packaging, sticker, foto, social media, katalog, atau lainnya.
4. Upload Project R&D memakai disk aplikasi `b2`, path per project/product, private temporary URL, serta metadata nama, MIME, ukuran, dan uploader.
5. File Project R&D saat ini dapat dihapus bersama object storage dari halaman produk; perilaku tersebut tidak aman untuk file master bersama dan tidak boleh disalin ke Master Design.
6. Hanya tipe fisik `packaging_design` dan `sticker` yang masuk antrean `MarketingMaterialFulfillment` untuk Purchasing dan Inventory.
7. `MarketingMaterialFulfillment` terkait langsung ke `RndProjectMarketingMaterial`; belum dapat menjadi transaksi dari Master Design.
8. Permission Brand Marketing existing hanya menyediakan `upload marketing materials` untuk material Project R&D.
9. Purchasing sudah mempunyai permission melihat/memproses fulfillment, tetapi belum mempunyai katalog desain reusable.
10. Sidebar Back Office bersifat custom dan item menu wajib ditambah eksplisit dengan permission yang sesuai.
11. Penyimpanan privat dan temporary URL sudah menjadi pola existing; provider aktual mengikuti konfigurasi disk dan tidak diasumsikan dari nama UI.

## 5. Problem statement

Belum ada sumber kebenaran yang dapat menjawab:

- bundle desain apa saja yang dimiliki suatu Brand;
- versi mana yang aktif dan boleh digunakan Purchasing;
- item serta file apa yang termasuk dalam satu bundle;
- mana file source, preview, production, dan supporting;
- spesifikasi fisik apa yang harus dipakai saat produksi;
- siapa yang membuat, mereview, menerbitkan, atau mengarsipkan versi;
- bagaimana mempertahankan histori saat desain direvisi;
- bagaimana mencegah file master terhapus oleh penggunaan project/transaksi.

## 6. Goals

1. Menyediakan katalog bundle desain yang wajib dimiliki tepat satu Brand.
2. Menyediakan revisioning immutable untuk versi yang sudah published.
3. Menyimpan item, spesifikasi, dan file privat dengan metadata serta audit yang lengkap.
4. Memisahkan hak Designer dan Purchasing secara server-side.
5. Memungkinkan Purchasing menemukan, melihat, dan mengunduh file production versi published.
6. Menjaga kompatibilitas data Project R&D dan fulfillment existing.
7. Menyediakan fondasi aman untuk integrasi project/order pada fase berikutnya.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Bundle baru yang mempunyai Brand | Tidak ada master bundle | 100% | Constraint database dan feature test |
| Published version yang berubah in-place | Belum berlaku | 0 | Audit log dan mutation test |
| Download production tanpa authorization | Belum ada endpoint master | 0 | Authorization test |
| Published physical item dengan file/spec tidak lengkap | Belum berlaku | 0 | Publish guard test |
| File master yang terhapus akibat archive/project | Belum berlaku | 0 | File lifecycle test dan UAT |
| Query tambahan per item pada index | Belum ada | Tidak ada N+1 | Query/performance test |

## 8. Non-goals

- Mengganti Permintaan Design atau Permintaan Konten.
- Memigrasikan atau menghapus `RndProjectMarketingMaterial` existing.
- Mengubah `MarketingMaterialFulfillment` existing.
- Membuat transaksi order, vendor, harga, atau Receiving dari Master Design pada versi pertama.
- Menautkan Master Design ke Project R&D pada versi pertama.
- Membuat bundle multi-brand; campaign lintas brand tetap berupa bundle terpisah per Brand.
- Menggunakan branch access sebagai pengganti Brand scope.
- Menyediakan editor desain di browser, DAM eksternal, public CDN, atau integrasi Adobe/Figma.
- Menambah dependency penyimpanan atau frontend.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission rencana |
|---|---|---|
| Designer Marketing | Membuat bundle/version, item, spesifikasi, dan file | `view design master bundles`, `create design master bundles`, `edit design master bundles`, `upload design master files` |
| Reviewer Brand Marketing | Review dan memberi keputusan | `review design master bundles` |
| Publisher Brand Marketing | Publish revisi dan archive bundle | `publish design master bundles`, `archive design master bundles` |
| Purchasing | Melihat published version dan mengunduh file production | `view design master bundles`, `download design master production files` |
| Administrator | Memberikan permission melalui Role & Permission | Permission management existing |

Tidak ada permission hard delete pada versi pertama.

## 10. User journey

### 10.1 Designer

1. Membuka **Brand Marketing → Master Design**.
2. Membuat bundle dan memilih satu Brand existing.
3. Mengisi kode, nama, kategori, periode, dan deskripsi.
4. Sistem membuat Version 1 berstatus `draft`.
5. Designer menambahkan item, spesifikasi, preview, source, production, dan supporting file.
6. Designer menekan **Kirim untuk Review**.
7. Reviewer menyetujui atau mengembalikan dengan alasan.
8. Publisher menerbitkan versi yang disetujui.
9. Untuk perubahan berikutnya, Designer membuat revisi baru dari published version; versi lama tidak diedit.

### 10.2 Purchasing

1. Membuka menu Master Design dengan permission view.
2. Secara default hanya melihat bundle aktif dengan published version.
3. Memfilter Brand, kategori, tipe item, dan fisik/digital.
4. Membuka detail bundle dan memilih item fisik.
5. Melihat preview serta spesifikasi produksi.
6. Mengunduh file berjenis `production` melalui URL sementara setelah authorization server-side.
7. Purchasing tidak dapat melihat/download source file tanpa permission source yang terpisah dan tidak dapat mengubah master.

## 11. Business rules

1. Setiap bundle wajib dimiliki tepat satu record `Brand` existing.
2. Kode bundle dinormalisasi dan unik case-insensitive dalam satu Brand; kode yang sama boleh dipakai Brand lain.
3. Kategori bundle versi pertama: `regular`, `seasonal`, `campaign`, `product_launch`, `store_opening`, `partnership`, `event`, `other`.
4. Satu bundle dapat memiliki banyak revisi, tetapi maksimal satu published version aktif.
5. Version number meningkat monoton mulai dari 1 dan unik per bundle.
6. Published version bersifat immutable; perubahan membuat revisi draft baru.
7. Publish revisi baru menandai published version lama sebagai `superseded` dalam transaksi yang sama.
8. Revisi baru dapat meng-clone metadata item/spec dari versi published, tetapi tidak membuat salinan binary file tanpa kebutuhan; referensi file yang dipakai ulang harus eksplisit dan aman.
9. Satu version wajib mempunyai minimal satu item sebelum review/publish.
10. Vocabulary tipe material mengikuti tipe marketing material existing agar tidak membuat label paralel tanpa alasan.
11. Item fisik ditentukan server-side dari tipe yang disepakati, bukan checkbox browser yang bebas dimanipulasi.
12. Item fisik wajib mempunyai spesifikasi produksi dan minimal satu file `production` sebelum publish.
13. Setiap item published wajib mempunyai minimal satu preview yang aman ditampilkan; source/supporting bersifat opsional sesuai kebutuhan.
14. File dibedakan sebagai `source`, `preview`, `production`, atau `supporting`.
15. File tidak pernah ditimpa pada path yang sama; upload revisi membuat record/path baru.
16. Download selalu melalui endpoint/action terotorisasi dan temporary URL berumur pendek.
17. Source file tidak dapat diunduh hanya dengan permission view atau download production.
18. Bundle archived tidak muncul pada daftar default Purchasing dan tidak dapat membuat revisi/publish baru sampai dipulihkan oleh permission yang sesuai.
19. Archive tidak menghapus version, item, file, atau audit log.
20. Hard delete bundle/version/file tidak tersedia di UI versi pertama.
21. Brand scope tidak mengikuti branch user. User dengan permission view dapat melihat Brand sesuai kebijakan permission yang diterapkan; scope per Brand dapat ditambahkan kemudian bila ada kebutuhan terbukti.
22. Data Project R&D existing tetap menjadi sumber untuk project lama dan tidak otomatis ditafsirkan sebagai master.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Menyediakan menu Brand Marketing → Master Design | Must |
| FR-2 | Bundle wajib memilih satu Brand existing | Must |
| FR-3 | Mendukung pencarian/filter Brand, kategori, status, tipe, dan fisik/digital | Must |
| FR-4 | Mendukung lifecycle draft, review, approved, published, rejected, superseded | Must |
| FR-5 | Mendukung item dan spesifikasi terstruktur per version | Must |
| FR-6 | Mendukung file source, preview, production, dan supporting secara privat | Must |
| FR-7 | Published version immutable dan revisi baru dapat dibuat dari versi published | Must |
| FR-8 | Purchasing hanya melihat published version secara default | Must |
| FR-9 | Purchasing dapat mengunduh production file dengan permission khusus | Must |
| FR-10 | Source download mempunyai permission terpisah | Must |
| FR-11 | Menampilkan histori version dan audit actor/waktu/alasan | Must |
| FR-12 | Archive tidak menghapus file atau histori | Must |
| FR-13 | Preview image/PDF dimuat secara lazy melalui URL sementara | Should |
| FR-14 | Notifikasi internal dikirim saat submit review, revision required, dan publish | Should |
| FR-15 | Menampilkan jumlah bundle/item tanpa N+1 | Must |

## 13. Status dan lifecycle

### 13.1 Bundle

| Status | Makna | Transisi |
|---|---|---|
| `active` | Bundle dapat mempunyai draft/published version | `archived` |
| `archived` | Tidak dipakai untuk penggunaan baru; histori tetap tersedia | `active` melalui restore |

### 13.2 Bundle version

| Status | Makna | Transisi yang diizinkan | Aktor |
|---|---|---|---|
| `draft` | Sedang disusun | `in_review` | Designer |
| `in_review` | Menunggu reviewer | `approved`, `revision_required` | Reviewer |
| `revision_required` | Perlu diperbaiki | `draft`, lalu `in_review` | Designer |
| `approved` | Lulus review, menunggu publish | `published`, `revision_required` | Publisher/Reviewer |
| `published` | Versi aktif untuk consumer | `superseded` otomatis saat versi baru publish | Sistem |
| `superseded` | Versi lama, read-only | Terminal | Sistem |

Rejection non-final direpresentasikan sebagai `revision_required` agar histori tetap jelas dan draft dapat diperbaiki. Pembatalan draft dapat ditambahkan kemudian bila kebutuhan operasional terbukti.

## 14. Model data konseptual

### 14.1 `MarketingDesignBundle`

- `brand_id` wajib;
- normalized `code` dan `name`;
- category, description, validity period;
- `status` active/archived;
- creator/updater/archive metadata;
- unique `(brand_id, normalized_code)`.

### 14.2 `MarketingDesignBundleVersion`

- bundle ID dan version number;
- lifecycle status;
- change summary dan review/publish notes;
- creator, reviewer, publisher, timestamps;
- active/published uniqueness dijaga dengan constraint/transaction yang sesuai database existing.

### 14.3 `MarketingDesignItem`

- version ID;
- type, title, description, sort order;
- structured production fields: dimensions, material specification, finishing, color/print notes, sides, dan notes;
- sifat fisik diturunkan dari type pada server.

### 14.4 `MarketingDesignFile`

- item ID dan file role;
- private storage disk/path;
- original name, MIME, extension, size, checksum;
- uploader dan upload timestamp;
- optional reused-from file ID bila revision memakai binary yang sama;
- tidak ada public URL tersimpan.

### 14.5 `MarketingDesignAuditLog`

- bundle/version/item context;
- event, actor, from/to, reason, metadata aman, timestamp;
- append-only dan tidak menyimpan signed URL.

Relasi baru ke `Brand` ditambahkan. Tidak ada foreign key ke project/fulfillment pada versi pertama.

## 15. Integration contract

Not applicable — fitur versi pertama tidak memanggil ESB atau layanan desain eksternal. Object storage diakses melalui Laravel Storage disk existing. Provider, credential, dan bucket tidak ditulis dalam dokumentasi.

## 16. Authorization dan security

1. Seluruh route/Page/Resource/Action memakai permission server-side; visibility tombol bukan kontrol keamanan.
2. Purchasing tidak memperoleh permission edit/upload/review/publish hanya karena dapat view/download production.
3. Download memeriksa record, version status, file role, dan permission sebelum membuat temporary URL.
4. ID/path dari browser tidak boleh langsung dipakai untuk membaca object storage.
5. Original filename disanitasi untuk response header; path dibentuk server-side.
6. Validasi memakai MIME dan extension yang disepakati; preview hanya untuk format aman.
7. SVG tidak dirender inline tanpa sanitization; default diperlakukan sebagai download atau dikonversi ke preview aman di luar scope.
8. ZIP/source tidak diekstrak oleh aplikasi pada versi pertama.
9. Checksum membantu mendeteksi binary sama, tetapi tidak digunakan untuk melewati authorization.
10. Tidak ada hard delete UI; operasi cleanup storage harus melalui audit terpisah.
11. Permission baru ditambahkan ke `config/permissions.php`; pemberian ke role production dilakukan melalui Role & Permission, bukan menjalankan seeder.

## 17. UI/UX specification

### 17.1 Lokasi

Canonical menu: **Back Office → Brand Marketing → Master Design**. Purchasing memakai halaman yang sama dengan permission terbatas; tidak dibuat database atau halaman master kedua di grup Purchasing.

### 17.2 Index

Gunakan mode list/table karena pengguna membandingkan Brand, kode, kategori, version, jumlah item, status, dan tanggal publish.

Kolom minimum:

- Brand;
- kode dan nama bundle;
- kategori;
- current published version;
- jumlah item;
- status bundle/version;
- tanggal publish;
- updated at untuk draft.

Filter: Semua Brand (default), Brand tertentu, kategori, status, physical/digital, tipe item, periode, dan search kode/nama.

### 17.3 Detail

Urutan:

1. identitas Brand dan bundle;
2. status/current version dan aksi sesuai permission;
3. daftar item dengan preview;
4. spesifikasi produksi;
5. file per role;
6. revision history;
7. audit ringkas.

Purchasing melihat published version default. Designer dapat memilih draft/revisi dan membandingkan metadata tanpa diff visual binary.

### 17.4 Form/version editor

- Form dibagi menjadi Informasi Bundle, Informasi Version, Item, Spesifikasi, dan File.
- Item panjang memakai repeater/list server-driven yang stabil; upload tidak hilang saat validasi gagal.
- Loading/progress, jenis file, batas ukuran, error, retry, dan cancel harus terlihat.
- Mutation dicegah dari double submit.
- Draft dapat disimpan tanpa memenuhi publish guard; guard lengkap dijalankan saat submit review/publish.

### 17.5 Responsive dan aksesibilitas

- Periksa viewport 360, 390, 768, 1024, 1440 px dan zoom 200%.
- Tabel hanya scroll pada container, bukan seluruh halaman.
- Modal/detail file mengikuti fokus, Escape, label, dan scroll internal sesuai standar UI.
- Status selalu mempunyai teks, bukan warna saja.
- Preview besar dimuat lazy dan mempunyai fallback untuk file non-previewable.

## 18. File storage contract

### 18.1 Path

Path dibentuk server-side dengan ID, bukan nama Brand/bundle:

```text
brand-marketing/design-master/
└── brand-{brand_id}/
    └── bundle-{bundle_id}/
        └── version-{version_id}/
            └── item-{item_id}/
                ├── source/
                ├── preview/
                ├── production/
                └── supporting/
```

### 18.2 File role dan format awal

| Role | Tujuan | Format baseline |
|---|---|---|
| Preview | Ditampilkan di UI | JPG, JPEG, PNG, WEBP, PDF |
| Production | File resmi untuk Purchasing/vendor | PDF dan ZIP |
| Source | File editable/original | ZIP sebagai container aman pada versi pertama |
| Supporting | Guide/mockup/dokumen pendukung | PDF, JPG, JPEG, PNG, WEBP, ZIP |

Ukuran maksimum final wajib diverifikasi terhadap limit Livewire, PHP, reverse proxy, dan object storage saat Phase 0. Baseline proposal: preview 15 MB/file; production/source/supporting 100 MB/file. Jika infrastruktur tidak mendukung, nilai diturunkan dan dicatat sebelum implementasi, bukan diam-diam saat coding.

### 18.3 Lifecycle

- Upload baru ditulis ke path unik.
- Database record dibuat hanya setelah storage berhasil; kegagalan database membersihkan object baru yang belum direferensikan.
- Published/superseded/archive tidak menghapus object.
- Temporary URL maksimum mengikuti pola download existing dan tidak disimpan.
- Cleanup orphan hanya melalui command/report terpisah setelah reference audit; tidak termasuk versi pertama.

## 19. Non-functional requirements

1. Index memakai pagination server-side, eager loading, dan aggregate count tanpa N+1.
2. Detail memuat preview/file secara lazy; binary tidak dibawa dalam state Livewire.
3. Publish/revision memakai transaction dan row lock agar tidak ada dua published version.
4. Upload gagal tidak meninggalkan record setengah jadi; cleanup object mengikuti transaction boundary yang aman.
5. Tidak ada dependency baru.
6. Semua data status/role file memakai Enum atau sumber label terpusat.
7. Nama panjang dan metadata besar tidak menyebabkan payload/DOM berlebihan.

## 20. Observability dan audit

Audit event minimum:

- bundle created/updated/archived/restored;
- version created/submitted/revision-required/approved/published/superseded;
- item added/updated/removed pada draft;
- file uploaded/replaced pada draft;
- production/source file downloaded, dengan actor dan file ID tanpa signed URL;
- failed upload/publish guard sebagai application log aman bila perlu.

Dashboard monitoring baru tidak diperlukan. Error storage mengikuti logging/monitoring aplikasi existing dan tidak boleh mencetak credential atau signed URL.

## 21. Data migration dan kompatibilitas

1. Migration hanya menambah tabel/constraint/index baru dan relasi `Brand`.
2. Tidak ada perubahan schema/data `rnd_project_marketing_materials` atau `marketing_material_fulfillments` pada versi pertama.
3. Tidak ada backfill file Project R&D menjadi master secara otomatis.
4. Data demo/seed hanya untuk development/test dan tidak boleh mengubah role production.
5. Index ditambahkan berdasarkan query utama: Brand/status/category, bundle/version, item type, dan audit context; hindari indeks duplikat.
6. Data lama tetap dapat diunggah, dilihat, dipesan, dan diterima melalui workflow existing.

## 22. Testing strategy

1. Factory dan model tests untuk relasi, enum cast, uniqueness, dan published invariant.
2. Authorization test untuk Designer, Reviewer, Publisher, Purchasing, user tanpa permission, serta SUPERADMIN.
3. Workflow test create draft, submit, revision required, approve, publish, new revision, supersede, archive, dan restore.
4. Concurrency/idempotency test untuk publish dan double submit.
5. File test dengan fake disk: MIME/extension/size, private path, metadata, checksum, rollback cleanup, dan no hard delete.
6. Download test production vs source permission, archived/superseded history, tampered ID/path, dan safe filename.
7. Filament/Livewire test index, filters, pagination, actions, empty/loading/error state, dan permission visibility.
8. Regression test upload/fulfillment Project R&D existing tidak berubah.
9. Query count/performance test untuk list dengan banyak bundle/item/version.
10. Visual/manual UAT responsive, keyboard, long names, unavailable preview, failed upload, dan dark mode.

## 23. Rollout

1. Deploy migration additive dan aplikasi.
2. Jalankan permission sync melalui pipeline existing.
3. Berikan permission melalui Role & Permission: Designer manage/upload; reviewer/publisher sesuai keputusan organisasi; Purchasing view/download production.
4. Verifikasi private storage dengan file uji nonproduction di staging.
5. UAT create → review → publish → Purchasing download.
6. Verifikasi Project R&D/fulfillment existing sebagai regression smoke test.
7. Observasi error storage, authorization denial, latency, dan orphan upload.

Detail ada di [Rollout Runbook](rollout.md).

## 24. Rollback

1. Cabut permission menu/aksi baru untuk menghentikan penggunaan.
2. Rollback aplikasi ke artifact sebelumnya; tabel/file baru tetap dipertahankan.
3. Jangan menghapus file atau tabel sebagai rollback otomatis.
4. Drop schema/storage hanya melalui release terpisah setelah backup dan reference audit.
5. Tidak ada rollback ESB atau Project R&D karena fitur tidak memutasinya.

## 25. Acceptance criteria

1. Bundle tidak dapat dibuat tanpa Brand valid.
2. Kode bundle unik dalam Brand dan boleh sama pada Brand berbeda.
3. Hanya satu published version aktif per bundle.
4. Published version tidak dapat diedit atau dihapus.
5. Publish baru men-supersede versi lama secara atomic.
6. Physical item tidak dapat published tanpa spesifikasi dan production file.
7. Purchasing hanya melihat published bundle default dan tidak dapat mutation master.
8. Production/source download mengikuti permission terpisah dan authorization server-side.
9. File privat memakai path unik, metadata, checksum, dan temporary URL.
10. Archive tidak menghapus file/version/audit.
11. Index/filter/detail responsive dan tidak mempunyai N+1.
12. Project R&D serta fulfillment existing tetap berfungsi tanpa perubahan data.
13. Tidak ada public file URL, hard delete UI, dependency baru, atau integrasi eksternal.

## 26. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Published file ditimpa | Vendor memakai desain salah | Immutable version dan unique path |
| Purchasing mendapat source file | Kebocoran aset editable | Permission file-role terpisah |
| Archive menghapus object | Histori hilang | No hard delete; lifecycle test |
| Dua version published | Sumber kebenaran ambigu | Transaction, row lock, constraint, concurrency test |
| Upload besar gagal | Draft tidak konsisten/orphan | Audit infra, progress/error, compensating cleanup |
| Vocabulary material terduplikasi | Filter/workflow berbeda | Reuse/centralize vocabulary existing dengan regression test |
| Master dianggap transaksi order | Domain tercampur | Order/fulfillment menjadi fitur terpisah |
| Data project lama dimigrasi otomatis | File/link rusak | No backfill pada versi pertama |

## 27. Open decisions

| No | Keputusan | Rekomendasi/default planning | Pemilik | Batas |
|---|---|---|---|---|
| 1 | Siapa reviewer dan publisher secara organisasi? | Permission terpisah; boleh user berbeda atau orang sama | Brand Marketing | Sebelum pemberian role staging |
| 2 | Limit file final setelah audit infrastruktur | Preview 15 MB; lainnya 100 MB per file | IT + Brand Marketing | Phase 0 implementasi |
| 3 | Apakah source selain ZIP perlu didukung langsung? | V1 source wajib ZIP; format native ditinjau setelah UAT | Brand Marketing | Sebelum implementasi upload |

Keputusan tersebut tidak mengubah arsitektur utama dan dapat dikunci pada Phase 0 sebelum application code terkait ditulis.

## 28. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Audit infra storage, format/limit, role, query, dan characterization existing | Baseline dan keputusan final upload/role |
| 1 | Schema, model, enum, policy, permission, factory, audit | Domain foundation dan migration additive |
| 2 | File service dan lifecycle | Private upload/download, metadata, checksum, cleanup compensation |
| 3 | Workflow version | Draft, review, approval, publish, supersede, archive/restore |
| 4 | UI shared | Index/detail/editor untuk Designer dan read-only Purchasing |
| 5 | Delivery | Tests, responsive UAT, docs aktual, report, rollout evidence |
| Future | Project selection dan order/fulfillment dari master | PRD terpisah setelah library stabil |

## 29. Definition of Done

- [ ] Semua acceptance criteria terpenuhi.
- [ ] Open decision Phase 0 dicatat pada implementation report.
- [ ] Test workflow, authorization, file lifecycle, concurrency, dan regression lulus.
- [ ] Migration additive dan rollback dipahami.
- [ ] Permission staging diberikan dan diuji tanpa seeder production.
- [ ] README Brand Marketing/Purchasing dan arsitektur diperbarui sesuai implementasi aktual.
- [ ] Implementation report mencatat deviasi, limit file final, bukti UAT, dan risiko lanjutan.
