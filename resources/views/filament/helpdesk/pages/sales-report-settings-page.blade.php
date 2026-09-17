<x-filament-panels::page>
    @php
        $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
    @endphp
    <form wire:submit="save" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start gap-3 border-b border-gray-200 p-5 dark:border-gray-700">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-clock class="h-5 w-5" /></div>
            <div><h3 class="font-bold text-gray-900 dark:text-white">Auto-Reject Sales Report</h3><p class="mt-1 text-sm leading-6 text-gray-500">Aturan global untuk seluruh cabang. Hanya laporan Supervisor Review yang belum di-approve yang dapat ditolak otomatis.</p></div>
        </div>
        <div class="space-y-5 p-5 sm:p-6">
            <label for="auto-reject-enabled" class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                <span><span class="block text-sm font-semibold text-gray-900 dark:text-white">Aktifkan Auto-Reject</span><span class="mt-1 block text-xs leading-5 text-gray-500">Laporan yang melewati batas diproses pada eksekusi scheduler berikutnya.</span></span>
                <span class="relative mt-0.5 shrink-0"><input id="auto-reject-enabled" type="checkbox" wire:model="data.auto_reject_enabled" class="peer sr-only"><span class="block h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-blue-600 peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500 peer-focus-visible:ring-offset-2 dark:bg-gray-600"></span><span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition peer-checked:translate-x-5"></span></span>
            </label>
            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><x-heroicon-o-calendar-days class="h-5 w-5 text-blue-500" />Waktu &amp; Periode</h4>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label for="reject-days" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Auto-Reject Setelah <span class="text-red-500">*</span></label><div class="relative"><input id="reject-days" type="number" min="1" max="2147483647" step="1" required wire:model="data.auto_reject_after_days" class="{{ $inputClass }} pr-16"><span class="pointer-events-none absolute right-3 top-3 text-sm text-gray-400">Hari</span></div><p class="mt-2 text-xs leading-5 text-gray-500">Isi jumlah hari kalender bebas, minimal 1 hari. Dihitung sebagai hari × 24 jam sejak seluruh shift dikirim.</p>@error('data.auto_reject_after_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="reject-effective" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Berlaku Mulai <span class="text-red-500">*</span></label><input id="reject-effective" type="date" required wire:model="data.effective_from" class="{{ $inputClass }}"><p class="mt-2 text-xs leading-5 text-gray-500">Hanya tanggal laporan mulai tanggal ini yang terkena aturan.</p>@error('data.effective_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                </div>
            </section>
            <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><x-heroicon-o-document-text class="h-5 w-5 text-blue-500" />Informasi Penolakan</h4>
                <label for="reject-reason" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Alasan Auto-Reject <span class="text-red-500">*</span></label><textarea id="reject-reason" rows="3" required maxlength="255" wire:model="data.auto_reject_reason" class="{{ $inputClass }} resize-y" placeholder="Contoh: Tidak ada approval Supervisor dalam :days hari."></textarea><p class="mt-2 text-xs leading-5 text-gray-500">Gunakan <span class="font-semibold text-blue-600 dark:text-blue-300">:days</span> untuk menampilkan jumlah hari yang diatur. Alasan tercatat di riwayat approval.</p>@error('data.auto_reject_reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </section>
            <div class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30"><x-heroicon-o-information-circle class="mt-0.5 h-5 w-5 shrink-0 text-blue-500" /><div><h4 class="text-sm font-semibold text-blue-800 dark:text-blue-200">Informasi Proses Otomatis</h4><p class="mt-1 text-xs leading-6 text-blue-700 dark:text-blue-300">Hasil penolakan ditampilkan sebagai <strong>Rejected by System</strong> dan bernilai 0 pada scoring. Draft, Finance Review, Completed, serta tanggal tanpa laporan tidak diproses. Sabtu, Minggu, dan hari libur tetap masuk waktu tunggu.</p></div></div>
        </div>
        <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50/60 px-5 py-4 dark:border-gray-700 dark:bg-gray-800/30 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-gray-500">Perubahan berlaku setelah pengaturan disimpan.</p><button type="submit" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-50"><x-heroicon-o-check class="h-4 w-4" /><span wire:loading.remove wire:target="save">Simpan Pengaturan</span><span wire:loading wire:target="save">Menyimpan…</span></button></div>
    </form>
</x-filament-panels::page>
