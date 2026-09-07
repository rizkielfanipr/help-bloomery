<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="submit">
            {{ $this->form }}

            @php
                $applyDiscountTo = (int) ($data['applyDiscountTo'] ?? 0);
                $pickerConfig = match ($applyDiscountTo) {
                    1 => ['type' => 'category', 'field' => 'menuCategoryID', 'label' => 'Menu Category'],
                    2 => ['type' => 'category_detail', 'field' => 'menuCategoryDetailID', 'label' => 'Menu Category Detail'],
                    3 => ['type' => 'menu', 'field' => 'menuID', 'label' => 'Menu'],
                    default => null,
                };
                $pickerGroups = $pickerConfig && ! (bool) ($data['allCategories'] ?? true)
                    ? $this->pickerTargetGroups()
                    : [];
            @endphp

            @if ((bool) ($data['allCategories'] ?? true))
                <div class="mt-6 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">
                    Semua kategori aktif, jadi pemilihan category, category detail, atau menu per comcode tidak diperlukan.
                </div>
            @elseif (! $pickerConfig)
                <div class="mt-6 rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Pilih <strong>Apply Discount To</strong> agar kolom pilihan per comcode muncul.
                </div>
            @else
                <div class="mt-6 space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            Pilih {{ $pickerConfig['label'] }} per Comcode
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Setiap comcode memiliki ID {{ strtolower($pickerConfig['label']) }} yang berbeda. Lengkapi pilihan untuk masing-masing comcode.
                        </p>
                    </div>

                    @forelse ($pickerGroups as $group)
                        @php
                            $selectedCount = $this->pickerSelectionCountForComcode($pickerConfig['field'], $group['comcode']);
                            $pickerTypeArgument = str_replace("'", "\\'", $pickerConfig['type']);
                            $comcodeArgument = str_replace("'", "\\'", $group['comcode']);
                            $openPickerAction = "openPickerForComcode('{$pickerTypeArgument}', '{$comcodeArgument}')";
                        @endphp

                        <div
                            wire:key="promotion-target-{{ $pickerConfig['type'] }}-{{ $group['comcode'] }}"
                            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
                        >
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-md bg-primary-50 px-2.5 py-1 text-sm font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
                                            {{ $group['comcode'] }}
                                        </span>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                            {{ $selectedCount > 0 ? $selectedCount.' dipilih' : 'Belum dipilih' }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        {{ implode(', ', $group['branches']) }}
                                    </p>
                                </div>

                                <x-filament::button
                                    type="button"
                                    size="sm"
                                    icon="heroicon-m-list-bullet"
                                    wire:click="{{ $openPickerAction }}"
                                >
                                    Pilih {{ $pickerConfig['label'] }}
                                </x-filament::button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                            Pilih branch terlebih dahulu agar input per comcode muncul.
                        </div>
                    @endforelse

                    @error($pickerConfig['field'])
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <x-filament::button
                    tag="a"
                    color="gray"
                    href="{{ route('filament.helpdesk.pages.bulk-data') }}"
                    icon="heroicon-m-arrow-left"
                >
                    Kembali ke Bulk Data
                </x-filament::button>

                <x-filament::button type="submit" icon="heroicon-m-paper-airplane">
                    Submit Promotion Free Item
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if ($pickerOpen)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-4"
            @if (! $pickerLoaded)
                x-data
                x-init="$nextTick(() => $wire.loadPickerRows())"
            @endif
        >
            <div class="w-full max-w-5xl overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $this->pickerTitle() }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Dipilih: {{ $this->selectedPickerCount() }} item untuk {{ $pickerComcode ?? 'semua comcode' }}.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="closePicker"
                        class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-200"
                        aria-label="Tutup modal"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-4 p-6">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div class="grid w-full gap-3 md:max-w-2xl {{ $pickerType === 'menu' ? 'sm:grid-cols-2' : '' }}">
                            @if ($pickerType === 'menu')
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Nama Menu
                                    <input wire:model.live.debounce.700ms="pickerMenuNameSearch" type="search" placeholder="Cari semua nama menu..." class="mt-1 block w-full rounded-lg border-gray-300 text-sm font-normal shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white" />
                                </label>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Kode Menu
                                    <input wire:model.live.debounce.700ms="pickerMenuCodeSearch" type="search" placeholder="Cari semua kode menu..." class="mt-1 block w-full rounded-lg border-gray-300 text-sm font-normal shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white" />
                                </label>
                            @else
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Cari {{ $pickerType === 'category_detail' ? 'Menu Category Detail' : 'Menu Category' }}
                                    <input type="search" value="{{ $pickerSearch }}" wire:input.debounce.500ms="searchPicker($event.target.value)" placeholder="Cari berdasarkan kode atau nama..." class="mt-1 block w-full rounded-lg border-gray-300 text-sm font-normal shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white" />
                                </label>
                            @endif
                        </div>

                        <span class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            20 item per halaman
                        </span>
                    </div>

                    @if (! $pickerLoaded)
                        <div class="flex min-h-72 flex-col items-center justify-center rounded-xl border border-dashed border-primary-300 bg-primary-50/50 px-6 text-center dark:border-primary-500/30 dark:bg-primary-500/5">
                            <x-filament::loading-indicator class="h-8 w-8 text-primary-600" />
                            <p class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">
                                Mengambil {{ strtolower($this->pickerTitle()) }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Menghubungkan ke ESB {{ $pickerComcode }} dan memuat halaman {{ $pickerPage }} (maksimal 20 item).
                            </p>
                            <div class="mt-5 h-1.5 w-full max-w-sm overflow-hidden rounded-full bg-primary-100 dark:bg-primary-500/10">
                                <div class="h-full w-2/3 animate-pulse rounded-full bg-primary-600"></div>
                            </div>
                            <div class="mt-4 flex flex-wrap justify-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="rounded-full bg-white px-2.5 py-1 shadow-sm dark:bg-white/5">1. Validasi branch</span>
                                <span class="rounded-full bg-white px-2.5 py-1 shadow-sm dark:bg-white/5">2. Ambil data ESB</span>
                                <span class="rounded-full bg-white px-2.5 py-1 shadow-sm dark:bg-white/5">3. Siapkan pilihan</span>
                            </div>
                        </div>
                    @else
                        <div
                            wire:loading.flex
                            wire:target="searchPicker,pickerMenuNameSearch,pickerMenuCodeSearch,loadPickerRows,nextPickerPage,previousPickerPage"
                            class="min-h-72 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-primary-300 bg-primary-50/50 text-sm text-gray-600 dark:border-primary-500/30 dark:bg-primary-500/5 dark:text-gray-300"
                        >
                            <x-filament::loading-indicator class="h-7 w-7 text-primary-600" />
                            <span>
                                @if ($pickerType === 'menu' && ($pickerMenuNameSearch !== '' || $pickerMenuCodeSearch !== ''))
                                    Mencari menu dari ESB {{ $pickerComcode }}...
                                @else
                                    {{ $pickerSearch !== '' ? 'Mencari “'.$pickerSearch.'”' : 'Memuat halaman '.$pickerPage }} dari ESB {{ $pickerComcode }}...
                                @endif
                            </span>
                            <span class="text-xs text-gray-400">Maksimal 20 item per halaman</span>
                        </div>

                        <div
                            wire:loading.remove
                            wire:target="searchPicker,pickerMenuNameSearch,pickerMenuCodeSearch,loadPickerRows,nextPickerPage,previousPickerPage"
                            class="max-h-96 overflow-y-auto rounded-xl border border-gray-200 dark:border-white/10"
                        >
                        @forelse ($pickerRows as $row)
                            @php
                                $pickerValueArgument = str_replace("'", "\\'", $row['value']);
                                $togglePickerAction = "togglePickerValue('{$pickerValueArgument}')";
                            @endphp
                            <label
                                wire:key="promotion-picker-{{ md5($row['value']) }}"
                                class="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-4 py-3 transition last:border-b-0 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                    @checked($this->isPickerValueSelected($row['value']))
                                    wire:click="{{ $togglePickerAction }}"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $row['label'] }}
                                    </span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['meta'] }}
                                    </span>
                                </span>
                            </label>
                        @empty
                            <div class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                Data belum ditemukan untuk branch/comcode yang dipilih.
                            </div>
                        @endforelse
                        </div>
                    @endif

                    <div class="flex flex-col gap-3 border-t border-gray-200 pt-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @if ($pickerLoaded)
                                Halaman {{ $pickerPage }} · {{ count($pickerRows) }} item tampil
                            @else
                                Sedang menyiapkan data...
                            @endif
                        </p>

                        <div class="flex gap-2">
                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="previousPickerPage"
                                :disabled="! $pickerLoaded || $pickerPage <= 1"
                            >
                                Sebelumnya
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="nextPickerPage"
                                :disabled="! $pickerLoaded || ! $pickerHasNext"
                            >
                                Selanjutnya
                            </x-filament::button>

                            <x-filament::button type="button" wire:click="closePicker">
                                Selesai
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
