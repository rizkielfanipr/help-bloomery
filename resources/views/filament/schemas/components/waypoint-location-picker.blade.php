@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endonce

<div x-data="{
    map: null, marker: null, circle: null,
    base: @js($getStatePath()), lat: null, lng: null, radius: 100,
    searchQuery: '', searchResults: [], searchLoading: false, showResults: false, searchError: '',
    async init() {
        await this.$nextTick();
        this.lat = parseFloat(this.$wire.get(this.base + '.latitude')) || -7.7956;
        this.lng = parseFloat(this.$wire.get(this.base + '.longitude')) || 110.3695;
        this.radius = parseInt(this.$wire.get(this.base + '.radius_meters')) || 100;
        setTimeout(() => this.initMap(), 150);
    },
    initMap() {
        if (this.map || typeof L === 'undefined') return;
        this.map = L.map(this.$refs.map).setView([this.lat, this.lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(this.map);
        this.circle = L.circle([this.lat, this.lng], { radius: this.radius, color: '#2563eb', fillOpacity: .15 }).addTo(this.map);
        this.marker = L.marker([this.lat, this.lng], { draggable: true }).addTo(this.map);
        this.marker.on('dragend', () => { const p = this.marker.getLatLng(); this.setPosition(p.lat, p.lng); });
        this.map.on('click', e => this.setPosition(e.latlng.lat, e.latlng.lng));
        const observer = new ResizeObserver(() => this.map?.invalidateSize(false));
        observer.observe(this.$refs.map);
        setTimeout(() => this.map.invalidateSize(false), 250);
        setTimeout(() => this.map.invalidateSize(false), 700);
    },
    setPosition(lat, lng, zoom = null) {
        this.lat = lat; this.lng = lng;
        this.marker.setLatLng([lat, lng]); this.circle.setLatLng([lat, lng]);
        if (zoom) this.map.setView([lat, lng], zoom);
        this.$wire.set(this.base + '.latitude', lat);
        this.$wire.set(this.base + '.longitude', lng);
    },
    useGps() {
        navigator.geolocation?.getCurrentPosition(p => this.setPosition(p.coords.latitude, p.coords.longitude, 17), null, { enableHighAccuracy: true });
    },
    async search() {
        const query = this.searchQuery.trim();
        if (query.length < 3) {
            this.searchError = 'Masukkan minimal 3 karakter.';
            return;
        }
        this.searchLoading = true; this.searchError = ''; this.searchResults = [];
        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=id&q=' + encodeURIComponent(query);
            const response = await fetch(url, { headers: { 'Accept-Language': 'id,en' } });
            if (!response.ok) throw new Error('Pencarian lokasi gagal.');
            this.searchResults = await response.json();
            this.showResults = this.searchResults.length > 0;
            if (!this.searchResults.length) this.searchError = 'Lokasi tidak ditemukan.';
        } catch (error) {
            this.searchError = 'Gagal mencari lokasi. Coba kembali.';
        }
        this.searchLoading = false;
    },
    selectResult(result) {
        this.searchQuery = result.display_name;
        this.showResults = false;
        this.searchResults = [];
        this.setPosition(parseFloat(result.lat), parseFloat(result.lon), 17);
    },
    setRadius(value) {
        this.radius = parseInt(value); this.circle.setRadius(this.radius);
        this.$wire.set(this.base + '.radius_meters', this.radius);
    }
}" class="col-span-full space-y-2">
    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">Pin dan radius titik tujuan</p>
    <div>
        <div class="relative min-w-0">
            <input type="text" x-model="searchQuery" @keydown.enter.prevent="search()" @keydown.escape="showResults = false"
                   placeholder="Cari lokasi..."
                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 pr-10 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-white/10 dark:bg-white/5 dark:text-white">
            <button type="button" @click="search()" :disabled="searchLoading"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600 disabled:opacity-50">
                <svg x-show="!searchLoading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                <svg x-show="searchLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
            </button>
            <div x-show="showResults" @click.outside="showResults = false"
                 class="absolute z-[9999] mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl dark:border-white/10 dark:bg-gray-800">
                <template x-for="result in searchResults" :key="result.place_id">
                    <button type="button" @click="selectResult(result)" x-text="result.display_name"
                            class="block w-full border-b border-gray-100 px-3 py-2 text-left text-xs text-gray-700 last:border-0 hover:bg-blue-50 dark:border-white/5 dark:text-gray-200 dark:hover:bg-white/10"></button>
                </template>
            </div>
        </div>
    </div>
    <p x-show="searchError" x-text="searchError" class="text-xs text-red-500"></p>
    <div class="w-full" style="position: relative;">
        <div wire:ignore class="w-full">
            <div x-ref="map"
                 style="height:280px;min-height:280px;width:100%;position:relative;z-index:0;"
                 class="overflow-hidden rounded-xl bg-gray-100 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"></div>
        </div>
        <button type="button"
                @click="useGps()"
                title="Gunakan lokasi GPS saya"
                aria-label="Gunakan lokasi GPS saya"
                style="position: absolute; right: 12px; bottom: 32px; z-index: 500;"
                class="absolute bottom-3 right-3 z-[500] flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 shadow-md transition hover:bg-gray-50 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-blue-400">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="3" stroke-width="2" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v3m0 14v3M2 12h3m14 0h3m-4.343-5.657A8 8 0 1 1 6.343 17.657 8 8 0 0 1 17.657 6.343Z" />
            </svg>
        </button>
    </div>
    <input type="range" min="10" max="5000" step="10" :value="radius" @input="setRadius($event.target.value)" class="w-full accent-blue-600">
    <div class="flex justify-between text-[11px] text-gray-400">
        <span x-text="lat.toFixed(6) + ', ' + lng.toFixed(6)"></span>
        <span x-text="'Radius ' + radius + ' meter'"></span>
    </div>
</div>
