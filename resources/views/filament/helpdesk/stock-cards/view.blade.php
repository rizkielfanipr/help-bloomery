<x-filament-panels::page>
@php
    $entries    = $record->entries->sortBy('product_name');
    $filledEntries = $entries->filter(fn ($e) => $e->actual_qty !== null);

    $okCount      = $filledEntries->filter(fn ($e) => (float) $e->actual_qty === (float) $e->system_qty)->count();
    $surplusCount = $filledEntries->filter(fn ($e) => (float) $e->actual_qty > (float) $e->system_qty)->count();
    $deficitCount = $filledEntries->filter(fn ($e) => (float) $e->actual_qty < (float) $e->system_qty)->count();

    $fmtQty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
@endphp

<div class="space-y-4">

    {{-- Info header --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Cabang</p>
            <p class="mt-0.5 font-semibold text-gray-800 dark:text-gray-100">{{ $record->branch->name }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Tanggal</p>
            <p class="mt-0.5 font-semibold text-gray-800 dark:text-gray-100">{{ $record->report_date->isoFormat('D MMMM Y') }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Unit</p>
            <p class="mt-0.5 font-semibold text-gray-800 dark:text-gray-100">{{ \App\Filament\Casual\Pages\StockCardEntryPage::FLAG_UNITS[$record->flag_unit] ?? $record->flag_unit }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Disubmit</p>
            <p class="mt-0.5 font-semibold text-gray-800 dark:text-gray-100">
                {{ $record->submitted_at?->isoFormat('D MMM Y, HH:mm') ?? '-' }}
            </p>
        </div>
    </div>

    {{-- Summary KPI --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-xl bg-green-50 p-4 ring-1 ring-green-100 dark:bg-green-900/20 dark:ring-green-900/40">
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $okCount }}</p>
            <p class="text-xs font-semibold uppercase tracking-wide text-green-500">Sesuai</p>
        </div>
        <div class="rounded-xl bg-yellow-50 p-4 ring-1 ring-yellow-100 dark:bg-yellow-900/20 dark:ring-yellow-900/40">
            <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $surplusCount }}</p>
            <p class="text-xs font-semibold uppercase tracking-wide text-yellow-500">Surplus</p>
        </div>
        <div class="rounded-xl bg-red-50 p-4 ring-1 ring-red-100 dark:bg-red-900/20 dark:ring-red-900/40">
            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $deficitCount }}</p>
            <p class="text-xs font-semibold uppercase tracking-wide text-red-500">Defisit</p>
        </div>
    </div>

    {{-- Entries table --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <p class="text-sm font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">
                Material Usage — {{ $entries->count() }} item
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Kode</th>
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Nama Material</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Qty Sistem</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Qty Aktual</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Selisih</th>
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Satuan</th>
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($entries as $entry)
                        @php
                            $variance = $entry->actual_qty !== null
                                ? (float) $entry->actual_qty - (float) $entry->system_qty
                                : null;

                            if ($variance === null) {
                                $varClass = 'text-gray-400';
                                $varText  = '—';
                            } elseif ($variance == 0) {
                                $varClass = 'text-emerald-600 dark:text-emerald-400';
                                $varText  = '0';
                            } elseif ($variance > 0) {
                                $varClass = 'text-yellow-600 dark:text-yellow-400';
                                $varText  = '+' . $fmtQty(abs($variance));
                            } else {
                                $varClass = 'text-red-600 dark:text-red-400';
                                $varText  = '-' . $fmtQty(abs($variance));
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-5 py-3 font-mono text-xs text-gray-400 dark:text-gray-500">{{ $entry->product_code ?: '-' }}</td>
                            <td class="px-5 py-3 font-medium text-gray-700 dark:text-gray-300">{{ $entry->product_name }}</td>
                            <td class="px-5 py-3 text-right font-mono text-gray-600 dark:text-gray-400">{{ $fmtQty($entry->system_qty) }}</td>
                            <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                {{ $entry->actual_qty !== null ? $fmtQty($entry->actual_qty) : '—' }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-semibold {{ $varClass }}">{{ $varText }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $entry->system_unit ?: '-' }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $entry->notes ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-5 py-2.5 dark:border-gray-800">
            <p class="text-xs text-gray-400">
                Disubmit oleh {{ $record->submittedBy?->name ?? '-' }} · {{ $record->updated_at->diffForHumans() }}
            </p>
        </div>
    </div>

</div>
</x-filament-panels::page>
