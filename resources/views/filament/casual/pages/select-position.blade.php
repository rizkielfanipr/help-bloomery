<x-filament-panels::page>
    <div class="mx-auto max-w-2xl space-y-6">

        <x-filament::section>
            <div class="text-center">
                <x-heroicon-o-briefcase class="mx-auto h-12 w-12 text-primary-500" />
                <h2 class="mt-3 text-xl font-bold text-gray-900 dark:text-white">Pilih Posisi Anda</h2>
                <p class="mt-1 text-sm text-gray-500">Pilih posisi yang sesuai dengan penugasan Anda</p>
            </div>
        </x-filament::section>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @forelse($this->getPositions() as $position)
                <button
                    wire:click="selectPosition({{ $position->id }})"
                    wire:loading.attr="disabled"
                    class="group rounded-xl border-2 border-gray-200 bg-white p-5 text-left shadow-sm transition-all hover:border-primary-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-400"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                {{ $position->name }}
                            </h3>
                            @if($position->description)
                                <p class="mt-1 text-xs text-gray-500">{{ $position->description }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-900/30 dark:text-success-400">
                            Rp {{ number_format($position->fee_per_day, 0, ',', '.') }}/hari
                        </span>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <x-filament::icon icon="heroicon-m-arrow-right" class="h-5 w-5 text-gray-300 group-hover:text-primary-500 transition-colors" />
                    </div>
                </button>
            @empty
                <div class="col-span-2 rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-500">
                    <x-heroicon-o-exclamation-circle class="mx-auto h-10 w-10 text-gray-300" />
                    <p class="mt-2 text-sm">Belum ada posisi yang tersedia. Hubungi HR Admin.</p>
                </div>
            @endforelse
        </div>

    </div>
</x-filament-panels::page>
