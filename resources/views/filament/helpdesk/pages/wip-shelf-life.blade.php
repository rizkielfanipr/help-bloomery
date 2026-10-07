<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-5 p-5 sm:p-6 md:flex-row md:items-center">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-clock class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Research &amp; Development</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Shelf Life</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Isi masa simpan dan kondisi penyimpanan per produk Barang WIP yang aktif. Data disimpan lokal, dipakai seluruh Project, dan tidak dikirim ke ESB.</p>
                    </div>
                </div>
                @if($this->canEdit())
                    <div class="flex flex-col items-end gap-1.5">
                        <button type="button" wire:click="refreshCatalog" wire:loading.attr="disabled" wire:target="refreshCatalog" aria-label="Refresh Produk WIP" title="Refresh Produk WIP" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-300 p-2.5 text-gray-600 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            <x-heroicon-o-arrow-path class="h-5 w-5" />
                        </button>
                        @php($progress = $this->syncProgress())
                        @if($progress && ($progress['status'] ?? null) === 'running')
                            @php($percent = ($progress['total'] ?? 0) > 0 ? min(100, (int) round(($progress['scanned'] ?? 0) / $progress['total'] * 100)) : 0)
                            <div wire:poll.2s class="w-32">
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $percent }}%"></div>
                                </div>
                                <p class="mt-1 text-right text-[11px] text-gray-500 dark:text-gray-400">{{ $percent }}%</p>
                            </div>
                        @elseif($progress && ($progress['status'] ?? null) === 'completed')
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $progress['synced'] ?? 0 }} produk WIP tersinkron</p>
                        @elseif($progress && ($progress['status'] ?? null) === 'failed')
                            <p class="text-[11px] text-red-600 dark:text-red-400">Sinkronisasi gagal</p>
                        @endif
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-1 border-t border-gray-200 px-5 dark:border-gray-700 sm:px-6">
                <span class="border-b-2 border-blue-600 px-3 py-3 text-sm font-semibold text-blue-600 dark:text-blue-400">Produk WIP</span>
            </div>
        </section>

        <section class="relative space-y-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Cari kode/nama produk WIP" aria-label="Search" class="min-w-[200px] flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <select wire:model.live="unitFilter" aria-label="Unit" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">All Unit</option>
                    @foreach($this->availableUnits() as $unit)
                        <option value="{{ $unit }}">{{ $unit }}</option>
                    @endforeach
                </select>
                <select wire:model.live="shelfLifeFilter" aria-label="Status Shelf Life" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">Semua Shelf Life</option>
                    <option value="{{ \App\Enums\RndWipShelfLifeStatus::Complete->value }}">Lengkap</option>
                    <option value="{{ \App\Enums\RndWipShelfLifeStatus::Missing->value }}">Belum Diisi</option>
                    <option value="{{ \App\Enums\RndWipShelfLifeStatus::Inactive->value }}">Tidak Aktif</option>
                </select>
                <select wire:model.live="perPage" aria-label="Per Page" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
                <button type="button" wire:click="resetFilters" aria-label="Reset Filter" title="Reset Filter" class="rounded-lg border border-gray-300 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>

            @php($rows = $this->productRows())
            <div wire:loading.delay.flex wire:target="search,unitFilter,shelfLifeFilter,perPage,resetFilters,previousPage,nextPage,goToPage" class="absolute inset-0 z-10 hidden items-center justify-center rounded-b-2xl bg-white/70 dark:bg-gray-900/70">
                <span class="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-r-blue-600"></span>
                    Memuat data...
                </span>
            </div>
            @if($rows->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                    Tidak ada produk Barang WIP yang cocok dengan filter ini.
                    @if($this->canEdit())
                        <span class="block mt-1 text-xs">Tekan tombol refresh untuk mengambil daftar produk WIP terbaru dari ESB.</span>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">Produk WIP</th>
                                <th class="px-4 py-3">Unit</th>
                                <th class="px-4 py-3">Shelf Life</th>
                                <th class="px-4 py-3">Storage</th>
                                <th class="px-4 py-3">Status Data</th>
                                <th class="px-4 py-3">Last Synced</th>
                                <th class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($rows as $row)
                                @php($shelfLife = $row->wipShelfLife)
                                @php($shelfLifeStatus = $this->shelfLifeStatusFor($row))
                                <tr wire:key="wip-product-{{ $row->id }}">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $row->product_name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row->product_code ?: '-' }} · Product Detail #{{ $row->product_detail_id }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row->uom_name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        @if($shelfLife)
                                            <p class="whitespace-nowrap font-semibold text-gray-900 dark:text-white">{{ $shelfLife->shelfLifeLabel() }}</p>
                                            @if(filled($shelfLife->notes))
                                                <p class="max-w-[14rem] break-words text-xs text-gray-500 dark:text-gray-400">{{ $shelfLife->notes }}</p>
                                            @endif
                                        @else
                                            <span class="text-xs text-gray-400">Belum Diisi</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $shelfLife?->storageConditionLabel() ?: '—' }}</td>
                                    <td class="px-4 py-3"><x-rnd.wip-shelf-life-status :status="$shelfLifeStatus" /></td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $row->last_synced_at?->format('d M Y H:i') ?? 'Belum pernah' }}</td>
                                    <td class="px-4 py-3">
                                        @if($this->canEdit())
                                            <div class="flex flex-wrap items-center gap-1">
                                                <button type="button" wire:click="openShelfLifeModal({{ $row->id }})" wire:loading.attr="disabled" wire:target="openShelfLifeModal({{ $row->id }})" aria-label="{{ $shelfLife ? 'Edit Shelf Life' : 'Isi Shelf Life' }}" title="{{ $shelfLife ? 'Edit Shelf Life' : 'Isi Shelf Life' }}" class="inline-flex rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 disabled:opacity-50 dark:text-blue-400 dark:hover:bg-blue-950/40">
                                                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                </button>
                                                @if($shelfLife)
                                                    @if($shelfLife->is_active)
                                                        <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Nonaktifkan Shelf Life?', text: 'Project akan menganggap WIP ini belum mempunyai Shelf Life aktif. Data dan riwayat tetap tersimpan.', confirmText: 'Nonaktifkan' }).then((confirmed) => { if (confirmed) $wire.toggleShelfLifeActive({{ $row->id }}) })" aria-label="Nonaktifkan Shelf Life" title="Nonaktifkan Shelf Life" class="inline-flex rounded-lg p-1.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">
                                                            <x-heroicon-o-pause-circle class="h-4 w-4" />
                                                        </button>
                                                    @else
                                                        <button type="button" wire:click="toggleShelfLifeActive({{ $row->id }})" wire:loading.attr="disabled" wire:target="toggleShelfLifeActive" aria-label="Aktifkan Shelf Life" title="Aktifkan Shelf Life" class="inline-flex rounded-lg p-1.5 text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/40">
                                                            <x-heroicon-o-play-circle class="h-4 w-4" />
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">Detail</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-rnd.numbered-pagination :paginator="$rows" previous-method="previousPage" next-method="nextPage" go-to-method="goToPage" label="produk" />
            @endif
        </section>
    </div>
    @include('filament.helpdesk.pages.partials.wip-shelf-life-modal', [
        'shelfLifeSaveMethod' => 'saveShelfLife',
    ])
</x-filament-panels::page>
