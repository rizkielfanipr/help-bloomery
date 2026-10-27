---
document_id: {DOMAIN}-{FEATURE}-PRD
title: PRD — {FEATURE_NAME}
status: draft
version: 1.0
owner: {OWNER}
technical_owner: Information Technology
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
target_release: TBD
related_documents:
  - docs/standards/code-remediation.md
  - docs/standards/ui-consistency.md
supersedes: null
---

# PRD — {FEATURE_NAME}

> Jangan menghapus heading standar. Bagian yang tidak relevan ditulis `Not applicable — {alasan}`.

## 1. Identitas dan status

| Atribut | Nilai |
|---|---|
| Domain | {DOMAIN} |
| Fitur | {FEATURE_NAME} |
| Status | draft |
| Prioritas | {P0/P1/P2} |
| Target pengguna | {ROLE/TIM} |
| Modul terdampak | {AFFECTED_MODULES} |
| Acuan arsitektur | `docs/standards/code-remediation.md` |
| Acuan UI | `docs/standards/ui-consistency.md` |

## 2. Executive summary

{Ringkasan 3–5 kalimat: masalah, solusi, dan dampak yang diharapkan.}

## 3. Latar belakang

{Konteks bisnis dan alasan fitur dibutuhkan sekarang.}

## 4. Baseline sistem

{Kondisi aplikasi saat ini yang sudah diaudit: modul, model, permission, route, integrasi, dan data existing yang relevan. Sebutkan path file bila perlu.}

## 5. Problem statement

{Masalah spesifik yang diselesaikan, dengan contoh nyata bila ada.}

## 6. Goals

1. {Goal}

## 7. Success metrics

| Metric | Baseline | Target | Cara mengukur |
|---|---|---|---|
| {Metric} | {Nilai} | {Nilai} | {Sumber data} |

## 8. Non-goals

- {Hal yang sengaja tidak dikerjakan}

## 9. Aktor dan tanggung jawab

| Aktor | Tanggung jawab | Permission |
|---|---|---|
| {Role} | {Tanggung jawab} | `{permission}` |

## 10. User journey

1. {Langkah pengguna dari awal sampai akhir}

## 11. Business rules

1. {Aturan bisnis yang wajib dijaga server-side}

## 12. Functional requirements

| ID | Requirement | Prioritas |
|---|---|---|
| FR-1 | {Requirement} | {Must/Should/Could} |

## 13. Status dan lifecycle

| Status | Makna | Transisi yang diizinkan | Aktor |
|---|---|---|---|
| {status} | {Makna} | {status berikutnya} | {Role} |

## 14. Model data konseptual

{Entitas, field penting, relasi, constraint unik, dan aturan penghapusan. Nama tabel final mengikuti audit schema.}

## 15. Integration contract

{Endpoint/sistem eksternal, request/response yang dibutuhkan, idempotency, retry, timeout, dan perilaku saat hasil tidak pasti.}

## 16. Authorization dan security

{Policy/permission per aksi, record scope (company/branch), validasi server-side, file upload, dan data sensitif.}

## 17. UI/UX specification

{Lokasi menu, halaman, tabel, filter, form, modal, empty/loading/error state, dan perilaku mobile. Ikuti `docs/standards/ui-consistency.md`.}

## 18. Non-functional requirements

{Performa, volume data, concurrency, availability, dan batas ukuran.}

## 19. Observability dan audit

{Audit trail, log, monitoring transaction, notifikasi gagal, dan metrik yang perlu dipantau.}

## 20. Data migration dan kompatibilitas

{Migration additive, backfill, perlakuan data lama, dan kompatibilitas route/permission existing.}

## 21. Testing strategy

{Characterization test, feature test per aksi, authorization test, HTTP fake untuk integrasi, dan test regresi.}

## 22. Rollout

{Feature flag, urutan deploy, konfigurasi, permission sync, dan UAT. Detail operasional ditulis di `rollout.md`.}

## 23. Rollback

{Cara mengembalikan aplikasi dan data bila rollout gagal.}

## 24. Acceptance criteria

1. {Kriteria yang dapat diverifikasi}

## 25. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| {Risiko} | {Dampak} | {Mitigasi} |

## 26. Open decisions

| No | Keputusan | Opsi | Pemilik keputusan | Batas waktu |
|---|---|---|---|---|
| 1 | {Pertanyaan} | {Opsi} | {Role} | {Tanggal/phase} |

## 27. Implementation phases

| Phase | Scope | Output |
|---|---|---|
| 0 | Audit dan kontrak | {Output} |

## 28. Definition of Done

- [ ] Seluruh acceptance criteria terpenuhi.
- [ ] Test terdampak ditambahkan dan lulus.
- [ ] Authorization diuji untuk role yang berhak dan tidak berhak.
- [ ] Migration additive dan rollback dipahami.
- [ ] Dokumentasi fitur dan README domain diperbarui.
