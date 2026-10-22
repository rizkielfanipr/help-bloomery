@php
    $source = $compliment->effectiveSalesNumberSource();
    $report = $compliment->salesReport;
@endphp
<div class="space-y-4 text-sm">
    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Sales Number</dt><dd class="mt-0.5 break-all font-mono font-semibold text-gray-900 dark:text-white">{{ $compliment->sales_number ?? 'Data lama — Sales Number belum tersedia' }}</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Verifikasi ESB</dt><dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">{{ $source->getLabel() }}</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Jenis Compliment</dt><dd class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $compliment->compliment_type_name }}</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Transaksi ESB</dt><dd class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $compliment->sales_transaction_at?->format('d M Y H:i') ?? '-' }} · {{ $compliment->source_branch_code ?? '-' }} / {{ $compliment->source_comcode ?? '-' }}</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Sales Report</dt><dd class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $report?->branch?->name ?? '-' }} · {{ $report?->report_date?->isoFormat('D MMM Y') }} · Shift {{ $compliment->shift_number }}</dd></div>
        <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Disubmit</dt><dd class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $compliment->submittedBy?->name ?? '-' }} · {{ $compliment->created_at?->timezone(config('app.timezone'))->isoFormat('D MMM Y, HH:mm') }}</dd></div>
    </dl>
    <div>
        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Keterangan Transaksi</p>
        <p class="mt-1 whitespace-pre-line break-words text-gray-700 dark:text-gray-300">{{ $compliment->notes }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @forelse($compliment->attachment_paths ?? [] as $attachment)
            <a href="{{ route('sales-report-evidence.compliment', ['compliment' => $compliment->id, 'index' => $loop->index]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:border-blue-900 dark:bg-gray-900 dark:text-blue-300">
                <x-heroicon-o-paper-clip class="h-4 w-4" />
                {{ \App\Services\SalesReport\SalesReportEvidenceStore::isLegacyPdf($attachment) ? 'File lama (PDF) '.$loop->iteration : 'Foto '.$loop->iteration }}
            </a>
        @empty
            <p class="text-xs text-gray-400">Tidak ada bukti.</p>
        @endforelse
    </div>
    @if($report)
        <a href="{{ \App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource::getUrl('view', ['record' => $report]) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">
            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /> Buka Sales Report asal
        </a>
    @endif
</div>
