<x-filament-panels::page>
@php
    $entries = $record->entries->sortBy('payment_method_name');
    $totalSystem = (float) $entries->sum('sales_system_amount');
    $totalStore = (float) $entries->sum('sales_store_amount');
    $totalStoreDifference = $totalStore - $totalSystem;
    $totalSettlement = (float) $entries->sum('settlement_amount');
    $fmt = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $reconciliationLabels = [
        'matched' => ['Sesuai', 'bg-emerald-100 text-emerald-700'],
        'under' => ['Kurang', 'bg-red-100 text-red-700'],
        'over' => ['Lebih', 'bg-amber-100 text-amber-700'],
    ];
@endphp

<div class="space-y-5">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Cabang</p>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->branch->name }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Tanggal & Shift</p>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->report_date->isoFormat('D MMMM Y') }}</p>
            <p class="text-xs text-gray-400">Shift {{ $record->shift_number }} · {{ $record->shift_started_at?->format('H:i') ?? '-' }}–{{ $record->shift_ended_at?->format('H:i') ?? '-' }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Status</p>
            <span @class([
                'mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                'bg-emerald-100 text-emerald-700' => $record->status === \App\Enums\SalesReportStatus::Completed,
                'bg-red-100 text-red-700' => in_array($record->status, [\App\Enums\SalesReportStatus::RejectedBySupervisor, \App\Enums\SalesReportStatus::RejectedByFinance], true),
                'bg-amber-100 text-amber-700' => in_array($record->status, [\App\Enums\SalesReportStatus::PendingSupervisor, \App\Enums\SalesReportStatus::PendingFinance], true),
                'bg-gray-100 text-gray-700' => $record->status === \App\Enums\SalesReportStatus::Draft,
            ])>{{ $record->status->getLabel() }}</span>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <p class="text-xs text-gray-400">Dikirim oleh</p>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $record->submittedBy->name }}</p>
            <p class="text-xs text-gray-400">{{ $record->submitted_at?->isoFormat('D MMM Y, HH:mm') }}</p>
        </div>
    </div>

    @if($record->rejection_reason)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Alasan penolakan</p>
            <p class="mt-1">{{ $record->rejection_reason }}</p>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl bg-blue-50 p-4 ring-1 ring-blue-100">
            <p class="text-xs font-medium text-blue-600">Total Sales System</p>
            <p class="mt-1 text-xl font-bold text-blue-900">{{ $fmt($totalSystem) }}</p>
        </div>
        <div class="rounded-xl bg-violet-50 p-4 ring-1 ring-violet-100">
            <p class="text-xs font-medium text-violet-600">Total Sales Store</p>
            <p class="mt-1 text-xl font-bold text-violet-900">{{ $fmt($totalStore) }}</p>
            <p class="text-xs {{ abs($totalStoreDifference) < .01 ? 'text-emerald-600' : 'text-red-600' }}">
                Selisih {{ $totalStoreDifference > 0 ? '+' : '' }}{{ $fmt($totalStoreDifference) }}
            </p>
        </div>
        <div class="rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
            <p class="text-xs font-medium text-emerald-600">Total Settlement</p>
            <p class="mt-1 text-xl font-bold text-emerald-900">
                {{ $record->status === \App\Enums\SalesReportStatus::Completed ? $fmt($totalSettlement) : '-' }}
            </p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <p class="font-semibold text-gray-800 dark:text-gray-100">Rekonsiliasi Sales & Settlement</p>
            <p class="text-xs text-gray-400">Finance mengisi Settlement; nominal dan persentase MDR dihitung otomatis dari selisih terhadap Sales System.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[1250px] w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left">Metode</th>
                        <th class="px-4 py-3 text-right">Sales System</th>
                        <th class="px-4 py-3 text-right">Sales Store</th>
                        <th class="px-4 py-3 text-right">Selisih Store</th>
                        <th class="px-4 py-3 text-right">Settlement</th>
                        <th class="px-4 py-3 text-right">MDR (%)</th>
                        <th class="px-4 py-3 text-right">Expected</th>
                        <th class="px-4 py-3 text-right">Selisih Settlement</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Catatan</th>
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
                                           class="w-36 rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                                    @error("settlementRows.{$entry->id}.settlement") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <span class="font-mono">{{ $entry->settlement_amount !== null ? $fmt($entry->settlement_amount) : '-' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($isFinanceInput)
                                    <span class="inline-flex min-w-24 justify-end rounded-lg bg-gray-100 px-3 py-2 font-mono text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">
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
                                    <span class="text-xs text-gray-400">Otomatis</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($isFinanceInput)
                                    <textarea rows="2" wire:model="settlementRows.{{ $entry->id }}.note" class="w-52 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800" placeholder="Catatan Finance"></textarea>
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
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="text-sm font-semibold text-gray-800 dark:text-gray-200">Catatan Approval</label>
                <textarea wire:model="reviewNote" rows="3" class="mt-2 w-full rounded-lg border-gray-300 text-sm" placeholder="Opsional, wajib jika SPV menyetujui laporan yang memiliki selisih"></textarea>
                @error('reviewNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="{{ $this->canReviewAsSupervisor() ? 'approveSupervisor' : 'approveFinance' }}"
                        wire:loading.attr="disabled" class="mt-3 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                    {{ $this->canReviewAsSupervisor() ? 'Approve SPV' : 'Approve & Selesaikan' }}
                </button>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="text-sm font-semibold text-gray-800 dark:text-gray-200">Alasan Penolakan</label>
                <textarea wire:model="rejectionReason" rows="3" class="mt-2 w-full rounded-lg border-gray-300 text-sm" placeholder="Wajib diisi saat menolak"></textarea>
                @error('rejectionReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="{{ $this->canReviewAsSupervisor() ? 'rejectSupervisor' : 'rejectFinance' }}"
                        wire:loading.attr="disabled" class="mt-3 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                    Tolak Laporan
                </button>
            </div>
        </div>
    @endif

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
        <p class="font-semibold text-gray-800 dark:text-gray-100">Riwayat Approval</p>
        <div class="mt-3 space-y-3">
            @forelse($record->approvals->sortByDesc('created_at') as $approval)
                <div class="flex gap-3 border-l-2 border-blue-200 pl-3">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ ucfirst($approval->stage) }} · {{ ucfirst($approval->action) }}
                        </p>
                        <p class="text-xs text-gray-400">{{ $approval->actor?->name ?? 'Sistem' }} · {{ $approval->created_at->isoFormat('D MMM Y, HH:mm') }} · Revisi {{ $approval->revision_number }}</p>
                        @if($approval->notes)<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $approval->notes }}</p>@endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">Belum ada riwayat approval.</p>
            @endforelse
        </div>
    </div>
</div>
</x-filament-panels::page>
