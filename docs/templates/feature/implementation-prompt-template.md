---
document_id: {DOMAIN}-{FEATURE}-PROMPT
title: Prompt Implementasi — {FEATURE_NAME}
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

# Prompt Implementasi — {FEATURE_NAME}

Salin blok berikut ke agent implementasi dari root repository.

```text
Implementasikan {FEATURE_NAME} pada domain {DOMAIN} di branch {TARGET_BRANCH}.

## Dokumen sumber yang wajib dibaca
- {PRD_PATH} — sumber requirement utama.
- docs/standards/code-remediation.md — standar arsitektur, keamanan data, dan kualitas teknis.
- docs/standards/ui-consistency.md — standar UI.
- AGENTS.md — aturan repository.
- {DOKUMEN_LAIN}

## Hasil akhir yang diharapkan
{Perilaku yang harus berjalan end-to-end setelah pekerjaan selesai.}

## Scope
{Scope}

## Non-scope
{Hal yang tidak boleh dikerjakan.}

## Modul terdampak
{AFFECTED_MODULES}

## Aturan repository
- Ikuti konvensi file sibling dan struktur folder existing.
- Gunakan `php artisan make:*` untuk file baru.
- Jangan menambah atau mengubah dependency tanpa persetujuan.
- Jalankan `vendor/bin/pint --dirty --format agent` setelah mengubah PHP.

## Aturan arsitektur
- Mutation berada di Action/Service, bukan langsung di Resource atau Blade.
- Perubahan status memakai transaction dan lock bila ada concurrency.
- {ATURAN_ARSITEKTUR_TAMBAHAN}

## Aturan authorization
- Setiap aksi divalidasi server-side melalui Policy/permission; visibility tombol bukan kontrol keamanan.
- {PERMISSION_BARU}

## Aturan UI
- Ikuti docs/standards/ui-consistency.md dan komponen existing.
- {ATURAN_UI_TAMBAHAN}

## Aturan integration
- Gunakan HTTP fake di test; jangan memanggil sistem eksternal sungguhan.
- Tangani timeout, retry, idempotency, dan hasil tidak pasti.
- {ATURAN_INTEGRASI_TAMBAHAN}

## Aturan data safety
- Migration hanya additive kecuali disetujui.
- Jangan mengubah atau menghapus data production.
- Jangan menulis credential atau payload sensitif ke log maupun dokumentasi.

## Tahapan implementasi
1. Audit repository dan catat baseline.
2. {Phase}
3. Jalankan test terdampak dan formatter.

## Test minimum
- {Test}

## Format laporan akhir
1. Branch aktif.
2. Ringkasan perubahan per phase.
3. File yang dibuat/diubah.
4. Test yang dijalankan dan hasilnya.
5. Deviasi dari PRD dan alasannya.
6. Risiko dan pekerjaan lanjutan.

## Larangan
Jangan commit, push, membuka PR, merge, atau deploy tanpa instruksi terpisah.
```
