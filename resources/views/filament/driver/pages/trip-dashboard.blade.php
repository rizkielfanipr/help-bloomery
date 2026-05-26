<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Active Trip Banner --}}
        @if($this->activeTrip)
            <x-filament::section>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-info-500/10 text-info-500">
                            <x-heroicon-o-truck class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Perjalanan Aktif</p>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $this->activeTrip->tripRoute->name }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $this->activeTrip->vehicle->license_plate }} •
                                Mulai: {{ $this->activeTrip->started_at->format('H:i') }}
                            </p>
                        </div>
                    </div>
                    <x-filament::button
                        tag="a"
                        :href="$this->activeTrip ? \App\Filament\Driver\Pages\ActiveTrip::getUrl(['trip' => $this->activeTrip->id]) : '#'"
                        icon="heroicon-m-arrow-right"
                        icon-position="after"
                        color="info"
                    >
                        Lanjutkan
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-base font-medium text-gray-700 dark:text-gray-300">Tidak ada perjalanan aktif</p>
                        <p class="text-sm text-gray-500">Klik tombol untuk memulai perjalanan baru</p>
                    </div>
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Driver\Pages\StartTrip::getUrl()"
                        icon="heroicon-m-play"
                        color="success"
                    >
                        Mulai Perjalanan
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- Recent Trips --}}
        @if($this->recentTrips->isNotEmpty())
            <x-filament::section heading="5 Perjalanan Terakhir">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($this->recentTrips as $trip)
                        <div class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-success-500/10 text-success-500">
                                    <x-heroicon-m-check class="h-4 w-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $trip->tripRoute->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $trip->trip_date->format('d M Y') }} •
                                        {{ $trip->vehicle->license_plate ?? '-' }}
                                    </p>
                                </div>
                            </div>
                            @if($trip->meal_allowance_amount)
                                <span class="text-sm font-medium text-success-600">
                                    Rp {{ number_format($trip->meal_allowance_amount, 0, ',', '.') }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Driver\Pages\TripHistory::getUrl()"
                        color="gray"
                        outlined
                        size="sm"
                    >
                        Lihat Semua Riwayat
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
