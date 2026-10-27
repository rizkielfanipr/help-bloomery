# Kitchen

Jadwal produksi kitchen berbasis katalog BOM Assembly. Kondisi per 27 Oktober 2026.

## Lokasi

| Tempat | Menu | Permission |
|---|---|---|
| Back Office → Kitchen | Jadwal Produksi | `view kitchen production schedules`; branch lain: `view all branch kitchen production schedules`; aksi: `create`, `update`, `cancel kitchen production schedules` |

## Jadwal Produksi

- Kalender bulanan (agenda di mobile) berisi jadwal per branch dan tanggal. Filter dan rentang tanggal diterapkan di query.
- **Buat/Edit jadwal**: pilih branch dan tanggal, lalu pilih beberapa **BOM Assembly** dari katalog lokal R&D. Picker berada di langkah dialog yang sama, bukan modal bertumpuk. Urutan item bisa diatur dan item bisa dihapus.
- Hanya BOM Assembly (`bom_type_id = 1`) yang lolos `ProductionBomEligibilityService` yang dapat dipilih; request palsu ditolak di server. ESB tidak dipanggil.
- **Detail** menampilkan item jadwal dari snapshot ringan. **Batalkan** mengubah status menjadi `cancelled` tanpa menghapus item atau snapshot.
- Status: `scheduled`, `cancelled`.
- Setiap aksi diotorisasi ulang di server (Policy + branch access), dan Kitchen tidak memiliki approval atau publish.

## Data

`KitchenProductionSchedule`, `KitchenProductionScheduleItem`; katalog dari `RndBomCatalog`. Lihat [Data Model](../../architecture/data-model.md#kitchen).

## Dokumen terkait

| Dokumen | Status |
|---|---|
| [Jadwal Produksi](production-schedule/README.md) dan [PRD](production-schedule/prd.md) | Implemented |

## Kandidat fitur

| Kandidat | Status |
|---|---|
| Production Order ESB | Kandidat fitur; **belum memiliki PRD approved**. PRD akan dibuat di branch dan PR terpisah. |
