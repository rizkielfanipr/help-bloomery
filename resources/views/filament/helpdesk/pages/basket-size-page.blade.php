<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-3">
                <div><label class="mb-1 block text-sm font-semibold">Dari</label><input wire:model.live="dateFrom" type="date" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"></div>
                <div><label class="mb-1 block text-sm font-semibold">Sampai</label><input wire:model.live="dateTo" type="date" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"></div>
                <div><label class="mb-1 block text-sm font-semibold">Branch</label><select wire:model.live="branchId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"><option value="">Semua Branch</option>@foreach($this->branches() as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-5 dark:border-gray-700"><h2 class="text-lg font-bold">Ranking Basket Size Employee</h2><p class="text-sm text-gray-500">Diurutkan berdasarkan total Basket Size Credit terbesar.</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-5 py-3 text-left">Rank</th><th class="px-5 py-3 text-left">Employee</th><th class="px-5 py-3 text-left">Position</th><th class="px-5 py-3 text-right">Shift</th><th class="px-5 py-3 text-right">Rata-rata</th><th class="px-5 py-3 text-right">Total Credit</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->ranking() as $index => $row)
                            <tr>
                                <td class="px-5 py-3 font-bold">#{{ $index + 1 }}</td>
                                <td class="px-5 py-3"><a href="{{ \App\Filament\Helpdesk\Pages\BasketSizePage::getUrl(['employee' => $row->employee_id, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo, 'branchId' => $branchId]) }}" class="font-bold text-blue-600 hover:underline">{{ $row->employee_name }}</a><p class="text-xs text-gray-400">{{ $row->employee_code }}</p></td>
                                <td class="px-5 py-3">{{ $row->employee_position ?: '—' }}</td>
                                <td class="px-5 py-3 text-right">{{ $row->shift_count }}</td>
                                <td class="px-5 py-3 text-right">Rp {{ number_format((float) $row->average_credit, 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-bold text-emerald-600">Rp {{ number_format((float) $row->total_credit, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">Belum ada record Basket Size pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($employee)
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-5 dark:border-gray-700"><h2 class="text-lg font-bold">Riwayat Employee</h2><p class="text-sm text-gray-500">Klik Sales Report untuk melihat laporan sumber.</p></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[860px] text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-5 py-3 text-left">Tanggal</th><th class="px-5 py-3 text-left">Branch / Shift</th><th class="px-5 py-3 text-right">Revenue</th><th class="px-5 py-3 text-right">Pax</th><th class="px-5 py-3 text-right">Basket Shift</th><th class="px-5 py-3 text-right">Credit</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($this->history() as $row)
                        <tr><td class="px-5 py-3">{{ $row->basketSizeRecord->report_date->format('d M Y') }}</td><td class="px-5 py-3"><p class="font-bold">{{ $row->basketSizeRecord->branch?->name }}</p><p class="text-xs text-gray-500">{{ $row->basketSizeRecord->shift_name }} · {{ substr($row->basketSizeRecord->shift_start_time, 0, 5) }}–{{ substr($row->basketSizeRecord->shift_end_time, 0, 5) }}</p></td><td class="px-5 py-3 text-right">Rp {{ number_format((float) $row->basketSizeRecord->revenue, 0, ',', '.') }}</td><td class="px-5 py-3 text-right">{{ number_format($row->basketSizeRecord->total_pax) }}</td><td class="px-5 py-3 text-right">Rp {{ number_format((float) $row->basketSizeRecord->basket_size, 0, ',', '.') }}</td><td class="px-5 py-3 text-right font-bold">Rp {{ number_format((float) $row->basket_size_credit, 0, ',', '.') }}</td><td class="px-5 py-3 text-right"><a href="{{ $this->salesReportUrl($row->sales_report_id) }}" class="text-xs font-bold text-blue-600 hover:underline">Sales Report →</a></td></tr>
                    @endforeach
                </tbody></table></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
