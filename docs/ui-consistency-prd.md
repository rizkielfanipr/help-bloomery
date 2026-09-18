# PRD — Standar UI dan Konsistensi Help Bloomery

## 1. Identitas dan status

| Field | Nilai |
| --- | --- |
| Baseline | 17 September 2026 |
| Status | Rancangan; standardisasi lintas modul belum dimulai |
| Tujuan | Pengembangan berikutnya mengikuti pola UI yang konsisten, sederhana, informatif, dan responsif |
| Referensi utama | R&D Project Workspace, Sales Report Scores, Sales Report Settings |
| Teknologi existing | Laravel 13, Filament 5, Livewire 4, Alpine.js 3, Tailwind CSS 4, Blade |
| Deliverable saat ini | PRD saja; tidak mengubah komponen, halaman, dependency, atau AGENTS.md |
| Strategi pelaksanaan | Dokumentasikan pola existing, ekstrak komponen bersama, adopsi bertahap |

Dokumen ini menjadi acuan pengembangan UI oleh developer dan AI. Instruksi user terbaru tetap diprioritaskan. Periksa kondisi repository sebelum implementasi; daftar kandidat komponen di sini bukan klaim bahwa semuanya sudah tersedia. Dokumen tidak memberi izin deployment atau perubahan perilaku bisnis.

## 2. Latar belakang

Halaman aplikasi dibuat secara bertahap, sehingga pola header, container, formulir, tombol, modal, dan informasi pengisian dapat berbeda antar modul. User sudah memilih konsep halaman project R&D dan Sales Report Scores sebagai arah visual: container berborder, aksen biru, ikon yang jelas, informasi ringkas, dan layout tanpa shadow berlebihan.

Panduan saja belum menjamin konsistensi. Elemen berulang perlu menggunakan komponen bersama agar perbaikan spacing, aksesibilitas, dan perilaku berlaku pada halaman yang memakai komponen tersebut.

## 3. Referensi existing dan batas penggunaannya

| Referensi repository | Pola yang diadopsi |
| --- | --- |
| [R&D Project Workspace](../resources/views/filament/helpdesk/rnd-projects/index.blade.php) | Header berikon, aksen biru, section informatif, filter, modal formulir |
| [R&D Project Detail](../resources/views/filament/helpdesk/rnd-projects/view.blade.php) | Pengelompokan informasi detail dan aksi terkait |
| [Sales Report Scores](../resources/views/filament/helpdesk/pages/sales-report-scores-page.blade.php) | Ringkasan angka, kartu cabang, filter, detail harian dalam modal |
| [Sales Report Settings](../resources/views/filament/helpdesk/pages/sales-report-settings-page.blade.php) | Form bersection, petunjuk lokal, informasi proses otomatis, footer simpan |
| [R&D Picker Modal](../resources/views/components/rnd/picker-modal.blade.php) | Shell modal existing yang diperiksa sebelum membuat shell baru |
| [Panduan Panel](panels.md) | Konteks panel dan navigasi; cocokkan dengan implementasi aktual |

Referensi diambil per pola, bukan disalin seluruh halaman. Sales Report Settings sudah menghapus banner pengantar besarnya atas permintaan user: halaman pengaturan tidak wajib memiliki header promosi atau ringkasan tambahan. Hindari heading ganda dari layout Filament dan header konten.

## 4. Tujuan dan ukuran keberhasilan

1. Halaman baru memakai standar container, warna, spacing, input, tombol, dan modal yang sama.
2. Pola yang benar-benar berulang memakai komponen existing atau komponen bersama.
3. Form dan detail dapat digunakan pada mobile tanpa kehilangan aksi atau informasi penting.
4. Tidak ada scroll horizontal pada keseluruhan halaman pada ukuran viewport yang didukung.
5. Loading, validasi, error, empty state, dan permission memiliki perilaku yang jelas.
6. Developer dan AI mempunyai acuan eksplisit sebelum menambah UI.
7. Adopsi UI tidak mengubah kalkulasi, status, approval, query scope, atau kontrak integrasi.

