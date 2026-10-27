# Status Pelaksanaan Code Remediation

## 1. Tujuan dokumen

Dokumen ini menjadi checkpoint untuk melanjutkan program perbaikan kode tanpa mengulang audit dari awal. Gunakan bersama:

- `docs/code-remediation-prd.md` sebagai requirement utama;
- `docs/esb-integration-consolidation-roadmap.md` untuk integrasi ESB;
- `docs/application-stability-scalability-prd.md` untuk operasional dan infrastruktur;
- `docs/ui-consistency-prd.md` untuk standar UI;
- laporan Phase 1–3 sebagai bukti historis implementasi.

Status di bawah merupakan hasil audit repository pada **20 Oktober 2026**. Kode aktual dan automated test tetap menjadi sumber bukti utama apabila dokumen berbeda dengan implementasi.

## 2. Legenda status

| Status | Arti |
| --- | --- |
| ✅ Selesai | Sudah diimplementasikan dan mempunyai bukti validasi yang terdokumentasi |
| 🟡 Sebagian | Sebagian subproses sudah tersedia, tetapi belum memenuhi definition of done |
| 🔵 Berjalan | Sedang terdapat implementasi aktif di worktree atau phase yang sedang dikerjakan |
| ⬜ Belum dimulai | Belum ditemukan implementasi yang cukup |
| 🔁 Berkelanjutan | Harus terus dijalankan sebagai quality gate |
| ⏸ Ditunda | Disimpan sebagai opsi dan bukan prioritas saat ini |

## 3. Status proses dan subproses

