<details class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
    <summary class="cursor-pointer font-semibold">Cara menghitung SLA ERP IT</summary>
    <div class="mt-3 space-y-2 text-xs leading-relaxed">
        <p><strong>Jam yang dihitung:</strong> Senin–Jumat, 08.00–17.00 WIB (Asia/Jakarta), yaitu 9 jam per hari. Sabtu, Minggu, sebelum 08.00, dan setelah 17.00 tidak dihitung. Jam istirahat dan hari libur nasional yang jatuh pada weekdays tetap dihitung.</p>
        <p><strong>Respons pertama:</strong> dari waktu Submitted sampai IT pertama kali mengubah status menjadi Review. Membuka tiket atau mengedit catatan belum dianggap respons.</p>
        <p><strong>Penyelesaian:</strong> dari Submitted sampai Completed, termasuk waktu tunggu review dan approval. Rejected tidak masuk rata-rata penyelesaian.</p>
        <p><strong>Rumus:</strong> jumlahkan irisan durasi setiap hari dengan 08.00–17.00. Rata-rata = total durasi valid ÷ jumlah tiket dengan durasi valid, menggunakan seluruh hasil filter daftar, bukan hanya halaman yang sedang tampil.</p>
        <p><strong>Contoh:</strong> Jumat 16.30 → Senin 09.30 = Jumat 30 menit + Senin 90 menit = 2 jam kerja. Sabtu 10.00 → Senin 08.30 = 30 menit kerja.</p>
        <p><strong>Ketepatan data:</strong> timestamp kejadian sebenarnya tetap disimpan, termasuk di luar jam kerja. Durasi dihitung dalam detik dan dibulatkan hanya saat ditampilkan. Durasi 0 tetap masuk sampel. Data historis tanpa timestamp yang pasti ditampilkan sebagai “Tidak tersedia” dan dikecualikan dari rata-rata.</p>
        <p><strong>Filter tanggal:</strong> menggunakan tanggal pengajuan (Submitted). Tiket belum direspons atau belum selesai belum masuk rata-rata metrik terkait. “Belum selesai” menghitung tiket aktif selain Completed dan Rejected.</p>
        <p>Pengukuran ini belum memiliki batas target SLA per priority atau request type, sehingga belum menandai tiket sebagai lewat SLA.</p>
    </div>
</details>
