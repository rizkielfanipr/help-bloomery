---
document_id: BRAND-MARKETING-DESIGN-MASTER-LIBRARY-REPORT
title: Laporan Implementasi — Master Design Library Berbasis Brand
status: draft
version: 2.4
owner: Brand Marketing
technical_owner: Information Technology
created_at: 2026-11-03
updated_at: 2026-11-03
target_release: TBD
related_documents:
  - docs/products/brand-marketing/design-master-library/prd.md
supersedes: null
---

# Laporan Implementasi — Master Design Library Berbasis Brand

Acuan: `docs/products/brand-marketing/design-master-library/prd.md` (v2.4)

## 1. Status per phase

| Phase | Status | Catatan |
|---|---|---|
| 0 Audit | Selesai | Keputusan di PRD §18.2 dan §27 |
| 1 Domain | Selesai | Migration additive, model, factory, enum, policy, permission, audit |
| 2 File | Selesai | Upload privat, checksum, compensation, download terotorisasi; diuji dengan fake disk |
| 3 Action | Selesai | Bundle, item, file, arsip, dan pemulihan |
| 4 UI | Selesai | Index, detail section card, modal, read-only Purchasing, sidebar |
| 5 Delivery | Sebagian | Test, dokumentasi, dan UAT lokal selesai; UAT staging belum |

## 2. Ringkasan perubahan

Menu **Brand Marketing → Master Design** menjadi penyimpanan file desain per Brand: bundle berisi item, dan setiap item mempunyai satu file desain privat yang diunggah saat item ditambah serta spesifikasi produksi yang diisi terpisah. Designer mengubah isi bundle secara langsung, Purchasing melihat bundle aktif dan mengunduh file desain. Mutasi berada di `app/Actions/BrandMarketing/DesignMaster/`; file, kelengkapan, akses, dan audit berada di `app/Services/BrandMarketing/DesignMaster/`. Tabel dan file Project R&D serta fulfillment tidak disentuh.

## 3. Perubahan scope sebelum merge

Implementasi pertama memuat version, revisi, review, approval, publish, supersede, dan notifikasi sesuai PRD v1.1. Setelah mencobanya, user memutuskan fitur cukup berupa penyimpanan file. Seluruh bagian itu dihapus pada branch yang sama sebelum merge (PRD v2.0), termasuk tabel `marketing_design_bundle_versions` dan permission `review`/`publish design master bundles`. Karena belum pernah di-deploy, migration ditulis ulang tanpa migration penghapus.

Pada perubahan yang sama, periode berlaku dan ringkasan Version 1 juga dihapus dari form.

Perubahan berikutnya (PRD v2.1), juga atas keputusan user: jenis file (preview, production, source, supporting) dan card Design Files dihapus. Setiap item kini mempunyai satu file desain yang wajib diunggah di modal Tambah Item, spesifikasi produksi diisi lewat aksi terpisah, dan dua permission unduh digabung menjadi `download design master files`. Path file tidak lagi memuat ID item agar item dan file tersimpan dalam satu transaksi.

Perubahan terakhir (PRD v2.2), juga atas keputusan user: tombol Lepas menjadi **Hapus** dan menghapus item permanen beserta seluruh file dan object-nya. Ini menggantikan aturan awal "tidak ada hard delete" untuk item; bundle tetap hanya dapat diarsipkan dan audit tetap mencatat penghapusan. Tombol Unduh kini selalu tampil (nonaktif bila belum ada file), dan tombol file berlabel Unggah File untuk item tanpa file. Index kemudian dibuat ulang mengikuti pola list Project R&D (PRD v2.3), menggantikan tabel Filament beserta render hook header filternya. Card Audit Activity juga dihapus dari halaman detail (PRD v2.4); audit tetap dicatat di database.

## 4. Keputusan yang diambil

