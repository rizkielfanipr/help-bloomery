@php $report = $void->salesReport; @endphp
<div class="space-y-4 text-sm">
    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Sales Number</dt><dd class="mt-0.5 break-all font-mono font-semibold text-gray-900 dark:text-white">{{ $void->sales_number }}</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Verifikasi ESB</dt><dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">{{ $void->sales_number_source->getLabel() }}</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Payment Method</dt><dd class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $void->payment_method_name }}@if($void->payment_method_source === \App\Enums\SalesReportDataSource::Manual) <span class="text-amber-600">(manual)</span>@endif</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Nilai Transaksi ESB</dt><dd class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $void->transaction_amount !== null ? 'Rp '.number_format((float) $void->transaction_amount, 0, ',', '.') : '-' }}</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Transaksi ESB</dt><dd class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $void->sales_transaction_at?->format('d M Y H:i') ?? '-' }} · {{ $void->source_branch_code ?? '-' }} / {{ $void->source_comcode ?? '-' }}</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Sales Report</dt><dd class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $report?->branch?->name ?? '-' }} · {{ $report?->report_date?->isoFormat('D MMM Y') }} · Shift {{ $void->shift_number }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Disubmit</dt><dd class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $void->submittedBy?->name ?? '-' }} · {{ $void->created_at?->timezone(config('app.timezone'))->isoFormat('D MMM Y, HH:mm') }}</dd></div>
    </dl>
    <div>
        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Alasan Void</p>
        <p class="mt-1 whitespace-pre-line break-words text-gray-700 dark:text-gray-300">{{ $void->reason }}</p>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <a href="{{ route('sales-report-evidence.void', ['void' => $void->id, 'kind' => 'original']) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:border-blue-900 dark:bg-gray-900 dark:text-blue-300"><x-heroicon-o-document-text class="h-4 w-4" /> Foto Nota Awal</a>
        <a href="{{ route('sales-report-evidence.void', ['void' => $void->id, 'kind' => 'voided']) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:border-blue-900 dark:bg-gray-900 dark:text-blue-300"><x-heroicon-o-document-minus class="h-4 w-4" /> Foto Nota Setelah Void</a>
    </div>
    @if($report)
        <a href="{{ \App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource::getUrl('view', ['record' => $report]) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">
            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /> Buka Sales Report asal
        </a>
    @endif
</div>
