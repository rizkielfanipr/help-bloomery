<x-filament-panels::page>
@php
    $status = $record->status;
    $nextStatuses = $this->nextStatusOptions();
    $fieldClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-gray-700 dark:bg-gray-900 dark:text-white';
    $isRejected = $status === \App\Enums\ItRequestStatus::Rejected;
    $timelineSteps = [
        [\App\Enums\ItRequestStatus::Submitted, 'heroicon-o-document-text'],
        [\App\Enums\ItRequestStatus::Review, 'heroicon-o-magnifying-glass'],
        [$isRejected ? \App\Enums\ItRequestStatus::Rejected : \App\Enums\ItRequestStatus::Approved, $isRejected ? 'heroicon-o-x-mark' : 'heroicon-o-check'],
        [\App\Enums\ItRequestStatus::Progress, 'heroicon-o-wrench-screwdriver'],
        [\App\Enums\ItRequestStatus::Completed, 'heroicon-o-check-badge'],
    ];
    $statusOrder = [
        \App\Enums\ItRequestStatus::Submitted->value => 0,
        \App\Enums\ItRequestStatus::Review->value => 1,
        \App\Enums\ItRequestStatus::Approved->value => 2,
        \App\Enums\ItRequestStatus::Rejected->value => 2,
        \App\Enums\ItRequestStatus::Progress->value => 3,
        \App\Enums\ItRequestStatus::Completed->value => 4,
    ];
    $currentOrder = $statusOrder[$status->value] ?? -1;
    $businessHours = app(\App\Services\ItBusinessHoursService::class);
    $slaBreakdowns = $this->getSlaBreakdowns();
@endphp

