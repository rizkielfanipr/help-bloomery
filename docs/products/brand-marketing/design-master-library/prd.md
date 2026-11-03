---
document_id: BRAND-MARKETING-DESIGN-MASTER-LIBRARY-PRD
title: PRD — Master Design Library Berbasis Brand
status: implemented
version: 2.4
owner: Brand Marketing
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-03
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

> Implementasi selesai pada branch fitur; UAT staging belum dijalankan. Dokumen ini tidak mengotorisasi migration production, perubahan role production, pemindahan file existing, atau deployment.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Brand Marketing |
| Fitur | Master Design Library Berbasis Brand |
| Status | implemented |
| Prioritas | P1 |
| Target pengguna | Designer Marketing dan Purchasing |
| Modul terdampak | Brand Marketing; Purchasing sebagai consumer |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

### Riwayat perubahan

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 2026-11-02 | Planning dengan version, review, dan publish |
| 1.1 | 2026-11-03 | Keputusan Phase 0; periode berlaku dan ringkasan Version 1 dihapus dari form |
| 2.0 | 2026-11-03 | Atas keputusan user, fitur disederhanakan menjadi penyimpanan file desain: version, revisi, review, publish, dan notifikasinya dihapus sebelum merge |
| 2.4 | 2026-11-03 | Card Audit Activity dihapus dari halaman detail; audit tetap dicatat |
| 2.3 | 2026-11-03 | Index memakai pola list Project R&D, menggantikan tabel Filament |
| 2.2 | 2026-11-03 | Item dihapus permanen beserta file dan object-nya lewat tombol Hapus; tombol Unduh selalu tampil dan Unggah File tersedia untuk item tanpa file |
| 2.1 | 2026-11-03 | Jenis file dihapus: satu file desain per item diunggah saat item ditambah; satu permission unduh; spesifikasi produksi diisi terpisah setelah item ada; card Design Files dihapus |

## 2. Executive summary

File desain marketing saat ini terutama disimpan sebagai material milik produk Project R&D, sehingga belum ada katalog reusable lintas project berdasarkan Brand. Fitur ini menyediakan Master Design Library sebagai **penyimpanan file desain per Brand**: bundle berisi item, dan setiap item mempunyai satu file desain privat serta spesifikasi produksi opsional. Designer mengelola isinya secara langsung tanpa alur persetujuan, sedangkan Purchasing melihat bundle aktif dan mengunduh file desain. Data Project R&D dan fulfillment existing tidak dipindahkan atau diubah.

## 3. Latar belakang

Satu Brand dapat memiliki bundle desain reguler, seasonal, campaign, product launch, store opening, partnership, dan event. Setiap bundle dapat berisi packaging, sticker, katalog, social media, foto, serta file pendukung. Tanpa penyimpanan terpusat, file tersebar per project dan tidak mempunyai spesifikasi produksi yang konsisten bagi Purchasing.

Brand Marketing memutuskan bahwa kebutuhan saat ini adalah penyimpanan, bukan alur persetujuan. Isi bundle yang tersimpan dianggap berlaku; perubahan dilakukan dengan mengganti file atau item secara langsung, dan jejaknya tercatat di audit.

## 4. Baseline sistem

1. Master `Brand` existing hanya menyimpan nama dan relasi branch; belum mempunyai relasi desain.
2. Brand Marketing existing menangani Permintaan Design, Kategori Design, dan Permintaan Konten.
3. `RndProjectMarketingMaterial` menyimpan satu file desain per produk Project R&D dengan tipe packaging, sticker, foto, social media, katalog, atau lainnya.
4. Upload Project R&D memakai disk aplikasi `b2`, path per project/product, private temporary URL, serta metadata nama, MIME, ukuran, dan uploader.
5. File Project R&D saat ini dapat dihapus bersama object storage dari halaman produk; perilaku tersebut tidak aman untuk file master bersama dan tidak disalin ke Master Design.
6. Hanya tipe fisik `packaging_design` dan `sticker` yang masuk antrean `MarketingMaterialFulfillment`.
7. Sidebar Back Office bersifat custom dan item menu wajib ditambah eksplisit dengan permission yang sesuai.

