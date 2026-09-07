<div class="space-y-5">
    <div class="grid gap-3 rounded-xl bg-gray-50 p-4 text-sm dark:bg-gray-800 sm:grid-cols-2">
        <div><p class="text-xs text-gray-500">Nomor Maintenance</p><p class="font-semibold text-gray-900 dark:text-white">{{ $maintenance->maintenance_number }}</p></div>
        <div><p class="text-xs text-gray-500">Status</p><p class="font-semibold text-gray-900 dark:text-white">{{ $maintenance->status === 'submitted' ? 'Terkirim' : 'Draft' }}</p></div>
        <div><p class="text-xs text-gray-500">Teknisi</p><p class="font-semibold text-gray-900 dark:text-white">{{ $maintenance->technician?->name ?? '—' }}</p></div>
        <div><p class="text-xs text-gray-500">Cabang</p><p class="font-semibold text-gray-900 dark:text-white">{{ $maintenance->branch?->name ?? '—' }}</p></div>
        <div><p class="text-xs text-gray-500">Tanggal pengecekan</p><p class="font-semibold text-gray-900 dark:text-white">{{ $maintenance->checked_at?->format('d M Y') ?? '—' }}</p></div>
        <div><p class="text-xs text-gray-500">Dikirim</p><p class="font-semibold text-gray-900 dark:text-white">{{ $maintenance->submitted_at?->format('d M Y, H:i') ?? 'Belum dikirim' }}</p></div>
    </div>

    <div class="space-y-3">
        @foreach($maintenance->items as $item)
            <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="font-semibold text-gray-900 dark:text-white">{{ $item->question }}</p><p class="mt-1 text-xs text-gray-500">{{ $item->check_procedure ?: '—' }}</p></div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $item->result === 'pass' ? 'bg-emerald-100 text-emerald-700' : ($item->result === 'fail' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">{{ $item->result === 'pass' ? 'Baik' : ($item->result === 'fail' ? 'Perlu perbaikan' : ($item->result === 'na' ? 'Tidak berlaku' : 'Belum diisi')) }}</span>
                </div>
                <p class="mt-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $item->notes ?: 'Belum ada catatan.' }}</p>
                @if($item->photoUrls())
                    <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">
                        @foreach($item->photoUrls() as $index => $url)
                            <a href="{{ $url }}" target="_blank" class="aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800"><img src="{{ $url }}" alt="Bukti {{ $index + 1 }}" class="h-full w-full object-cover"></a>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-xs text-gray-400">Belum ada foto bukti.</p>
                @endif
                <p class="mt-3 text-[11px] text-gray-400">Dibuat {{ $item->created_at?->format('d M Y, H:i') ?? '—' }} · Terakhir diubah {{ $item->updated_at?->format('d M Y, H:i') ?? '—' }}</p>
            </article>
        @endforeach
    </div>
</div>