| Keputusan | Alasan |
|---|---|
| Batas 12 MB per file | Aturan upload Livewire global saat ini 12 MB, dan menaikkannya memengaruhi semua upload aplikasi (PRD §18.2) |
| Brand dikunci setelah bundle dibuat | Brand menjadi identitas bundle dan bagian path file |
| Hapus item permanen; file yang diganti terhapus lunak | Keputusan user (v2.4); object file lama tetap ada sampai itemnya dihapus |
| Status kelengkapan item hanya informatif | Tanpa publish, tidak ada titik yang perlu diblokir |
| Satu permission unduh untuk semua file | Keputusan user setelah jenis file dihapus; Purchasing dapat mengunduh semua file di bundle aktif |
| Policy dievaluasi langsung, bukan lewat Gate | `Gate::before` melewati aturan status bagi SUPERADMIN |

## 5. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §18.2 | Baseline 15 MB dan 100 MB turun menjadi 12 MB | Batas Livewire global | Bila file cetak lebih besar dibutuhkan: audit PHP/proxy dan ubah aturan Livewire lewat keputusan terpisah |

## 6. Hasil verifikasi

| Pemeriksaan | Hasil |
|---|---|
| Test Master Design dan regression `MarketingMaterialFulfillmentTest` | 111 test lulus, 515 assertion |
| Suite Feature penuh (4 shard) | Lihat PR |
| Migration MySQL lokal | Lulus |
| Browser lokal 360, 390, 768, 1024, 1440 px dan setara zoom 200% | Tidak ada overflow atau error JavaScript; urutan card dan tombol per peran sesuai |
| Dark mode, nama panjang, empty state, preview tidak tersedia, Escape pada modal | Sesuai |

Belum diuji: upload dan download ke object storage sungguhan, batas PHP/proxy staging, serta login browser sebagai pengelola arsip (hanya feature test).

## 7. Temuan saat implementasi

| Temuan | Penanganan |
|---|---|
| Action yang dirender manual mengabaikan `visible()`, sehingga tombol mutasi tampil bagi Purchasing walau server menolaknya | Blade memeriksa `isVisible()`; test memeriksa HTML |
| `Gate::before` membuat SUPERADMIN melewati aturan status | `MarketingDesignAccess` memanggil method Policy langsung |
| Validasi 15 MB pada upload Project R&D existing secara nyata dibatasi 12 MB oleh Livewire | Tidak diubah karena di luar scope |
| Halaman list kustom tidak merender tabel Filament, padahal tabel itulah yang biasanya merender modal action, sehingga tombol Buat Bundle tidak membuka modal | View index merender `<x-filament-actions::modals />` secara eksplisit; diverifikasi di browser |
| Tema Back Office (`.fi-modal-footer` flex rata kanan) membuat tombol modal `requiresConfirmation` terjepit hingga sekitar 43 px | Modal konfirmasi Master Design memakai footer rata kanan. Perbaikan di tema dibiarkan untuk PR terpisah karena berdampak pada semua modul |
| Foreign key baru membuat hapus Brand yang punya bundle gagal dengan error database | Aksi hapus Brand kini memberi pesan jelas |

## 8. Limit dan format file final

| Role | Format | Batas |
|---|---|---|
| Preview | JPG, JPEG, PNG, WEBP, PDF | 12 MB |
| Production | PDF, ZIP | 12 MB |
| Source | ZIP | 12 MB |
| Supporting | PDF, JPG, JPEG, PNG, WEBP, ZIP | 12 MB |

## 9. Risiko dan pekerjaan lanjutan

- File cetak atau file kerja di atas 12 MB belum dapat diunggah.
- Semua file di bundle aktif dapat diunduh oleh pemegang `download design master files`, termasuk file editable bila Designer mengunggahnya.
- Tanpa alur persetujuan, file yang diganti Designer langsung terlihat oleh Purchasing. Jejaknya ada di audit dan object lama tetap tersimpan.
- Batas PHP dan reverse proxy production belum terverifikasi.
- Object dari file yang diganti tetap tersimpan sampai itemnya dihapus.
- Hapus item tidak dapat dibatalkan; bila penghapusan object di penyimpanan gagal, object yatim tercatat di log dan perlu dibersihkan manual.
- Integrasi Project R&D dan order memerlukan PRD terpisah.
