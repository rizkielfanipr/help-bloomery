<x-filament-panels::page>
@php
    $status = $record->status;
    $checkins = $record->waypointCheckins->keyBy('trip_route_waypoint_id');
    $distance = $record->odo_start !== null && $record->odo_end !== null ? max(0, $record->odo_end - $record->odo_start) : null;
@endphp
<x-helpdesk.desktop-detail-shell
    :code="$record->code"
    :title="$record->tripRoute?->name ?? 'Perjalanan Driver'"
    :subtitle="($record->driver?->name ?? 'No Driver').' · '.($record->vehicle?->license_plate ?? 'No Vehicle')"
    :status="$status->getLabel()"
    :status-color="$status->getColor()"
    meta-label="Tanggal Perjalanan"
    :meta-value="$record->trip_date?->format('d M Y') ?? '-'"
>
    <x-slot:main>
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="mb-5 font-bold">Detail Perjalanan</h2>
            <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs text-gray-400">Driver</dt><dd class="mt-1 font-semibold">{{ $record->driver?->name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Kendaraan</dt><dd class="mt-1 font-semibold">{{ $record->vehicle?->brand }} {{ $record->vehicle?->model }} · {{ $record->vehicle?->license_plate }}</dd></div>
                <div><dt class="text-xs text-gray-400">Mulai</dt><dd class="mt-1 font-semibold">{{ $record->started_at?->format('d M Y, H:i') ?? '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Selesai</dt><dd class="mt-1 font-semibold">{{ $record->completed_at?->format('d M Y, H:i') ?? '-' }}</dd></div>
            </dl>
            @if($record->notes)<p class="mt-5 border-t border-gray-100 pt-5 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">{{ $record->notes }}</p>@endif
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="font-bold">Titik Perjalanan</h2>
                <span class="text-xs text-gray-400">{{ $record->waypointCheckins->whereNotNull('checked_in_at')->count() }}/{{ $record->tripRoute?->waypoints->count() ?? 0 }} selesai</span>
            </div>
            <div class="space-y-3">
                @forelse($record->tripRoute?->waypoints ?? [] as $index => $waypoint)
                    @php $checkin = $checkins->get($waypoint->id); @endphp
                    <div class="flex gap-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-800/60">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $checkin?->checked_in_at ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">{{ $index + 1 }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap justify-between gap-2">
                                <p class="font-semibold">{{ $waypoint->name }}</p>
                                <span class="text-xs {{ $checkin?->checked_in_at ? 'text-emerald-600' : 'text-gray-400' }}">{{ $checkin?->checked_in_at?->format('d M Y, H:i') ?? 'Belum check-in' }}</span>
                            </div>
                            @if($checkin?->attachment_path)
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($checkin->attachment_path, now()->addHour()) }}" target="_blank" class="mt-2 inline-flex text-xs font-medium text-blue-600">Lihat bukti ↗</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Rute belum memiliki waypoint.</p>
                @endforelse
            </div>
        </section>

        @if($record->fuelFillup)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <h2 class="mb-5 font-bold">Pengisian BBM</h2>
                <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-xs text-gray-400">SPBU</dt><dd class="mt-1 font-semibold">{{ $record->fuelFillup->spbu_address }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Jenis BBM</dt><dd class="mt-1 font-semibold">{{ $record->fuelFillup->fuel_type }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Volume</dt><dd class="mt-1 font-semibold">{{ number_format((float) $record->fuelFillup->liters, 2, ',', '.') }} L</dd></div>
                    <div><dt class="text-xs text-gray-400">Total</dt><dd class="mt-1 font-semibold">Rp {{ number_format((float) $record->fuelFillup->total_price, 0, ',', '.') }}</dd></div>
                </dl>
            </section>
        @endif

        @if($record->odo_start_photo || $record->odo_end_photo || $record->fuelFillup?->attachment_path || $record->fuelFillup?->fuel_indicator_photo)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <h2 class="mb-4 font-bold">Dokumentasi Perjalanan</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([
                        $record->odo_start_photo => 'Odometer Awal',
                        $record->odo_end_photo => 'Odometer Akhir',
                        $record->fuelFillup?->attachment_path => 'Nota BBM',
                        $record->fuelFillup?->fuel_indicator_photo => 'Indikator BBM',
                    ] as $path => $label)
                        @if($path)
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" class="h-40 w-full object-cover">
                                <p class="p-2 text-center text-xs font-medium">{{ $label }}</p>
                            </a>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    </x-slot:main>

    <x-slot:aside>
        <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="font-bold">Ringkasan Operasional</h2>
            <div class="mt-5 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-blue-50 p-4 dark:bg-blue-950/30"><p class="text-xs text-blue-500">Odo Awal</p><p class="mt-1 text-lg font-bold">{{ $record->odo_start !== null ? number_format($record->odo_start).' km' : '-' }}</p></div>
                <div class="rounded-xl bg-emerald-50 p-4 dark:bg-emerald-950/30"><p class="text-xs text-emerald-500">Odo Akhir</p><p class="mt-1 text-lg font-bold">{{ $record->odo_end !== null ? number_format($record->odo_end).' km' : '-' }}</p></div>
                <div class="col-span-2 rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><p class="text-xs text-gray-400">Total Jarak</p><p class="mt-1 text-2xl font-bold">{{ $distance !== null ? number_format($distance).' km' : '-' }}</p></div>
            </div>
        </section>
        <section class="rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 p-6 text-white shadow-lg shadow-blue-900/15">
            <p class="text-xs text-blue-100">Biaya Perjalanan</p>
            <p class="mt-1 text-2xl font-bold">Rp {{ number_format((float) $record->meal_allowance_amount, 0, ',', '.') }}</p>
            <div class="mt-4 border-t border-white/20 pt-4 text-sm text-blue-50">
                <div class="flex justify-between"><span>Pengisian BBM</span><span>{{ $record->has_fuel_fillup ? 'Ada' : 'Tidak' }}</span></div>
                @if($record->fuelFillup)<div class="mt-2 flex justify-between"><span>Total BBM</span><span>Rp {{ number_format((float) $record->fuelFillup->total_price, 0, ',', '.') }}</span></div>@endif
            </div>
        </section>
    </x-slot:aside>
</x-helpdesk.desktop-detail-shell>
</x-filament-panels::page>
