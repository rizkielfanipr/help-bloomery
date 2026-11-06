# Research & Development

Pengembangan produk dari project sampai rilis: project dan produk, BOM di ESB, Master Bahan dengan review IT, sourcing dan evaluasi sampel, harga resmi, shelf life WIP, memo internal ke operasional, dan kalender tugas lintas cabang. Kondisi per 2 November 2026.

## Lokasi

| Tempat | Menu | Permission |
|---|---|---|
| Back Office → Research & Development | Project | `view rnd projects` (+ create/edit/delete); task: `view/create/update/assign … rnd project tasks` |
| | Template Checkpoint | `view rnd project task templates`, `manage rnd project task templates` |
| | Recipe Adjustment | `view bill of materials`, `edit bill of materials`, `view bom adjustment history`, `reconcile bom adjustments` |
| | Shelf Life | `view wip shelf life`, `manage wip shelf life` |
| | Product Price Index | `view product price index`, `sync product price index` |
| | Prefix Category, Prefix Name | `view prefix categories`, `view prefix names` (+ create/edit/delete) |
| | Memo Internal | `view any rnd internal memo` dan permission Memo Internal lainnya |
| | Review Sourcing | `review material samples as rnd`, `receive material samples`, `reopen material sample approval`, `review material sourcing as rnd` |
| Back Office → Information Technology | Review Data | `review rnd materials as it` |
| Back Office → Purchasing | Sourcing Bahan | `view material sourcings`, `submit material sourcing`, `hand over material samples` |
| Back Office → Finance | Product Pricelist | `view material pricelist`, `manage material pricelist` |

## Project dan produk