## 5. Problem statement

Belum ada tempat yang dapat menjawab:

- bundle desain apa saja yang dimiliki suatu Brand;
- item serta file apa yang termasuk dalam satu bundle;
- spesifikasi fisik apa yang harus dipakai saat produksi;
- siapa yang mengunggah, mengganti, atau mengarsipkan isi bundle;
- bagaimana mencegah file master terhapus oleh penggunaan project/transaksi.

## 6. Goals

1. Menyediakan penyimpanan bundle desain yang wajib dimiliki tepat satu Brand.
2. Menyimpan item, spesifikasi, dan file privat dengan metadata serta audit yang lengkap.
3. Memisahkan hak Designer dan Purchasing secara server-side.
4. Memungkinkan Purchasing menemukan, melihat, dan mengunduh file desain.
5. Menjaga kompatibilitas data Project R&D dan fulfillment existing.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Bundle baru yang mempunyai Brand | Tidak ada master bundle | 100% | Constraint database dan feature test |
| Download file tanpa authorization | Belum ada endpoint master | 0 | Authorization test |
| File master yang terhapus akibat archive/ganti file | Belum berlaku | 0 | File lifecycle test dan UAT |
| Query tambahan per item pada index/detail | Belum ada | Tidak ada N+1 | Query/performance test |

## 8. Non-goals

- Version, revisi, review, approval, atau publish.
- Mengganti Permintaan Design atau Permintaan Konten.
- Memigrasikan atau menghapus `RndProjectMarketingMaterial` existing, atau mengubah `MarketingMaterialFulfillment`.
- Membuat order, vendor, harga, atau Receiving dari Master Design, atau menautkannya ke Project R&D.
- Bundle multi-brand; campaign lintas brand berupa bundle terpisah per Brand.
- Editor desain di browser, DAM eksternal, public CDN, integrasi Adobe/Figma, atau dependency baru.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Designer Marketing | Membuat bundle, mengelola item, spesifikasi, dan file | `view design master bundles`, `create design master bundles`, `edit design master bundles`, `upload design master files` |
| Pengelola arsip | Mengarsipkan dan memulihkan bundle | `archive design master bundles` |
| Purchasing | Melihat bundle aktif dan mengunduh file desain | `view design master bundles`, `download design master files` |
| Administrator | Memberikan permission melalui Role & Permission | Permission management existing |

Tidak ada permission hapus terpisah: menghapus item permanen memakai `edit design master bundles`, sedangkan bundle hanya dapat diarsipkan.

## 10. User journey

### 10.1 Designer

1. Membuka **Brand Marketing → Master Design**.
2. Membuat bundle: Brand, kode, kategori, nama, dan deskripsi.
3. Menambahkan item: tipe, nama, deskripsi, dan langsung mengunggah file desainnya.
4. Mengisi spesifikasi produksi item fisik kapan saja setelah item ada.
5. Mengganti file, mengubah, atau menghapus item secara permanen kapan saja selama bundle aktif.

### 10.2 Purchasing

1. Membuka menu Master Design dengan permission view.
2. Melihat bundle aktif; bundle arsip tidak tampil.
3. Memfilter Brand, kategori, tipe item, dan fisik/digital.
4. Membuka detail bundle, melihat preview serta spesifikasi produksi.
5. Mengunduh file desain melalui URL sementara setelah authorization server-side.
6. Tidak dapat mengubah data.

## 11. Business rules

