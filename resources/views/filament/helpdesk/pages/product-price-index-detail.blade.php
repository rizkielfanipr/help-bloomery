<x-filament-panels::page>
    @php $product = $this->product(); $stats = $this->stats(); $history = $this->history(); @endphp
    <div class="space-y-5">
        <section class="rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-5 dark:border-blue-900 dark:from-blue-950/30 dark:to-indigo-950/30">
            <a href="{{ \App\Filament\Helpdesk\Pages\ProductPriceIndexPage::getUrl() }}" class="text-xs font-bold text-blue-600">← Product Price Index</a>
            <h2 class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ $product->product_name }}</h2>
            <p class="mt-1 font-mono text-sm font-bold text-blue-600">{{ $product->product_code ?: 'PD-'.$product->product_detail_id }} · {{ $product->uom_name ?: '-' }}</p>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['Harga Terakhir', $stats['latest'], 'blue'],
                ['Weighted Average', $stats['average'], 'indigo'],
                ['Harga Terendah', $stats['minimum'], 'emerald'],
                ['Harga Tertinggi', $stats['maximum'], 'red'],
            ] as [$label, $value, $color])
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-xs text-gray-500">{{ $label }}</p><p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">Rp {{ number_format($value, 0, ',', '.') }}</p></div>
            @endforeach
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-xs text-gray-500">Jumlah PO</p><p class="mt-2 text-lg font-bold">{{ $stats['po_count'] }}</p></div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-end dark:border-gray-700">
                <div><h3 class="font-bold">Histori Purchase Order</h3><p class="text-xs text-gray-500">Klik dan telusuri seluruh transaksi pembelian produk ini.</p></div>
                <div class="flex gap-2"><input wire:model.live="dateFrom" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"><input wire:model.live="dateTo" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-4 py-3 text-left">Tanggal</th><th class="px-4 py-3 text-left">Nomor PO</th><th class="px-4 py-3 text-left">Supplier</th><th class="px-4 py-3 text-left">Branch</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-left">Unit</th><th class="px-4 py-3 text-right">Harga</th><th class="px-4 py-3 text-right">Diskon</th><th class="px-4 py-3 text-right">VAT</th><th class="px-4 py-3 text-right">Harga Normalisasi</th><th class="px-4 py-3 text-left">Status</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($history as $item)
                            <tr><td class="px-4 py-3">{{ $item->purchaseOrder?->purchase_date?->format('d M Y') }}</td><td class="px-4 py-3 font-mono font-bold text-blue-600">{{ $item->purchaseOrder?->purchase_num }}</td><td class="px-4 py-3">{{ $item->purchaseOrder?->supplier_name ?: '-' }}</td><td class="px-4 py-3">{{ $item->purchaseOrder?->branch_name ?: '-' }}</td><td class="px-4 py-3 text-right font-bold">{{ number_format((float) $item->qty, 2, ',', '.') }}</td><td class="px-4 py-3">{{ $item->uom_name ?: '-' }}</td><td class="px-4 py-3 text-right">Rp {{ number_format((float) $item->price, 0, ',', '.') }}</td><td class="px-4 py-3 text-right">Rp {{ number_format((float) $item->discount, 0, ',', '.') }}</td><td class="px-4 py-3 text-right">Rp {{ number_format((float) $item->vat, 0, ',', '.') }}</td><td class="px-4 py-3 text-right font-bold text-blue-700">Rp {{ number_format($item->normalizedNetPrice(), 0, ',', '.') }}</td><td class="px-4 py-3"><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ $item->purchaseOrder?->status_name }}</span></td></tr>
                        @empty
                            <tr><td colspan="11" class="px-5 py-14 text-center text-gray-500">Tidak ada histori pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex justify-between border-t border-gray-200 px-4 py-3 text-xs dark:border-gray-700"><span>Halaman {{ $history->currentPage() }} dari {{ max(1, $history->lastPage()) }}</span><div class="flex gap-2"><button wire:click="$set('page', {{ max(1, $history->currentPage() - 1) }})" @disabled($history->onFirstPage()) class="rounded border px-3 py-1.5 disabled:opacity-40">Sebelumnya</button><button wire:click="$set('page', {{ min($history->lastPage(), $history->currentPage() + 1) }})" @disabled(!$history->hasMorePages()) class="rounded border px-3 py-1.5 disabled:opacity-40">Berikutnya</button></div></div>
        </section>
    </div>
</x-filament-panels::page>
