---
document_id: PURCHASING-SOURCING-DURATION-TRACKING-PRD
title: PRD — Pelacakan Durasi Sourcing
status: implemented
version: 1.1
owner: Purchasing
technical_owner: Information Technology
created_at: 2026-11-02
updated_at: 2026-11-02
target_release: TBD
related_documents:
  - docs/products/purchasing/README.md
  - docs/products/rnd/material-sourcing-lifecycle/prd.md
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# PRD — Pelacakan Durasi Sourcing

> Dokumen ini adalah kontrak implementasi. Dokumen tidak mengotorisasi migration production, backfill production, deployment, atau perubahan data manual; langkah tersebut tetap mengikuti [Rollout Runbook](rollout.md).

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | Purchasing |
| Fitur | Pelacakan Durasi Sourcing |
| Status | implemented; UAT dan backfill staging belum dijalankan |
| Prioritas | P1 |
| Target pengguna | Purchasing, R&D, dan reviewer operasional |
| Modul terdampak | Purchasing — Sourcing Bahan; R&D — Review Sourcing |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

Sourcing Bahan belum memperlihatkan secara eksplisit kapan suatu material masuk sourcing, kapan sourcing mencapai keputusan final, dan berapa lama prosesnya. Sistem sudah menyimpan `sourcing_requested_at` untuk material workflow project, tetapi belum mempunyai timestamp selesai sourcing dan index masih menampilkan `updated_at`, yang tidak mewakili batas proses bisnis. Fitur ini menambahkan timestamp selesai yang ditulis server-side, perhitungan durasi kalender, serta ringkasan pada index dan detail sourcing. Lifecycle Pricelist tetap terpisah dan tidak diubah.

## 3. Latar belakang

Purchasing perlu melihat umur antrean sourcing dan durasi penyelesaian per material agar dapat mengidentifikasi proses yang lama, menelusuri resample, dan mengevaluasi waktu sourcing per produk. Saat ini pengguna harus menyimpulkan waktu dari perubahan status, riwayat sampel, atau `updated_at`. Cara tersebut tidak konsisten karena `updated_at` dapat berubah akibat pembaruan lain dan `workflow_completed_at` baru terisi setelah proses Pricelist selesai.

## 4. Baseline sistem

1. Record utama sourcing adalah `RndProductEsbMaterial`.
2. `sourcing_requested_at` sudah tersedia dan diisi oleh `MaterialPostSyncRouter` saat bahan biasa hasil Project masuk antrean Purchasing.
3. Sourcing mandiri dibuat oleh `CreateStandaloneMaterialSourcingAction`, tetapi belum mengisi `sourcing_requested_at`.
4. `workflow_completed_at` menandai seluruh workflow Master Bahan selesai setelah pricing terverifikasi; field tersebut bukan tanggal selesai sourcing.
5. Siklus sampel menyimpan `evaluated_at`, tetapi data tersebut berada per siklus dan tidak menjadi timestamp ringkas pada record sourcing.
6. Alur sampel baru berakhir pada keputusan R&D `approved_by_rnd` atau `rejected_by_rnd`; `resample_requested` tetap aktif.
7. Alur legacy/sourcing mandiri masih memakai review R&D lalu Finance. Status final berhasilnya adalah `approved`; penolakan R&D menghasilkan `rejected`, sedangkan penolakan Finance mengembalikan record ke `pending_rnd_review`.
8. Approval atau rejection pada alur sampel dapat dibuka kembali. Riwayat status disimpan di audit log dan riwayat siklus sampel.
9. Index Purchasing dan Review Sourcing R&D memakai kolom bersama dari `MaterialSourcingResource::queueColumns()` serta filter bersama dari `queueFilters()`.
10. Detail supplier memakai modal `view-sourcing.blade.php`; riwayat sampel mempunyai modal terpisah.
11. Permission existing sudah memisahkan akses Purchasing dan R&D. Fitur baca ini tidak memerlukan permission baru.
12. Tidak ada mutation ESB, queue, scheduler, atau integrasi eksternal yang diperlukan oleh fitur ini.

