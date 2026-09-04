<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="submit">
            {{ $this->form }}

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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-4">
            <div class="w-full max-w-5xl overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $this->pickerTitle() }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Dipilih: {{ $this->selectedPickerCount() }} item. Menampilkan data aktif, dipisahkan per comcode dan branch.
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
                        <div class="w-full md:max-w-md">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Cari Menu
                            </label>
                            <input
                                type="search"
                                wire:model.live.debounce.500ms="pickerSearch"
                                @disabled($pickerType !== 'menu')
                                placeholder="{{ $pickerType === 'menu' ? 'Cari kode menu...' : 'Search hanya tersedia untuk data menu' }}"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm disabled:bg-gray-100 disabled:text-gray-400 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800"
                            />
                        </div>

                        <div class="w-full md:w-40">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Data per halaman
                            </label>
                            <select
                                wire:change="setPickerPerPage($event.target.value)"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white"
                            >
                                <option value="10" @selected($pickerPerPage === 10)>10 data</option>
                                <option value="20" @selected($pickerPerPage === 20)>20 data</option>
                            </select>
                        </div>
                    </div>

                    <div
                        wire:loading.flex
                        wire:target="openPicker,loadPickerRows,nextPickerPage,previousPickerPage,setPickerPerPage,updatedPickerSearch"
                        class="min-h-72 items-center justify-center gap-3 rounded-xl border border-dashed border-gray-300 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400"
                    >
                        <x-filament::loading-indicator class="h-6 w-6" />
                        <span>Memuat data ESB...</span>
                    </div>

                    <div
                        wire:loading.remove
                        wire:target="openPicker,loadPickerRows,nextPickerPage,previousPickerPage,setPickerPerPage,updatedPickerSearch"
                        class="max-h-96 overflow-y-auto rounded-xl border border-gray-200 dark:border-white/10"
                    >
                        @forelse ($pickerRows as $row)
                            <label
                                wire:key="promotion-picker-{{ md5($row['value']) }}"
                                class="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-4 py-3 transition last:border-b-0 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                    @checked($this->isPickerValueSelected($row['value']))
                                    wire:click="togglePickerValue(@js($row['value']))"
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

                    <div class="flex flex-col gap-3 border-t border-gray-200 pt-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Halaman {{ $pickerPage }} · {{ count($pickerRows) }} data tampil
                        </p>

                        <div class="flex gap-2">
                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="previousPickerPage"
                                :disabled="$pickerPage <= 1"
                            >
                                Sebelumnya
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="nextPickerPage"
                                :disabled="! $pickerHasNext"
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
