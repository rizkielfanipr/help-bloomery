<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $lastPage = max(1, $rows->lastPage());
        $pageStart = max(1, $rows->currentPage() - 3);
        $pageEnd = min($lastPage, $pageStart + 6);
        $pageStart = max(1, $pageEnd - 6);
        $lastSync = $this->lastSyncedAt();
        $lastAutomaticRun = $this->lastAutomaticRun();
    @endphp

    <div class="space-y-5">
        <section class="rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-5 dark:border-blue-900 dark:from-blue-950/30 dark:to-indigo-950/30">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Research & Development</p>
                    <h2 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Product Price Index</h2>
                    <p class="mt-1 text-sm text-gray-500">Harga pembelian bersih, weighted average, dan histori Purchase Order dari ESB.</p>
                </div>
                <div class="rounded-xl border border-blue-100 bg-white/80 px-4 py-3 text-sm dark:border-blue-900 dark:bg-gray-900/70">
                    <p class="text-xs text-gray-400">Sinkronisasi terakhir</p>
                    <p class="mt-1 font-bold text-gray-900 dark:text-white">{{ $lastSync?->format('d M Y, H:i') ?? 'Belum pernah sync' }}</p>
                </div>
            </div>
        </section>

        <div class="inline-flex rounded-xl border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900">
            <button type="button" wire:click="$set('tab', 'materials')" class="rounded-lg px-4 py-2 text-sm font-bold {{ $tab === 'materials' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' }}">Harga Bahan</button>
            <button type="button" wire:click="$set('tab', 'wip')" class="rounded-lg px-4 py-2 text-sm font-bold {{ $tab === 'wip' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' }}">Harga WIP</button>
        </div>

        @if(auth()->user()?->hasRole('SUPERADMIN') || auth()->user()?->can('sync product price index'))
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2"><x-heroicon-o-arrow-path class="h-5 w-5 text-blue-600" /><h3 class="font-bold">Sinkronisasi Purchase Order ESB</h3></div>
                <p class="text-xs text-gray-500">Otomatis setiap Senin 01.00 · rolling 90 hari</p>
            </div>
            <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                <div><label class="mb-1 block text-xs font-semibold text-gray-500">Dari tanggal</label><input wire:model="syncFrom" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
                <div><label class="mb-1 block text-xs font-semibold text-gray-500">Sampai tanggal</label><input wire:model="syncTo" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
                <button type="button" wire:click="sync" wire:loading.attr="disabled" wire:target="sync" class="self-end rounded-lg bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="sync">Sync ESB</span><span wire:loading wire:target="sync">Menyinkronkan...</span>
                </button>
            </div>
            <div wire:loading.flex wire:target="sync" class="mt-4 items-center gap-3 rounded-lg border border-blue-100 bg-blue-50 p-3 text-xs font-semibold text-blue-700">
                <span class="h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-r-blue-600"></span>
                Mengambil PO dan detail produknya. Jangan tutup halaman sampai proses selesai.
            </div>
            @if($lastAutomaticRun)
                <p class="mt-3 text-xs text-gray-500">Proses otomatis terakhir: {{ $lastAutomaticRun->started_at->format('d M Y, H:i') }} · {{ ucfirst($lastAutomaticRun->status) }} · {{ $lastAutomaticRun->products_snapshotted }} produk</p>
            @endif
        </section>
        @endif

        @if($tab === 'materials')
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-3 border-b border-gray-200 p-4 md:grid-cols-2 xl:grid-cols-5 dark:border-gray-700">
                <input wire:model.live.debounce.500ms="search" type="search" placeholder="Cari kode atau nama produk..." class="rounded-lg border border-gray-300 px-3 py-2 text-sm xl:col-span-2 dark:border-gray-600 dark:bg-gray-800">
                <input wire:model.live.debounce.500ms="supplier" type="search" placeholder="Supplier..." class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <input wire:model.live.debounce.500ms="branch" type="search" placeholder="Branch..." class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <select wire:model.live="perPage" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"><option value="20">20 per halaman</option><option value="50">50 per halaman</option></select>
                <input wire:model.live="dateFrom" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <input wire:model.live="dateTo" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                        <tr><th class="px-4 py-3 text-left">Product</th><th class="px-4 py-3 text-left">Unit</th><th class="px-4 py-3 text-right">Harga Terakhir</th><th class="px-4 py-3 text-right">Weighted Average</th><th class="px-4 py-3 text-right">Terendah</th><th class="px-4 py-3 text-right">Tertinggi</th><th class="px-4 py-3 text-right">Perubahan</th><th class="px-4 py-3 text-left">Supplier Terakhir</th><th class="px-4 py-3 text-center">PO</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($rows as $row)
                            <tr class="hover:bg-blue-50/50 dark:hover:bg-blue-950/20">
                                <td class="px-4 py-3"><a href="{{ \App\Filament\Helpdesk\Pages\ProductPriceIndexDetailPage::getUrl(['productDetail' => $row->product_detail_id]) }}" class="font-bold text-gray-900 hover:text-blue-600 dark:text-white">{{ $row->product_name }}</a><p class="mt-0.5 font-mono text-xs font-semibold text-blue-600">{{ $row->product_code ?: 'PD-'.$row->product_detail_id }}</p></td>
                                <td class="px-4 py-3"><span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ $row->uom_name ?: '-' }}</span></td>
                                <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($row->latest_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-bold text-blue-700">Rp {{ number_format((float) $row->average_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format((float) $row->minimum_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format((float) $row->maximum_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $row->change_percent > 0 ? 'bg-red-50 text-red-700' : ($row->change_percent < 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600') }}">{{ $row->change_percent > 0 ? '+' : '' }}{{ number_format($row->change_percent, 1) }}%</span></td>
                                <td class="px-4 py-3">{{ $row->latest_supplier ?: '-' }}</td>
                                <td class="px-4 py-3 text-center font-bold">{{ $row->po_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-5 py-16 text-center text-gray-500"><x-heroicon-o-chart-bar-square class="mx-auto mb-3 h-10 w-10 text-gray-300" />Belum ada data. Jalankan Sync ESB terlebih dahulu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
                <p class="text-xs text-gray-500">Halaman {{ $rows->currentPage() }} dari {{ $lastPage }} · {{ number_format($rows->total()) }} produk</p>
                <div class="inline-flex max-w-full overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-600">
                    <button wire:click="goToPage(1)" @disabled($rows->onFirstPage()) class="border-r px-3 py-2 text-xs disabled:opacity-40 dark:border-gray-600">First</button>
                    @foreach(range($pageStart, $pageEnd) as $pageNumber)<button wire:click="goToPage({{ $pageNumber }})" class="border-r px-3 py-2 text-xs dark:border-gray-600 {{ $pageNumber === $rows->currentPage() ? 'bg-blue-600 text-white' : '' }}">{{ $pageNumber }}</button>@endforeach
                    <button wire:click="goToPage({{ $lastPage }})" @disabled(!$rows->hasMorePages()) class="px-3 py-2 text-xs disabled:opacity-40">Last</button>
                </div>
            </div>
        </section>
        @else
            @php $wipRows = $this->wipRows(); @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                    <h3 class="font-bold text-gray-900 dark:text-white">Index Harga WIP</h3>
                    <p class="mt-1 text-sm text-gray-500">Harga dihitung dari qty bahan pada BOM × harga purchase snapshot terbaru. WIP bertingkat diuraikan sampai bahan dasarnya.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                            <tr><th class="px-4 py-3 text-left">WIP</th><th class="px-4 py-3 text-left">Unit</th><th class="px-4 py-3 text-right">Harga WIP</th><th class="px-4 py-3 text-center">Bahan Dasar</th><th class="px-4 py-3 text-left">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($wipRows as $row)
                                <tr>
                                    <td class="px-4 py-3"><p class="font-bold text-gray-900 dark:text-white">{{ $row['product_name'] }}</p><p class="font-mono text-xs font-semibold text-blue-600">{{ $row['product_code'] ?: 'PD-'.$row['product_detail_id'] }}</p></td>
                                    <td class="px-4 py-3">{{ $row['uom_name'] ?: '-' }}</td>
                                    <td class="px-4 py-3 text-right font-bold {{ $row['complete'] ? 'text-blue-700' : 'text-gray-400' }}">{{ $row['complete'] ? 'Rp '.number_format($row['price'], 0, ',', '.') : '-' }}</td>
                                    <td class="px-4 py-3 text-center font-bold">{{ $row['material_count'] }}</td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-bold {{ $row['complete'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $row['complete'] ? 'Lengkap' : 'Perlu Data' }}</span><p class="mt-1 max-w-lg text-xs text-gray-500">{{ $row['message'] }}</p></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-16 text-center text-gray-500">Belum ada BOM WIP lokal yang dapat dihitung.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
