---
document_id: INVENTORY-ITEM-JOURNAL-PRD
title: PRD — Item Journal Inventory
status: draft
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-28
updated_at: 2026-10-28
target_release: TBD
related_documents:
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
  - docs/products/inventory/README.md
  - docs/products/quality-control/README.md
  - docs/architecture/panels.md
  - docs/architecture/rbac.md
supersedes: null
---

# PRD — Item Journal Inventory

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Inventory |
| Fitur | Item Journal |
| Status | draft |
| Prioritas | P1 |
| Target pengguna | Staff store, Inventory, dan administrator yang diberi permission |
| Modul terdampak | Employee App, Back Office Inventory, Back Office Quality Control, ESB Core |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

Item Journal saat ini berada di navigasi Quality Control walaupun fungsi utamanya adalah penyesuaian stok ESB. Fitur akan dipindahkan secara user-facing ke domain Inventory: Employee App memperoleh tile **Item Journal** mandiri dan Back Office menempatkan rekapan Item Journal di grup **Inventory**. Lampiran baru menggunakan alur kamera dan hanya menerima gambar. Data, nomor jurnal, integrasi ESB, serta riwayat lama tetap dipertahankan tanpa konversi tabel.

## 3. Latar belakang

Store membutuhkan Item Journal untuk menambah atau mengurangi stok atas produk tertentu. Ketergantungan navigasi terhadap tile Quality Control membuat fitur sulit ditemukan dan secara konsep mengharuskan user store memiliki akses QC yang tidak berkaitan dengan tugasnya. Di Back Office, rekapan juga lebih tepat dimiliki Inventory karena berisi transaksi penyesuaian stok dan lokasi ESB.

## 4. Baseline sistem

Hasil audit per 28 Oktober 2026:

- Employee App memakai `App\Filament\Casual\Pages\ItemJournalPage`, tetapi halaman tidak mempunyai tile launcher sendiri dan dibuka melalui bottom navigation Quality Control.
- Akses halaman menggunakan policy `QualityControlItemJournal` dan permission `view/create/submit quality control item journals`.
- Back Office memakai resource `QualityControlItemJournalResource` dengan navigation group **Quality Control** dan hanya menyediakan daftar/detail.
- Sidebar manual menempatkan Item Journal pada grup **Quality Control**.
- Permission Item Journal dikelompokkan di domain `Quality Control` dalam `config/permissions.php`.
- Lampiran baru menerima JPG, PNG, WebP, atau PDF, maksimal lima file dan 5 MB per file.
- Submit membuat Item Journal melalui `EsbItemJournalService`, kemudian mengunggah lampiran. Status lokal yang digunakan adalah `submitting`, `succeeded`, `attachment_failed`, `unknown`, dan `verification_required`.
- Data berada di tabel `quality_control_item_journals`, `quality_control_item_journal_details`, dan `quality_control_item_journal_attachments`.
- Scope default hanya menampilkan jurnal pembuat. Permission `view all quality control item journals` saat ini membuka seluruh jurnal.

## 5. Problem statement

1. Item Journal tertanam pada modul QC sehingga staff store tidak memperoleh jalur akses yang jelas dan mandiri.
2. Back Office menempatkan transaksi stok di grup yang tidak sesuai dengan ownership operasionalnya.
3. Lampiran dapat dipilih dari file atau PDF, padahal bukti yang diinginkan adalah foto kondisi aktual dari kamera.
4. Pemindahan fitur berisiko memutus route, permission, atau riwayat bila dilakukan dengan mengganti model/tabel secara langsung.

## 6. Goals

