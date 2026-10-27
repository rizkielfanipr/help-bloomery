---
document_id: STANDARDS-DOCUMENTATION-STANDARD
title: Standar Dokumentasi Help Bloomery
status: in-review
version: 1.0
owner: Information Technology
technical_owner: Information Technology
created_at: 2026-10-27
updated_at: 2026-10-27
target_release: TBD
related_documents:
  - docs/README.md
  - docs/templates/README.md
supersedes: null
---

# Standar Dokumentasi Help Bloomery

Dokumen ini mengatur di mana dokumentasi disimpan, bagaimana diberi nama, kapan dibuat, dan bagaimana dirawat sampai diarsipkan. Tujuannya: setiap kebutuhan informasi mempunyai **satu dokumen canonical** yang mudah ditemukan.

## 1. Struktur folder

| Folder | Isi |
|---|---|
| `docs/README.md` | Portal dokumentasi dan indeks utama |
| `docs/templates/` | Template reusable; tidak berisi dokumen fitur |
| `docs/standards/` | Standar lintas modul: kode, UI, struktur codebase, dokumentasi |
| `docs/architecture/` | Gambaran sistem, data model, panel, RBAC, dan integrasi |
| `docs/products/{domain}/{feature}/` | Dokumentasi fitur per domain bisnis |
| `docs/operations/` | CI/CD, deployment, production, monitoring, backup/restore |
| `docs/reports/` | Audit dan laporan implementasi lintas modul yang tidak dimiliki satu fitur |
| `docs/archive/` | Dokumen yang sudah tidak berlaku; dibuat saat pertama kali ada dokumen yang diarsipkan |

Domain produk mengikuti kelompok menu/permission aplikasi: `analytics`, `daily-briefing`, `finance`, `information-technology`, `inventory`, `kitchen`, `operational`, `purchasing`, `rnd`, `sales-growth`, `workforce`. Folder domain dibuat hanya ketika ada dokumen di dalamnya.

## 2. Jenis dokumen

| Jenis | Nama file | Fungsi |
|---|---|---|
| Indeks fitur | `README.md` | Status, ringkasan, dan daftar dokumen fitur |
| PRD | `prd.md` | Sumber requirement canonical |
| Implementation plan | `implementation-plan.md` | Urutan teknis, phase, dan quality gate |
| Implementation prompt | `implementation-prompt.md` | Instruksi siap pakai untuk AI/developer |
| Rollout runbook | `rollout.md` | Konfigurasi, deployment, UAT, rollback, monitoring |
| Keputusan | `decisions/NNNN-judul.md` | ADR khusus fitur |
| Laporan | `reports/*.md` | Audit, phase report, implementation report |
| Standar | `docs/standards/*.md` | Aturan lintas modul |
| Runbook operasional | `docs/operations/**` | Prosedur server dan pipeline |

Tidak semua fitur memerlukan semua jenis dokumen. Jangan membuat file kosong hanya untuk melengkapi paket.

## 3. Kapan dokumen harus dibuat

- **PRD** — sebelum implementasi fitur baru atau perubahan perilaku yang menyentuh lebih dari satu modul, data production, integrasi eksternal, atau permission.
- **Implementation plan** — bila implementasi lebih dari satu phase atau lebih dari satu PR.
- **Implementation prompt** — bila implementasi dikerjakan oleh AI agent.
- **Rollout runbook** — bila ada migration, konfigurasi environment, queue/scheduler, integrasi eksternal, atau langkah manual di server.
- **ADR** — bila memilih satu dari beberapa opsi yang memiliki konsekuensi jangka panjang.
- **Report** — setelah audit atau setiap phase yang menghasilkan temuan atau keputusan baru.

## 4. Konvensi penamaan

- Gunakan kebab-case huruf kecil untuk folder dan file: `production-schedule/`, `phase-0-report.md`.
- Nama fitur di folder, bukan di nama file: `products/kitchen/production-schedule/prd.md`, bukan `products/kitchen/kitchen-production-schedule-prd.md`.
- Laporan phase: `reports/phase-{n}-report.md`; laporan akhir: `reports/implementation-report.md`; audit: `reports/{topik}-audit.md`.
- ADR: `decisions/0001-judul-keputusan.md`, nomor berurutan per fitur.
- Jangan memakai nama seperti `final-final.md`, `new-prd.md`, `latest.md`, atau `fix-v2-final.md`.
- Versi ditulis di metadata atau riwayat Git, bukan di nama file, kecuali dua versi memang harus berlaku bersamaan karena alasan kompatibilitas.

## 5. Metadata

Dokumen baru memakai front matter berikut:

```yaml
---
document_id: DOMAIN-FEATURE-TYPE
title: Nama Dokumen
status: draft
version: 1.0
owner: Nama Domain atau Tim
technical_owner: Information Technology
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
target_release: TBD
related_documents: []
supersedes: null
---
```

- `document_id` memakai huruf besar dan tanda hubung, misalnya `KITCHEN-PRODUCTION-SCHEDULE-PRD`.
- `related_documents` dan `supersedes` memakai path dari root repository.
- Dokumen lama tidak wajib langsung diberi metadata. Jangan mengarang owner, tanggal, target release, atau status. Bila informasi belum dapat dibuktikan, catat dokumen tersebut sebagai `needs-review` pada README domain.

## 6. Status lifecycle

