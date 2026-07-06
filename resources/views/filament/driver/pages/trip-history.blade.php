@php
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
@endphp

<div class="flex flex-col bg-emerald-600" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Driver\Pages\TripDashboard::getUrl() }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Riwayat Perjalanan</span>
        </div>

        <p class="text-emerald-200">{{ $monthNames[$this->reportMonth] }} {{ $this->reportYear }}</p>
        <p class="text-xl font-semibold text-white">{{ $this->periodStart->format('d M') }} — {{ $this->periodEnd->format('d M Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        {{-- Period navigator --}}
        <div class="mx-5 flex items-center justify-between gap-3">
            <button wire:click="previousPeriod"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white ring-1 ring-black/5 transition active:bg-gray-100 dark:bg-gray-900 dark:ring-white/10">
                <svg class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </button>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ $this->periodStart->format('d M') }} — {{ $this->periodEnd->format('d M Y') }}
            </p>
            <button wire:click="nextPeriod"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white ring-1 ring-black/5 transition active:bg-gray-100 dark:bg-gray-900 dark:ring-white/10">
                <svg class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </button>
        </div>

        {{-- Summary stats --}}
        <div class="mx-5 mt-4 grid grid-cols-2 gap-3">
            <div class="flex flex-col items-center gap-1.5 rounded-2xl bg-white py-4 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->trips->count() }}</p>
                <p class="text-xs text-gray-400">Perjalanan</p>
            </div>
            <div class="flex flex-col items-center gap-1.5 rounded-2xl bg-amber-50 py-4 dark:bg-amber-900/20">
                <p class="text-lg font-bold leading-tight text-amber-600 dark:text-amber-400">
                    {{ $this->totalFuelCost > 0 ? number_format($this->totalFuelCost / 1000, 0).'k' : '0' }}
                </p>
                <p class="px-1 text-center text-xs leading-tight text-gray-400">BBM</p>
            </div>
        </div>

        {{-- Download PDF --}}
        @if($this->trips->isNotEmpty())
            <div class="mx-5 mt-4">
                <button wire:click="downloadPdf"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-red-50 py-3 text-sm font-semibold text-red-600 ring-1 ring-red-100 transition active:scale-95 dark:bg-red-900/20 dark:ring-red-800/30">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    Download Laporan PDF
                </button>
            </div>
        @endif

        {{-- Trip list --}}
        <div class="mx-5 mt-4 space-y-3">
            @forelse($this->trips as $trip)
                <a href="{{ \App\Filament\Driver\Pages\TripDetail::getUrl(['trip' => $trip->id]) }}"
                   class="block overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 transition active:scale-[0.99] dark:bg-gray-900 dark:ring-white/10">
                    <div class="px-5 pt-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-gray-900 dark:text-white">{{ $trip->tripRoute->name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $trip->trip_date->format('d M Y') }} · {{ $trip->vehicle->license_plate ?? '-' }}
                                    @if($trip->completed_at)
                                        · Selesai {{ $trip->completed_at->format('H:i') }}
                                    @endif
                                </p>
                            </div>
                            <svg class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                            </svg>
                        </div>

                        {{-- Waypoints --}}
                        @if($trip->waypointCheckins->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach($trip->waypointCheckins->sortBy('waypoint.urutan') as $checkin)
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs
                                        {{ $checkin->checked_in_at ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                                        @if($checkin->checked_in_at)
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        @else
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        @endif
                                        {{ $checkin->waypoint->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- BBM --}}
                        @if($trip->has_fuel_fillup && $trip->fuelFillup)
                            <div class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                                ⛽ {{ $trip->fuelFillup->fuel_type }} · {{ $trip->fuelFillup->liters }}L · Rp {{ number_format($trip->fuelFillup->total_price, 0, ',', '.') }}
                            </div>
                        @endif
                    </div>
                    <div class="h-4"></div>
                </a>
            @empty
                <div class="flex flex-col items-center gap-3 rounded-2xl bg-white px-5 py-12 text-center ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <svg class="h-12 w-12 text-gray-200 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                    </svg>
                    <p class="text-sm font-medium text-gray-400">Tidak ada perjalanan di periode ini</p>
                </div>
            @endforelse
        </div>

    </div>

    <x-driver.bottom-nav active="history" />

</div>