1. Menyediakan tile Item Journal mandiri di Employee App.
2. Memungkinkan role store menggunakan Item Journal tanpa permission akses Quality Control.
3. Memindahkan menu rekapan Back Office ke grup Inventory.
4. Mengubah input lampiran baru menjadi gambar melalui alur kamera.
5. Menjaga seluruh data lama, nomor jurnal, status, dan integrasi ESB tetap kompatibel.
6. Menjaga authorization berbasis permission dan ownership; bukan nama role di kode runtime.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Jalur tile Item Journal mandiri | Tidak ada | 100% user berpermission melihat tile mandiri | Feature test launcher dan UAT role store |
| Ketergantungan pada tile QC | Wajib melalui navigasi QC | Tidak ada | Authorization dan navigation test |
| Lokasi menu Back Office | Quality Control | Inventory | Resource/sidebar test |
| Tipe lampiran baru | Gambar dan PDF | Gambar saja | Validation dan component test |
| Kehilangan data lama | Risiko saat pemindahan | 0 record berubah atau hilang | Pre/post-deploy count dan regression test |

## 8. Non-goals

- Tidak mengganti endpoint atau payload utama Item Journal ESB.
- Tidak mengganti tabel dan model legacy `QualityControlItemJournal*` pada rollout ini.
- Tidak mengubah metode perhitungan stok tersedia.
- Tidak membuat approval baru untuk Item Journal.
- Tidak mengubah status lifecycle transaksi yang sudah ada.
- Tidak menjamin secara kriptografis bahwa gambar pasti baru diambil pada seluruh browser; web bergantung pada kemampuan kamera dan dialog sistem perangkat.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Staff store | Membuka tile, membuat, submit, melihat jurnal sendiri, dan mengelola lampiran miliknya | `access employee app item journal`; permission aksi Item Journal existing |
| Inventory | Melihat rekapan Item Journal sesuai scope yang diberikan | `view quality control item journals`; opsional `view all quality control item journals` |
| Administrator | Memberikan permission dan branch access | Permission Management Access existing |
| SUPERADMIN | Akses seluruh data dan konfigurasi | Gate existing |

Nama permission aksi legacy dipertahankan untuk kompatibilitas. Pengelompokannya di matrix permission dipindahkan dari **Quality Control** ke **Inventory**. Permission baru hanya `access employee app item journal` sebagai gate tile/panel.

## 10. User journey

### 10.1 Employee App

1. User login dan melihat tile **Item Journal** bila memiliki `access employee app item journal`.
2. User membuka tile dan melihat riwayat jurnal yang dapat diakses.
3. User memilih **Buat Item Journal**, lalu mengisi tanggal, company, branch ESB, lokasi, produk, purpose, mode tambah/kurangi, quantity, HPP opsional, dan informasi tambahan.
4. Bila memerlukan lampiran, user menekan **Ambil Foto** dan kamera belakang diprioritaskan.
5. User meninjau foto, dapat mengambil ulang atau menghapusnya, lalu submit.
6. Sistem membuat transaksi ESB, mengunggah foto, dan menampilkan status serta opsi retry sesuai perilaku existing.

### 10.2 Back Office

1. User berpermission membuka **Inventory → Item Journal**.
2. User memfilter dan membuka detail jurnal.
3. Halaman tetap read-only dan menampilkan data integrasi ESB serta lampiran existing.

## 11. Business rules

