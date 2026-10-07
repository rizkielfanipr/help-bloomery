{{--
    Shelf Life WIP of this Product (docs/rnd-wip-shelf-life-prd.md §17). Read-only for everyone who
    can view the Project; only users allowed to create a missing master see "Isi Shelf Life".
    Data comes from ManagesProjectWipShelfLife::wipShelfLifeRows() — no query or ESB call here.
--}}
<section wire:init="loadAllBomComponents" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-labelledby="wip-shelf-life-heading-section">
    <div class="flex flex-col gap-1 border-b border-gray-200 p-5 dark:border-gray-700">
        <div class="flex items-center gap-2">
            <x-heroicon-o-clock class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            <h3 id="wip-shelf-life-heading-section" class="text-lg font-bold text-gray-900 dark:text-white">Shelf Life WIP</h3>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">Masa simpan dibaca otomatis dari master Shelf Life WIP. Seluruh WIP wajib lengkap sebelum produk Ready/Released.</p>
    </div>

    <div class="relative p-5">
        @if(! $bomComponentsInitialized)
            <div class="flex items-center justify-center gap-2 py-8 text-sm text-gray-500 dark:text-gray-400" role="status">
                <x-filament::loading-indicator class="h-5 w-5" /> Memetakan WIP...
            </div>
        @else
            <div wire:loading.flex wire:target="refreshWipComponentRecipes,saveWipShelfLife" class="absolute inset-0 z-10 hidden items-center justify-center rounded-b-2xl bg-white/70 dark:bg-gray-900/70" role="status">
                <span class="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400"><x-filament::loading-indicator class="h-4 w-4" /> Memuat...</span>
            </div>

            @if($autoWipComponentError)
                <p class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300" role="alert">
                    <x-heroicon-o-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0" />
                    Mapping WIP belum selesai, sehingga daftar WIP mungkin belum lengkap. Shelf Life tidak ditebak dari nama.
                </p>
            @endif

            @php
                $wipRows = $this->wipShelfLifeRows();
                $canCreateWipShelfLife = $this->canCreateWipShelfLife() && ! $project->trashed();
            @endphp

            @if($wipRows === [])
                <p class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">Produk ini belum memakai WIP, sehingga tidak ada Shelf Life WIP yang perlu dilengkapi.</p>
            @else
                <ul class="grid gap-3 lg:grid-cols-2">
                    @foreach($wipRows as $row)
                        <li wire:key="wip-shelf-life-{{ $row['key'] }}" class="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="break-words font-semibold text-gray-900 dark:text-white">{{ $row['product_name'] }}</p>
                                    <p class="break-words text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['product_code'] !== '' ? $row['product_code'] : '-' }}
                                        @if($row['product_detail_id']) · Product Detail #{{ $row['product_detail_id'] }} @endif
                                        @if($row['uom_name'] !== '') · {{ $row['uom_name'] }} @endif
                                    </p>
                                </div>
                                <x-rnd.wip-shelf-life-status :status="$row['status']" />
                            </div>

                            <p class="break-words text-[11px] text-gray-400">Jalur: {{ implode(' · ', $row['paths']) }}</p>

                            @if($row['master'] && $row['status'] === \App\Enums\RndWipShelfLifeStatus::Complete)
                                <dl class="grid grid-cols-2 gap-2 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800/50">
                                    <div><dt class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Masa Simpan</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $row['master']->shelfLifeLabel() }}</dd></div>
                                    <div><dt class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Penyimpanan</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $row['master']->storageConditionLabel() }}</dd></div>
                                    @if(filled($row['master']->notes))
                                        <div class="col-span-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Catatan</dt><dd class="break-words text-gray-700 dark:text-gray-200">{{ $row['master']->notes }}</dd></div>
                                    @endif
                                </dl>
                            @elseif($row['status'] === \App\Enums\RndWipShelfLifeStatus::Inactive)
                                <p class="text-xs text-gray-500 dark:text-gray-400">Master Shelf Life WIP ini dinonaktifkan. Aktifkan kembali dari menu Shelf Life.</p>
                            @elseif($row['status'] === \App\Enums\RndWipShelfLifeStatus::IdentityIncomplete)
                                <p class="text-xs text-red-600 dark:text-red-400">Product Detail ID WIP tidak tersedia, sehingga Shelf Life belum dapat disimpan. Periksa BOM terkait.</p>
                            @elseif($canCreateWipShelfLife)
                                <button type="button" wire:click="openWipShelfLifeModal({{ $row['product_detail_id'] }})" wire:loading.attr="disabled" wire:target="openWipShelfLifeModal" class="inline-flex items-center justify-center gap-1.5 self-start rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60">
                                    <x-heroicon-o-plus class="h-4 w-4" /> Isi Shelf Life
                                </button>
                            @else
                                <p class="text-xs text-gray-500 dark:text-gray-400">Shelf Life belum diisi. Hubungi tim R&amp;D yang berwenang mengelola BOM.</p>
                            @endif

                            @if(! $row['recipe_resolved'] && $row['product_detail_id'])
                                <p class="flex items-start gap-1.5 text-[11px] text-amber-700 dark:text-amber-300"><x-heroicon-o-exclamation-triangle class="mt-0.5 h-3.5 w-3.5 shrink-0" /> BOM turunan WIP ini tidak ditemukan, sehingga WIP di dalamnya belum dapat dipastikan.</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
</section>

@include('filament.helpdesk.pages.partials.wip-shelf-life-modal', [
    'shelfLifeSaveMethod' => 'saveWipShelfLife',
    'shelfLifeContextNote' => 'Master Shelf Life WIP berlaku untuk seluruh Project dan menu Shelf Life. Disimpan lokal, tidak dikirim ke ESB.',
])
