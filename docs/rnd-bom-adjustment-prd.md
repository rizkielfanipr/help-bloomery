# PRD — R&D BOM Adjustment dan Riwayat Perubahan BOM

## 1. Identitas dokumen

| Field | Nilai |
| --- | --- |
| Modul | Research & Development |
| Fitur | BOM Adjustment |
| Status | Rancangan untuk implementasi bertahap |
| Prioritas | P0 untuk audit log, P1 untuk submenu dan editor |
| Target pengguna | Tim R&D dan pengguna berizin Bill of Material |
| Referensi kode | `docs/code-remediation-prd.md` |
| Referensi UI | `docs/ui-consistency-prd.md` |
| Integrasi | ESB Bill of Material browse, detail, dan update |

Dokumen ini menjadi sumber requirement implementasi. Dokumen tidak mengizinkan deployment, mutation ESB production, penghapusan data, maupun perubahan kontrak API tanpa verifikasi.

## 2. Latar belakang

Perubahan resep saat ini dilakukan dari halaman Product dalam R&D Project. Pengguna harus membuat atau membuka Project meskipun kebutuhan sebenarnya hanya memperbarui BOM Assembly yang sudah ada di ESB. Proses update juga belum mempunyai riwayat domain yang dapat dicari untuk melihat data sebelum, data sesudah, pengguna, waktu, alasan, dan kepastian hasil mutation ESB.

Fitur BOM Adjustment menyediakan workspace khusus untuk mencari, membuka, dan mengubah BOM Assembly tanpa Project. Semua jalur update BOM di aplikasi harus memakai Action bisnis yang sama agar validasi, concurrency check, mutation safety, snapshot lokal, serta logging tidak berbeda antarhalaman.

## 3. Tujuan

1. Menyediakan submenu `Research & Development → BOM Adjustment`.
2. Menampilkan index BOM Assembly dengan pencarian, filter, dan pagination server-side.
3. Memungkinkan update resep tanpa membuat Project.
4. Mewajibkan alasan perubahan sebelum mutation dikirim.
5. Menyimpan audit trail sebelum, permintaan perubahan, dan hasil sesudah mutation.
6. Mencegah overwrite ketika BOM sudah berubah di ESB.
7. Mencegah retry mutation otomatis ketika hasil timeout tidak pasti.
8. Memasukkan update dari halaman Project existing ke riwayat yang sama.
9. Menampilkan riwayat perubahan yang dapat dicari dan difilter.
10. Menjaga Forecast, HPP, Harga WIP, Memo Internal, serta export memakai snapshot terbaru.

## 4. Batasan scope

### Termasuk

- BOM dengan tipe Assembly/WIP.
- Browse, detail, dan update BOM ESB.
- Katalog/snapshot BOM lokal untuk index dan perbandingan.
- Product picker server-side.
- Audit log perubahan BOM.
- Deteksi perubahan dari luar Help Bloomery saat sinkronisasi.
- Rekonsiliasi mutation dengan hasil tidak pasti.
- Permission, policy, test, dan navigation.

### Tidak termasuk

- Membuat BOM baru dari submenu ini.
- Mengubah BOM Menu pada fase awal.
- Menghapus atau menonaktifkan BOM ESB.
- Mengubah formula Forecast/HPP yang sudah disepakati.
- Mengetahui identitas user yang mengubah langsung di ESB jika API tidak mengirimkannya.
- Retry otomatis mutation setelah timeout/connection failure.

## 5. Prinsip arsitektur

```text
Filament Page
    → authorization dan presentation validation
    → Action bisnis
        → payload builder / comparator
        → database transaction lokal
        → EsbBillOfMaterialService
        → snapshot dan change log
```

- Blade tidak melakukan query atau HTTP.
- Page tidak membangun payload ESB atau menentukan transaction boundary.
- Semua update BOM memakai Action yang sama.
- Read dan mutation ESB tetap melalui service/client existing.
- Index memakai database pagination, bukan menyimpan seluruh BOM di state Livewire.
- Test tidak boleh melakukan network eksternal.

## 6. Informasi arsitektur existing