1. Tile dan halaman Item Journal tidak boleh membutuhkan `access employee app quality control`.
2. Akses create, submit, view, dan delete attachment tetap diperiksa server-side melalui policy.
3. Back Office tetap read-only.
4. Record lama tidak dipindahkan, dihapus, atau ditulis ulang.
5. Lampiran baru maksimal lima gambar, maksimal 5 MB per gambar, dengan MIME yang diizinkan: JPEG, PNG, dan WebP.
6. PDF existing tetap dapat ditampilkan, di-retry, atau dihapus sesuai permission; larangan PDF hanya berlaku pada input baru.
7. UI tidak boleh menyatakan kamera benar-benar eksklusif bila browser/OS masih menawarkan galeri.
8. Kegagalan upload lampiran tidak boleh membuat Item Journal ESB yang sudah berhasil dianggap belum dibuat.
9. Idempotency dan mekanisme `verification_required`/`unknown` existing harus tetap berlaku.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Launcher Employee App menampilkan tile **Item Journal** berdasarkan permission khusus tile. | Must |
| FR-2 | Item Journal dihapus dari bottom navigation Quality Control. | Must |
| FR-3 | Halaman Item Journal memiliki navigasi kembali ke launcher dan tidak memakai konteks visual QC. | Must |
| FR-4 | Permission aksi existing tetap menentukan view/create/submit/delete attachment. | Must |
| FR-5 | Role `STORE_STAFF` memperoleh permission tile dan aksi Item Journal secara default pada seeder untuk instalasi baru/test. | Must |
| FR-6 | Permission Item Journal ditampilkan pada domain Inventory di matrix Role & Permission. | Must |
| FR-7 | Sidebar Back Office memindahkan Item Journal dari Quality Control ke Inventory. | Must |
| FR-8 | Resource Back Office memakai navigation group **Inventory** tanpa mengubah kemampuan read-only. | Must |
| FR-9 | Input lampiran baru berlabel **Ambil Foto**, image-only, memprioritaskan kamera belakang, dan tidak menawarkan PDF dari UI aplikasi. | Must |
| FR-10 | Server menolak file non-image atau file di atas 5 MB. | Must |
| FR-11 | User dapat melihat preview, menghapus, dan mengambil ulang foto sebelum submit. | Should |
| FR-12 | UI menampilkan pesan kompatibilitas bila kamera tidak tersedia atau izin kamera ditolak. | Must |
| FR-13 | Data dan attachment lama tetap dapat dibaca tanpa backfill. | Must |
| FR-14 | Link dari System Monitoring ke detail Item Journal tetap valid. | Must |
| FR-15 | Dokumentasi Inventory, Quality Control, Panels, dan RBAC diperbarui saat implementasi selesai. | Must |

## 13. Status dan lifecycle

Lifecycle tidak berubah.

| Status | Makna | Transisi yang diizinkan | Aktor |
|---|---|---|---|
| `submitting` | Permintaan sedang/baru dikirim ke ESB | `succeeded`, `attachment_failed`, `unknown`, `verification_required` | Sistem |
| `succeeded` | Journal dan seluruh attachment berhasil diproses | Tetap; attachment dapat dihapus sesuai permission | Sistem/user berpermission |
| `attachment_failed` | Journal ESB berhasil tetapi satu atau lebih attachment gagal | `succeeded` melalui retry | Pemilik berpermission |
| `unknown` | Hasil request tidak pasti akibat gangguan koneksi | Verifikasi manual sesuai flow existing | Sistem/IT |
| `verification_required` | Sistem membutuhkan verifikasi sebelum retry | Verifikasi manual sesuai flow existing | IT |

## 14. Model data konseptual

Tidak ada perubahan schema pada phase ini.

- `QualityControlItemJournal` tetap menjadi aggregate root dan menyimpan branch lokal opsional, pembuat, identitas ESB, tanggal, status, idempotency, payload, error, dan nomor jurnal.
- `QualityControlItemJournalDetail` tetap menyimpan produk, purpose, quantity, UOM, dan HPP.
- `QualityControlItemJournalAttachment` tetap menyimpan metadata dan lokasi file.
- Nama legacy dipertahankan karena penggantian tabel/model tidak memberikan manfaat bisnis sebanding dengan risiko migrasi.

## 15. Integration contract

- Tetap memakai ESB Core melalui `EsbItemJournalService`.
- Create journal, upload attachment, dan delete attachment tidak berubah.
- Payload gambar menggunakan MIME aktual yang sudah divalidasi.
- Timeout, retry, idempotency, transaction monitoring, dan uncertain-result handling mengikuti implementasi existing.
- Tidak ada environment variable baru.

## 16. Authorization dan security

1. `access employee app item journal` mengontrol tile dan akses dasar halaman.
2. Policy tetap wajib untuk `viewAny`, `view`, `create`, `submit`, dan `deleteAttachments`.
3. Permission tile tidak otomatis memberi hak submit; permission aksi tetap terpisah.
4. Query riwayat store tetap membatasi jurnal milik user kecuali memiliki permission view-all.
5. Evaluasi branch scope untuk view-all dilakukan saat implementasi; bila diubah, record dengan `branch_id = null` harus mempunyai perilaku kompatibel yang diuji dan didokumentasikan.
6. Validasi menggunakan MIME server-side, ukuran maksimal, dan batas jumlah file; ekstensi atau atribut HTML tidak dianggap cukup.
7. Nama file tidak digunakan sebagai path penyimpanan.
8. Request/response payload sensitif tidak ditampilkan kepada user tanpa permission teknis yang sesuai.