- **Project**: nama, deskripsi, tanggal mulai, dan tanggal rilis (end date), dengan **daftar Project aktif** dan **kalender** yang menggabungkan tanggal rilis dan deadline task. Tipe project: `release_product`, `premix`, `partnership` (premix punya assembly; partnership punya menu dan equipment).
- **Project Overview** ([PRD](project-overview-rich-text/prd.md)): section rich-text pada detail seluruh tipe Project, setelah header dan statistik. Viewer (`view rnd projects`) membaca konten atau empty state; editor (`edit rnd projects`) menulisnya lewat modal Filament RichEditor dengan toolbar yang sama seperti Informasi BOM. Konten disimpan di `overview_html`, HTML disanitasi server-side dengan allowlist, dan konten kosong dinormalisasi menjadi `null`. Gambar JPEG/PNG/WebP maksimum 8 MB tersimpan private di disk `b2`, langsung di bawah `rnd/project-overviews/{projectId}/rich-editor/`; gambar base64, eksternal, atau milik Project lain tidak diterima. Gambar yang dihapus dari konten dihapus dari storage setelah save berhasil, dan upload baru dibersihkan bila save gagal. `description` tetap plain text dan menjadi satu-satunya sumber daftar, pencarian, dan header; Overview tidak dicari, tidak diekspor, dan tidak dimuat pada query daftar Project.
- **Daftar Project aktif** ([PRD](project-active-list/prd.md)): list responsif yang hanya memuat Project tidak terarsip dengan `start_date <= hari ini <= end_date`, inklusif, urut `updated_at` terbaru. Search nama/deskripsi dan filter tipe hanya mempersempit scope aktif; tidak ada filter status. Setiap baris menampilkan identitas, tipe, periode, jumlah isi per tipe, progress waktu, dan aksi buka/edit/arsip sesuai permission. Project Upcoming, Completed, dan Archived tidak muncul di daftar ini. Kalender rilis dan kalender Task tetap memuat rilis seluruh Project tidak terarsip serta mengikuti search/filter tipe; deep-link `openTask` tidak bergantung pada status Project.
- **Tab Nonaktif** ([PRD](project-inactive-list/prd.md)): list Project Upcoming, Completed, dan Archived dengan filter status, tipe, dan search serta pagination 20 record per halaman; tidak pernah memuat Project aktif. Project Upcoming/Completed dapat dibuka, diubah, dan diarsipkan sesuai permission. Project Archived hanya memiliki aksi **Pulihkan** (`edit rnd projects`), halaman detailnya tidak dapat dibuka, dan pemulihan tidak mengubah periodenya.
- **Produk Project**: status `draft`, `development`, `trial`, `ready`, `released`, `cancelled`. Gerbang Ready/Released mewajibkan setiap WIP pada BOM produk punya master Shelf Life aktif.
- Halaman produk menyediakan:
  - **BOM** — menautkan BOM ESB (impor dari katalog), mengedit komponen langsung ke ESB, instruksi BOM dengan gambar, BOM menu, dan PDF BOM. **Buat Resep** (Assembly maupun Menu) mengirim BOM ke ESB tanpa kode BOM, sama seperti upload manual di ESB, sehingga tidak bisa bentrok dengan kode BOM lain.
    Export PDF Project hanya aktif untuk scope yang memiliki BOM eligible. Store membutuhkan minimal satu BOM Menu; ketika kosong tombol dinonaktifkan dengan penjelasan, sementara direct request tetap ditolak server. Dokumen Project memakai identitas dokumen tunggal pada header/footer, memisahkan setiap produk ke halaman sendiri, dan menampilkan produk asal pada Sales Projection agar preview, download, dan SOP Store konsisten.
  - **Analisis harga menu** — weighted average harga bahan, food cost, dan rekomendasi harga per region.
  - **Master Bahan/WIP** — membuat bahan baru dengan prefix nama, lalu mengirimnya ke review IT (lihat bagian berikut).
  - **Material marketing** — packaging, stiker, foto produk, social media, katalog, lainnya. Tipe fisik diteruskan ke Purchasing dan Inventory.
  - **Material Forecast** — kebutuhan bahan berdasarkan proyeksi penjualan, dengan tampilan Kitchen/Store dan export Excel dua sheet. Filter **Menu Projection** ([PRD](material-forecast-menu-filter/prd.md)) default **All Menu**; user dapat memilih satu atau beberapa menu yang memiliki Sales Projection positif. Pilihan yang diterapkan membatasi ringkasan, detail projection, material, warning, catatan, jumlah sumber, dan kedua sheet Excel (metadata **Scope Menu**), bertahan saat berpindah Kitchen/Store, dan tersimpan di URL `menu_ids`, bukan di database. Seluruh bulan, region, channel, dan branch projection menu terpilih tetap dijumlahkan; persentase forecast tetap global per Project. ID pada URL halaman yang tidak lagi eligible atau bukan milik Project dibuang dengan pesan; export dengan ID seperti itu ditolak `422` tanpa file. Filter memakai permission `view material forecast` dan tidak mengubah data.
  - **Shelf Life WIP** — mengisi master yang belum ada (tidak mengedit master existing).
- Proyeksi penjualan per region dibaca di menu [Sales & Growth](../sales-growth/README.md).

## Kalender tugas dan Template Checkpoint

- Task project memiliki kategori (trial, tasting, quality control, approval, production, foto produk, launching, general), prioritas, tanggal assign, deadline, dan lampiran instruksi. Task dibagikan ke **PIC per branch** (satu branch boleh lebih dari satu PIC).
- PIC menekan **Mulai Mengerjakan**, mengirim follow-up *progress* (status tidak berubah) atau *submission* (menjadi `submitted`). Reviewer meng-approve atau meminta revisi (wajib catatan). Status task dihitung dari assignment aktifnya.
- Status task: `draft`, `assigned`, `in_progress`, `submitted`, `revision_required`, `completed`, `cancelled`. Pembatalan menghentikan reminder tanpa menghapus riwayat.
- Reminder deadline (3 hari, 1 hari, hari H, terlambat) dikirim oleh `rnd:send-project-task-reminders` pukul 07:00.
- **Template Checkpoint** menyimpan daftar checkpoint reusable. **Gunakan Template** membuat task dari checkpoint terpilih dalam satu transaksi (dengan idempotency key). Deadline tidak boleh melewati tanggal rilis. **Copy Task** menyalin deskripsi task ke task baru. Template yang pernah dipakai hanya bisa dinonaktifkan.