Ukuran keberhasilan berupa hasil pemeriksaan halaman dan alur pengguna. Jumlah komponen atau jumlah class yang dihapus bukan ukuran keberhasilan.

## 5. Scope

### In scope

- Standar visual dan pola interaksi yang mengikuti referensi existing.
- Kontrak kandidat komponen Blade bersama dan strategi pemakaiannya.
- Responsive layout, aksesibilitas dasar, copy formulir, dan state UI.
- Integrasi panduan ke workflow developer/AI pada phase implementasi.
- Adopsi bertahap pada back office dan aplikasi user.

### Out of scope

- Migrasi Vue, React, Astro, atau frontend framework lainnya.
- Refactoring domain bisnis, perubahan API, database bisnis, status, SLA, atau scoring.
- Penggantian struktur panel, route, sidebar, maupun nama menu secara massal.
- Penambahan dependency atau library ikon tanpa persetujuan.
- Redesain seluruh modul sekaligus atau perubahan layout PDF/export.
- Menganggap semua halaman harus memakai kartu dan modal yang sama.

## 6. Prinsip desain

- **Sederhana:** setiap section mempunyai fungsi; dekorasi tidak mendominasi informasi.
- **Informatif:** petunjuk ditempatkan dekat field atau aksi yang dijelaskan.
- **Konsisten:** gunakan pola existing sebelum membuat variasi baru.
- **Responsif:** pilih struktur berdasarkan sifat konten, bukan hanya menambahkan breakpoint.
- **Terukur:** warna, spacing, radius, dan ukuran elemen mengikuti token yang ditetapkan.
- **Aksesibel:** label, fokus, keyboard, dan teks status merupakan bagian desain.
- **Aman terhadap workflow:** perubahan tampilan menjaga otorisasi dan perilaku existing.

## 7. Fondasi visual

Token berikut merupakan baseline dari halaman referensi. Implementasi boleh memakai utility Tailwind atau token CSS existing; tidak perlu membuat konfigurasi baru apabila utility sudah memadai.

| Elemen | Baseline |
| --- | --- |
| Background container | `bg-white`; dark variant mengikuti panel existing |
| Border | `border border-gray-200`; dark `border-gray-700` |
| Radius container utama | `rounded-2xl` |
| Radius section kecil/kartu ringkas | `rounded-xl` |
| Radius input/tombol | `rounded-lg` |
| Jarak section halaman | `space-y-6` atau `gap-6` |
| Padding section utama | `p-5 sm:p-6` |
| Padding section kecil | `p-4` |
| Aksen utama | Blue 600; hover Blue 700 |
| Background informasi | Blue 50; teks Blue 700 |
| Teks utama | Gray 900/950 |
| Teks sekunder | Gray 500/600; pastikan kontras terhadap background |
| Judul halaman konten | `text-2xl font-bold` |
| Judul section | `text-sm`/`text-lg` sesuai hierarki, `font-semibold`/`font-bold` |
| Isi formulir | `text-sm` |
| Petunjuk | `text-xs leading-5` atau `leading-6` |
| Ikon aksi | 16–20 px |
| Ikon header | 24 px dalam container 40–48 px |
| Shadow | Tidak digunakan pada section dan kartu biasa |

Backdrop gelap pada modal diperbolehkan. Panel yang mendukung dark mode tetap mendapat dark variant; halaman yang ditetapkan light-only tidak diubah menjadi dark mode oleh standardisasi ini.

### Warna status

| Makna | Warna |
| --- | --- |
| Selesai/berhasil | Green/Emerald |
| Menunggu/perlu perhatian | Amber |
| Ditolak/error/destruktif | Red |
| Draft/tidak berlaku/informasi netral | Gray |
| Aksi utama/informasi produk | Blue |

