<div
    x-data="{
        map: null,
        marker: null,
        circle: null,
        lat: null,
        lng: null,
        radius: 100,

        async init() {
            await this.$nextTick();
            const d = this.$wire.data || {};
            this.lat    = parseFloat(d.location_lat)         || -7.7956;
            this.lng    = parseFloat(d.location_lng)         || 110.3695;
            this.radius = parseInt(d.location_radius_meters) || 100;
            setTimeout(() => this.initMap(), 80);
        },

        initMap() {
            if (this.map) return;
            this.map = L.map(this.$refs.mapEl).setView([this.lat, this.lng], 17);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'OpenStreetMap contributors'
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
            this.marker.bindTooltip('Lokasi Kerja', { permanent: false, direction: 'top' });
            this.marker.on('dragend', () => {
                const pos = this.marker.getLatLng();
                this.setPosition(pos.lat, pos.lng);
            });
            this.map.on('click', (e) => {
                this.marker.setLatLng(e.latlng);
                this.setPosition(e.latlng.lat, e.latlng.lng);
            });
        },

        setPosition(lat, lng) {
            this.lat = lat;
            this.lng = lng;
            this.circle.setLatLng([lat, lng]);
            this.$wire.set('data.location_lat', lat);
            this.$wire.set('data.location_lng', lng);
        },

        updateRadius(r) {
            this.radius = parseInt(r);
            if (this.circle) this.circle.setRadius(this.radius);
            this.$wire.set('data.location_radius_meters', this.radius);
        }
    }"
    class="col-span-full space-y-3"
>
    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
        Klik peta atau seret pin untuk menentukan titik lokasi kerja
    </p>

    <div wire:ignore>
        <div
            x-ref="mapEl"
            style="height:300px;border-radius:8px;overflow:hidden;"
            class="ring-1 ring-gray-200 dark:ring-white/10"
        ></div>
    </div>

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

    <div class="flex gap-5 rounded-lg bg-gray-50 px-4 py-2.5 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-400">
        <span>Lat: <code class="font-mono" x-text="lat !== null ? lat.toFixed(7) : '—'"></code></span>
        <span>Lng: <code class="font-mono" x-text="lng !== null ? lng.toFixed(7) : '—'"></code></span>
        <span>Radius: <code class="font-mono" x-text="radius + 'm'"></code></span>
    </div>
</div>
