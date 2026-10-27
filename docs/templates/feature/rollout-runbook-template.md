---
document_id: {DOMAIN}-{FEATURE}-ROLLOUT
title: Rollout Runbook — {FEATURE_NAME}
status: draft
version: 1.0
owner: {OWNER}
technical_owner: Information Technology
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
target_release: TBD
related_documents:
  - {PRD_PATH}
  - docs/operations/ci-cd/release-pipeline.md
supersedes: null
---

# Rollout Runbook — {FEATURE_NAME}

> Jangan menulis credential, token, atau isi `.env`. Sebutkan nama variabel environment saja.

## 1. Prerequisite

- [ ] PR {#PR} sudah di-merge dan CI hijau.
- [ ] {Prasyarat lain}

## 2. Environment dan configuration

| Variabel | Wajib | Default | Keterangan |
|---|---|---|---|
| `{ENV_NAME}` | {Ya/Tidak} | {Default} | {Keterangan} |

## 3. Migration

{Daftar migration, sifat additive, dan perkiraan durasi/lock pada tabel besar.}

## 4. Queue dan scheduler

{Job/command baru, queue yang dipakai, dan jadwal.}

## 5. External integration

{Sistem eksternal, environment yang dipakai (sandbox/production), dan batasan saat UAT.}

## 6. Backup

{Data yang perlu dibackup sebelum rollout dan lokasi backup.}

## 7. Deployment steps

1. {Langkah}

## 8. Smoke test

- [ ] {Pemeriksaan cepat setelah deploy}

## 9. Data verification

{Query/hal yang diperiksa untuk memastikan data benar. Jangan menyalin data pribadi ke dokumen.}

## 10. Monitoring

{Dashboard, log, dan metrik yang dipantau, beserta ambang normal.}

## 11. Rollback trigger

- {Kondisi yang mewajibkan rollback}

## 12. Rollback steps

1. {Langkah}

## 13. Post-rollout observation

{Durasi observasi dan hal yang dipantau.}

## 14. Sign-off

| Peran | Nama | Tanggal | Hasil |
|---|---|---|---|
| Owner bisnis | | | |
| Technical owner | | | |