## 17. UI/UX specification

### Employee App

- Tile **Item Journal** sejajar dengan tile operasional lain di launcher, memakai ikon pertukaran/penyesuaian stok.
- Halaman mempertahankan layout mobile existing dan tidak menampilkan bottom navigation QC.
- Header menyediakan tombol kembali ke launcher, judul Item Journal, serta akses riwayat/form yang jelas.
- Kontrol lampiran berubah dari **Tambah Foto / PDF** menjadi **Ambil Foto**.
- Kamera belakang diprioritaskan dengan `capture="environment"`; implementasi boleh memakai camera API bila dapat dilakukan tanpa dependency baru.
- Preview foto menampilkan tombol **Ambil Ulang** dan **Hapus**.
- State wajib: meminta izin, kamera tidak tersedia, izin ditolak, upload berlangsung, validasi gagal, dan retry.
- Desktop/perangkat tanpa kamera menampilkan penjelasan bahwa pengambilan foto memerlukan perangkat berkamera; attachment tetap opsional sesuai baseline.

### Back Office

- Menu: **Inventory → Item Journal**.
- Tabel dan detail menggunakan komponen existing; tidak ada create/edit/delete record.
- Filter, table density, empty state, dan dark mode mengikuti `docs/standards/ui-consistency.md`.
- Tidak ada Item Journal pada grup Quality Control setelah rollout.

## 18. Non-functional requirements

- Tidak menambah query per row pada daftar Back Office.
- Launcher tidak melakukan request ESB.
- Maksimal lima attachment dan 5 MB per attachment dipertahankan.
- Halaman tetap dapat digunakan pada viewport mobile minimal 360 px.
- Perubahan tidak boleh menambah dependency frontend tanpa persetujuan.
- Integrasi harus tetap idempotent saat user menekan submit lebih dari sekali.

## 19. Observability dan audit

- Semua mutation ESB tetap dicatat melalui `EsbTransactionRecorder`.
- Error create/upload/delete tetap tersimpan pada `last_error` dan monitoring transaction.
- Audit creator, uploader, timestamps, item journal number, dan payload hash dipertahankan.
- Tidak ada log yang boleh memuat credential atau token ESB.

## 20. Data migration dan kompatibilitas

- Tidak ada migration database atau backfill.
- Route resource dan model legacy dipertahankan untuk mencegah link monitoring rusak.
- Permission aksi legacy tidak dihapus atau diganti pada rollout ini; hanya dipindahkan pengelompokannya ke Inventory.
- Permission tile baru dibuat melalui `permissions:sync` saat deploy.
- Assignment role production harus ditinjau melalui menu Role & Permission; seeder tidak dijalankan untuk mengubah role production.
- Attachment PDF lama tetap valid sebagai data historis; validasi image-only hanya berlaku untuk upload baru.

## 21. Testing strategy

1. Characterization test flow create/submit/retry/delete existing sebelum refactor.
2. Launcher test: tile muncul dengan permission baru dan tidak muncul tanpa permission.
3. Authorization test: akses Item Journal tidak memerlukan permission QC; permission tile saja belum cukup untuk submit.
4. Navigation test: Back Office group Inventory dan sidebar tidak lagi menampilkan Item Journal di Quality Control.
5. Validation test: gambar valid diterima; PDF dan file non-image ditolak; jumlah dan ukuran tetap dibatasi.
6. View test: input memiliki `accept="image/*"` dan `capture="environment"`, tanpa opsi PDF.
7. Regression test: journal dan attachment lama tetap muncul, link monitoring tetap valid, serta ESB HTTP fake menerima payload yang sama.
8. Role seeder test untuk STORE_STAFF, INVENTORY_STAFF, QUALITY_CONTROL, dan SUPERADMIN.

## 22. Rollout