## Recipe Adjustment (BOM)

- Katalog BOM **Assembly** lokal disinkronkan dari ESB di background (**Refresh BOM**). Perubahan BOM di ESB yang tidak berasal dari aplikasi dicatat sebagai `external_esb`.
- **Edit BOM** mengubah komponen BOM Assembly/Menu tanpa harus lewat project. Editor yang sama dipakai di halaman produk project melalui `UpdateEsbBillOfMaterialAction`.
- **Change History** mencatat setiap perubahan (sumber `bom_adjustment`, `project`, `external_esb`) dengan diff. Hasil yang tidak pasti (`needs_reconciliation`) diselesaikan dengan **Reconcile**, yang hanya mengamati ESB dan tidak mengirim ulang mutasi.

## Shelf Life WIP

- Menu **Shelf Life** menampilkan produk ESB kategori "Barang WIP" (snapshot lokal yang di-refresh di background) dan satu-satunya tempat untuk mengedit, menonaktifkan, atau mengaktifkan kembali master shelf life.
- Master disimpan per produk WIP pada base unit-nya. Master tidak dikirim ke ESB.
- Shelf life lama per menu sudah dihentikan, dan permission-nya dihapus oleh `permissions:sync`.

## Master Bahan dan sourcing

Alur bahan baru dari R&D sampai harga resmi. Status dilacak dalam beberapa dimensi terpisah.

1. **R&D** membuat Master Bahan/WIP di produk project lalu **Kirim untuk Review IT**. Data di-snapshot dengan hash dan nomor versi, dan bahan terkunci untuk R&D. Notifikasi Telegram ke IT dikirim bila diaktifkan. R&D dapat menarik pengajuan selama belum di-claim IT.
2. **IT** (Review Data) melakukan claim, lalu memilih salah satu: mengoreksi data teknis (kategori, UOM, SKU, konversi, flag), mengembalikan ke R&D dengan alasan (tampil sebagai **Catatan revisi IT** beserta reviewer dan waktunya di kolom Keterangan bahan pada halaman produk selama status `revision_required`, sampai R&D mengirim ulang), **menautkan** ke produk ESB existing (setiap unit harus cocok dengan Product Detail aktif), atau **approve push** untuk membuat produk di ESB. Approval terikat pada versi dan hash data.
3. **Sync ESB** dibaca ulang dan setiap unit harus terpetakan sebelum statusnya `synced`. Hasil tidak pasti menjadi `needs_reconciliation`, lalu IT memverifikasi atau menyatakan tidak diterapkan.
4. Setelah sync terverifikasi:
   - **Bahan biasa** → Sourcing Request ke Purchasing.
   - **WIP** → sourcing `not_required`, langsung masuk antrean Pricelist.
5. **Purchasing** (Sourcing Bahan) menambah kandidat supplier, lalu **Serahkan Sampel** dengan foto.
6. **R&D** (Review Sourcing) **Terima Sampel** dengan foto penerimaan sendiri, lalu **Evaluasi Sampel**:
   - **Approve** — supplier terpilih dan satu Pricing Request dibuat dalam transaksi yang sama.
   - **Request Resample** — wajib alasan; siklus sampel baru dibuka.
   - **Reject** — wajib alasan; final. Hanya bisa dilanjutkan dengan **Buka Kembali Sourcing** (permission `reopen material sample approval` dan alasan).
   - Approval sampel dapat dibuka ulang selama Finance belum mulai memproses harga.
