# Daftar Project Nonaktif

| Atribut | Nilai |
|---|---|
| Domain | Research & Development |
| Status | implemented |
| Owner | Research & Development |
| Technical owner | Information Technology |
| Modul terdampak | Back Office → Research & Development → Project |
| Target release | TBD |

## Ringkasan

Perubahan ini menambah tab Nonaktif pada index Project R&D untuk melihat Project Upcoming, Completed, dan Archived dengan filter status, serta memulihkan Project yang diarsipkan. Tab Daftar tetap hanya menampilkan Project aktif. Perubahan tidak menambah status, permission, tabel, integrasi, queue, atau scheduler.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | implemented | Sumber requirement canonical untuk tab Nonaktif dan pemulihan Project |

## Keputusan

| Keputusan | Status |
|---|---|
| Project nonaktif tampil pada tab terpisah di index yang sama, bukan pada tab Daftar | Dipilih saat implementasi 2 November 2026 berdasarkan permintaan user |
| Pemulihan memakai permission existing `edit rnd projects` | Mengikuti `HasPermissions` existing |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-02 | in-development | PRD dibuat dan implementasi dimulai pada branch `feature/rnd-inactive-project-list` |
| 2026-11-02 | implemented | Tab Nonaktif, filter status, dan pemulihan selesai pada branch yang sama; menunggu review, merge, dan UAT staging |
