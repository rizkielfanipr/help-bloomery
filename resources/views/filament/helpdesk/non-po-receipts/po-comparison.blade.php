<x-filament::section heading="Perbandingan Dengan PO {{ $comparison['poNumber'] }}" description="Hasil baca terakhir dari ESB. Pemeriksaan ini tidak mengubah data; verifikasi selalu mengambil PO terbaru.">
    <dl class="grid gap-3 text-sm sm:grid-cols-3">
        <div><dt class="text-xs font-medium text-gray-500">Status PO</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $comparison['statusName'] ?? '-' }}</dd></div>
        <div><dt class="text-xs font-medium text-gray-500">Cabang PO</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $comparison['branchName'] ?? '-' }}</dd></div>
        <div><dt class="text-xs font-medium text-gray-500">Supplier Resmi PO</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $comparison['supplierName'] ?? '-' }}</dd></div>
    </dl>

    @if ($comparison['blockers'] !== [])
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
            <p class="font-semibold">Goods Receipt tidak dapat diproses:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($comparison['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
        </div>
    @elseif ($comparison['supplierMismatch'])
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
            Supplier berbeda: Inventory mencatat <strong>{{ $comparison['localSupplier'] }}</strong>, PO memakai <strong>{{ $comparison['supplierName'] }}</strong>. Centang konfirmasi saat verifikasi; supplier PO yang dipakai.
        </div>
    @else
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300">Seluruh item Accepted cocok dengan PO.</div>
    @endif

    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead><tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700">
                <th class="px-3 py-2">Produk Lokal</th><th class="px-3 py-2">UOM</th><th class="px-3 py-2 text-right">Accepted</th><th class="px-3 py-2">Baris PO</th><th class="px-3 py-2 text-right">Order</th><th class="px-3 py-2 text-right">Outstanding</th><th class="px-3 py-2">Hasil</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($comparison['items'] as $line)
                    <tr wire:key="comparison-{{ $line['itemId'] }}">
                        <td class="px-3 py-2"><p class="font-medium text-gray-900 dark:text-white">{{ $line['productName'] }}</p><p class="text-xs text-gray-500">{{ $line['productCode'] ?: '-' }}</p></td>
                        <td class="px-3 py-2">{{ $line['uomName'] }}</td>
                        <td class="px-3 py-2 text-right">{{ \App\Filament\Helpdesk\Resources\NonPoReceipts\NonPoReceiptResource::qty($line['acceptedQty']) }}</td>
                        <td class="px-3 py-2">{{ $line['poProductName'] ?? '-' }} {{ $line['poUomName'] ? '· '.$line['poUomName'] : '' }}</td>
                        <td class="px-3 py-2 text-right">{{ $line['orderedQty'] === null ? '-' : \App\Filament\Helpdesk\Resources\NonPoReceipts\NonPoReceiptResource::qty($line['orderedQty']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $line['outstandingQty'] === null ? '-' : \App\Filament\Helpdesk\Resources\NonPoReceipts\NonPoReceiptResource::qty($line['outstandingQty']) }}</td>
                        <td class="px-3 py-2">
                            @if ($line['message'])
                                <span class="{{ $line['acceptedQty'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500' }}">{{ $line['acceptedQty'] > 0 ? $line['message'] : 'Tidak perlu PO (tanpa Accepted)' }}</span>
                            @else
                                <span class="text-emerald-600 dark:text-emerald-400">Cocok</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament::section>
