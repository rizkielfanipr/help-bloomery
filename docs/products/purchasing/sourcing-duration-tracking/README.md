# Pelacakan Durasi Sourcing

| Atribut | Nilai |
|---|---|
| Domain | Purchasing |
| Status | implemented; UAT dan backfill staging belum dijalankan |
| Owner | Purchasing |
| Technical owner | Information Technology |
| Modul terdampak | Purchasing — Sourcing Bahan; R&D — Review Sourcing |
| Target release | TBD |

## Ringkasan

Fitur ini mencatat waktu material masuk ke proses sourcing, waktu sourcing mencapai keputusan final, dan durasi kalender proses tersebut. Informasi tampil pada index dan detail Sourcing Bahan serta Review Sourcing tanpa mencampurkan durasi sourcing dengan proses Pricelist Finance. Data lama diisi lewat command `rnd:backfill-sourcing-durations` yang default dry-run.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | implemented | Sumber requirement canonical |
| [Implementation plan](implementation-plan.md) | implemented | Urutan teknis, migration, backfill, dan quality gate |
| [Rollout](rollout.md) | draft | Deployment, backfill, UAT, monitoring, dan rollback; belum dijalankan |

## Keputusan

| Keputusan | Status |
|---|---|
| Durasi memakai waktu kalender, bukan hari kerja | Diimplementasikan |
| Pricelist Finance tidak termasuk durasi sourcing project | Diimplementasikan |
| Timestamp ditulis server-side dan tidak dapat diubah manual | Diimplementasikan |
| Record project tanpa tanggal mulai tetap null saat backfill, termasuk project legacy | Diimplementasikan; [laporan §3](reports/implementation-report.md#3-keputusan-yang-diambil) |

## Laporan

| Laporan | Tanggal |
|---|---|
| [Laporan implementasi](reports/implementation-report.md) | 2026-11-02 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-02 | draft | Dokumen planning dibuat berdasarkan audit implementasi existing |
| 2026-11-02 | implemented | Implementasi selesai; UAT dan backfill staging menunggu rollout |
