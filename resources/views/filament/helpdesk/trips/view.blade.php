<x-filament-panels::page>
@php
    $status = $record->status;
    $checkins = $record->waypointCheckins->keyBy('trip_route_waypoint_id');
    $waypoints = $record->tripRoute?->waypoints ?? collect();
    $completedWaypoints = $record->waypointCheckins->whereNotNull('checked_in_at')->count();
    $distance = $record->odo_start !== null && $record->odo_end !== null
        ? max(0, $record->odo_end - $record->odo_start)
        : null;
    $vehicleName = collect([$record->vehicle?->brand, $record->vehicle?->model])->filter()->join(' ');
    $documents = collect([
        ['path' => $record->odo_start_photo, 'label' => 'Odometer Awal'],
        ['path' => $record->odo_end_photo, 'label' => 'Odometer Akhir'],
        ['path' => $record->fuelFillup?->attachment_path, 'label' => 'Nota BBM'],
        ['path' => $record->fuelFillup?->fuel_indicator_photo, 'label' => 'Indikator BBM'],
    ])->filter(fn (array $document) => filled($document['path']));
@endphp

<div class="mx-auto w-full max-w-6xl space-y-5">
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $record->code }}</p>
                <h1 class="mt-1 truncate text-xl font-semibold text-gray-900 dark:text-white">{{ $record->tripRoute?->name ?? 'Perjalanan Driver' }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $record->driver?->name ?? 'No Driver' }} · {{ $record->vehicle?->license_plate ?? 'No Vehicle' }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                <span @class([
                    'rounded-md px-2.5 py-1 text-xs font-semibold',
                    'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' => $status === \App\Enums\TripStatus::Pending,
                    'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' => $status === \App\Enums\TripStatus::InProgress,
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $status === \App\Enums\TripStatus::Completed,
                ])>{{ $status->getLabel() }}</span>
                <span class="text-xs text-gray-400">{{ $record->trip_date?->format('d M Y') ?? '-' }}</span>
            </div>
        </div>

        <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Driver</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->driver?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Kendaraan</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $vehicleName ?: '-' }}{{ $record->vehicle?->license_plate ? ' · '.$record->vehicle->license_plate : '' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Mulai</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->started_at?->format('d M Y, H:i') ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Selesai</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->completed_at?->format('d M Y, H:i') ?? '-' }}</p></div>
        </div>

        @if($record->notes)
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Catatan Perjalanan</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->notes }}</p>
            </div>
        @endif

        <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Ringkasan Operasional</h2>
                <span class="text-xs text-gray-400">Terakhir diperbarui {{ $record->updated_at->format('d M Y, H:i') }}</span>
            </div>
            <dl class="mt-4 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Odometer Awal</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->odo_start !== null ? number_format($record->odo_start).' km' : '-' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Odometer Akhir</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->odo_end !== null ? number_format($record->odo_end).' km' : '-' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Total Jarak</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $distance !== null ? number_format($distance).' km' : '-' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Biaya Perjalanan</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">Rp {{ number_format((float) $record->meal_allowance_amount, 0, ',', '.') }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Pengisian BBM</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->has_fuel_fillup ? 'Ada' : 'Tidak' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Total BBM</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->fuelFillup ? 'Rp '.number_format((float) $record->fuelFillup->total_price, 0, ',', '.') : '-' }}</dd></div>
            </dl>
        </div>

        <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Titik Perjalanan</h2>
                <span class="text-xs font-medium text-gray-500">{{ $completedWaypoints }}/{{ $waypoints->count() }} selesai</span>
            </div>
            <div class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                @forelse($waypoints as $index => $waypoint)
                    @php $checkin = $checkins->get($waypoint->id); @endphp
                    <div class="flex gap-4 px-4 py-4">
                        <div @class([
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                            'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300' => $checkin?->checked_in_at,
                            'border-gray-200 bg-gray-50 text-gray-500 dark:border-gray-700 dark:bg-gray-800' => ! $checkin?->checked_in_at,
                        ])>{{ $index + 1 }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $waypoint->name }}</p>
                                <span class="text-xs text-gray-500">{{ $checkin?->checked_in_at?->format('d M Y, H:i') ?? 'Belum check-in' }}</span>
                            </div>
                            @if($checkin?->attachment_path)
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($checkin->attachment_path, now()->addHour()) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400">Lihat bukti <span aria-hidden="true">↗</span></a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-gray-400">Rute belum memiliki waypoint.</p>
                @endforelse
            </div>
        </div>

        @if($record->fuelFillup)
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Pengisian BBM</h2>
                <dl class="mt-4 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">SPBU</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->fuelFillup->spbu_address ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Jenis BBM</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->fuelFillup->fuel_type ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Volume</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ number_format((float) $record->fuelFillup->liters, 2, ',', '.') }} L</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Total</dt><dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">Rp {{ number_format((float) $record->fuelFillup->total_price, 0, ',', '.') }}</dd></div>
                </dl>
            </div>
        @endif
    </section>

    @if($documents->isNotEmpty())
        <section class="rounded-xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Dokumentasi Perjalanan</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach($documents as $document)
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($document['path'], now()->addHour()) }}" target="_blank" rel="noopener" class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($document['path'], now()->addHour()) }}" class="aspect-[4/3] w-full object-cover" alt="{{ $document['label'] }}">
                        <p class="border-t border-gray-200 px-3 py-2 text-center text-xs font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $document['label'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
</x-filament-panels::page>
