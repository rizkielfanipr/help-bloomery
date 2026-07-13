<x-filament-panels::page>
@php
    $entries      = $record->entries->sortBy('payment_method_name');
    $totalSystem  = $entries->sum('sales_system_amount');
    $totalStore   = $entries->sum('sales_store_amount');
    $totalSelisih = (float) $totalSystem - (float) $totalStore;

    $fmt = fn ($val) => 'Rp ' . number_format((float) $val, 0, ',', '.');
@endphp

<div class="space-y-4">

    {{-- Info header --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Cabang</p>
            <p class="mt-0.5 font-semibold text-gray-800 dark:text-gray-100">{{ $record->branch->name }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Tanggal</p>
            <p class="mt-0.5 font-semibold text-gray-800 dark:text-gray-100">{{ $record->report_date->isoFormat('D MMMM Y') }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Disubmit</p>
            <p class="mt-0.5 font-semibold text-gray-800 dark:text-gray-100">
                {{ $record->submitted_at?->isoFormat('D MMM Y, HH:mm') ?? '-' }}
            </p>
        </div>
    </div>

    {{-- Table --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <p class="text-sm font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">Sales Payment Method</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Metode Pembayaran</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Sales System</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Sales Store</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Selisih</th>
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($entries as $entry)
                        @php $selisih = (float)$entry->sales_system_amount - (float)$entry->sales_store_amount; @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $entry->payment_method_name }}</td>
                            <td class="px-5 py-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ $fmt($entry->sales_system_amount) }}</td>
                            <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $fmt($entry->sales_store_amount) }}</td>
                            <td class="px-5 py-3 text-right font-mono font-semibold
                                {{ $selisih < 0 ? 'text-red-600 dark:text-red-400' : ($selisih == 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-500 dark:text-amber-400') }}">
                                {{ $selisih < 0 ? '-' : ($selisih > 0 ? '+' : '') }}{{ $fmt(abs($selisih)) }}
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $entry->notes ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50 font-semibold dark:border-gray-700 dark:bg-gray-800">
                        <td class="px-5 py-3 text-gray-700 dark:text-gray-200">Total</td>
                        <td class="px-5 py-3 text-right font-mono text-gray-800 dark:text-gray-100">{{ $fmt($totalSystem) }}</td>
                        <td class="px-5 py-3 text-right font-mono text-gray-800 dark:text-gray-100">{{ $fmt($totalStore) }}</td>
                        <td class="px-5 py-3 text-right font-mono
                            {{ $totalSelisih < 0 ? 'text-red-600 dark:text-red-400' : ($totalSelisih == 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-500 dark:text-amber-400') }}">
                            {{ $totalSelisih < 0 ? '-' : ($totalSelisih > 0 ? '+' : '') }}{{ $fmt(abs($totalSelisih)) }}
                        </td>
                        <td class="px-5 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="border-t border-gray-100 px-5 py-2.5 dark:border-gray-800">
            <p class="text-xs text-gray-400">Disubmit oleh {{ $record->submittedBy->name }} · {{ $record->updated_at->diffForHumans() }}</p>
        </div>
    </div>

</div>
</x-filament-panels::page>
