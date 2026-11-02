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
        <dl class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 p-3 text-sm sm:grid-cols-2 lg:grid-cols-5 dark:border-gray-700">
            @foreach ([
                'Status Sourcing' => $record->sourcing_status->getLabel(),
                'Asal Sourcing' => $record->isStandalone() ? 'Mandiri' : 'Project',
                'Masuk Sourcing' => \App\Services\Rnd\Material\MaterialSourcingTimeline::startedLabel($record),
                'Selesai Sourcing' => \App\Services\Rnd\Material\MaterialSourcingTimeline::completedLabel($record),
                'Durasi Sourcing' => \App\Services\Rnd\Material\MaterialSourcingTimeline::durationLabel($record),
            ] as $label => $value)
                <div class="min-w-0">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-0.5 break-words font-semibold text-gray-900 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

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
