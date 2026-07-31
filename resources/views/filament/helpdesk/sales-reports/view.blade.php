<x-filament-panels::page>
@php
    $entries = $record->entries->sortBy('payment_method_name');
    $totalSystem = (float) $entries->sum('sales_system_amount');
    $totalStore = (float) $entries->sum('sales_store_amount');
    $totalStoreDifference = $totalStore - $totalSystem;
    $totalSettlement = (float) $entries->sum('settlement_amount');
    $fmt = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $reconciliationLabels = [
        'matched' => ['Matched', 'bg-emerald-100 text-emerald-700'],
        'under' => ['Under', 'bg-red-100 text-red-700'],
        'over' => ['Over', 'bg-amber-100 text-amber-700'],
    ];
    $approvalStageLabels = [
        'submitter' => 'Submitter',
        'supervisor' => 'Supervisor',
        'finance' => 'Finance',
    ];
    $approvalActionLabels = [
        'submitted' => 'Submitted',
        'resubmitted' => 'Resubmitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
@endphp

<div class="space-y-5">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-2 text-gray-400"><x-heroicon-o-building-storefront class="h-4 w-4" /><p class="text-xs">Branch</p></div>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->branch->name }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-2 text-gray-400"><x-heroicon-o-calendar-days class="h-4 w-4" /><p class="text-xs">Report Period</p></div>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->report_date->isoFormat('D MMMM Y') }}</p>
            <p class="text-xs text-gray-400">Shift {{ $record->shift_number }} · {{ $record->shift_started_at?->format('H:i') ?? '-' }}–{{ $record->shift_ended_at?->format('H:i') ?? '-' }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-2 text-gray-400"><x-heroicon-o-signal class="h-4 w-4" /><p class="text-xs">Status</p></div>
            <span @class([
                'mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                'bg-emerald-100 text-emerald-700' => $record->status === \App\Enums\SalesReportStatus::Completed,
                'bg-red-100 text-red-700' => in_array($record->status, [\App\Enums\SalesReportStatus::RejectedBySupervisor, \App\Enums\SalesReportStatus::RejectedByFinance], true),
                'bg-amber-100 text-amber-700' => in_array($record->status, [\App\Enums\SalesReportStatus::PendingSupervisor, \App\Enums\SalesReportStatus::PendingFinance], true),
                'bg-gray-100 text-gray-700' => $record->status === \App\Enums\SalesReportStatus::Draft,
            ])>{{ $record->status->getLabel() }}</span>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-2 text-gray-400"><x-heroicon-o-identification class="h-4 w-4" /><p class="text-xs">Report Staff</p></div>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->employee_name ?? '-' }}</p>
            <p class="text-xs text-gray-400">{{ collect([$record->employee_code, $record->employee_position])->filter()->join(' · ') ?: '-' }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-2 text-gray-400"><x-heroicon-o-user-circle class="h-4 w-4" /><p class="text-xs">Submitted By</p></div>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->submittedBy->name }}</p>
            <p class="text-xs text-gray-400">{{ $record->submitted_at?->isoFormat('D MMM Y, HH:mm') }}</p>
        </div>
    </div>

    @if($record->rejection_reason)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Rejection Reason</p>
            <p class="mt-1">{{ $record->rejection_reason }}</p>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
            <div class="flex items-center gap-2 text-blue-600"><x-heroicon-o-computer-desktop class="h-4 w-4" /><p class="text-xs font-medium">System Sales</p></div>
            <p class="mt-1 text-xl font-bold text-blue-900">{{ $fmt($totalSystem) }}</p>
        </div>
        <div class="rounded-xl border border-violet-100 bg-violet-50 p-4">
            <div class="flex items-center gap-2 text-violet-600"><x-heroicon-o-building-storefront class="h-4 w-4" /><p class="text-xs font-medium">Store Sales</p></div>
            <p class="mt-1 text-xl font-bold text-violet-900">{{ $fmt($totalStore) }}</p>
            <p class="text-xs {{ abs($totalStoreDifference) < .01 ? 'text-emerald-600' : 'text-red-600' }}">
                Difference {{ $totalStoreDifference > 0 ? '+' : '' }}{{ $fmt($totalStoreDifference) }}
            </p>
        </div>
        <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
            <div class="flex items-center gap-2 text-emerald-600"><x-heroicon-o-banknotes class="h-4 w-4" /><p class="text-xs font-medium">Settlement Total</p></div>
            <p class="mt-1 text-xl font-bold text-emerald-900">
                {{ $record->status === \App\Enums\SalesReportStatus::Completed ? $fmt($totalSettlement) : '-' }}
            </p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <x-heroicon-o-scale class="h-5 w-5 text-blue-600" />
                <p class="font-semibold text-gray-800 dark:text-gray-100">Sales and Settlement Reconciliation</p>
            </div>
            <p class="text-xs text-gray-400">Finance enters the settlement amount. The system calculates the MDR and reconciliation result automatically.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[1250px] w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left">Payment Method</th>
                        <th class="px-4 py-3 text-right">System Sales</th>
                        <th class="px-4 py-3 text-right">Store Sales</th>
                        <th class="px-4 py-3 text-right">Store Difference</th>
                        <th class="px-4 py-3 text-right">Settlement</th>
                        <th class="px-4 py-3 text-right">MDR (%)</th>
                        <th class="px-4 py-3 text-right">Expected Settlement</th>
                        <th class="px-4 py-3 text-right">Settlement Difference</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($entries as $entry)
                        @php
                            $storeDifference = (float) $entry->sales_store_amount - (float) $entry->sales_system_amount;
                            $preview = $this->settlementPreview($entry->id);
                            $isFinanceInput = $this->canReviewAsFinance();
                            $recon = $reconciliationLabels[$entry->reconciliation_status] ?? null;
                        @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $entry->payment_method_name }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($entry->sales_system_amount) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($entry->sales_store_amount) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ abs($storeDifference) < .01 ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $storeDifference > 0 ? '+' : '' }}{{ $fmt($storeDifference) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($isFinanceInput)
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="settlementRows.{{ $entry->id }}.settlement"
                                           class="w-36 rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                                    @error("settlementRows.{$entry->id}.settlement") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <span class="font-mono">{{ $entry->settlement_amount !== null ? $fmt($entry->settlement_amount) : '-' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($isFinanceInput)
                                    <span class="inline-flex min-w-24 justify-end rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 font-mono text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                        {{ $preview['mdrPercentage'] === null ? '-' : number_format($preview['mdrPercentage'], 4, ',', '.').'%' }}
                                    </span>
                                @else
                                    {{ $entry->mdr_percentage !== null ? number_format((float)$entry->mdr_percentage, 4, ',', '.').'%' : '-' }}
                                @endif
                            </td>
                            @php $expectedSettlement = $isFinanceInput ? $preview['expected'] : $entry->expected_settlement_amount; @endphp
                            <td class="px-4 py-3 text-right font-mono">{{ $expectedSettlement === null ? '-' : $fmt($expectedSettlement) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold">
                                @php $settlementDifference = $isFinanceInput ? $preview['difference'] : $entry->settlement_difference; @endphp
                                <span class="{{ $settlementDifference !== null && abs((float)$settlementDifference) > 100 ? 'text-red-600' : 'text-emerald-600' }}">
                                    {{ $settlementDifference === null ? '-' : (((float)$settlementDifference > 0 ? '+' : '').$fmt($settlementDifference)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($recon)
                                    <span class="rounded-full px-2 py-1 text-xs {{ $recon[1] }}">{{ $recon[0] }}</span>
                                @elseif($isFinanceInput)
                                    <span class="text-xs text-gray-400">Automatic</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($isFinanceInput)
                                    <textarea rows="2" wire:model="settlementRows.{{ $entry->id }}.note" class="w-52 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800" placeholder="Finance notes"></textarea>
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
    </div>

    @if($this->canReviewAsSupervisor() || $this->canReviewAsFinance())
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-emerald-600" /><label class="text-sm font-semibold text-gray-800 dark:text-gray-200">Review Notes</label></div>
                <p class="mt-1 text-xs text-gray-400">Required when approving a report with a difference between System Sales and Store Sales.</p>
                <textarea wire:model="reviewNote" rows="3" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm leading-5 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800" placeholder="Add review notes if needed"></textarea>
                @error('reviewNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="{{ $this->canReviewAsSupervisor() ? 'approveSupervisor' : 'approveFinance' }}"
                        wire:loading.attr="disabled" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                    <x-heroicon-o-check class="h-4 w-4" />
                    {{ $this->canReviewAsSupervisor() ? 'Set as Finance Review' : 'Set as Completed' }}
                </button>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-2"><x-heroicon-o-x-circle class="h-5 w-5 text-red-600" /><label class="text-sm font-semibold text-gray-800 dark:text-gray-200">Rejection Reason</label></div>
                <p class="mt-1 text-xs text-gray-400">Explain what must be corrected before the report is submitted again.</p>
                <textarea wire:model="rejectionReason" rows="3" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm leading-5 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800" placeholder="Enter a clear rejection reason"></textarea>
                @error('rejectionReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="{{ $this->canReviewAsSupervisor() ? 'rejectSupervisor' : 'rejectFinance' }}"
                        wire:loading.attr="disabled" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                    {{ $this->canReviewAsSupervisor() ? 'Set as Rejected by Supervisor' : 'Set as Rejected by Finance' }}
                </button>
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-2"><x-heroicon-o-clock class="h-5 w-5 text-blue-600" /><p class="font-semibold text-gray-800 dark:text-gray-100">Review History</p></div>
        <div class="mt-3 space-y-3">
            @forelse($record->approvals->sortByDesc('created_at') as $approval)
                <div class="flex gap-3 border-l-2 border-blue-200 pl-3">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ $approvalStageLabels[$approval->stage] ?? ucfirst($approval->stage) }} · {{ $approvalActionLabels[$approval->action] ?? ucfirst($approval->action) }}
                        </p>
                        <p class="text-xs text-gray-400">{{ $approval->actor?->name ?? 'System' }} · {{ $approval->created_at->isoFormat('D MMM Y, HH:mm') }} · Revision {{ $approval->revision_number }}</p>
                        @if($approval->notes)<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $approval->notes }}</p>@endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">No review activity has been recorded.</p>
            @endforelse
        </div>
    </div>
</div>
</x-filament-panels::page>