## 5. Problem statement

Pengguna belum dapat menjawab secara langsung dan konsisten:

- kapan material resmi masuk sourcing;
- kapan sourcing selesai atau masih berjalan;
- berapa lama sourcing berlangsung;
- apakah resample masih dihitung sebagai proses aktif;
- apa dampak reopen terhadap durasi;
- data lama mana yang dapat diberi tanggal secara dapat dibuktikan.

## 6. Goals

1. Menyediakan tanggal mulai, tanggal selesai, dan durasi sourcing yang berasal dari transisi server-side.
2. Menampilkan informasi tersebut pada index dan detail sourcing.
3. Memisahkan durasi sourcing dari durasi review IT, sinkronisasi ESB, dan Pricelist Finance.
4. Menjaga histori keputusan lama saat sourcing dibuka kembali.
5. Memberikan backfill konservatif untuk data existing tanpa menebak tanggal dari `updated_at`.

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| Record sourcing baru dengan tanggal mulai | Sourcing project terisi; sourcing mandiri belum konsisten | 100% record sourcing baru terisi | Query record baru pada antrean sourcing |
| Keputusan sourcing final baru dengan tanggal selesai | Belum tersedia | 100% transisi final mengisi timestamp | Feature test dan audit sampel production setelah rollout |
| Durasi terlihat dari index/detail | Tidak tersedia | Tersedia pada kedua tampilan | UAT Purchasing dan R&D |
| Record lama dengan bukti valid yang berhasil dibackfill | Belum ada backfill | Seluruh record eligible terisi; record ambigu tetap null | Laporan dry-run dan hasil command backfill |

## 8. Non-goals

- Menetapkan SLA sourcing atau memberi notifikasi overdue.
- Menghitung hari kerja, hari libur, atau jam operasional.
- Mengubah workflow sourcing, sampel, approval, resample, pricing, atau production.
- Menggabungkan sourcing project dengan sourcing mandiri.
- Mengubah permission, role, navigation, atau integrasi ESB.
- Membuat dashboard analitik agregat, export, atau KPI supplier pada versi ini.
- Mengizinkan pengguna mengedit timestamp secara manual.

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| Purchasing | Melihat waktu dan durasi pada Sourcing Bahan; menjalankan proses sourcing existing | `view material sourcings` dan permission aksi existing |
| R&D | Melihat waktu dan durasi pada Review Sourcing; mengevaluasi sampel existing | Permission Review Sourcing existing |
| Sistem | Menulis timestamp mulai/selesai dan menghitung durasi | Not applicable — proses internal server |
| Information Technology | Menjalankan rollout/backfill dan memverifikasi hasil | Akses deployment sesuai prosedur operasional |

## 10. User journey

1. Material masuk antrean sourcing project setelah sinkronisasi ESB terverifikasi, atau sourcing mandiri berhasil dibuat.
2. Sistem mengisi tanggal masuk sourcing satu kali.
3. Purchasing melihat tanggal masuk dan durasi berjalan pada index Sourcing Bahan.
4. Purchasing mengelola kandidat dan menyerahkan sampel sesuai workflow existing.
5. R&D melihat durasi yang sama pada Review Sourcing dan melakukan evaluasi.
6. Saat keputusan sourcing final tercapai, sistem mengisi tanggal selesai.
7. Index dan detail menampilkan durasi final yang tidak lagi bertambah.
8. Jika sourcing dibuka kembali, tanggal selesai aktif dikosongkan dan durasi kembali berjalan dari tanggal masuk awal; histori keputusan sebelumnya tetap ada di audit log.

## 11. Business rules

