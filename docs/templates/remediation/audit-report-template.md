---
document_id: {DOMAIN}-{SCOPE}-AUDIT
title: Audit — {SCOPE_NAME}
status: draft
version: 1.0
owner: {OWNER}
technical_owner: Information Technology
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
target_release: TBD
related_documents: []
supersedes: null
---

# Audit — {SCOPE_NAME}

## 1. Identitas

| Atribut | Nilai |
|---|---|
| Tanggal audit | YYYY-MM-DD |
| Baseline | Branch `{branch}`, commit `{hash}` |
| Scope | {Modul/area yang diaudit} |
| Metode | {Baca kode, test, query read-only, review konfigurasi} |
| Batasan | {Yang tidak diaudit, misalnya data production} |

## 2. Ringkasan

{Kesimpulan utama dalam 3–5 kalimat.}

## 3. Peta kondisi saat ini

{Alur, modul, model, permission, dan integrasi yang relevan.}

## 4. Temuan

| ID | Severity | Temuan | Bukti | Dampak | Rekomendasi |
|---|---|---|---|---|---|
| A-1 | {Kritis/Tinggi/Sedang/Rendah} | {Temuan} | `{path:line}` | {Dampak} | {Rekomendasi} |

## 5. Hal yang sudah benar

- {Perilaku yang sesuai dan perlu dipertahankan}

## 6. Cakupan test saat ini

{Test yang ada, yang lulus, dan celah yang tidak terdeteksi test.}

## 7. Rekomendasi urutan perbaikan

1. {Perbaikan}

## 8. Pertanyaan terbuka

- {Pertanyaan untuk owner bisnis/teknis}
