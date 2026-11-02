{{--
    Mobile-first product picker shared by the Item Journal and Receiving Tanpa PO tiles. The host uses
    ManagesEsbStockProductPicker and passes $pickerDescription and $pickerSelectMethod (called with
    the Product Detail ID).
--}}
@if($productPickerOpen)
    <div wire:init="loadProducts" class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6">
        <button type="button" aria-label="Tutup pemilih produk" class="absolute inset-0 bg-slate-950/55" wire:click="closeProductPicker"></button>
        <div class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-t-3xl border border-gray-200 bg-white sm:rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <div><h3 class="font-bold text-gray-900 dark:text-white">Pilih Produk</h3><p class="mt-0.5 text-xs text-gray-500">{{ $pickerDescription }}</p></div>
                <button type="button" wire:click="closeProductPicker" class="rounded-xl border border-gray-200 p-2 text-gray-500 dark:border-gray-700"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
            </div>
            <div class="grid gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 dark:border-gray-800">
                <label class="relative"><x-heroicon-o-magnifying-glass class="absolute left-3 top-3 h-4 w-4 text-gray-400" /><input type="search" wire:model.live.debounce.700ms="productSearch" class="w-full rounded-xl border border-gray-200 py-2.5 pl-9 pr-3 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Cari nama produk"></label>
                <label class="relative"><x-heroicon-o-qr-code class="absolute left-3 top-3 h-4 w-4 text-gray-400" /><input type="search" wire:model.live.debounce.700ms="productCodeSearch" class="w-full rounded-xl border border-gray-200 py-2.5 pl-9 pr-3 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Cari kode produk"></label>
            </div>
            @if($stockLoadWarning)
                <div class="border-b border-amber-100 bg-amber-50 px-5 py-3 text-xs text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">{{ $stockLoadWarning }}</div>
            @elseif(blank($branchId) || blank($locationId))
                <div class="border-b border-blue-100 bg-blue-50 px-5 py-3 text-xs text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300">Pilih cabang dan lokasi untuk melihat stok saat ini.</div>
            @endif
            <div class="relative min-h-[300px] flex-1 overflow-y-auto">
                <div wire:loading.flex wire:target="loadProducts,productSearch,productCodeSearch,previousProductPage,nextProductPage" class="absolute inset-0 z-20 items-center justify-center bg-white/85 dark:bg-gray-900/85" role="status">
                    <div class="text-center"><div class="mx-auto h-11 w-11 animate-spin rounded-full border-4 border-blue-100 border-r-blue-500 border-t-blue-600 dark:border-blue-950"></div><p class="mt-3 text-xs font-semibold text-blue-600">Memuat produk ESB...</p></div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($productOptions as $product)
                        <button type="button" wire:key="journal-product-{{ $product['productDetailID'] }}" wire:click="{{ $pickerSelectMethod }}({{ $product['productDetailID'] }})" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-blue-50 dark:hover:bg-blue-950/20">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $product['productName'] }}</span>
                                <span class="mt-1 block text-xs text-gray-500"><span class="font-mono text-blue-600">{{ $product['productCode'] }}</span> · {{ $product['unit'] ?: '-' }}</span>
                                <span class="mt-1 block text-xs font-medium {{ $product['availableStock'] !== null ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                    Stok saat ini: {{ $product['availableStock'] !== null ? ((float) $product['availableStock'] === 0.0 ? '0' : rtrim(rtrim(number_format((float) $product['availableStock'], 4, ',', '.'), '0'), ',')).' '.($product['availableStockUnit'] ?: $product['unit']) : 'tidak tersedia' }}
                                </span>
                            </span>
                            <x-heroicon-o-plus-circle class="h-5 w-5 shrink-0 text-blue-500" />
                        </button>
                    @empty
                        <div class="px-5 py-16 text-center"><x-heroicon-o-cube-transparent class="mx-auto h-9 w-9 text-gray-300" /><p class="mt-3 text-sm font-semibold text-gray-600 dark:text-gray-300">Produk tidak ditemukan</p><p class="mt-1 text-xs text-gray-400">Coba gunakan nama atau kode yang berbeda.</p></div>
                    @endforelse
                </div>
            </div>
            @php
                $productLastPage = max(1, (int) ceil($productTotal / max(1, $productPerPage)));
            @endphp
            <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3 dark:border-gray-800">
                <p class="text-xs font-medium text-gray-500">Halaman {{ $productPage }} dari {{ $productLastPage }} · {{ number_format($productTotal) }} produk</p>
                <div class="flex gap-2"><button type="button" wire:click="previousProductPage" @disabled($productPage <= 1) class="rounded-xl border border-gray-200 p-2 text-gray-600 disabled:opacity-30 dark:border-gray-700"><x-heroicon-o-chevron-left class="h-4 w-4" /></button><button type="button" wire:click="nextProductPage" @disabled(!$productHasNext) class="rounded-xl border border-gray-200 p-2 text-gray-600 disabled:opacity-30 dark:border-gray-700"><x-heroicon-o-chevron-right class="h-4 w-4" /></button></div>
            </div>
        </div>
    </div>
@endif
