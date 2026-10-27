# Kitchen — Jadwal Produksi

| Atribut | Nilai |
|---|---|
| Domain | Kitchen |
| Menu | Kitchen → Jadwal Produksi |
| Status tertulis PRD | Revisi requirement sederhana — sumber requirement terbaru |
| Prioritas (PRD) | P1 |
| Indeks | needs-review — PRD belum memakai metadata standar; status lifecycle belum dikonfirmasi |

## Ringkasan

Kalender produksi sederhana: pengguna memilih tanggal lalu memilih beberapa BOM Assembly dari katalog lokal yang sudah dipakai modul R&D. Modul Kitchen tidak mempunyai approval, publish, atau sinkronisasi BOM sendiri.

PRD ini menyatakan menggantikan rancangan Jadwal Produksi sebelumnya; rancangan lama tersebut tidak tersimpan di repository.

## Dokumen

| Dokumen | Keterangan |
|---|---|
| [PRD](prd.md) | Sumber requirement canonical |
| [Implementation prompt](implementation-prompt.md) | Prompt implementasi end-to-end |

## Dokumen terkait

- [Code Remediation](../../../standards/code-remediation.md)
- [UI Consistency](../../../standards/ui-consistency.md)
- [R&D BOM Adjustment](../../rnd/bom-adjustment/prd.md) — katalog BOM Assembly
- [R&D Project Task Calendar](../../rnd/project-task-calendar/prd.md) — referensi mekanisme kalender

Path lama: `docs/kitchen-production-schedule-prd.md`, `docs/kitchen-production-schedule-implementation-prompt.md`.
