<x-filament-panels::page>
@php
    $labelOrder = ['DINE IN' => 0, 'TAKEAWAY' => 1];
    $reconciliations = $record->reconciliations->sortBy(fn ($r) => sprintf('%02d|%s', $labelOrder[$r->label] ?? 99, $r->payment_method_name));
    $reconciliationsByLabel = $reconciliations->groupBy('label');
    // Always show DINE IN / TAKEAWAY, even when the branch has no ESB
    // data for that label at all, so the reviewer sees why it's missing
    // rather than the section silently disappearing.
    $allLabels = collect(array_keys($labelOrder))->merge($reconciliationsByLabel->keys())->unique()
        ->sortBy(fn ($label) => $labelOrder[$label] ?? 99);
    $entriesByKey = $record->entries->groupBy(fn ($e) => $e->label.'|'.$e->payment_method_name);
    $employeesByShift = $record->employees->groupBy('shift_number');
    $totalSystem = (float) $reconciliations->sum('system_amount');
    $totalReportedStore = (float) $reconciliations->sum('reported_store_amount');
    $totalStore = (float) $reconciliations->sum('store_amount');
    $totalStoreDifference = $totalStore - $totalSystem;
    $totalSettlement = (float) $reconciliations->sum('settlement_amount');
    $fmt = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $isPending = in_array($record->status, [
        \App\Enums\SalesReportStatus::PendingSupervisor,
        \App\Enums\SalesReportStatus::PendingFinance,
    ], true);
    $reconciliationLabels = [
        'matched' => ['Matched', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
        'under' => ['Under', 'border-red-200 bg-red-50 text-red-700'],
        'over' => ['Over', 'border-amber-200 bg-amber-50 text-amber-700'],
    ];
    $approvalStageLabels = ['submitter' => 'Submitter', 'supervisor' => 'Supervisor', 'finance' => 'Finance'];
    $approvalActionLabels = ['submitted' => 'Submitted', 'resubmitted' => 'Resubmitted', 'approved' => 'Approved', 'rejected' => 'Rejected'];
@endphp

<div class="mx-auto w-full max-w-6xl space-y-5">
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">Sales Report #{{ $record->id }}</p>
                <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $record->branch->name }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $record->report_date->isoFormat('D MMMM Y') }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                <span @class([
                    'rounded-md border px-2.5 py-1 text-xs font-semibold',
                    'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' => $record->status === \App\Enums\SalesReportStatus::Draft,
                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300' => $isPending,
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300' => $record->status === \App\Enums\SalesReportStatus::Completed,
                ])>{{ $record->status->getLabel() }}</span>
                <span class="text-xs text-gray-400">{{ $record->submitted_at?->isoFormat('D MMM Y, HH:mm') ?? '-' }}</span>
                @if($this->canRefetchEsb())
                    <button type="button" wire:click="refetchEsb" wire:loading.attr="disabled" class="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <x-heroicon-o-arrow-path class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="refetchEsb" />
                        Refresh Data ESB
                    </button>
                @endif
            </div>
        </div>

        <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Branch</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->branch->name }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Report Period</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->report_date->isoFormat('D MMMM Y') }}</p></div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Shift Submissions</p>
                @forelse($record->shiftSubmissions->sortBy('shift_number') as $submission)
                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">
                        Shift {{ $submission->shift_number }}
                        <span class="font-normal text-gray-400">· {{ $submission->submittedBy?->name ?? '-' }} · {{ $submission->submitted_at?->isoFormat('D MMM, HH:mm') ?? '-' }}</span>
                    </p>
                @empty
                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">-</p>
                @endforelse
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Report Staff</p>
                @forelse($employeesByShift as $shiftNumber => $employees)
                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">
                        Shift {{ $shiftNumber }}:
                        <span class="font-normal text-gray-500">{{ $employees->pluck('employee_name')->filter()->join(', ') ?: '-' }}</span>
                    </p>
                @empty
                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">-</p>
                @endforelse
            </div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Last Updated</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->updated_at->isoFormat('D MMM Y, HH:mm') }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Revision</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->revision_number }}</p></div>
        </div>

        <div class="grid border-t border-gray-200 dark:border-gray-700 sm:grid-cols-3">
            <div class="px-6 py-5 sm:border-r sm:border-gray-200 dark:sm:border-gray-700">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">System Sales <span class="normal-case text-gray-300">(hari ini, gabungan Shift 1+2)</span></p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $fmt($totalSystem) }}</p>
            </div>
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700 sm:border-r sm:border-t-0">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Store Sales <span class="normal-case text-gray-300">(after SPV review)</span></p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $fmt($totalStore) }}</p>
                <p @class(['mt-1 text-xs', 'text-emerald-600' => abs($totalStoreDifference) < .01, 'text-red-600' => abs($totalStoreDifference) >= .01])>Difference {{ $totalStoreDifference > 0 ? '+' : '' }}{{ $fmt($totalStoreDifference) }}</p>
                <p class="mt-1 text-xs text-gray-400">Total (Shift 1+2): {{ $fmt($totalReportedStore) }}</p>
            </div>
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700 sm:border-t-0">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Settlement Total</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $record->status === \App\Enums\SalesReportStatus::Completed ? $fmt($totalSettlement) : '-' }}</p>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Basket Size per Shift</h2>
            <p class="mt-1 text-xs text-gray-400">Revenue ÷ pax, lalu dibagi rata kepada staff in charge.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-5 py-3 text-left">Shift</th><th class="px-5 py-3 text-right">Revenue</th><th class="px-5 py-3 text-right">Pax</th><th class="px-5 py-3 text-right">Basket Size</th><th class="px-5 py-3 text-left">Staff Credit</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($record->basketSizeRecords->sortBy('shift_number') as $basket)
                        <tr><td class="px-5 py-3"><p class="font-bold">{{ $basket->shift_name }}</p><p class="text-xs text-gray-400">{{ substr($basket->shift_start_time, 0, 5) }}–{{ substr($basket->shift_end_time, 0, 5) }}</p></td><td class="px-5 py-3 text-right">{{ $fmt($basket->revenue) }}</td><td class="px-5 py-3 text-right">{{ number_format($basket->total_pax) }}</td><td class="px-5 py-3 text-right font-bold">{{ $basket->basket_size !== null ? $fmt($basket->basket_size) : 'Pax 0' }}</td><td class="px-5 py-3">@foreach($basket->employeeRecords as $employee)<p>{{ $employee->employee_name }} <span class="font-bold text-blue-600">· {{ $employee->basket_size_credit !== null ? $fmt($employee->basket_size_credit) : '—' }}</span></p>@endforeach</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">Basket Size belum dihitung.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Sales and Settlement Reconciliation</h2>
            <p class="mt-1 text-xs text-gray-400">Shift 1 dan Shift 2 dijumlahkan lalu dibandingkan dengan data sistem. Supervisor correction dan Finance settlement diproses di sini.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1450px] text-sm">
                <thead class="border-b border-gray-200 bg-gray-50/70 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Payment Method</th><th class="px-4 py-3 text-right">Shift 1</th><th class="px-4 py-3 text-right">Shift 2</th><th class="px-4 py-3 text-right">System Sales</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">SPV Correction</th><th class="px-4 py-3 text-right">Store Difference</th><th class="px-4 py-3 text-right">Settlement</th><th class="px-4 py-3 text-right">MDR (%)</th><th class="px-4 py-3 text-right">Expected Settlement</th><th class="px-4 py-3 text-right">Settlement Difference</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($allLabels as $label)
                        @php $group = $reconciliationsByLabel->get($label, collect()); @endphp
                        <tr wire:key="label-{{ $label }}" class="bg-gray-50 dark:bg-gray-800/60">
                            <td colspan="13" class="px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-blue-600 dark:text-blue-400">{{ $label }}</td>
                        </tr>
                        @if($group->isEmpty())
                            <tr wire:key="empty-{{ $label }}">
                                <td colspan="13" class="px-4 py-6">
                                    <div class="flex flex-col items-center gap-2 text-center text-xs text-gray-400 dark:text-gray-500">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800">
                                            <x-heroicon-o-inbox class="h-4 w-4 text-gray-300 dark:text-gray-500" />
                                        </div>
                                        <span>Tidak ada data {{ $label }} untuk laporan ini.</span>
                                    </div>
                                </td>
                            </tr>
                        @endif
                        @foreach($group as $reconciliation)
                            @php
                                $methodEntries = $entriesByKey->get($reconciliation->label.'|'.$reconciliation->payment_method_name, collect());
                                $shift1Amount = $methodEntries->firstWhere('shift_number', 1)?->sales_store_amount;
                                $shift2Amount = $methodEntries->firstWhere('shift_number', 2)?->sales_store_amount;
                                $storeDifference = (float) $reconciliation->store_amount - (float) $reconciliation->system_amount;
                                $preview = $this->settlementPreview($reconciliation->id);
                                $isSupervisorInput = $this->canReviewAsSupervisor();
                                $isFinanceInput = $this->canReviewAsFinance();
                                $recon = $reconciliationLabels[$reconciliation->reconciliation_status] ?? null;
                                $expectedSettlement = $isFinanceInput ? $preview['expected'] : $reconciliation->expected_settlement_amount;
                                $settlementDifference = $isFinanceInput ? $preview['difference'] : $reconciliation->settlement_difference;
                            @endphp
                            <tr wire:key="reconciliation-{{ $reconciliation->id }}" class="align-top">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $reconciliation->payment_method_name }}</td>
                                <td class="px-4 py-3 text-right font-mono text-gray-500">{{ $shift1Amount === null ? '-' : $fmt($shift1Amount) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-gray-500">{{ $shift2Amount === null ? '-' : $fmt($shift2Amount) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $reconciliation->system_amount === null ? '-' : $fmt($reconciliation->system_amount) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $fmt($reconciliation->reported_store_amount) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($isSupervisorInput)
                                        <input type="number" min="0" step="0.01" wire:model="supervisorRows.{{ $reconciliation->id }}.store_amount" class="w-36 rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm dark:border-gray-700 dark:bg-gray-900">
                                        @error("supervisorRows.{$reconciliation->id}.store_amount") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @else
                                        <span class="font-mono">{{ $fmt($reconciliation->store_amount) }}</span>
                                    @endif
                                </td>
                                <td @class(['px-4 py-3 text-right font-mono font-semibold', 'text-emerald-600' => abs($storeDifference) < .01, 'text-red-600' => abs($storeDifference) >= .01])>{{ $storeDifference > 0 ? '+' : '' }}{{ $fmt($storeDifference) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($isFinanceInput)
                                        <input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="settlementRows.{{ $reconciliation->id }}.settlement" class="w-36 rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm dark:border-gray-700 dark:bg-gray-900">
                                        @error("settlementRows.{$reconciliation->id}.settlement") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @else
                                        <span class="font-mono">{{ $reconciliation->settlement_amount !== null ? $fmt($reconciliation->settlement_amount) : '-' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono">{{ $isFinanceInput ? ($preview['mdrPercentage'] === null ? '-' : number_format($preview['mdrPercentage'], 4, ',', '.').'%') : ($reconciliation->mdr_percentage !== null ? number_format((float) $reconciliation->mdr_percentage, 4, ',', '.').'%' : '-') }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $expectedSettlement === null ? '-' : $fmt($expectedSettlement) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold"><span @class(['text-red-600' => $settlementDifference !== null && abs((float) $settlementDifference) > 100, 'text-emerald-600' => $settlementDifference === null || abs((float) $settlementDifference) <= 100])>{{ $settlementDifference === null ? '-' : (((float) $settlementDifference > 0 ? '+' : '').$fmt($settlementDifference)) }}</span></td>
                                <td class="px-4 py-3">@if($recon)<span class="rounded-md border px-2 py-1 text-xs {{ $recon[1] }}">{{ $recon[0] }}</span>@elseif($isFinanceInput)<span class="text-xs text-gray-400">Automatic</span>@else<span class="text-gray-400">-</span>@endif</td>
                                <td class="px-4 py-3">
                                    @if($isSupervisorInput)
                                        <textarea rows="2" wire:model="supervisorRows.{{ $reconciliation->id }}.notes" class="w-52 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Supervisor notes"></textarea>
                                        @error("supervisorRows.{$reconciliation->id}.notes") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @elseif($isFinanceInput)
                                        <textarea rows="2" wire:model="settlementRows.{{ $reconciliation->id }}.note" class="w-52 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Finance notes"></textarea>
                                        @error("settlementRows.{$reconciliation->id}.note") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @else
                                        <p class="max-w-52 text-gray-500">{{ $reconciliation->finance_note ?? $reconciliation->supervisor_notes ?? '-' }}</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($this->canReviewAsSupervisor() || $this->canReviewAsFinance())
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                <div @class(['grid gap-5', 'lg:grid-cols-2' => $this->canReviewAsFinance()])>
                    @if($this->canReviewAsFinance())
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Review Notes <span class="font-normal normal-case text-gray-400">(optional, required when sales differ)</span></label>
                        <textarea wire:model="reviewNote" rows="3" class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Add review notes if needed"></textarea>
                        @error('reviewNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rejection Reason <span class="font-normal normal-case text-gray-400">(required when rejected)</span></label>
                        <textarea wire:model="rejectionReason" rows="3" class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Explain what must be corrected"></textarea>
                        @error('rejectionReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    @if($this->canReviewAsFinance())
                        <button type="button" wire:click="rejectFinance" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:bg-gray-900"><x-heroicon-o-arrow-uturn-left class="h-4 w-4" />Return to Supervisor</button>
                    @endif
                    <button type="button" wire:click="{{ $this->canReviewAsSupervisor() ? 'approveSupervisor' : 'approveFinance' }}" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-600 transition hover:bg-blue-50 disabled:opacity-50 dark:border-blue-900 dark:bg-gray-900"><x-heroicon-o-check class="h-4 w-4" />{{ $this->canReviewAsSupervisor() ? 'Set as Finance Review' : 'Set as Completed' }}</button>
                </div>
            </div>
        @endif
    </section>

    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Review History</h2></div>
        <div class="overflow-x-auto px-6 py-5">
            @if($record->approvals->isNotEmpty())
                <div class="flex min-w-max items-start">
                    @foreach($record->approvals->sortBy('created_at') as $approval)
                        <div class="flex items-start">
                            <div class="w-48 text-center">
                                <div @class(['mx-auto flex h-9 w-9 items-center justify-center rounded-full border', 'border-red-200 bg-red-50 text-red-600' => $approval->action === 'rejected', 'border-emerald-200 bg-emerald-50 text-emerald-600' => $approval->action === 'approved', 'border-blue-200 bg-blue-50 text-blue-600' => ! in_array($approval->action, ['rejected', 'approved'], true)])>
                                    @if($approval->action === 'rejected')<x-heroicon-o-x-mark class="h-4 w-4" />@elseif($approval->action === 'approved')<x-heroicon-o-check class="h-4 w-4" />@else<x-heroicon-o-document-text class="h-4 w-4" />@endif
                                </div>
                                <p class="mt-2 text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $approvalStageLabels[$approval->stage] ?? ucfirst($approval->stage) }} · {{ $approvalActionLabels[$approval->action] ?? ucfirst($approval->action) }}</p>
                                <p class="mt-1 text-[11px] text-gray-500">{{ $approval->created_at->isoFormat('D MMM Y, HH:mm') }}</p>
                                <p class="text-[11px] text-gray-400">oleh {{ $approval->actor?->name ?? 'System' }} · Rev. {{ $approval->revision_number }}</p>
                                @if($approval->notes)<p class="mx-auto mt-1 max-w-40 text-[11px] leading-4 text-gray-500">{{ $approval->notes }}</p>@endif
                            </div>
                            @if(! $loop->last)<div class="mt-4 h-px w-12 bg-gray-200 dark:bg-gray-700"></div>@endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400">No review activity has been recorded.</p>
            @endif
        </div>
    </section>
</div>
</x-filament-panels::page>
