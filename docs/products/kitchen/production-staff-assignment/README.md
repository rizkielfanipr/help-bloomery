# Assignment Staff Produksi per Detail Production Order

| Atribut | Nilai |
|---|---|
| Domain | Kitchen |
| Status | draft — belum diimplementasikan |
| Owner | Kitchen |
| Technical owner | Information Technology |
| Modul terdampak | Kitchen → Jadwal Produksi; Master Employee sebagai sumber read-only |
| Target release | TBD |

## Ringkasan

Fitur ini merencanakan assignment lokal satu atau beberapa **Staff Produksi** pada setiap detail BOM/product di dalam Production Order ESB. Production Order tetap snapshot read-only; assignment disimpan di database Bloomery, dibatasi ke employee aktif pada Branch Kitchen yang sama, dan seluruh perubahannya diaudit.

## Dokumen

| Dokumen | Status | Keterangan |
|---|---|---|
| [PRD](prd.md) | draft | Sumber requirement canonical; keputusan terbuka berada di §26 |
| [Implementation plan](implementation-plan.md) | draft | Urutan phase, boundary teknis, migration, test, dan quality gate |
| [Implementation prompt](implementation-prompt.md) | draft | Instruksi lengkap untuk agent implementasi; tidak boleh dijalankan sebelum gate PRD ditutup |
| [Rollout](rollout.md) | draft | Deployment additive, permission, UAT, monitoring, dan rollback |

## Keputusan

| Keputusan | Status |
|---|---|
| Assignment disimpan lokal per detail Production Order dan tidak dikirim ke ESB | Disetujui |
| Nama UI canonical adalah **Staff Produksi** | Disetujui |
| Satu detail mendukung multiple Staff Produksi | Disetujui |
| Eligibility employee aktif dan satu Branch dengan order | Rekomendasi; menunggu konfirmasi OD-1 |
| Koreksi setelah detail selesai dengan konfirmasi dan audit | Rekomendasi; menunggu konfirmasi OD-2 |
| MVP hanya Back Office tanpa Employee App | Rekomendasi; menunggu konfirmasi OD-3 |

## Riwayat status

| Tanggal | Status | Catatan |
|---|---|---|
| 2026-11-04 | draft | PRD dan paket planning dibuat dari audit kalender, snapshot detail, sync identity, Employee, permission, serta UI existing |
