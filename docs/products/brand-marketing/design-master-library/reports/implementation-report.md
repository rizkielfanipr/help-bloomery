---
document_id: BRAND-MARKETING-DESIGN-MASTER-LIBRARY-REPORT
title: Laporan Implementasi — Master Design Library Berbasis Brand
status: draft
version: 1.0
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

Acuan: `docs/products/brand-marketing/design-master-library/prd.md`

## 1. Status per phase

| Phase | Status | Catatan |
|---|---|---|
| 0 Audit | Selesai | Keputusan dicatat di PRD §18.2 dan §27 |
| 1 Domain | Selesai | Migration additive, model, factory, enum, policy, permission, audit |
| 2 File | Selesai | Upload privat, checksum, compensation, download terotorisasi; diuji dengan fake disk |
| 3 Lifecycle | Selesai | Draft sampai superseded, archive, restore, readiness guard, notifikasi |
| 4 UI | Selesai | Index, detail section card, editor modal, read-only Purchasing, sidebar |
| 5 Delivery | Sebagian | Test, dokumentasi, dan UAT lokal selesai; UAT staging belum |

## 2. Ringkasan perubahan

Menu **Brand Marketing → Master Design** dipakai bersama oleh Designer, Reviewer, Publisher, dan Purchasing. Mutasi berada di `app/Actions/BrandMarketing/DesignMaster/`, sedangkan file, readiness, audit, notifikasi, dan perbandingan berada di `app/Services/BrandMarketing/DesignMaster/`. Tabel dan file Project R&D serta fulfillment tidak disentuh.

## 3. Keputusan yang diambil

| Keputusan | Alasan | Dokumen terkait |
|---|---|---|
| Batas 12 MB per file untuk semua role | Aturan upload Livewire global saat ini 12 MB, dan menaikkannya memengaruhi semua upload aplikasi | PRD §18.2 |
| `published_marker` dengan unique index | Menjaga satu published version di MySQL dan SQLite tanpa generated column | PRD §14.2 |
| Soft delete untuk item dan file | Item boleh dilepas dari draft, tetapi tidak ada hard delete dan object bisa dipakai version lain | PRD §18.3 |
| Purchasing ditolak pada version superseded | Mencegah desain lama dikirim ke vendor | PRD §16 |
| File supporting hanya untuk pengguna internal | Purchasing hanya memperoleh view dan production | PRD §16 |
| Dimensi dan material sebagai spesifikasi minimum item fisik | PRD mewajibkan spesifikasi tanpa menyebut field minimum | PRD §11.12 |
| Policy dievaluasi langsung, bukan lewat Gate | `Gate::before` melewati aturan status bagi SUPERADMIN | PRD §16 |
| Revisi baru hanya bila tidak ada version terbuka | Satu alur kerja per bundle mencegah dua draft saling menimpa saat publish | PRD §11.6 |

## 4. Deviasi dari PRD

| Bagian PRD | Deviasi | Alasan | Tindak lanjut |
|---|---|---|---|
| §18.2 | Baseline 15 MB dan 100 MB turun menjadi 12 MB | Batas Livewire global; PRD meminta penurunan dicatat | Bila file cetak lebih besar dibutuhkan: audit PHP/proxy dan ubah aturan Livewire pada keputusan terpisah |
| §17.3 | Unggah file baru berada pada card Design Items, bukan Design Files | File selalu milik satu item; ganti dan lepas tetap di Design Files | Tidak ada |
| §14.1, §17.2 | Periode berlaku dihapus dari schema, form, index, filter, dan detail; input ringkasan Version 1 dihapus dari form pembuatan | Permintaan user setelah implementasi | PRD sudah diperbarui; ringkasan tetap tersedia lewat Edit Ringkasan |
| §17.4 | Editor berupa modal pada detail, bukan halaman form terpisah | Mengikuti pola edit Project Detail; item dan upload tetap dipisah dengan jelas | Tidak ada |

## 5. Hasil verifikasi

| Pemeriksaan | Hasil |
|---|---|
| Test Master Design dan regression `MarketingMaterialFulfillmentTest` | 150 test lulus, 673 assertion |
| Suite Feature penuh dalam 4 shard | 2.447 test lulus; dijalankan sebelum perbaikan penguncian Brand, yang setelahnya diuji ulang dengan 150 test di atas |
| Migration MySQL lokal | Lulus; unique index dan foreign key terbentuk |
| Publish paralel dua proses pada MySQL lokal | Berurutan lewat row lock; tepat satu published dan satu superseded |
| Browser lokal 360, 390, 768, 1024, 1440 px dan setara zoom 200% | Tidak ada overflow atau error JavaScript |
| Dark mode, nama panjang, empty state, preview tidak tersedia, Escape pada modal | Sesuai |
| Tombol per peran di browser (SUPERADMIN pada draft, Purchasing) | Sesuai setelah perbaikan; lihat §6 |

Belum diuji: upload dan download ke object storage sungguhan, batas PHP/proxy staging, serta login browser memakai role Designer, Reviewer, dan Publisher. Role tersebut hanya diuji lewat feature test.

## 6. Temuan saat implementasi

| Temuan | Penanganan |
|---|---|
| Action yang dirender manual mengabaikan `visible()`, sehingga tombol mutasi tampil bagi Purchasing walau server menolaknya | Blade memeriksa `isVisible()`; test memeriksa HTML |
| `Gate::before` membuat SUPERADMIN melewati aturan status | `MarketingDesignAccess` memanggil method Policy langsung; ada test khusus |
| Validasi 15 MB pada upload Project R&D existing secara nyata dibatasi 12 MB oleh Livewire | Tidak diubah karena di luar scope; dicatat sebagai temuan |
| Brand dapat diubah pada bundle published, padahal menjadi identitas bundle dan bagian path file | Brand dikunci setelah bundle dibuat; PRD §11.1 diperbarui |
| Foreign key baru membuat hapus Brand yang punya bundle gagal dengan error database | Aksi hapus Brand kini memberi pesan jelas dan membatalkan penghapusan |

## 7. Limit dan format file final

| Role | Format | Batas |
|---|---|---|
| Preview | JPG, JPEG, PNG, WEBP, PDF | 12 MB |
| Production | PDF, ZIP | 12 MB |
| Source | ZIP | 12 MB |
| Supporting | PDF, JPG, JPEG, PNG, WEBP, ZIP | 12 MB |

## 8. Risiko dan pekerjaan lanjutan

- File cetak atau source di atas 12 MB belum dapat diunggah. Ini risiko adopsi terbesar dan perlu dinilai Brand Marketing pada UAT.
- Batas PHP dan reverse proxy production belum terverifikasi.
- Object dari file yang diganti atau dilepas tetap tersimpan. Cleanup memerlukan audit referensi tersendiri.
- Reviewer dan publisher belum ditunjuk; hingga itu, hanya SUPERADMIN yang dapat menyelesaikan publish.
- Integrasi Project R&D dan order memerlukan PRD terpisah.