Warna tidak menjadi satu-satunya penanda status. Sertakan teks status dari enum atau sumber terpusat. Jangan membuat label status baru hanya untuk kepentingan styling.

## 8. Pola halaman

### 8.1 Index/workspace

Urutan: identitas halaman bila diperlukan → filter → ringkasan bila relevan → daftar data → pagination.

- Header berisi ikon, judul, deskripsi maksimal beberapa kalimat singkat, serta aksi utama bila tersedia.
- Filter mempunyai label yang terlihat dan layout bertumpuk di mobile.
- Ringkasan menunjukkan informasi yang membantu keputusan; hindari metrik dekoratif.
- Daftar memakai kartu untuk item yang berdiri sendiri; tabel untuk perbandingan kolom yang nyata.
- Aksi ditempatkan dekat item terkait dan tidak disembunyikan hanya karena layar kecil.

### 8.2 Detail

Urutan: identitas record → status/informasi utama → section domain → aksi terkait.

- Nama panjang boleh wrap; kode dan label tetap dapat dibaca.
- Kelompokkan informasi menurut tugas pengguna, bukan menurut nama tabel database.
- Edit sederhana dapat memakai modal; workflow panjang tetap boleh memakai halaman tersendiri.
- Tautan kembali, status, dan aksi tidak bersaing dengan judul utama.

### 8.3 Settings

Urutan: judul aturan → aktivasi bila relevan → field per kelompok → informasi konsekuensi → simpan.

- Header besar dan kartu statistik tidak wajib.
- Gunakan informasi proses otomatis untuk menjelaskan dampak aturan.
- Bedakan pengaturan tersimpan dari preview yang belum disimpan.
- Jangan menambahkan flow konfirmasi yang tidak dibutuhkan oleh aturan existing.

### 8.4 Form

- Label memakai kapital setiap kata dengan istilah/kode resmi tetap dipertahankan.
- Field wajib ditandai dan tetap mempunyai validasi server.
- Petunjuk umum section menggunakan heading **Informasi Pengisian**; proses otomatis memakai **Informasi Proses Otomatis**.
- Petunjuk singkat khusus field ditempatkan tepat di bawah field.
- Error ditempatkan dekat field dan tidak hanya melalui toast.
- Layout default satu kolom; dua kolom hanya jika field masih nyaman dibaca.
- Attachment mengikuti pola ERP Request yang ada; audit komponen aktual sebelum menyalin. Jenis file, batas ukuran/jumlah, preview, hapus, dan upload state harus terlihat.
- Input yang tidak tersedia karena pilihan induk belum diisi tampil disabled/read-only sesuai alur existing, dengan alasan yang jelas.

## 9. Tombol, ikon, filter, dan pagination

- Satu aksi utama per area; simpan/create memakai biru, aksi sekunder memakai border/netral.
- Aksi destruktif memakai merah dengan label jelas.
- Tombol memiliki loading dan perlindungan pengiriman berulang jika melakukan mutation.
- Icon-only button wajib mempunyai accessible name, misalnya `aria-label`.
- Gunakan Heroicons melalui Blade component untuk pola halaman ini; jangan menulis SVG/path manual yang menduplikasi ikon.
- Sistem ikon launcher/sidebar existing tidak diganti massal; ikuti konteks komponen navigasi tersebut.
- Search, sync, dan reset dikelompokkan dengan filter dan tetap mudah digunakan di mobile.
- Pagination ringkas memakai arrow beserta informasi halaman/rentang. Boundary arrow disabled. Pagination tidak menghilangkan filter aktif.

## 10. Modal

### Anatomi

1. Backdrop.
2. Header: judul, deskripsi opsional, tombol tutup.
3. Body: konten atau formulir, scroll internal bila tinggi konten melebihi viewport.
4. Footer: aksi terkait; simpan/batal untuk form, tutup untuk detail.

### Perilaku wajib