1. Tanggal masuk sourcing menggunakan `sourcing_requested_at` dan ditulis oleh server.
2. Tanggal masuk hanya diisi sekali untuk lifecycle record yang sama dan tidak direset oleh resample atau reopen.
3. Tanggal selesai sourcing menggunakan field baru `sourcing_completed_at`; `workflow_completed_at` tidak boleh dipakai untuk tujuan ini.
4. Untuk alur sampel baru, status `approved_by_rnd` dan `rejected_by_rnd` adalah keputusan final sourcing dan mengisi tanggal selesai.
5. `resample_requested`, `sample_handed_over`, `sample_received`, dan status aktif lainnya tidak mengisi tanggal selesai.
6. Untuk alur legacy/sourcing mandiri, `approved` mengisi tanggal selesai setelah approval Finance; `rejected` mengisi tanggal selesai setelah rejection R&D.
7. Penolakan Finance yang mengembalikan record ke `pending_rnd_review` bukan penyelesaian sourcing.
8. Aksi yang melanjutkan kembali record berstatus terminal wajib mengosongkan `sourcing_completed_at` dalam transaksi yang sama dengan perubahan status.
9. Reopen tidak mengubah `sourcing_requested_at`; durasi aktif dihitung dari permintaan pertama sampai waktu sekarang.
10. Durasi selesai adalah selisih `sourcing_requested_at` dan `sourcing_completed_at` dalam waktu kalender.
11. Durasi aktif adalah selisih `sourcing_requested_at` dan waktu server saat tampilan dirender.
12. Durasi negatif akibat data lama tidak valid tidak boleh ditampilkan sebagai angka; UI menampilkan **Tidak Tersedia** dan kondisi dicatat untuk verifikasi.
13. Timestamp ditampilkan dalam timezone aplikasi `Asia/Jakarta` dengan tanggal dan waktu.
14. Durasi kurang dari satu jam ditampilkan dalam menit; setelah itu ditampilkan dalam kombinasi hari dan jam yang relevan. Nilai tidak dibulatkan ke hari kerja.
15. Record tanpa tanggal mulai yang dapat dibuktikan menampilkan **Tidak Tersedia**, bukan memakai `updated_at`.
16. Harga/Pricelist Finance pada workflow baru tidak memperpanjang durasi sourcing.
17. WIP dengan sourcing `not_required` tidak mempunyai tanggal atau durasi sourcing dan tetap dikecualikan dari antrean.
18. Record `not_started` belum masuk sourcing: tanggal dan durasinya `—`, dan tidak termasuk filter Masih Berjalan maupun Selesai.
19. Record legacy `not_started` yang menerima supplier pertama setelah rilis mengisi `sourcing_requested_at` saat itu, karena waktunya diketahui server. Record yang sudah pernah diputuskan tidak memperoleh tanggal mulai dari pengajuan ulang.
20. `sourcing_status` menjadi sumber kondisi berjalan atau selesai pada tampilan dan filter. Record final tanpa tanggal selesai termasuk **Selesai** dan menampilkan **Tidak Tersedia**.

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | Sistem menyimpan `sourcing_completed_at` nullable pada record material sourcing | Must |
| FR-2 | Sourcing project dan sourcing mandiri baru mengisi `sourcing_requested_at` server-side | Must |
| FR-3 | Transisi terminal mengisi tanggal selesai secara atomic dan retry-safe | Must |
| FR-4 | Resample tidak menandai sourcing selesai | Must |
| FR-5 | Reopen atau pengajuan ulang dari status terminal mengosongkan tanggal selesai tanpa mengubah tanggal mulai | Must |
| FR-6 | Index Sourcing Bahan menampilkan Masuk Sourcing, Selesai Sourcing, dan Durasi | Must |
| FR-7 | Modal detail sourcing menampilkan status, asal, tanggal mulai, tanggal selesai, dan durasi | Must |
| FR-8 | Review Sourcing R&D menampilkan informasi yang sama melalui komponen kolom/detail bersama | Must |
| FR-9 | Tanggal mulai dan selesai dapat diurutkan dan difilter rentang tanggal | Should |
| FR-10 | Pengguna dapat memfilter sourcing Masih Berjalan atau Selesai | Should |
| FR-11 | `updated_at` tetap tersedia sebagai informasi teknis, tetapi dapat disembunyikan default agar tabel tidak terlalu padat | Should |
| FR-12 | Backfill mengisi hanya timestamp yang dapat dibuktikan dan menghasilkan ringkasan hasil | Must |
| FR-13 | Audit log merekam selesai dan reopen beserta timestamp lama yang relevan | Must |