1. Setiap bundle wajib dimiliki tepat satu `Brand` existing. Brand tidak dapat diubah setelah bundle dibuat karena menjadi identitas bundle dan bagian path file.
2. Kode bundle dinormalisasi dan unik case-insensitive dalam satu Brand; kode yang sama boleh dipakai Brand lain.
3. Kategori bundle: `regular`, `seasonal`, `campaign`, `product_launch`, `store_opening`, `partnership`, `event`, `other`.
4. Isi bundle aktif dapat diubah langsung oleh pemegang permission; tidak ada status persetujuan.
5. Vocabulary tipe item mengikuti tipe marketing material existing.
6. Item fisik ditentukan server-side dari tipe (Design Packaging, Sticker), bukan input browser.
7. Setiap item mempunyai tepat satu file desain aktif yang wajib diunggah saat item ditambah. File tidak dibedakan per jenis.
8. Spesifikasi produksi tidak ditanyakan saat menambah item; diisi terpisah untuk item fisik. Item fisik tanpa dimensi atau material ditandai **Belum Lengkap** secara informatif.
9. File tidak pernah ditimpa pada path yang sama; mengganti file membuat record dan path baru, dan file lama tetap tersimpan.
10. Download selalu melalui endpoint terotorisasi dan temporary URL berumur pendek.
12. Bundle archived tidak dapat diubah dan tidak terlihat oleh pengguna noninternal sampai dipulihkan.
13. Archive tidak menghapus item, file, atau audit log. Bundle tidak dapat dihapus. **Hapus item** bersifat permanen: record item, file desainnya, file lama hasil penggantian, dan object-nya di penyimpanan ikut terhapus dan tidak dapat dikembalikan; audit mencatat aktor, waktu, nama item, dan jumlah file yang terhapus.
14. Brand scope tidak mengikuti branch user.
15. Data Project R&D existing tidak otomatis ditafsirkan sebagai master.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Menyediakan menu Brand Marketing → Master Design | Must |
| FR-2 | Bundle wajib memilih satu Brand existing | Must |
| FR-3 | Mendukung pencarian/filter Brand, kategori, status, tipe, dan fisik/digital | Must |
| FR-4 | Mendukung item dan spesifikasi terstruktur per bundle | Must |
| FR-5 | Setiap item mempunyai satu file desain privat yang diunggah saat item ditambah | Must |
| FR-6 | Purchasing hanya melihat bundle aktif | Must |
| FR-7 | Unduhan memerlukan permission `download design master files` | Must |
| FR-8 | Spesifikasi produksi diisi terpisah setelah item ada | Must |
| FR-9 | Mencatat audit actor/waktu/alasan di database; audit tidak ditampilkan di halaman detail | Must |
| FR-10 | Archive tidak menghapus file atau histori | Must |
| FR-11 | Preview image/PDF dimuat secara lazy melalui URL sementara | Should |
| FR-12 | Menampilkan jumlah bundle/item tanpa N+1 | Must |

## 13. Status bundle

| Status | Makna | Transisi |
|---|---|---|
| `active` | Bundle dapat diubah dan terlihat oleh Purchasing | `archived` |
| `archived` | Tidak dipakai untuk penggunaan baru; isi dan histori tetap tersimpan | `active` melalui restore |

## 14. Model data

### 14.1 `MarketingDesignBundle`

- `brand_id` wajib; `code`, `normalized_code`, `name`, `category`, `description`;
- `status` active/archived dengan metadata arsip;
- creator/updater;
- unique `(brand_id, normalized_code)`.

### 14.2 `MarketingDesignItem`

- bundle ID, type, title, description, sort order;
- spesifikasi produksi: dimensions, material specification, finishing, color/print notes, sides, dan notes;
- dihapus permanen; jejaknya hanya berada di audit.

### 14.3 `MarketingDesignFile`

- item ID; satu file aktif per item, file yang diganti terhapus lunak;
- private storage disk/path, original name, MIME, extension, size, checksum SHA-256;
- uploader dan upload timestamp;
- tidak ada public URL tersimpan;
- file yang diganti terhapus lunak dan object-nya tetap ada sampai itemnya dihapus.

### 14.4 `MarketingDesignAuditLog`

- bundle/item/file context;
- event, actor, from/to status, reason, metadata aman, timestamp;
- append-only dan tidak menyimpan signed URL.

Relasi baru ke `Brand` ditambahkan. Tidak ada foreign key ke project/fulfillment.

