<x-filament-panels::page>
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200';
    @endphp

    <div
        class="w-full max-w-none space-y-4"
        x-data="{ productModalOpen: false, productTarget: 'result', unitFilter: '', unitLabel: '- Semua Unit -', conversionFilter: '', filterDropdown: null, categorySearch: '', subCategorySearch: '', unitSearch: '' }"
        @open-product-picker.window="
            productTarget = $event.detail.target;
            productModalOpen = true;
            $wire.loadProducts();
            $nextTick(() => $refs.productCodeSearch?.focus());
        "
        @keydown.escape.window="productModalOpen = false"
    >
        @if($loading)
            <section class="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                <span class="mx-auto mb-2 block h-6 w-6 animate-spin rounded-full border-2 border-blue-200 border-r-blue-600"></span>
                Memuat detail BOM dari ESB...
            </section>
        @elseif($loadError)
            <section class="rounded-xl border border-red-200 bg-red-50 p-6 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
                <p class="font-semibold">BOM belum dapat dimuat</p>
                <p class="mt-1">{{ $loadError }}</p>
                <button type="button" wire:click="loadDetail" class="mt-3 rounded-lg border border-red-300 px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-100 dark:border-red-800">Coba Lagi</button>
            </section>
        @else
            <div class="space-y-4">
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 lg:p-6">
                    <div class="mb-5">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Informasi Resep</h3>
                        <p class="text-sm text-gray-500">Identitas utama dan produk yang dihasilkan.</p>
                    </div>
                    @unless($this->isMenu())
                        <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(120px,0.6fr)]">
                            <div>
                                <label class="{{ $label }}">Product Name <span class="text-red-500">*</span></label>
                                <button type="button" @click="$dispatch('open-product-picker', { target: 'result' })" class="flex min-h-10 w-full overflow-hidden rounded-lg border border-gray-300 bg-white text-left text-sm hover:border-blue-400 dark:border-gray-600 dark:bg-gray-800">
                                    <span class="min-w-0 flex-1 truncate px-3 py-2 {{ $draft['productName'] ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">{{ $draft['productName'] ?: 'Pilih produk hasil' }}</span>
                                    <span class="flex w-10 shrink-0 items-center justify-center bg-blue-600 font-bold text-white">...</span>
                                </button>
                                @error('draft.productDetailID')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Product Code</label>
                                <input value="{{ $draft['productCode'] ?: '' }}" readonly class="{{ $input }} bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            </div>
                            <div>
                                <label class="{{ $label }}">Unit</label>
                                <input value="{{ $draft['uomName'] ?: '' }}" readonly class="{{ $input }} bg-gray-50 text-center font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">BOM Menu tidak memiliki Product Hasil tunggal di ESB.</p>
                    @endunless
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 lg:p-6">
                    <div class="mb-5 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Komponen Resep</h3>
                            <p class="text-sm text-gray-500">Bahan penyusun BOM ini.</p>
                        </div>
                        <button type="button" @click="$dispatch('open-product-picker', { target: 'component' })" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300">
                            <x-heroicon-o-plus class="h-4 w-4" /> Tambah Komponen
                        </button>
                    </div>
                    @error('draft.bomDetails')<p class="mb-3 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror

                    <div class="space-y-4">
                        @foreach($draft['bomDetails'] ?? [] as $index => $component)
                            <div wire:key="component-row-{{ $index }}" class="rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                <div class="mb-4 flex items-center justify-between">
                                    <p class="font-semibold text-gray-800 dark:text-gray-100">Bahan {{ $index + 1 }}</p>
                                    <button type="button" wire:click="removeComponent({{ $index }})" aria-label="Hapus" title="Hapus" class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30">
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-12">
                                    <div class="sm:col-span-2 lg:col-span-6">
                                        <label class="{{ $label }}">Product Name</label>
                                        <p class="flex min-h-10 items-center truncate rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white">{{ $component['productName'] }}</p>
                                    </div>
                                    <div class="lg:col-span-3">
                                        <label class="{{ $label }}">Product Code</label>
                                        <input value="{{ $component['productCode'] }}" readonly class="{{ $input }} bg-white text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    </div>
                                    <div class="lg:col-span-1">
                                        <label class="{{ $label }}">Unit</label>
                                        <input value="{{ $component['uomName'] }}" readonly class="{{ $input }} bg-white text-center font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    </div>
                                    <div class="lg:col-span-2">
                                        <label class="{{ $label }}">Qty *</label>
                                        <input type="number" step="0.01" wire:model="draft.bomDetails.{{ $index }}.qty" class="{{ $input }}">
                                        @error("draft.bomDetails.{$index}.qty")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 border-t border-gray-200 pt-5 dark:border-gray-700">
                        <label class="{{ $label }}">Alasan Perubahan *</label>
                        <textarea wire:model="reason" rows="3" maxlength="1000" placeholder="Jelaskan alasan perubahan BOM ini..." class="{{ $input }}"></textarea>
                        @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 lg:p-6">
                    <div class="mb-5">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Riwayat Perubahan</h3>
                        <p class="text-sm text-gray-500">Histori perubahan BOM ini, termasuk yang terjadi langsung di ESB.</p>
                    </div>

                    @php
                        $timeline = $this->changeTimeline();
                    @endphp
                    @if($timeline->isEmpty())
                        <p class="text-sm text-gray-400">Belum ada riwayat perubahan untuk BOM ini.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($timeline as $log)
                                @php
                                    $timelineDetail = $this->timelineDetailDiff($log);
                                @endphp
                                <details class="group overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-3.5 [&::-webkit-details-marker]:hidden">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $log->created_at->format('d M Y H:i') }} &middot; {{ $log->changedBy?->display_username ?? 'Tidak diketahui' }}</p>
                                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $log->reason ?: 'Tidak ada alasan yang dicatat.' }}</p>
                                            </div>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <x-filament::badge :color="$log->event->getColor()">{{ $log->event->getLabel() }}</x-filament::badge>
                                            <x-filament::badge :color="$log->status->getColor()">{{ $log->status->getLabel() }}</x-filament::badge>
                                            <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                                        </div>
                                    </summary>

                                    <div class="space-y-3 border-t border-gray-200 p-3.5 dark:border-gray-700">
                                        @if($timelineDetail['rows'] === [])
                                            <p class="text-xs text-gray-400">Tidak ada perubahan komponen terdeteksi.</p>
                                        @else
                                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                                <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                                                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                                                        <tr class="text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                            <th class="px-3 py-2">Component</th>
                                                            <th class="px-3 py-2">Unit</th>
                                                            <th class="px-3 py-2 text-right">Qty Before</th>
                                                            <th class="px-3 py-2 text-right">Qty After</th>
                                                            <th class="px-3 py-2">Note</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                        @foreach($timelineDetail['rows'] as $row)
                                                            <tr>
                                                                <td class="px-3 py-2 font-semibold text-gray-800 dark:text-gray-100">{{ $row['product'] }}</td>
                                                                <td class="px-3 py-2 text-gray-500">{{ $row['unit'] }}</td>
                                                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $row['before'] === null ? '-' : number_format($row['before'], 2, ',', '.') }}</td>
                                                                <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">{{ $row['after'] === null ? '-' : number_format($row['after'], 2, ',', '.') }}</td>
                                                                <td class="px-3 py-2">
                                                                    <span @class([
                                                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $row['note'] === 'Added',
                                                                        'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $row['note'] === 'Removed',
                                                                        'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' => $row['note'] === 'Qty Changed',
                                                                    ])>{{ $row['note'] }}</span>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                        @if($log->error_message)
                                            <p class="text-xs text-red-600 dark:text-red-400"><strong>Error:</strong> {{ $log->error_message }}</p>
                                        @endif
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                </section>

                <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button type="button" wire:click="openPreview" wire:loading.attr="disabled" wire:target="openPreview,submit" class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-600 bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                        <x-heroicon-o-paper-airplane class="h-5 w-5" /> Preview Perubahan
                    </button>
                </div>
            </div>
        @endif

        @if($previewOpen)
            @php
                $diff = $this->previewChanges();
            @endphp
            <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closePreview()">
                <button type="button" wire:click="closePreview" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup preview"></button>
                <x-rnd.picker-modal title="Preview Perubahan BOM" description="Periksa perubahan sebelum dikirim ke ESB." max-width="4xl">
                    <x-slot name="close">
                        <button type="button" wire:click="closePreview" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </x-slot>
                    <div class="space-y-3 overflow-y-auto p-5 text-sm">
                        @if(!$diff['has_changes'])
                            <p class="text-gray-500">Tidak ada perubahan terdeteksi.</p>
                        @else
                            @if($diff['product_result_changed'])
                                <p>Product Result: <strong>{{ data_get($diff, 'product_result.before.productName') }}</strong> &rarr; <strong>{{ data_get($diff, 'product_result.after.productName') }}</strong></p>
                            @endif
                            @if($diff['unit_changed'])
                                <p>Unit: <strong>{{ $diff['unit']['before'] }}</strong> &rarr; <strong>{{ $diff['unit']['after'] }}</strong></p>
                            @endif
                            @foreach($diff['components_added'] as $added)
                                <p class="text-emerald-700 dark:text-emerald-300">+ Komponen ditambahkan: {{ $added['productName'] }}</p>
                            @endforeach
                            @foreach($diff['components_removed'] as $removed)
                                <p class="text-red-700 dark:text-red-300">- Komponen dihapus: {{ $removed['productName'] }}</p>
                            @endforeach
                            @foreach($diff['components_changed'] as $changed)
                                <p>Qty {{ $changed['productName'] }}: <strong>{{ $changed['before_qty'] }}</strong> &rarr; <strong>{{ $changed['after_qty'] }}</strong></p>
                            @endforeach
                        @endif
                        <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <span class="font-semibold">Alasan:</span> {{ $reason }}
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                        <button type="button" wire:click="closePreview" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">Batal</button>
                        <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-600 bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit">Konfirmasi &amp; Kirim ke ESB</span>
                            <span wire:loading wire:target="submit">Mengirim...</span>
                        </button>
                    </div>
                </x-rnd.picker-modal>
            </div>
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

                <x-rnd.picker-modal
                    title="Pilih Produk Aktif"
                    description="Pilih product aktif dari Master Product ESB."
                    max-width="7xl"
                    x-show="productModalOpen"
                    x-transition
                    @click.stop
                >
                    <x-slot:close>
                        <button type="button" @click="productModalOpen = false" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </x-slot:close>

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
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="productNameSearch" type="search" placeholder="Cari semua nama..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900"></th>
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
                                @foreach($productOptions as $product)
                                    <tr
                                        data-unit="{{ mb_strtolower($product['unit']) }}"
                                        data-conversion="{{ mb_strtolower((rtrim(rtrim(number_format($product['conversionFactor'], 4, '.', ''), '0'), '.') ?: '0').' '.($product['baseUnit'] ?: $product['unit'])) }}"
                                        x-show="$el.dataset.unit.includes(unitFilter.toLowerCase()) && $el.dataset.conversion.includes(conversionFilter.toLowerCase())"
                                        @click="$wire.selectProduct(productTarget, {{ $product['productDetailID'] }}); productModalOpen = false"
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

                        @if(empty($productOptions))
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
                </x-rnd.picker-modal>
            </div>
        </template>
    </div>
</x-filament-panels::page>