| Tahap | Proses | Subproses | Status | Catatan atau langkah berikutnya |
| ---: | --- | --- | :---: | --- |
| 0 | Guardrail | Modular monolith dan preservation contract | ✅ | Tidak melakukan rewrite, microservices, atau perubahan workflow tanpa requirement terpisah |
| 0 | Guardrail | Isolasi perubahan per domain | 🟡 | Terapkan satu concern per perubahan dan pertahankan pekerjaan user di worktree |
| 1 | Baseline | Inventaris Page, Resource, Model, Service, Job, Policy, route, dan test | ✅ | Bukti tersedia pada laporan Phase 1 |
| 1 | Baseline | Identifikasi hotspot kode, Livewire, query, ESB, dan authorization | ✅ | Perlu refresh metrik setelah perubahan besar |
| 1 | Baseline | Sinkronisasi baseline dengan kondisi terbaru | 🟡 | Jumlah file dan fitur sudah bertambah sejak audit awal |
| 2 | Safety net | Blokir network eksternal dalam test | ✅ | Menggunakan `Http::preventStrayRequests()` dan HTTP fake |
| 2 | Safety net | Perbaikan test scaffold dan memory test | ✅ | Full suite terakhir yang terdokumentasi mencapai 931 test lulus |
| 2 | Safety net | Characterization test seluruh workflow kritis | 🟡 | Kuat pada ESB dan beberapa domain, belum merata |
| 2 | Safety net | Browser/smoke test halaman utama | 🟡 | Belum menjadi quality gate untuk semua modul |
| 3 | Authorization | Policy Stock Card, Item Journal, Receiving, dan BOM Adjustment | ✅ | Authorization server-side sudah tersedia pada domain prioritas |
| 3 | Authorization | Policy System Monitoring | 🔵 | File awal tersedia di worktree dan belum final |
| 3 | Authorization | Standardisasi branch scope seluruh Resource/Page | 🟡 | Perlu audit domain yang masih melakukan pemeriksaan sendiri |
| 3 | Authorization | Regression test akses lintas branch | 🟡 | Wajib untuk Daily Briefing, monitoring, approval, dan data sensitif |
| 4 | ESB Core | Mapping Branch lokal dan ESB | ✅ | Termasuk sinkronisasi dan timestamp terakhir berhasil |
| 4 | ESB Core | Shared `EsbCoreClient` multi-company | ✅ | Token, timeout, refresh `401`, context, dan parsing dipusatkan |
| 4 | ESB Core | Item Journal, Goods Receipt, Stock Movement, Company Product | ✅ | Sudah menggunakan transport bersama sesuai keluarga autentikasi |
| 4 | ESB Core | Ekstraksi Purchase Order, Master Product, dan BOM | ✅ | Compatibility facade lama sudah dihapus |
| 4 | ESB legacy | Audit dan pecah `EsbService` sales legacy | 🟡 | Masih ada service besar dan instansiasi langsung dari Filament Page |
| 4 | ESB legacy | Pisahkan Sales, Stock Information, dan Promotion | ⬜ | Gunakan client sesuai keluarga autentikasi, bukan memaksakan `EsbCoreClient` |
| 4 | ESB legacy | Dependency injection seluruh consumer | 🟡 | Shared Core selesai; consumer legacy masih tersisa |
| 5 | Mutation safety | Item Journal: submission key, hash, dan status `unknown` | ✅ | Duplicate submission dan hasil koneksi tidak pasti sudah ditangani |
| 5 | Mutation safety | Goods Receipt: submission key, hash, dan status `unknown` | ✅ | Tidak melakukan retry mutation otomatis |
| 5 | Mutation safety | Product mutation dan blocking status `unknown` | ✅ | Tidak dapat dikirim ulang sebelum rekonsiliasi |
| 5 | Mutation safety | Reconciliation update BOM | 🟡 | BOM Adjustment sudah mempunyai `needs_reconciliation` |
| 5 | Mutation safety | Attempt record create BOM | ⬜ | Harus dibuat sebelum ESB mengembalikan BOM ID |
| 5 | Mutation safety | Duplicate guard dan reconciliation create/update BOM | ⬜ | Prioritas bisnis tertinggi berikutnya |
| 5 | Mutation safety | Audit penutup seluruh tombol Push to ESB | 🟡 | Ulangi setelah Phase D4 BOM selesai |
| 6 | Error handling | Configuration, authentication, validation, connection/timeout | 🟡 | Sudah ditangani per service tetapi belum memakai taxonomy seragam |
| 6 | Error handling | Rate limit, server error, dan unknown response | ⬜ | Buat exception terstruktur dan mapping UI/log |
| 6 | Error handling | Correlation ID dan safe context | ⬜ | Dibutuhkan recorder System Monitoring |
| 6 | Error handling | Sanitasi payload sensitif | 🔵 | `MonitoringSanitizer` awal tersedia dan perlu test allowlist per domain |
| 7 | Monitoring | PRD dan prompt implementasi | ✅ | `system-monitoring-prd.md` dan prompt implementasi tersedia |
| 7 | Monitoring | Enum, model, migration, policy, config, factory | 🔵 | Implementasi awal ada di worktree dan belum dinyatakan selesai |
| 7 | Monitoring | Automated test fondasi | ⬜ | Test monitoring belum ditemukan saat audit |
| 7 | Monitoring | Transaction recorder yang failure-safe | 🔵 | Harus dipastikan tidak menggagalkan transaksi bisnis |
| 7 | Monitoring | Pilot Item Journal | ⬜ | Instrumentasi consumer pertama setelah fondasi lulus test |
| 7 | Monitoring | Goods Receipt, Product, dan BOM | ⬜ | Dilakukan bertahap setelah pilot stabil |
| 7 | Monitoring | ESB Transaction Monitor | ⬜ | UI dibuat setelah recorder menghasilkan data terpercaya |
| 7 | Monitoring | Overview dan Service Health | ⬜ | Hanya membaca snapshot lokal, tanpa HTTP eksternal saat render |
| 7 | Monitoring | Queue/Scheduler dan Error Monitor | ⬜ | Menunggu heartbeat dan taxonomy error |
| 7 | Monitoring | Deployment Monitor dan Incident Center | ⬜ | Membutuhkan ingestion, health state, alert, dan authorization |
| 7 | Monitoring | Retention dan daily aggregate | ⬜ | Wajib sebelum aktivasi production agar data tidak tumbuh tanpa batas |
| 8 | Queue | Database queue dan queued notification | 🟡 | Driver tersedia; coverage Job masih terbatas |
| 8 | Queue | Sinkronisasi katalog R&D | ✅ | Job terkait sudah tersedia |
| 8 | Queue | Refresh Stock Movement dan Master Branch | ⬜ | Kandidat operasi read-only pertama untuk dipindahkan |
| 8 | Queue | Reconciliation transaksi `unknown` | ⬜ | Dikerjakan setelah mutation safety lengkap |
| 8 | Queue | Upload attachment dan export besar | ⬜ | Harus mempunyai status, progress, failure, dan expiry |
| 8 | Queue | Timeout, backoff, uniqueness, `failed()` | 🟡 | Standardisasikan pada seluruh Job |
| 8 | Queue | Worker production dan restart saat deploy | 🟡 | Harus diverifikasi pada VPS dan setelah setiap deployment |
| 9 | Scheduler | `withoutOverlapping()` pada task berkala | ✅ | Sudah digunakan pada scheduler utama |
| 9 | Scheduler | Scheduler heartbeat dan late alert | ⬜ | Bagian dari System Monitoring |
| 9 | Scheduler | Audit idempotency seluruh command | 🟡 | Belum seluruh task mempunyai bukti retry-safe |
| 9 | Scheduler | `onOneServer()` | ⏸ | Digunakan jika aplikasi berjalan pada lebih dari satu scheduler node |
| 10 | Database | Audit N+1, query duplikat, dan full scan | 🟡 | Perlu pengukuran ulang pada database yang dapat diakses |
| 10 | Database | Hilangkan query dalam Blade | 🟡 | Masih ditemukan pada Service Request, Trip, dan BOM Adjustment |
| 10 | Database | Pagination, column selection, eager loading | 🟡 | Belum merata pada seluruh halaman besar |
| 10 | Database | Index berdasarkan `EXPLAIN` | 🟡 | Jangan menambah indeks hanya berdasarkan dugaan |
| 10 | Database | Unique constraint invariant bisnis | 🟡 | Sudah ada pada submission tertentu, perlu audit domain lain |
| 10 | Database | Retention log dan monitoring | ⬜ | Dibutuhkan sebelum monitoring production |
| 11 | Livewire | Audit public property dan dataset besar | 🟡 | Hotspot telah ditemukan, baseline payload terbaru belum ada |
| 11 | Livewire | Stock Card sebagai pilot | 🟡 | Workflow sudah banyak diperbaiki, pengukuran payload belum lengkap |
| 11 | Livewire | Server-side search, pagination, lazy modal | 🟡 | Sudah ada pada sebagian picker/modal |
| 11 | Livewire | Draft persisten, stable key, locked ID | 🟡 | Perlu audit menyeluruh pada halaman besar |
| 11 | Livewire | Snapshot dan DOM measurement | ⬜ | Tetapkan baseline sebelum/sesudah optimasi |
| 12 | Modularisasi | `ViewProjectProductPage` | ⬜ | Hotspot terbesar; pecah per use case setelah mutation Product/BOM aman |
| 12 | Modularisasi | `ViewProject`, Bulk Promotion, Sales Information | ⬜ | Jangan refactor bersamaan; satu domain per perubahan |
| 12 | Modularisasi | `ViewRndInternalMemo` | ⬜ | Ekstrak Action/Service berdasarkan use case, bukan jumlah baris saja |
| 12 | Modularisasi | Actions dan domain service bebas Filament | 🟡 | Sudah dimulai pada R&D dan ESB, belum merata |
| 12 | Modularisasi | Namespace/folder per domain | 🟡 | Lakukan setelah responsibility boundary stabil |
| 13 | UI | Standar UI dan komponen reusable | 🟡 | PRD tersedia; adopsi belum konsisten seluruh modul |
| 13 | UI | Loading, empty, error, disabled, responsive, dark mode | 🟡 | Perlu browser test dan accessibility review |
| 14 | File lifecycle | Validasi upload dan authorization file | 🟡 | Audit ulang seluruh attachment dan export |
| 14 | File lifecycle | Private storage, temporary URL, orphan cleanup | 🟡 | Belum konsisten lintas domain |
| 14 | File lifecycle | Queued export dan expiry | ⬜ | Dikerjakan bersama standardisasi Job |
| 15 | Cleanup | Hapus compatibility ESB Core lama | ✅ | Reference audit sudah dilakukan |
| 15 | Cleanup | Bersihkan raw login/token/base URL legacy | 🟡 | Menunggu migrasi ESB sales legacy |
| 15 | Cleanup | Architecture test HTTP di Page/Blade | ⬜ | Tambahkan setelah consumer sah selesai dimigrasikan |
| 15 | Cleanup | Architecture test dependency ESB | ⬜ | Mencegah pola direct HTTP/direct construction kembali |
| 15 | Cleanup | Sinkronisasi dokumentasi status | 🔵 | Dokumen ini menjadi checkpoint baru; dokumen lama perlu diarahkan ke sini |
| 16 | Deployment | Test, build, migrate, optimize | 🟡 | Workflow tersedia tetapi perlu health gate yang konsisten |
| 16 | Deployment | Artifact frontend dan clean working tree | 🟡 | Generated build pernah mengotori worktree VPS |
| 16 | Deployment | Restart queue worker | 🟡 | Wajib setelah perubahan Job/queue |
| 16 | Deployment | Backup dan post-deploy health check | ⬜ | Hubungkan setelah Service Health tersedia |
| 16 | Deployment | Rollback repeatable | 🟡 | Ada catatan per fitur, belum menjadi prosedur universal |
| 17 | Operasional | Uptime, 5xx, p50/p95 | ⬜ | Menunggu observability production |
| 17 | Operasional | Queue backlog, failed jobs, scheduler, backup freshness | ⬜ | Masuk scope System Monitoring |
| 17 | Operasional | Incident workflow dan restore drill | ⬜ | Belum mempunyai bukti pengujian berkala |
| 18 | API readiness | Action/Service bebas Livewire | 🟡 | Sebagian domain sudah siap |
| 18 | API readiness | Versioned API dan frontend terpisah | ⏸ | Bukan prioritas remediation saat ini |
| 19 | Penutupan | Workflow kritis mempunyai test dan observability | 🟡 | Belum merata |
| 19 | Penutupan | Tidak ada HTTP mentah di Page/Blade | 🟡 | ESB legacy masih tersisa |
| 19 | Penutupan | Seluruh mutation retry-safe | 🟡 | BOM belum lengkap |
| 19 | Penutupan | Query, payload, queue, dan deployment terukur | 🟡 | Menunggu monitoring dan baseline terbaru |
| 19 | Penutupan | Dokumentasi sesuai implementasi | 🔵 | Update dokumen ini setiap milestone |

