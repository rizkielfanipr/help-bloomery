{{--
    WIP Shelf Life form shared by the Shelf Life menu and the Project Product page
    (docs/rnd-wip-shelf-life-prd.md §16.3). Expects the host to use ManagesWipShelfLifeForm and to
    provide $shelfLifeSaveMethod; $shelfLifeContextNote is optional.
--}}
@if($shelfLifeModalOpen && $shelfLifeTarget)
    <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeShelfLifeModal()">
        <button type="button" wire:click="closeShelfLifeModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal Shelf Life"></button>
        <form wire:submit="{{ $shelfLifeSaveMethod }}" class="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-labelledby="wip-shelf-life-heading">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                <div class="min-w-0">
                    <h3 id="wip-shelf-life-heading" class="text-lg font-bold text-gray-900 dark:text-white">{{ $shelfLifeTarget['is_existing'] ? 'Edit Shelf Life' : 'Isi Shelf Life' }}</h3>
                    @if(filled($shelfLifeContextNote ?? null))
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $shelfLifeContextNote }}</p>
                    @endif
                </div>
                <button type="button" wire:click="closeShelfLifeModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="space-y-4 overflow-y-auto p-5">
                <dl class="rounded-xl border border-gray-200 bg-gray-50 p-3.5 text-sm dark:border-gray-700 dark:bg-gray-800/60">
                    <dt class="text-[10px] font-bold uppercase tracking-wide text-gray-400">WIP</dt>
                    <dd class="mt-0.5 break-words font-semibold text-gray-900 dark:text-white">{{ $shelfLifeTarget['product_name'] }}</dd>
                    <dd class="mt-0.5 break-words text-xs text-gray-500 dark:text-gray-400">
                        {{ $shelfLifeTarget['product_code'] ?: '-' }} · Product Detail #{{ $shelfLifeTarget['product_detail_id'] }}{{ filled($shelfLifeTarget['uom_name']) ? ' · '.$shelfLifeTarget['uom_name'] : '' }}
                    </dd>
                    @if(array_key_exists('purchase_uom_name', $shelfLifeTarget))
                        <dd class="mt-1 text-xs text-gray-600 dark:text-gray-300">Unit Purchase: <span class="font-semibold">{{ $shelfLifeTarget['purchase_uom_name'] ?: 'Belum tersedia' }}</span></dd>
                    @endif
                </dl>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="wip-shelf-life-value" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Masa Simpan *</label>
                        <input id="wip-shelf-life-value" type="number" step="0.01" min="0.01" inputmode="decimal" wire:model="shelfLifeValue" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('shelfLifeValue')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="wip-shelf-life-unit" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Satuan *</label>
                        <select id="wip-shelf-life-unit" wire:model="shelfLifeUnit" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @foreach($this->shelfLifeUnitOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('shelfLifeUnit')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="wip-shelf-life-storage" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Kondisi Penyimpanan *</label>
                        <select id="wip-shelf-life-storage" wire:model="shelfLifeStorageCondition" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @foreach($this->shelfLifeStorageOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('shelfLifeStorageCondition')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="wip-shelf-life-notes" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Catatan <span class="font-normal text-gray-400">(opsional)</span></label>
                        <textarea id="wip-shelf-life-notes" wire:model="shelfLifeNotes" rows="3" placeholder="Contoh: simpan 2–5°C, wadah tertutup" class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                        @error('shelfLifeNotes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                <button type="button" wire:click="closeShelfLifeModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Batal</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="{{ $shelfLifeSaveMethod }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="{{ $shelfLifeSaveMethod }}">Simpan</span>
                    <span wire:loading wire:target="{{ $shelfLifeSaveMethod }}">Menyimpan...</span>
                </button>
            </div>
        </form>
    </div>
@endif