- Memiliki `role="dialog"`, `aria-modal`, dan nama dialog yang dapat dibaca screen reader.
- Mendukung Escape, tombol tutup, dan backdrop sesuai pola halaman referensi.
- Fokus masuk ke dialog, tertahan dalam dialog, dan kembali ke trigger saat ditutup.
- Scroll halaman belakang dikunci ketika dialog terbuka.
- Maksimal satu modal utama aktif; jangan menumpuk modal detail dan pengaturan.
- Validasi gagal mempertahankan input dan dialog tetap terbuka.
- Close/cancel tidak menyimpan perubahan.
- Filter atau konteks record berubah: dialog ditutup atau konteks diperbarui secara eksplisit.
- Saat mutation sedang berjalan, cegah submit berulang. Jangan menutup dialog seolah proses batal jika request server masih berjalan.
- Ukuran modal mengikuti isi: form sederhana lebih sempit, detail/picker lebih lebar, maksimal sekitar 90vh.

Komponen `rnd/picker-modal` saat ini menyediakan shell. Audit tanggung jawab fokus/backdrop di caller sebelum memindahkan perilaku ke komponen bersama. Jangan mengklaim shell tersebut sudah menangani seluruh perilaku di atas.

## 11. Responsive dan konten panjang

Viewport pemeriksaan minimum: 360, 390, 768, 1024, dan 1440 px; periksa juga zoom browser 200%.

| Jenis konten | Strategi |
| --- | --- |
| Header/aksi | Stack di mobile, flex pada layar lebih lebar |
| Filter/form | Satu kolom mobile, grid saat ruang cukup |
| Nama/deskripsi panjang | Wrap; `min-w-0` pada flex child bila perlu |
| URL/kode sangat panjang | Break/wrap sesuai kebutuhan tanpa menghilangkan nilai |
| Kartu statistik | Grid yang menyesuaikan ruang, label tidak terpotong |
| Tabel transaksi lebar | Scroll horizontal hanya dalam container tabel |
| Modal | Padding luar, batas tinggi viewport, body scroll |
| Detail harian | Baris/kartu yang stack di mobile |

Tabel Stock Card memiliki banyak tipe transaksi dan tidak harus dipaksa muat pada satu layar. Pertahankan tabel komparatif jika diperlukan; gunakan container scroll, petunjuk scroll, dan kolom identitas tetap bila terbukti membantu. Jangan menyembunyikan kolom wajib atau input staff demi estetika. Breakpoint saja bukan solusi untuk struktur konten yang terlalu lebar.

## 12. Kandidat komponen reusable

Nama berikut merupakan usulan, bukan file yang sudah dibuat. Tempatkan di `resources/views/components` mengikuti struktur existing; jangan membuat direktori arsitektur baru tanpa persetujuan.

| Kandidat | Kontrak minimum | Catatan |
| --- | --- | --- |
| Page Header | title, description opsional, icon, eyebrow opsional, actions slot | Tidak wajib pada settings |
| Section | title opsional, icon opsional, description, content/actions slots | Border/radius/padding konsisten |
| Form Information | heading, tone, content | Pengisian vs proses otomatis |
| Stat Card | label, value, icon opsional, description | Tidak menghitung data bisnis |
| Status Badge | label, semantic color | Enum sebagai sumber label |
| Modal Shell | title, description, width, close/footer slots | Evaluasi shell R&D existing lebih dulu |
| Form Field | label, required, helper, error, input slot/id | Mendukung Livewire binding |
| Empty State | title, description, icon, action opsional | Bedakan kosong vs gagal mengambil data |
| Action Button | variant, label, icon, loading, disabled | Evaluasi komponen Filament existing |
| Attachment Field | existing upload contract, restrictions, preview/remove | Ikuti ERP Request dan disk existing |

Aturan komponen:

