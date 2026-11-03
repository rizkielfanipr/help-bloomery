<x-filament-panels::page>
    @php
        $isInternal = $this->isInternal();
        $bundles = $this->bundles();
        $select = 'rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        $tab = fn (bool $active): string => 'inline-flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-semibold transition sm:gap-2 sm:px-3 '.($active
            ? 'bg-white text-blue-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-blue-300 dark:ring-gray-600'
            : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200');
        $bundleRowGrid = 'xl:grid-cols-[minmax(0,1fr)_9rem_8rem_5rem_9rem_7rem]';
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-5 p-5 sm:p-6 md:flex-row md:items-center">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-swatch class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Brand Marketing</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Master Design</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Simpan file desain per Brand dalam bundle berisi item, file desain, dan spesifikasi produksi yang siap diunduh Purchasing.</p>
                    </div>
                </div>
                @if($this->createBundleAction->isVisible())
                    <div class="shrink-0">{{ $this->createBundleAction }}</div>
                @endif
            </div>
            <div class="flex items-center gap-2 border-t border-gray-200 bg-gray-50/60 px-5 py-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800/30 dark:text-gray-400 sm:px-6">
                <x-heroicon-o-information-circle class="h-4 w-4 shrink-0 text-blue-500" />
                <span>{{ $isInternal ? 'Daftar menampilkan bundle aktif; bundle yang diarsipkan tersedia di tab Diarsipkan. Pilih bundle untuk mengelola item, file desain, dan spesifikasi produksi.' : 'Daftar menampilkan bundle aktif. Pilih bundle untuk melihat item, spesifikasi produksi, dan mengunduh file desain.' }}</span>
            </div>
        </section>

        <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div @class(['grid grid-cols-1 gap-3', 'lg:grid-cols-[minmax(0,1fr)_auto]' => $isInternal])>
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari kode, nama, atau deskripsi bundle..." aria-label="Cari kode, nama, atau deskripsi bundle"
                           class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                @if($isInternal)
                    <div class="flex min-w-0 rounded-lg border border-gray-300 bg-gray-50 p-1 dark:border-gray-600 dark:bg-gray-800">
                        <button type="button" wire:click="showActive" class="{{ $tab($statusView === 'active') }}">
                            <x-heroicon-o-list-bullet class="hidden h-4 w-4 sm:block" /> Aktif
                        </button>
                        <button type="button" wire:click="showArchived" class="{{ $tab($statusView === 'archived') }}">
                            <x-heroicon-o-archive-box class="hidden h-4 w-4 sm:block" /> Diarsipkan
                        </button>
                    </div>
                @endif
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <select wire:model.live="brandFilter" aria-label="Filter Brand" class="{{ $select }}">
                    <option value="">Semua Brand</option>
                    @foreach($this->brandOptions() as $brandId => $brandName)
                        <option value="{{ $brandId }}">{{ $brandName }}</option>
                    @endforeach
                </select>
                <select wire:model.live="categoryFilter" aria-label="Filter kategori" class="{{ $select }}">
                    <option value="">Semua Kategori</option>
                    @foreach($this->categoryOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="itemTypeFilter" aria-label="Filter tipe item" class="{{ $select }}">
                    <option value="">Semua Tipe Item</option>
                    @foreach($this->itemTypeOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="natureFilter" aria-label="Filter fisik atau digital" class="{{ $select }}">
                    <option value="">Fisik & Digital</option>
                    <option value="physical">Fisik</option>
                    <option value="digital">Digital</option>
                </select>
            </div>
        </section>

        <div wire:loading.flex wire:target="search,brandFilter,categoryFilter,itemTypeFilter,natureFilter,showActive,showArchived,resetFilters,previousBundlesPage,nextBundlesPage,goToBundlesPage" class="items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <x-filament::loading-indicator class="h-4 w-4" /> Memuat bundle...
        </div>

        @if($bundles->isEmpty())
            <section class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-16 text-center dark:border-gray-700 dark:bg-gray-900">
                @if($this->hasFilters())
                    <x-heroicon-o-magnifying-glass class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-3 font-bold text-gray-800 dark:text-gray-100">Bundle tidak ditemukan.</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ubah kata kunci atau filter, atau reset filter untuk melihat seluruh bundle.</p>
                    <button type="button" wire:click="resetFilters" wire:loading.attr="disabled" wire:target="resetFilters" class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                        <x-heroicon-o-arrow-path class="h-4 w-4" /> Reset Filter
                    </button>
                @elseif($statusView === 'archived')
                    <x-heroicon-o-archive-box class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-3 font-bold text-gray-800 dark:text-gray-100">Belum ada bundle yang diarsipkan.</h3>
                @else
                    <x-heroicon-o-swatch class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-3 font-bold text-gray-800 dark:text-gray-100">Belum ada bundle Master Design.</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bundle desain yang dibuat Brand Marketing akan tampil di sini.</p>
                @endif
            </section>
        @else
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-label="Daftar bundle Master Design">
                <div aria-hidden="true" class="hidden gap-3 border-b border-gray-200 bg-gray-50 px-5 py-2.5 text-xs font-bold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400 xl:grid {{ $bundleRowGrid }}">
                    <span>Bundle</span>
                    <span>Brand</span>
                    <span>Kategori</span>
                    <span>Item</span>
                    <span>Diperbarui</span>
                    <span class="text-right">Aksi</span>
                </div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($bundles as $bundle)
                        @include('filament.helpdesk.marketing-design-bundles.partials.bundle-list-row', ['bundle' => $bundle])
                    @endforeach
                </ul>
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700 xl:px-5">
                    <x-rnd.numbered-pagination :paginator="$bundles" previous-method="previousBundlesPage" next-method="nextBundlesPage" go-to-method="goToBundlesPage" label="bundle" />
                </div>
            </section>
        @endif
    </div>

    {{-- The list replaces the Filament table, which normally renders the action modals. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
