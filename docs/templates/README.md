# Template Dokumentasi

Template reusable untuk dokumen baru. Aturan penamaan, metadata, dan lifecycle ada di [Standar Dokumentasi](../standards/documentation-standard.md).

## Daftar template

| Template | Dipakai untuk | Nama file setelah disalin |
|---|---|---|
| [feature/README.md](feature/README.md) | Indeks dan status fitur | `README.md` |
| [feature/prd-template.md](feature/prd-template.md) | Requirement fitur baru | `prd.md` |
| [feature/implementation-plan-template.md](feature/implementation-plan-template.md) | Urutan teknis dan phase | `implementation-plan.md` |
| [feature/implementation-prompt-template.md](feature/implementation-prompt-template.md) | Prompt untuk agent/developer | `implementation-prompt.md` |
| [feature/implementation-report-template.md](feature/implementation-report-template.md) | Laporan setelah implementasi | `reports/implementation-report.md` |
| [feature/rollout-runbook-template.md](feature/rollout-runbook-template.md) | Deployment, UAT, rollback | `rollout.md` |
| [remediation/remediation-prd-template.md](remediation/remediation-prd-template.md) | Perbaikan perilaku/kualitas yang sudah ada | `prd.md` |
| [remediation/audit-report-template.md](remediation/audit-report-template.md) | Audit sebelum remediasi | `reports/{topik}-audit.md` |
| [remediation/phase-report-template.md](remediation/phase-report-template.md) | Laporan per phase | `reports/phase-{n}-report.md` |
| [decision/adr-template.md](decision/adr-template.md) | Keputusan arsitektur/produk | `decisions/{nnnn}-{judul}.md` |

## Fitur baru

```bash
cp -R docs/templates/feature docs/products/{domain}/{feature}
cd docs/products/{domain}/{feature}
mv prd-template.md prd.md
mv implementation-plan-template.md implementation-plan.md
mv implementation-prompt-template.md implementation-prompt.md
mv rollout-runbook-template.md rollout.md
mkdir -p reports && mv implementation-report-template.md reports/implementation-report.md
```

Setelah menyalin:

1. Ganti seluruh placeholder `{...}`.
2. Hapus file yang tidak diperlukan, misalnya laporan sebelum implementasi dimulai.
3. Isi metadata front matter.
4. Tambahkan fitur ke README domain.
5. Jalankan `bash ops/ci/check-doc-links.sh`.

## Placeholder

| Placeholder | Isi |
|---|---|
| `{FEATURE_NAME}` | Nama fitur yang dibaca manusia |
| `{DOMAIN}` | Domain produk, misalnya `kitchen` |
| `{OWNER}` | Domain atau tim pemilik bisnis |
| `{PRD_PATH}` | Path PRD dari root repository |
| `{TARGET_BRANCH}` | Branch kerja |
| `{AFFECTED_MODULES}` | Modul/menu yang terdampak |
