<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->product_name }}</p>
            <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $record->product_code }}</p>
        </div>
        <x-filament::badge :color="$record->sourcing_status->getColor()">
            {{ $record->sourcing_status->getLabel() }}
        </x-filament::badge>
    </div>

    <div class="max-h-[calc(100dvh-16rem)] space-y-3 overflow-y-auto overscroll-contain pb-1 pr-1 sm:max-h-[60dvh]">
        @forelse ($record->sourcings as $sourcing)
            <div @class([
                'rounded-xl border p-4 shadow-sm',
                'border-success-400 bg-success-50/70 ring-1 ring-success-200 dark:bg-success-950/20 dark:ring-success-900' => $record->sourcing_selected_id === $sourcing->id,
                'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' => $record->sourcing_selected_id !== $sourcing->id,
            ])>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $sourcing->supplier_name }}
                        @if ($record->sourcing_selected_id === $sourcing->id)
                            <x-filament::badge color="success" size="sm" class="ml-1">Supplier Terpilih</x-filament::badge>
                        @endif
                    </p>
                    <p class="whitespace-nowrap text-base font-bold text-primary-700 dark:text-primary-300">
                        Rp{{ number_format((float) $sourcing->price, 0, ',', '.') }}
                    </p>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-5">
                    <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5"><dt class="text-gray-400">Merk</dt><dd class="mt-0.5 font-medium">{{ $sourcing->brand ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5"><dt class="text-gray-400">MOQ</dt><dd class="mt-0.5 font-medium">{{ $sourcing->moq ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5"><dt class="text-gray-400">Lead Time</dt><dd class="mt-0.5 font-medium">{{ $sourcing->lead_time_days ? $sourcing->lead_time_days.' hari' : '—' }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5"><dt class="text-gray-400">Kontak</dt><dd class="mt-0.5 font-medium">{{ $sourcing->contact_name ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5"><dt class="text-gray-400">Telepon</dt><dd class="mt-0.5 font-medium">{{ $sourcing->contact_phone ?: '—' }}</dd></div>
                </dl>

                @if ($sourcing->notes)
                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $sourcing->notes }}</p>
                @endif

                @if ($sourcing->attachmentUrl())
                    <a href="{{ $sourcing->attachmentUrl() }}" target="_blank"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 transition hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-950/40 dark:text-primary-300">
                        <x-heroicon-o-paper-clip class="h-3.5 w-3.5" />
                        Lihat Lampiran
                    </a>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada supplier yang diajukan.</p>
        @endforelse
    </div>

    @if ($record->rnd_note || $record->finance_note)
        <div class="space-y-2 border-t border-gray-200 pt-3 dark:border-gray-700">
            @if ($record->rnd_note)
                <p class="text-xs text-gray-600 dark:text-gray-300">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">Catatan RnD ({{ $record->rndReviewer?->name ?? '—' }}):</span>
                    {{ $record->rnd_note }}
                </p>
            @endif
            @if ($record->finance_note)
                <p class="text-xs text-gray-600 dark:text-gray-300">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">Catatan Finance ({{ $record->financeReviewer?->name ?? '—' }}):</span>
                    {{ $record->finance_note }}
                </p>
            @endif
        </div>
    @endif
</div>
