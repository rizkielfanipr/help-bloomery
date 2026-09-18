# Refactoring Phase 4–7 — Catatan Implementasi

Tanggal: 18 September 2026. Referensi: [PRD](codebase-refactoring-prd.md). Tidak ada commit, push, migration, perubahan domain, atau perubahan permission.

## Phase 4 — Client ESB Core

`EsbCoreClient` memusatkan request JSON/multipart, timeout, login, cache token, refresh 401 satu kali, dan pemeriksaan envelope/error. Digunakan EsbCoreService, EsbCompanyProductService, EsbGoodsReceiptService, dan EsbItemJournalService; Stock Movement mewarisi akses client melalui service Item Journal existing.

Token per company dan lock login menggunakan key existing `esb_core.access_token.{company}` dan `esb_core.login_lock.{company}`. Credential default Core tetap dipisahkan dari credential company; default tidak diubah menjadi BLSS. Branch, purpose, taxonomy, dan catalog mempertahankan scope cache existing. Catalog memasukkan branch lokal, tanggal, unit, dan fingerprint mapping company/branch.

Request tidak diulang otomatis karena timeout/5xx. Refresh hanya berlaku sekali setelah 401, termasuk multipart attachment yang sebelumnya bisa rekursif tanpa batas. Payload dan path transaksi tidak diganti. Login harus memiliki status ok dan accessToken berupa string yang tidak kosong. Error tidak menampilkan raw response body/header.

Purpose dan taxonomy sekarang menolak data halaman invalid, halaman kosong dengan kelanjutan, serta batas pagination yang masih memiliki kelanjutan. Hasil parsial tidak masuk cache. Ini merupakan perubahan penanganan kegagalan integrasi yang disengaja; alur sukses tetap sama.

Test BOM existing sebelumnya mengharapkan tipe 1 selalu dipaksakan, sementara kontrak kode baseline memakai default jika kosong (`??=`). Test dikoreksi untuk mengkarakterisasi kedua kontrak, default 1 dan tipe eksplisit yang dipertahankan; logika BOM tidak diubah.

Verifikasi sebelum pemindahan modul: 45 test / 293 assertions lulus untuk client, Core, bulk product, Stock Movement, Item Journal, dan Receiving. Gabungan tes memakai memory_limit 512M karena batas PHP lokal 128M tidak cukup untuk kelompok test Filament ini. Tidak mengubah batas memory production.

Integrasi non-Core `EsbService` dan `EsbPromotionService` dengan token/static endpoint berbeda tetap dipertahankan. Tidak menggabungkan credential yang berbeda hanya untuk menyamakan struktur. Pengambilan seluruh produk/BOM dan saran kode Core kini menolak pagination terputus atau batas halaman yang belum selesai. Taxonomy default Core memakai client bersama melalui getAllProducts, sehingga halaman gagal tidak dilewati lalu dicache; pengambilannya berurutan, menggantikan pool khusus lama. Respons product/BOM wajib memiliki array data. Integrasi non-Core tetap di luar konsolidasi client ini.

## Phase 5 — Ekstraksi Receiving dan Pengiriman Item Journal

`RecordInboundGoodsReceiptAction` menerima aktor, context form yang sudah divalidasi, lokasi/payload, item QC yang sudah dinilai, serta path bukti yang sudah disimpan. Action menyimpan receipt, item, batch/expiry, dan incident vendor dalam satu transaksi; inspector/reporter menggunakan aktor eksplisit.

Page tetap memeriksa PO terbaru, validasi dokumen/QC/qty/lokasi, menyimpan upload, membersihkan upload jika persistensi gagal, mengirim notifikasi Purchasing, memanggil API ESB, dan menampilkan feedback. Hanya qty Accepted dikirim; Hold/Rejected serta demerit tetap mengikuti service QC existing. Tidak memanggil HTTP dalam transaksi database.

Enam test Receiving/action lulus dengan 92 assertions, termasuk batch/shelf life, alur form, dan rollback parent receipt saat item gagal disimpan.

Stock Card synchronizer/category service, Sales Report calculator/reconciliation, ERP SLA/business hours, R&D forecast/price service, serta SOP publisher existing dipertahankan. Pemindahan namespace bukan bukti seluruh proses bisnis sudah diekstrak. Ekstraksi tambahan proses modul lain tetap pekerjaan lanjutan Phase 5; service existing dipakai kembali, bukan diganti demi jumlah class.

### Pengiriman Item Journal

`SubmitQualityControlItemJournalAction` memusatkan create ESB, status hasil, upload attachment tersimpan, pemetaan URL, dan status verification_required/attachment_failed. Page mempertahankan validasi Company/Branch/Location/Product/Purpose, persistensi draft lokal, upload awal ke R2, feedback, retry/delete, serta scope daftar.

