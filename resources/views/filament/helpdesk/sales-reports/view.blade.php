<x-filament-panels::page>
@php
    $entries = $record->entries->sortBy('payment_method_name');
    $totalSystem = (float) $entries->sum('sales_system_amount');
    $totalStore = (float) $entries->sum('sales_store_amount');
    $totalStoreDifference = $totalStore - $totalSystem;
    $totalSettlement = (float) $entries->sum('settlement_amount');
    $fmt = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $isRejected = in_array($record->status, [
        \App\Enums\SalesReportStatus::RejectedBySupervisor,
        \App\Enums\SalesReportStatus::RejectedByFinance,
    ], true);
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
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $record->report_date->isoFormat('D MMMM Y') }} · Shift {{ $record->shift_number }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                <span @class([
                    'rounded-md border px-2.5 py-1 text-xs font-semibold',
                    'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' => $record->status === \App\Enums\SalesReportStatus::Draft,
                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300' => $isPending,
                    'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300' => $isRejected,
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300' => $record->status === \App\Enums\SalesReportStatus::Completed,
                ])>{{ $record->status->getLabel() }}</span>
                <span class="text-xs text-gray-400">{{ $record->submitted_at?->isoFormat('D MMM Y, HH:mm') ?? '-' }}</span>
            </div>
        </div>

        <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Branch</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->branch->name }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Report Period</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->report_date->isoFormat('D MMMM Y') }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Shift</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">Shift {{ $record->shift_number }} · {{ $record->shift_started_at?->format('H:i') ?? '-' }}–{{ $record->shift_ended_at?->format('H:i') ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Submitted By</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->submittedBy->name }}</p></div>
            <div class="sm:col-span-2"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Report Staff</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->employee_name ?? '-' }}</p><p class="mt-0.5 text-xs text-gray-400">{{ collect([$record->employee_code, $record->employee_position])->filter()->join(' · ') ?: '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Last Updated</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->updated_at->isoFormat('D MMM Y, HH:mm') }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Revision</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->revision_number }}</p></div>
        </div>

        <div class="grid border-t border-gray-200 dark:border-gray-700 sm:grid-cols-3">
            <div class="px-6 py-5 sm:border-r sm:border-gray-200 dark:sm:border-gray-700">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">System Sales</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $fmt($totalSystem) }}</p>
            </div>
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700 sm:border-r sm:border-t-0">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Store Sales</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $fmt($totalStore) }}</p>
                <p @class(['mt-1 text-xs', 'text-emerald-600' => abs($totalStoreDifference) < .01, 'text-red-600' => abs($totalStoreDifference) >= .01])>Difference {{ $totalStoreDifference > 0 ? '+' : '' }}{{ $fmt($totalStoreDifference) }}</p>
            </div>
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700 sm:border-t-0">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Settlement Total</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $record->status === \App\Enums\SalesReportStatus::Completed ? $fmt($totalSettlement) : '-' }}</p>
            </div>
        </div>

        @if($record->rejection_reason)
            <div class="border-t border-red-100 bg-red-50/60 px-6 py-4 dark:border-red-950 dark:bg-red-950/20">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-600">Rejection Reason</p>
                <p class="mt-1 text-sm leading-6 text-red-800 dark:text-red-300">{{ $record->rejection_reason }}</p>
            </div>
        @endif
    </section>

    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Sales and Settlement Reconciliation</h2>
            <p class="mt-1 text-xs text-gray-400">Finance enters the settlement amount. MDR and reconciliation are calculated automatically.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1250px] text-sm">
                <thead class="border-b border-gray-200 bg-gray-50/70 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Payment Method</th><th class="px-4 py-3 text-right">System Sales</th><th class="px-4 py-3 text-right">Store Sales</th><th class="px-4 py-3 text-right">Store Difference</th><th class="px-4 py-3 text-right">Settlement</th><th class="px-4 py-3 text-right">MDR (%)</th><th class="px-4 py-3 text-right">Expected Settlement</th><th class="px-4 py-3 text-right">Settlement Difference</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($entries as $entry)
                        @php
                            $storeDifference = (float) $entry->sales_store_amount - (float) $entry->sales_system_amount;
                            $preview = $this->settlementPreview($entry->id);
                            $isFinanceInput = $this->canReviewAsFinance();
                            $recon = $reconciliationLabels[$entry->reconciliation_status] ?? null;
                            $expectedSettlement = $isFinanceInput ? $preview['expected'] : $entry->expected_settlement_amount;
                            $settlementDifference = $isFinanceInput ? $preview['difference'] : $entry->settlement_difference;
                        @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $entry->payment_method_name }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($entry->sales_system_amount) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($entry->sales_store_amount) }}</td>
                            <td @class(['px-4 py-3 text-right font-mono font-semibold', 'text-emerald-600' => abs($storeDifference) < .01, 'text-red-600' => abs($storeDifference) >= .01])>{{ $storeDifference > 0 ? '+' : '' }}{{ $fmt($storeDifference) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($isFinanceInput)
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="settlementRows.{{ $entry->id }}.settlement" class="w-36 rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm dark:border-gray-700 dark:bg-gray-900">
                                    @error("settlementRows.{$entry->id}.settlement") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <span class="font-mono">{{ $entry->settlement_amount !== null ? $fmt($entry->settlement_amount) : '-' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ $isFinanceInput ? ($preview['mdrPercentage'] === null ? '-' : number_format($preview['mdrPercentage'], 4, ',', '.').'%') : ($entry->mdr_percentage !== null ? number_format((float) $entry->mdr_percentage, 4, ',', '.').'%' : '-') }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $expectedSettlement === null ? '-' : $fmt($expectedSettlement) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold"><span @class(['text-red-600' => $settlementDifference !== null && abs((float) $settlementDifference) > 100, 'text-emerald-600' => $settlementDifference === null || abs((float) $settlementDifference) <= 100])>{{ $settlementDifference === null ? '-' : (((float) $settlementDifference > 0 ? '+' : '').$fmt($settlementDifference)) }}</span></td>
                            <td class="px-4 py-3">@if($recon)<span class="rounded-md border px-2 py-1 text-xs {{ $recon[1] }}">{{ $recon[0] }}</span>@elseif($isFinanceInput)<span class="text-xs text-gray-400">Automatic</span>@else<span class="text-gray-400">-</span>@endif</td>
                            <td class="px-4 py-3">
                                @if($isFinanceInput)
                                    <textarea rows="2" wire:model="settlementRows.{{ $entry->id }}.note" class="w-52 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Finance notes"></textarea>
                                    @error("settlementRows.{$entry->id}.note") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <p class="max-w-52 text-gray-500">{{ $entry->finance_note ?? $entry->notes ?? '-' }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($this->canReviewAsSupervisor() || $this->canReviewAsFinance())
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                <div class="grid gap-5 lg:grid-cols-2">
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
                </div>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="{{ $this->canReviewAsSupervisor() ? 'rejectSupervisor' : 'rejectFinance' }}" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:bg-gray-900"><x-heroicon-o-x-mark class="h-4 w-4" />{{ $this->canReviewAsSupervisor() ? 'Set as Rejected by Supervisor' : 'Set as Rejected by Finance' }}</button>
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
