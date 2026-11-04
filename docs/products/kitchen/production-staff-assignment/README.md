# Assignment Staff Produksi per Detail Production Order

| Atribut | Nilai |
|---|---|
| Domain | Kitchen |
| Status | in-development — kode selesai, menunggu merge, UAT staging, dan rollout |
| Owner | Kitchen |
| Technical owner | Information Technology |
| Modul terdampak | Kitchen → Jadwal Produksi; Master Employee sebagai sumber read-only |
| Target release | TBD |

## Ringkasan

Assignment lokal satu atau beberapa **Staff Produksi** pada setiap detail BOM/product di dalam Production Order ESB. Production Order tetap snapshot read-only; assignment disimpan di database Bloomery, dibatasi ke employee aktif pada Branch Kitchen yang sama, dan seluruh perubahannya diaudit. Perilaku yang berjalan dijelaskan di [README Kitchen](../README.md#staff-produksi).

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | in-development | Sumber requirement canonical; kontrak final Phase 0 di §14.2a, keputusan di §26 |
| [Implementation plan](implementation-plan.md) | in-development | Urutan phase, boundary teknis, migration, test, dan quality gate |
| [Rollout](rollout.md) | draft | Deployment additive, permission, UAT, monitoring, dan rollback |
| [Implementation report](reports/implementation-report.md) | draft | Hasil implementasi, deviasi, verifikasi, dan pekerjaan lanjutan |

## Keputusan

| Keputusan | Status |
|---|---|
| Assignment disimpan lokal per detail Production Order dan tidak dikirim ke ESB | Disetujui |
| Nama UI canonical adalah **Staff Produksi** | Disetujui |
| Satu detail mendukung multiple Staff Produksi | Disetujui |
| OD-1: hanya employee aktif dan satu Branch dengan order | Disetujui 4 November 2026 |
| OD-2: koreksi setelah detail selesai boleh dengan konfirmasi dan audit | Disetujui 4 November 2026 |
| OD-3: MVP hanya Back Office tanpa Employee App | Disetujui 4 November 2026 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-04 | draft | PRD dan paket planning dibuat dari audit kalender, snapshot detail, sync identity, Employee, permission, serta UI existing |
| 2026-11-04 | in-development | OD-1–OD-3 ditutup; implementasi Phase 0–4 di branch `feature/kitchen-production-staff-assignment` |
