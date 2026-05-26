<x-filament-panels::page>
    @php
        $user = auth()->user();
        $position = $user->casualPosition;
        $record = $this->anyTodayRecord;
        $hasClockedIn  = $record && $record->clock_in_at;
        $hasClockedOut = $record && $record->clock_out_at;
    @endphp

    {{-- Leaflet CSS --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="mx-auto max-w-xl space-y-4">

        {{-- Header Info --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <x-heroicon-o-user class="h-7 w-7 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $user->name }}</p>
                    <p class="text-sm text-gray-500">
                        {{ $position?->name ?? 'Belum memilih posisi' }}
                        @if($position)
                            · Rp {{ number_format($position->fee_per_day, 0, ',', '.') }}/hari
                        @endif
                    </p>
                </div>
                <div class="text-right" x-data="{ time: '' }" x-init="
                    function pad(n){ return n.toString().padStart(2,'0'); }
                    function tick(){ let d = new Date(); time = pad(d.getHours())+':'+pad(d.getMinutes())+':'+pad(d.getSeconds()); }
                    tick(); setInterval(tick, 1000);
                ">
                    <p class="text-2xl font-mono font-bold text-gray-900 dark:text-white" x-text="time"></p>
                    <p class="text-xs text-gray-500">{{ now()->isoFormat('dddd, D MMMM Y') }}</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Status today --}}
        @if($hasClockedIn)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-heroicon-m-check-circle class="h-6 w-6 text-success-500" />
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            Clock In: <strong>{{ $record->clock_in_at->format('H:i') }}</strong>
                            @if($record->is_late)
                                <span class="ml-2 inline-flex items-center rounded-full bg-warning-100 px-2 py-0.5 text-xs font-medium text-warning-700">
                                    Terlambat {{ $record->late_minutes }} menit
                                </span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">Shift: {{ $record->shift->name }} ({{ $record->shift->start_time }} – {{ $record->shift->end_time }})</p>
                    </div>
                </div>
                @if($hasClockedOut)
                    <div class="mt-2 flex items-center gap-3">
                        <x-heroicon-m-check-circle class="h-6 w-6 text-info-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                Clock Out: <strong>{{ $record->clock_out_at->format('H:i') }}</strong>
                                @if($record->is_early_out)
                                    <span class="ml-2 inline-flex items-center rounded-full bg-warning-100 px-2 py-0.5 text-xs font-medium text-warning-700">
                                        Lebih awal {{ $record->early_out_minutes }} menit
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- DONE --}}
        @if($hasClockedOut)
            <x-filament::section>
                <div class="py-4 text-center">
                    <x-heroicon-o-face-smile class="mx-auto h-14 w-14 text-success-400" />
                    <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">Absensi hari ini selesai!</p>
                    <p class="text-sm text-gray-500">Sampai jumpa besok.</p>
                </div>
            </x-filament::section>

        {{-- CLOCK OUT FORM --}}
        @elseif($hasClockedIn)
            <x-filament::section heading="Clock Out">
                <div
                    wire:key="clock-out-form-{{ $this->formKey }}"
                    x-data="locationCapture(@entangle('latitude'), @entangle('longitude'))"
                >
                    {{ $this->clockOutForm }}

                    @include('filament.casual.pages.partials.location-capture', ['shift' => $record->shift])

                    <div class="mt-4">
                        <x-filament::button
                            wire:click="clockOut"
                            wire:loading.attr="disabled"
                            color="danger"
                            icon="heroicon-m-arrow-right-start-on-rectangle"
                            size="lg"
                            class="w-full"
                        >
                            Clock Out Sekarang
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

        {{-- CLOCK IN FORM --}}
        @else
            <x-filament::section heading="Clock In">
                @if($this->activeShifts->isEmpty())
                    <div class="py-6 text-center text-gray-500">
                        <x-heroicon-o-clock class="mx-auto h-10 w-10 text-gray-300" />
                        <p class="mt-2 text-sm">Tidak ada shift aktif saat ini. Hubungi HR Admin.</p>
                    </div>
                @else
                    <div
                        wire:key="clock-in-form-{{ $this->formKey }}"
                        x-data="locationCapture(@entangle('latitude'), @entangle('longitude'))"
                    >
                        {{ $this->clockInForm }}

                        @include('filament.casual.pages.partials.location-capture')

                        <div class="mt-4">
                            <x-filament::button
                                wire:click="clockIn"
                                wire:loading.attr="disabled"
                                color="success"
                                icon="heroicon-m-arrow-right-end-on-rectangle"
                                size="lg"
                                class="w-full"
                            >
                                Clock In Sekarang
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endif

    </div>

    {{-- Leaflet JS + Alpine component --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    function locationCapture(latModel, lngModel) {
        return {
            lat: latModel,
            lng: lngModel,
            loading: false,
            error: null,
            map: null,
            userMarker: null,

            get captured() { return this.lat !== null && this.lng !== null; },

            capture() {
                this.loading = true;
                this.error = null;
                if (!navigator.geolocation) {
                    this.error = 'Browser tidak mendukung geolokasi.';
                    this.loading = false;
                    return;
                }
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.lat = pos.coords.latitude;
                        this.lng = pos.coords.longitude;
                        this.loading = false;
                        this.$nextTick(() => this.initMap());
                    },
                    (err) => {
                        this.error = 'Gagal mendapatkan lokasi: ' + err.message;
                        this.loading = false;
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            },

            initMap() {
                if (!this.lat || !this.lng) return;
                const mapEl = this.$refs.map;
                if (!mapEl) return;
                if (this.map) { this.map.remove(); this.map = null; }

                this.map = L.map(mapEl).setView([this.lat, this.lng], 17);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(this.map);

                this.userMarker = L.marker([this.lat, this.lng])
                    .bindPopup('Lokasi Anda')
                    .addTo(this.map)
                    .openPopup();

                // Shift location marker & radius (passed via data attribute)
                const shiftLat = parseFloat(mapEl.dataset.shiftLat || '');
                const shiftLng = parseFloat(mapEl.dataset.shiftLng || '');
                const radius   = parseInt(mapEl.dataset.radius || '0');

                if (!isNaN(shiftLat) && !isNaN(shiftLng) && radius > 0) {
                    L.marker([shiftLat, shiftLng], {
                        icon: L.divIcon({ className: 'bg-green-500', html: '<div style="background:#22c55e;width:12px;height:12px;border-radius:50%;border:2px solid white;"></div>' })
                    }).bindPopup('Lokasi Shift').addTo(this.map);

                    L.circle([shiftLat, shiftLng], { radius, color: '#22c55e', fillOpacity: 0.1 }).addTo(this.map);
                }
            }
        };
    }
    </script>
</x-filament-panels::page>
