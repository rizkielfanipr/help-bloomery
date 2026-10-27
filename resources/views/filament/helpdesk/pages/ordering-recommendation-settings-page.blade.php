<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400">
                            <x-heroicon-o-map-pin class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-xl font-bold text-gray-950 dark:text-white">Lokasi Stok per Branch</h1>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih lokasi inventory ESB yang dianggap sebagai stok tersedia untuk setiap kombinasi Branch, Company Code, dan ESB Branch.</p>
                        </div>
                    </div>
                </div>
                <div class="w-full lg:max-w-sm">
                    <label for="ordering-location-search" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Cari Mapping</label>
                    <input id="ordering-location-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Branch, Company Code, atau ESB Branch" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Mapping ESB Aktif</h2>
                <p class="mt-1 text-xs leading-5 text-gray-500">Data Stock Movement tetap diambil per mapping. Pengaturan ini hanya menentukan lokasi mana di dalam response mapping tersebut yang boleh dihitung.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800/60">
                        <tr>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Company Code</th>
                            <th class="px-5 py-3">ESB Branch</th>
                            <th class="px-5 py-3">Mode</th>
                            <th class="px-5 py-3">Lokasi Stok</th>
                            <th class="px-5 py-3">Diperbarui</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->mappings() as $mapping)
                            @php
                                $mappingMode = $mapping->ordering_stock_location_mode ?? \App\Enums\OrderingStockLocationMode::Unconfigured;
                                $effectiveScope = $mapping->orderingStockLocationScope();
                            @endphp
                            <tr wire:key="ordering-location-{{ $mapping->id }}" class="align-top">
                                <td class="px-5 py-4 font-medium text-gray-900 dark:text-white">{{ $mapping->branch?->name ?? 'Branch tidak ditemukan' }}</td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $mapping->esb_comcode }}</td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $mapping->esb_branch_code }}</td>
                                <td class="px-5 py-4">
                                    <x-filament::badge :color="$mappingMode->getColor()">{{ $mappingMode->getLabel() }}</x-filament::badge>
                                </td>
                                <td class="max-w-xs px-5 py-4 text-gray-600 dark:text-gray-300">
                                    @if($mappingMode === \App\Enums\OrderingStockLocationMode::All)
                                        Semua lokasi pada mapping ini
                                    @elseif($mappingMode === \App\Enums\OrderingStockLocationMode::Selected)
                                        {{ collect($mapping->ordering_stock_locations)->pluck('name')->filter()->implode(', ') ?: 'Belum ada lokasi dipilih' }}
                                    @elseif($effectiveScope === 'all')
                                        <span class="text-amber-600 dark:text-amber-400">Fallback lama: semua lokasi</span>
                                    @elseif(is_array($effectiveScope) && $effectiveScope !== [])
                                        <span class="text-amber-600 dark:text-amber-400">Fallback lama: {{ implode(', ', $effectiveScope) }}</span>
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">Recommendation akan Blocked sampai dikonfigurasi.</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-gray-500">{{ $mapping->ordering_stock_locations_updated_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}</td>
                                <td class="px-5 py-4 text-right">
                                    <x-filament::button type="button" size="sm" color="gray" icon="heroicon-o-adjustments-horizontal" wire:click="configure({{ $mapping->id }})">
                                        Atur Lokasi
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">Tidak ada mapping ESB aktif yang sesuai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <x-filament::modal id="ordering-stock-location-settings" width="3xl" :close-by-clicking-away="false">
        <x-slot name="heading">Atur Lokasi Stok</x-slot>
        <x-slot name="description">Lokasi diambil dari API Stock Movement untuk mapping yang dipilih. Tidak ada data yang dikirim atau diubah di ESB.</x-slot>

        <form wire:submit="save" class="space-y-5">
            <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-4 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
                Setelah disimpan, Daily Usage dan Stock Movement 8 minggu untuk mapping ini dijadwalkan, lalu forecast baru dibuat. Forecast lama tidak dihapus.
            </div>

            <fieldset class="space-y-3">
                <legend class="text-sm font-semibold text-gray-900 dark:text-white">Mode Lokasi</legend>
                @foreach(\App\Enums\OrderingStockLocationMode::cases() as $option)
                    <label wire:key="ordering-location-mode-{{ $option->value }}" class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <input
                            type="radio"
                            name="ordering-stock-location-mode"
                            value="{{ $option->value }}"
                            wire:click="selectMode('{{ $option->value }}')"
                            @checked($mode === $option->value)
                            class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500"
                        >
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $option->getLabel() }}</span>
                            <span class="mt-0.5 block text-xs leading-5 text-gray-500">
                                @if($option === \App\Enums\OrderingStockLocationMode::Selected) Gunakan hanya lokasi yang dipilih.
                                @elseif($option === \App\Enums\OrderingStockLocationMode::All) Jumlahkan saldo terbaru seluruh lokasi dalam mapping branch ini.
                                @else Gunakan fallback lama dari server. Jika fallback kosong, recommendation akan Blocked.
                                @endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </fieldset>

            @if($mode === \App\Enums\OrderingStockLocationMode::Selected->value)
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Lokasi Terpilih</h3>
                            <p class="mt-1 text-xs text-gray-500">Pencarian memakai transaksi 30 hari terakhir dari mapping ini.</p>
                        </div>
                        <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-path" wire:click="discoverLocations" wire:loading.attr="disabled" wire:target="discoverLocations">
                            <span wire:loading.remove wire:target="discoverLocations">Ambil Lokasi dari ESB</span>
                            <span wire:loading wire:target="discoverLocations">Mengambil…</span>
                        </x-filament::button>
                    </div>

                    <div class="max-h-72 space-y-2 overflow-y-auto rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        @forelse($availableLocations as $location)
                            <label wire:key="available-location-{{ $location['fingerprint'] }}" class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800">
                                <input type="checkbox" wire:model="selectedLocationFingerprints" value="{{ $location['fingerprint'] }}" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="min-w-0">
                                    <span class="block break-words text-sm font-medium text-gray-900 dark:text-white">{{ $location['name'] }}</span>
                                    <span class="mt-0.5 block break-all text-xs text-gray-500">Key: {{ $location['key'] }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="px-2 py-6 text-center text-sm text-gray-500">Belum ada lokasi yang dapat dipilih. Klik “Ambil Lokasi dari ESB” terlebih dahulu.</p>
                        @endforelse
                    </div>
                    @error('selectedLocationFingerprints') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @foreach($errors->get('selectedLocationFingerprints.*') as $messages) @foreach($messages as $message)<p class="text-sm text-red-600">{{ $message }}</p>@endforeach @endforeach
                </div>
            @endif

            <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:justify-end dark:border-gray-700">
                <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: 'ordering-stock-location-settings' })">Batal</x-filament::button>
                <x-filament::button type="submit" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="save">Simpan & Jadwalkan Ulang</x-filament::button>
            </div>
        </form>
    </x-filament::modal>
</x-filament-panels::page>