- UI presentasi tidak melakukan query database atau perhitungan domain.
- Caller tetap bertanggung jawab atas permission, branch scope, record scope, dan mutation.
- Meneruskan attributes/slots yang dibutuhkan tanpa menimpa `wire:model`, `wire:key`, `aria-*`, atau event Alpine.
- Jangan membuat abstraksi dengan banyak mode untuk kasus yang baru muncul sekali.
- Ekstrak setelah menemukan pemakaian berulang yang nyata; lakukan pilot pada halaman referensi.
- Jangan mengubah nama komponen existing tanpa menelusuri seluruh caller.

## 13. State UI

| State | Perilaku |
| --- | --- |
| Loading pertama | Spinner/pesan pada area yang memuat; tidak dianggap empty state |
| Refresh | Indikator pada tombol/area terkait, data lama dapat tetap terlihat |
| Kosong | Penjelasan singkat, aksi yang relevan bila diizinkan |
| Error integrasi | Alasan yang membantu pengguna dan opsi coba ulang bila tersedia |
| Validasi gagal | Error lokal, input tetap tersedia |
| Save berhasil | Feedback singkat, state tersimpan diperbarui |
| Tidak punya permission | Aksi tidak tampil; server tetap menolak akses tidak sah |
| Read-only | Field/aksi dinonaktifkan dengan penjelasan bila perlu |
| Tidak berlaku | Tanda netral dan alasan; jangan menggantinya dengan angka 0 |

Jangan menampilkan exception teknis, token, company credential, atau detail internal integrasi sebagai copy UI.

## 14. Alur pengembangan developer dan AI

### Alur wajib pada phase implementasi

1. Baca instruksi user, AGENTS.md, panduan panel, dan standar UI ini.
2. Tentukan pola halaman: index, detail, form, settings, atau picker.
3. Baca halaman referensi dan komponen sibling aktual.
4. Cari komponen existing untuk setiap pola berulang.
5. Tetapkan perubahan minimal; jangan mengubah workflow bisnis.
6. Gunakan dokumentasi sesuai versi package sebelum perubahan kode.
7. Implementasikan semua state yang terdampak dan responsive layout.
8. Jalankan test terdampak; lakukan pemeriksaan visual desktop/mobile dan keyboard.
9. Build bila asset berubah, cek hasil build, dan pastikan UI yang diuji memakai asset terbaru.
10. Laporkan perubahan, validasi, dan batas yang masih ada. Commit/push hanya ketika diminta.

### Usulan aturan AGENTS.md, belum diterapkan

> Untuk perubahan UI, baca `docs/ui-consistency-prd.md` dan panduan UI turunan yang sudah tersedia. Ikuti pola R&D Project Workspace dan Sales Report Scores sesuai jenis halaman. Periksa komponen existing sebelum membuat markup berulang. Pertahankan workflow, permission, branch scope, dan kalkulasi. Periksa responsive layout serta interaksi modal pada perubahan yang relevan.

Pada phase dokumentasi, buat `docs/ui-guidelines.md` sebagai panduan operasional ringkas yang menautkan PRD ini. PRD memuat scope/roadmap; guidelines memuat aturan dan contoh final. Jangan memelihara dua daftar token yang saling bertentangan.

## 15. Roadmap implementasi

| Phase | Hasil | Acceptance |
| --- | --- | --- |
| 1 — Audit | Inventaris pola/komponen existing dan halaman pilot | Referensi aktual, duplikasi dan pengecualian tercatat |
| 2 — Guidelines | Panduan UI ringkas dan aturan AGENTS.md | Aturan jelas, contoh nyata, tidak mengizinkan refactor di luar scope |
| 3 — Komponen pilot | Header/section/info/modal yang terbukti berulang | Pilot berjalan tanpa perubahan bisnis dan tanpa dependency baru |
| 4 — Adopsi reference pages | R&D, Scores, Settings memakai komponen yang cocok | Visual selaras, modal/filter/save tetap bekerja |
| 5 — Adopsi bertahap | Modul lain dipilih menurut prioritas user | Scope per modul, review dan test sebelum melanjutkan |
| 6 — Pemeliharaan | Review UI menjadi bagian setiap perubahan frontend | Penyimpangan dibahas, panduan tetap sesuai implementasi |

