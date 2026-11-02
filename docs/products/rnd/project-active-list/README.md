# Daftar Project Aktif

| Atribut | Nilai |
|---|---|
| Domain | Research & Development |
| Status | draft |
| Owner | Research & Development |
| Technical owner | Information Technology |
| Modul terdampak | Back Office → Research & Development → Project |
| Target release | TBD |

## Ringkasan

Perubahan ini mengganti grid kartu pada daftar utama Project R&D menjadi daftar baris yang ringkas dan responsif. Daftar utama hanya menampilkan Project yang aktif berdasarkan periode hari ini, sementara kalender Task tetap dipertahankan sebagai workspace terpisah. Perubahan tidak menambah status, permission, tabel, integrasi, queue, atau scheduler.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | draft | Sumber requirement canonical untuk tampilan dan scope daftar Project aktif |
| [Implementation plan](implementation-plan.md) | draft | Urutan teknis, file terdampak, test, dan quality gate |
| [Implementation prompt](implementation-prompt.md) | draft | Prompt siap pakai untuk agent implementasi |

## Keputusan

| Keputusan | Status |
|---|---|
| Kalender Task tetap tersedia dan bukan bagian dari daftar Project aktif | Disetujui pada 2 November 2026 |
| Daftar utama tidak menyediakan mode Upcoming, Completed, atau Archived | Disetujui pada 2 November 2026 |
| Definisi aktif memakai periode existing dan soft-delete existing | Disetujui pada 2 November 2026 |

## Laporan

| Laporan | Tanggal |
|---|---|
| Belum ada — implementasi belum dimulai | — |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-02 | draft | PRD, implementation plan, dan prompt implementasi dibuat; belum mengubah kode aplikasi |
