<x-filament-panels::page>
@php
    $entries = $record->entries->sortBy('product_name');
    $fmtQty = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $isSupervisorInput = $this->canReviewAsSupervisor();
    $isFinanceInput = $this->canReviewAsFinance();
    $approvalStageLabels = ['submitter' => 'Submitter', 'supervisor' => 'Supervisor', 'finance' => 'Finance'];
    $approvalActionLabels = ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'];
@endphp

<div class="mx-auto w-full max-w-6xl space-y-5" wire:init="loadTransactionBreakdown">
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

        <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-6">
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
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Product Source</p>
                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">Stock Movement Harian</p>
                <p class="text-xs text-gray-400">{{ $record->report_date->format('d M Y') }}</p>
            </div>
        </div>

        @if($record->status === \App\Enums\StockCardStatus::PendingSupervisor && ! $record->system_fetched_at)
            <div class="mx-6 mb-5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-950/30">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <p class="text-sm text-amber-700 dark:text-amber-400">Ambil data sistem ESB dulu ("Refresh Data ESB") sebelum bisa approve, biar qty toko bisa dibandingkan sama qty sistem.</p>
            </div>
        @endif
    </section>

    @if($record->selection_snapshot)
        @php
            $selectionCategories = collect($record->selection_snapshot['categories'] ?? []);
            $selectionSource = $record->selection_snapshot['source'] ?? [];
        @endphp
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Daily Product Selection</h2>
                <p class="mt-1 text-xs text-gray-500">{{ $record->entries->count() }} products saved for this report · {{ $selectionSource['company_code'] ?? '-' }} / {{ $selectionSource['branch_code'] ?? '-' }}</p>
            </div>
            <div class="grid gap-3 px-6 py-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($selectionCategories as $category)
                    <div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $category['category'] }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $category['selected'] }} of {{ $category['available'] }} products
                            @if($category['mode'] === 'limited')
                                · target {{ $category['target'] }}
                            @endif
                        </p>
                        @if($category['rotate_daily'])
                            <p class="mt-1 text-xs font-medium text-blue-600 dark:text-blue-400">Daily rotation active</p>
                        @endif
                    </div>
                @endforeach
            </div>
            @foreach($record->selection_snapshot['warnings'] ?? [] as $warning)
                <p class="border-t border-amber-200 bg-amber-50 px-6 py-3 text-xs text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">{{ $warning }}</p>
            @endforeach
        </section>
    @endif

    @include('filament.helpdesk.stock-cards.movement-table', ['movementDateLabel' => $record->report_date->toDateString()])

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
