---
document_id: {DOMAIN}-{FEATURE}-PLAN
title: Implementation Plan — {FEATURE_NAME}
status: draft
version: 1.0
owner: {OWNER}
technical_owner: Information Technology
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
target_release: TBD
related_documents:
  - {PRD_PATH}
supersedes: null
---

# Implementation Plan — {FEATURE_NAME}

Acuan requirement: `{PRD_PATH}`. Dokumen ini hanya mengatur urutan teknis; requirement tidak ditulis ulang di sini.

## 1. Scope implementasi

- **Termasuk:** {Scope}
- **Tidak termasuk:** {Non-scope}

## 2. Baseline repository

{Commit/branch acuan, kondisi modul terkait, dan hasil audit yang relevan.}

## 3. File dan modul terdampak

| Area | Path perkiraan | Jenis perubahan |
|---|---|---|
| {Model/Action/Resource/View/Test} | `{path}` | {baru/ubah} |

## 4. Dependency dan integration boundary

{Package, service internal, dan sistem eksternal yang disentuh. Batas tanggung jawab tiap komponen.}

## 5. Urutan phase

| Phase | Scope | PR | Prasyarat |
|---|---|---|---|
| 0 | {Scope} | {Branch/PR} | {Prasyarat} |

## 6. Migration strategy

{Migration additive, urutan deploy, index, dan backfill.}

## 7. Data compatibility

{Perlakuan data lama, nilai default, dan kompatibilitas route/permission.}

## 8. Authorization

{Policy, permission baru, dan sinkronisasi role.}

## 9. UI

{Halaman/komponen yang dibuat atau diubah dan komponen existing yang dipakai ulang.}

## 10. Testing per phase

| Phase | Test minimum |
|---|---|
| 0 | {Test} |

## 11. Observability

{Audit log, monitoring transaction, dan log yang ditambahkan.}

## 12. Rollout

{Ringkasan; detail di `rollout.md`.}

## 13. Rollback

{Langkah rollback per phase.}

## 14. Risiko

| Risiko | Mitigasi |
|---|---|
| {Risiko} | {Mitigasi} |

## 15. Quality gates

- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Test terdampak lulus
- [ ] CI Pull Request hijau
- [ ] Review authorization dan data safety

## 16. Definition of Done

- [ ] Semua phase selesai sesuai PRD.
- [ ] Implementation report ditulis di `reports/`.
- [ ] README fitur diperbarui.
