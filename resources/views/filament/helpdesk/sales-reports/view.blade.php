<x-filament-panels::page>
@php
    $entries = $record->entries->sortBy('paymentMethod.sort_order');
    $totalShift1 = $entries->sum('shift_1_amount');
    $totalShift2 = $entries->sum('shift_2_amount');

    $fmt = fn ($val) => 'Rp ' . number_format((float) $val, 0, ',', '.');
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
            <p class="text-xs text-gray-400">Modal Shift 1</p>
            <p class="mt-0.5 font-semibold text-gray-800 dark:text-gray-100">{{ $fmt($record->modal_shift_1) }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Modal Shift 2</p>
            <p class="mt-0.5 font-semibold text-gray-800 dark:text-gray-100">{{ $fmt($record->modal_shift_2) }}</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <p class="text-sm font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">Sales Payment Method Take Away</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Method Payment</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Shift 1</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Shift 2</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Total</th>
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($entries as $entry)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $entry->paymentMethod->name }}</td>
                            <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ $fmt($entry->shift_1_amount) }}</td>
                            <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ $fmt($entry->shift_2_amount) }}</td>
                            <td class="px-5 py-3 text-right font-medium text-gray-800 dark:text-gray-200">{{ $fmt((float)$entry->shift_1_amount + (float)$entry->shift_2_amount) }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $entry->notes ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50 font-semibold dark:border-gray-700 dark:bg-gray-800">
                        <td class="px-5 py-3 text-gray-700 dark:text-gray-200">Total</td>
                        <td class="px-5 py-3 text-right text-gray-800 dark:text-gray-100">{{ $fmt($totalShift1) }}</td>
                        <td class="px-5 py-3 text-right text-gray-800 dark:text-gray-100">{{ $fmt($totalShift2) }}</td>
                        <td class="px-5 py-3 text-right text-gray-800 dark:text-gray-100">{{ $fmt($totalShift1 + $totalShift2) }}</td>
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