- `EsbBillOfMaterialService` sudah menyediakan browse, detail, create, dan update.
- `BillOfMaterialPage` existing disembunyikan dan mengarahkan pengguna ke Project.
- Inline editor BOM berada di `ViewProjectProductPage`.
- Conflict check existing memakai `editedDate`.
- Permission existing: `view bill of materials`, `create bill of materials`, `edit bill of materials`, dan `add existing bill of materials`.
- `spatie/laravel-activitylog` tersedia, tetapi mutation BOM ESB membutuhkan record domain yang dapat dicari dan mempunyai status rekonsiliasi.

## 7. Navigation dan route

```text
Research & Development
└── BOM Adjustment
```

Route yang disarankan:

```text
GET /bom-adjustments
GET /bom-adjustments/{bomId}/edit
```

Halaman utama mempunyai tab:

1. `BOM Assembly`
2. `Change History`

Route lama `bill-of-material` diaudit terlebih dahulu. Redirect atau file lama hanya dihapus setelah pencarian reference, route test, sidebar discovery, dan regression test membuktikan aman.

## 8. Katalog BOM lokal

Index tidak mengambil seluruh katalog ESB pada setiap render. Tambahkan tabel `rnd_bom_catalogs` sebagai snapshot read model.

| Field | Tujuan |
| --- | --- |
| `esb_bom_id` | Identifier ESB, unique |
| `bom_code` | Kode BOM |
| `bom_name` | Nama BOM |
| `bom_type_id` | ID tipe BOM |
| `bom_type_name` | Nama tipe BOM |
| `product_detail_id` | Product Detail hasil |
| `product_code` | Kode produk hasil |
| `product_name` | Nama produk hasil |
| `uom_name` | Unit hasil |
| `component_count` | Jumlah komponen |
| `is_active` | Status BOM |
| `detail_snapshot` | Snapshot detail terakhir |
| `esb_edited_at` | Waktu perubahan dari ESB jika tersedia |
| `sync_status` | `synced`, `failed`, atau `needs_reconciliation` |
| `last_synced_at` | Waktu sinkronisasi terakhir |

Indeks database minimal:

- unique `esb_bom_id`;
- index `bom_type_id, is_active`;
- index `product_detail_id`;
- index `sync_status`;
- index kolom pencarian yang terbukti melalui query plan.

## 9. Sinkronisasi katalog

Sinkronisasi dijalankan melalui Job dengan unique lock:

1. Ambil seluruh halaman browse BOM dari ESB.
2. Identifikasi BOM Assembly dari field tipe yang telah diverifikasi.
3. Upsert metadata katalog.
4. Ambil detail hanya ketika dibutuhkan atau ketika metadata menunjukkan perubahan.
5. Bandingkan snapshot lama dengan snapshot baru.
6. Catat perubahan eksternal jika snapshot berubah tanpa mutation lokal yang cocok.
7. Simpan progress, jumlah berhasil, gagal, dan waktu refresh.

Tombol `Refresh BOM` menampilkan progress dan tidak menahan request browser sampai seluruh data selesai.

## 10. UI index BOM Assembly

Gunakan pola R&D Project Workspace:

- container putih, border abu tipis, `rounded-2xl`;
- ikon Heroicon existing dengan aksen biru;
- tidak memakai shadow berlebihan;
- satu heading halaman;
- loading, error, empty, dan stale state yang jelas;
- layout mobile tidak membuat seluruh halaman scroll horizontal.

### Filter

| Filter | Perilaku |
| --- | --- |
| Search | Kode/nama BOM dan kode/nama produk hasil |
| Unit | Filter unit hasil |
| Status | Active atau Inactive |
| Sync Status | Synced, Failed, Needs Reconciliation |
| Per Page | 10, 20, atau 50; default 20 |

### Kolom tabel

| Kolom | Isi |
| --- | --- |
| BOM | Kode dan nama BOM |
| Product Result | Kode dan nama produk hasil |
| Unit | Unit hasil |
| Components | Jumlah komponen |
| Status | Active/Inactive |
| Last Synced | Waktu snapshot terakhir |
| Last Changed | Waktu perubahan terakhir |
| Action | Detail/Edit sesuai permission |

Pagination memakai kontrol panah sederhana dan label `halaman / total halaman`.

## 11. UI editor BOM

Editor mengadopsi form inline Main Recipe existing, tetapi tidak bergantung pada Project.

### Informasi BOM