Action memeriksa permission submit dan kepemilikan/view-all sebelum create. Journal yang sudah memiliki nomor ESB ditolak sebelum pengiriman ulang. Retry attachment memanggil metode upload terpisah, tanpa membuat jurnal baru. Guard nomor ini tidak menjamin idempotensi lintas proses yang membaca record stale; rekonsiliasi tetap diperlukan untuk kegagalan create yang tidak pasti.

Sembilan test action dan UI/back office Item Journal lulus dengan 52 assertions. Mock service membuktikan create dipanggil sekali saat attachment gagal, retry hanya attachment, status gagal yang sesuai, dan pemilik lain ditolak sebelum request.

## Phase 6 — Struktur Modul

274 file PHP dipindahkan ke module folders: resource Helpdesk beserta Pages/Schemas/Tables/RelationManagers existing; halaman modul Helpdesk dan Employee; dua widget Helpdesk; resource Service Requests Employee. 73 template page/widget dipindahkan ke view module; template shared dan detail/include yang belum perlu dipindahkan tetap stabil.

Import eksplisit, referensi class pada Blade/routes/test/provider, referensi view, serta discovery module diperbarui. Referensi sibling yang sebelumnya memakai namespace halaman bersama diberi import ketika modulnya berbeda. Nama route dipertahankan saat template berpindah; route dan view yang sebelumnya memiliki string serupa tidak boleh tertukar.

Discovery resource/page/widget memindai Modules selain lokasi bersama existing. Filament Resource Pages memiliki isDiscovered false, sehingga tidak diregistrasikan sebagai standalone page.

Snapshot 287 route sebelum/sesudah grouping sama setelah hanya memetakan action class ke namespace baru: domain, method, URI, nama, dan middleware tidak berubah. Test discovery memastikan resource Project/Stock Card dan page Receiving/Launcher ditemukan satu kali. Resource bersama Admin/Helpdesk di `app/Filament/Resources` tidak dipindahkan.

## Phase 7 — Identitas Employee

Provider menjadi EmployeePanelProvider dan namespace panel user menjadi `App\Filament\Employee`; ID panel menjadi employee. Registrasi bootstrap, User::canAccessPanel, login/launcher, getUrl(panel), route name internal, layout/view, theme Vite, middleware reference, navigation, dan test mengikuti identitas baru.

Domain `config('app.domain')` tetap; fallback path `/casual` dipertahankan sebagai kompatibilitas. URI QR asset, export, dan halaman modul tetap. Tidak perlu redirect tambahan pada URL yang tetap sama; nama route internal bukan URL eksternal. Profile Driver/Technician tetap class modul yang berbeda.

LocalStorage membaca employee-theme terlebih dahulu lalu casual-theme sebagai fallback. Casual tetap digunakan untuk nama modul workforce, model/permission bisnis casual, domain local yang existing, dan path kompatibilitas. File route historis casual.php dinamai employee.php tetapi tetap tidak dimuat, sesuai baseline.

Snapshot 287 route setelah rename sama setelah memetakan nama filament.casual ke filament.employee, action namespace, middleware SetUpPanel:employee, dan nama export briefing. Tes login/launcher/QR/branch/discovery: 21 test / 88 assertions lulus. Build Vite berhasil.

## Batas dan Kelanjutan

Pengujian UI browser tidak tersedia pada sesi ini; CUA tidak menemukan browser. Upload langsung ke R2, kamera, production, dan screenshot semua breakpoint belum diverifikasi. Temuan baseline akses back office/warranty/maintenance tidak diperbaiki diam-diam.

Phase 8–9 adalah backlog operasional/API terpisah sesuai PRD. Tidak menambahkan infrastruktur, backup, queue, atau API frontend pada refactoring ini.

Sebelum deployment, kirim seluruh perubahan class/view beserta penghapusan path lama dan manifest build; composer autoload serta cache lama harus diperbarui. Revert seluruh pemindahan namespace/provider/reference sebagai satu kesatuan saat rollback; tidak perlu rollback database.

## Hasil Verifikasi Lanjutan

- 35 test / 653 assertions: client, discovery, dan RBAC; seluruh named link pada kedua sidebar kustom terdaftar.
- 39 test / 204 assertions: BOM recipe, Core/client, dan material forecast setelah path source test dipindahkan.
- Scanner QR: 7 test Node lulus; jalur template mengikuti module Technician.
- `php artisan view:cache`, build Vite, Pint, dan whitespace check berhasil.

Run seluruh suite juga dilakukan. Kegagalan yang berkaitan dengan route Breeze yang tidak aktif, role casual_staff lowercase, field scheduledDate yang telah dihapus, serta asumsi scanner lama diperiksa terhadap checkout baseline e1c5724 terpisah dengan database SQLite in-memory. Tidak memperbaiki atau menghapus test fitur historis tersebut sebagai bagian pemindahan folder. Run suite final: 663 test, 632 passed, 3.795 assertions, 31 kegagalan. Sebanyak 30 nama test gagal cocok dengan hasil checkout baseline terpisah; satu test sidebar Product List tidak memalsukan HTTP sehingga mencoba mengakses jaringan. Fixture sidebar tersebut diperbaiki dengan mock HTTP dan preventStrayRequests, kemudian 11 test Product List lulus dengan 34 assertions. Tiga test action Item Journal yang dibuat setelah suite penuh mulai juga diuji secara terpisah; sembilan test action/UI/back office lulus. Tidak mengklaim seluruh suite sudah hijau.