7. **Finance** (Product Pricelist) menyimpan draft harga lalu **mengonfirmasi** setelah harga dimasukkan di ESB, dengan referensi ESB (mode manual; `ESB_PRICELIST_MODE=manual`). Mapping yang salah dikembalikan ke IT. Revisi harga membuat request baru dan menyimpan request lama sebagai riwayat.
8. Workflow **Completed** dihitung otomatis setelah sync terverifikasi, sourcing selesai (`approved_by_rnd` atau `not_required`), dan harga `priced`.

| Dimensi | Status |
|---|---|
| Review IT | `draft`, `waiting_it_review`, `under_it_review`, `revision_required`, `approved_for_esb`, `review_cancelled` |
| Sync ESB | `not_ready`, `queued`, `syncing`, `synced`, `failed`, `needs_reconciliation` |
| Sourcing | `not_required`, `waiting_purchasing`, `sourcing_in_progress`, `sample_handed_over`, `sample_received`, `resample_requested`, `approved_by_rnd`, `rejected_by_rnd` (+ status legacy `not_started`, `pending_rnd_review`, `pending_finance_review`, `approved`, `rejected`) |
| Siklus sampel | `prepared`, `handed_over`, `received`, `approved`, `resample_requested`, `rejected` |
| Pricing | `not_ready`, `ready_for_pricing`, `draft_pricing`, `submitting`, `verification_required`, `priced`, `failed`, `cancelled` |

