<x-filament-panels::page>
@php
    $entries = $record->entries->sortBy('product_name');
    $fmtQty = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $isSupervisorInput = $this->canReviewAsSupervisor();
    $isFinanceInput = $this->canReviewAsFinance();
    $approvalStageLabels = ['submitter' => 'Submitter', 'supervisor' => 'Supervisor', 'finance' => 'Finance'];
    $approvalActionLabels = ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'];
@endphp

<div class="mx-auto w-full max-w-6xl space-y-5">
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">Stock Card #{{ $record->id }}</p>
                <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $record->branch->name }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $record->report_date->isoFormat('D MMMM Y') }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                <span @class([
                    'rounded-md border px-2.5 py-1 text-xs font-semibold',
                    'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' => $record->status->getColor() === 'gray',
                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300' => $record->status->getColor() === 'warning',
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300' => $record->status->getColor() === 'success',
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

        <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-5">
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Branch</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->branch->name }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Report Date</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->report_date->isoFormat('D MMMM Y') }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Submitted By</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->submittedBy?->name ?? '-' }}</p></div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Report Staff</p>
                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">
                    {{ $record->employees->pluck('employee_name')->filter()->join(', ') ?: '-' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">System Data</p>
                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">
                    {{ $record->system_fetched_at?->isoFormat('D MMM Y, HH:mm') ?? 'Belum diambil' }}
                </p>
            </div>
        </div>

        @if($record->status === \App\Enums\StockCardStatus::PendingSupervisor && ! $record->system_fetched_at)
            <div class="mx-6 mb-5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-950/30">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <p class="text-sm text-amber-700 dark:text-amber-400">Ambil data sistem ESB dulu ("Refresh Data ESB") sebelum bisa approve, biar qty toko bisa dibandingkan sama qty sistem.</p>
            </div>
        @endif
    </section>

    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Stock Opname Reconciliation</h2>
            <p class="mt-1 text-xs text-gray-400">Qty yang dilaporkan staff dibandingkan dengan data sistem ESB. Supervisor correction diproses di sini.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px] text-sm">
                <thead class="border-b border-gray-200 bg-gray-50/70 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-right">Reported by Staff</th>
                        <th class="px-4 py-3 text-right">System Qty</th>
                        <th class="px-4 py-3 text-right">SPV Correction</th>
                        <th class="px-4 py-3 text-right">Difference</th>
                        <th class="px-4 py-3 text-left">Staff Notes</th>
                        <th class="px-4 py-3 text-left">Supervisor Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($entries as $entry)
                        @php
                            $variance = $entry->variance;
                            $hasVariance = $variance !== null && abs($variance) > 0.0001;
                        @endphp
                        <tr wire:key="entry-{{ $entry->id }}" class="align-top">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $entry->product_name }}</p>
                                <p class="text-xs text-gray-400">{{ $entry->product_code }} &middot; {{ $entry->system_unit }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-500">{{ $fmtQty($entry->reported_qty) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-500">{{ $entry->system_qty === null ? '-' : $fmtQty($entry->system_qty) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($isSupervisorInput)
                                    <input type="number" min="0" step="0.0001" wire:model="entryRows.{{ $entry->id }}.actual_qty" class="w-32 rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm dark:border-gray-700 dark:bg-gray-900">
                                    @error("entryRows.{$entry->id}.actual_qty") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $fmtQty($entry->actual_qty) }}</span>
                                @endif
                            </td>
                            <td @class(['px-4 py-3 text-right font-mono font-semibold', 'text-amber-600' => $hasVariance, 'text-gray-400' => ! $hasVariance])>
                                {{ $variance === null ? '—' : (($variance > 0 ? '+' : '').$fmtQty($variance)) }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $entry->notes ?: '-' }}</td>
                            <td class="px-4 py-3">
                                @if($isSupervisorInput)
                                    <textarea rows="2" wire:model="entryRows.{{ $entry->id }}.supervisor_notes" class="w-52 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Correction notes"></textarea>
                                    @error("entryRows.{$entry->id}.supervisor_notes") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <p class="max-w-52 text-gray-500">{{ $entry->supervisor_notes ?? '-' }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

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
                                <p class="text-[11px] text-gray-400">by {{ $approval->actor?->name ?? 'System' }} · Rev. {{ $approval->revision_number }}</p>
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