## 13. Status dan lifecycle

Fitur tidak menambah status. Aturan timestamp mengikuti status existing berikut.

| Status/kejadian | Kondisi durasi | Perubahan timestamp |
|---|---|---|
| `not_started` | Belum berlaku | Tidak berubah |
| `waiting_purchasing` / record mandiri dibuat | Berjalan | Isi `sourcing_requested_at` jika null; selesai tetap null |
| `sourcing_in_progress` | Berjalan | Tidak berubah |
| `sample_handed_over` | Berjalan | Tidak berubah |
| `sample_received` / `under_rnd_review` | Berjalan | Tidak berubah |
| `resample_requested` | Berjalan | Selesai tetap null |
| `approved_by_rnd` | Selesai berhasil | Isi `sourcing_completed_at` |
| `rejected_by_rnd` | Selesai ditolak | Isi `sourcing_completed_at` |
| `pending_rnd_review` / `pending_finance_review` legacy | Berjalan | Selesai tetap null |
| `approved` legacy | Selesai berhasil | Isi `sourcing_completed_at` |
| `rejected` legacy | Selesai ditolak | Isi `sourcing_completed_at` |
| Reopen/pengajuan ulang, termasuk tambah atau ubah supplier legacy | Berjalan kembali | Kosongkan `sourcing_completed_at`; mulai tidak berubah |

## 14. Model data konseptual

Tambahkan secara additive pada `rnd_product_esb_materials`:

| Field | Tipe | Null | Aturan |
|---|---|---|---|
| `sourcing_completed_at` | timestamp | Ya | Ditulis server-side ketika sourcing final; dikosongkan saat reopen |

Field existing yang digunakan:

- `sourcing_requested_at`: batas mulai sourcing;
- `sourcing_status`: menentukan aktif/final dan hasil;
- `sourcing_origin`: membedakan project dan standalone;
- `workflow_completed_at`: tetap khusus completion seluruh workflow dan tidak diubah maknanya;
- `rnd_reviewed_at`, `finance_reviewed_at`, dan `RndMaterialSampleCycle.evaluated_at`: kandidat sumber backfill yang dapat dibuktikan;
- `RndMaterialAuditLog`: histori perubahan dan bukti reopen.

Durasi tidak disimpan sebagai angka agar tidak menjadi data turunan yang basi. Durasi dihitung dari dua timestamp pada read path melalui presenter/accessor/service yang dapat diuji tanpa query tambahan.

## 15. Integration contract

Not applicable — fitur hanya membaca dan menulis database lokal. Tidak ada request ke ESB atau layanan eksternal. Implementasi dan test dilarang memanggil ESB sungguhan.

## 16. Authorization dan security

1. Tidak ada permission baru.
2. Kolom dan detail mengikuti scope Resource/Policy existing; fitur tidak memperluas record yang dapat dilihat pengguna.
3. Timestamp tidak tersedia sebagai field input dan tidak boleh diterima dari state Livewire/browser.
4. Mutation tetap dilakukan oleh Action/Service existing yang memvalidasi permission server-side.
5. Backfill hanya dijalankan melalui proses deployment yang terkontrol, mendukung dry-run, dan tidak mencetak data sensitif.

## 17. UI/UX specification

### 17.1 Index