**Sourcing tanpa project** (Sourcing Bahan → Buat Sourcing) memilih produk ESB existing dan masih memakai approval legacy (R&D lalu Finance). Sourcing tanpa project juga dapat berasal dari [Request Sourcing](../purchasing/README.md#request-sourcing) Employee App yang dikonversi Purchasing; kolom Project-nya bertuliskan **Request User** dan alur reviewnya sama. Pembacaan ulang produk ESB untuk keduanya memakai `ActiveEsbProductDetailResolver`. Penyatuan dengan alur sampel menunggu keputusan PRD §32 butir 8. Data legacy tetap bisa dibaca dan diproses dengan alur lamanya. Audit trail per bahan tersimpan di `rnd_material_audit_logs`.

### Durasi sourcing

Sourcing Bahan dan Review Sourcing memakai kolom, filter, dan ringkasan modal yang sama ([PRD](../purchasing/sourcing-duration-tracking/prd.md)). Durasi memakai waktu kalender dan dihitung saat tampilan dirender, tidak disimpan.

- **Mulai** (`sourcing_requested_at`): diisi sekali saat bahan masuk antrean Purchasing setelah sync terverifikasi, saat sourcing mandiri dibuat, atau saat bahan legacy `not_started` menerima supplier pertama. Resample dan reopen tidak mengubahnya.
- **Selesai** (`sourcing_completed_at`): diisi pada `approved_by_rnd`, `rejected_by_rnd`, `approved` (approval Finance), dan `rejected` (penolakan R&D), dalam transaksi yang sama dengan perubahan status. Resample dan penolakan Finance yang mengembalikan record ke `pending_rnd_review` tetap berjalan. Pricelist dan `workflow_completed_at` tidak memengaruhi durasi.
- **Reopen dan pengajuan ulang** (buka ulang approval, buka kembali penolakan, tambah atau ubah supplier legacy): mengosongkan tanggal selesai dan durasi berjalan lagi dari tanggal mulai awal. Audit log menyimpan tanggal selesai sebelumnya pada `previous_sourcing_completed_at`.
- **Tampilan**: record berjalan menampilkan suffix `berjalan`. Data yang tidak terbukti atau berkronologi tidak valid menampilkan `Tidak Tersedia`. `not_started` dan `not_required` menampilkan `—`.
- **Data lama**: `rnd:backfill-sourcing-durations` hanya mengisi field null dari sumber yang terbukti: sourcing mandiri dari `created_at`; keputusan sampel dari `evaluated_at` siklus terbaru yang cocok; legacy dari `finance_reviewed_at` atau `rnd_reviewed_at`. Tanggal mulai record project yang null, termasuk project legacy, tetap null.

## Memo Internal

- Memo per **Brand** dan periode (Company Code selalu BLSS; satu memo aktif per brand, periode, dan revisi). Menu dipilih dari katalog Master Menu BLSS global yang disinkronkan di background.
- Setiap menu di-resolve BOM-nya secara rekursif sampai bahan dasar dan packaging (**Ambil Ulang** per menu). Forecast quantity dan shelf life per menu adalah data lokal memo.
- **Ringkasan Item Akhir** mengonsolidasikan kebutuhan per item untuk Store dan Kitchen, dengan Minimum Order per item dan Purchase UOM dari ESB. Produk tambahan (WIP/RAW) dapat ditambahkan manual.
- Export release dan export produk tersedia. Kontrol status, finalisasi, revisi, arsip, dan PDF dari alur lama tidak lagi tampil di UI. Action-nya masih ada untuk data lama.

## Product Price Index

Snapshot harga Purchase Order ESB mingguan (`product-price-index:sync --days=90`, Senin 01:00, atau sinkron manual), weighted average per produk, detail riwayat per produk, dan indeks harga WIP yang dihitung dari BOM.

## Prefix Category dan Prefix Name

Master prefix untuk penamaan Master Bahan di halaman produk project: kode, label, kategori terkait, urutan, dan status aktif.

## Command

| Command | Fungsi |
|---|---|
| `rnd:sync-esb-materials` | 02:30 — tarik perubahan Master Product ESB ke bahan yang sudah tertaut |
| `rnd:send-project-task-reminders` | 07:00 — reminder deadline task |
| `product-price-index:sync` | Senin 01:00 — snapshot harga PO |
| `rnd:audit-material-legacy` | Dry-run: klasifikasi Master Bahan legacy |
| `rnd:audit-wip-shelf-life` | Dry-run: konflik identitas shelf life WIP |
| `rnd:backfill-sourcing-durations` | Isi tanggal mulai dan selesai sourcing dari sumber terbukti; dry-run kecuali `--apply` |
| `rnd:backfill-internal-memo-brands` | Isi brand memo lama dari branch lama; dry-run kecuali `--apply` |
| `esb:create-units`, `esb:rename-products`, `esb:replace-product-units`, `esb:replace-bom-units`, `esb:set-recipe-unit`, `esb:normalize-mille-crepe-whole-menus` | Utilitas one-off perubahan massal di ESB berdasarkan file Excel; setiap perubahan diverifikasi |

## Peta kode

Baca file di bawah ini lebih dulu; jangan menelusuri seluruh repository. Pola `{a,b}` dan `*` adalah glob dari root repository.

| Lapisan | Lokasi |
|---|---|
| Back Office | `app/Filament/Helpdesk/Resources/{Projects,RndProjectTaskTemplates,RndInternalMemos,ReviewSourcings,PrefixCategories,PrefixNames}/`, `app/Filament/Helpdesk/Pages/{ViewProjectProductPage,BomAdjustmentPage,EditBomAdjustmentPage,BillOfMaterialPage,CreateBomRecipePage,WipShelfLifePage,ProductPriceIndexPage,ProductPriceIndexDetailPage}.php`, `app/Filament/Helpdesk/Concerns/` |
| Logika | `app/Actions/Rnd/`, `app/Actions/{ArchiveRndProjectAction,RestoreRndProjectAction}.php`, `app/Services/Rnd/`, `app/Services/{RndProjectMaterialForecastService,ProductPriceIndexService,ProductPriceSnapshotService,PurchaseOrderPriceSyncService,WipPriceIndexService,SyncRndEsbMaterialFromRemote}.php`, `app/Http/Controllers/Helpdesk/{RndProjectBomPdfController,RndProductBomPdfController}.php`, `app/Jobs/Rnd/`, `app/Livewire/` |
| View | `resources/views/filament/helpdesk/rnd-projects/view.blade.php`, `resources/views/exports/rnd-product-bom-pdf.blade.php`, `resources/views/exports/partials/rnd-{bom-section,project-sales-projection}.blade.php` |
| Policy | `app/Policies/Rnd*.php` |
| Enum | `app/Enums/{Rnd,Material}*.php` |
| Model | `app/Models/{Rnd,MaterialSourcing,Prefix,ProductPrice}*.php` |
| Command | `app/Console/Commands/{AuditRndMaterialLegacy,AuditWipShelfLife,BackfillInternalMemoBrands,BackfillMaterialSourcingDurations,SendRndProjectTaskReminders,SyncRndEsbMaterials,SyncProductPriceIndex}Command.php`, `app/Console/Commands/Esb*.php` |
| Test | `tests/Feature/{Rnd,ProjectTask,Bom,MaterialSourcing,ProductPriceIndex,ShelfLife,BackfillMaterialSourcingDurations}*Test.php`, `tests/Feature/MaterialWorkflow/`, `tests/Unit/{InternalMemo,RndInternalMemo,MaterialSourcingTimeline}*Test.php` |

## Data

Model `Rnd*`, `MaterialSourcing`, `MaterialSourcingApproval`, `PrefixCategory`, `PrefixName`, `ProductPriceSnapshot`, `ProductPriceSyncRun`. Lihat [Data Model](../../architecture/data-model.md#research--development).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [PRD BOM Adjustment](bom-adjustment/prd.md) | Implemented |
| [Remediasi Konsistensi Export BOM PDF](bom-pdf-consistency/prd.md) | Implemented |
| [PRD Material Sourcing Lifecycle](material-sourcing-lifecycle/prd.md) | Implemented; open decision §32 butir 8 (sourcing tanpa project) |
| [Pelacakan Durasi Sourcing](../purchasing/sourcing-duration-tracking/README.md) | Implemented; rollout dan UAT staging belum dijalankan |
| [PRD Project Task Calendar](project-task-calendar/prd.md) | Implemented |
| [PRD Project Checkpoint Calendar](project-checkpoint-calendar/prd.md) | Implemented |
| [PRD Project Types](project-types/prd.md) | Implemented |
| [Daftar Project Aktif](project-active-list/README.md) dan [PRD](project-active-list/prd.md) | Implemented |
| [Daftar Project Nonaktif](project-inactive-list/README.md) dan [PRD](project-inactive-list/prd.md) | Implemented |
| [Filter Menu Material Forecast](material-forecast-menu-filter/README.md) dan [PRD](material-forecast-menu-filter/prd.md) | Implemented |
| [Project Overview Rich Text](project-overview-rich-text/README.md) dan [PRD](project-overview-rich-text/prd.md) | Implemented; rollout dan UAT staging belum dijalankan |
| [PRD WIP Shelf Life](wip-shelf-life/prd.md) | Implemented |
| [PRD Internal Memo — Brand](internal-memo-brand/prd.md) | Implemented — scope brand dan katalog BLSS |
| [PRD Internal Memo — Simplification](internal-memo-simplification/prd.md) | Implemented — alur operasional memo saat ini |
| [PRD Internal Memo](internal-memo/prd.md) | Sebagian digantikan oleh Simplification; tetap dirujuk kode untuk aturan BOM, konsolidasi, dan forecast |
| [PRD Internal Memo — Multi-Branch](internal-memo-multi-branch/prd.md), [Phase 0 report](internal-memo-multi-branch/reports/phase-0-report.md) | Scope branch/company digantikan oleh Brand; report tetap dirujuk kode sebagai bukti kontrak katalog |

Dataset: [development-todo.csv](development-todo.csv).

## Role bawaan

`RND_STAFF` mengelola project, BOM, memo, shelf life, task, dan review sampel. `IT_STAFF` me-review Master Bahan. `PURCHASING_STAFF` mengurus sourcing. `FINANCE_STAFF` mengelola Pricelist. `DESIGN_STAFF` mengunggah material marketing.
