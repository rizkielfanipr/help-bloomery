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
            <livewire:material-sourcing-supplier-card :sourcing="$sourcing" :key="'sourcing-supplier-'.$sourcing->id" />
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