<div class="mx-auto w-full max-w-6xl space-y-5">
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $record->ticket_number }}</p>
                <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $record->module?->name ?? 'IT Request' }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $record->requestType?->name ?? 'Request' }} · {{ $record->branch?->name ?? 'No Branch' }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                <span @class([
                    'rounded-md border px-2.5 py-1 text-xs font-semibold',
                    'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' => $status === \App\Enums\ItRequestStatus::Submitted,
                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300' => $status === \App\Enums\ItRequestStatus::Review,
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300' => in_array($status, [\App\Enums\ItRequestStatus::Approved, \App\Enums\ItRequestStatus::Completed], true),
                    'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300' => $status === \App\Enums\ItRequestStatus::Progress,
                    'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300' => $status === \App\Enums\ItRequestStatus::Rejected,
                ])>{{ $status->getLabel() }}</span>
                <span class="text-xs text-gray-400">{{ $record->submitted_at?->format('d M Y, H:i') ?? 'Tanggal pengajuan tidak tersedia' }}</span>
            </div>
        </div>

        <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Requester</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->requester?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Branch</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->branch?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Request Type</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->requestType?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">ERP Module</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->module?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Priority</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ ucfirst($record->priority) }}</p></div>
        </div>

        <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Description</p>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->keterangan }}</p>
        </div>

        @if($record->it_notes && in_array($status, [\App\Enums\ItRequestStatus::Rejected, \App\Enums\ItRequestStatus::Completed], true))
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                <p class="whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->it_notes }}</p>
            </div>
        @endif

        @if($this->canFollowUp())
            @php $showNotesInput = in_array($status, [\App\Enums\ItRequestStatus::Review, \App\Enums\ItRequestStatus::Progress], true); @endphp
            <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                @if($showNotesInput)
                    <div class="grid gap-4">
                        <div>
                            <textarea wire:model="itNotes" rows="3" class="{{ $fieldClass }}" placeholder="{{ $status === \App\Enums\ItRequestStatus::Review ? 'Catatan (wajib diisi jika reject)...' : 'Catatan penyelesaian...' }}"></textarea>
                            @error('itNotes')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endif
                <div @class(['flex flex-wrap justify-end gap-2', 'mt-4' => $showNotesInput])>
                    @foreach($nextStatuses as $value => $label)
                        <button
                            wire:click="transitionTo('{{ $value }}')"
                            wire:loading.attr="disabled"
                            @class([
                                'inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold disabled:opacity-50',
                                'border-red-200 bg-white text-red-600 hover:bg-red-50 dark:border-red-900 dark:bg-gray-900' => $value === \App\Enums\ItRequestStatus::Rejected->value,
                                'border-blue-200 bg-white text-blue-600 hover:bg-blue-50 dark:border-blue-900 dark:bg-gray-900' => $value !== \App\Enums\ItRequestStatus::Rejected->value,
                            ])
                        ><x-heroicon-o-arrow-right class="h-4 w-4" />{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
            <div class="overflow-x-auto pb-1">
                <div class="flex min-w-[760px] items-start">
                    @foreach($timelineSteps as [$stepStatus, $icon])
                        @php
                            $isReached = $currentOrder >= ($statusOrder[$stepStatus->value] ?? 99);
                            $activity = $record->activities->last(fn ($item) => $item->to_status === $stepStatus->value && in_array($item->action, ['submitted', 'status_changed'], true));
                        @endphp
                        <div class="flex min-w-0 flex-1 items-start">
                            <div class="w-full text-center">
                                <div @class(['mx-auto flex h-9 w-9 items-center justify-center rounded-full border', 'border-red-200 bg-red-50 text-red-600 dark:border-red-900 dark:bg-red-950/30' => $isReached && $stepStatus === \App\Enums\ItRequestStatus::Rejected, 'border-blue-200 bg-blue-50 text-blue-600 dark:border-blue-900 dark:bg-blue-950/30' => $isReached && ! in_array($stepStatus, [\App\Enums\ItRequestStatus::Completed, \App\Enums\ItRequestStatus::Rejected], true), 'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-900 dark:bg-emerald-950/30' => $isReached && $stepStatus === \App\Enums\ItRequestStatus::Completed, 'border-gray-200 bg-gray-50 text-gray-400 dark:border-gray-700 dark:bg-gray-800' => ! $isReached])><x-dynamic-component :component="$icon" class="h-4 w-4" /></div>
                                <p @class(['mt-2 text-xs font-semibold', 'text-gray-800 dark:text-gray-200' => $isReached, 'text-gray-400' => ! $isReached])>{{ $stepStatus->getLabel() }}</p>
                                @if($activity)<p class="mt-1 text-[11px] text-gray-500">{{ $activity->created_at->format('d M Y, H:i') }}</p><p class="text-[11px] text-gray-400">oleh {{ $activity->actor?->name ?? 'System' }}</p>@else<p class="mt-1 text-[11px] text-gray-400">{{ $isReached && $stepStatus === \App\Enums\ItRequestStatus::Submitted ? $record->created_at->format('d M Y, H:i') : 'Belum diproses' }}</p>@endif
                            </div>
                            @if(! $loop->last)<div @class(['mt-4 h-px w-10 shrink-0', 'bg-blue-300 dark:bg-blue-800' => $currentOrder > $loop->index, 'bg-gray-200 dark:bg-gray-700' => $currentOrder <= $loop->index])></div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="space-y-4 rounded-xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-900">
        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">SLA ERP IT · Jam kerja</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Senin–Jumat 08.00–17.00 WIB. Waktu di luar jadwal tidak menambah durasi.</p>
        </div>
        <dl class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['Pengajuan', $record->submitted_at],
                ['Respons pertama IT', $record->first_responded_at],
                ['Selesai', $status === \App\Enums\ItRequestStatus::Completed ? $record->resolved_at : null],
            ] as [$label, $timestamp])
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $timestamp ? $timestamp->copy()->setTimezone(\App\Services\ItBusinessHoursService::TIMEZONE)->format('d M Y H:i:s').' WIB' : ($label === 'Pengajuan' ? 'Tidak tersedia' : 'Belum tercatat') }}</dd>
                </div>
            @endforeach
        </dl>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                <p class="text-xs text-gray-500 dark:text-gray-400">Durasi respons pertama</p>
                <p class="mt-1 font-semibold text-blue-700 dark:text-blue-300">{{ $record->response_business_seconds !== null ? $businessHours->formatDuration($record->response_business_seconds) : ($status === \App\Enums\ItRequestStatus::Submitted ? 'Belum direspons' : 'Tidak tersedia') }}</p>
            </div>
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                <p class="text-xs text-gray-500 dark:text-gray-400">Durasi penyelesaian</p>
                <p class="mt-1 font-semibold text-blue-700 dark:text-blue-300">{{ $record->resolution_business_seconds !== null ? $businessHours->formatDuration($record->resolution_business_seconds) : match ($status) { \App\Enums\ItRequestStatus::Completed => 'Tidak tersedia', \App\Enums\ItRequestStatus::Rejected => 'Ditolak', default => 'Belum selesai' } }}</p>
            </div>
        </div>
        @if (! in_array($status, [\App\Enums\ItRequestStatus::Completed, \App\Enums\ItRequestStatus::Rejected], true))
            <p class="text-xs text-gray-500 dark:text-gray-400">Umur tiket aktif: {{ $businessHours->formatDuration($businessHours->secondsBetween($record->submitted_at, now())) }} kerja sejak Submitted, diperbarui saat halaman dimuat atau tiket ditindaklanjuti.</p>
        @endif
        @foreach ($slaBreakdowns as $metric)
            <details class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-700">
                <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">{{ $metric['label'] }} · {{ $metric['duration'] }}</summary>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                        <thead><tr><th class="py-2 pr-3">Tanggal</th><th class="py-2 pr-3">Mulai dihitung (WIB)</th><th class="py-2 pr-3">Akhir dihitung (WIB)</th><th class="py-2">Durasi</th></tr></thead>
                        <tbody>
                            @forelse ($metric['days'] as $day)
                                <tr class="border-t border-gray-100 dark:border-gray-800"><td class="py-2 pr-3">{{ $day['date'] }}</td><td class="py-2 pr-3">{{ $day['start'] }}</td><td class="py-2 pr-3">{{ $day['end'] }}</td><td class="py-2">{{ $businessHours->formatDuration($day['seconds']) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-2">Tidak ada waktu yang beririsan dengan jam kerja. Durasi 0 tetap valid.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        @endforeach
        <x-erp-request.sla-explanation />
    </section>

    @if(! empty($record->attachments))
        <section class="rounded-xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-900"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Attachments</h2><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($record->attachments as $path)<a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank" class="rounded-lg border border-gray-200 p-3 text-sm font-semibold text-gray-700 hover:border-blue-300 hover:text-blue-600 dark:border-gray-700 dark:text-gray-300">↗ {{ basename($path) }}</a>@endforeach</div></section>
    @endif

    @if($record->activities->isNotEmpty())
        <section class="rounded-xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-900"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Activity History</h2><div class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">@foreach($record->activities as $activity)<div class="py-3 first:pt-0 last:pb-0"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ ucwords(str_replace('_', ' ', $activity->action)) }}</p><time class="text-xs text-gray-400">{{ $activity->created_at->format('d M Y, H:i') }}</time></div><p class="mt-1 text-xs text-gray-500">{{ $activity->actor?->name ?? 'System' }}@if($activity->from_status || $activity->to_status) · {{ $activity->from_status ?: 'New' }} → {{ $activity->to_status ?: '-' }}@endif</p>@if($activity->notes)<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $activity->notes }}</p>@endif</div>@endforeach</div></section>
    @endif
</div>
</x-filament-panels::page>
