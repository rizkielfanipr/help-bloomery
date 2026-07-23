<x-filament-panels::page>
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200';
    @endphp

    <div
        class="w-full max-w-none space-y-4"
        x-data="{ productModalOpen: false, importModalOpen: false, productTarget: 'result', productIndex: 0, unitFilter: '', unitLabel: '- Semua Unit -', conversionFilter: '', filterDropdown: null, importFilterDropdown: null, categorySearch: '', subCategorySearch: '', unitSearch: '', importUnitOptionSearch: '', importTypeOptionSearch: '' }"
        @open-product-picker.window="
            productTarget = $event.detail.target;
            productIndex = $event.detail.index ?? 0;
            productModalOpen = true;
            $wire.loadProducts();
            $nextTick(() => $refs.productCodeSearch?.focus());
        "
        @keydown.escape.window="productModalOpen = false; importModalOpen = false"
    >
        <div class="rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-5 dark:border-blue-900/50 dark:from-blue-950/40 dark:to-indigo-950/40 lg:p-6">
            <div class="flex items-start gap-4">
                <div class="rounded-xl bg-blue-600 p-3 text-white"><x-heroicon-o-beaker class="h-6 w-6" /></div>
                <div>
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Assembly Recipe</p>
                    <h2 class="mt-0.5 text-2xl font-bold text-gray-900 dark:text-white">{{ $isEditing ? 'Update Bill of Material' : 'Buat Bill of Material Baru' }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $isEditing ? 'Perbarui informasi dan bahan BOM yang tersimpan di ESB Core.' : 'Isi produk hasil dan bahan penyusun. Data akan dikirim ke ESB Core.' }}</p>
                </div>
            </div>
        </div>

        <form wire:submit="create" class="space-y-4">
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 lg:p-6">
                <div class="mb-5">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Informasi Resep</h3>
                    <p class="text-sm text-gray-500">Identitas utama dan produk yang dihasilkan.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="{{ $label }}">Nama BOM <span class="text-red-500">*</span></label>
                        <input wire:model="data.bomName" class="{{ $input }}" placeholder="Contoh: Assembly Croissant">
                        @error('data.bomName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Kode BOM <span class="text-red-500">*</span></label>
                        <input wire:model="data.bomCode" class="{{ $input }}" placeholder="Contoh: BOM-CRS-001">
                        @error('data.bomCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2 xl:col-span-4">
                        @php
                            $selectedResult = $selectedProducts[(int) ($data['productDetailID'] ?? 0)] ?? null;
                        @endphp
                        <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(120px,0.6fr)]">
                            <div>
                                <label class="{{ $label }}">Product Name <span class="text-red-500">*</span></label>
                                <button type="button" @click="$dispatch('open-product-picker', { target: 'result', index: 0 })" class="flex min-h-10 w-full overflow-hidden rounded-lg border border-gray-300 bg-white text-left text-sm hover:border-blue-400 dark:border-gray-600 dark:bg-gray-800">
                                    <span class="min-w-0 flex-1 truncate px-3 py-2 {{ $selectedResult ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">{{ $selectedResult['productName'] ?? 'Pilih produk hasil' }}</span>
                                    <span class="flex w-10 shrink-0 items-center justify-center bg-blue-600 font-bold text-white">...</span>
                                </button>
                            </div>
                            <div>
                                <label class="{{ $label }}">Product Code</label>
                                <input value="{{ $selectedResult['productCode'] ?? '' }}" readonly class="{{ $input }} bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            </div>
                            <div>
                                <label class="{{ $label }}">Unit</label>
                                <input value="{{ ($selectedResult['baseUnit'] ?? '') ?: ($selectedResult['unit'] ?? '') }}" readonly class="{{ $input }} bg-gray-50 text-center font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            </div>
                        </div>
                        @error('data.productDetailID') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Total Biaya BOM</label>
                        <input wire:model="data.bomCostTotal" type="number" min="0" step="0.0001" class="{{ $input }}">
                        @error('data.bomCostTotal') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2 xl:col-span-4">
                        <label class="{{ $label }}">Catatan</label>
                        <textarea wire:model="data.notes" rows="3" class="{{ $input }}" placeholder="Catatan proses atau keterangan resep..."></textarea>
                        @error('data.notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2 xl:col-span-2">
                        <label class="{{ $label }}">Akses BOM</label>
                        <select wire:model.live="data.accessType" class="{{ $input }}">
                            <option value="0">Semua pengguna ESB</option>
                            <option value="1">Pengguna tertentu</option>
                        </select>
                    </div>
                    @if((int) ($data['accessType'] ?? 0) === 1)
                        <div class="md:col-span-2 xl:col-span-2">
                            <label class="{{ $label }}">User Access ID</label>
                            <input wire:model="data.selectedUserAccess" class="{{ $input }}" placeholder="Memerlukan API master user ESB" disabled>
                            <p class="mt-1 text-xs text-amber-600">Pilihan user belum aktif karena API daftar user belum tersedia.</p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 lg:p-6">
                <div class="mb-5 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Bahan Penyusun</h3>
                        <p class="text-sm text-gray-500">Minimal satu material untuk membentuk produk hasil.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if(!$isEditing)
                            <button type="button" @click="importModalOpen = true; $wire.loadImportBomOptions(); $nextTick(() => $refs.importBomCodeSearch?.focus())" class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900 dark:bg-indigo-950/50 dark:text-indigo-300">
                                <x-heroicon-o-document-arrow-down class="h-4 w-4" /> Import BOM
                            </button>
                        @endif
                        <button type="button" wire:click="addMaterial" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300">
                            <x-heroicon-o-plus class="h-4 w-4" /> Tambah Bahan
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach($data['bomDetails'] ?? [] as $index => $material)
                        <div wire:key="material-{{ $index }}" class="rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                            <div class="mb-4 flex items-center justify-between">
                                <p class="font-semibold text-gray-800 dark:text-gray-100">Bahan {{ $index + 1 }}</p>
                                @if(count($data['bomDetails']) > 1)
                                    <button type="button" wire:click="removeMaterial({{ $index }})" class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30" title="Hapus bahan">
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-9">
                                <div class="sm:col-span-2 lg:col-span-3 2xl:col-span-2">
                                    <label class="{{ $label }}">Product Name *</label>
                                    @php
                                        $selectedMaterial = $selectedProducts[(int) ($material['productDetailID'] ?? 0)] ?? null;
                                    @endphp
                                    <button type="button" @click="$dispatch('open-product-picker', { target: 'material', index: {{ $index }} })" class="flex min-h-10 w-full overflow-hidden rounded-lg border border-gray-300 bg-white text-left text-sm hover:border-blue-400 dark:border-gray-600 dark:bg-gray-800">
                                        <span class="min-w-0 flex-1 truncate px-3 py-2 {{ $selectedMaterial ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">{{ $selectedMaterial['productName'] ?? 'Pilih produk bahan' }}</span>
                                        <span class="flex w-10 shrink-0 items-center justify-center bg-blue-600 font-bold text-white">...</span>
                                    </button>
                                    @error("data.bomDetails.$index.productDetailID") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Product Code</label>
                                    <input value="{{ $selectedMaterial['productCode'] ?? '' }}" readonly class="{{ $input }} bg-white text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Unit</label>
                                    <input value="{{ ($selectedMaterial['baseUnit'] ?? '') ?: ($selectedMaterial['unit'] ?? '') }}" readonly class="{{ $input }} bg-white text-center font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Qty *</label>
                                    <input wire:model="data.bomDetails.{{ $index }}.qty" type="number" min="0.0001" step="0.0001" class="{{ $input }}">
                                    @error("data.bomDetails.$index.qty") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Last HPP *</label>
                                    <input wire:model="data.bomDetails.{{ $index }}.lastHPP" type="number" min="0" step="0.0001" class="{{ $input }}">
                                    @error("data.bomDetails.$index.lastHPP") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Waste / Yield (%)</label>
                                    <input wire:model="data.bomDetails.{{ $index }}.yieldPercent" type="number" min="0" max="100" step="0.0001" class="{{ $input }}">
                                    @error("data.bomDetails.$index.yieldPercent") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Tolerance (%)</label>
                                    <input wire:model="data.bomDetails.{{ $index }}.tolerancePercent" type="number" min="0" max="100" step="0.0001" class="{{ $input }}">
                                    @error("data.bomDetails.$index.tolerancePercent") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Print Group</label>
                                    <input wire:model="data.bomDetails.{{ $index }}.printGroup" class="{{ $input }}" placeholder="Opsional">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                <a href="{{ \App\Filament\Helpdesk\Pages\BillOfMaterialPage::getUrl() }}" class="rounded-xl border border-gray-300 px-5 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Batal</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="create"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-600 bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <x-heroicon-o-paper-airplane class="h-5 w-5" />
                    <span wire:loading.remove wire:target="create">{{ $isEditing ? 'Update BOM' : 'Kirim ke ESB' }}</span>
                    <span wire:loading wire:target="create">{{ $isEditing ? 'Memperbarui...' : 'Mengirim...' }}</span>
                </button>
            </div>
        </form>

        @if(!$isEditing)
            <template x-teleport="body">
                <div x-show="importModalOpen" x-cloak class="fixed inset-0 z-[110] flex items-center justify-center p-3 sm:p-6">
                    <div x-show="importModalOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/50" @click="importModalOpen = false"></div>
                    <div x-show="importModalOpen" x-transition class="relative flex max-h-[88vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" @click.stop>
                        <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                            <div>
                                <h3 class="font-bold text-gray-900 dark:text-white">Import Bahan dari BOM</h3>
                                <p class="mt-1 text-sm text-gray-500">Pilih BOM sumber. Hanya bahan penyusun yang akan disalin.</p>
                            </div>
                            <button type="button" @click="importModalOpen = false" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>
                        <div class="relative min-h-0 flex-1 overflow-auto">
                            <div
                                wire:loading.flex
                                wire:target="loadImportBomOptions"
                                class="absolute inset-0 z-40 items-center justify-center bg-white/80 backdrop-blur-[2px] dark:bg-gray-900/80"
                                role="status"
                                aria-label="Memuat daftar BOM"
                            >
                                <div class="relative h-14 w-14">
                                    <div class="absolute inset-0 rounded-full border-4 border-blue-100 dark:border-blue-950"></div>
                                    <div class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-t-blue-600 border-r-blue-400"></div>
                                    <div class="absolute inset-[10px] animate-[spin_1.2s_linear_infinite_reverse] rounded-full border-2 border-transparent border-b-blue-500"></div>
                                </div>
                                <span class="sr-only">Memuat daftar BOM...</span>
                            </div>
                            @php
                                $importUnitOptions = collect($importBomOptions)->pluck('uomName')->filter()->unique()->sort()->values();
                                $importTypeOptions = collect($importBomOptions)->pluck('bomTypeName')->filter()->unique()->sort()->values();
                            @endphp
                            <table class="w-full min-w-[760px] text-sm">
                                <thead class="sticky top-0 z-10 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 pt-3 text-left">Kode BOM</th>
                                        <th class="px-4 pt-3 text-left">Nama BOM</th>
                                        <th class="px-4 pt-3 text-left">Produk Hasil</th>
                                        <th class="px-4 pt-3 text-left">Unit</th>
                                        <th class="px-4 pt-3 text-left">Tipe</th>
                                    </tr>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-4 pb-3 pt-2"><input x-ref="importBomCodeSearch" wire:model.live.debounce.200ms="importBomCodeSearch" type="search" placeholder="Cari kode..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900"></th>
                                        <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.200ms="importBomNameSearch" type="search" placeholder="Cari nama..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900"></th>
                                        <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.200ms="importBomProductSearch" type="search" placeholder="Cari produk..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900"></th>
                                        <th class="relative px-4 pb-3 pt-2">
                                            <button type="button" @click="importFilterDropdown = importFilterDropdown === 'unit' ? null : 'unit'; importUnitOptionSearch = ''" class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-2.5 py-2 text-left text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                                <span class="truncate">{{ $importBomUnitSearch ?: '- Semua Unit -' }}</span>
                                                <x-heroicon-o-chevron-down class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                            </button>
                                            <div x-show="importFilterDropdown === 'unit'" x-cloak @click.outside="importFilterDropdown = null" class="absolute left-4 right-4 top-[calc(100%-0.5rem)] z-30 overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                                <div class="border-b border-gray-200 p-2 dark:border-gray-700"><input x-model="importUnitOptionSearch" type="search" placeholder="Cari unit..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-800"></div>
                                                <div class="max-h-52 overflow-y-auto p-1">
                                                    <button type="button" @click="$wire.set('importBomUnitSearch', ''); importFilterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold hover:bg-blue-50 dark:hover:bg-blue-950/30">- Semua Unit -</button>
                                                    @foreach($importUnitOptions as $option)
                                                        <button type="button" data-label="{{ mb_strtolower($option) }}" x-show="$el.dataset.label.includes(importUnitOptionSearch.toLowerCase())" @click="$wire.set('importBomUnitSearch', @js($option)); importFilterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-normal hover:bg-blue-50 dark:hover:bg-blue-950/30">{{ $option }}</button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </th>
                                        <th class="relative px-4 pb-3 pt-2">
                                            <button type="button" @click="importFilterDropdown = importFilterDropdown === 'type' ? null : 'type'; importTypeOptionSearch = ''" class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-2.5 py-2 text-left text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                                <span class="truncate">{{ $importBomTypeSearch ?: '- Semua Tipe -' }}</span>
                                                <x-heroicon-o-chevron-down class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                            </button>
                                            <div x-show="importFilterDropdown === 'type'" x-cloak @click.outside="importFilterDropdown = null" class="absolute left-4 right-4 top-[calc(100%-0.5rem)] z-30 overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                                <div class="border-b border-gray-200 p-2 dark:border-gray-700"><input x-model="importTypeOptionSearch" type="search" placeholder="Cari tipe..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-800"></div>
                                                <div class="max-h-52 overflow-y-auto p-1">
                                                    <button type="button" @click="$wire.set('importBomTypeSearch', ''); importFilterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold hover:bg-blue-50 dark:hover:bg-blue-950/30">- Semua Tipe -</button>
                                                    @foreach($importTypeOptions as $option)
                                                        <button type="button" data-label="{{ mb_strtolower($option) }}" x-show="$el.dataset.label.includes(importTypeOptionSearch.toLowerCase())" @click="$wire.set('importBomTypeSearch', @js($option)); importFilterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-normal hover:bg-blue-50 dark:hover:bg-blue-950/30">{{ $option }}</button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($this->importBomRows() as $bomOption)
                                        <tr
                                            @click="$wire.set('importBomId', '{{ $bomOption['bomID'] }}'); $wire.importBomMaterials().then(() => importModalOpen = false)"
                                            class="cursor-pointer text-gray-700 transition hover:bg-blue-50 dark:text-gray-200 dark:hover:bg-blue-950/30"
                                        >
                                            <td class="px-5 py-3 font-mono font-semibold text-blue-700 dark:text-blue-300">{{ $bomOption['bomCode'] ?: '-' }}</td>
                                            <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $bomOption['bomName'] }}</td>
                                            <td class="px-5 py-3 text-gray-700 dark:text-gray-200">{{ $bomOption['productName'] ?: '-' }}</td>
                                            <td class="px-5 py-3">{{ $bomOption['uomName'] ?: '-' }}</td>
                                            <td class="px-5 py-3"><span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ $bomOption['bomTypeName'] ?? 'Assembly' }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if($importBomOptions === [])
                                <div wire:loading.remove wire:target="loadImportBomOptions" class="py-14 text-center text-sm text-gray-500">Belum ada BOM yang dapat dipilih.</div>
                            @endif
                        </div>

                        @php
                            $importBomTotal = count($this->filteredImportBoms());
                            $importBomLastPage = max(1, (int) ceil($importBomTotal / $importBomPerPage));
                            $importPageStart = max(1, $importBomPage - 4);
                            $importPageEnd = min($importBomLastPage, $importPageStart + 8);
                            $importPageStart = max(1, $importPageEnd - 8);
                        @endphp
                        <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-300">Halaman {{ $importBomPage }} dari {{ $importBomLastPage }}</p>
                            <div class="max-w-full overflow-x-auto">
                                <div class="inline-flex min-w-max overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                                    <button type="button" @click="$wire.goToImportBomPage(1)" @disabled($importBomPage <= 1) class="border-r border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">First</button>
                                    <button type="button" @click="$wire.goToImportBomPage({{ max(1, $importBomPage - 1) }})" @disabled($importBomPage <= 1) class="border-r border-gray-300 px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">&laquo;</button>
                                    @foreach(range($importPageStart, $importPageEnd) as $pageNumber)
                                        <button type="button" @click="$wire.goToImportBomPage({{ $pageNumber }})" @disabled($pageNumber === $importBomPage) class="border-r border-gray-300 px-3.5 py-2 text-xs font-semibold transition dark:border-gray-600 {{ $pageNumber === $importBomPage ? 'bg-blue-600 text-white disabled:cursor-default disabled:opacity-100' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800' }}">{{ $pageNumber }}</button>
                                    @endforeach
                                    <button type="button" @click="$wire.goToImportBomPage({{ min($importBomLastPage, $importBomPage + 1) }})" @disabled($importBomPage >= $importBomLastPage) class="border-r border-gray-300 px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">&raquo;</button>
                                    <button type="button" @click="$wire.goToImportBomPage({{ $importBomLastPage }})" @disabled($importBomPage >= $importBomLastPage) class="px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-200 dark:hover:bg-gray-800">Last</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        @endif

        <template x-teleport="body">
            <div
                x-show="productModalOpen"
                x-cloak
                class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <div x-show="productModalOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/50" @click="productModalOpen = false"></div>

                <div
                    x-show="productModalOpen"
                    x-transition
                    class="relative flex max-h-[90vh] w-full max-w-7xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
                    @click.stop
                >
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Pilih Produk Aktif</h3>
                        </div>
                        <button type="button" @click="productModalOpen = false" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="relative min-h-0 flex-1 overflow-auto">
                        <div
                            wire:loading.flex
                            wire:target="loadProducts"
                            class="absolute inset-0 z-40 items-center justify-center bg-white/80 backdrop-blur-[2px] dark:bg-gray-900/80"
                            role="status"
                            aria-label="Memuat daftar produk"
                        >
                            <div class="relative h-14 w-14">
                                <div class="absolute inset-0 rounded-full border-4 border-blue-100 dark:border-blue-950"></div>
                                <div class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-t-blue-600 border-r-blue-400"></div>
                                <div class="absolute inset-[10px] animate-[spin_1.2s_linear_infinite_reverse] rounded-full border-2 border-transparent border-b-blue-500"></div>
                            </div>
                            <span class="sr-only">Memuat daftar produk...</span>
                        </div>
                        @php
                        @endphp
                        <table class="w-full min-w-[1120px] table-fixed text-sm">
                            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="w-[16%] px-4 pt-3">Product Code</th>
                                    <th class="w-[23%] px-4 pt-3">Product Name</th>
                                    <th class="w-[18%] px-4 pt-3">Kategori</th>
                                    <th class="w-[19%] px-4 pt-3">Subkategori</th>
                                    <th class="w-[10%] px-4 pt-3">Unit</th>
                                    <th class="w-[14%] px-4 pt-3">Conversion Factor</th>
                                </tr>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 pb-3 pt-2"><input x-ref="productCodeSearch" wire:model.live.debounce.700ms="productCodeSearch" type="search" placeholder="Cari semua kode..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900"></th>
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="productSearch" type="search" placeholder="Cari semua nama..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900"></th>
                                    <th class="relative px-4 pb-3 pt-2">
                                        <button type="button" @click="filterDropdown = filterDropdown === 'category' ? null : 'category'; categorySearch = ''" class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-2.5 py-2 text-left text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900">
                                            <span class="truncate">{{ $categoryOptions[(int) $productCategoryId] ?? '- Semua Kategori -' }}</span>
                                            <x-heroicon-o-chevron-down class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        </button>
                                        <div x-show="filterDropdown === 'category'" x-cloak @click.outside="filterDropdown = null" class="absolute left-4 right-4 top-[calc(100%-0.5rem)] z-30 overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                            <div class="border-b border-gray-200 p-2 dark:border-gray-700"><input x-model="categorySearch" type="search" placeholder="Cari kategori..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-800"></div>
                                            <div class="max-h-60 overflow-y-auto p-1">
                                                <button type="button" @click="$wire.set('productCategoryId', ''); filterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold hover:bg-blue-50 dark:hover:bg-blue-950/30">- Semua Kategori -</button>
                                                @foreach($categoryOptions as $categoryId => $categoryName)
                                                    <button type="button" data-label="{{ mb_strtolower($categoryName) }}" x-show="$el.dataset.label.includes(categorySearch.toLowerCase())" @click="$wire.set('productCategoryId', '{{ $categoryId }}'); filterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-normal hover:bg-blue-50 dark:hover:bg-blue-950/30">{{ $categoryName }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </th>
                                    <th class="relative px-4 pb-3 pt-2">
                                        <button type="button" @click="filterDropdown = filterDropdown === 'subcategory' ? null : 'subcategory'; subCategorySearch = ''" class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-2.5 py-2 text-left text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900">
                                            <span class="truncate">{{ $subCategoryOptions[(int) $productSubCategoryId] ?? '- Semua Subkategori -' }}</span>
                                            <x-heroicon-o-chevron-down class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        </button>
                                        <div x-show="filterDropdown === 'subcategory'" x-cloak @click.outside="filterDropdown = null" class="absolute left-4 right-4 top-[calc(100%-0.5rem)] z-30 overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                            <div class="border-b border-gray-200 p-2 dark:border-gray-700"><input x-model="subCategorySearch" type="search" placeholder="Cari subkategori..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-800"></div>
                                            <div class="max-h-60 overflow-y-auto p-1">
                                                <button type="button" @click="$wire.set('productSubCategoryId', ''); filterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold hover:bg-blue-50 dark:hover:bg-blue-950/30">- Semua Subkategori -</button>
                                                @foreach($subCategoryOptions as $subCategoryId => $subCategoryName)
                                                    <button type="button" data-label="{{ mb_strtolower($subCategoryName) }}" x-show="$el.dataset.label.includes(subCategorySearch.toLowerCase())" @click="$wire.set('productSubCategoryId', '{{ $subCategoryId }}'); filterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-normal hover:bg-blue-50 dark:hover:bg-blue-950/30">{{ $subCategoryName }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </th>
                                    <th class="relative px-4 pb-3 pt-2">
                                        <button type="button" @click="filterDropdown = filterDropdown === 'unit' ? null : 'unit'; unitSearch = ''" class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-2.5 py-2 text-left text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900">
                                            <span class="truncate" x-text="unitLabel"></span>
                                            <x-heroicon-o-chevron-down class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        </button>
                                        <div x-show="filterDropdown === 'unit'" x-cloak @click.outside="filterDropdown = null" class="absolute left-4 right-4 top-[calc(100%-0.5rem)] z-30 overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                            <div class="border-b border-gray-200 p-2 dark:border-gray-700"><input x-model="unitSearch" type="search" placeholder="Cari unit..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-800"></div>
                                            <div class="max-h-60 overflow-y-auto p-1">
                                                <button type="button" @click="unitFilter = ''; unitLabel = '- Semua Unit -'; filterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold hover:bg-blue-50 dark:hover:bg-blue-950/30">- Semua Unit -</button>
                                                @foreach($unitOptions as $unit)
                                                    <button type="button" data-label="{{ mb_strtolower($unit) }}" x-show="$el.dataset.label.includes(unitSearch.toLowerCase())" @click="unitFilter = @js(mb_strtolower($unit)); unitLabel = @js($unit); filterDropdown = null" class="block w-full rounded-md px-3 py-2 text-left text-xs font-normal hover:bg-blue-50 dark:hover:bg-blue-950/30">{{ $unit }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </th>
                                    <th class="px-4 pb-3 pt-2">
                                        <input x-model.debounce.150ms="conversionFilter" type="search" placeholder="Cari konversi..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900">
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($products as $product)
                                    <tr
                                        data-unit="{{ mb_strtolower($product['unit']) }}"
                                        data-code="{{ mb_strtolower($product['productCode']) }}"
                                        data-name="{{ mb_strtolower($product['productName']) }}"
                                        data-category="{{ mb_strtolower($product['categoryName']) }}"
                                        data-sub-category="{{ mb_strtolower($product['subCategoryName']) }}"
                                        data-conversion="{{ mb_strtolower((rtrim(rtrim(number_format($product['conversionFactor'], 4, '.', ''), '0'), '.') ?: '0').' '.($product['baseUnit'] ?: $product['unit'])) }}"
                                        x-show="$el.dataset.unit.includes(unitFilter.toLowerCase()) && $el.dataset.conversion.includes(conversionFilter.toLowerCase())"
                                        @click="$wire.selectProduct(productTarget, productIndex, {{ $product['productDetailID'] }}); productModalOpen = false"
                                        class="cursor-pointer text-gray-700 transition hover:bg-blue-50 dark:text-gray-200 dark:hover:bg-blue-950/30"
                                    >
                                        <td class="px-4 py-3"><p class="truncate font-mono font-semibold text-blue-700 dark:text-blue-300">{{ $product['productCode'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><p class="truncate font-semibold text-gray-900 dark:text-white">{{ $product['productName'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><p class="truncate">{{ $product['categoryName'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><p class="truncate">{{ $product['subCategoryName'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ $product['unit'] ?: '-' }}</span></td>
                                        <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">
                                            {{ rtrim(rtrim(number_format($product['conversionFactor'], 4, '.', ''), '0'), '.') ?: '0' }} {{ $product['baseUnit'] ?: $product['unit'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if(empty($products))
                            <div wire:loading.remove wire:target="loadProducts" class="py-14 text-center text-sm text-gray-500">
                                <x-heroicon-o-cube-transparent class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                                Tidak ada produk aktif yang berhasil dimuat.
                            </div>
                        @endif
                    </div>

                    @php
                        $productLastPage = max(1, (int) ceil($productTotal / max(1, $productPerPage)));
                        $pageWindow = 9;
                        $pageStart = max(1, $productPage - intdiv($pageWindow, 2));
                        $pageEnd = min($productLastPage, $pageStart + $pageWindow - 1);
                        $pageStart = max(1, $pageEnd - $pageWindow + 1);
                    @endphp
                    <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                            Halaman {{ number_format($productPage) }} dari {{ number_format(max(1, (int) ceil($productTotal / max(1, $productPerPage)))) }}
                        </p>
                        <div class="max-w-full overflow-x-auto">
                            <div class="inline-flex min-w-max overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                                <button
                                    type="button"
                                    @click="$wire.goToProductPage(1)"
                                    @disabled($productPage <= 1)
                                    class="border-r border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                >
                                    First
                                </button>
                                <button
                                    type="button"
                                    @click="$wire.previousProductPage()"
                                    @disabled($productPage <= 1)
                                    aria-label="Halaman sebelumnya"
                                    class="border-r border-gray-300 px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                >
                                    &laquo;
                                </button>

                                @foreach(range($pageStart, $pageEnd) as $pageNumber)
                                    <button
                                        type="button"
                                        @click="$wire.goToProductPage({{ $pageNumber }})"
                                        @disabled($pageNumber === $productPage)
                                        class="border-r border-gray-300 px-3.5 py-2 text-xs font-semibold transition dark:border-gray-600 {{ $pageNumber === $productPage ? 'bg-blue-600 text-white disabled:cursor-default disabled:opacity-100' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800' }}"
                                    >
                                        {{ $pageNumber }}
                                    </button>
                                @endforeach

                                <button
                                    type="button"
                                    @click="$wire.nextProductPage()"
                                    @disabled(!$productHasNext)
                                    aria-label="Halaman berikutnya"
                                    class="border-r border-gray-300 px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                >
                                    &raquo;
                                </button>
                                <button
                                    type="button"
                                    @click="$wire.goToProductPage({{ $productLastPage }})"
                                    @disabled($productPage >= $productLastPage)
                                    class="px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-200 dark:hover:bg-gray-800"
                                >
                                    Last
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-filament-panels::page>