## 15. Integration contract

Not applicable — fitur tidak memanggil ESB atau layanan desain eksternal. Object storage diakses melalui Laravel Storage disk existing. Provider, credential, dan bucket tidak ditulis dalam dokumentasi.

## 16. Authorization dan security

1. Seluruh route/Page/Resource/Action memakai permission server-side; visibility tombol bukan kontrol keamanan.
2. Pengguna **internal** adalah pemilik `view design master bundles` yang juga memiliki salah satu permission create, edit, upload, atau archive. Hanya pengguna internal yang dapat melihat bundle archived.
3. Pengguna noninternal seperti Purchasing hanya dapat membuka bundle aktif. URL langsung ke bundle archived menghasilkan 404.
4. Unduh dan preview file memerlukan kemampuan melihat bundle serta `download design master files`. Preview inline hanya untuk gambar dan PDF.
5. Download memeriksa record, status bundle, dan permission sebelum membuat temporary URL. File yang sudah diganti atau dihapus menghasilkan 404.
6. ID/path dari browser tidak dipakai langsung untuk membaca object storage; original filename disanitasi untuk response header.
7. Validasi memakai MIME dari isi file dan extension; SVG tidak diterima, ZIP tidak diekstrak.
8. Aturan status bundle berlaku bagi semua pengguna, termasuk SUPERADMIN. `Gate::before` melewati method Policy sepenuhnya, sehingga mutasi dan akses file Master Design mengevaluasi method Policy secara langsung; SUPERADMIN tetap lolos pemeriksaan permission di dalamnya.
9. Permission baru ditambahkan ke `config/permissions.php`; pemberian ke role production dilakukan melalui Role & Permission, bukan seeder.

## 17. UI/UX specification

### 17.1 Lokasi

Canonical menu: **Back Office → Brand Marketing → Master Design**. Purchasing memakai halaman yang sama dengan permission terbatas.

### 17.2 Index

Index mengikuti pola index Project R&D (`resources/views/filament/helpdesk/rnd-projects/index.blade.php`): card header dengan tombol Buat Bundle dan strip informasi, bar filter (search kode/nama/deskripsi, Semua Brand sebagai default, kategori, tipe item, fisik/digital), tab Aktif/Diarsipkan khusus pengguna internal, list baris dengan header kolom Bundle, Brand, Kategori, Item, Diperbarui, Aksi, serta pagination bernomor 20 bundle per halaman. Baris stack di mobile. Pembuatan bundle memakai modal berisi Brand, kode, kategori, nama, dan deskripsi.

### 17.3 Detail

Detail mengikuti pola visual Project Detail R&D (`resources/views/filament/helpdesk/rnd-projects/view.blade.php`): `rounded-2xl`, border netral, header section dengan icon block, badge berteks, dan minim shadow. Susunan card:

1. **Summary** — breadcrumb, Brand, kode/nama, kategori, deskripsi, status, pembuat, waktu pembaruan, serta aksi Edit Bundle dan Arsipkan/Pulihkan.
2. **Informasi Bundle** — metadata stabil.
3. **Design Items** — preview lazy, tipe, fisik/digital, status kelengkapan bagi pengguna internal, file desain beserta metadata, serta aksi tambah, edit, hapus permanen, unduh, dan ganti file. Tombol Unduh selalu tampil bagi pemegang permission unduh dan nonaktif bila item belum punya file; tombol file berlabel Unggah File bila item belum punya file dan Ganti File bila sudah ada.
4. **Spesifikasi Produksi** — list item fisik. Setiap baris menampilkan identitas item (nama, tipe, file desain), status Spesifikasi Lengkap/Perlu Dilengkapi, aksi Isi Spesifikasi, dan daftar label-nilai spesifikasi; item tanpa spesifikasi menampilkan empty state. Modal Isi Spesifikasi menampilkan ringkasan item yang sama di atas form.

