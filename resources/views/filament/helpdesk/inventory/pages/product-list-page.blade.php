<x-filament-panels::page>
    <div class="space-y-4">
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="relative overflow-x-auto">
                <div
                    wire:loading.flex
                    wire:target="loadProducts, productSearch, productCodeSearch, previousProductPage, nextProductPage, goToProductPage"
                    class="absolute inset-0 z-40 items-center justify-center bg-white/80 backdrop-blur-[2px] dark:bg-gray-900/80"
                    role="status"
                    aria-label="Memuat daftar produk"
                >
                    <div class="relative h-14 w-14">
                        <div class="absolute inset-0 rounded-full border-4 border-primary-100 dark:border-primary-950"></div>
                        <div class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-r-primary-400 border-t-primary-600"></div>
                        <div class="absolute inset-[10px] animate-[spin_1.2s_linear_infinite_reverse] rounded-full border-2 border-transparent border-b-primary-500"></div>
                    </div>
                    <span class="sr-only">Memuat daftar produk...</span>
                </div>

                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/60">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 pb-2 pt-3">Kode</th>
                            <th class="px-4 pb-2 pt-3">Nama Produk</th>
                            <th class="px-4 pb-2 pt-3">Kategori</th>
                            <th class="px-4 pb-2 pt-3">Sub Kategori</th>
                            <th class="px-4 pb-2 pt-3">Unit</th>
                            <th class="px-4 pb-2 pt-3">Kedaluwarsa</th>
                            <th class="px-4 pb-2 pt-3">Lokasi</th>
                            <th class="px-4 pb-2 pt-3 text-right">Aksi</th>
                        </tr>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 pb-3 pt-1">
                                <input wire:model.live.debounce.700ms="productCodeSearch" type="search" placeholder="Cari semua kode..."
                                    class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900">
                            </th>
                            <th class="px-4 pb-3 pt-1">
                                <input wire:model.live.debounce.700ms="productSearch" type="search" placeholder="Cari semua nama..."
                                    class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900">
                            </th>
                            <th class="px-4 pb-3 pt-1"></th>
                            <th class="px-4 pb-3 pt-1"></th>
                            <th class="px-4 pb-3 pt-1"></th>
                            <th class="px-4 pb-3 pt-1"></th>
                            <th class="px-4 pb-3 pt-1"></th>
                            <th class="px-4 pb-3 pt-1"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($products as $product)
                            @php
                                $setting = $productSettingsByCode[$product['productCode']] ?? null;
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $product['productCode'] }}</td>
                                <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ $product['productName'] }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $product['categoryName'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $product['subCategoryName'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $product['unit'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                    {{ $setting['expiry_days'] ?? null ? $setting['expiry_days'].' hari' : '—' }}
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                    {{ ($setting['locations_count'] ?? 0) > 0 ? $setting['locations_count'].' lokasi' : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button
                                            type="button"
                                            wire:click="openSettingsModal('{{ $product['productCode'] }}', '{{ addslashes($product['productName']) }}')"
                                            class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                            title="Pengaturan"
                                        >
                                            <x-heroicon-o-cog-6-tooth class="h-4 w-4" />
                                        </button>
                                        <a
                                            href="{{ route('helpdesk.products.label-pdf', ['code' => $product['productCode']]) }}"
                                            target="_blank"
                                            class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                            title="Cetak Label"
                                        >
                                            <x-heroicon-o-qr-code class="h-4 w-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada produk ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
                    Halaman {{ number_format($productPage) }} dari {{ number_format($productLastPage) }} &middot; {{ number_format($productTotal) }} produk
                </p>
                <div class="max-w-full overflow-x-auto">
                    <div class="inline-flex min-w-max overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                        <button type="button" wire:click="goToProductPage(1)" @disabled($productPage <= 1)
                            class="border-r border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                            First
                        </button>
                        <button type="button" wire:click="previousProductPage" @disabled($productPage <= 1)
                            aria-label="Halaman sebelumnya"
                            class="border-r border-gray-300 px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            &laquo;
                        </button>

                        @foreach (range($pageStart, $pageEnd) as $pageNumber)
                            <button type="button" wire:click="goToProductPage({{ $pageNumber }})" @disabled($pageNumber === $productPage)
                                class="border-r border-gray-300 px-3.5 py-2 text-xs font-semibold transition dark:border-gray-600 {{ $pageNumber === $productPage ? 'bg-primary-600 text-white disabled:cursor-default disabled:opacity-100' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800' }}">
                                {{ $pageNumber }}
                            </button>
                        @endforeach

                        <button type="button" wire:click="nextProductPage" @disabled(! $productHasNext)
                            aria-label="Halaman berikutnya"
                            class="border-r border-gray-300 px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            &raquo;
                        </button>
                        <button type="button" wire:click="goToProductPage({{ $productLastPage }})" @disabled($productPage >= $productLastPage)
                            class="px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-200 dark:hover:bg-gray-800">
                            Last
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($showSettingsModal)
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4">
            <button type="button" aria-label="Tutup modal" class="absolute inset-0 bg-slate-950/55" wire:click="cancelSettingsModal"></button>
            <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Pengaturan Produk</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $editingProductName }} &middot; {{ $editingProductCode }}</p>
                    </div>
                    <button type="button" wire:click="cancelSettingsModal" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>

                <form wire:submit.prevent="saveSettings" class="space-y-4 p-5">
                    {{ $this->settingsForm }}

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="cancelSettingsModal"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            Batal
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-filament-panels::page>
