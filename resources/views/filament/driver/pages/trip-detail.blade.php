@php
    use Illuminate\Support\Facades\Storage;
    $trip = $this->tripModel;
    $fuel = $trip->fuelFillup;

    $sectionClass = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900';
    $labelClass   = 'text-xs font-semibold text-slate-500 dark:text-slate-400';
    $valueClass   = 'mt-0.5 text-sm font-medium text-gray-900 dark:text-white';
@endphp

<div class="flex flex-col bg-emerald-600 dark:bg-emerald-900" style="min-height:100dvh">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Driver\Pages\TripHistory::getUrl() }}"
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
            <span class="text-base font-semibold text-white">Detail Perjalanan</span>
        </div>
        <p class="font-mono text-sm text-emerald-200">{{ $trip->code }}</p>
        <p class="text-xl font-semibold text-white">{{ $trip->tripRoute?->name ?? '—' }}</p>
    </div>

    {{-- CONTENT --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">
        <div class="flex flex-col gap-4 px-5">

            {{-- ── Info Umum ── --}}
            <div class="{{ $sectionClass }}">
                <p class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Info Perjalanan</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="{{ $labelClass }}">Tanggal</p>
                        <p class="{{ $valueClass }}">{{ $trip->trip_date->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="{{ $labelClass }}">Kendaraan</p>
                        <p class="{{ $valueClass }}">{{ $trip->vehicle?->license_plate ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="{{ $labelClass }}">Mulai</p>
                        <p class="{{ $valueClass }}">{{ $trip->started_at?->format('H:i') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="{{ $labelClass }}">Selesai</p>
                        <p class="{{ $valueClass }}">{{ $trip->completed_at?->format('H:i') ?? '—' }}</p>
                    </div>
                    @if($trip->meal_allowance_amount > 0)
                        <div class="col-span-2">
                            <p class="{{ $labelClass }}">Uang Makan</p>
                            <p class="{{ $valueClass }}">Rp {{ number_format($trip->meal_allowance_amount, 0, ',', '.') }}</p>
                        </div>
                    @endif
                    @if($trip->notes)
                        <div class="col-span-2">
                            <p class="{{ $labelClass }}">Catatan</p>
                            <p class="{{ $valueClass }}">{{ $trip->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── Odometer ── --}}
            <div class="{{ $sectionClass }}">
                <p class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Odometer</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="{{ $labelClass }}">Awal</p>
                        <p class="{{ $valueClass }} font-mono">{{ $trip->odo_start ? number_format($trip->odo_start).' km' : '—' }}</p>
                    </div>
                    <div>
                        <p class="{{ $labelClass }}">Akhir</p>
                        <p class="{{ $valueClass }} font-mono">{{ $trip->odo_end ? number_format($trip->odo_end).' km' : '—' }}</p>
                    </div>
                    @if($trip->odo_start && $trip->odo_end)
                        <div class="col-span-2">
                            <p class="{{ $labelClass }}">Jarak Tempuh</p>
                            <p class="{{ $valueClass }} font-mono">{{ number_format($trip->odo_end - $trip->odo_start) }} km</p>
                        </div>
                    @endif
                </div>
                @if($trip->odo_start_photo || $trip->odo_end_photo)
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        @if($trip->odo_start_photo)
                            <div>
                                <p class="{{ $labelClass }} mb-1.5">Foto Odo Awal</p>
                                <a href="{{ Storage::disk('b2')->temporaryUrl($trip->odo_start_photo, now()->addHour()) }}" target="_blank">
                                    <img src="{{ Storage::disk('b2')->temporaryUrl($trip->odo_start_photo, now()->addHour()) }}"
                                         class="h-28 w-full rounded-xl object-cover ring-1 ring-black/10">
                                </a>
                            </div>
                        @endif
                        @if($trip->odo_end_photo)
                            <div>
                                <p class="{{ $labelClass }} mb-1.5">Foto Odo Akhir</p>
                                <a href="{{ Storage::disk('b2')->temporaryUrl($trip->odo_end_photo, now()->addHour()) }}" target="_blank">
                                    <img src="{{ Storage::disk('b2')->temporaryUrl($trip->odo_end_photo, now()->addHour()) }}"
                                         class="h-28 w-full rounded-xl object-cover ring-1 ring-black/10">
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ── Titik Perjalanan ── --}}
            @if($trip->waypointCheckins->isNotEmpty())
                <div class="{{ $sectionClass }}">
                    <p class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Titik Perjalanan</p>
                    <div class="flex flex-col gap-3">
                        @foreach($trip->waypointCheckins->sortBy('waypoint.urutan') as $checkin)
                            <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        @if($checkin->checked_in_at)
                                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                                                <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                                </svg>
                                            </div>
                                        @else
                                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700">
                                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                                </svg>
                                            </div>
                                        @endif
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $checkin->waypoint?->name ?? '—' }}</p>
                                    </div>
                                    @if($checkin->checked_in_at)
                                        <p class="text-xs text-gray-400">{{ $checkin->checked_in_at->format('H:i') }}</p>
                                    @endif
                                </div>
                                @if($checkin->attachment_path)
                                    <a href="{{ Storage::disk('b2')->temporaryUrl($checkin->attachment_path, now()->addHour()) }}" target="_blank" class="mt-2 block">
                                        <img src="{{ Storage::disk('b2')->temporaryUrl($checkin->attachment_path, now()->addHour()) }}"
                                             class="h-32 w-full rounded-xl object-cover ring-1 ring-black/10">
                                    </a>
                                @endif
                                @if($checkin->notes)
                                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $checkin->notes }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ── Pengisian BBM ── --}}
            @if($trip->has_fuel_fillup && $fuel)
                <div class="{{ $sectionClass }}">
                    <p class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Pengisian BBM</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <p class="{{ $labelClass }}">Alamat SPBU</p>
                            <p class="{{ $valueClass }}">{{ $fuel->spbu_address }}</p>
                        </div>
                        <div>
                            <p class="{{ $labelClass }}">Jenis BBM</p>
                            <p class="{{ $valueClass }}">{{ $fuel->fuel_type }}</p>
                        </div>
                        <div>
                            <p class="{{ $labelClass }}">Jumlah</p>
                            <p class="{{ $valueClass }} font-mono">{{ $fuel->liters }} L</p>
                        </div>
                        @if($fuel->price_per_liter)
                            <div>
                                <p class="{{ $labelClass }}">Harga/Liter</p>
                                <p class="{{ $valueClass }}">Rp {{ number_format($fuel->price_per_liter, 0, ',', '.') }}</p>
                            </div>
                        @endif
                        <div>
                            <p class="{{ $labelClass }}">Total Biaya</p>
                            <p class="{{ $valueClass }} font-semibold text-amber-600">Rp {{ number_format($fuel->total_price, 0, ',', '.') }}</p>
                        </div>
                    </div>
                    @if($fuel->attachment_path || $fuel->fuel_indicator_photo)
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            @if($fuel->attachment_path)
                                <div>
                                    <p class="{{ $labelClass }} mb-1.5">Foto Nota</p>
                                    <a href="{{ Storage::disk('b2')->temporaryUrl($fuel->attachment_path, now()->addHour()) }}" target="_blank">
                                        <img src="{{ Storage::disk('b2')->temporaryUrl($fuel->attachment_path, now()->addHour()) }}"
                                             class="h-28 w-full rounded-xl object-cover ring-1 ring-black/10">
                                    </a>
                                </div>
                            @endif
                            @if($fuel->fuel_indicator_photo)
                                <div>
                                    <p class="{{ $labelClass }} mb-1.5">Foto Indikator</p>
                                    <a href="{{ Storage::disk('b2')->temporaryUrl($fuel->fuel_indicator_photo, now()->addHour()) }}" target="_blank">
                                        <img src="{{ Storage::disk('b2')->temporaryUrl($fuel->fuel_indicator_photo, now()->addHour()) }}"
                                             class="h-28 w-full rounded-xl object-cover ring-1 ring-black/10">
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>

</div>