Purchasing melihat struktur yang sama dalam mode baca tanpa tombol mutasi. Seluruh card stack satu kolom di mobile tanpa horizontal scroll halaman.

### 17.4 Form

- Modal Tambah Item berisi tipe, nama, deskripsi, dan file desain. Spesifikasi produksi memakai modal terpisah. Input tetap tersedia saat validasi gagal.
- Progress, jenis file, batas ukuran, error, retry, dan cancel terlihat pada upload.
- Mutation dicegah dari double submit.

### 17.5 Responsive dan aksesibilitas

Periksa viewport 360, 390, 768, 1024, 1440 px dan zoom 200%; tabel hanya scroll di container; modal mengikuti fokus dan Escape; status selalu mempunyai teks; preview mempunyai fallback.

## 18. File storage contract

### 18.1 Path

```text
brand-marketing/design-master/
└── brand-{brand_id}/
    └── bundle-{bundle_id}/
        └── {uuid}.{ext}
```

Path tidak memuat ID item agar object dapat ditulis sebelum record item dibuat, sehingga item dan file tersimpan dalam satu transaksi.

### 18.2 Format dan batas file

Format: JPG, JPEG, PNG, WEBP, PDF, dan ZIP. SVG tidak diterima.

**Keputusan Phase 0 (2026-11-03):** batas **12 MB per file**, bukan proposal awal 15 MB/100 MB.

| Lapisan | Hasil audit | Dampak |
|---|---|---|
| Livewire | `temporary_file_upload.rules` bernilai `null`, sehingga berlaku default `max:12288` (12 MB) global untuk semua upload aplikasi | Batas nyata |
| Konfigurasi Livewire global | Menaikkan aturan memperbesar pintu upload seluruh komponen dan tidak dapat dibatasi per halaman | Tidak diubah |
| PHP dan reverse proxy production | Tidak berada di repository | Wajib diverifikasi pada UAT staging |

Batas berada pada satu konstanta di layanan penyimpanan file. Menaikkannya memerlukan keputusan terpisah IT dan Brand Marketing.

### 18.3 Lifecycle

- Upload baru ditulis ke path unik; record dibuat setelah storage berhasil, dan kegagalan database membersihkan object baru.
- Mengganti file menandai record lama sebagai terhapus lunak; object-nya tetap ada.
- Menghapus item menghapus permanen seluruh record file item itu, lalu object-nya setelah transaksi selesai. Kegagalan penyimpanan dicatat di log dan hanya menyisakan object yatim, tidak pernah record tanpa object.
- Archive tidak menghapus object.
- Temporary URL berumur 15 menit dan tidak disimpan.
- Cleanup orphan hanya melalui proses terpisah setelah reference audit.

## 19. Non-functional requirements

1. Index memakai pagination server-side, eager loading, dan aggregate count tanpa N+1.
2. Detail memuat preview secara lazy; binary tidak dibawa dalam state Livewire.
3. Mutasi memakai transaction dan row lock bundle.
4. Upload gagal tidak meninggalkan record setengah jadi.
5. Status memakai Enum; tidak ada dependency baru.

## 20. Observability dan audit

Audit event: bundle created/updated/archived/restored; item added/updated/deleted permanently; spesifikasi diperbarui; file replaced; file downloaded dengan actor dan file ID tanpa signed URL. Error storage mengikuti logging existing tanpa credential atau signed URL.

## 21. Data migration dan kompatibilitas

1. Migration hanya menambah tabel baru dan relasi `Brand`.
2. Tidak ada perubahan `rnd_project_marketing_materials` atau `marketing_material_fulfillments`, dan tidak ada backfill.
3. Data demo/seed hanya untuk development/test.

## 22. Testing strategy

