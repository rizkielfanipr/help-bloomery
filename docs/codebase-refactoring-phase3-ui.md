# Phase 3 — Komponen UI Reusable

Tanggal: 18 September 2026. Referensi: [PRD](codebase-refactoring-prd.md).

## Ekstraksi Pertama: Attachment

Komponen `resources/views/components/attachment-upload.blade.php` dipakai oleh form ERP Request, Design Request, dan Content Request. Ketiganya sebelumnya memiliki markup attachment yang sama, dengan perbedaan teks tombol.

Props: `files`, `model`, `removeAction`, `buttonText`, dan `accept`. Binding bertingkat seperti `qc.photos` didukung. Nama file di-escape oleh Blade; pesan error item mengikuti binding caller. Style existing, ikon SVG, truncate nama file, dan dark variant dipertahankan.

Alur: pengguna memilih file → Livewire memperbarui property caller → komponen menampilkan nama file → tombol hapus memanggil handler caller dengan index → caller memperbarui daftar. Submit, validasi jenis/ukuran/jumlah file, penyimpanan, serta aturan bisnis tetap dimiliki page. `accept` hanya petunjuk browser, bukan validasi server.

Label dan informasi batas file tetap berada pada caller. Komponen tidak menambahkan batas atau loading baru; perilaku submit/loading existing tidak diubah. Form teknisi dengan preview gambar, Receiving dengan binding per produk, serta Item Journal tidak dipindahkan pada ekstraksi ini.

## Evaluasi Komponen Existing

Header Teknisi, shell detail back office, modal R&D, modal generic, dan bottom navigation sudah tersedia. Modal R&D dan generic memiliki kontrak berbeda; tidak digabung pada perubahan attachment ini. Komponen informasi pengisian kini tersedia; penerapan attachment pada form dengan preview/binding dinamis masih perlu dievaluasi pada kelanjutan Phase 3.

## Verifikasi

Test Livewire mencakup render, upload PDF, nama file, dan penghapusan pada tiga page. Test render komponen mencakup binding bertingkat, handler custom, accept, escaping nama file, dan pesan validasi. Test Content Request existing ikut dijalankan. Build Vite berhasil; tidak ada perubahan route, schema, atau permission.

Pengujian ini tidak mencakup browser, screenshot layar kecil, atau upload langsung ke R2. Phase 3 masih berjalan; hasil ini merupakan ekstraksi pertama yang sudah digunakan beberapa halaman, bukan penyelesaian seluruh komponen UI aplikasi. Belum di-commit atau di-push.

## Ekstraksi Kedua: Informasi Pengisian

`resources/views/components/form-information.blade.php` digunakan pada Document Check dan Konfirmasi Penerimaan di Receiving, serta petunjuk Company Code di Item Journal. Props `title`, `icon`, dan `headingClass` mengatur heading dan ikon; slot menampung isi petunjuk; attribute caller mempertahankan warna, border, dan spacing existing. Ikon opsional menggunakan Heroicon yang sudah terpasang. Isi panjang mengikuti lebar container dengan `min-w-0` pada varian ikon.

Tidak ada perubahan input, validasi, upload, API, atau proses penyimpanan. Komponen ini tidak mengatur aturan bisnis form.

Verifikasi lanjutan: 16 test / 146 assertions lulus, meliputi komponen informasi, komponen attachment, Receiving, dan Item Journal. Test komponen memeriksa judul custom, escaping, slot, attribute, serta kedua varian ikon. Pint, build Vite, dan diff whitespace berhasil. Pemeriksaan visual browser masih belum dilakukan.


Setelah Phase 6–7, caller berada di namespace App\Filament\Employee\Modules dan view module; API komponen tetap sama. Implementasi komponen terpilih selesai, dengan QA browser tetap tertunda karena browser tidak tersedia pada sesi ini. Header/shell/modal existing dipertahankan untuk reuse, tanpa penggabungan kontrak yang berbeda.
