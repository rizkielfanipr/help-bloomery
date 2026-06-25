<div>
@php
    $trip      = $this->tripModel;
    $route     = $trip->tripRoute;
    $waypoints = $route->waypoints;
    $checkins  = $trip->waypointCheckins->keyBy('trip_route_waypoint_id');
    $requiresAttachment = $route->requires_waypoint_attachment;
    $completedCount = $checkins->filter(fn($c) => $c->checked_in_at)->count();
    $totalCount     = $waypoints->count();
    $progress       = $totalCount > 0 ? round($completedCount / $totalCount * 100) : 0;
@endphp

<div class="flex flex-col bg-emerald-600" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-6 pt-14">
        <div class="mb-3 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\TripDashboard::getUrl() }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-emerald-200">Perjalanan Aktif</p>
                <p class="truncate text-base font-semibold text-white">{{ $route->name }}</p>
            </div>
            <div class="shrink-0 text-right">
                <span class="text-2xl font-bold text-white">{{ $completedCount }}/{{ $totalCount }}</span>
                <p class="text-[10px] text-emerald-200">titik</p>
            </div>
        </div>

        <p class="mb-2 text-xs text-emerald-200">{{ $trip->vehicle->license_plate }} · Mulai {{ $trip->started_at->format('H:i') }}</p>

        {{-- Progress bar --}}
        <div class="h-1.5 overflow-hidden rounded-full bg-white/20">
            <div class="h-1.5 rounded-full bg-white transition-all duration-500" style="width:{{ $progress }}%"></div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-10 pt-6 dark:bg-gray-950">

        {{-- Waypoints timeline --}}
        <div class="mx-5">
            <p class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">Titik Perjalanan</p>

            <div class="relative">
                {{-- Vertical line --}}
                <div class="absolute bottom-4 left-4 top-4 w-px bg-gray-200 dark:bg-gray-700"></div>

                <div class="space-y-3">
                    @foreach($waypoints as $waypoint)
                        @php
                            $checkin     = $checkins[$waypoint->id] ?? null;
                            $isDone      = $checkin && $checkin->checked_in_at;
                            $hasAttach   = $checkin && $checkin->attachment_path;
                            $isReady     = $isDone && (!$requiresAttachment || $hasAttach);
                        @endphp

                        <div class="flex gap-4">
                            {{-- Dot --}}
                            <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-gray-50 dark:ring-gray-950
                                {{ $isReady ? 'bg-emerald-500' : ($isDone ? 'bg-amber-400' : 'bg-gray-200 dark:bg-gray-700') }}">
                                @if($isReady)
                                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                @elseif($isDone)
                                    <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                                @else
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $waypoint->urutan }}</span>
                                @endif
                            </div>

                            {{-- Content --}}
                            <div class="min-w-0 flex-1 overflow-hidden rounded-2xl bg-white pb-4 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                                <div class="flex items-start justify-between gap-2 px-4 pt-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $waypoint->urutan }}. {{ $waypoint->name }}
                                        </p>
                                        @if($waypoint->description)
                                            <p class="mt-0.5 text-xs text-gray-400">{{ $waypoint->description }}</p>
                                        @endif
                                        @if($isDone)
                                            <p class="mt-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                ✓ Check-in: {{ $checkin->checked_in_at->format('H:i') }}
                                            </p>
                                            @if($hasAttach)
                                                <p class="text-xs text-blue-500">📎 Bukti terupload</p>
                                            @elseif($requiresAttachment)
                                                <p class="text-xs text-red-500">⚠ Belum upload bukti</p>
                                            @endif
                                            @if($checkin->notes)
                                                <p class="mt-0.5 text-xs italic text-gray-400">{{ $checkin->notes }}</p>
                                            @endif
                                        @else
                                            <p class="mt-1 text-xs text-gray-400">Belum check-in</p>
                                        @endif
                                    </div>

                                    @if($checkin)
                                        <button wire:click="openCheckinModal({{ $checkin->id }})"
                                                class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition
                                                    {{ $isReady ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' : 'bg-emerald-600 text-white active:scale-95' }}">
                                            {{ $isReady ? 'Edit' : 'Check-in' }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Complete trip button --}}
        <div class="mx-5 mt-6">
            @if(!$trip->allWaypointsCompleted())
                <p class="mb-3 text-center text-xs text-gray-400">
                    Selesaikan semua titik{{ $requiresAttachment ? ' beserta upload bukti' : '' }} untuk mengakhiri perjalanan.
                </p>
            @endif
            <button wire:click="openFuelModal"
                    @disabled(!$trip->allWaypointsCompleted())
                    class="flex w-full items-center justify-center gap-2 rounded-2xl py-4 text-sm font-bold text-white transition
                        {{ $trip->allWaypointsCompleted() ? 'bg-emerald-600 active:scale-95 active:bg-emerald-700' : 'cursor-not-allowed bg-gray-200 text-gray-400 dark:bg-gray-800 dark:text-gray-600' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5"/>
                </svg>
                Selesaikan Perjalanan
            </button>
        </div>

    </div>

</div>

{{-- ══ CHECKIN MODAL ══ --}}
<x-filament::modal id="checkin-modal" width="lg">
    <x-slot name="heading">Check-in Titik Perjalanan</x-slot>
    <div wire:key="checkin-form-{{ $this->checkinModalKey }}">
        {{ $this->checkinForm }}
    </div>
    <x-slot name="footerActions">
        <x-filament::button wire:click="saveCheckin" color="success" icon="heroicon-m-check">Simpan</x-filament::button>
        <x-filament::button color="gray" outlined wire:click="cancelCheckinModal">Batal</x-filament::button>
    </x-slot>
</x-filament::modal>

{{-- ══ FUEL MODAL ══ --}}
<x-filament::modal id="fuel-modal" width="2xl">
    <x-slot name="heading">Pengisian BBM</x-slot>
    <x-slot name="description">Apakah ada pengisian BBM dalam perjalanan ini?</x-slot>
    {{ $this->fuelForm }}
    <x-slot name="footerActions">
        <x-filament::button wire:click="submitFuel" color="success" icon="heroicon-m-flag">Selesaikan Perjalanan</x-filament::button>
        <x-filament::button color="gray" outlined wire:click="cancelFuelModal">Batal</x-filament::button>
    </x-slot>
</x-filament::modal>
</div>
