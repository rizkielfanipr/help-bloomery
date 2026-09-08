<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $lastPage = max(1, $rows->lastPage());
        $pageStart = max(1, $rows->currentPage() - 3);
        $pageEnd = min($lastPage, $pageStart + 6);
        $pageStart = max(1, $pageEnd - 6);
    @endphp

    <div class="space-y-5">
        <section class="rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-5 dark:border-blue-900 dark:from-blue-950/30 dark:to-indigo-950/30">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Research & Development</p>
                    <h2 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Shelf Life Produk</h2>
                    <p class="mt-1 text-sm text-gray-500">Daftar shelf life seluruh produk yang dibuat pada project R&D.</p>
                </div>
                <a href="{{ route('helpdesk.exports.shelf-life', $this->exportParameters()) }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                    Export Excel
                </a>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-3 border-b border-gray-200 p-4 md:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_auto] dark:border-gray-700">
                <input wire:model.live.debounce.500ms="search" type="search" placeholder="Cari nama produk, SKU, atau project..." class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <select wire:model.live="storageCondition" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Semua penyimpanan</option>
                    @foreach(\App\Models\RndProjectProduct::STORAGE_CONDITIONS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Semua status</option>
                    @foreach(\App\Models\RndProjectProduct::STATUSES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="perPage" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="20">20 per halaman</option>
                    <option value="50">50 per halaman</option>
                </select>
            </div>

            <div wire:loading.flex wire:target="search,storageCondition,status,perPage" class="items-center gap-2 border-b border-blue-100 bg-blue-50 px-4 py-2 text-xs font-semibold text-blue-700">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-r-blue-600"></span>
                Memuat data shelf life...
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1080px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left">Produk</th>
                            <th class="px-4 py-3 text-left">Project R&D</th>
                            <th class="px-4 py-3 text-left">Shelf Life</th>
                            <th class="px-4 py-3 text-left">Penyimpanan</th>
                            <th class="px-4 py-3 text-left">Catatan</th>
                            <th class="px-4 py-3 text-left">Tanggal Rilis</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($rows as $product)
                            @php
                                $unit = \App\Models\RndProjectProduct::SHELF_LIFE_UNITS[$product->shelf_life_unit] ?? null;
                                $storage = \App\Models\RndProjectProduct::STORAGE_CONDITIONS[$product->storage_condition] ?? null;
                                $statusLabel = \App\Models\RndProjectProduct::STATUSES[$product->status] ?? $product->status;
                            @endphp
                            <tr class="hover:bg-blue-50/50 dark:hover:bg-blue-950/20">
                                <td class="px-4 py-3">
                                    <p class="font-bold text-gray-900 dark:text-white">{{ $product->name }}</p>
                                    <p class="mt-0.5 font-mono text-xs font-semibold text-blue-600">{{ $product->product_code ?: 'SKU belum diisi' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $product->project?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if($product->shelf_life_value && $unit)
                                        <span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ $product->shelf_life_value }} {{ $unit }}</span>
                                    @else
                                        <span class="text-xs font-semibold text-gray-400">Belum diatur</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $storage ?? '-' }}</td>
                                <td class="max-w-xs px-4 py-3 text-gray-600 dark:text-gray-300"><p class="line-clamp-2">{{ $product->storage_notes ?: '-' }}</p></td>
                                <td class="px-4 py-3">{{ $product->release_date?->format('d M Y') ?? '-' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $statusLabel }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-16 text-center text-gray-500"><x-heroicon-o-clock class="mx-auto mb-3 h-10 w-10 text-gray-300" />Belum ada produk yang sesuai dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
                <p class="text-xs text-gray-500">Halaman {{ $rows->currentPage() }} dari {{ $lastPage }} · {{ number_format($rows->total()) }} produk</p>
                <div class="inline-flex max-w-full overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-600">
                    <button wire:click="goToPage(1)" @disabled($rows->onFirstPage()) class="border-r px-3 py-2 text-xs disabled:opacity-40 dark:border-gray-600">First</button>
                    @foreach(range($pageStart, $pageEnd) as $pageNumber)
                        <button wire:click="goToPage({{ $pageNumber }})" class="border-r px-3 py-2 text-xs dark:border-gray-600 {{ $pageNumber === $rows->currentPage() ? 'bg-blue-600 text-white' : '' }}">{{ $pageNumber }}</button>
                    @endforeach
                    <button wire:click="goToPage({{ $lastPage }})" @disabled(!$rows->hasMorePages()) class="px-3 py-2 text-xs disabled:opacity-40">Last</button>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