- BOM Code.
- BOM Name.
- BOM Type.
- Product Result.
- Result Unit.
- Status.
- Last Synced.
- Last Edited ESB.

### Komponen resep

| Kolom | Aturan |
| --- | --- |
| Product | Dipilih lewat modal Master Product |
| Unit | Mengikuti active product detail |
| Qty | Wajib lebih dari nol |
| HPP | Informasi HPP ESB saat ini |
| Action | Ganti/hapus berdasarkan permission |

Kolom `Waste %`, `Tolerance %`, dan `Print Group` tidak ditampilkan kembali sesuai keputusan UI sebelumnya. Payload yang wajib dipertahankan API tetap diambil dari snapshot terbaru ketika tidak diedit pengguna.

Product picker memakai server-side search, pagination, loading spinner, active unit filtering, serta mencegah komponen duplikat.

### Alasan perubahan

`Alasan Perubahan` wajib diisi, maksimal 1.000 karakter. Alasan disimpan dalam audit log dan tidak dikirim ke ESB kecuali kontrak API membutuhkannya.

## 12. Preview perubahan

Sebelum mutation dikirim, tampilkan modal yang merangkum:

- Product Result berubah.
- Komponen ditambahkan.
- Komponen dihapus.
- Quantity berubah.
- Unit berubah.
- Status berubah.

User menekan `Konfirmasi & Kirim ke ESB` setelah memeriksa perbandingan.

## 13. Action update terpusat

Buat `UpdateEsbBillOfMaterialAction` yang dipakai BOM Adjustment dan inline editor Project.

Alur:

1. Authorize action.
2. Ambil detail BOM terbaru dari ESB.
3. Bandingkan `editedDate` dengan versi saat form dimuat.
4. Validasi invariant resep.
5. Bangun payload dari detail terbaru dan field yang diedit.
6. Simpan attempt berstatus `pending` beserta before/requested snapshot.
7. Kirim PUT melalui `EsbBillOfMaterialService`.
8. Ambil ulang detail BOM.
9. Simpan after snapshot, diff, katalog, dan status `success`.
10. Beri hasil terstruktur kepada Page untuk notification.

Invariant:

- Minimal satu komponen.
- Product Result tidak boleh menjadi komponennya sendiri.
- Satu Product Detail tidak boleh berulang.
- Quantity harus lebih dari nol.
- Field API yang tidak diedit tetap dipertahankan dari response terbaru.

## 14. Concurrency dan mutation safety

Jika `editedDate` berubah setelah form dibuka, update dihentikan dan user diminta memuat ulang data.

Status attempt:

| Status | Arti |
| --- | --- |
| `pending` | Mutation sedang diproses |
| `success` | ESB mengonfirmasi dan detail terbaru cocok |
| `failed` | ESB menolak sebelum hasil menjadi tidak pasti |
| `needs_reconciliation` | Timeout/connection failure, hasil ESB belum diketahui |

Mutation tidak boleh otomatis di-retry setelah timeout atau connection failure. Action rekonsiliasi mengambil detail terbaru ESB dan membandingkannya dengan requested snapshot.

## 15. Riwayat perubahan BOM

Tambahkan tabel `rnd_bom_change_logs`.

| Field | Tujuan |
| --- | --- |
| `esb_bom_id` | BOM yang berubah |
| `bom_code`, `bom_name` | Kolom pencarian |
| `product_code`, `product_name` | Identitas produk hasil |
| `source` | `bom_adjustment`, `project`, atau `external_esb` |
| `event` | Jenis perubahan |
| `status` | Status attempt |
| `reason` | Alasan pengguna |
| `before_snapshot` | Data sebelum |
| `requested_snapshot` | Data yang diminta |
| `after_snapshot` | Data terverifikasi sesudah |
| `changes` | Diff terstruktur |
| `error_code`, `error_message` | Error aman |
| `esb_edited_at_before/after` | Version marker ESB |
| `changed_by` | User lokal |
| `reconciled_by`, `reconciled_at` | Rekonsiliasi |

Audit record tidak ikut terhapus ketika BOM tidak lagi aktif.

### Filter history

- Search kode/nama BOM, produk, user, dan alasan.
- Change Type.
- Status mutation.
- Source.
- User.
- Date range.

Detail dibuka dalam modal dan menampilkan daftar perubahan, bukan hanya JSON mentah.