Tambahkan kolom pada tabel bersama:

| Label | Nilai | Empty state |
|---|---|---|
| MASUK SOURCING | `sourcing_requested_at`, format `d M Y H:i` | Tidak Tersedia |
| SELESAI SOURCING | `sourcing_completed_at`, format `d M Y H:i` | — untuk proses aktif; Tidak Tersedia untuk data ambigu |
| DURASI | Durasi kalender; suffix **berjalan** bila belum selesai | Tidak Tersedia |

- Status tetap menjadi sumber makna selesai berhasil atau selesai ditolak; durasi tidak menggantikan badge status.
- Default sorting existing dipertahankan untuk menghindari perubahan antrean tanpa keputusan bisnis. Kolom mulai dan selesai dapat di-sort.
- Kolom `DIPERBARUI` tetap tersedia dan boleh dijadikan toggle hidden-by-default bila tabel terlalu padat.
- Pada mobile, tabel memakai pola scroll container existing; kolom wajib tidak dihilangkan berdasarkan permission atau CSS.

### 17.2 Filter

- **Masuk Sourcing:** Dari Tanggal dan Sampai Tanggal.
- **Selesai Sourcing:** Dari Tanggal dan Sampai Tanggal.
- **Kondisi Durasi:** Semua, Masih Berjalan, Selesai.
- Filter status dan filter existing tetap dipertahankan.

### 17.3 Detail

Modal detail supplier menampilkan ringkasan sebelum daftar supplier:

- Status Sourcing;
- Asal Sourcing: Project atau Mandiri;
- Masuk Sourcing;
- Selesai Sourcing;
- Durasi Sourcing.

Modal tetap memakai struktur, scroll internal, dark mode, dan aksi tutup existing. Tidak ada edit tanggal pada modal.

### 17.4 Copy

- Aktif: `4 hari 2 jam berjalan`.
- Selesai: `6 hari 6 jam`.
- Belum dapat dibuktikan: `Tidak Tersedia`.
- Tidak berlaku: `—` dengan konteks status yang sudah terlihat.
- Kurang dari satu menit: `Kurang dari 1 menit`, bukan angka 0.

## 18. Non-functional requirements

1. Index tidak menambah query per row; seluruh nilai berasal dari kolom record yang sudah dimuat.
2. Perhitungan durasi bersifat deterministik dengan waktu referensi yang dapat dibekukan dalam test.
3. Migration bersifat additive dan nullable sehingga deployment aplikasi lama tetap dapat berjalan sementara.
4. Update status dan timestamp berada dalam transaction/row lock existing agar concurrent submit tidak menghasilkan hasil berbeda.
5. Backfill harus idempotent, chunked, mendukung dry-run, dan hanya mengisi field null.
6. Tidak ada dependency baru.

## 19. Observability dan audit

1. Event `sourcing_requested` existing tetap menjadi bukti tanggal mulai project.
2. Saat keputusan final, audit metadata mencatat `sourcing_completed_at` dan hasil sourcing.
3. Saat reopen, audit metadata mencatat timestamp selesai sebelumnya sebelum field aktif dikosongkan. Keputusan legacy memakai event baru `legacy_sourcing_approved`, `legacy_sourcing_rejected`, dan `legacy_sourcing_resubmitted`.
4. Command `rnd:backfill-sourcing-durations` melaporkan jumlah scanned, eligible, updated, skipped ambiguous, invalid chronology, dan unchanged, beserta rincian per field, tanpa menampilkan identitas sensitif. Backfill tidak menulis audit event dan tidak mengubah `updated_at`.
5. Tidak diperlukan Monitoring Transaction baru karena tidak ada integrasi eksternal atau mutation berisiko hasil tidak pasti.

## 20. Data migration dan kompatibilitas

### 20.1 Schema

