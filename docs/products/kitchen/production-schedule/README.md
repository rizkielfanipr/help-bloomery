# Kitchen — Jadwal Produksi

| Atribut | Nilai |
|---|---|
| Domain | Kitchen |
| Menu | Kitchen → Jadwal Produksi |
| Status | Kalender Production Order ESB (PRD §25–§38): `in-development` — kode selesai, belum rollout. Rencana produksi lokal (PRD §1–§24): `deprecated`, dihapus pada v3.0 |
| Prioritas (PRD) | P1 |

## Ringkasan

Halaman ini adalah kalender Production Order ESB read-only untuk Branch Kitchen. Data dibaca dari snapshot lokal yang diisi oleh sinkronisasi queue; halaman tidak memanggil ESB dan tidak mengubah data. Perilaku saat ini dijelaskan di [README Kitchen](../README.md).

Riwayat keputusan:

- v2.0 (27 Oktober 2026) — Production Order ESB ditambahkan sebagai overlay read-only di samping rencana lokal.
- v2.1 (28 Oktober 2026) — seluruh halaman dan sinkronisasi dibatasi ke Branch ber-Brand `Kitchen`; mapping canonical Kitchen Jateng=`BLSS/KTJ`, Kitchen Surabaya=`BLO13/HOF`, Kitchen Jakarta=`BLHJ/TCHL`; ID Branch ESB numerik selalu dari sinkronisasi API.
- v3.0 (28 Oktober 2026) — rencana produksi lokal dihapus total termasuk tabelnya; kalender Production Order selalu tampil tanpa toggle environment.
- v3.1 (28 Oktober 2026) — mapping Production Order diambil dari Master Branch: setiap mapping aktif dan lengkap pada Branch Kitchen disinkronkan, tanpa allowlist company atau daftar pasangan di konfigurasi. Branch Kitchen baru cukup ditambahkan di Master Branch.

## Dokumen

| Dokumen | Keterangan |
|---|---|
| [PRD](prd.md) | Sumber requirement; §25–§38 berlaku, §1–§24 deprecated |
| [Implementation Plan](implementation-plan.md) | Rencana bertahap overlay read-only Production Order ESB |
| [Implementation Prompt](implementation-prompt.md) | Instruksi end-to-end untuk agent/developer; dihapus setelah rollout selesai |
| [Rollout](rollout.md) | Runbook staging/production, backup tabel lama, rekonsiliasi, dan rollback |
| [Implementation Report](reports/implementation-report.md) | Status phase, keputusan, deviasi, dan hasil test |

## Dokumen terkait

- [Code Remediation](../../../standards/code-remediation.md)
- [UI Consistency](../../../standards/ui-consistency.md)
- [Integrasi ESB](../../../architecture/integrations/esb.md) — boundary `EsbCoreClient`
- [R&D Project Task Calendar](../../rnd/project-task-calendar/prd.md) — referensi mekanisme kalender
