<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
        <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">BOM Assembly</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Daftar Assembly yang dikembangkan dalam Project Premix ini.</p>
        </div>
        @if($canManage)
            <button type="button" wire:click="openPremixPicker" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">
                <x-heroicon-o-plus class="h-4 w-4" /> Tambah Assembly
            </button>
        @endif
    </div>
    <div class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($project->premixAssemblies as $bom)
            <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="font-mono text-xs font-bold text-blue-600">{{ $bom->bom_code ?: 'Tanpa kode' }}</p>
                <h4 class="mt-1 font-bold text-gray-900 dark:text-white">{{ $bom->bom_name }}</h4>
                <p class="mt-1 text-sm text-gray-500">{{ $bom->product_name ?: 'Produk hasil belum tersedia' }} · {{ $bom->uom_name ?: '-' }}</p>
                <span class="mt-3 inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">Assembly</span>
                @if($canManage)
                    <button type="button" wire:click="removePremixAssembly({{ $bom->id }})" wire:confirm="Lepas BOM Assembly ini dari Project?" class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/30">
                        <x-heroicon-o-trash class="h-4 w-4" /> Lepas
                    </button>
                @endif
            </article>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-gray-300 py-12 text-center dark:border-gray-700">
                <x-heroicon-o-cube class="mx-auto h-10 w-10 text-gray-300" />
                <h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada BOM Assembly</h4>
                <p class="mt-1 text-sm text-gray-500">Tambahkan Assembly dari katalog lokal R&amp;D.</p>
            </div>
        @endforelse
    </div>
</section>

@if($premixPickerOpen)
    @php($catalog = $this->premixCatalog)
    <div class="fixed inset-0 z-[140] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="premix-picker-title">
        <button type="button" wire:click="closePremixPicker" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup pemilih Assembly"></button>
        <x-rnd.picker-modal title="Tambah BOM Assembly" description="Pilih satu atau beberapa BOM bertipe Assembly dari katalog lokal.">
            <x-slot:close><button type="button" wire:click="closePremixPicker" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
            <div class="border-b border-gray-200 p-4 dark:border-gray-700"><input wire:model.live.debounce.300ms="premixCatalogSearch" type="search" placeholder="Cari nama, kode BOM, atau produk..." class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
            <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
                @forelse($catalog as $item)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <input wire:model="selectedAssemblyIds" type="checkbox" value="{{ $item->id }}" class="mt-1 rounded border-gray-300 text-blue-600">
                        <span class="min-w-0"><span class="block font-bold text-gray-900 dark:text-white">{{ $item->bom_name }}</span><span class="block text-xs text-gray-500">{{ $item->bom_code ?: '-' }} · {{ $item->product_name ?: '-' }} · {{ $item->uom_name ?: '-' }}</span></span>
                    </label>
                @empty
                    <p class="py-10 text-center text-sm text-gray-500">Tidak ada BOM Assembly yang sesuai.</p>
                @endforelse
            </div>
            @if($catalog->hasPages())
                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 text-xs text-gray-500 dark:border-gray-700"><span>Halaman {{ $catalog->currentPage() }} dari {{ $catalog->lastPage() }}</span><div class="flex gap-2"><button type="button" wire:click="previousPremixCatalogPage" @disabled($catalog->onFirstPage()) class="rounded-lg border border-gray-300 px-3 py-1.5 font-bold disabled:opacity-40">Sebelumnya</button><button type="button" wire:click="nextPremixCatalogPage" @disabled(! $catalog->hasMorePages()) class="rounded-lg border border-gray-300 px-3 py-1.5 font-bold disabled:opacity-40">Berikutnya</button></div></div>
            @endif
            <div class="flex items-center justify-between gap-3 border-t border-gray-200 p-4 dark:border-gray-700">
                <span class="text-sm text-gray-500">{{ count($selectedAssemblyIds) }} dipilih</span>
                <div class="flex gap-2"><button type="button" wire:click="closePremixPicker" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold">Batal</button><button type="button" wire:click="addPremixAssemblies" wire:loading.attr="disabled" @disabled($selectedAssemblyIds === []) class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Tambahkan</button></div>
            </div>
        </x-rnd.picker-modal>
    </div>
@endif
