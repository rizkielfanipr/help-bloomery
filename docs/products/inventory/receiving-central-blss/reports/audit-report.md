---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-AUDIT
title: Audit — Receiving BLSS dan Ketergantungan Mapping Branch Lokal
status: in-review
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-30
updated_at: 2026-10-30
target_release: TBD
related_documents:
  - docs/products/inventory/receiving-central-blss/prd.md
  - docs/products/inventory/receiving-simplification/prd.md
supersedes: null
---

# Audit — Receiving BLSS dan Ketergantungan Mapping Branch Lokal

## 1. Identitas

| Atribut | Nilai |
|---|---|
| Tanggal audit | 2026-10-30 |
| Baseline | Branch `docs/receiving-blss-central-inventory-plan`, aplikasi dari `main` commit `d750709a` |
| Scope | Employee App Receiving, persist GR, mapping branch, monitoring, policy/resource, dan test terkait |
| Metode | Baca kode dan dokumentasi, review test, menjalankan test lokal read-only terhadap integrasi yang di-fake/mock |
| Batasan | Tidak membaca database, credential, payload, log, atau transaksi production; tidak mengirim request ESB nyata |

## 2. Ringkasan

Authorization Employee App sudah mengikuti permission Receiving, dan daftar PO sudah dibatasi ke BLSS. Kegagalan terjadi lebih akhir: submit mewajibkan mapping aktif dan dua bagian berikutnya dereference mapping sebagai nilai wajib. Schema lokal sudah mendeskripsikan `local_branch_id` nullable, sehingga perbaikan dapat dilakukan tanpa migration. Resolver existing memiliki efek samping update saat fallback branch code; Receiving memerlukan lookup read-only agar submit tidak mengubah konfigurasi Master Branch.

## 3. Peta kondisi saat ini

| Area | Kondisi saat ini |
|---|---|
| Daftar PO | `GoodsReceiptPage::loadPurchaseOrders()` meminta Authorized dan Receiving hanya untuk `BLSS` |
| Akses Employee App | Policy `accessEmployeeApp` dan `submit` memakai `access employee app goods receipt` |
| Detail PO dan lokasi | Diambil dari ESB memakai company BLSS dan branch ID PO |
| Submit | Status PO dibaca ulang, lalu resolver mapping wajib berhasil |
| Persist lokal | Menyimpan company, ESB branch, local branch, supplier, location, QC, attachment, payload, dan submission key |
| Mutasi ESB | `EsbGoodsReceiptService::create()` dibungkus `EsbTransactionRecorder` |
| QC downstream | Item/expiry disimpan; rejection membuat Vendor Compliance incident dan notifikasi Purchasing |
| Back Office | Read-only; akses record mengikuti `view goods receipts` dan scope branch tersendiri |

## 4. Temuan

| ID | Severity | Temuan | Bukti | Dampak | Rekomendasi |
|---|---|---|---|---|---|
| A-1 | Tinggi | Guard mapping memblokir PO unmapped | `GoodsReceiptPage.php:424–441` | Inventory Pusat tidak dapat menyelesaikan GR | Jadikan mapping opsional setelah validasi BLSS/PO |
| A-2 | Tinggi | Persist dan monitoring dereference mapping | `GoodsReceiptPage.php:491–498`, `606–617` | Null mapping akan menghasilkan exception | Ubah kontrak internal menjadi nullable dan kirim `branch_id` null |
| A-3 | Sedang | Resolver fallback melakukan update konfigurasi | `EsbBranchMappingResolver.php:34–42` | Submit dapat memutasi Master Branch | Tambah lookup read-only khusus kebutuhan Receiving |
| A-4 | Sedang | Test existing mengharapkan penolakan unmapped | `GoodsReceiptPageTest.php:306–350` | Safety net bertentangan dengan requirement baru | Ganti dengan test sukses dan pertahankan test negatif lain |
| A-5 | Sedang | Back Office memakai authorization berbeda | `GoodsReceiptPolicy.php:11–21`, `GoodsReceiptResource.php:51–77` | Menghapus mapping dapat memengaruhi visibility record | Pertahankan scope; test nullable untuk viewer terkait |
| A-6 | Rendah | Dokumen lama mencampur rencana SPN dan branch access | `receiving-simplification/prd.md` §12–§13 | Scope implementasi berisiko melebar | Tautkan remediasi canonical dan tunda multi-company |

## 5. Hal yang sudah benar

- Company list saat ini terkunci ke `BLSS`.
- Policy submit memakai permission Receiving, bukan akses branch user.
- Status PO diverifikasi ulang sebelum create.
- Location berasal dari endpoint branch PO.
- Submission key dan status `unknown` tersedia untuk menjaga idempotency dan rekonsiliasi.
- Data branch ESB dan nama branch sudah mempunyai kolom terpisah dari local branch.
- Recorder menerima context `branch_id` nullable; service monitoring kemungkinan tidak perlu diubah, hanya call site dan test.
- Back Office bersifat read-only dan memiliki permission terpisah.

## 6. Cakupan test saat ini

Baseline yang dijalankan pada 30 Oktober 2026:

```text
46 test lulus, 310 assertion, 0 gagal
```

File yang dijalankan: `GoodsReceiptPageTest`, `ReceivingAuthorizationTest`, `EsbGoodsReceiptServiceTest`, `EsbTransactionRecorderTest`, `InboundGoodsReceiptQcServiceTest`, dan `GoodsReceiptServiceTest`.

Cakupan existing sudah meliputi BLSS-only list, mapped branch lintas akses user, penolakan unmapped, permission, batch/ED, dokumen/foto, bulk action, idempotency/unknown outcome, ESB client, monitoring, dan QC. Celah utama: sukses tanpa mapping, lookup tanpa mutasi, nullable monitoring context, context PO berubah, dan visibility Back Office untuk receipt nullable.

## 7. Rekomendasi urutan perbaikan

1. Tambahkan test yang mereproduksi target sukses tanpa mapping dan test lookup read-only.
2. Buat lookup mapping opsional tanpa mutasi; pertahankan method resolver existing bagi konsumen lain.
3. Ubah call site persist/monitoring agar local branch nullable.
4. Tegakkan ulang guard BLSS, status PO, dan location sebelum write ESB.
5. Jalankan regresi QC, Vendor Compliance, idempotency, monitoring, dan Back Office.
6. Uji staging memakai PO BLSS non-production yang belum dimapping, lalu observasi sebelum production.

## 8. Pertanyaan terbuka

- Tidak ada pertanyaan bisnis yang terbuka: scope BLSS dan Receiving terpusat sudah dikonfirmasi.
- Pada implementasi, nama method lookup read-only mengikuti konvensi service setelah sibling method ditinjau; keputusan ini tidak mengubah behavior bisnis.
