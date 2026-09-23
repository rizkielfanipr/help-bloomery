@php
    $fmtQty = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $staffEntries = $record->entries->keyBy('product_code');
    $visibleTransactionTypes = array_values(array_filter($transactionTypes, fn ($type) => ! in_array($type, ['Beginning', 'Purchase Invoice Adjustment'], true)));
@endphp
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Stock Movement ESB</h2>
            <p class="mt-1 text-xs text-gray-500">@if($record->selection_snapshot)
                    Menampilkan produk yang dipilih dan disimpan saat laporan dibuat. Data transaksi ESB hanya dipasangkan ke produk tersebut.
                @elseif($this->hasDetailCategoryFilter())
                    Produk pada {{ $movementDateLabel }} mengikuti kategori laporan atau pengaturan global untuk laporan lama tanpa snapshot. Produk dengan input staff tetap ditampilkan; seluruh tipe transaksi tetap tersedia.
                @else
                    Seluruh produk dan tipe transaksi pada {{ $movementDateLabel }}, tanpa dibatasi produk yang tersimpan dalam laporan Stock Card.
                @endif</p>
        </div>
        @if($categoryMappingFailures !== [])
            <p role="alert" class="px-6 py-3 text-xs text-amber-700">Kategori produk gagal dimuat untuk {{ implode(', ', $categoryMappingFailures) }}. Produk yang belum dapat dipetakan tidak ditampilkan, kecuali sudah memiliki input staff. Coba Refresh Rincian Transaksi.</p>
        @endif
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-3 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <p>Qty Utama adalah saldo ESB. Qty Staff berasal dari jumlah fisik yang diinput staff pada laporan; koreksi hanya dilakukan reviewer sesuai permission. Setiap kolom tipe menampilkan total Masuk dan Keluar pada tanggal laporan, bukan saldo. Tipe tanpa transaksi tetap ditampilkan dengan qty 0.</p>
                @if($transactionsFetchedAt)
                    <p class="mt-1">{{ count($visibleTransactionTypes) }} tipe transaksi · Rincian diambil {{ $transactionsFetchedAt }}. Rincian ini tidak mengubah qty fisik atau approval laporan.</p>
                @endif
                @if($transactionError)
                    <p class="mt-1 text-red-600 dark:text-red-400">{{ $transactionError }}</p>
                @elseif($transactionsLoaded && empty($visibleTransactionTypes))
                    <p class="mt-1">Tidak ada transaksi ESB pada tanggal laporan.</p>
                @endif
                <span wire:loading wire:target="loadTransactionBreakdown,refetchEsb" class="mt-1 inline-flex items-center gap-2">
                    <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" /> Memuat semua tipe transaksi…
                </span>
            </div>
            <button type="button" wire:click="loadTransactionBreakdown" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300">
                <x-heroicon-o-arrow-path class="h-4 w-4" /> Refresh Rincian Transaksi
            </button>
        </div>
        @php
            $movementProducts = $this->filteredMovementBalances();
            $movementTotal = $movementProducts->count();
            $movementLastPage = max(1, (int) ceil($movementTotal / 25));
            $movementCurrentPage = min(max(1, $movementPage), $movementLastPage);
            $movementOffset = ($movementCurrentPage - 1) * 25;
            $pagedMovementProducts = $movementProducts->slice($movementOffset, 25);
            $previousCategory = null;
        @endphp
        <div class="px-6 py-3">
            <input type="search" wire:model.live.debounce.300ms="movementSearch" aria-label="Cari Produk ESB" placeholder="Cari nama atau kode produk ESB…" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800">
                    <tr>
                        <th class="min-w-52 px-4 py-3 text-left">Produk ESB</th>
                        <th class="px-4 py-3 text-right">Qty Utama <span class="block text-[10px] font-normal">Saldo ESB</span></th>
                        <th class="px-4 py-3 text-left">Satuan</th>
                        @foreach($visibleTransactionTypes as $type)
                            <th class="min-w-40 px-4 py-3 text-right">{{ $type }}<span class="block text-[10px] font-normal">Masuk / Keluar</span></th>
                        @endforeach
                        <th class="min-w-40 px-4 py-3 text-right">Qty Staff</th>
                        <th class="min-w-40 px-4 py-3 text-right">Koreksi Qty</th>
                        <th class="px-4 py-3 text-right">Selisih Tersimpan</th>
                        <th class="min-w-40 px-4 py-3 text-left">Catatan Staff</th>
                        <th class="min-w-52 px-4 py-3 text-left">Catatan Supervisor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($pagedMovementProducts as $product)
                        @php
                            $staffEntry = $staffEntries->get($product['productCode']);
                            $productCategory = trim((string) ($product['productCategory'] ?? $staffEntry?->product_category ?? '')) ?: 'Tanpa Kategori';
                        @endphp
                        @if($productCategory !== $previousCategory)
                            <tr data-stock-category-row="{{ $productCategory }}" class="bg-blue-50/70 dark:bg-blue-950/20">
                                <td colspan="{{ 8 + count($visibleTransactionTypes) }}" class="px-4 py-2.5">
                                    <span class="text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-300">{{ $productCategory }}</span>
                                </td>
                            </tr>
                            @php
                                $previousCategory = $productCategory;
                            @endphp
                        @endif
                        <tr wire:key="esb-product-{{ $product['productCode'] }}" class="align-top">
                            <td class="px-4 py-3"><p class="font-medium text-gray-800 dark:text-gray-200">{{ $product['productName'] ?? $product['productCode'] }}</p><p class="text-xs text-gray-400">{{ $product['productCode'] }} · {{ $productCategory }}</p></td>
                            <td class="px-4 py-3 text-right font-mono font-semibold">
                                {{ $fmtQty($product['totalQty']) }}
                                @if(! ($product['live'] ?? false) && $product['totalQty'] !== null)<p class="mt-1 text-[10px] font-normal text-gray-400">Saldo tersimpan</p>@endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $product['unit'] }}</td>
                            @foreach($visibleTransactionTypes as $type)
                                @php $transactionQty = $transactionQuantities[$product['productCode']][$type] ?? ['qty_in' => 0, 'qty_out' => 0]; @endphp
                                <td class="px-4 py-3 text-right text-xs">
                                    @if(isset($transactionQuantities[$product['productCode']]))
                                    <p class="font-mono text-emerald-700 dark:text-emerald-400">Masuk {{ $fmtQty($transactionQty['qty_in']) }}</p>
                                    <p class="mt-1 font-mono text-amber-700 dark:text-amber-400">Keluar {{ $fmtQty($transactionQty['qty_out']) }}</p>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-mono text-gray-500">
                                {{ $fmtQty($staffEntry?->reported_qty) }}
                                @if($staffEntry && $staffEntry->system_unit !== $product['unit'])<p class="text-[10px]">{{ $staffEntry->system_unit }}</p>@endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($staffEntry && $isSupervisorInput)
                                    <input type="number" min="0" step="0.0001" wire:model="entryRows.{{ $staffEntry->id }}.actual_qty" aria-label="Koreksi Qty {{ $staffEntry->product_code }}" class="w-32 rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm dark:border-gray-700 dark:bg-gray-900">
                                    @error("entryRows.{$staffEntry->id}.actual_qty") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <span class="font-mono">{{ $fmtQty($staffEntry?->actual_qty) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-500">{{ $fmtQty($staffEntry?->variance) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $staffEntry?->notes ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if($staffEntry && $isSupervisorInput)
                                    <textarea rows="2" wire:model="entryRows.{{ $staffEntry->id }}.supervisor_notes" class="w-52 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Correction notes"></textarea>
                                    @error("entryRows.{$staffEntry->id}.supervisor_notes") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <p class="text-gray-500">{{ $staffEntry?->supervisor_notes ?: '—' }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 8 + count($visibleTransactionTypes) }}" class="px-6 py-8 text-center text-gray-400">{{ $transactionsLoaded ? 'Tidak ada produk ESB yang sesuai.' : 'Rincian ESB belum tersedia. Klik Refresh Rincian Transaksi untuk mengambil data.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($movementTotal > 0)
            <div class="flex items-center justify-between gap-3 border-t border-gray-200 px-6 py-3 text-xs text-gray-500 dark:border-gray-700">
                <span>Menampilkan {{ $movementOffset + 1 }}–{{ min($movementOffset + 25, $movementTotal) }} dari {{ $movementTotal }} produk ESB</span>
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="goToMovementPage({{ $movementCurrentPage - 1 }})" @disabled($movementCurrentPage <= 1) aria-label="Produk ESB Sebelumnya" class="rounded-lg border border-gray-200 p-2 disabled:opacity-30 dark:border-gray-700"><x-heroicon-o-chevron-left class="h-4 w-4" /></button>
                    <span>{{ $movementCurrentPage }}/{{ $movementLastPage }}</span>
                    <button type="button" wire:click="goToMovementPage({{ $movementCurrentPage + 1 }})" @disabled($movementCurrentPage >= $movementLastPage) aria-label="Produk ESB Berikutnya" class="rounded-lg border border-gray-200 p-2 disabled:opacity-30 dark:border-gray-700"><x-heroicon-o-chevron-right class="h-4 w-4" /></button>
                </div>
            </div>
        @endif
        @if($isSupervisorInput || $isFinanceInput)
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                <div @class(['grid gap-5', 'lg:grid-cols-2' => $isFinanceInput])>
                    @if($isFinanceInput)
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Review Notes <span class="font-normal normal-case text-gray-400">(optional)</span></label>
                            <textarea wire:model="reviewNote" rows="3" class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Add review notes if needed"></textarea>
                            @error('reviewNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rejection Reason <span class="font-normal normal-case text-gray-400">(required when returning to Supervisor)</span></label>
                            <textarea wire:model="rejectionReason" rows="3" class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Explain what must be corrected"></textarea>
                            @error('rejectionReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    @if($isFinanceInput)
                        <button type="button" wire:click="rejectFinance" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:bg-gray-900"><x-heroicon-o-arrow-uturn-left class="h-4 w-4" />Return to Supervisor</button>
                    @endif
                    <button type="button"
                            wire:click="{{ $isSupervisorInput ? 'approveSupervisor' : 'approveFinance' }}"
                            wire:loading.attr="disabled"
                            @disabled($isSupervisorInput && ! $record->system_fetched_at)
                            class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-600 transition hover:bg-blue-50 disabled:opacity-50 dark:border-blue-900 dark:bg-gray-900">
                        <x-heroicon-o-check class="h-4 w-4" />{{ $isSupervisorInput ? 'Set as Finance Review' : 'Set as Completed' }}
                    </button>
                </div>
            </div>
        @endif
    </section>
