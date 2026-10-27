---
document_id: {DOMAIN}-{SCOPE}-REMEDIATION-PRD
title: PRD Remediasi — {SCOPE_NAME}
status: draft
version: 1.0
owner: {OWNER}
technical_owner: Information Technology
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
target_release: TBD
related_documents:
  - docs/standards/code-remediation.md
supersedes: null
---

# PRD Remediasi — {SCOPE_NAME}

> Remediasi memperbaiki perilaku atau kualitas yang sudah ada. Jangan menambah fitur baru di dokumen ini; fitur baru memakai template PRD fitur.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | {DOMAIN} |
| Scope | {SCOPE_NAME} |
| Status | draft |
| Audit acuan | `{AUDIT_REPORT_PATH}` |

## 2. Ringkasan

{Masalah utama dan hasil yang diharapkan setelah remediasi.}

## 3. Baseline dan bukti

{Kondisi saat ini, dengan path file, test, atau data yang membuktikan masalah.}

## 4. Temuan

| ID | Temuan | Severity | Bukti | Dampak |
|---|---|---|---|---|
| R-1 | {Temuan} | {Kritis/Tinggi/Sedang/Rendah} | `{path:line}` | {Dampak} |

## 5. Goals dan non-goals

- **Goals:** {Goals}
- **Non-goals:** {Non-goals}

## 6. Perilaku yang wajib dipertahankan

{Workflow, permission, route, data existing, dan kontrak integrasi yang tidak boleh berubah.}

## 7. Target perilaku

| ID temuan | Perilaku target | Acceptance criteria |
|---|---|---|
| R-1 | {Target} | {Kriteria} |

## 8. Data existing dan migrasi

{Perlakuan data yang sudah terdampak, backfill, dan dry-run.}

## 9. Authorization dan security

{Perubahan atau penguatan otorisasi.}

## 10. Testing strategy

{Characterization test sebelum perubahan, regression test per temuan.}

## 11. Phase remediasi

| Phase | Temuan | Output | Prasyarat |
|---|---|---|---|
| 1 | R-1 | {Output} | {Prasyarat} |

## 12. Rollout dan rollback

{Urutan deploy, feature flag, dan rollback.}

## 13. Risiko

| Risiko | Mitigasi |
|---|---|
| {Risiko} | {Mitigasi} |

## 14. Open decisions

| No | Keputusan | Pemilik |
|---|---|---|
| 1 | {Pertanyaan} | {Role} |

## 15. Definition of Done

- [ ] Setiap temuan memiliki test regresi.
- [ ] Perilaku yang wajib dipertahankan tetap lulus characterization test.
- [ ] Phase report ditulis untuk setiap phase.
