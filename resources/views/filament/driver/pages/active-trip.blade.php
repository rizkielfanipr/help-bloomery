<x-filament-panels::page>
    @php
        $trip = $this->tripModel;
        $route = $trip->tripRoute;
        $waypoints = $route->waypoints;
        $checkins = $trip->waypointCheckins->keyBy('trip_route_waypoint_id');
        $requiresAttachment = $route->requires_waypoint_attachment;
    @endphp

    <div class="space-y-4">

        {{-- Trip Header --}}
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $route->name }}</h2>
                    <p class="text-sm text-gray-500">
                        {{ $trip->vehicle->license_plate }} •
                        {{ $trip->trip_date->format('d M Y') }} •
                        Mulai: {{ $trip->started_at->format('H:i') }}
                    </p>
                    @if($trip->meal_allowance_amount)
                        <p class="mt-1 text-sm text-success-600 font-medium">
                            Uang makan: Rp {{ number_format($trip->meal_allowance_amount, 0, ',', '.') }}
                        </p>
                    @endif
                </div>
                @php
                    $completedCount = $checkins->filter(fn($c) => $c->checked_in_at)->count();
                    $totalCount = $waypoints->count();
                    $allDone = $trip->allWaypointsCompleted();
                @endphp
                <div class="text-right">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $completedCount }}/{{ $totalCount }}</span>
                    <p class="text-xs text-gray-500">titik selesai</p>
                </div>
            </div>

            {{-- Progress bar --}}
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    class="h-2 rounded-full bg-primary-500 transition-all duration-500"
                    style="width: {{ $totalCount > 0 ? ($completedCount / $totalCount * 100) : 0 }}%"
                ></div>
            </div>
        </x-filament::section>

        {{-- Waypoints Timeline --}}
        <x-filament::section heading="Titik Perjalanan">
            <ol class="relative border-l border-gray-200 dark:border-gray-700">
                @foreach($waypoints as $index => $waypoint)
                    @php
                        $checkin = $checkins[$waypoint->id] ?? null;
                        $isDone = $checkin && $checkin->checked_in_at;
                        $hasAttachment = $checkin && $checkin->attachment_path;
                        $isReady = $isDone && (!$requiresAttachment || $hasAttachment);
                    @endphp
                    <li class="mb-6 ml-6">
                        {{-- Dot --}}
                        <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-8 ring-white dark:ring-gray-900
                            {{ $isReady ? 'bg-success-500' : ($isDone ? 'bg-warning-400' : 'bg-gray-200 dark:bg-gray-700') }}">
                            @if($isReady)
                                <x-heroicon-m-check class="h-3.5 w-3.5 text-white" />
                            @elseif($isDone)
                                <x-heroicon-m-paper-clip class="h-3.5 w-3.5 text-white" />
                            @else
                                <span class="text-xs font-bold text-gray-500">{{ $waypoint->urutan }}</span>
                            @endif
                        </span>

                        <div class="rounded-lg border bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $waypoint->urutan }}. {{ $waypoint->name }}
                                    </h3>
                                    @if($waypoint->description)
                                        <p class="text-xs text-gray-500">{{ $waypoint->description }}</p>
                                    @endif

                                    @if($isDone)
                                        <p class="mt-1 text-xs text-success-600">
                                            ✓ Check-in: {{ $checkin->checked_in_at->format('H:i') }}
                                        </p>
                                        @if($hasAttachment)
                                            <p class="text-xs text-info-600">📎 Bukti terupload</p>
                                        @elseif($requiresAttachment)
                                            <p class="text-xs text-danger-500">⚠ Belum upload bukti</p>
                                        @endif
                                        @if($checkin->notes)
                                            <p class="text-xs text-gray-500 italic">{{ $checkin->notes }}</p>
                                        @endif
                                    @else
                                        <p class="mt-1 text-xs text-gray-400">Belum check-in</p>
                                    @endif
                                </div>

                                {{-- Action button --}}
                                @if($checkin)
                                    <x-filament::button
                                        wire:click="openCheckinModal({{ $checkin->id }})"
                                        size="xs"
                                        :color="$isReady ? 'gray' : 'primary'"
                                        outlined
                                    >
                                        {{ $isReady ? 'Edit' : 'Check-in' }}
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-filament::section>

        {{-- Selesai Button --}}
        <div class="flex justify-end">
            <x-filament::button
                wire:click="openFuelModal"
                :disabled="!$trip->allWaypointsCompleted()"
                color="success"
                icon="heroicon-m-flag"
                size="lg"
            >
                Selesaikan Perjalanan
            </x-filament::button>
        </div>

        @if(!$trip->allWaypointsCompleted())
            <p class="text-center text-xs text-gray-500">
                Selesaikan semua titik perjalanan untuk dapat mengakhiri perjalanan
                @if($requiresAttachment)
                    (termasuk upload bukti di setiap titik)
                @endif
            </p>
        @endif

    </div>

    {{-- ===== MODAL CHECK-IN ===== --}}
    <x-filament::modal
        id="checkin-modal"
        width="lg"
    >
        <x-slot name="heading">Check-in Titik Perjalanan</x-slot>

        <div wire:key="checkin-form-{{ $this->checkinModalKey }}">
            {{ $this->checkinForm }}
        </div>

        <x-slot name="footerActions">
            <x-filament::button wire:click="saveCheckin" icon="heroicon-m-check">
                Simpan Check-in
            </x-filament::button>
            <x-filament::button
                color="gray"
                outlined
                wire:click="cancelCheckinModal"
            >
                Batal
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- ===== MODAL BBM ===== --}}
    <x-filament::modal
        id="fuel-modal"
        width="2xl"
    >
        <x-slot name="heading">Pengisian BBM</x-slot>
        <x-slot name="description">
            Apakah ada pengisian BBM dalam perjalanan ini?
        </x-slot>

        {{ $this->fuelForm }}

        <x-slot name="footerActions">
            <x-filament::button wire:click="submitFuel" color="success" icon="heroicon-m-flag">
                Selesaikan Perjalanan
            </x-filament::button>
            <x-filament::button
                color="gray"
                outlined
                wire:click="cancelFuelModal"
            >
                Batal
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

</x-filament-panels::page>
