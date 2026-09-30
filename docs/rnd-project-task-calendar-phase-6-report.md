# Laporan Implementasi Phase 6 — Kalender Tugas Project R&D

## Status

Phase 6 selesai pada 30 September 2026. Fitur utama Kalender Tugas Project, workflow PIC, review, notifikasi, reminder, attachment, dan hardening telah tervalidasi oleh test terfokus dan full suite.

## Perbaikan hardening

1. Assignment PIC memakai row lock pada Task agar dua request bersamaan tidak melewati pemeriksaan duplikat.
2. Pasangan Task, Branch, dan PIC yang sama ditolak dengan validation error yang dapat ditampilkan UI.
3. Assignment cancelled diaktifkan kembali saat PIC lama dipilih lagi, sehingga unique constraint tidak menghasilkan error mentah.
4. Create Task menolak pasangan Branch dan PIC yang berulang sebelum Task dibuat.
5. Total attachment instruksi lama dan baru dibatasi maksimal lima file.
6. Attachment lama dipertahankan ketika Task diedit dan tidak ada file baru.
7. File instruksi dan hasil hanya dapat dibuka jika path tercatat pada Task atau Follow-up terkait.
8. Query attachment pada Blade dihapus dan diganti state Livewire.
9. Detail Task memakai eager loading untuk assignment, Branch, PIC, dan follow-up.
10. Nama foreign key Follow-up dan Reminder dipendekkan agar memenuhi batas identifier MySQL.

## Insiden migration MySQL

Migration awal gagal karena nama otomatis foreign key Follow-up melebihi batas 64 karakter MySQL.

Constraint sekarang memakai nama eksplisit:

- `rnd_task_followup_assignment_fk`
- `rnd_task_reminder_assignment_fk`

Jika kegagalan lama sudah meninggalkan tabel `rnd_project_task_follow_ups` tetapi migration belum tercatat, migration akan mendeteksi tabel parsial tersebut dan melengkapi index serta foreign key yang belum terbentuk tanpa menghapus data.

## Validasi

- Migration MySQL lokal: berhasil.
- Pint: berhasil.
- Test Project Task: 102 test, 221 assertion, seluruhnya lulus.
- Full suite: 1.128 test, 5.700 assertion, seluruhnya lulus dengan memory limit 512 MB.
- Pemeriksaan whitespace Git: berhasil.

## Deployment

1. Backup database.
2. Pull commit implementasi.
3. Jalankan `php artisan migrate --force`.
4. Jalankan permission seeder yang digunakan aplikasi bila server belum memiliki permission Project Task.
5. Jalankan `php artisan optimize:clear`.
6. Pastikan cron `schedule:run` dan queue worker aktif untuk reminder.
7. Smoke test Kalender Tugas, assignment PIC, attachment, notification, review, dan reminder.

## Rollback

Rollback kode tidak boleh menghapus tabel Project Task yang sudah berisi data. Hentikan scheduler reminder bila perlu, rollback kode aplikasi, dan pertahankan tabel sampai prosedur backup serta pemulihan data disetujui.
