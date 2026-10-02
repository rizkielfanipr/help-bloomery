# Phase 0 — Audit dan Contract Freeze: Store Sales Order

Audit dilakukan 2 Oktober 2026 sebelum perubahan kode, sesuai `docs/store-sales-order-prd.md`.

## 1. Kondisi repository aktual vs PRD

Fitur ini sepenuhnya baru (tidak ada implementasi existing). Namun repository sudah punya seluruh fondasi yang PRD asumsikan:

- **`EsbCoreClient`** (`app/Services/EsbCoreClient.php`) sudah menyediakan `request($companyCode, 'get', $path, $data)` + `successfulResult()`, dengan login/token-cache/401-refresh/connection-error-handling lengkap, menerima Company Code apa pun yang terdaftar di `config('esb.core.companies')`. Tidak perlu perubahan pada client ini.
- **Mapping branch utama** baru saja disatukan (commit `8b982a1`, "Unify memo and stock card branch mapping"): `Branch::activeStockCardEsbCode()` sekarang menjadi **satu-satunya** sumber "mapping ESB utama" yang dipakai bersama oleh Stock Card dan R&D Internal Memo. PRD §8.2 eksplisit meminta "mengikuti resolver branch yang berlaku di repository saat implementasi" — maka Store Sales Order memakai `Branch::activeStockCardEsbCode()` yang sama, BUKAN membuat field mapping baru. Resolver baru (`ResolveSalesOrderBranchMappingAction`) dibuat khusus di namespace `StoreSalesOrder` untuk validasi kelengkapan (Company Code, Branch Code, numeric Branch ID, credential ESB Core) tanpa mengubah file R&D Memo/Stock Card manapun (sesuai larangan "Jangan memindahkan modul lain").
- **Pola Operational module**: `docs/customer-complaints-prd.md` yang sudah diimplementasikan penuh (tile launcher permission-tunggal, back office `Operational → X`, attachment ERP Request, Policy branch-scope, Activity log) menjadi referensi langsung — struktur Store Sales Order dibuat semirip mungkin.
- **Attachment ERP Request**: pola upload (`WithFileUploads`, disk `b2`, validasi inline) dan pola download terautorisasi (`{Feature}AttachmentController` meniru `CustomerComplaintAttachmentController`, BUKAN ERP Request asli yang terbukti TIDAK terautorisasi) tetap dipakai sebagaimana di Customer Complaints.
- **Permission config**: `config/permissions.php` sudah punya group `Operational` (SOP Kategori, SOP Store, Customer Complaints) — Store Sales Order ditambahkan sebagai entri baru di situ.
- **Role production**: `STORE_STAFF` (submitter) dan `SUPERVISOR_STORE` (reviewer) sudah dipakai untuk permission Customer Complaints yang punya aktor setara (Admin Store, Operational Reviewer/Manager) — dipakai ulang dengan pola yang sama (Open Decision #2).

## 2. Verifikasi live endpoint `GET /sales/product-sales`

Dicoba langsung lewat `EsbCoreClient` dengan dua Company Code yang mempunyai credential ESB Core nyata di environment ini (BLSS dan BLO6):

```
GET https://services.esb.co.id/core/sales/product-sales?page=1&limit=5&branchID=6
→ HTTP 403 {"code":"EC03100002","message":"You did not have access to this resource"}

GET https://services.esb.co.id/core/sales/product-sales?page=1&limit=5&branchID=2 (BLO6)
→ HTTP 403 {"code":"EC03100002","message":"You did not have access to this resource"}
```

Dicoba juga di host legacy (`corev1`, static token) sebagai diagnostic banding:

```
GET https://core-api.esb.co.id/corev1/sales/product-sales?...
→ HTTP 404 "Page not found."
```

**Kesimpulan**: endpoint `/sales/product-sales` di host ESB Core (`services.esb.co.id/core`) memang ada (403, bukan 404 — route dikenali, akses ditolak), sesuai path yang disebut PRD §9.1. Tapi kredensial ESB Core yang terkonfigurasi untuk BLSS dan BLO6 saat ini **tidak mempunyai scope/izin** untuk endpoint ini di sisi ESB. Ini murni masalah konfigurasi akun di ESB (di luar kendali kode), bukan path/kontrak yang salah.

**Dampak terhadap implementasi**: field response (`productSalesNum`, `productSalesDate`, `requiredDate`, `branchID`, `branchName`, `customerID`, `customerName`, `customerAddress`, `productSalesTotal`, `currencySign`, `statusID`, `statusName`, `createdBy`, `linkPurchaseNum`, `additionalInfo`) **belum bisa diverifikasi terhadap response asli** — ini memakai spesifikasi PRD §9.2 sebagai kontrak kerja, didesain defensif (setiap field dibaca dengan fallback null/kosong, tidak ada asumsi field pasti ada) sehingga ketika akses ESB sudah diberikan, verifikasi ulang tidak memerlukan desain ulang besar. Ini bukan blocker yang menyentuh data/keamanan/kontrak yang tidak dapat dibalik (operasi read-only, tidak ada mutation) — pekerjaan tetap dilanjutkan dengan asumsi terdokumentasi ini, sesuai instruksi "lanjutkan bagian yang dapat dibuktikan dan catat asumsi konservatif."

**Pagination/exact-match**: tidak dapat dibuktikan langsung (lihat di atas). Implementasi tetap melakukan pencocokan ulang exact terhadap `productSalesNum` dan `branchID` di sisi aplikasi terhadap SELURUH baris yang dikembalikan (bukan mempercayai baris pertama), persis seperti diminta PRD §9.1, sehingga tetap aman meski ESB mengembalikan lebih dari satu baris untuk satu `page`.

## 3. Audit pola yang dipakai ulang

- **Launcher tile**: `LauncherPage::tiles()` — tile baru ditambahkan persis seperti tile "Complain" (satu permission `create store sales orders` menggerbangi tile + akses panel Casual + submit, mengikuti pola grup config `Akses Employee App`).
- **Custom sidebar Helpdesk**: `resources/views/vendor/filament-panels/components/layout/index.blade.php` — entri baru ditambahkan ke grup `operational` yang sudah ada, dengan kondisi `$initialOpen` diperluas, persis seperti Customer Complaints.
- **Attachment**: disk `b2`, `WithFileUploads`, validasi `mimes:jpg,jpeg,png,webp,pdf|max:5120`, dan controller download khusus meniru `CustomerComplaintAttachmentController` (path tidak embed ID record, resolusi lewat pencarian JSON path yang benar-benar terdaftar, authorize via Policy).
- **Permission migration production**: pola `2026_09_21_082905_grant_technician_monthly_maintenance_permissions.php` / migration permission Customer Complaints — `Permission::firstOrCreate()` + `Role::givePermissionTo()`, `down()` kosong.

## 4. Open decisions (PRD §25) — keputusan konservatif

| # | Keputusan PRD | Keputusan diambil | Alasan |
|---|---|---|---|
| 1 | Format nomor "SL" vs `productSalesNum` | Diperlakukan sebagai string bebas, di-trim, tidak diasumsikan format tertentu (tidak ada regex pattern) | Tidak dapat diverifikasi (lihat §2); field dikirim apa adanya ke ESB sebagai parameter exact-match |
| 2 | Role awal create/review | `STORE_STAFF` (create), `SUPERVISOR_STORE` (review: view any/view/update/update status) | Sama persis dengan pemetaan Customer Complaints untuk aktor setara |
| 3 | Edit setelah `In Preparation`? | Diizinkan — `update store sales orders` tidak digerbangi status, mengikuti pola R&D Internal Memo ("Menu dapat ditambah dihapus kapan saja") | PRD tidak secara eksplisit melarang; conservative default is to allow edits by permission, not block silently |
| 4 | Dua kategori attachment? | Satu field attachment saja (seperti Customer Complaints) | PRD §10.4 tidak membedakan kategori secara eksplisit dalam field list |
| 5 | Required date kosong boleh dilengkapi lokal? | Tidak — `required_date` murni snapshot ESB, read-only, null jika ESB mengembalikan null | PRD §15.1 mencantumkannya sebagai snapshot field, bukan field operasional yang bisa diedit |
| 6 | Pilihan produk global vs master table? | Enum/config terpusat (`StoreSalesOrderProductType`), bukan tabel master | PRD §10.3 eksplisit: "Pilihan disimpan sebagai enum/config terpusat" |

Tidak ada keputusan di atas yang menyentuh data irreversible, keamanan, atau kontrak bisnis yang tidak dapat dibalik — seluruhnya aman dilanjutkan dengan asumsi konservatif di atas, didokumentasikan untuk review.

**Selesai jika** (kriteria PRD): kontrak request/response, mapping branch, permission awal, dan behavior error telah dibuktikan tanpa membuat data production. ✅ Terpenuhi sejauh yang bisa dibuktikan di environment ini; field response ESB tetap berstatus "spesifikasi PRD, belum terverifikasi live" sampai akses endpoint diberikan — dicatat secara eksplisit, bukan diasumsikan diam-diam.
