# Phase 2 — Ekstraksi Awal Modul Teknisi

Tanggal: 18 September 2026.

Referensi: [PRD refactoring](codebase-refactoring-prd.md), [baseline Phase 1](codebase-refactoring-phase1-baseline.md), dan [inventaris](codebase-refactoring-phase1-inventory.md).

## Tujuan dan Batas Perubahan

Mengurangi duplikasi proses pembuatan request manual dan QR serta memusatkan transisi Menunggu Sparepart dan Lanjutkan Pengerjaan di workflow existing. Ini merupakan pola ekstraksi awal, bukan pemindahan seluruh modul Teknisi.

Folder dan ID panel, route, UI, permission, schema database, serta disk dan lokasi attachment tetap dipertahankan. Tidak membutuhkan migration, seeder, atau build frontend.

## Alur Request Manual

1. `TechnicianRequestPage` memeriksa cabang utama pengguna dan memvalidasi catatan serta attachment.
2. Halaman menyimpan attachment ke disk `b2` dengan path existing.
3. `CreateServiceRequestAction` membuat request berstatus Submitted, sumber manual, tanpa asset dan jadwal, menggunakan cabang utama pengguna.
4. `AssetServiceRequestAssigner` existing mencari teknisi yang memenuhi aturan assignment.
5. Halaman menampilkan feedback dan tautan WhatsApp existing.

## Alur Request QR

1. `AssetIssueReportController` menemukan asset dari token QR, memeriksa asset aktif dan akses cabang, lalu memvalidasi laporan dan foto.
2. Controller menyimpan foto ke disk dan path existing.
3. Action yang sama memeriksa akses asset dan membuat request menggunakan cabang asset, sumber asset_qr, tanpa jadwal.
4. Assigner existing memilih teknisi dan mengirim notifikasi assignment.
5. Controller mempertahankan notifikasi fallback jika belum ada teknisi, notifikasi sukses pengguna, dan redirect existing.

## Pembagian Tanggung Jawab

| Lapisan | Tanggung jawab |
| --- | --- |
| Page / controller | Validasi input dan file, penyimpanan upload, feedback, redirect |
| `CreateServiceRequestAction` | Guard cabang/asset, persistensi request dalam transaksi, pemanggilan assigner |
| `AssetServiceRequestAssigner` | Aturan pemilihan teknisi dan notifikasi assignment existing |
| `ServiceRequestWorkflow` | Otorisasi aktor dan cabang, pemeriksaan status, transisi proses |

Action menerima catatan yang sudah divalidasi dan daftar path file yang sudah disimpan. Action tidak menerima raw upload. Otorisasi workflow existing digunakan kembali; tidak dibuat sistem policy kedua.

## Transisi Sparepart

- `awaitParts`: InProgress → AwaitingParts.
- `resume`: AwaitingParts → InProgress.

Kedua transisi menggunakan transaksi dan row lock, memeriksa aktor/akses cabang dan status record terbaru sebelum memperbarui status. Repair yang sedang berjalan tetap dipertahankan. Trait Filament memanggil workflow dan tetap mengatur visibility serta refresh tampilan.

## Verifikasi

43 test lulus dengan 260 assertions. Cakupan: action baru, workflow teknisi, kuota, request QR, asset, export request, akses cabang, login, dan launcher. Test tambahan memastikan cabang manual/QR, assignment dan attachment, penolakan asset tidak aktif/tidak dapat diakses, serta transisi sparepart dan penolakan aktor/status yang tidak sesuai.

Snapshot 287 route dibandingkan dengan Phase 1: domain, method, URI, nama, action, dan middleware tetap sama. Pint dan pemeriksaan whitespace dijalankan.

Verifikasi menggunakan database test dan storage fake. Tidak menguji upload langsung ke R2, browser/kamera, atau production. Temuan existing pada baseline yang berada di luar alur ini tetap menjadi pekerjaan terpisah, termasuk perbedaan kontrak warranty dan cakupan akses back office. Maintenance tidak diubah pada phase ini.

## Pola untuk Phase Berikutnya

Ekstrak satu proses bisnis yang kohesif ke action/service existing, pertahankan validasi dan interaksi pada caller, lalu buktikan kontrak dengan test. Jangan memindahkan namespace atau mengubah perilaku UI bersamaan tanpa kebutuhan.

Phase berikutnya adalah Phase 3: evaluasi dan ekstraksi komponen UI reusable sesuai PRD. Perubahan Phase 1 dan Phase 2 belum di-commit atau di-push.
