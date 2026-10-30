---
document_id: INVENTORY-RECEIVING-CENTRAL-BLSS-PROMPT
title: Prompt Implementasi — Receiving Terpusat BLSS
status: in-review
version: 1.0
owner: Inventory
technical_owner: Information Technology
created_at: 2026-10-30
updated_at: 2026-10-30
target_release: TBD
related_documents:
  - docs/products/inventory/receiving-simplification/prd.md
  - docs/products/inventory/README.md
supersedes: null
---

# Prompt Implementasi — Receiving Terpusat BLSS

Salin bagian instruksi berikut ke agent implementasi setelah rencana disetujui. Prompt ini bukan perintah untuk melakukan implementasi pada tahap penulisan dokumentasi.

---

Implementasikan remediasi Receiving Inventory Pusat di repository Help Bloomery berdasarkan `docs/products/inventory/receiving-simplification/prd.md` §22 dan `implementation-plan.md` pada folder yang sama. PRD adalah sumber requirement canonical. Hasil yang diminta adalah PR implementasi siap review, bukan deployment.

## A. Konteks bisnis dan batas scope

Inventory Pusat memproses seluruh PO BLSS yang Authorized/Receiving berdasarkan permission `access employee app goods receipt`. Branch ESB PO tidak wajib dimapping ke branch lokal. Jangan menambah SPN/company lain. Jangan mengganti cabang/lokasi ESB PO dengan cabang user/pusat. Mapping aktif bila tersedia tetap boleh mengisi local_branch_id; jika tidak tersedia simpan null. Jangan membuat atau mengaktifkan mapping secara otomatis. Jangan memperluas permission Back Office atau modul lain.

## B. Persiapan wajib

1. Baca AGENTS.md, `.claude/skills/feature-delivery/SKILL.md`, skill Laravel/Pest yang relevan, README Inventory, PRD §22, plan, dan standar dokumentasi/remediasi.
2. Pastikan working tree bersih. Fetch main dan buat branch fix dari main terbaru. Bila docs rencana belum merge, tentukan basis yang mempertahankan rencana dan jelaskan di PR; jangan reset/hapus pekerjaan lain.
3. Gunakan Boost search-docs sebelum perubahan kode bila tersedia; bila tidak tersedia, nyatakan keterbatasannya dan ikuti pola kode serta dokumentasi resmi yang relevan.
4. Audit file yang dipetakan di README. Telusuri tambahan hanya untuk dependency konkret. Jangan membaca/menampilkan credential atau payload production.

## C. Audit dan implementasi inti

- `app/Filament/Casual/Pages/GoodsReceiptPage.php`: audit selectPurchaseOrder, submit, persist dan payload. Reproduksi test guard mapping terlebih dahulu.
- Hapus prasyarat mapping pada submit tanpa menghapus validasi PO, company, lokasi, qty, QC atau permission.
- Ubah parameter persist `BranchEsbCode` menjadi nullable sesuai convention; seluruh dereference mapping harus aman. local_branch_id null bila tidak ada mapping; company, esb_branch_id, branch_name, PO, supplier dan lokasi tetap dari konteks ESB yang diverifikasi.
- Monitoring berada di `app/Services/Monitoring/EsbTransactionRecorder.php`; context branch_id dapat null tanpa menghilangkan referensi receipt, PO dan idempotency key. Audit schema/konsumen terkait sebelum mengubah kontrak service bersama.
- Guard company `BLSS` pada submit juga wajib; jangan percaya public property Livewire hanya karena selectPurchaseOrder sudah memvalidasinya.
- Ambil ulang status dan konteks PO sebelum write. Jika branch/konteks berubah, minta reload dan jangan create. Lokasi harus milik branch PO yang tervalidasi; jangan hanya percaya locationId dari browser.
- `EsbBranchMappingResolver` tetap lookup kompatibel untuk konsumen lain. Jangan mengubah resolver menjadi bypass global atau menangkap semua exception sebagai mapping kosong.
- Tidak ada migration yang direncanakan: local_branch_id sudah nullable. Bila schema aktual berbeda, laporkan sebelum membuat perubahan schema.

## D. Dependency dan kompatibilitas

