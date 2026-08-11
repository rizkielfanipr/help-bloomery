@php
    $reports  = $this->reports();
    $dayNames = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         VIOLET HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-5 pt-14">
        <div class="mb-1 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.sales-report-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Riwayat Sales Report</span>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-4 dark:bg-gray-950">

        @if(! auth()->user()->branch_id)
            <div class="px-5 py-16 text-center">
                <svg class="mx-auto mb-3 h-12 w-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
                <p class="text-sm text-gray-400">Akun belum terhubung ke cabang</p>
            </div>
        @elseif($reports->isEmpty())
            <div class="px-5 py-16 text-center">
                <svg class="mx-auto mb-3 h-12 w-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
                <p class="text-sm text-gray-400">Belum ada sales report</p>
            </div>
        @else
            @foreach($reports->groupBy(fn ($r) => $r->report_date->format('Y-m')) as $monthKey => $monthReports)
                @php
                    $firstDate  = $monthReports->first()->report_date;
                    $monthLabel = $monthNames[$firstDate->month - 1] . ' ' . $firstDate->year;
                @endphp

                <div class="mb-1 mt-5 px-5 first:mt-2">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        {{ $monthLabel }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 px-4">
                    @foreach($monthReports as $report)
                        @php
                            $totalStore  = (float) $report->entries->sum('sales_store_amount');
                            $isExpanded  = $expandedReportId === $report->id;
                            $submittedShiftNumbers = $report->shiftSubmissions->pluck('shift_number')->sort()->values();
                        @endphp

                        <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                            {{-- Card header --}}
                            <button wire:click="toggleReport({{ $report->id }})"
                                    class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition active:bg-gray-50 dark:active:bg-gray-800">

                                {{-- Date badge --}}
                                <div class="flex w-11 flex-shrink-0 flex-col items-center rounded-xl bg-blue-50 py-2 dark:bg-blue-900/30">
                                    <span class="text-[11px] font-semibold uppercase text-blue-400">
                                        {{ $dayNames[$report->report_date->dayOfWeek] }}
                                    </span>
                                    <span class="text-base font-bold leading-none text-blue-700 dark:text-blue-300">
                                        {{ $report->report_date->format('d') }}
                                    </span>
                                </div>

                                {{-- Info --}}
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        Rp {{ number_format($totalStore, 0, ',', '.') }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-400">
                                        {{ $submittedShiftNumbers->map(fn ($n) => 'Shift '.$n)->join(', ') ?: 'Belum ada shift terkirim' }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-400">
                                        {{ $report->entries->pluck('payment_method_name')->unique()->count() }} metode pembayaran
                                    </p>
                                    @if($report->employees->isNotEmpty())
                                        <p class="mt-0.5 truncate text-[11px] text-gray-500">
                                            {{ $report->employees->pluck('employee_name')->filter()->join(', ') }}
                                        </p>
                                    @endif
                                    <span @class([
                                        'mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                        'bg-emerald-100 text-emerald-700' => $report->status === \App\Enums\SalesReportStatus::Completed,
                                        'bg-amber-100 text-amber-700' => in_array($report->status, [\App\Enums\SalesReportStatus::PendingSupervisor, \App\Enums\SalesReportStatus::PendingFinance], true),
                                        'bg-gray-100 text-gray-600' => $report->status === \App\Enums\SalesReportStatus::Draft,
                                    ])>{{ $report->status->getLabel() }}</span>
                                    @php
                                        $submitterNames = $report->shiftSubmissions->pluck('submittedBy.name')->filter()->unique()->join(', ');
                                    @endphp
                                    @if($submitterNames)
                                        <p class="mt-0.5 truncate text-[11px] text-gray-400">{{ $submitterNames }}</p>
                                    @endif
                                </div>

                                {{-- Chevron --}}
                                <svg class="h-4 w-4 flex-shrink-0 text-gray-300 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                </svg>
                            </button>

                            {{-- Expanded detail --}}
                            @if($isExpanded)
                                @php
                                    $entriesByKey = $report->entries->groupBy(fn ($e) => $e->label.'|'.$e->payment_method_name);
                                    $reconciliationsByKey = $report->reconciliations->keyBy(fn ($r) => $r->label.'|'.$r->payment_method_name);
                                    $labelOrder = ['DINE IN' => 0, 'TAKEAWAY' => 1];
                                    $methodsByLabel = $entriesByKey->keys()->merge($reconciliationsByKey->keys())->unique()
                                        ->map(fn ($key) => explode('|', $key, 2))
                                        ->groupBy(fn ($parts) => $parts[0])
                                        ->map(fn ($parts) => $parts->pluck(1)->sort()->values());
                                    // Always show DINE IN / TAKEAWAY, even when the branch has no ESB
                                    // data for that label at all, so staff sees why it's missing rather
                                    // than the section silently disappearing.
                                    $allLabels = collect(array_keys($labelOrder))->merge($methodsByLabel->keys())->unique()
                                        ->sortBy(fn ($label) => $labelOrder[$label] ?? 99);
                                    $totalSystem = (float) $report->reconciliations->sum('system_amount');
                                    $totalSelisih = $totalSystem - $totalStore;
                                @endphp
                                <div class="border-t border-gray-100 dark:border-gray-800">

                                    {{-- Entries — grouped by Dine In / Takeaway, stacked per method so numbers stay readable on narrow screens --}}
                                    @foreach($allLabels as $label)
                                        @php $methods = $methodsByLabel->get($label, collect()); @endphp
                                        <div class="bg-gray-50 px-4 py-1.5 dark:bg-gray-800/50">
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-blue-600 dark:text-blue-400">{{ $label }}</span>
                                        </div>
                                        @if($methods->isEmpty())
                                            <div class="flex flex-col items-center gap-2 border-b border-gray-100 px-4 py-6 text-center last:border-0 dark:border-gray-800">
                                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800">
                                                    <x-heroicon-o-inbox class="h-4 w-4 text-gray-300 dark:text-gray-500" />
                                                </div>
                                                <span class="text-xs text-gray-400 dark:text-gray-500">Tidak ada data {{ $label }} untuk laporan ini.</span>
                                            </div>
                                        @else
                                            @foreach($methods as $method)
                                                @php
                                                    $key = $label.'|'.$method;
                                                    $methodEntries = $entriesByKey->get($key, collect());
                                                    $shift1 = (float) ($methodEntries->firstWhere('shift_number', 1)?->sales_store_amount ?? 0);
                                                    $shift2 = (float) ($methodEntries->firstWhere('shift_number', 2)?->sales_store_amount ?? 0);
                                                    $methodTotal = $shift1 + $shift2;
                                                    $methodSystem = $reconciliationsByKey->get($key)?->system_amount;
                                                @endphp
                                                <div class="border-b border-gray-100 px-4 py-2.5 last:border-0 dark:border-gray-800">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="truncate text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $method }}</span>
                                                        <span class="shrink-0 text-right text-xs font-mono font-semibold text-gray-800 dark:text-gray-100">
                                                            {{ number_format($methodTotal, 0, ',', '.') }}
                                                        </span>
                                                    </div>
                                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10px] text-gray-400">
                                                        <span>Shift 1: {{ number_format($shift1, 0, ',', '.') }}</span>
                                                        <span>Shift 2: {{ number_format($shift2, 0, ',', '.') }}</span>
                                                        <span>Sistem: {{ $methodSystem === null ? '-' : number_format((float) $methodSystem, 0, ',', '.') }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    @endforeach

                                    {{-- Total row --}}
                                    <div class="border-t-2 border-gray-200 bg-gray-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800/50">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-[10px] font-bold uppercase text-gray-500">Total Toko</span>
                                            <span class="text-sm font-mono font-bold text-blue-700 dark:text-blue-300">
                                                Rp {{ number_format($totalStore, 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <div class="mt-1 flex items-center justify-between gap-2">
                                            <span class="text-[10px] font-bold uppercase text-gray-500">Total Sistem</span>
                                            <span class="text-xs font-mono font-semibold text-gray-600 dark:text-gray-300">
                                                Rp {{ number_format($totalSystem, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>

                                    @if($report->reconciliations->isNotEmpty())
                                        <div class="flex items-center justify-between px-4 py-2">
                                            <span class="text-[10px] font-bold uppercase text-gray-500">Selisih (Sistem − Toko)</span>
                                            <span class="text-xs font-bold {{ $totalSelisih < 0 ? 'text-red-500' : ($totalSelisih == 0 ? 'text-gray-400' : 'text-emerald-600') }}">
                                                {{ $totalSelisih < 0 ? '-' : '' }}Rp {{ number_format(abs($totalSelisih), 0, ',', '.') }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                        </div>
                    @endforeach
                </div>
            @endforeach

            {{-- Load more --}}
            <div class="mt-4 px-4">
                <button wire:click="loadMore"
                        class="w-full rounded-xl bg-white py-3 text-xs font-medium text-gray-500 ring-1 ring-gray-200 transition active:bg-gray-50 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-700">
                    Muat lebih banyak
                </button>
            </div>
        @endif

    </div>

    <x-sales-report.bottom-nav active="history" />
</div>
