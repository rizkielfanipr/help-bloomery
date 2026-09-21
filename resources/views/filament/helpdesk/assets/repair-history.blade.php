<div class="space-y-4">
    @forelse($requests as $request)
        <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <p class="font-bold">{{ $request->code }} · {{ $request->status->getLabel() }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ $request->created_at->format('d M Y') }} · {{ $request->technician?->display_username ?? 'Belum ditugaskan' }}</p>
            <p class="mt-2 text-sm">{{ $request->requestor_notes }}</p>
            <p class="mt-2 text-sm text-gray-500">Diagnosis: {{ $request->diagnosis ?: 'Belum diperiksa' }}</p>
            @foreach($request->repairs as $repair)
                <div class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <p class="text-sm font-semibold">{{ $repair->cycle_label }} · {{ $repair->completed_at?->format('d M Y H:i') ?? 'Belum selesai' }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $repair->after_notes }}</p>
                    @if($repair->outsource_report)
                        <p class="mt-2 text-sm text-gray-500">Vendor: {{ $repair->outsource_report['vendor'] }} · Biaya: Rp {{ number_format($repair->outsource_report['cost'] ?? 0, 0, ',', '.') }}</p>
                        @foreach($repair->outsource_report['files'] as $file)<a class="mt-2 block text-sm text-blue-600" href="{{ \Storage::disk('b2')->temporaryUrl($file, now()->addHour()) }}" target="_blank">Report Vendor {{ $loop->iteration }}</a>@endforeach
                    @endif
                </div>
            @endforeach
        </section>
    @empty<p class="text-sm text-gray-500">Belum ada riwayat perbaikan.</p>@endforelse
</div>
