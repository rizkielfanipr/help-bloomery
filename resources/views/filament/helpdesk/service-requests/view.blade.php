<x-filament-panels::page>
@php
    $status = $record->status;
    $attachments = $record->attachments ?? [];
    $isResubmitted = $status === \App\Enums\ServiceRequestStatus::ReSubmitted;
    $timelineSteps = [
        [\App\Enums\ServiceRequestStatus::Submitted, 'heroicon-o-paper-airplane'],
        [\App\Enums\ServiceRequestStatus::InProgress, 'heroicon-o-wrench-screwdriver'],
        [$isResubmitted ? \App\Enums\ServiceRequestStatus::ReSubmitted : \App\Enums\ServiceRequestStatus::Warranty, $isResubmitted ? 'heroicon-o-arrow-path' : 'heroicon-o-shield-check'],
        [\App\Enums\ServiceRequestStatus::Completed, 'heroicon-o-check-badge'],
    ];
    $statusOrder = [
        \App\Enums\ServiceRequestStatus::Submitted->value => 0,
        \App\Enums\ServiceRequestStatus::InProgress->value => 1,
        \App\Enums\ServiceRequestStatus::Warranty->value => 2,
        \App\Enums\ServiceRequestStatus::ReSubmitted->value => 2,
        \App\Enums\ServiceRequestStatus::Completed->value => 3,
    ];
    $currentOrder = $statusOrder[$status->value] ?? -1;
@endphp