Audit GoodsReceipt, InboundGoodsReceiptQcService, Vendor Compliance incident, notification Purchasing, upload cleanup, ESB service dan monitoring. Pertahankan aturan Accepted/Hold/Rejected, batch/ED, bukti foto, demerit, status dan payload. Create GR hanya untuk Accepted. Jangan write retry otomatis pada failed/unknown. Pertahankan submission-key idempotency dan jangan membuat incident/receipt/GR ganda.

GoodsReceiptPolicy dan GoodsReceiptResource mempunyai scope viewer terpisah. Jangan otomatis memberikan `view goods receipts` atau all-branch kepada pemegang Receiving. Viewer all-branch harus bisa melihat receipt null; viewer terbatas tetap mengikuti scope existing. Tampilkan branch_name ESB tanpa mewajibkan relasi lokal. Jika ada kebutuhan tambahan untuk seluruh riwayat Inventory Pusat, laporkan terpisah.

## E. Test yang wajib dibuktikan

Gunakan Pest dan pola test existing; seluruh transaksi ESB di-fake/mock, tanpa API write nyata.

1. User Receiving tanpa akses branch dan tanpa mapping berhasil menerima PO BLSS.
2. PO BLSS lain dengan branch berbeda juga berhasil; tidak tergantung branch_id user.
3. Mapping aktif diisi seperti sebelumnya; mapping nonaktif/beda company tidak menghalangi dan tidak dibuat/diaktifkan.
4. Tanpa permission Receiving, termasuk user all-branch, tidak boleh akses/submit.
5. Company non-BLSS ditolak pada select dan manipulasi langsung submit; create tidak dipanggil.
6. PO tidak receivable, branch berubah saat submit, lokasi invalid, qty melebihi outstanding, QC/dokumen tidak valid tetap ditolak sebelum write.
7. Full Accepted, partial, Hold-only dan Rejected-only tanpa mapping mempertahankan status dan hasil QC; no Accepted berarti no create; incident dan notifikasi sesuai aturan.
8. Submit key berulang tidak menggandakan receipt/incident/GR. Failed/unknown tetap dapat direkonsiliasi, tanpa retry buta.
9. Monitoring transaksi dengan local branch null tetap menyimpan konteks receipt/PO/company, termasuk jalur gagal.
10. Back Office list/detail: viewer all-branch dapat membaca receipt null, viewer terbatas tetap dibatasi; Receiving saja tidak memberi akses Back Office.
11. Regresi foto/cleanup storage, ED/batch, payload ESB dan data historis mapped/null tetap lulus.

Ubah test `receiving explains an unmapped purchase order branch instead of returning forbidden` menjadi test sukses yang sesuai requirement; jangan sekadar menghapus cakupannya. Audit dan sesuaikan assertion lama lain yang mengharuskan mapping. Tambahkan test sesuai AC-1 sampai AC-10 PRD, bukan test yang hanya meniru implementasi.

## F. Verifikasi dan delivery

Jalankan perintah quality gate pada implementation-plan §5. Tambahkan regresi yang relevan bila file bersama disentuh. Pint hanya untuk perubahan PHP. Jangan menjalankan full Feature suite dalam satu proses besar. Perbaiki kegagalan terkait dan laporkan test yang tidak dapat dijalankan dengan alasan faktual.

Perbarui README Inventory: BLSS saja, seluruh cabang melalui permission Receiving, mapping lokal opsional; perbarui Peta kode bila dependency baru ditemukan. Selaraskan PRD/status sesuai bukti dan catat bagian historis yang digantikan §22. Buat laporan `reports/implementation-report.md` berisi phase, commit/PR, keputusan, deviasi, test aktual, UAT tertunda, rollout dan rollback. Hapus prompt implementasi setelah selesai menurut standar dokumentasi.

Commit dan push branch, buat PR Bahasa Indonesia memakai `.github/pull_request_template.md`. Isi perubahan, alasan, risiko, migration (tidak ada bila terbukti), queue/scheduler, integrasi, verifikasi dan UAT staging. Jangan mengklaim UAT kamera/perangkat/production sudah lulus tanpa bukti. Jangan merge, approve deployment, mengubah database production atau mengirim GR nyata tanpa izin tersendiri.

Jawab dalam Bahasa Indonesia: perubahan inti, test aktual, link PR, risiko/batas akses Back Office, dan pekerjaan UAT yang masih diperlukan.