| Status | Arti |
|---|---|
| `draft` | Sedang ditulis; belum boleh dijadikan acuan implementasi |
| `in-review` | Menunggu review owner bisnis/teknis |
| `approved` | Disetujui sebagai acuan implementasi |
| `in-development` | Implementasi sedang berjalan |
| `implemented` | Sudah berjalan di production dan sesuai dokumen |
| `deprecated` | Masih dibaca untuk konteks, tetapi tidak lagi menjadi acuan |
| `archived` | Dipindah ke `docs/archive/`; hanya untuk riwayat |

`needs-review` bukan status dokumen. Label ini dipakai di indeks untuk dokumen lama yang status tertulisnya hilang, tidak jelas, atau tidak sesuai kondisi kode.

## 7. Single source of truth

- Satu kebutuhan hanya memiliki satu dokumen canonical. Dokumen lain menautkan, bukan menyalin.
- Requirement berada di `prd.md`; prompt, plan, dan rollout merujuk requirement, bukan menuliskannya ulang dengan versi berbeda.
- Standar lintas modul berada di `docs/standards/`; PRD fitur merujuk standar tersebut.
- Bila dua dokumen bertentangan dan tidak ada yang menyatakan `supersedes`, jangan memilih pemenang. Catat keduanya sebagai `needs-review` di README domain beserta keputusan yang perlu dikonfirmasi.

## 8. Menautkan dokumen

- Antar-dokumen di dalam `docs/` memakai link Markdown relatif: `[PRD](../production-schedule/prd.md)`.
- Komentar kode merujuk path dari root repository: `docs/products/kitchen/production-schedule/prd.md §7`.
- Rujukan section memakai nomor heading (`§7.2`), sehingga nomor heading tidak boleh diubah tanpa memperbarui rujukannya.
- Setelah memindahkan atau mengganti nama file, jalankan `bash ops/ci/check-doc-links.sh`.

## 9. Menggantikan dokumen lama

1. Dokumen baru mengisi `supersedes` dengan path dokumen lama dan menjelaskan scope yang digantikan.
2. Dokumen lama diberi catatan di bagian atas: digantikan oleh dokumen apa, sejak kapan, dan scope mana.
3. Bila seluruh isi dokumen lama sudah tidak berlaku, pindahkan ke `docs/archive/` dengan `git mv`.
4. Bila hanya sebagian scope yang digantikan, dokumen lama tetap di tempatnya dengan status `deprecated` pada scope tersebut dan dicatat di README domain.

Jangan mempertahankan dua salinan penuh dokumen yang sama. Bila path lama perlu dipertahankan sementara, file lama hanya berisi status *moved*, path canonical baru, alasan, dan tanggal perpindahan.

## 10. Aturan archive

- Arsip dipindah dengan `git mv` ke `docs/archive/{path-asal-tanpa-docs}`.
- Tambahkan catatan di bagian atas: status `archived`, tanggal, alasan, dan dokumen pengganti.
- Perbarui README domain dan seluruh rujukan kode. Komentar kode tidak boleh merujuk dokumen arsip sebagai acuan aktif.

## 11. Implementation report

- Dibuat per phase atau di akhir implementasi di `reports/` fitur terkait.
- Wajib memuat: acuan PRD, status per phase, commit/PR, keputusan yang diambil, deviasi dari PRD, test yang dijalankan, risiko dan pekerjaan lanjutan.
- Report adalah catatan historis: jangan diubah setelah PR terkait di-merge, kecuali untuk memperbaiki fakta yang salah.

## 12. Update setelah fitur selesai

Setelah fitur berjalan di production:

1. Ubah `status` PRD menjadi `implemented` dan perbarui `updated_at`.
2. Catat deviasi antara PRD dan implementasi pada implementation report, bukan dengan menulis ulang PRD.
3. Perbarui README fitur dan README domain.
4. Perbarui `docs/architecture/` bila data model, panel, RBAC, atau integrasi berubah.

## 13. Menggunakan template

```bash
cp -R docs/templates/feature docs/products/{domain}/{feature}
```

Setelah menyalin:

1. Ganti nama file template menjadi nama standar (`prd-template.md` → `prd.md`, dan seterusnya).
2. Ganti seluruh placeholder `{...}`.
3. Hapus file template yang tidak diperlukan.
4. Isi metadata.
5. Tambahkan fitur ke README domain.
6. Jalankan `bash ops/ci/check-doc-links.sh`.

Lihat [docs/templates/README.md](../templates/README.md) untuk daftar template.

## 14. Checklist review dokumentasi

- [ ] Lokasi file sesuai struktur folder dan domain.
- [ ] Nama file kebab-case dan tanpa versi di nama.
- [ ] Metadata lengkap pada dokumen baru.
- [ ] Tidak ada dokumen canonical ganda untuk kebutuhan yang sama.
- [ ] Heading standar template tidak dihapus; bagian yang tidak relevan ditulis `Not applicable — {alasan}`.
- [ ] Rujukan ke standar dan dokumen terkait memakai link yang valid.
- [ ] README fitur dan README domain diperbarui.
- [ ] `bash ops/ci/check-doc-links.sh` lulus.
- [ ] Tidak ada secret, token, isi `.env`, payload production, atau data pribadi.

## 15. Data sensitif

Dokumentasi tidak boleh memuat:

- credential, token, API key, password, atau isi `.env`;
- payload, log, atau screenshot production yang memuat data pelanggan, karyawan, atau transaksi;
- nomor telepon, alamat, atau identitas pribadi.

Gunakan nama variabel environment (`ESB_TOKEN_*`) dan data contoh fiktif.