Semua phase di atas berstatus belum dimulai sebagai program standardisasi. Halaman referensi sudah ada; itu tidak berarti seluruh komponen standar sudah diimplementasikan. Jangan otomatis memulai phase berikutnya hanya karena PRD selesai.

## 16. Kriteria penerimaan

- [ ] Container, warna, spacing, tipografi, input, dan tombol sesuai baseline.
- [ ] Tidak ada shadow pada kartu/section biasa.
- [ ] Header tidak berulang dan informasi tidak dibuat berlebihan.
- [ ] Label/ikon/status tetap terbaca tanpa mengandalkan warna saja.
- [ ] Petunjuk section berada di dalam container yang relevan.
- [ ] Mobile tidak mengalami scroll horizontal seluruh halaman.
- [ ] Konten panjang, data kosong, angka nol, dan label panjang diperiksa.
- [ ] Modal dapat dibuka, ditutup, dioperasikan dengan keyboard, dan tidak menumpuk.
- [ ] Input validasi gagal tetap tersedia, close/cancel tidak melakukan save.
- [ ] Loading dan disabled state bekerja tanpa submit ganda.
- [ ] Permission, cakupan cabang, route, kalkulasi, dan approval tidak berubah.
- [ ] Komponen existing digunakan bila sesuai; abstraksi baru memiliki alasan nyata.
- [ ] Test terdampak lolos; build dan review visual dilakukan sesuai perubahan.
- [ ] Pengecualian desain mendapat alasan spesifik, bukan variasi dekoratif spontan.

## 17. Pengujian dan review

Test perilaku penting: buka/tutup modal, pergantian konteks, validasi, save/cancel, loading mutation, permission, dan akses record. Jangan menulis test yang hanya menyalin seluruh class Tailwind atau menghasilkan snapshot rapuh untuk setiap perubahan spacing.

Review visual meliputi viewport minimum, modal dengan konten panjang, nama record panjang, field errors, data kosong, dan zoom 200%. Periksa dark mode hanya pada panel yang memang mendukungnya. Catat bagian yang belum dapat diverifikasi; keberhasilan build saja bukan bukti layout telah benar.

PR memuat halaman yang berubah, alasan pemilihan pola, bukti validasi, dan pengecualian jika ada. Jangan menyertakan perubahan module lain yang masih belum di-commit saat user meminta push satu fitur.

## 18. Guardrail bisnis dan perubahan konteks

- Standardisasi UI tidak mengubah scoring Sales Report: hanya Completed mendapat nilai 100; aturan tanggal wajib tetap mengikuti konfigurasi existing.
- Auto-reject tetap hanya memproses laporan Supervisor Review yang memenuhi pengaturan; Finance Review tidak ditolak oleh aturan tersebut.
- Penghapusan header Settings merupakan keputusan user yang dipertahankan.
- Data attachment, akses token ESB, company/branch mapping, dan lokasi penyimpanan tidak diubah untuk kepentingan visual.
- Jangan menjalankan permission sync massal, mengubah role, atau menambah akses untuk mempermudah review UI.
- Perubahan Stock Card yang belum di-commit adalah scope berbeda; tidak otomatis masuk program standardisasi ini.
- Pembaruan preferensi user dicatat pada panduan dan diterapkan pada scope yang diminta, bukan seluruh aplikasi tanpa instruksi.

## 19. Definition of Done untuk PRD ini

Dokumen tersedia, referensi lokal valid, scope dan status implementasi jelas, alur developer/AI serta acceptance criteria lengkap. Penyelesaian PRD tidak mencakup pembuatan komponen, perubahan AGENTS.md, modifikasi UI, commit, push, atau deployment.
