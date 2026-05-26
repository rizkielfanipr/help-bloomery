<x-filament-panels::page>
    @php
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    @endphp

    <div class="space-y-6">

        {{-- Period Navigator --}}
        <x-filament::section>
            <div class="flex items-center justify-between">
                <x-filament::button
                    wire:click="previousPeriod"
                    color="gray"
                    outlined
                    icon="heroicon-m-chevron-left"
                    size="sm"
                >
                    Sebelumnya
                </x-filament::button>

                <div class="text-center">
                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                        Periode: {{ $this->periodStart->format('d M Y') }} — {{ $this->periodEnd->format('d M Y') }}
                    </p>
                    <p class="text-xs text-gray-500">{{ $monthNames[$reportMonth] }} {{ $reportYear }}</p>
                </div>

                <x-filament::button
                    wire:click="nextPeriod"
                    color="gray"
                    outlined
                    icon="heroicon-m-chevron-right"
                    icon-position="after"
                    size="sm"
                >
                    Berikutnya
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->trips->count() }}</p>
                    <p class="text-sm text-gray-500">Total Perjalanan</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-success-600">
                        Rp {{ number_format($this->totalMealAllowance, 0, ',', '.') }}
                    </p>
                    <p class="text-sm text-gray-500">Total Uang Makan</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-info-600">
                        Rp {{ number_format($this->totalFuelCost, 0, ',', '.') }}
                    </p>
                    <p class="text-sm text-gray-500">Total BBM</p>
                </div>
            </x-filament::section>
        </div>

        {{-- Download PDF --}}
        @if($this->trips->isNotEmpty())
            <div class="flex justify-end">
                <x-filament::button
                    wire:click="downloadPdf"
                    color="danger"
                    icon="heroicon-m-document-arrow-down"
                >
                    Download Laporan PDF
                </x-filament::button>
            </div>
        @endif

        {{-- Trip List --}}
        @forelse($this->trips as $trip)
            <x-filament::section>
                <div class="space-y-3">
                    {{-- Trip Header --}}
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $trip->tripRoute->name }}</h3>
                            <p class="text-sm text-gray-500">
                                {{ $trip->trip_date->format('d M Y') }} •
                                {{ $trip->vehicle->license_plate ?? '-' }}
                            </p>
                            @if($trip->completed_at)
                                <p class="text-xs text-gray-400">
                                    Selesai: {{ $trip->completed_at->format('H:i') }}
                                </p>
                            @endif
                        </div>
                        @if($trip->meal_allowance_amount)
                            <span class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-800 dark:bg-success-900 dark:text-success-200">
                                Uang Makan: Rp {{ number_format($trip->meal_allowance_amount, 0, ',', '.') }}
                            </span>
                        @endif
                    </div>

                    {{-- Waypoints Summary --}}
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($trip->waypointCheckins->sortBy('waypoint.urutan') as $checkin)
                            <span class="inline-flex items-center gap-1 rounded-full
                                {{ $checkin->checked_in_at ? 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}
                                px-2 py-0.5 text-xs">
                                @if($checkin->checked_in_at)
                                    <x-heroicon-m-check class="h-3 w-3" />
                                @else
                                    <x-heroicon-m-clock class="h-3 w-3" />
                                @endif
                                {{ $checkin->waypoint->name }}
                            </span>
                        @endforeach
                    </div>

                    {{-- BBM Info --}}
                    @if($trip->has_fuel_fillup && $trip->fuelFillup)
                        <div class="rounded-md bg-amber-50 p-2 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                            ⛽ BBM: {{ $trip->fuelFillup->fuel_type }}
                            {{ $trip->fuelFillup->liters }} L @
                            Rp {{ number_format($trip->fuelFillup->price_per_liter, 0, ',', '.') }}/L =
                            <strong>Rp {{ number_format($trip->fuelFillup->total_price, 0, ',', '.') }}</strong>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="py-8 text-center text-gray-500">
                    <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-300" />
                    <p class="mt-2 text-sm">Tidak ada perjalanan di periode ini</p>
                </div>
            </x-filament::section>
        @endforelse

    </div>
</x-filament-panels::page>
