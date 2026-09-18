<x-filament-panels::page>
    <div wire:init="loadCategories">
        <x-filament::section heading="Product Category Visibility" description="Satu pengaturan kategori untuk seluruh cabang. Kategori dari semua Company Code digabung berdasarkan nama; perbedaan huruf besar/kecil dan spasi diabaikan. Rincian Stock Movement tetap lengkap.">
            <form wire:submit="save" class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="space-y-1">
                        <h2 class="text-sm font-semibold">Product Categories</h2>
                        <p class="text-sm text-gray-500">{{ count($categoryOptions) }} kategori tersedia · {{ count($selectedCategories) }} kategori dipilih</p>
                    </div>
                    <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-path" wire:click="refreshCategories" wire:loading.attr="disabled" wire:target="loadCategories,refreshCategories,save">
                        Refresh Categories
                    </x-filament::button>
                </div>
                <div wire:loading.flex wire:target="loadCategories,refreshCategories" role="status" class="items-center gap-2 text-sm text-gray-500">
                    <x-filament::loading-indicator class="h-5 w-5" /> Memuat kategori produk dari seluruh Company Code…
                </div>
                @if($failedCompanies !== [])
                    <p role="alert" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        Kategori gagal diperbarui untuk {{ implode(', ', $failedCompanies) }}. Kategori terakhir yang tersedia tetap dipertahankan. Coba Refresh Categories.
                    </p>
                @endif
                <fieldset @disabled(! auth()->user()?->can('edit stock card settings')) class="space-y-4">
                    <label class="flex items-center gap-3 text-sm"><input type="checkbox" wire:model.live="allCategories" class="rounded border-gray-300">All Categories</label>
                    @if(! $allCategories)
                        <div class="space-y-3">
                            <label for="category-search" class="text-sm font-medium">Search Categories</label>
                            <input id="category-search" type="search" wire:model.live.debounce.300ms="categorySearch" placeholder="Cari nama kategori…" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900">
                            <div class="grid max-h-80 grid-cols-1 gap-3 overflow-y-auto rounded-lg border border-gray-200 p-4 sm:grid-cols-2 dark:border-gray-700">
                                @forelse($this->visibleCategories() as $category)
                                    <label wire:key="category-{{ md5($category) }}" class="flex min-w-0 items-start gap-3 text-sm"><input type="checkbox" wire:model.live="selectedCategories" value="{{ $category }}" class="mt-0.5 rounded border-gray-300"><span class="break-words">{{ $category }}</span></label>
                                @empty
                                    <p class="text-sm text-gray-500">Tidak ada kategori yang sesuai.</p>
                                @endforelse
                            </div>
                            @error('selectedCategories') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @foreach($errors->get('selectedCategories.*') as $messages) @foreach($messages as $message) <p class="text-sm text-red-600">{{ $message }}</p> @endforeach @endforeach
                        </div>
                    @endif
                    <label class="flex items-center gap-3 text-sm"><input type="checkbox" wire:model="showUncategorized" class="rounded border-gray-300">Show Uncategorized Products</label>
                </fieldset>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                    Pengaturan global berlaku untuk laporan baru dan dicatat saat laporan dibuat. Pengaturan dan input laporan lama tetap dipertahankan. Produk tetap diambil sesuai cabang staff.
                </div>
                @can('edit stock card settings')
                    <div class="flex justify-end"><x-filament::button type="submit" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="save,loadCategories,refreshCategories">Save Settings</x-filament::button></div>
                @endcan
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
