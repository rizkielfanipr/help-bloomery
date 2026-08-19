@php
    $cards    = $this->stockCards();
    $dayNames = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    $statusColorMap = [
        'gray' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'success' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    ];
    $approvalStageLabels = ['submitter' => 'Submitter', 'supervisor' => 'Supervisor', 'finance' => 'Finance'];
    $approvalActionLabels = ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    $fmtQty = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-5 pt-14">
        <div class="mb-1 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.stock-card-page') }}"
               wire:navigate
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
            <span class="text-base font-semibold text-white">Riwayat Stock Card</span>
        </div>
    </div>

    {{-- WHITE CONTENT CARD --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-4 dark:bg-gray-950">

        @if(! auth()->user()->branch_id)
            <div class="px-5 py-16 text-center">
                <svg class="mx-auto mb-3 h-12 w-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
                <p class="text-sm text-gray-400">Akun belum terhubung ke cabang</p>
            </div>
        @elseif($cards->isEmpty())
            <div class="px-5 py-16 text-center">
                <svg class="mx-auto mb-3 h-12 w-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125h7.5c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-7.5Z"/>
                </svg>
                <p class="text-sm text-gray-400">Belum ada stock card</p>
            </div>
        @else
            @foreach($cards->groupBy(fn ($c) => $c->report_date->format('Y-m')) as $monthKey => $monthCards)
                @php
                    $firstDate  = $monthCards->first()->report_date;
                    $monthLabel = $monthNames[$firstDate->month - 1] . ' ' . $firstDate->year;
                @endphp

                <div class="mb-1 mt-5 px-5 first:mt-2">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        {{ $monthLabel }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 px-4">
                    @foreach($monthCards as $card)
                        @php
                            $isExpanded  = $expandedCardId === $card->id;
                            $totalItems  = $card->entries->count();
                            $deficitCount = $card->entries->filter(fn ($e) => $e->variance !== null && $e->variance < 0)->count();
                            $surplusCount = $card->entries->filter(fn ($e) => $e->variance !== null && $e->variance > 0)->count();
                            $statusBadgeClass = $statusColorMap[$card->status->getColor()] ?? $statusColorMap['gray'];
                            $statusLabel = $card->status->getLabel();
                            $staffNames = $card->employees->pluck('employee_name')->filter()->join(', ');
                            $varianceSummary = collect([
                                $deficitCount > 0 ? "{$deficitCount} defisit" : null,
                                $surplusCount > 0 ? "{$surplusCount} surplus" : null,
                            ])->filter()->join(' · ');
                        @endphp

                        <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                            {{-- Card header --}}
                            <button wire:click="toggleCard({{ $card->id }})"
                                    class="flex w-full items-start gap-3 px-4 py-3.5 text-left transition active:bg-gray-50 dark:active:bg-gray-800">

                                {{-- Date badge --}}
                                <div class="flex w-11 flex-shrink-0 flex-col items-center rounded-xl bg-blue-50 py-2 dark:bg-blue-900/30">
                                    <span class="text-[11px] font-semibold uppercase text-blue-400">
                                        {{ $dayNames[$card->report_date->dayOfWeek] }}
                                    </span>
                                    <span class="text-base font-bold leading-none text-blue-700 dark:text-blue-300">
                                        {{ $card->report_date->format('d') }}
                                    </span>
                                </div>

                                {{-- Info --}}
                                <div class="min-w-0 flex-1 pt-0.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate text-sm font-semibold text-gray-800 dark:text-gray-100">
                                            {{ $totalItems }} produk
                                        </p>
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-[11px] text-gray-400">
                                        {{ $card->submittedBy?->name ?? '-' }}
                                        @if($card->submitted_at)
                                            &middot; {{ $card->submitted_at->locale('id')->isoFormat('HH:mm') }}
                                        @endif
                                    </p>
                                    @if($varianceSummary !== '')
                                        <p class="mt-1 text-[11px] font-medium {{ $deficitCount > 0 ? 'text-red-500' : 'text-yellow-600' }}">
                                            {{ $varianceSummary }}
                                        </p>
                                    @endif
                                </div>

                                {{-- Chevron --}}
                                <svg class="mt-1 h-4 w-4 flex-shrink-0 text-gray-300 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                </svg>
                            </button>

                            {{-- Expanded detail --}}
                            @if($isExpanded)
                                <div class="border-t border-gray-100 dark:border-gray-800">

                                    {{-- Summary info --}}
                                    <div class="space-y-2 border-b border-gray-100 bg-gray-50/70 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/40">
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Staff In Charge</p>
                                            <p class="text-right text-xs text-gray-700 dark:text-gray-300">{{ $staffNames ?: '-' }}</p>
                                        </div>
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">System Data</p>
                                            <p class="text-right text-xs text-gray-700 dark:text-gray-300">
                                                {{ $card->system_fetched_at?->locale('id')->isoFormat('D MMM, HH:mm') ?? 'Belum diambil' }}
                                            </p>
                                        </div>
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Sumber Produk</p>
                                            <p class="text-right text-xs text-gray-700 dark:text-gray-300">
                                                Daily Usage {{ $card->report_date->copy()->subMonthNoOverflow()->format('d M') }}–{{ $card->report_date->format('d M Y') }}
                                            </p>
                                        </div>
                                        @if($card->revision_number > 0)
                                            <div class="flex items-start justify-between gap-3">
                                                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Revisi</p>
                                                <p class="text-right text-xs text-gray-700 dark:text-gray-300">Rev. {{ $card->revision_number }}</p>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Product list --}}
                                    <div class="overflow-x-auto">
                                        <table class="w-full min-w-full text-[10px]">
                                            <thead>
                                                <tr class="border-b border-gray-100 text-gray-400 dark:border-gray-800">
                                                    <th class="px-3 py-1.5 text-left font-bold uppercase tracking-wide">Produk</th>
                                                    <th class="px-1 py-1.5 text-right font-bold uppercase tracking-wide">Lapor</th>
                                                    <th class="px-1 py-1.5 text-right font-bold uppercase tracking-wide">Sistem</th>
                                                    <th class="px-1 py-1.5 text-right font-bold uppercase tracking-wide">SPV</th>
                                                    <th class="px-3 py-1.5 text-right font-bold uppercase tracking-wide">Selisih</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                @foreach($card->entries->sortBy('product_name') as $entry)
                                                    @php
                                                        $variance = $entry->variance;
                                                        $varColor = $variance === null
                                                            ? 'text-gray-400'
                                                            : ($variance < 0 ? 'text-red-500' : ($variance == 0 ? 'text-gray-400' : 'text-yellow-600'));
                                                        $varLabel = $variance === null
                                                            ? '—'
                                                            : ($variance == 0
                                                                ? '0'
                                                                : ($variance > 0 ? '+' : '') . $fmtQty($variance));
                                                    @endphp
                                                    <tr>
                                                        <td class="max-w-[120px] px-3 py-2 align-top">
                                                            <p class="truncate font-medium text-gray-700 dark:text-gray-300">{{ $entry->product_name }}</p>
                                                            @if($entry->product_code)
                                                                <p class="truncate text-[9px] text-gray-400">{{ $entry->product_code }} · {{ $entry->product_category ?: 'Tanpa Kategori' }}</p>
                                                            @endif
                                                            @if($entry->notes)
                                                                <p class="mt-1 text-[9px] italic leading-tight text-gray-400 dark:text-gray-500">Staff: {{ $entry->notes }}</p>
                                                            @endif
                                                            @if($entry->supervisor_notes)
                                                                <p class="mt-0.5 text-[9px] italic leading-tight text-blue-500 dark:text-blue-400">SPV: {{ $entry->supervisor_notes }}</p>
                                                            @endif
                                                        </td>
                                                        <td class="px-1 py-2 text-right align-top font-mono text-gray-500">{{ $fmtQty($entry->reported_qty) }}</td>
                                                        <td class="px-1 py-2 text-right align-top font-mono text-gray-500">{{ $fmtQty($entry->system_qty) }}</td>
                                                        <td class="px-1 py-2 text-right align-top font-mono text-gray-700 dark:text-gray-300">{{ $fmtQty($entry->actual_qty) }}</td>
                                                        <td class="px-3 py-2 text-right align-top font-mono font-semibold {{ $varColor }}">{{ $varLabel }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    {{-- Review history --}}
                                    @if($card->approvals->isNotEmpty())
                                        <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                                            <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-gray-400">Riwayat Review</p>
                                            <div class="space-y-2">
                                                @foreach($card->approvals->sortBy('created_at') as $approval)
                                                    <div class="flex items-start gap-2">
                                                        <div @class([
                                                            'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full',
                                                            'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' => $approval->action === 'rejected',
                                                            'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400' => $approval->action === 'approved',
                                                            'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' => ! in_array($approval->action, ['rejected', 'approved'], true),
                                                        ])>
                                                            @if($approval->action === 'rejected')
                                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                                            @elseif($approval->action === 'approved')
                                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                                            @else
                                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                                            @endif
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                                                {{ $approvalStageLabels[$approval->stage] ?? ucfirst($approval->stage) }} · {{ $approvalActionLabels[$approval->action] ?? ucfirst($approval->action) }}
                                                            </p>
                                                            <p class="text-[10px] text-gray-400">
                                                                {{ $approval->actor?->name ?? 'System' }} &middot; {{ $approval->created_at->locale('id')->isoFormat('D MMM, HH:mm') }}
                                                            </p>
                                                            @if($approval->notes)
                                                                <p class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-400">{{ $approval->notes }}</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
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

    <x-stock-card.bottom-nav active="history" />
</div>