Migration additive menambah `sourcing_completed_at` nullable di dekat `sourcing_requested_at`. Indeks tambahan hanya dibuat bila audit query/explain membuktikan kebutuhan filter; jangan menambah indeks spekulatif.

### 20.2 Backfill tanggal mulai

Urutan sumber:

1. Pertahankan `sourcing_requested_at` existing.
2. Untuk `sourcing_origin = standalone` yang null, gunakan `created_at` record sebagai waktu pengajuan karena record dibuat bersamaan dengan permintaan sourcing.
3. Untuk `sourcing_origin = project` yang null, termasuk record project legacy, biarkan null dan laporkan sebagai ambigu; `created_at` record tersebut adalah waktu pembuatan bahan, bukan permintaan sourcing, dan `updated_at` tidak boleh dipakai.

### 20.3 Backfill tanggal selesai

Backfill hanya untuk record yang saat ini terminal:

1. `approved_by_rnd` atau `rejected_by_rnd`: gunakan `evaluated_at` dari siklus dengan nomor terbaru bila statusnya cocok; siklus lama yang cocok tidak dipakai bila ada siklus yang lebih baru.
2. `approved` legacy: gunakan `finance_reviewed_at`.
3. `rejected` legacy: gunakan `rnd_reviewed_at`.
4. Bila timestamp sumber null, tidak cocok dengan status, lebih awal dari tanggal mulai, atau ada bukti reopen setelah keputusan tersebut, biarkan null dan laporkan.
5. Record aktif dan resample tetap mempunyai `sourcing_completed_at = null`.
6. Tanggal mulai yang tidak terbukti tidak menghalangi pengisian tanggal selesai yang terbukti; durasinya tetap **Tidak Tersedia**.

Backfill tidak mengubah status, supplier terpilih, audit lama, pricing, atau `workflow_completed_at`.

## 21. Testing strategy

1. Characterization test index, filter, scope, dan action sourcing existing sebelum perubahan.
2. Feature test tanggal mulai pada post-sync project dan pembuatan sourcing mandiri.
3. Feature test approve/reject sampel mengisi tanggal selesai yang sama dengan waktu server terkontrol.
4. Feature test resample tidak mengisi tanggal selesai.
5. Feature test reopen approval/rejection mengosongkan tanggal selesai dan mempertahankan tanggal mulai.
6. Feature test approval ulang menulis tanggal selesai baru.
7. Feature test legacy: approval Finance selesai, rejection R&D selesai, rejection Finance tetap berjalan, dan pengajuan ulang mengosongkan selesai.
8. Livewire test index/detail/filter untuk Purchasing dan R&D, termasuk null, ongoing, final, data ambigu, dan nama panjang.
9. Unit test formatter durasi untuk menit, jam, hari, ongoing, selesai, null, dan kronologi tidak valid.
10. Command test dry-run, apply, idempotency, chunking, status terminal, reopen, dan skip data ambigu.
11. Authorization regression: pengguna tanpa akses tetap tidak dapat membuka Resource/record.
12. Tidak ada HTTP call eksternal dalam test fitur ini.

## 22. Rollout

1. Deploy migration additive.
2. Deploy aplikasi yang menulis timestamp baru dan mampu membaca nilai null.
3. Jalankan command backfill dalam mode dry-run dan review ringkasannya.
4. Jalankan backfill apply setelah hasil dry-run disetujui oleh technical owner.
5. Lakukan smoke test index/detail serta satu alur ongoing, final, resample, dan reopen di staging.
6. Pantau error aplikasi dan jumlah record ambigu setelah deployment.

Detail operasional ada di [Rollout Runbook](rollout.md).

## 23. Rollback

1. Rollback aplikasi mengembalikan UI lama; kolom nullable boleh tetap ada agar tidak kehilangan histori.
2. Jangan menghapus nilai hasil backfill saat rollback aplikasi karena data bersifat audit dan tidak mengubah workflow.
3. Drop column hanya dilakukan dalam release terpisah setelah dipastikan tidak ada kode yang membaca/menulisnya.
4. Tidak ada rollback ESB karena fitur tidak berintegrasi dengan ESB.