1. Deploy kode dan jalankan migration pipeline normal; tidak ada migration baru.
2. Jalankan `permissions:sync` melalui pipeline untuk membuat permission tile.
3. Berikan `access employee app item journal` dan permission aksi kepada role production yang disetujui melalui Role & Permission.
4. Uji staging menggunakan STORE_STAFF, INVENTORY_STAFF, QUALITY_CONTROL, dan user tanpa akses.
5. Verifikasi transaksi sandbox/non-production sebelum penggunaan production.
6. Pantau error ESB dan attachment selama minimal satu siklus operasional.

## 23. Rollback

- Rollback aplikasi ke release sebelumnya mengembalikan navigasi lama tanpa menyentuh data.
- Permission baru boleh tetap berada di database karena tidak digunakan release lama.
- Tidak ada data rollback atau reverse migration.
- Journal yang dibuat selama release baru tetap dapat dibaca oleh release lama karena schema dan payload tidak berubah.

## 24. Acceptance criteria

1. STORE_STAFF yang diberi permission dapat membuka Item Journal dari tile mandiri tanpa akses QC.
2. User tanpa permission tile tidak melihat tile dan mendapat 403 bila membuka URL langsung.
3. Item Journal tidak lagi muncul pada bottom navigation Quality Control.
4. Back Office menampilkan Item Journal hanya di grup Inventory.
5. Resource Back Office tetap read-only dan seluruh data lama tetap terlihat sesuai authorization.
6. Upload baru hanya menerima gambar maksimal lima file dan 5 MB per file.
7. Mobile memprioritaskan kamera belakang; penolakan izin atau perangkat tanpa kamera mempunyai pesan yang jelas.
8. PDF lama tetap dapat dilihat atau dikelola sesuai permission.
9. Submit dan retry ESB tetap idempotent serta tercatat di monitoring.
10. Test authorization, navigation, validation, integrasi, dan regresi lulus.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Dialog sistem masih menawarkan galeri | Klaim camera-only tidak selalu absolut | Gunakan capture kamera, hilangkan picker umum/PDF, dan komunikasikan batasan browser |
| Permission baru belum diberikan di production | Tile tidak muncul | Checklist rollout dan UAT per role; assignment melalui UI |
| Rename namespace/route memutus deep link | Monitoring/detail 404 | Pertahankan route/model legacy pada rollout ini |
| Scope view-all terlalu luas | Kebocoran data lintas branch | Audit dan test branch scope sebelum perubahan; jangan memperluas scope tanpa kontrak |
| Attachment lama berupa PDF | Retry atau detail historis rusak | Terapkan image-only hanya pada upload baru |
| User submit berulang saat koneksi lambat | Duplikasi journal ESB | Pertahankan submission key, payload hash, dan uncertain-result handling |

## 26. Open decisions

Tidak ada keputusan yang menghalangi dimulainya implementasi. Assignment final permission untuk role production tetap harus dikonfirmasi pada UAT karena role production dikelola melalui UI dan tidak boleh ditimpa seeder.

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Characterization dan kontrak akses | Test baseline, permission mapping, daftar deep link |
| 1 | Navigasi dan ownership | Tile mandiri, lepas dari QC bottom nav, menu Back Office Inventory |
| 2 | Kamera dan validasi | Camera-first input, preview, image-only server validation, error states |
| 3 | Authorization dan kompatibilitas | Seeder test, policy/branch audit, legacy attachment/link regression |
| 4 | Dokumentasi, UAT, delivery | README/architecture update, targeted test, CI, rollout verification |

## 28. Definition of Done

- [ ] Seluruh acceptance criteria terpenuhi.
- [ ] Test terdampak ditambahkan dan lulus.
- [ ] Authorization diuji untuk role yang berhak dan tidak berhak.
- [ ] Data lama dan attachment lama terbukti kompatibel.
- [ ] Tidak ada migration data yang tidak diperlukan.
- [ ] README Inventory, Quality Control, Panels, dan RBAC diperbarui sesuai kode final.
- [ ] UAT mobile camera dan Back Office Inventory selesai.
