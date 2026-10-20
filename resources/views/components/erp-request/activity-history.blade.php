@props([
    'activities',
    'compact' => false,
])

@php
    $warrantyClaims = $activities->where('action', 'warranty_claimed');
    $actionLabels = [
        'submitted' => 'Permintaan dikirim',
        'status_changed' => 'Status diperbarui',
        'warranty_claimed' => 'Keluhan setelah Warranty',
        'warranty_auto_completed' => 'Warranty selesai otomatis',
    ];
@endphp

<div class="space-y-4">
    @if($warrantyClaims->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-red-200 bg-red-50/70 dark:border-red-900 dark:bg-red-950/20">
            <div class="border-b border-red-200 px-4 py-3 dark:border-red-900">
                <p class="text-xs font-bold uppercase tracking-wider text-red-700 dark:text-red-300">Keluhan Setelah Warranty</p>
                <p class="mt-1 text-xs text-red-600/80 dark:text-red-400">Seluruh klaim ulang tersimpan dan tetap dapat ditinjau.</p>
            </div>
            <div class="divide-y divide-red-200/70 dark:divide-red-900/70">
                @foreach($warrantyClaims as $claim)
                    <div class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-red-700 dark:text-red-300">{{ $claim->actor?->name ?? 'Pemohon' }}</p>
                            <time class="text-[11px] text-red-500 dark:text-red-400">{{ $claim->created_at->format('d M Y, H:i') }}</time>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-900 dark:text-red-200">{{ $claim->notes ?: 'Tidak ada rincian keluhan.' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div>
        <p class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Log Aktivitas</p>
        @if($activities->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-xs text-gray-400 dark:border-gray-700">Belum ada log aktivitas.</div>
        @else
            <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-900">
                @foreach($activities as $activity)
                    @php
                        $fromStatus = $activity->from_status ? App\Enums\ItRequestStatus::tryFrom($activity->from_status)?->getLabel() : null;
                        $toStatus = $activity->to_status ? App\Enums\ItRequestStatus::tryFrom($activity->to_status)?->getLabel() : null;
                    @endphp
                    <div @class(['px-4', 'py-3' => $compact, 'py-4' => ! $compact])>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $actionLabels[$activity->action] ?? ucwords(str_replace('_', ' ', $activity->action)) }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $activity->actor?->name ?? 'System' }}@if($fromStatus || $toStatus) · {{ $fromStatus ?: 'Baru' }} → {{ $toStatus ?: '-' }}@endif</p>
                            </div>
                            <time class="shrink-0 text-[11px] text-gray-400">{{ $activity->created_at->format('d M Y, H:i') }}</time>
                        </div>
                        @if($activity->notes)
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $activity->notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