## 4. Ringkasan progres indikatif

Persentase berikut merupakan indikator perencanaan, bukan metrik otomatis.

| Kelompok | Perkiraan |
| --- | ---: |
| Baseline dan safety net | 90% |
| Authorization dan branch isolation | 65% |
| Konsolidasi ESB Core | 85% |
| Mutation safety dan reconciliation | 75% |
| Legacy ESB cleanup | 30% |
| Queue dan background processing | 30% |
| System Monitoring | 15% |
| Database dan query optimization | 35% |
| Livewire optimization | 30% |
| Modularisasi halaman besar | 20% |
| UI consistency | 50% |
| Deployment dan operasional | 45% |
| API readiness | Ditunda |
| Keseluruhan remediation | sekitar 50–55% |

## 5. Jalur kritis menuju selesai

```text
Amankan dan validasi worktree System Monitoring
    → Selesaikan mutation safety create/update BOM
    → Standardisasi taxonomy error ESB
    → Tambahkan test fondasi Monitoring
    → Instrumentasikan Item Journal sebagai pilot
    → Instrumentasikan Goods Receipt, Product, dan BOM
    → Bangun ESB Transaction Monitor
    → Pindahkan proses read-only berat ke queue
    → Bangun Service Health dan Queue/Scheduler Monitor
    → Migrasikan ESB sales legacy
    → Hilangkan query database dari Blade
    → Pecah halaman R&D/Sales berdasarkan use case
    → Optimasi Livewire berdasarkan hasil pengukuran
    → Tambahkan architecture enforcement dan cleanup
    → Aktifkan deployment health gate dan incident workflow
    → Tutup remediation setelah seluruh definition of done terpenuhi
```

