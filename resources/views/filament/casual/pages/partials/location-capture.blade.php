@php
    $shift = $shift ?? null;
    $shiftLat = $shift?->location_lat ?? '';
    $shiftLng = $shift?->location_lng ?? '';
    $radius   = $shift?->location_radius_meters ?? 0;
    $locationRequired = $shift?->location_required ?? false;
@endphp

<div class="mt-4 space-y-3">
    <div class="flex items-center gap-3">
        <x-filament::button
            type="button"
            x-on:click="capture()"
            :disabled="false"
            color="gray"
            outlined
            icon="heroicon-m-map-pin"
            size="sm"
        >
            <span x-show="!loading">Ambil Lokasi GPS</span>
            <span x-show="loading">Mencari lokasi...</span>
        </x-filament::button>

        <span x-show="captured" class="text-xs text-success-600 dark:text-success-400">
            ✓ Lokasi didapat
            @if($locationRequired)
                <span class="ml-1 text-xs text-gray-500">(wajib untuk shift ini)</span>
            @endif
        </span>
        @if(!$locationRequired)
            <span class="text-xs text-gray-400">(opsional)</span>
        @endif
    </div>

    <div x-show="error" class="rounded-md bg-danger-50 px-3 py-2 text-xs text-danger-700 dark:bg-danger-900/20 dark:text-danger-400" x-text="error"></div>

    <div
        x-show="captured"
        x-ref="map"
        data-shift-lat="{{ $shiftLat }}"
        data-shift-lng="{{ $shiftLng }}"
        data-radius="{{ $radius }}"
        style="height: 220px; border-radius: 8px; overflow: hidden; display: none;"
        x-bind:style="captured ? 'display:block;height:220px;border-radius:8px;overflow:hidden;' : 'display:none;'"
    ></div>

    <div x-show="captured" class="text-xs text-gray-500">
        Koordinat: <span x-text="lat?.toFixed(6)"></span>, <span x-text="lng?.toFixed(6)"></span>
    </div>
</div>
