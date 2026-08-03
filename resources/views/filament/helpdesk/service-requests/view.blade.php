<x-filament-panels::page>
@php
    $status = $record->status;
    $attachments = $record->attachments ?? [];
@endphp
<x-helpdesk.desktop-detail-shell
    :code="'SR-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)"
    title="Permintaan Service"
    :subtitle="($record->scheduledBy?->name ?? 'Unknown').' · '.($record->requestor_notes ?: 'Tanpa catatan')"
    :status="$status->getLabel()"
    :status-color="$status->getColor()"
    meta-label="Tanggal Penjadwalan"
    :meta-value="$record->scheduled_date?->format('d M Y') ?? '-'"
>
    <x-slot:main>
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="mb-5 text-base font-bold text-gray-900 dark:text-white">Detail Permintaan</h2>
            <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-xs text-gray-400">Pemohon</dt><dd class="mt-1 font-semibold">{{ $record->scheduledBy?->name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Teknisi</dt><dd class="mt-1 font-semibold">{{ $record->technician?->name ?? 'Belum ditugaskan' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Garansi</dt><dd class="mt-1 font-semibold">{{ $record->warranty_expires_at?->format('d M Y, H:i') ?? '-' }}</dd></div>
            </dl>
            <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Catatan Pemohon</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->requestor_notes ?: '-' }}</p>
            </div>
        </section>

        @if($attachments)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <h2 class="mb-4 font-bold">Lampiran</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($attachments as $path)
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank" class="rounded-xl border border-gray-200 p-3 text-sm font-medium hover:border-blue-400 dark:border-gray-700">
                            ↗ {{ basename($path) }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="mb-5 font-bold">Riwayat Perbaikan</h2>
            <div class="space-y-4">
                @forelse($record->repairs as $repair)
                    <article class="rounded-xl bg-gray-50 p-5 dark:bg-gray-800/60">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold">{{ $repair->cycle_label }}</p>
                            <span class="text-xs text-gray-400">{{ $repair->completed_at ? 'Selesai '.$repair->completed_at->format('d M Y, H:i') : 'Sedang dikerjakan' }}</span>
                        </div>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div><p class="text-xs text-gray-400">Teknisi</p><p class="text-sm font-medium">{{ $repair->technician?->name ?? '-' }}</p></div>
                            <div><p class="text-xs text-gray-400">Mulai</p><p class="text-sm font-medium">{{ $repair->started_at?->format('d M Y, H:i') ?? '-' }}</p></div>
                            <div><p class="text-xs text-gray-400">Kondisi Awal</p><p class="text-sm">{{ $repair->before_notes ?: '-' }}</p></div>
                            <div><p class="text-xs text-gray-400">Hasil Perbaikan</p><p class="text-sm">{{ $repair->after_notes ?: '-' }}</p></div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            @foreach(['before_photos' => 'Sebelum', 'after_photos' => 'Sesudah'] as $field => $label)
                                @foreach($repair->{$field} as $photo)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" target="_blank" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($photo, now()->addHour()) }}" class="h-44 w-full object-cover">
                                        <p class="p-2 text-center text-xs font-medium">{{ $label }}</p>
                                    </a>
                                @endforeach
                            @endforeach
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-gray-400">Belum ada proses perbaikan.</p>
                @endforelse
            </div>
        </section>
    </x-slot:main>

    <x-slot:aside>
        <section class="rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 p-5 text-white shadow-lg shadow-blue-900/15">
            <p class="text-xs text-blue-100">Progress</p>
            <div class="mt-4 space-y-3">
                @foreach([\App\Enums\ServiceRequestStatus::Submitted, \App\Enums\ServiceRequestStatus::InProgress, \App\Enums\ServiceRequestStatus::Warranty, \App\Enums\ServiceRequestStatus::Completed] as $step)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="h-2.5 w-2.5 rounded-full {{ $status === $step ? 'bg-white ring-4 ring-white/20' : 'bg-blue-400/60' }}"></span>
                        <span class="{{ $status === $step ? 'font-semibold text-white' : 'text-blue-100/70' }}">{{ $step->getLabel() }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </x-slot:aside>
</x-helpdesk.desktop-detail-shell>
</x-filament-panels::page>