## 6. Prioritas saat melanjutkan

1. Jangan memulai phase baru sebelum meninjau `git status`, perubahan aktif, dan test terkait.
2. Tuntaskan serta uji fondasi System Monitoring yang sedang berada di worktree.
3. Selesaikan Phase D4: idempotency dan reconciliation mutation BOM.
4. Buat taxonomy error ESB sebelum menghubungkan seluruh consumer ke monitoring.
5. Gunakan Item Journal sebagai pilot recorder monitoring.
6. Migrasikan consumer berikutnya satu per satu: Goods Receipt, Product, lalu BOM.
7. Pindahkan proses read-only berat ke queue sebelum mutation eksternal.
8. Setelah fondasi stabil, lanjutkan ESB legacy, query Blade, modularisasi, dan Livewire.

## 7. Aturan pembaruan dokumen

Setelah satu milestone selesai:

1. ubah status hanya jika ada bukti kode dan test;
2. tambahkan tanggal review terbaru;
3. catat test terfokus dan full suite pada laporan phase terkait;
4. perbarui persentase hanya sebagai indikator;
5. jangan menandai selesai apabila migration, worker, scheduler, permission, atau konfigurasi production masih belum diverifikasi;
6. jika dokumen berbeda dengan kode, audit kode dan test terlebih dahulu lalu perbarui dokumen ini.

