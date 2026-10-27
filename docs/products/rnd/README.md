# Research & Development

Kolom *Status tertulis* dikutip dari dokumen; tidak diubah saat restrukturisasi. *needs-review* berarti status tertulis tidak ada, tidak jelas, atau tidak sesuai kondisi kode saat ini.

| Fitur | Dokumen | Status tertulis | Indeks |
|---|---|---|---|
| BOM Adjustment | [PRD](bom-adjustment/prd.md) | Rancangan untuk implementasi bertahap | needs-review |
| Internal Memo | [PRD](internal-memo/prd.md) | Rancangan final untuk review; implementasi belum dimulai | needs-review — sebagian scope digantikan, lihat di bawah |
| Internal Memo — Simplification | [PRD](internal-memo-simplification/prd.md), [implementation report](internal-memo-simplification/reports/implementation-report.md) | Siap digunakan sebagai acuan implementasi; report: Phase 0–6 selesai (1 Oktober 2026) | needs-review |
| Internal Memo — Multi-Branch | [PRD](internal-memo-multi-branch/prd.md), [phase 0 report](internal-memo-multi-branch/reports/phase-0-report.md), [implementation report](internal-memo-multi-branch/reports/implementation-report.md) | Siap untuk review; belum mengotorisasi deployment | needs-review — scope Branch/company digantikan PRD Brand |
| Internal Memo — Brand | [PRD](internal-memo-brand/prd.md), [implementation prompt](internal-memo-brand/implementation-prompt.md) | Rancangan untuk review; belum mengotorisasi migrasi atau deployment production | needs-review |
| Material Sourcing Lifecycle | [PRD](material-sourcing-lifecycle/prd.md), [implementation prompt](material-sourcing-lifecycle/implementation-prompt.md) | Draft untuk review; belum mengotorisasi implementasi, mutation ESB production, migration production, atau deployment | needs-review — workflow sudah di-merge ke `main` (PR #11) |
| Project Checkpoint Calendar | [PRD](project-checkpoint-calendar/prd.md) | Rancangan untuk review; belum mengotorisasi implementasi, migration production, deployment, atau permission sync | needs-review |
| Project Task Calendar | [PRD](project-task-calendar/prd.md), [phase 6 report](project-task-calendar/reports/phase-6-report.md) | Implemented / Completed | — |
| Project Types | [PRD](project-types/prd.md), [implementation prompt](project-types/implementation-prompt.md) | Draft untuk persetujuan | needs-review |
| WIP Shelf Life | [PRD](wip-shelf-life/prd.md) | Rancangan untuk review; belum mengotorisasi implementasi, migration production, atau deployment | needs-review — permission Shelf Life Menu lama sudah dihapus dari kode |

Dataset: [development-todo.csv](development-todo.csv).

## Rantai dokumen Internal Memo

Keempat PRD Internal Memo berlaku berlapis. Relasinya dinyatakan oleh dokumen itu sendiri:

1. **Internal Memo** — PRD awal.
2. **Simplification** — menggantikan alur operasional PRD awal (status, finalisasi, revisi, dan workflow PDF).
3. **Multi-Branch** — menambahkan Branch Tujuan dan scope company per Branch.
4. **Brand** — menggantikan ketentuan Branch Tujuan, company berdasarkan Branch, pembatasan akses Branch, katalog per Branch, dan pivot Menu–Branch dari Multi-Branch.

Belum ada dokumen yang menyatakan requirement final gabungan. **Keputusan yang perlu dikonfirmasi:** apakah PRD Brand + Simplification menjadi acuan tunggal dan PRD awal serta Multi-Branch diarsipkan.

Path lama: seluruh file `docs/rnd-*.md`, `docs/material-sourcing-lifecycle-*.md`, dan `docs/rnd-development-todo.csv`.