<div class="mx-auto w-full max-w-6xl space-y-5">
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $record->code ?: ('SR-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)) }}</p>
                <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">Permintaan Service</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $record->scheduledBy?->name ?? 'Pemohon' }} · {{ $record->scheduled_date?->format('d M Y') ?? '-' }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-1 sm:items-end">
                <span @class([
                    'rounded-md border px-2.5 py-1 text-xs font-semibold',
                    'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' => $status === \App\Enums\ServiceRequestStatus::Submitted,
                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300' => $status === \App\Enums\ServiceRequestStatus::InProgress,
                    'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-900 dark:bg-purple-950/30 dark:text-purple-300' => $status === \App\Enums\ServiceRequestStatus::Warranty,
                    'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300' => $status === \App\Enums\ServiceRequestStatus::ReSubmitted,
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300' => $status === \App\Enums\ServiceRequestStatus::Completed,
                ])>{{ $status->getLabel() }}</span>
                <span class="text-xs text-gray-400">{{ $record->created_at?->format('d M Y, H:i') }}</span>
            </div>
        </div>

        <div class="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pemohon</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->scheduledBy?->name ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Teknisi</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->technician?->name ?? 'Belum ditugaskan' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Tanggal Penjadwalan</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->scheduled_date?->format('d M Y') ?? '-' }}</p></div>
            <div><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Garansi Hingga</p><p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $record->warranty_expires_at?->format('d M Y H:i') ?? '-' }}</p></div>
        </div>

        <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Catatan Pemohon</p>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->requestor_notes ?: '-' }}</p>
        </div>

        @if($record->status === \App\Enums\ServiceRequestStatus::Warranty)
            <div class="flex items-center justify-end border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                <button
                    wire:click="mountAction('klaim_garansi')"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-4 py-2 text-sm font-semibold text-amber-600 hover:bg-amber-50 dark:border-amber-900 dark:bg-gray-900"
                >
                    <x-heroicon-o-arrow-path class="h-4 w-4" />
                    Klaim Garansi
                </button>
            </div>
        @endif

        <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
            <div class="overflow-x-auto pb-1">
                <div class="flex min-w-[640px] items-start">
                    @foreach($timelineSteps as [$stepStatus, $icon])
                        @php
                            $isReached = $currentOrder >= ($statusOrder[$stepStatus->value] ?? 99);
                        @endphp
                        <div class="flex min-w-0 flex-1 items-start">
                            <div class="w-full text-center">
                                <div @class([
                                    'mx-auto flex h-9 w-9 items-center justify-center rounded-full border',
                                    'border-blue-200 bg-blue-50 text-blue-600 dark:border-blue-900 dark:bg-blue-950/30' => $isReached && ! in_array($stepStatus, [\App\Enums\ServiceRequestStatus::Completed, \App\Enums\ServiceRequestStatus::ReSubmitted], true),
                                    'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-900 dark:bg-emerald-950/30' => $isReached && $stepStatus === \App\Enums\ServiceRequestStatus::Completed,
                                    'border-red-200 bg-red-50 text-red-600 dark:border-red-900 dark:bg-red-950/30' => $isReached && $stepStatus === \App\Enums\ServiceRequestStatus::ReSubmitted,
                                    'border-gray-200 bg-gray-50 text-gray-400 dark:border-gray-700 dark:bg-gray-800' => ! $isReached,
                                ])>
                                    <x-dynamic-component :component="$icon" class="h-4 w-4" />
                                </div>
                                <p @class(['mt-2 text-xs font-semibold', 'text-gray-800 dark:text-gray-200' => $isReached, 'text-gray-400' => ! $isReached])>{{ $stepStatus->getLabel() }}</p>
                                <p class="mt-1 text-[11px] text-gray-400">
                                    {{ $isReached && $stepStatus === \App\Enums\ServiceRequestStatus::Submitted ? ($record->created_at?->format('d M Y, H:i') ?? '-') : ($isReached ? 'Selesai' : 'Belum diproses') }}
                                </p>
                            </div>
                            @if(! $loop->last)
                                <div @class(['mt-4 h-px w-10 shrink-0', 'bg-blue-300 dark:bg-blue-800' => $currentOrder > $loop->index, 'bg-gray-200 dark:bg-gray-700' => $currentOrder <= $loop->index])></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if(! empty($attachments))
        <section class="rounded-xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Lampiran</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($attachments as $path)
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank" class="rounded-lg border border-gray-200 p-3 text-sm font-semibold text-gray-700 hover:border-blue-300 hover:text-blue-600 dark:border-gray-700 dark:text-gray-300">
                        ↗ {{ basename($path) }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Hasil Tindak Lanjut & Riwayat Perbaikan</h2>
            @if($record->repairs->isNotEmpty())
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                    {{ $record->repairs->count() }} Tahap
                </span>
            @endif
        </div>

        @if($record->repairs->isEmpty())
            <div class="mt-4 rounded-xl border border-dashed border-gray-200 p-6 text-center dark:border-gray-700">
                <x-heroicon-o-wrench-screwdriver class="mx-auto h-8 w-8 text-gray-400" />
                <p class="mt-2 text-sm font-medium text-gray-600 dark:text-gray-400">Belum ada tindak lanjut dari teknisi</p>
                <p class="mt-1 text-xs text-gray-400">Hasil pemeriksaan dan pekerjaan teknisi akan tampil di sini setelah pekerjaan dimulai.</p>
            </div>
        @else
            <div class="mt-4 space-y-4">
                @foreach($record->repairs as $repair)
                    <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 dark:border-gray-700 dark:bg-gray-800/40">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200/80 pb-3 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 font-mono text-xs font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                    #{{ $repair->cycle }}
                                </span>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $repair->cycle_label }}</p>
                            </div>
                            @if($repair->completed_at)
                                <span class="rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300">Selesai Dikerjakan</span>
                            @else
                                <span class="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300">Sedang Dikerjakan</span>
                            @endif
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Teknisi</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $repair->technician?->name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Waktu Mulai</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $repair->started_at?->format('d M Y, H:i') ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Waktu Selesai</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $repair->completed_at?->format('d M Y, H:i') ?? 'Sedang dikerjakan...' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Masa Garansi</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $repair->warranty_expires_at?->format('d M Y') ?? '-' }}</p>
                            </div>
                        </div>

                        @if($repair->before_notes || ! empty($repair->before_photos))
                            <div class="mt-4 rounded-lg border border-amber-200/80 bg-amber-50/60 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
                                <p class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Catatan Kondisi Awal (Sebelum Perbaikan)</p>
                                <p class="mt-1.5 text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $repair->before_notes ?: 'Tidak ada catatan awal' }}</p>
                                
                                @if(! empty($repair->before_photos))
                                    <div class="mt-3 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                        @foreach($repair->before_photos as $photo)
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" target="_blank" class="group relative block aspect-[4/3] overflow-hidden rounded-lg border border-amber-200/80 bg-white dark:border-gray-700 dark:bg-gray-900">
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" class="h-full w-full object-cover transition duration-200 group-hover:scale-105" alt="Foto Sebelum">
                                                <div class="absolute inset-0 bg-black/0 transition duration-200 group-hover:bg-black/10"></div>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($repair->after_notes || ! empty($repair->after_photos))
                            <div class="mt-4 rounded-lg border border-emerald-200/80 bg-emerald-50/60 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Hasil & Catatan Akhir (Setelah Perbaikan)</p>
                                <p class="mt-1.5 text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $repair->after_notes ?: 'Tidak ada catatan akhir' }}</p>
                                
                                @if(! empty($repair->after_photos))
                                    <div class="mt-3 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                        @foreach($repair->after_photos as $photo)
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" target="_blank" class="group relative block aspect-[4/3] overflow-hidden rounded-lg border border-emerald-200/80 bg-white dark:border-gray-700 dark:bg-gray-900">
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" class="h-full w-full object-cover transition duration-200 group-hover:scale-105" alt="Foto Sesudah">
                                                <div class="absolute inset-0 bg-black/0 transition duration-200 group-hover:bg-black/10"></div>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
</x-filament-panels::page>
