<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $summary = $this->summary();
        $lastPage = max(1, $rows->lastPage());
        $pageStart = max(1, $rows->currentPage() - 3);
        $pageEnd = min($lastPage, $pageStart + 6);
        $pageStart = max(1, $pageEnd - 6);
    @endphp

    <div class="space-y-5">
        <section class="rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-5 dark:border-blue-900 dark:from-blue-950/30 dark:to-indigo-950/30">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Sales & Growth</p>
            <h2 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Sales Projection</h2>
            <p class="mt-1 text-sm text-gray-500">Seluruh target penjualan produk yang disubmit oleh tim R&D.</p>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-xs font-semibold text-gray-400">Target Quantity</p><p class="mt-1 text-xl font-bold text-blue-700 dark:text-blue-300">{{ number_format($summary['quantity'], 2, ',', '.') }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-xs font-semibold text-gray-400">Target Revenue</p><p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-300">Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-xs font-semibold text-gray-400">Produk</p><p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ number_format($summary['products']) }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-xs font-semibold text-gray-400">Branch Ditargetkan</p><p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ number_format($summary['branches']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-3 border-b border-gray-200 p-4 md:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_1.5fr_auto] dark:border-gray-700">
                <input wire:model.live.debounce.500ms="search" type="search" placeholder="Cari produk, SKU, atau project..." class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <input wire:model.live="month" type="month" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <select wire:model.live="channel" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Semua channel</option>
                    @foreach(\App\Models\RndProductSalesProjection::CHANNELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <select wire:model.live="branchId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Semua branch</option>
                    @foreach($this->branches() as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                </select>
                <select wire:model.live="perPage" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"><option value="20">20 / halaman</option><option value="50">50 / halaman</option></select>
            </div>

            <div wire:loading.flex wire:target="search,month,channel,branchId,perPage" class="items-center gap-2 border-b border-blue-100 bg-blue-50 px-4 py-2 text-xs font-semibold text-blue-700">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-r-blue-600"></span>Memuat sales projection...
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1260px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                        <tr><th class="px-4 py-3 text-left">Produk</th><th class="px-4 py-3 text-left">Periode</th><th class="px-4 py-3 text-left">Region / Channel</th><th class="px-4 py-3 text-right">Quantity</th><th class="px-4 py-3 text-right">Revenue</th><th class="px-4 py-3 text-left">Target per Branch</th><th class="px-4 py-3 text-left">Catatan</th><th class="px-4 py-3 text-left">Diperbarui</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($rows as $projection)
                            <tr class="align-top hover:bg-blue-50/50 dark:hover:bg-blue-950/20">
                                <td class="px-4 py-3"><p class="font-bold text-gray-900 dark:text-white">{{ $projection->product?->name ?? '-' }}</p><p class="font-mono text-xs font-semibold text-blue-600">{{ $projection->product?->product_code ?: 'SKU belum diisi' }}</p><p class="mt-1 text-xs text-gray-400">{{ $projection->product?->project?->name ?? '-' }}</p></td>
                                <td class="px-4 py-3 font-bold">{{ $projection->projection_month?->format('M Y') ?? '-' }}</td>
                                <td class="px-4 py-3"><p class="font-semibold">{{ $projection->region?->name ?? '-' }}</p><span class="mt-1 inline-block rounded-md bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ \App\Models\RndProductSalesProjection::CHANNELS[$projection->channel] ?? ucfirst($projection->channel) }}</span></td>
                                <td class="px-4 py-3 text-right font-bold">{{ number_format((float) $projection->target_quantity, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-700">Rp {{ number_format((float) $projection->target_revenue, 0, ',', '.') }}</td>
                                <td class="min-w-64 px-4 py-3">
                                    @forelse($projection->targetBranches as $branch)
                                        <div class="mb-1 flex items-center justify-between gap-4 rounded-md bg-gray-50 px-2 py-1.5 text-xs last:mb-0 dark:bg-gray-800"><span>{{ $branch->name }}</span><strong>{{ number_format((float) $branch->pivot->target_quantity, 2, ',', '.') }}</strong></div>
                                    @empty<span class="text-xs text-gray-400">Belum ada branch.</span>@endforelse
                                </td>
                                <td class="max-w-xs px-4 py-3 text-gray-600 dark:text-gray-300">{{ $projection->notes ?: '-' }}</td>
                                <td class="px-4 py-3"><p>{{ $projection->updated_at?->format('d M Y, H:i') }}</p><p class="mt-0.5 text-xs text-gray-400">{{ $projection->creator?->name ?? '-' }}</p></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-16 text-center text-gray-500"><x-heroicon-o-presentation-chart-line class="mx-auto mb-3 h-10 w-10 text-gray-300" />Belum ada sales projection yang sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
                <p class="text-xs text-gray-500">Halaman {{ $rows->currentPage() }} dari {{ $lastPage }} · {{ number_format($rows->total()) }} projection</p>
                <div class="inline-flex max-w-full overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-600">
                    <button wire:click="goToPage(1)" @disabled($rows->onFirstPage()) class="border-r px-3 py-2 text-xs disabled:opacity-40 dark:border-gray-600">First</button>
                    @foreach(range($pageStart, $pageEnd) as $pageNumber)<button wire:click="goToPage({{ $pageNumber }})" class="border-r px-3 py-2 text-xs dark:border-gray-600 {{ $pageNumber === $rows->currentPage() ? 'bg-blue-600 text-white' : '' }}">{{ $pageNumber }}</button>@endforeach
                    <button wire:click="goToPage({{ $lastPage }})" @disabled(!$rows->hasMorePages()) class="px-3 py-2 text-xs disabled:opacity-40">Last</button>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