1. Model/schema: relasi, enum cast, uniqueness, append-only audit, bundle tidak dapat dihapus, hapus item permanen beserta object.
2. Authorization: Designer, pengelola arsip, Purchasing, user tanpa permission, dan SUPERADMIN.
3. Workflow: create, update, item, archive, restore.
4. File dengan fake disk: MIME/extension/size, path, metadata, checksum, compensation, tampered ID/path, safe filename, dan permission unduh.
5. Filament: index, filter, pagination, modal, detail, read-only Purchasing, tombol per peran.
6. Regression Project R&D dan fulfillment; query count index dan detail.
7. Visual/manual UAT responsive, keyboard, nama panjang, preview tidak tersedia, dark mode.

## 23. Rollout

Lihat [Rollout Runbook](rollout.md): migration → aplikasi → permission sync → grant via Role & Permission → smoke test storage staging → UAT Designer dan Purchasing → regression existing → observasi.

## 24. Rollback

Cabut permission, rollback aplikasi, pertahankan tabel dan object. Drop schema/storage hanya melalui release terpisah setelah backup dan reference audit.

## 25. Acceptance criteria

1. Bundle tidak dapat dibuat tanpa Brand valid, dan Brand tidak dapat diubah.
2. Kode bundle unik dalam Brand dan boleh sama pada Brand berbeda.
3. Designer dapat menambah item beserta file desainnya, mengisi spesifikasi, mengganti file, serta mengubah dan menghapus item secara permanen pada bundle aktif.
4. Bundle archived tidak dapat diubah dan tidak terlihat oleh Purchasing.
5. Purchasing tidak dapat melakukan mutasi.
6. Download mengikuti permission `download design master files` dan authorization server-side.
7. File privat memakai path unik, metadata, checksum, dan temporary URL.
8. Archive dan ganti file tidak menghapus object atau audit; hapus item menghapus item, file, dan object-nya secara permanen tetapi tetap tercatat di audit.
9. Index/filter/detail responsive dan tidak mempunyai N+1.
10. Project R&D serta fulfillment existing tetap berfungsi tanpa perubahan data.
11. Tidak ada public file URL, hapus bundle, dependency baru, atau integrasi eksternal.

## 26. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| File diganti tanpa persetujuan | Purchasing memakai desain yang belum final | Keputusan sadar Brand Marketing; setiap penggantian tercatat di audit dan object lama tetap ada |
| File editable ikut terunduh Purchasing | Aset kreatif tersebar | Keputusan sadar Brand Marketing (v2.1); unggah hanya file yang memang boleh diterima Purchasing |
| Item terhapus tidak sengaja | File desain hilang permanen | Modal konfirmasi menyatakan penghapusan tidak dapat dibatalkan; audit mencatat aktor dan nama item |
| Upload besar gagal | Data tidak konsisten/orphan | Batas eksplisit, progress/error, compensating cleanup |
| Master dianggap transaksi order | Domain tercampur | Order/fulfillment menjadi fitur terpisah |

## 27. Keputusan

| No | Keputusan | Hasil |
|---|---|---|
| 1 | Limit file final | 12 MB per file (§18.2) |
| 2 | Jenis file | Dihapus; satu file desain per item dan satu permission unduh (v2.1) |
| 3 | Version, review, dan publish | Dihapus; fitur berupa penyimpanan file (v2.0) |

## 28. Implementation phases

| Phase | Scope |
|---|---|
| 0 | Audit infra storage, format/limit, role, query |
| 1 | Schema, model, enum, policy, permission, factory, audit |
| 2 | File service dan download terotorisasi |
| 3 | Action bundle, item, arsip |
| 4 | UI index, detail, modal, read-only Purchasing |
| 5 | Tests, responsive UAT, docs, report |
| Future | Project selection dan order/fulfillment dari master melalui PRD terpisah |

## 29. Definition of Done

- [x] Semua acceptance criteria terpenuhi pada test dan pemeriksaan lokal; bukti staging menunggu UAT.
- [x] Keputusan Phase 0 dan penyederhanaan v2.0 dicatat pada implementation report.
- [x] Test workflow, authorization, file lifecycle, dan regression lulus.
- [ ] Permission staging diberikan dan diuji tanpa seeder production.
- [x] README Brand Marketing/Purchasing dan arsitektur diperbarui sesuai implementasi aktual.
