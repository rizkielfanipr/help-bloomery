<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->product_name }}</p>
            <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $record->product_code }}</p>
        </div>
        <x-filament::badge :color="$record->sourcing_status->getColor()">
            {{ $record->sourcing_status->getLabel() }}
        </x-filament::badge>
    </div>

    <div class="space-y-3">
        @forelse ($record->sourcings as $sourcing)
            <div @class([
                'rounded-lg border p-3',
                'border-primary-400 bg-primary-50 dark:bg-primary-950/30' => $record->sourcing_selected_id === $sourcing->id,
                'border-gray-200 dark:border-gray-700' => $record->sourcing_selected_id !== $sourcing->id,
            ])>
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $sourcing->supplier_name }}
                        @if ($record->sourcing_selected_id === $sourcing->id)
                            <span class="ml-1 text-xs font-medium text-primary-600 dark:text-primary-400">(Terpilih)</span>
                        @endif
                    </p>
                    <p class="whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                        Rp{{ number_format((float) $sourcing->price, 0, ',', '.') }}
                    </p>
                </div>

                <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-4">
                    <div><dt class="text-gray-400">MOQ</dt><dd>{{ $sourcing->moq ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Lead Time</dt><dd>{{ $sourcing->lead_time_days ? $sourcing->lead_time_days.' hari' : '—' }}</dd></div>
                    <div><dt class="text-gray-400">Kontak</dt><dd>{{ $sourcing->contact_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Telepon</dt><dd>{{ $sourcing->contact_phone ?: '—' }}</dd></div>
                </dl>

                @if ($sourcing->notes)
                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $sourcing->notes }}</p>
                @endif

                @if ($sourcing->attachmentUrl())
                    <a href="{{ $sourcing->attachmentUrl() }}" target="_blank"
                        class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
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
