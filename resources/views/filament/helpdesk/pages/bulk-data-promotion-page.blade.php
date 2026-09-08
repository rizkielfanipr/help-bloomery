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
            <x-rnd.picker-modal
                :title="$this->pickerTitle()"
                :description="'Dipilih: '.$this->selectedPickerCount().' item untuk '.($pickerComcode ?? 'semua comcode').'.'"
                max-width="7xl"
            >
                <x-slot:close>
                    <button
                        type="button"
                        wire:click="closePicker"
                        class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-200"
                        aria-label="Tutup modal"
                    >
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </x-slot:close>

                <div class="space-y-4 p-6">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div class="grid w-full gap-3 {{ $pickerType === 'menu' ? 'lg:grid-cols-3' : 'md:max-w-2xl' }}">
                            @if ($pickerType === 'menu')
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Nama Menu
                                    <input wire:model.live.debounce.700ms="pickerMenuNameSearch" type="search" placeholder="Cari semua nama menu..." class="mt-1 block w-full rounded-lg border-gray-300 text-sm font-normal shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white" />
                                </label>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Kode Menu
                                    <input wire:model.live.debounce.700ms="pickerMenuCodeSearch" type="search" placeholder="Cari semua kode menu..." class="mt-1 block w-full rounded-lg border-gray-300 text-sm font-normal shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white" />
                                </label>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Branch
                                    <select wire:model.live="pickerBranchFilter" class="mt-1 block w-full rounded-lg border-gray-300 text-sm font-normal shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                                        <option value="">- Semua Branch -</option>
                                        @foreach ($this->pickerBranchOptions() as $pickerBranchKey => $pickerBranchLabel)
                                            <option value="{{ $pickerBranchKey }}">{{ $pickerBranchLabel }}</option>
                                        @endforeach
                                    </select>
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
                            wire:target="searchPicker,pickerMenuNameSearch,pickerMenuCodeSearch,pickerBranchFilter,loadPickerRows,nextPickerPage,previousPickerPage,goToPickerPage"
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
                            wire:target="searchPicker,pickerMenuNameSearch,pickerMenuCodeSearch,pickerBranchFilter,loadPickerRows,nextPickerPage,previousPickerPage,goToPickerPage"
                            class="max-h-96 overflow-y-auto rounded-xl border border-gray-200 dark:border-white/10"
                        >
                        @if ($pickerType === 'menu')
                            <table class="w-full min-w-[760px] table-fixed text-sm">
                                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                                    <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        <th class="w-12 px-4 py-3"></th>
                                        <th class="w-[22%] px-4 py-3">Menu Code</th>
                                        <th class="w-[34%] px-4 py-3">Menu Name</th>
                                        <th class="w-[16%] px-4 py-3">Comcode</th>
                                        <th class="px-4 py-3">Branch</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($pickerRows as $row)
                                        @php
                                            $pickerValueArgument = str_replace("'", "\\'", $row['value']);
                                            $togglePickerAction = "togglePickerValue('{$pickerValueArgument}')";
                                        @endphp
                                        <tr wire:key="promotion-menu-picker-{{ md5($row['value']) }}" wire:click="{{ $togglePickerAction }}" class="cursor-pointer text-gray-700 transition hover:bg-blue-50 dark:text-gray-200 dark:hover:bg-blue-950/30">
                                            <td class="px-4 py-3"><input type="checkbox" tabindex="-1" class="pointer-events-none rounded border-gray-300 text-primary-600" @checked($this->isPickerValueSelected($row['value'])) /></td>
                                            <td class="px-4 py-3 font-mono font-semibold text-blue-700 dark:text-blue-300">{{ $row['code'] ?: '-' }}</td>
                                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                                            <td class="px-4 py-3">{{ $row['comcode'] }}</td>
                                            <td class="px-4 py-3">{{ $row['branch'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-14 text-center text-sm text-gray-500 dark:text-gray-400"><x-heroicon-o-cube-transparent class="mx-auto mb-3 h-10 w-10 text-gray-300" />Tidak ada menu aktif yang berhasil dimuat.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @else
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
                        @endif
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

                        @php
                            $pickerLastPage = max(1, (int) ceil($pickerTotal / max(1, $pickerPerPage)));
                            $pickerPageStart = max(1, $pickerPage - 4);
                            $pickerPageEnd = min($pickerLastPage, $pickerPageStart + 8);
                            $pickerPageStart = max(1, $pickerPageEnd - 8);
                        @endphp

                        <div class="flex max-w-full gap-2 overflow-x-auto">
                            @if ($pickerType === 'menu' && $pickerLastPage > 1)
                                <div class="inline-flex min-w-max overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                                    <button type="button" wire:click="goToPickerPage(1)" @disabled($pickerPage <= 1) class="border-r border-gray-300 px-3 py-2 text-xs font-semibold disabled:opacity-40 dark:border-gray-600">First</button>
                                    @foreach (range($pickerPageStart, $pickerPageEnd) as $pageNumber)
                                        <button type="button" wire:click="goToPickerPage({{ $pageNumber }})" @disabled($pageNumber === $pickerPage) class="border-r border-gray-300 px-3.5 py-2 text-xs font-semibold dark:border-gray-600 {{ $pageNumber === $pickerPage ? 'bg-blue-600 text-white disabled:opacity-100' : 'hover:bg-gray-50 dark:hover:bg-gray-800' }}">{{ $pageNumber }}</button>
                                    @endforeach
                                    <button type="button" wire:click="goToPickerPage({{ $pickerLastPage }})" @disabled($pickerPage >= $pickerLastPage) class="px-3 py-2 text-xs font-semibold disabled:opacity-40">Last</button>
                                </div>
                            @endif
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
            </x-rnd.picker-modal>
        </div>
    @endif
</x-filament-panels::page>