## 16. Perubahan dari luar aplikasi

Saat refresh menemukan snapshot berbeda dan tidak ada mutation lokal yang cocok, buat log:

```text
Source: External ESB
Changed By: Tidak diketahui
Event: External Change Detected
```

Identitas user ESB hanya dapat ditampilkan jika API mengirimkannya.

## 17. Permission dan policy

Gunakan permission existing:

- `view bill of materials` untuk index/detail;
- `edit bill of materials` untuk editor dan mutation.

Permission tambahan yang disarankan:

- `view bom adjustment history`;
- `reconcile bom adjustments`.

Policy dan Action melakukan server-side authorization. Visibilitas tombol bukan satu-satunya pengamanan.

## 18. Struktur kode target

Struktur akhir mengikuti kondisi repository dan phase modularisasi yang sudah berlaku. Kandidat:

```text
app/Filament/Helpdesk/.../BomAdjustmentPage.php
app/Filament/Helpdesk/.../EditBomAdjustmentPage.php
app/Actions/Rnd/Bom/UpdateEsbBillOfMaterialAction.php
app/Actions/Rnd/Bom/ReconcileBomAdjustmentAction.php
app/Actions/Rnd/Bom/SyncBomCatalogAction.php
app/Services/Rnd/Bom/BomPayloadBuilder.php
app/Services/Rnd/Bom/BomChangeComparator.php
app/Jobs/Rnd/SyncBomCatalogJob.php
app/Models/RndBomCatalog.php
app/Models/RndBomChangeLog.php
app/Policies/RndBomCatalogPolicy.php
app/Policies/RndBomChangeLogPolicy.php
```

Jangan membuat interface, DTO, repository, atau wrapper yang belum mempunyai kebutuhan nyata.

## 19. Kontrak API yang harus diverifikasi

1. Filter browse berdasarkan `bomTypeID` atau tipe Assembly.
2. Field tipe pada setiap row browse.
3. Stabilitas `editedDate` sebagai version marker.
4. Response setelah update dan kebutuhan GET ulang.
5. Ketersediaan audit history ESB.
6. Ketersediaan request/idempotency identifier.
7. Cara mengambil active dan inactive BOM.

Jika browse API tidak mendukung filter Assembly, Job mengambil seluruh halaman dan katalog lokal menyimpan hanya Assembly. Jangan memfilter satu halaman berisi 20 data di browser karena total dan pagination akan salah.

## 20. Test wajib

### Unit

- Diff component add/remove/change.
- Diff Product Result dan unit.
- Payload Assembly mempertahankan field yang tidak diedit.
- Sanitasi error.
- Rekonsiliasi requested snapshot.

### Feature/Livewire

- Navigation dan akses berdasarkan permission.
- Hanya Assembly tampil.
- Search/filter/pagination 10, 20, dan 50.
- Product picker paginated dan active unit saja.
- Validasi komponen dan alasan.
- Conflict `editedDate`.
- Update berhasil.
- Validation error ESB.
- Timeout menjadi `needs_reconciliation` tanpa retry mutation.
- History mencatat update dari BOM Adjustment dan Project.
- Perubahan eksternal terdeteksi saat sync.
- Seluruh HTTP difake.

### Regression

- Inline edit Project tetap bekerja.
- Forecast Kitchen/Store tetap benar.
- HPP dan Harga WIP tetap benar.
- Memo Internal tetap menguraikan WIP.
- Export Kitchen/Store tetap bekerja.

## 21. Phase implementasi

### Phase 0 — Audit dan characterization

- Audit API dan seluruh consumer mutation BOM.
- Kunci payload, validation, conflict, permission, dan error behavior existing dalam test.
- Dokumentasikan kontrak yang belum terbukti.

### Phase 1 — Data dan comparator

- Migration katalog dan change log.
- Model, factory, policy, permission.
- Comparator snapshot dengan unit test.

### Phase 2 — Sinkronisasi katalog

- Job dan unique lock.
- Progress/status refresh.
- Deteksi perubahan eksternal.
- Index lokal dengan pagination 20.

### Phase 3 — Action mutation terpusat

- Ekstrak payload builder.
- Buat update/reconcile Action.
- Migrasikan inline edit Project.
- Terapkan status mutation aman.