## 24. Acceptance criteria

1. Sourcing baru dari Project dan mandiri memiliki tanggal masuk yang ditulis server.
2. Approve/reject final mengisi tanggal selesai dalam transaksi status yang sama.
3. Resample tetap tampil berjalan.
4. Reopen mengosongkan tanggal selesai aktif, mempertahankan tanggal mulai, dan menyimpan bukti tanggal selesai sebelumnya pada audit log.
5. Index Purchasing dan Review Sourcing menampilkan tanggal mulai, tanggal selesai, dan durasi yang sama.
6. Detail menampilkan ringkasan lifecycle sebelum data supplier.
7. Filter tanggal dan kondisi berjalan/selesai menghasilkan record yang benar.
8. Pricelist dan `workflow_completed_at` tidak memengaruhi durasi sourcing.
9. WIP `not_required` tetap tidak masuk antrean sourcing.
10. Backfill tidak memakai `updated_at`, tidak menimpa nilai valid, dan dapat dijalankan ulang tanpa perubahan tambahan.
11. Data ambigu tampil **Tidak Tersedia** dan tercatat pada ringkasan backfill.
12. Tidak ada perubahan permission, role, status, workflow, ESB, queue, atau scheduler.

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| `workflow_completed_at` keliru dipakai sebagai selesai sourcing | Durasi ikut waktu Finance | Field terpisah dan regression test |
| Backfill menebak dari `updated_at` | Data historis tidak dapat dipercaya | Sumber eksplisit dan skip data ambigu |
| Reopen tidak membersihkan selesai | Record aktif terlihat selesai | Update atomic pada seluruh Action reopen/resubmit |
| Double click menulis waktu berbeda | Audit tidak konsisten | Transaction, lock, dan status guard existing |
| Perhitungan per row menambah query | Index lambat | Presenter/accessor murni dari atribut record |
| Tabel terlalu lebar | Penggunaan mobile menurun | Toggle kolom teknis dan scroll container existing |
| Definisi selesai legacy berbeda dengan alur baru | Analitik membingungkan | Label asal/status tetap terlihat dan aturan didokumentasikan |

## 26. Open decisions

Tidak ada keputusan yang memblokir implementasi berdasarkan arahan saat ini. Asumsi yang dikunci oleh PRD:

1. Durasi memakai waktu kalender.
2. Sourcing project selesai pada keputusan final R&D, bukan setelah Pricelist.
3. Sourcing mandiri/legacy selesai mengikuti terminal flow existing.
4. SLA, overdue, export, dan dashboard analitik ditunda ke fitur terpisah.

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Characterization dan audit data | Baseline test, sumber backfill, dan query volume |
| 1 | Schema dan domain timestamp | Migration, cast, formatter/presenter, serta aturan mutation |
| 2 | UI index/detail/filter | Kolom, filter, dan ringkasan modal bersama Purchasing/R&D |
| 3 | Backfill dan observability | Command dry-run/apply, audit metadata, dan test idempotency |
| 4 | Dokumentasi, UAT, dan delivery | README aktual, report, rollout evidence, dan PR implementasi |

## 28. Definition of Done

- [x] Seluruh acceptance criteria terpenuhi pada test dan verifikasi lokal; UAT staging masih terbuka.
- [x] Test terdampak ditambahkan dan lulus.
- [x] Authorization diuji untuk role yang berhak dan tidak berhak.
- [x] Migration additive dan rollback dipahami.
- [ ] Backfill dry-run dan staging UAT mempunyai bukti hasil.
- [x] Dokumentasi fitur dan README Purchasing/R&D diperbarui sesuai implementasi.
- [x] Implementation report mencatat hasil, deviasi, dan record ambigu tanpa data sensitif.
