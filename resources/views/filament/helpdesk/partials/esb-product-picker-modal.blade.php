{{--
    ESB product picker modal, identical for the BOM "Tambah Komponen" and the Memo Internal
    "Tambah Product" flows. The host uses ManagesEsbProductPicker and passes $pickerTitle,
    $pickerSelectMethod (called with the Product Detail ID), and $pickerCloseAction.
--}}
@if($inlineProductModalOpen)
    <div wire:init="loadInlineProducts" class="fixed inset-0 z-[160] flex items-center justify-center p-3 sm:p-6">
        <button type="button" aria-label="Tutup modal" class="absolute inset-0 bg-slate-950/55" wire:click="{{ $pickerCloseAction }}"></button>
        <x-rnd.picker-modal
            :title="$pickerTitle"
            description="Pilih product aktif dari Master Product ESB."
            max-width="7xl"
            x-data="{ unitFilter: '', conversionFilter: '' }"
        >
            <x-slot:close>
                <button type="button" wire:click="{{ $pickerCloseAction }}" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
            </x-slot:close>

            <div class="relative min-h-[280px] flex-1 overflow-auto">
                <div wire:loading.flex wire:target="loadInlineProducts,inlineProductNameSearch,inlineProductCodeSearch,previousInlineProductPage,nextInlineProductPage,goToInlineProductPage" class="absolute inset-0 z-40 items-center justify-center bg-white/80 backdrop-blur-[2px] dark:bg-gray-900/80" role="status">
                    <div class="relative h-14 w-14">
                        <div class="absolute inset-0 rounded-full border-4 border-blue-100 dark:border-blue-950"></div>
                        <div class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-r-blue-400 border-t-blue-600"></div>
                        <div class="absolute inset-[10px] animate-[spin_1.2s_linear_infinite_reverse] rounded-full border-2 border-transparent border-b-blue-500"></div>
                    </div>
                    <span class="sr-only">Memuat daftar produk...</span>
                </div>
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
                            <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="inlineProductCodeSearch" type="search" placeholder="Cari semua kode..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900"></th>
                            <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="inlineProductNameSearch" type="search" placeholder="Cari semua nama..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900"></th>
                            <th class="px-4 pb-3 pt-2">
                                <select wire:model.live="inlineProductCategoryId" class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                    <option value="">- Semua Kategori -</option>
                                    @foreach($inlineProductCategoryOptions as $categoryId => $categoryName)<option value="{{ $categoryId }}">{{ $categoryName }}</option>@endforeach
                                </select>
                            </th>
                            <th class="px-4 pb-3 pt-2">
                                <select wire:model.live="inlineProductSubCategoryId" class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                    <option value="">- Semua Subkategori -</option>
                                    @foreach($inlineProductSubCategoryOptions as $subCategoryId => $subCategoryName)<option value="{{ $subCategoryId }}">{{ $subCategoryName }}</option>@endforeach
                                </select>
                            </th>
                            <th class="px-4 pb-3 pt-2">
                                <select x-model="unitFilter" class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                    <option value="">- Semua -</option>
                                    @foreach($inlineProductUnitOptions as $unit)<option value="{{ mb_strtolower($unit) }}">{{ $unit }}</option>@endforeach
                                </select>
                            </th>
                            <th class="px-4 pb-3 pt-2"><input x-model.debounce.150ms="conversionFilter" type="search" placeholder="Cari konversi..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($inlineProductOptions as $productDetailId => $option)
                            @php $conversionLabel = (rtrim(rtrim(number_format((float) ($option['conversionFactor'] ?? 1), 4, '.', ''), '0'), '.') ?: '0').' '.(($option['baseUnit'] ?? '') ?: ($option['unit'] ?? '')); @endphp
                            <tr
                                wire:key="inline-product-{{ $productDetailId }}"
                                wire:click="{{ $pickerSelectMethod }}({{ $productDetailId }})"
                                data-unit="{{ mb_strtolower($option['unit'] ?? '') }}"
                                data-conversion="{{ mb_strtolower($conversionLabel) }}"
                                x-show="$el.dataset.unit.includes(unitFilter) && $el.dataset.conversion.includes(conversionFilter.toLowerCase())"
                                class="cursor-pointer text-gray-700 transition hover:bg-blue-50 dark:text-gray-200 dark:hover:bg-blue-950/30"
                            >
                                <td class="px-4 py-3"><p class="truncate font-mono font-semibold text-blue-700 dark:text-blue-300">{{ $option['productCode'] ?: '-' }}</p></td>
                                <td class="px-4 py-3"><p class="truncate font-semibold text-gray-900 dark:text-white">{{ $option['productName'] ?: '-' }}</p></td>
                                <td class="px-4 py-3"><p class="truncate">{{ $option['categoryName'] ?: '-' }}</p></td>
                                <td class="px-4 py-3"><p class="truncate">{{ $option['subCategoryName'] ?: '-' }}</p></td>
                                <td class="px-4 py-3"><span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ $option['unit'] ?: '-' }}</span></td>
                                <td class="px-4 py-3 font-medium">{{ $conversionLabel }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center text-gray-500">Product tidak ditemukan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @php
                $inlineProductLastPage = max(1, (int) ceil($inlineProductTotal / max(1, $inlineProductPerPage)));
                $inlinePageStart = max(1, $inlineProductPage - 4);
                $inlinePageEnd = min($inlineProductLastPage, $inlinePageStart + 8);
                $inlinePageStart = max(1, $inlinePageEnd - 8);
            @endphp
            <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-300">Halaman {{ $inlineProductPage }} dari {{ $inlineProductLastPage }}</p>
                <div class="max-w-full overflow-x-auto">
                    <div class="inline-flex min-w-max overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                        <button type="button" wire:click="goToInlineProductPage(1)" @disabled($inlineProductPage <= 1) class="border-r border-gray-300 px-3 py-2 text-xs font-semibold disabled:opacity-40 dark:border-gray-600">First</button>
                        <button type="button" wire:click="goToInlineProductPage({{ max(1, $inlineProductPage - 1) }})" @disabled($inlineProductPage <= 1) class="border-r border-gray-300 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:border-gray-600">&laquo;</button>
                        @foreach(range($inlinePageStart, $inlinePageEnd) as $pageNumber)
                            <button type="button" wire:click="goToInlineProductPage({{ $pageNumber }})" @disabled($pageNumber === $inlineProductPage) class="border-r border-gray-300 px-3.5 py-2 text-xs font-semibold dark:border-gray-600 {{ $pageNumber === $inlineProductPage ? 'bg-blue-600 text-white disabled:opacity-100' : 'hover:bg-gray-50 dark:hover:bg-gray-800' }}">{{ $pageNumber }}</button>
                        @endforeach
                        <button type="button" wire:click="goToInlineProductPage({{ min($inlineProductLastPage, $inlineProductPage + 1) }})" @disabled($inlineProductPage >= $inlineProductLastPage) class="border-r border-gray-300 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:border-gray-600">&raquo;</button>
                        <button type="button" wire:click="goToInlineProductPage({{ $inlineProductLastPage }})" @disabled($inlineProductPage >= $inlineProductLastPage) class="px-3 py-2 text-xs font-semibold disabled:opacity-40">Last</button>
                    </div>
                </div>
            </div>
        </x-rnd.picker-modal>
    </div>
@endif