### Phase 4 — UI BOM Adjustment

- Navigation dan index.
- Editor, product picker, preview perubahan.
- Responsive, loading, empty, error, dan stale states.

### Phase 5 — Change History

- Search/filter/pagination.
- Modal detail diff.
- Rekonsiliasi berdasarkan permission.

### Phase 6 — Quality gate dan cleanup

- Test terfokus dan seluruh R&D.
- Pint dan diff check.
- Route/sidebar discovery.
- Full suite dengan memory 512 MB.
- Audit reference sebelum menghapus Page/route lama.

## 22. Deployment dan rollback

Deployment:

1. Backup database sesuai prosedur deployment.
2. Pull commit.
3. Jalankan migration dengan `--force`.
4. Seed/sync permission menggunakan mekanisme existing.
5. Clear/cache aplikasi sesuai prosedur server.
6. Jalankan sync awal katalog melalui queue.
7. Verifikasi index read-only sebelum mengaktifkan mutation untuk role.

Rollback kode tidak boleh langsung menghapus tabel audit. Migration `down` hanya digunakan pada rollback terkontrol setelah memastikan data audit telah diamankan.

## 23. Acceptance criteria

- BOM Assembly dapat dicari dan dipaginasi tanpa Project.
- Default pagination 20 dan tersedia opsi 10/20/50.
- User berizin dapat mengubah komponen dan memberi alasan.
- Preview menunjukkan perubahan sebelum mutation.
- Conflict mencegah overwrite.
- Timeout tidak memicu retry mutation.
- Semua jalur update aplikasi masuk audit trail yang sama.
- History dapat dicari dan difilter.
- Perubahan eksternal dapat dideteksi.
- UI mengikuti standar R&D Project dan UI Consistency PRD.
- Full suite lulus dan test tidak mengakses network eksternal.

## 24. Prompt implementasi

Gunakan prompt berikut untuk memulai Phase 0:

```text
Implementasikan Phase 0 dari `docs/rnd-bom-adjustment-prd.md`.

Baca dan ikuti:
- `AGENTS.md`
- `docs/rnd-bom-adjustment-prd.md`
- `docs/code-remediation-prd.md`
- `docs/ui-consistency-prd.md`
- `docs/esb-integration-consolidation-roadmap.md`

Audit kondisi repository terlebih dahulu karena implementasi aktual dapat berubah dari baseline PRD.

Ketentuan wajib:
- Kerjakan hanya Phase 0; jangan membuat migration, UI, atau mutation baru pada phase ini.
- Audit seluruh jalur browse, detail, dan update BOM.
- Audit seluruh consumer `EsbBillOfMaterialService::updateBillOfMaterial()`.
- Pertahankan route, permission, payload, validation, conflict check, dan UI behavior existing.
- Tambahkan characterization test untuk payload Assembly, permission, `editedDate` conflict, validation error, timeout, connection failure, dan larangan retry mutation dengan hasil tidak pasti.
- Seluruh test harus terisolasi dari network eksternal.
- Jangan mengubah requirement production untuk mempertahankan test stale.
- Pertahankan seluruh perubahan existing di working tree.
- Jangan menghapus file atau route lama tanpa usage audit dan bukti test.

Sebelum mengubah kode:
1. Audit implementasi dan consumer BOM.
2. Bandingkan repository dengan PRD.
3. Laporkan kontrak API yang terbukti dan yang belum terbukti.
4. Kelompokkan risiko read, mutation, timeout, dan concurrency.

Sesudah pengerjaan:
1. Jalankan test terfokus.
2. Jalankan seluruh test R&D terkait.
3. Jalankan Pint dan diff check.
4. Laporkan file yang berubah dan keputusan implementasi.
5. Laporkan test gagal beserta klasifikasinya.
6. Perbarui laporan implementasi/PRD hanya berdasarkan bukti.
7. Jangan commit atau push kecuali diminta.
```

Setelah Phase 0 selesai dan direview, gunakan pola:

```text
Implementasikan Phase [N] dari `docs/rnd-bom-adjustment-prd.md` berdasarkan hasil phase sebelumnya. Kerjakan hanya phase tersebut, pertahankan kontrak production, isolasikan test dari network eksternal, jalankan quality gate yang diwajibkan, dan jangan commit atau push kecuali diminta.
```
