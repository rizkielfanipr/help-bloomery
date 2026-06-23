@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WPnI=" crossorigin=""></script>
@endonce

<div
    x-data="{
        map: null,
        marker: null,
        circle: null,
        lat: null,
        lng: null,
        radius: 100,

        searchQuery: '',
        searchResults: [],
        searchLoading: false,
        showResults: false,

        gpsLoading: false,
        gpsError: '',
        reverseLoading: false,

        async init() {
            await this.$nextTick();
            const d = this.$wire.data || {};
            this.lat    = parseFloat(d.lat)           || -7.7956;
            this.lng    = parseFloat(d.lng)           || 110.3695;
            this.radius = parseInt(d.radius_meters)   || 100;

            const hasSaved = parseFloat(d.lat) && parseFloat(d.lng);
            setTimeout(() => this.initMap(hasSaved ? 17 : 10), 80);

            this.$wire.$watch('data.lat', (v) => {
                const val = parseFloat(v);
                if (val && this.map) {
                    this.lat = val;
                    this.marker.setLatLng([this.lat, this.lng]);
                    this.circle.setLatLng([this.lat, this.lng]);
                    this.map.panTo([this.lat, this.lng]);
                }
            });
            this.$wire.$watch('data.lng', (v) => {
                const val = parseFloat(v);
                if (val && this.map) {
                    this.lng = val;
                    this.marker.setLatLng([this.lat, this.lng]);
                    this.circle.setLatLng([this.lat, this.lng]);
                    this.map.panTo([this.lat, this.lng]);
                }
            });
            this.$wire.$watch('data.radius_meters', (v) => {
                const val = parseInt(v);
                if (val && this.circle) {
                    this.radius = val;
                    this.circle.setRadius(val);
                }
            });
        },

        initMap(zoom = 13) {
            if (this.map) return;
            this.map = L.map(this.$refs.mapEl).setView([this.lat, this.lng], zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(this.map);
            this.circle = L.circle([this.lat, this.lng], {
                radius: this.radius,
                color: '#3b82f6',
                fillColor: '#93c5fd',
                fillOpacity: 0.2,
                weight: 2
            }).addTo(this.map);
            this.marker = L.marker([this.lat, this.lng], {
                draggable: true,
                title: 'Seret untuk mengubah lokasi'
            }).addTo(this.map);
            this.marker.bindTooltip('Titik Absen', { permanent: false, direction: 'top' });
            this.marker.on('dragend', () => {
                const pos = this.marker.getLatLng();
                this.setPosition(pos.lat, pos.lng);
            });
            this.map.on('click', (e) => {
                this.marker.setLatLng(e.latlng);
                this.setPosition(e.latlng.lat, e.latlng.lng);
            });
        },

        async setPosition(lat, lng, zoom, knownAddress = null) {
            this.lat = lat;
            this.lng = lng;
            this.circle.setLatLng([lat, lng]);
            this.marker.setLatLng([lat, lng]);
            if (zoom) this.map.setView([lat, lng], zoom);
            this.$wire.set('data.lat', lat);
            this.$wire.set('data.lng', lng);

            if (knownAddress) {
                this.$wire.set('data.address', knownAddress);
            } else {
                await this.reverseGeocode(lat, lng);
            }
        },

        async reverseGeocode(lat, lng) {
            this.reverseLoading = true;
            try {
                const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`;
                const res = await fetch(url, { headers: { 'Accept-Language': 'id,en' } });
                const data = await res.json();
                if (data && data.display_name) {
                    this.$wire.set('data.address', data.display_name);
                }
            } catch (e) {
                // silently ignore geocoding failures
            }
            this.reverseLoading = false;
        },

        updateRadius(r) {
            this.radius = parseInt(r);
            if (this.circle) this.circle.setRadius(this.radius);
            this.$wire.set('data.radius_meters', this.radius);
        },

        useGps() {
            if (!navigator.geolocation) {
                this.gpsError = 'Browser tidak mendukung GPS.';
                return;
            }
            this.gpsLoading = true;
            this.gpsError = '';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.gpsLoading = false;
                    this.setPosition(pos.coords.latitude, pos.coords.longitude, 17);
                },
                (err) => {
                    this.gpsLoading = false;
                    this.gpsError = 'Gagal mendapatkan lokasi: ' + err.message;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        },

        async search() {
            const q = this.searchQuery.trim();
            if (q.length < 3) return;
            this.searchLoading = true;
            this.searchResults = [];
            try {
                const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(q);
                const res = await fetch(url, { headers: { 'Accept-Language': 'id,en' } });
                this.searchResults = await res.json();
                this.showResults = this.searchResults.length > 0;
            } catch (e) {
                this.searchResults = [];
            }
            this.searchLoading = false;
        },

        selectResult(result) {
            this.searchQuery = result.display_name;
            this.showResults = false;
            this.searchResults = [];
            this.setPosition(parseFloat(result.lat), parseFloat(result.lon), 17, result.display_name);
        }
    }"
    class="col-span-full space-y-3"
>
    {{-- Search + GPS toolbar --}}
    <div class="flex gap-2">
        <div class="relative flex-1">
            <input
                type="text"
                x-model="searchQuery"
                @keydown.enter.prevent="search()"
                @keydown.escape="showResults = false"
                placeholder="Cari lokasi..."
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 pr-10 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-gray-400"
            >
            <button
                type="button"
                @click="search()"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600"
                :class="{ 'opacity-50 cursor-not-allowed': searchLoading }"
                :disabled="searchLoading"
            >
                <template x-if="searchLoading">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                </template>
                <template x-if="!searchLoading">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </template>
            </button>

            {{-- Search results dropdown --}}
            <div
                x-show="showResults"
                @click.outside="showResults = false"
                class="absolute z-[9999] mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-800"
            >
                <template x-for="result in searchResults" :key="result.place_id">
                    <button
                        type="button"
                        @click="selectResult(result)"
                        class="block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-blue-50 dark:text-gray-200 dark:hover:bg-white/10"
                        x-text="result.display_name"
                    ></button>
                </template>
            </div>
        </div>

        <button
            type="button"
            @click="useGps()"
            :disabled="gpsLoading"
            class="flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-blue-50 hover:border-blue-400 hover:text-blue-700 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
            title="Gunakan GPS saya"
        >
            <template x-if="gpsLoading">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
            </template>
            <template x-if="!gpsLoading">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2a7 7 0 0 1 7 7c0 5-7 13-7 13S5 14 5 9a7 7 0 0 1 7-7z"/>
                    <circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="2" fill="none"/>
                </svg>
            </template>
            <span x-text="gpsLoading ? 'Mencari...' : 'GPS Saya'"></span>
        </button>
    </div>

    <p x-show="gpsError" x-text="gpsError" class="text-xs text-red-500"></p>

    {{-- Map --}}
    <div wire:ignore>
        <div
            x-ref="mapEl"
            style="height:320px;border-radius:8px;overflow:hidden;"
            class="ring-1 ring-gray-200 dark:ring-white/10"
        ></div>
    </div>

    <p class="text-xs text-gray-400">Klik peta, seret pin, pakai GPS, atau cari nama lokasi untuk menentukan titik absen</p>

    {{-- Radius slider --}}
    <div class="space-y-1.5">
        <div class="flex items-center justify-between text-xs">
            <span class="font-medium text-gray-700 dark:text-gray-300">
                Radius: <span class="font-semibold text-blue-600" x-text="radius + ' meter'"></span>
            </span>
            <span class="text-gray-400">Geser untuk mengubah radius</span>
        </div>
        <input
            type="range"
            min="10" max="1000" step="10"
            :value="radius"
            @input="updateRadius($event.target.value)"
            class="w-full h-2 cursor-pointer accent-blue-600"
        >
        <div class="flex justify-between text-[10px] text-gray-400">
            <span>10m</span><span>250m</span><span>500m</span><span>750m</span><span>1000m</span>
        </div>
    </div>

    {{-- Coordinate display --}}
    <div class="flex items-center gap-5 rounded-lg bg-gray-50 px-4 py-2.5 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-400">
        <span>Lat: <code class="font-mono" x-text="lat !== null ? lat.toFixed(7) : '—'"></code></span>
        <span>Lng: <code class="font-mono" x-text="lng !== null ? lng.toFixed(7) : '—'"></code></span>
        <span>Radius: <code class="font-mono" x-text="radius + 'm'"></code></span>
        <span x-show="reverseLoading" class="ml-auto flex items-center gap-1 text-blue-500">
            <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            Mengisi alamat...
        </span>
    </div>
</div>