Verifikasi penutup integrasi: 44 test / 223 assertions lulus setelah guard format product/BOM dan taxonomy default diperbarui. Sebanyak 189 tautan lokal dokumentasi refactoring valid; tautan audit baseline mengikuti lokasi file baru, sementara tabel route baseline tetap historis.

Panduan rilis: build manifest memiliki entry resources/css/filament/employee/theme.css. Jangan memakai manifest lama setelah provider berubah. Jalankan composer dump-autoload serta optimize:clear sebelum warm cache; jika aplikasi tidak dapat boot karena cache provider/class lama, bersihkan cache konfigurasi/komponen lama pada bootstrap/cache sesuai prosedur deployment, kemudian jalankan Artisan kembali. Browser yang masih memiliki snapshot Livewire lama perlu memuat ulang halaman setelah rilis. Tidak ada perubahan database pada langkah ini.

## Penyederhanaan Struktur dan Cleanup

Dilanjutkan atas instruksi user pada 18 September 2026. Sebanyak 10 Actions dikelompokkan berdasarkan modul dan 28 Services berdasarkan domain; seluruh delapan service ESB berada di `app/Services/Esb`. Import, type hint, mock, pemanggilan container, dan tautan dokumentasi mengikuti namespace baru. Tidak menambah wrapper, dependency, model, migration, atau layer kosong.

Sebanyak 107 view direlokasi ke `resources/views/filament/{panel}/{module}` sesuai PRD. Detail/partial yang sebelumnya tersebar di root Helpdesk ikut masuk modul pemiliknya; PDF laporan casual berada pada Employee/Casual. Halaman bersama, auth Filament, layout bersama, schema komponen, dan komponen lintas halaman tetap di lokasi bersama. Angka ini mencakup view dari pengelompokan sebelumnya; bukan 107 view fitur baru. Lima view legacy di antaranya kemudian dihapus setelah audit.

53 file kode tidak digunakan dihapus: dua action export Design/Content tanpa pemanggil, tiga file route dashboard yang tidak direquire, placeholder auth route kosong, scaffold Breeze Auth/Profile beserta request/layout/view/component yang hanya dipakai scaffold tersebut, welcome/dashboard scaffold tidak diroute, dua partial Employee lama tanpa include, dua table header Design/Content tanpa render hook, dan location picker lama tanpa pemanggil. Auth/register aktif tetap melalui Filament. Test historis tetap dipertahankan; tidak dihapus untuk membuat suite lulus. Data, attachment R2, model/permission, dan transaksi tidak dihapus.

Audit meliputi referensi class/template, route aktif, provider, render hook, pemanggilan Blade component, dan discovery. Resource bersama Admin/Helpdesk serta panel Driver/Technician mandiri tetap dipakai dan dipertahankan. Discovery resource/widget root kosong Helpdesk/Employee dihapus; discovery Modules dan halaman bersama tetap digunakan.

Verifikasi awal: 29 test / 434 assertions lulus, termasuk container seluruh Actions/Services tanpa HTTP, keberadaan seluruh referensi literal view aplikasi/Blade, sidebar/discovery, desktop detail, login, dan launcher. Snapshot seluruh 287 route sebelum/sesudah cleanup identik pada domain, method, URI, nama, action, dan middleware. Build Vite, kompilasi Blade, composer dump-autoload tanpa script, Pint, whitespace check, dan tujuh test scanner QR berhasil. Tidak ada referensi source ke lokasi lama; 170 tautan lokal dokumen refactoring valid. Alias render hook Service Requests Employee juga diperjelas menjadi EmployeeListServiceRequests; class dan scope hook tetap sama.

Run regresi penutup: **668 test, 638 passed, 3.992 assertions; 21 failures dan 9 errors**. Seluruh 30 kasus gagal cocok dengan daftar kegagalan baseline yang telah diaudit; tidak ditemukan kasus gagal tambahan. Run awal sempat memiliki satu error render komponen Bulk Product saat kompilasi cache view dijalankan bersamaan dengan suite. Pengujian terpisah Bulk Product/struktur/discovery lulus (21 test / 460 assertions), lalu suite diulang tanpa build/cache mutation bersamaan; error tersebut tidak muncul lagi. Hubungan dengan kompilasi cache adalah dugaan dari kondisi run, bukan perubahan kode untuk memperbaiki library Blade.

Seluruh suite belum hijau karena test historis route Breeze yang tidak aktif, role casual_staff lama, dan scheduledDate/scanner lama masih gagal. Test tidak dihapus. QA browser/mobile visual tetap tertunda karena browser tidak tersedia. Perubahan belum di-commit atau di-push.
