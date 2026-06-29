@php $user = auth()->user(); @endphp

<div class="flex flex-col bg-blue-600" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Logbook Driver</span>
        </div>

        <p class="text-blue-200">{{ $user->name }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        {{-- Active trip --}}
        @if($this->activeTrip)
            @php $trip = $this->activeTrip; @endphp
            <div class="mx-5">
                <p class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">Perjalanan Aktif</p>
                <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center gap-3 px-5 pt-5">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-gray-900 dark:text-white">{{ $trip->tripRoute->name }}</p>
                            <p class="text-xs text-gray-500">{{ $trip->vehicle->license_plate }} · Mulai: {{ $trip->started_at->format('H:i') }}</p>
                        </div>
                        <span class="flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500"></span>
                            Aktif
                        </span>
                    </div>

                    <div class="mx-5 mt-4 border-t border-gray-100 dark:border-gray-800"></div>

                    <div class="px-5 py-4">
                        <a href="{{ \App\Filament\Casual\Pages\ActiveTrip::getUrl(['trip' => $trip->id]) }}"
                           class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                            </svg>
                            Lanjutkan Perjalanan
                        </a>
                    </div>
                </div>
            </div>
        @else
            <div class="mx-5">
                <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex flex-col items-center gap-4 px-5 py-10 text-center">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-900/20">
                            <svg class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">Tidak Ada Perjalanan Aktif</p>
                            <p class="mt-1 text-sm text-gray-400">Mulai perjalanan baru untuk mencatat logbook hari ini.</p>
                        </div>
                        <a href="{{ \App\Filament\Casual\Pages\StartTrip::getUrl() }}"
                           class="flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/>
                            </svg>
                            Mulai Perjalanan
                        </a>
                    </div>
                </div>
            </div>
        @endif

        {{-- Recent trips --}}
        @if($this->recentTrips->isNotEmpty())
            <div class="mx-5 mt-5">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">5 Perjalanan Terakhir</p>
                    <a href="{{ \App\Filament\Casual\Pages\TripHistory::getUrl() }}"
                       class="text-xs font-medium text-blue-600 dark:text-blue-400">Lihat Semua</a>
                </div>
                <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    @foreach($this->recentTrips as $index => $trip)
                        <div class="flex items-center gap-3 px-5 py-3.5 {{ $index > 0 ? 'border-t border-gray-100 dark:border-gray-800' : '' }}">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                                <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $trip->tripRoute->name }}</p>
                                <p class="text-xs text-gray-400">{{ $trip->trip_date->format('d M Y') }} · {{ $trip->vehicle->license_plate ?? '-' }}</p>
                            </div>
                            @if($trip->meal_allowance_amount)
                                <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                    Rp {{ number_format($trip->meal_allowance_amount, 0, ',', '.') }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    <x-driver.bottom-nav active="dashboard" />

</div>
